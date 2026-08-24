<?php
/**
 * cleanup.php — Deletes expired files from Google Drive AND removes them from files.json.
 *
 * A file expires when:
 *   - Never downloaded: EXPIRY_DAYS days after upload_at
 *   - Downloaded before: EXPIRY_DAYS days after last_downloaded_at
 *
 * A record is only removed from the database AFTER Google Drive confirms deletion.
 * If Drive deletion fails, the record is flagged as `pending_deletion = true` and
 * retried on every subsequent cleanup run — no orphans are ever left on Drive.
 *
 * Triggered automatically (~5% of download page visits) or manually:
 *   https://freenetly.com/cleanup.php?token=YOUR_CLEANUP_TOKEN
 *
 * Cron (daily at 2am):
 *   0 2 * * * curl -s "https://yourdomain.com/cleanup.php?token=YOUR_TOKEN" > /dev/null
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/drive_api.php';

// ── Auth ──────────────────────────────────────────────────────────────────────

$token = trim($_GET['token'] ?? $_SERVER['HTTP_X_CLEANUP_TOKEN'] ?? '');
if (!hash_equals(CLEANUP_TOKEN, $token)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

set_time_limit(300);
header('Content-Type: application/json');

// ── Get expired files from database ───────────────────────────────────────────

$expiredFiles = getExpiredFiles();

if (empty($expiredFiles)) {
    exit(json_encode(['deleted' => 0, 'kept' => 0, 'retried' => 0, 'message' => 'No expired files found.']));
}

// ── Build account map ─────────────────────────────────────────────────────────

$accounts = getDriveAccounts();
$accountMap = [];
foreach ($accounts as $idx => $acc) {
    $accountMap[$idx] = $acc;
}

// ── Process expired files ─────────────────────────────────────────────────────

$deleted     = [];
$retryFailed = [];

foreach ($expiredFiles as $record) {
    $expiryDays = max(1, (int)($record['expires_after_days'] ?? EXPIRY_DAYS));
    $pendingRetry = !empty($record['pending_deletion']);

    // Calculate expiry reason and age
    if (!$pendingRetry) {
        $neverDownloaded = empty($record['last_downloaded_at']);
        $baseDate = $neverDownloaded
            ? ($record['uploaded_at'] ?? null)
            : $record['last_downloaded_at'];
        
        $ageInDays = (int) floor((time() - strtotime($baseDate)) / 86400);
        $expiryReason = $neverDownloaded ? 'never_downloaded' : 'inactive_since_last_download';
    } else {
        $expiryReason = $record['pending_deletion_reason'] ?? 'retry';
        $ageInDays = (int) floor((time() - strtotime($record['uploaded_at'] ?? 'now')) / 86400);
    }

    // ── Delete from Google Drive first ────────────────────────────────────────

    $driveFileId = $record['drive_file_id'] ?? null;
    $accountIdx = (int)($record['account_index'] ?? 0);
    $driveResult = 'skipped';

    if (!$driveFileId) {
        $driveResult = 'no_drive_id';
    } elseif (!isset($accountMap[$accountIdx])) {
        $driveResult = tryDeleteFromAnyAccount($driveFileId, $accountMap);
    } else {
        $driveResult = tryDeleteFromAccount($driveFileId, $accountMap[$accountIdx]);
    }

    $driveSuccess = in_array($driveResult, ['deleted', 'already_gone', 'no_drive_id'], true);

    if ($driveSuccess) {
        // ── Confirmed gone from Drive → remove from DB ──────────────────────
        deleteFile($record['id']);
        
        $deleted[] = [
            'id'           => $record['id'],
            'name'         => $record['original_name'] ?? '',
            'age_days'     => $ageInDays,
            'reason'       => $expiryReason,
            'drive_result' => $driveResult,
        ];
    } else {
        // ── Drive deletion failed → flag and keep in DB for next retry ───────
        updateFile($record['id'], [
            'pending_deletion' => 1,
        ]);

        $retryFailed[] = [
            'id'    => $record['id'],
            'name'  => $record['original_name'] ?? '',
            'error' => $driveResult,
        ];
    }
}

// ── Respond ───────────────────────────────────────────────────────────────────

$totalFilesRemaining = getFileCount();

echo json_encode([
    'deleted'      => count($deleted),
    'kept'         => $totalFilesRemaining,
    'retry_failed' => count($retryFailed),
    'files'        => $deleted,
    'pending'      => $retryFailed,
], JSON_PRETTY_PRINT);

// ── Drive deletion helpers ────────────────────────────────────────────────────

/** Attempt deletion using the owning account. Returns a result string. */
function tryDeleteFromAccount(string $driveFileId, array $account): string {
    try {
        $drive = new DriveAPI($account);
        $drive->authenticate();
        $ok = $drive->deleteFile($driveFileId);
        return $ok ? 'deleted' : 'already_gone';
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
}

/**
 * Owning account is gone — try every configured account until one succeeds.
 * Useful when credentials were swapped or removed after the file was uploaded.
 */
function tryDeleteFromAnyAccount(string $driveFileId, array $accountMap): string {
    if (empty($accountMap)) {
        return 'no_accounts_configured';
    }
    $lastError = 'all_accounts_failed';
    foreach ($accountMap as $account) {
        $result = tryDeleteFromAccount($driveFileId, $account);
        if (in_array($result, ['deleted', 'already_gone'], true)) {
            return $result;
        }
        $lastError = $result;
    }
    return $lastError;
}


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/drive_api.php';

// ── Auth ──────────────────────────────────────────────────────────────────────

$token = trim($_GET['token'] ?? $_SERVER['HTTP_X_CLEANUP_TOKEN'] ?? '');
if (!hash_equals(CLEANUP_TOKEN, $token)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

// Prevent timeout on large datasets
set_time_limit(300);
header('Content-Type: application/json');
