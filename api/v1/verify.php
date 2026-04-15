<?php
/**
 * API Batch File Verification Endpoint
 * POST /api/v1/verify - Check if multiple files exist
 * 
 * Optimized for large-scale synchronization operations.
 * Supports up to 2 million files per request with automatic database query chunking.
 * 
 * Request body (JSON):
 * {
 *   "file_ids": ["abc123def456", "xyz789ghi012", ...]
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "results": {
 *     "abc123def456": {"exists": true, "filename": "example.pdf", "size": 1024000},
 *     "xyz789ghi012": {"exists": false}
 *   },
 *   "summary": {
 *     "total_checked": 2,
 *     "exists": 1,
 *     "missing": 1
 *   }
 * }
 * 
 * Performance:
 * - Memory: Allocates up to 2GB for very large batches
 * - Timeout: 5 minutes max execution time
 * - Database: Queries in chunks of 10,000 IDs for optimal performance
 */

// Set CORS headers FIRST
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
header('Access-Control-Max-Age: 3600');
header('Content-Type: application/json');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../api_auth.php';

function jsonError(string $message, int $code = 400): void {
    global $apiAuth;
    if (isset($apiAuth)) {
        $apiAuth->logUsage('/api/v1/verify', $code);
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function jsonSuccess(array $data): void {
    global $apiAuth;
    if (isset($apiAuth)) {
        $apiAuth->logUsage('/api/v1/verify', 200);
    }
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

try {
    // Authenticate API request
    $apiAuth = new APIAuth();
    if (!$apiAuth->authenticate()) {
        exit;
    }

    // Increase limits for large batch operations
    ini_set('memory_limit', '2048M');  // 2GB memory for large batches
    set_time_limit(300);  // 5 minutes max execution time

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed. Use POST.', 405);
    }

    // Get JSON body
    $jsonBody = file_get_contents('php://input');
    $data = json_decode($jsonBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonError('Invalid JSON in request body');
    }

    // Validate file_ids parameter
    if (!isset($data['file_ids']) || !is_array($data['file_ids'])) {
        jsonError('Missing or invalid "file_ids" parameter. Expected array of file IDs.');
    }

    $fileIds = $data['file_ids'];
    
    // Limit batch size to prevent abuse (increased for large-scale sync operations)
    $maxBatchSize = 2000000;  // 2 million files max per request
    if (count($fileIds) > $maxBatchSize) {
        jsonError("Too many file IDs. Maximum batch size is {$maxBatchSize}.");
    }

    if (count($fileIds) === 0) {
        jsonError('Empty file_ids array provided.');
    }

    // Sanitize file IDs
    $sanitizedIds = [];
    foreach ($fileIds as $fileId) {
        if (!is_string($fileId)) {
            continue;
        }
        $clean = preg_replace('/[^a-f0-9]/i', '', $fileId);
        if (strlen($clean) === 12) {
            $sanitizedIds[] = $clean;
        }
    }

    if (empty($sanitizedIds)) {
        jsonError('No valid file IDs provided. File IDs must be 12 character hexadecimal strings.');
    }

    // Query database in chunks for efficiency with large datasets
    $db = getDB();
    $chunkSize = 10000;  // Process 10k IDs at a time to avoid SQL limits
    $foundFiles = [];
    
    // Split IDs into chunks
    $chunks = array_chunk($sanitizedIds, $chunkSize);
    
    foreach ($chunks as $chunk) {
        $placeholders = str_repeat('?,', count($chunk) - 1) . '?';
        $sql = "SELECT id, filename, size, upload_date 
                FROM files 
                WHERE id IN ($placeholders)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($chunk);
        $chunkResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Merge chunk results
        $foundFiles = array_merge($foundFiles, $chunkResults);
    }

    // Build results array
    $results = [];
    $existingIds = [];
    
    foreach ($foundFiles as $file) {
        $existingIds[] = $file['id'];
        $results[$file['id']] = [
            'exists' => true,
            'filename' => $file['filename'],
            'size' => (int)$file['size'],
            'upload_date' => $file['upload_date']
        ];
    }

    // Add missing files to results
    foreach ($sanitizedIds as $fileId) {
        if (!in_array($fileId, $existingIds)) {
            $results[$fileId] = [
                'exists' => false
            ];
        }
    }

    // Calculate summary
    $summary = [
        'total_checked' => count($sanitizedIds),
        'exists' => count($existingIds),
        'missing' => count($sanitizedIds) - count($existingIds)
    ];

    jsonSuccess([
        'results' => $results,
        'summary' => $summary
    ]);

} catch (PDOException $e) {
    error_log("OneNetly API verify error: " . $e->getMessage());
    jsonError('Database error occurred', 500);
} catch (Exception $e) {
    error_log("OneNetly API verify error: " . $e->getMessage());
    jsonError('An error occurred: ' . $e->getMessage(), 500);
}
