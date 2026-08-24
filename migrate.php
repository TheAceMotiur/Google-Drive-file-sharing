<?php
/**
 * Database Migration Script
 * Runs schema migrations from database/ folder
 * 
 * Usage: php migrate.php
 * Or visit: http://yourdomain.com/migrate.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

set_time_limit(300); // 5 minutes max
ini_set('memory_limit', '512M');

// Set content type for web access
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "=== FreeNetly Database Migration ===\n\n";

// ── Check database connection ─────────────────────────────────────────────────

echo "[1/2] Checking database connection...\n";
try {
    // First try to connect without specifying database
    $dsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Check if database exists, create if not
    $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
    if ($stmt->rowCount() === 0) {
        echo "  Database '" . DB_NAME . "' not found. Creating...\n";
        $pdo->exec("CREATE DATABASE `" . DB_NAME . "` CHARACTER SET " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci");
        echo "✓ Database created: " . DB_NAME . "\n\n";
    } else {
        echo "✓ Database exists: " . DB_NAME . "\n\n";
    }
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n\nPlease check your database configuration in config.php\n");
}

// ── Run Schema Migrations ─────────────────────────────────────────────────────

echo "[2/2] Running database schema migrations...\n";
$migrationDir = __DIR__ . '/database';

if (!is_dir($migrationDir)) {
    die("❌ Migration directory not found: $migrationDir\n");
}

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $db = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Confirm which database we're connected to
    $stmt = $db->query("SELECT DATABASE()");
    $currentDb = $stmt->fetchColumn();
    echo "  Connected to database: $currentDb\n\n";
    
    // Execute migration files
    $files = glob($migrationDir . '/*.{sql,php}', GLOB_BRACE);
    sort($files);
    
    foreach ($files as $file) {
        $filename = basename($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        
        echo "  → $filename ... ";
        
        if ($extension === 'sql') {
            $sql = file_get_contents($file);
            $parts = explode(';', $sql);
            $statements = [];
            
            foreach ($parts as $part) {
                $part = trim($part);
                // Skip empty parts
                if (empty($part)) {
                    continue;
                }
                
                // Remove comment lines
                $lines = explode("\n", $part);
                $cleanedLines = array_filter($lines, function($line) {
                    $line = trim($line);
                    return !empty($line) && !preg_match('/^--/', $line);
                });
                $cleanedStatement = trim(implode("\n", $cleanedLines));
                
                if (!empty($cleanedStatement)) {
                    $statements[] = $cleanedStatement;
                }
            }
            
            foreach ($statements as $statement) {
                try {
                    $db->exec($statement);
                } catch (PDOException $e) {
                    echo "✗\n  Error: " . $e->getMessage() . "\n";
                    throw $e;
                }
            }
            echo "✓\n";
        } elseif ($extension === 'php') {
            include $file;
            echo "✓\n";
        }
    }
    
    echo "\n✓ Schema migrations completed (" . count($files) . " files)\n";
    
    // Verify tables were created
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Tables created: " . implode(', ', $tables) . "\n\n";
    
} catch (Exception $e) {
    die("❌ Schema migration failed: " . $e->getMessage() . "\n");
}

// ── Summary ───────────────────────────────────────────────────────────────────

echo "\n=== Migration Complete ===\n\n";

// Verify final state
$totalFiles = getFileCount();
$totalAccounts = count(getDriveAccounts());

echo "Database status:\n";
echo "  - Total files: $totalFiles\n";
echo "  - Total accounts: $totalAccounts\n";
echo "\n";

if ($totalAccounts > 0) {
    echo "✓ Migration successful!\n\n";
    echo "Next steps:\n";
    echo "1. Add Google Drive accounts via admin panel if needed\n";
    echo "2. Test file upload/download functionality\n";
} else {
    echo "✓ Database schema created successfully.\n\n";
    echo "Next steps:\n";
    echo "1. Go to the admin panel and add Google Drive accounts\n";
    echo "2. Configure your Drive API credentials\n";
}

echo "\n";
