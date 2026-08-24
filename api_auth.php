<?php
/**
 * API Authentication Middleware
 * Handles API key validation, rate limiting, and usage tracking
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class APIAuth {
    
    private ?array $apiKeyData = null;
    
    /**
     * Validate API key from request headers
     * Looks for X-API-Key header
     */
    public function authenticate(): bool {
        $apiKey = $this->getAPIKeyFromRequest();
        
        if (!$apiKey) {
            $this->sendError('Missing API key. Include X-API-Key header.', 401);
            return false;
        }
        
        $this->apiKeyData = $this->validateAPIKey($apiKey);
        
        if (!$this->apiKeyData) {
            $this->sendError('Invalid or inactive API key.', 401);
            return false;
        }
        
        // Check rate limit
        if (!$this->checkRateLimit()) {
            $this->sendError('Rate limit exceeded. Please try again later.', 429);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get API key from request headers
     */
    private function getAPIKeyFromRequest(): ?string {
        // Check $_SERVER first (required for Nginx + PHP-FPM)
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }
        
        // Check getallheaders() as fallback
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            
            // Check X-API-Key header
            if (isset($headers['X-API-Key'])) {
                return $headers['X-API-Key'];
            }
            
            // Check x-api-key (lowercase)
            if (isset($headers['x-api-key'])) {
                return $headers['x-api-key'];
            }
            
            // Check Authorization header (Bearer token)
            if (isset($headers['Authorization'])) {
                if (preg_match('/Bearer\s+(.+)/i', $headers['Authorization'], $matches)) {
                    return $matches[1];
                }
            }
        }
        
        // Check Authorization from $_SERVER
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            if (preg_match('/Bearer\s+(.+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Validate API key against database
     */
    private function validateAPIKey(string $apiKey): ?array {
        try {
            $db = getDB();
            $stmt = $db->prepare('
                SELECT id, api_key, name, website_url, is_active, rate_limit_per_hour, 
                       total_uploads, total_downloads
                FROM api_keys 
                WHERE api_key = ? AND is_active = 1
            ');
            $stmt->execute([$apiKey]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return null;
            }
            
            // Update last_used_at
            $updateStmt = $db->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?');
            $updateStmt->execute([$result['id']]);
            
            return $result;
        } catch (PDOException $e) {
            error_log('API key validation error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check rate limit for API key
     */
    private function checkRateLimit(): bool {
        if (!$this->apiKeyData) {
            return false;
        }
        
        try {
            $db = getDB();
            $stmt = $db->prepare('
                SELECT COUNT(*) as count 
                FROM api_usage_logs 
                WHERE api_key_id = ? 
                  AND request_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ');
            $stmt->execute([$this->apiKeyData['id']]);
            $result = $stmt->fetch();
            
            $currentUsage = $result['count'] ?? 0;
            $rateLimit = $this->apiKeyData['rate_limit_per_hour'];
            
            return $currentUsage < $rateLimit;
        } catch (PDOException $e) {
            error_log('Rate limit check error: ' . $e->getMessage());
            return true; // Allow on error to prevent blocking legitimate requests
        }
    }
    
    /**
     * Log API usage
     */
    public function logUsage(string $endpoint, int $responseCode, ?string $fileId = null): void {
        if (!$this->apiKeyData) {
            return;
        }
        
        try {
            $db = getDB();
            $stmt = $db->prepare('
                INSERT INTO api_usage_logs 
                (api_key_id, endpoint, ip_address, user_agent, response_code, file_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt->execute([
                $this->apiKeyData['id'],
                $endpoint,
                $ipAddress,
                $userAgent,
                $responseCode,
                $fileId
            ]);
        } catch (PDOException $e) {
            error_log('API usage logging error: ' . $e->getMessage());
        }
    }
    
    /**
     * Increment upload counter for API key
     */
    public function incrementUploadCount(): void {
        if (!$this->apiKeyData) {
            return;
        }
        
        try {
            $db = getDB();
            $stmt = $db->prepare('UPDATE api_keys SET total_uploads = total_uploads + 1 WHERE id = ?');
            $stmt->execute([$this->apiKeyData['id']]);
        } catch (PDOException $e) {
            error_log('Upload count increment error: ' . $e->getMessage());
        }
    }
    
    /**
     * Increment download counter for API key
     */
    public function incrementDownloadCount(): void {
        if (!$this->apiKeyData) {
            return;
        }
        
        try {
            $db = getDB();
            $stmt = $db->prepare('UPDATE api_keys SET total_downloads = total_downloads + 1 WHERE id = ?');
            $stmt->execute([$this->apiKeyData['id']]);
        } catch (PDOException $e) {
            error_log('Download count increment error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get current API key data
     */
    public function getAPIKeyData(): ?array {
        return $this->apiKeyData;
    }
    
    /**
     * Send JSON error response and exit
     */
    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
        exit;
    }
}

// ── API KEY MANAGEMENT FUNCTIONS ──────────────────────────────────────────────

/**
 * Generate a new API key
 */
function generateAPIKey(string $name, string $websiteUrl = '', int $rateLimit = 100): array {
    try {
        $db = getDB();
        
        // Generate unique API key
        $apiKey = 'ok_' . bin2hex(random_bytes(24)); // ok_ prefix for FreeNetly Key
        $apiSecret = bin2hex(random_bytes(32));
        
        $stmt = $db->prepare('
            INSERT INTO api_keys (api_key, api_secret, name, website_url, rate_limit_per_hour)
            VALUES (?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $apiKey,
            password_hash($apiSecret, PASSWORD_DEFAULT),
            $name,
            $websiteUrl,
            $rateLimit
        ]);
        
        return [
            'success' => true,
            'api_key' => $apiKey,
            'name' => $name,
            'website_url' => $websiteUrl,
            'rate_limit_per_hour' => $rateLimit,
            'created_at' => date('Y-m-d H:i:s')
        ];
    } catch (PDOException $e) {
        error_log('API key generation error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to generate API key'
        ];
    }
}

/**
 * Get all API keys
 */
function getAllAPIKeys(): array {
    try {
        $db = getDB();
        $stmt = $db->query('
            SELECT id, api_key, name, website_url, is_active, created_at, last_used_at,
                   total_uploads, total_downloads, rate_limit_per_hour
            FROM api_keys
            ORDER BY created_at DESC
        ');
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get API keys error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Delete API key
 */
function deleteAPIKey(int $id): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare('DELETE FROM api_keys WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Delete API key error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Toggle API key active status
 */
function toggleAPIKeyStatus(int $id, bool $isActive): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare('UPDATE api_keys SET is_active = ? WHERE id = ?');
        return $stmt->execute([$isActive ? 1 : 0, $id]);
    } catch (PDOException $e) {
        error_log('Toggle API key status error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get API usage statistics
 */
function getAPIUsageStats(int $apiKeyId, int $days = 7): array {
    try {
        $db = getDB();
        $stmt = $db->prepare('
            SELECT 
                DATE(request_time) as date,
                COUNT(*) as requests,
                SUM(CASE WHEN response_code = 200 THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN response_code != 200 THEN 1 ELSE 0 END) as failed
            FROM api_usage_logs
            WHERE api_key_id = ?
              AND request_time > DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(request_time)
            ORDER BY date DESC
        ');
        $stmt->execute([$apiKeyId, $days]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get API usage stats error: ' . $e->getMessage());
        return [];
    }
}
