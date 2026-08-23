<?php
/**
 * Google Drive API Helper (OAuth2 Refresh Token, no Composer needed)
 * Handles token refresh + Drive v3 resumable uploads.
 */

class DriveAPI {

    private string $accessToken = '';
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private string $accountId;

    public function __construct(array $account) {
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (empty($account[$key])) {
                throw new RuntimeException("Missing '$key' in Drive account config.");
            }
        }
        $this->clientId     = $account['client_id'];
        $this->clientSecret = $account['client_secret'];
        $this->refreshToken = $account['refresh_token'];
        $this->accountId    = $account['id'] ?? 'unknown';
    }

    // -----------------------------------------------------------------------
    // Auth
    // -----------------------------------------------------------------------

    /** Exchange the refresh token for a short-lived access token. */
    public function authenticate(): void {
        $response = $this->curlPost('https://oauth2.googleapis.com/token', http_build_query([
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        $data = json_decode($response['body'], true);

        if (empty($data['access_token'])) {
            if ((int)$response['http_code'] === 0) {
                // curl never reached Google at all (DNS/TLS/firewall/no outbound internet)
                $msg = $response['curl_error'] !== '' ? $response['curl_error'] : 'Could not connect to oauth2.googleapis.com (network/DNS/TLS issue on the server)';
            } else {
                $msg = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
            }
            // Include raw body so the developer can see what Google actually returned
            throw new RuntimeException("Google OAuth2 auth failed: $msg | HTTP {$response['http_code']} | Raw: {$response['body']}");
        }

        $this->accessToken = $data['access_token'];
    }

    // -----------------------------------------------------------------------
    // Upload
    // -----------------------------------------------------------------------

    /**
     * Upload a file to Google Drive using resumable upload (supports large files).
     *
     * @param  string      $filePath     Absolute path to the temp file.
     * @param  string      $fileName     Original file name.
     * @param  string      $mimeType     MIME type of the file.
     * @param  string|null $folderId     Drive folder ID or null for root.
     * @return array       ['id' => ..., 'name' => ..., 'size' => ...]
     */
    public function uploadFile(string $filePath, string $fileName, string $mimeType, ?string $folderId = null): array {
        if (empty($this->accessToken)) {
            $this->authenticate();
        }

        $fileSize = filesize($filePath);

        // --- Metadata ---
        $metadata = ['name' => $fileName];
        if ($folderId) {
            $metadata['parents'] = [$folderId];
        }

        // --- Initiate resumable session ---
        $initResponse = $this->curlPost(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable',
            json_encode($metadata),
            [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json; charset=UTF-8',
                'X-Upload-Content-Type: ' . $mimeType,
                'X-Upload-Content-Length: ' . $fileSize,
            ],
            true // return headers
        );

        if ($initResponse['http_code'] !== 200) {
            throw new RuntimeException('Failed to initiate resumable upload. HTTP ' . $initResponse['http_code']);
        }

        // Extract session URI from Location header
        if (!preg_match('/^Location:\s*(.+)$/mi', $initResponse['headers'], $m)) {
            throw new RuntimeException('No Location header in resumable upload initiation response.');
        }
        $uploadUri = trim($m[1]);

        // --- Stream file in chunks ---
        $chunkSize  = 16 * 1024 * 1024; // 16 MB chunks for smoother large file uploads
        $handle     = fopen($filePath, 'rb');
        $uploaded   = 0;
        $finalBody  = '';

        while (!feof($handle)) {
            $chunk     = fread($handle, $chunkSize);
            $chunkLen  = strlen($chunk);
            $rangeEnd  = $uploaded + $chunkLen - 1;

            $ch = curl_init($uploadUri);
            curl_setopt_array($ch, [
                CURLOPT_PUT            => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Range: bytes ' . $uploaded . '-' . $rangeEnd . '/' . $fileSize,
                    'Content-Length: ' . $chunkLen,
                ],
                CURLOPT_POSTFIELDS     => $chunk,
                CURLOPT_SSL_VERIFYPEER => self::sslVerifyPeer(),
            ]);
            $result   = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $uploaded += $chunkLen;

            if ($httpCode === 200 || $httpCode === 201) {
                $finalBody = $result; // upload complete
                break;
            } elseif ($httpCode !== 308) {
                throw new RuntimeException("Chunk upload failed. HTTP $httpCode: $result");
            }
        }
        fclose($handle);

        $file = json_decode($finalBody, true);
        if (empty($file['id'])) {
            throw new RuntimeException('Upload completed but no file ID returned: ' . $finalBody);
        }

        return $file;
    }

    // -----------------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------------

    /** Make a Drive file publicly readable by anyone with the link. */
    public function makePublic(string $fileId): void {
        if (empty($this->accessToken)) {
            $this->authenticate();
        }

        $response = $this->curlPost(
            "https://www.googleapis.com/drive/v3/files/$fileId/permissions",
            json_encode(['role' => 'reader', 'type' => 'anyone']),
            [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
            ]
        );

        if ($response['http_code'] !== 200) {
            throw new RuntimeException('Failed to set public permission. HTTP ' . $response['http_code']);
        }
    }

    // -----------------------------------------------------------------------
    // Delete
    // -----------------------------------------------------------------------

    /** Permanently delete a file from Google Drive. Returns true on success. */
    public function deleteFile(string $fileId): bool {
        if (empty($this->accessToken)) {
            $this->authenticate();
        }

        $ch = curl_init("https://www.googleapis.com/drive/v3/files/$fileId");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->accessToken],
            CURLOPT_SSL_VERIFYPEER => self::sslVerifyPeer(),
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // 204 = deleted, 404 = already gone — both are fine
        return $code === 204 || $code === 404;
    }

    // -----------------------------------------------------------------------
    // Storage info
    // -----------------------------------------------------------------------

    /** Returns ['usage' => bytes_used, 'limit' => bytes_total] or null on error. */
    public function getStorageInfo(): ?array {
        if (empty($this->accessToken)) {
            $this->authenticate();
        }

        $ch = curl_init('https://www.googleapis.com/drive/v3/about?fields=storageQuota');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->accessToken],
            CURLOPT_SSL_VERIFYPEER => self::sslVerifyPeer(),
        ]);
        $body = curl_exec($ch);

        $data = json_decode($body, true);
        if (empty($data['storageQuota'])) return null;

        return [
            'usage' => (int)($data['storageQuota']['usage']         ?? 0),
            'limit' => (int)($data['storageQuota']['limit']         ?? 0),
            'inDrive'=> (int)($data['storageQuota']['usageInDrive'] ?? 0),
        ];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** TEMPORARY: cert verification is skipped only when DRIVE_API_INSECURE_SSL_DEV (local hosts) is on. */
    private static function sslVerifyPeer(): bool {
        return !(defined('DRIVE_API_INSECURE_SSL_DEV') && DRIVE_API_INSECURE_SSL_DEV === true);
    }

    private function curlPost(string $url, string $body, array $headers = [], bool $returnHeaders = false): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => self::sslVerifyPeer(),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($returnHeaders) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = $returnHeaders ? curl_getinfo($ch, CURLINFO_HEADER_SIZE) : 0;
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $response = '';
        }

        if ($returnHeaders) {
            return [
                'http_code'  => $httpCode,
                'headers'    => substr($response, 0, $headerSize),
                'body'       => substr($response, $headerSize),
                'curl_error' => $curlError,
            ];
        }
        return ['http_code' => $httpCode, 'body' => $response, 'curl_error' => $curlError];
    }

    public function getAccountId(): string {
        return $this->accountId;
    }

    public function getAccessToken(): string {
        return $this->accessToken;
    }

    /**
     * Copy a file from another Drive account to this account.
     * Returns the new file ID on success.
     */
    public function copyFileFromUrl(string $sourceFileId, string $fileName, ?string $folderId = null): string {
        if (empty($this->accessToken)) {
            $this->authenticate();
        }

        // Download file from source
        $downloadUrl = 'https://www.googleapis.com/drive/v3/files/' . urlencode($sourceFileId) . '?alt=media';
        $tempFile = tempnam(sys_get_temp_dir(), 'drive_copy_');
        
        $fp = fopen($tempFile, 'wb');
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, self::sslVerifyPeer());
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fp);

        if ($httpCode !== 200) {
            @unlink($tempFile);
            throw new RuntimeException('Failed to download file for copying. HTTP ' . $httpCode);
        }

        // Get file metadata to preserve MIME type
        $mimeType = mime_content_type($tempFile) ?: 'application/octet-stream';

        // Upload to this account
        try {
            $result = $this->uploadFile($tempFile, $fileName, $mimeType, $folderId);
            @unlink($tempFile);
            return $result['id'];
        } catch (Exception $e) {
            @unlink($tempFile);
            throw $e;
        }
    }
}
