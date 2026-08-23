<?php
/**
 * Google Drive Uploader - Configuration
 * Uses OAuth2 Refresh Token auth (client_id + client_secret + refresh_token).
 * Add multiple accounts to DRIVE_ACCOUNTS for round-robin storage spreading.
 */

define('SITE_NAME', 'OneNetly');
define('SITE_EMAIL', 'onenetly@gmail.com');

// Auto-detect site URL from server variables (defaults to production URL)
function getSiteUrl(): string {
    // Use onenetly.com in production, or auto-detect for local development
    if (!empty($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') === false && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false) {
        return 'https://onenetly.com';
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return $protocol . '://' . $host;
}
define('SITE_URL', getSiteUrl());

// ═══════════════════════════════════════════════════════════════════════════════
// TEMPORARY DEV-ONLY SSL BYPASS (Google Drive API calls in drive_api.php)
// ═══════════════════════════════════════════════════════════════════════════════
// Only disables certificate verification when running on a local/.local dev
// host — NEVER activates on a real domain. Remove once the local CA bundle
// (curl.cainfo / openssl.cafile in php.ini) is fixed. Traffic is still
// encrypted (TLS); only the certificate chain check is skipped.
$__driveApiHost = $_SERVER['HTTP_HOST'] ?? '';
define('DRIVE_API_INSECURE_SSL_DEV', (bool)preg_match('/(^|\.)(localhost|127\.0\.0\.1|.*\.local)$/i', $__driveApiHost));
unset($__driveApiHost);

// Max upload size — Herd/Nginx: set via php.ini or ini_set() (see upload.php)
define('MAX_FILE_SIZE_MB', 5120); // 5 GB
define('MAX_FILE_SIZE_BYTES', MAX_FILE_SIZE_MB * 1024 * 1024);


// Google Drive folder ID to upload into (null = root)
define('DEFAULT_DRIVE_FOLDER_ID', null);

// ═══════════════════════════════════════════════════════════════════════════════
// STRICT FILE EXTENSION WHITELIST
// ═══════════════════════════════════════════════════════════════════════════════
// Only extensions listed here are allowed. Any other file type will be rejected.
// This provides strong security by explicitly defining what is permitted.
// 
// To allow ALL extensions (not recommended): set to an empty array []
// To be more permissive: uncomment additional categories below
// ═══════════════════════════════════════════════════════════════════════════════

define('ALLOWED_EXTENSIONS', [
    // ──────────────────────────────────────────────────────────────────────────
    // DOCUMENTS (Common formats)
    // ──────────────────────────────────────────────────────────────────────────
    'pdf',                          // Adobe PDF
    'doc', 'docx',                  // Microsoft Word
    'xls', 'xlsx',                  // Microsoft Excel
    'ppt', 'pptx',                  // Microsoft PowerPoint
    'txt',                          // Plain Text
    'rtf',                          // Rich Text Format
    'odt', 'ods', 'odp',           // OpenDocument (LibreOffice)
    'csv',                          // Comma-Separated Values
    
    // ──────────────────────────────────────────────────────────────────────────
    // IMAGES (Web-safe + common photo formats)
    // ──────────────────────────────────────────────────────────────────────────
    'jpg', 'jpeg',                  // JPEG images
    'png',                          // PNG images
    'gif',                          // GIF images
    'webp',                         // WebP images (modern)
    'svg',                          // SVG vector graphics
    'bmp',                          // Bitmap
    'ico',                          // Icons
    'heic', 'heif',                // Apple HEIC/HEIF
    
    // ──────────────────────────────────────────────────────────────────────────
    // VIDEOS (Common formats)
    // ──────────────────────────────────────────────────────────────────────────
    'mp4',                          // MPEG-4 (most common)
    'mov',                          // QuickTime
    'avi',                          // AVI container
    'mkv',                          // Matroska
    'webm',                         // WebM (web standard)
    'wmv',                          // Windows Media Video
    'flv',                          // Flash Video
    'mpeg', 'mpg',                  // MPEG-1/2
    'm4v',                          // iTunes video
    
    // ──────────────────────────────────────────────────────────────────────────
    // AUDIO (Common formats)
    // ──────────────────────────────────────────────────────────────────────────
    'mp3',                          // MP3 (most common)
    'wav',                          // WAV (uncompressed)
    'ogg',                          // Ogg Vorbis
    'flac',                         // FLAC (lossless)
    'aac',                          // Advanced Audio Coding
    'm4a',                          // MPEG-4 Audio
    'wma',                          // Windows Media Audio
    
    // ──────────────────────────────────────────────────────────────────────────
    // ARCHIVES (Compressed files)
    // ──────────────────────────────────────────────────────────────────────────
    'zip',                          // ZIP archive
    'rar',                          // RAR archive
    '7z',                           // 7-Zip
    'tar',                          // Tar archive
    'gz',                           // Gzip
    
    // ──────────────────────────────────────────────────────────────────────────
    // CODE & DEVELOPMENT (Common formats)
    // ──────────────────────────────────────────────────────────────────────────
    'html', 'htm',                  // HTML
    'css',                          // CSS
    'js',                           // JavaScript
    'json',                         // JSON data
    'xml',                          // XML
    'md', 'markdown',               // Markdown
    
    // ═══════════════════════════════════════════════════════════════════════════
    // OPTIONAL: Uncomment sections below to allow additional file types
    // ═══════════════════════════════════════════════════════════════════════════
    
    /*
    // ── EXTENDED DOCUMENTS ──
    'epub',                         // E-books
    'pages',                        // Apple Pages
    'numbers',                      // Apple Numbers
    'key',                          // Apple Keynote
    
    // ── RAW IMAGES (Professional photography) ──
    'raw', 'cr2', 'nef', 'dng', 'arw', 'orf',
    
    // ── DESIGN FILES ──
    'psd',                          // Adobe Photoshop
    'ai',                           // Adobe Illustrator
    'sketch',                       // Sketch
    'fig',                          // Figma
    'xd',                           // Adobe XD
    
    // ── 3D MODELS ──
    'obj', 'fbx', 'stl', 'gltf', 'glb',
    
    // ── FONTS ──
    'ttf', 'otf', 'woff', 'woff2',
    
    // ── EXECUTABLES & MOBILE APPS ──
    'exe',                          // Windows executable
    'apk',                          // Android app
    'ipa',                          // iOS app
    'dmg',                          // macOS disk image
    'iso',                          // ISO disk image
    
    // ── ADDITIONAL CODE LANGUAGES ──
    'py',                           // Python
    'java',                         // Java
    'cpp', 'c', 'h',               // C/C++
    'php',                          // PHP (allowed if not in BLOCKED_EXTENSIONS)
    'rb',                           // Ruby
    'go',                           // Go
    'rs',                           // Rust
    'swift',                        // Swift
    'kt',                           // Kotlin
    'ts',                           // TypeScript
    'sql',                          // SQL
    'yaml', 'yml',                  // YAML
    */
]);

// Blocked file extensions for security
// Only blocking server-side executable scripts that could run on PHP/Nginx server
define('BLOCKED_EXTENSIONS', [
    // PHP executables (primary concern for PHP server)
    'php', 'php3', 'php4', 'php5', 'php7', 'mjs', 'phtml', 'phps', 'pht', 'phar',
    // Shell scripts (could be dangerous if server is misconfigured)
    'sh', 'bash', 'zsh', 'fish', 'ksh', 'csh',
    // Windows batch/PowerShell (if running on Windows server)
    'bat', 'cmd', 'ps1',
    // Server config files (sensitive)
    'htaccess', 'htpasswd',
]);

// Short link base (used in download.php)
define('DOWNLOAD_BASE', SITE_URL . '/download/');

// Auto-delete files this many days after their last download (or last upload if never downloaded)
define('EXPIRY_DAYS', 90);

// Secret token to protect the cleanup endpoint (change this to a random string)
define('CLEANUP_TOKEN', 'change-this-to-a-random-secret-string');

// Admin panel credentials
define('ADMIN_USERNAME', 'TheAceMotiur');
define('ADMIN_PASSWORD', 'AmiMotiur27@'); // Change this to a strong password!

// Database Configuration (MySQL)
require_once __DIR__ . '/configuration.php';

//define('DB_HOST', 'localhost');
//define('DB_NAME', 'onefiles');
//define('DB_USER', 'root');
//define('DB_PASS', 'AmiMotiur27@');
//define('DB_CHARSET', 'utf8mb4');