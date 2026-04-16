<?php
/**
 * stream.php — Proxy download endpoint with automatic bandwidth failover.
 * Streams files from Google Drive through your server, hiding the Drive backend.
 * If bandwidth limit is hit, automatically copies file to another account.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/drive_api.php';

set_time_limit(0);
ini_set('memory_limit', '768M');

// Disable all output buffering for clean binary streaming
while (ob_get_level()) {
    ob_end_clean();
}

// Disable implicit flush to prevent corruption
ob_implicit_flush(false);

// Silent error logging (no output, only to log file)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/stream_debug.log');

// Log access for debugging
@error_log(sprintf('[%s] stream.php: ID=%s, IP=%s', date('Y-m-d H:i:s'), $_GET['id'] ?? 'none', $_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// ── CORS Headers for Secure Downloads ────────────────────────────────────────

// Allow downloads from any origin (required for HTTPS downloads)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Range');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Content-Disposition');
header('Access-Control-Max-Age: 3600');

// Handle CORS preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────


function getNextAccountIndex(int $currentIdx, int $totalAccounts): int {
    return ($currentIdx + 1) % $totalAccounts;
}

/**
 * Try to stream file from Drive. Returns HTTP code.
 */
function tryStreamFile(string $accessToken, string $driveFileId, string $fileName, string $mimeType, int $fileSize): int {
    $downloadUrl = 'https://www.googleapis.com/drive/v3/files/' . urlencode($driveFileId) . '?alt=media';
    
    $ch = curl_init($downloadUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    // Write output directly to php://output without buffering
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) {
        echo $data;
        return strlen($data);
    });
    
    $result = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return $httpCode;
}

/**
 * Check if error is bandwidth/quota related.
 */
function isBandwidthError(int $httpCode): bool {
    // 403 = Forbidden (quota exceeded), 429 = Too Many Requests
    return in_array($httpCode, [403, 429], true);
}

/**
 * Copy file to another account and update database.
 */
function migrateFileToNewAccount(array $found, int $currentAccountIdx, array $accounts): ?array {
    $totalAccounts = count($accounts);
    if ($totalAccounts < 2) {
        return null; // No other accounts available
    }
    
    // Try each account in sequence
    $tried = 0;
    $nextIdx = getNextAccountIndex($currentAccountIdx, $totalAccounts);
    
    while ($tried < $totalAccounts - 1) {
        if ($nextIdx === $currentAccountIdx) {
            $nextIdx = getNextAccountIndex($nextIdx, $totalAccounts);
            continue;
        }
        
        $targetAccount = $accounts[$nextIdx];
        
        try {
            // Authenticate with target account
            $targetDrive = new DriveAPI($targetAccount);
            $targetDrive->authenticate();
            
            // Copy file from source to target account
            $newFileId = $targetDrive->copyFileFromUrl(
                $found['drive_file_id'],
                $found['original_name'],
                $targetAccount['folder_id'] ?? null
            );
            
            // Make new file public
            $targetDrive->makePublic($newFileId);
            
            // Store old file info for deletion
            $oldDriveFileId = $found['drive_file_id'];
            $sourceAccount = $accounts[$currentAccountIdx];
            
            // Update database record
            if (updateFile($found['id'], [
                'drive_file_id' => $newFileId,
                'account_index' => $nextIdx
            ])) {
                // Delete old file from original account to save storage
                try {
                    $sourceDrive = new DriveAPI($sourceAccount);
                    $sourceDrive->authenticate();
                    $sourceDrive->deleteFile($oldDriveFileId);
                } catch (Exception $e) {
                    // Ignore deletion errors - migration was successful
                    error_log('Failed to delete old file from source account: ' . $e->getMessage());
                }
                
                return [
                    'drive_file_id' => $newFileId,
                    'account_index' => $nextIdx,
                    'account' => $targetAccount
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            // Try next account
            $nextIdx = getNextAccountIndex($nextIdx, $totalAccounts);
            $tried++;
        }
    }
    
    return null; // All accounts failed
}

// ── Validate ID ───────────────────────────────────────────────────────────────

$id = trim($_GET['id'] ?? '');

if (empty($id) || !preg_match('/^[a-f0-9]{12}$/', $id)) {
    http_response_code(400);
    die('Invalid download link.');
}

// ── Find record ───────────────────────────────────────────────────────────────

$found = getFileById($id);

if ($found === null) {
    http_response_code(404);
    die('File not found or link has expired.');
}

// ── Get access token and try streaming ────────────────────────────────────────

$accounts    = getDriveAccounts();
$accountIdx  = (int)($found['account_index'] ?? 0);
$account     = $accounts[$accountIdx] ?? $accounts[0];

try {
    $drive = new DriveAPI($account);
    $drive->authenticate();
    $accessToken = $drive->getAccessToken();
} catch (Exception $e) {
    http_response_code(500);
    die('Unable to authenticate with storage provider.');
}

$driveFileId = $found['drive_file_id'];
$fileName    = $found['original_name'];
$fileSize    = (int)($found['size'] ?? 0);
$mimeType    = $found['mime'] ?? 'application/octet-stream';

// Determine Content-Disposition based on file type and request
$forceDownload = isset($_GET['download']);

// File types that should be displayed inline (in browser)
$inlineMimeTypes = [
    'image/',       // All images
    'video/',       // All videos
    'audio/',       // All audio
    'application/pdf',
    'text/',        // All text files
    'application/json',
    'application/xml'
];

// Check if mime type should be inline
$shouldBeInline = false;
foreach ($inlineMimeTypes as $prefix) {
    if (strpos($mimeType, $prefix) === 0 || $mimeType === $prefix) {
        $shouldBeInline = true;
        break;
    }
}

$disposition = ($shouldBeInline && !$forceDownload) ? 'inline' : 'attachment';

// Set headers BEFORE any output
header('Content-Type: ' . $mimeType);
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($fileName) . '"');
if ($fileSize > 0) {
    header('Content-Length: ' . $fileSize);
}
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

// Try to stream from current account (no output after this point)
$httpCode = tryStreamFile($accessToken, $driveFileId, $fileName, $mimeType, $fileSize);

// If bandwidth error, migrate to another account and retry
if (isBandwidthError($httpCode) && count($accounts) > 1) {
    // Clear any output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Attempt migration
    $migrationResult = migrateFileToNewAccount($found, $accountIdx, $accounts);
    
    if ($migrationResult !== null) {
        // Migration successful, get new access token
        try {
            $newDrive = new DriveAPI($migrationResult['account']);
            $newDrive->authenticate();
            $newAccessToken = $newDrive->getAccessToken();
            
            // Reset headers
            header('Content-Type: ' . $mimeType);
            $disposition = ($isImageMime && !$forceDownload) ? 'inline' : 'attachment';
            header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($fileName) . '"');
            if ($fileSize > 0) {
                header('Content-Length: ' . $fileSize);
            }
            header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
            header('X-Content-Type-Options: nosniff');
            
            // Retry streaming from new account
            $httpCode = tryStreamFile($newAccessToken, $migrationResult['drive_file_id'], $fileName, $mimeType, $fileSize);
            
            if ($httpCode !== 200) {
                http_response_code(500);
                die('Failed to retrieve file from backup account.');
            }
        } catch (Exception $e) {
            http_response_code(500);
            die('Migration successful but failed to stream from new account.');
        }
    } else {
        http_response_code(503);
        die('Bandwidth limit exceeded and no alternative accounts available.');
    }
} elseif ($httpCode !== 200) {
    http_response_code(500);
    die('Failed to retrieve file.');
}

exit;
