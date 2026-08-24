<?php
/**
 * download.php — File info & download page.
 * Tracks last_downloaded_at per visit. Cleanup runs automatically via internal cron.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/internal_cron.php';

// -- Helpers -------------------------------------------------------------------

function formatBytes(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $i = (int) floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function fileIcon(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'pdf'  => '📄', 'doc'  => '📝', 'docx' => '📝',
        'xls'  => '📊', 'xlsx' => '📊', 'ppt'  => '📽️', 'pptx' => '📽️',
        'zip'  => '🗜️',  'rar'  => '🗜️',  '7z'   => '🗜️',
        'mp4'  => '🎬', 'mov'  => '🎬', 'avi'  => '🎬', 'mkv'  => '🎬',
        'mp3'  => '🎵', 'wav'  => '🎵', 'flac' => '🎵',
        'jpg'  => '🖼️',  'jpeg' => '🖼️',  'png'  => '🖼️',  'gif'  => '🖼️',
        'webp' => '🖼️',  'svg'  => '🖼️',
        'txt'  => '📃', 'csv'  => '📊', 'json' => '📋',
    ];
    return $map[$ext] ?? '📦';
}

function isImageFile(string $name): bool {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico'];
    return in_array($ext, $imageExts, true);
}

function daysUntilExpiry(array $record): int {
    $expiry   = (int)($record['expires_after_days'] ?? EXPIRY_DAYS);
    $base     = $record['last_downloaded_at'] ?? $record['uploaded_at'] ?? null;
    if (!$base) return $expiry;
    $diffDays = (int) floor((time() - strtotime($base)) / 86400);
    return max(0, $expiry - $diffDays);
}

function renderErrorPage(string $msg): void {
    $site = defined('SITE_NAME') ? SITE_NAME : 'FreeNetly';
    $home = defined('SITE_URL')  ? SITE_URL  : '/';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>$site — Error</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white flex items-center justify-center min-h-screen">
  <div class="text-center space-y-4">
    <div class="text-6xl">⚠️</div>
    <h1 class="text-2xl font-bold">$msg</h1>
    <a href="$home" class="inline-block mt-4 px-6 py-2 bg-blue-600 hover:bg-blue-500 rounded-lg transition">
      Go Home
    </a>
  </div>
</body>
</html>
HTML;
    exit;
}

// -- Validate ID ---------------------------------------------------------------

$id = trim($_GET['id'] ?? '');

if (empty($id) || !preg_match('/^[a-f0-9]{12}$/', $id)) {
    http_response_code(400);
    renderErrorPage('Invalid download link.');
}

// -- Find record ---------------------------------------------------------------

// Find file record in database
$found = getFileById($id);

if ($found === null) {
    http_response_code(404);
    renderErrorPage('File not found or the link has expired.');
}

// -- Track this page visit -----------------------------------------------------

incrementDownloadCount($id);
$found['last_downloaded_at'] = date('Y-m-d H:i:s');
$found['download_count'] = (int)($found['download_count'] ?? 0) + 1;

// -- Build template vars -------------------------------------------------------

$siteName      = SITE_NAME;
$siteUrl       = SITE_URL;
$fileName      = htmlspecialchars($found['original_name']);
$fileSize      = formatBytes((int)($found['size'] ?? 0));
$fileExt       = strtoupper(pathinfo($found['original_name'], PATHINFO_EXTENSION)) ?: 'FILE';
$icon          = fileIcon($found['original_name']);
$isImage       = isImageFile($found['original_name']);
$uploadedAt    = htmlspecialchars($found['uploaded_at'] ?? '');
$downloadCount = (int)($found['download_count'] ?? 0);
$daysLeft      = daysUntilExpiry($found);
$expiryDays    = (int)($found['expires_after_days'] ?? EXPIRY_DAYS);
$directLink    = SITE_URL . '/stream/' . urlencode($id);
$downloadLink  = SITE_URL . '/stream/' . urlencode($id) . '?download=1';
$shareLink     = htmlspecialchars(DOWNLOAD_BASE . $id);

// Calculate actual removal date
$baseDate = $found['last_downloaded_at'] ?? $found['uploaded_at'] ?? date('Y-m-d H:i:s');
$removalDate = date('Y-m-d H:i:s', strtotime($baseDate . ' + ' . $expiryDays . ' days'));

if ($daysLeft <= 7)      { $expiryClass = 'bg-red-900/60 text-red-300 border-red-800'; }
elseif ($daysLeft <= 30) { $expiryClass = 'bg-yellow-900/60 text-yellow-300 border-yellow-800'; }
else                     { $expiryClass = 'bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-700'; }

// Set page variables for header
$pageTitle = $fileName . ' — ' . $siteName;
$pageDescription = 'Download ' . $fileName . ' (' . $fileSize . ') from ' . $siteName . '.';
$extraHead = '
  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="' . $shareLink . '" />
  <meta property="og:title" content="Download: ' . $fileName . '" />
  <meta property="og:description" content="File size: ' . $fileSize . ' • Download from ' . $siteName . ' - Free file sharing service" />
  <meta property="og:image" content="' . SITE_URL . '/images/og-facebook.png" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  
  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image" />
  <meta property="twitter:url" content="' . $shareLink . '" />
  <meta property="twitter:title" content="Download: ' . $fileName . '" />
  <meta property="twitter:description" content="File size: ' . $fileSize . ' • Free download via ' . $siteName . '" />
  <meta property="twitter:image" content="' . SITE_URL . '/images/og-twitter.png" />
  <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
  <style>[v-cloak]{display:none}</style>
';

require_once __DIR__ . '/includes/header.php';
?>

<main class="flex-1 flex items-center justify-center px-4 py-16">
    <div id="app" v-cloak class="w-full max-w-lg space-y-4">

      <!-- File card -->
      <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-7 text-center space-y-4 shadow-2xl">

        <!-- Icon + name -->
        <div class="space-y-2">
          <div class="text-7xl leading-none"><?= $icon ?></div>
          <h1 class="text-xl font-bold break-all leading-snug"><?= $fileName ?></h1>
          <div class="flex items-center justify-center gap-3 text-sm text-gray-600 dark:text-gray-400 flex-wrap">
            <span class="bg-gray-800 px-2 py-0.5 rounded font-mono text-xs text-blue-300"><?= $fileExt ?></span>
            <span><?= $fileSize ?></span>
            <?php if ($uploadedAt): ?>
            <span>Uploaded <?= $uploadedAt ?></span>
            <?php endif; ?>
          </div>
          <?php if ($downloadCount > 0): ?>
          <p class="text-xs text-gray-600">
            Downloaded <?= $downloadCount ?> time<?= $downloadCount !== 1 ? 's' : '' ?>
          </p>
          <?php endif; ?>
        </div>

        <!-- Image Preview (if image file) -->
        <?php if ($isImage): ?>
        <div class="mt-4 mb-4">
          <div class="relative rounded-xl overflow-hidden bg-gray-800 border border-gray-700">
            <img 
              src="<?= $directLink ?>" 
              alt="<?= $fileName ?>" 
              class="w-full h-auto max-h-96 object-contain"
              loading="lazy"
              onerror="this.parentElement.innerHTML='<div class=&quot;p-8 text-gray-500 text-sm&quot;>🖼️ Preview unavailable</div>'"
            />
          </div>
          <p class="text-xs text-gray-500 mt-2 text-center">Image Preview</p>
        </div>
        <?php endif; ?>

        <!-- Download button -->
        <a
          href="https://base64-encoder.com/go/<?= $downloadLink ?>"
          class="flex items-center justify-center gap-2 w-full py-3.5 rounded-xl bg-blue-600 hover:bg-blue-500 active:scale-95 text-white font-bold text-lg transition-all duration-150 shadow-lg shadow-blue-900/40"
        >
          ⬇️ Download File
        </a>
      </div>

      <!-- Share link -->
      <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 space-y-2">
        <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">Share this file</p>
        <div class="flex items-center gap-2">
          <input
            type="text"
            readonly
            value="<?= $shareLink ?>"
            class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs font-mono text-blue-300 focus:outline-none cursor-pointer"
            @click="$event.target.select()"
          />
          <button
            @click="copyShareLink"
            class="flex-shrink-0 px-4 py-2 rounded-lg text-xs font-bold transition"
            :class="copied ? 'bg-green-600 text-white' : 'bg-blue-600 hover:bg-blue-500 text-white'"
          >
            {{ copied ? '✓ Copied!' : 'Copy' }}
          </button>
        </div>
      </div>

      <!-- Social Share Buttons -->
      <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider mb-3">Share on social</p>
        <div class="grid grid-cols-3 gap-2">
          <!-- Facebook -->
          <a
            :href="'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl)"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-[#1877F2] hover:bg-[#166FE5] text-white text-sm font-semibold transition-all hover:scale-105 active:scale-95"
            title="Share on Facebook"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
            <span class="hidden sm:inline">Facebook</span>
          </a>

          <!-- Twitter/X -->
          <a
            :href="'https://twitter.com/intent/tweet?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(shareText)"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-[#000000] hover:bg-[#1a1a1a] text-white text-sm font-semibold transition-all hover:scale-105 active:scale-95"
            title="Share on X (Twitter)"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
            </svg>
            <span class="hidden sm:inline">Twitter</span>
          </a>

          <!-- WhatsApp -->
          <a
            :href="'https://wa.me/?text=' + encodeURIComponent(shareText + ' ' + shareUrl)"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-[#25D366] hover:bg-[#22C55E] text-white text-sm font-semibold transition-all hover:scale-105 active:scale-95"
            title="Share on WhatsApp"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
            </svg>
            <span class="hidden sm:inline">WhatsApp</span>
          </a>

          <!-- Telegram -->
          <a
            :href="'https://t.me/share/url?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(shareText)"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-[#0088cc] hover:bg-[#0077b3] text-white text-sm font-semibold transition-all hover:scale-105 active:scale-95"
            title="Share on Telegram"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
            </svg>
            <span class="hidden sm:inline">Telegram</span>
          </a>

          <!-- LinkedIn -->
          <a
            :href="'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(shareUrl)"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-[#0077B5] hover:bg-[#006399] text-white text-sm font-semibold transition-all hover:scale-105 active:scale-95"
            title="Share on LinkedIn"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
            </svg>
            <span class="hidden sm:inline">LinkedIn</span>
          </a>

          <!-- Email -->
          <a
            :href="'mailto:?subject=' + encodeURIComponent(shareText) + '&body=' + encodeURIComponent('Check out this file: ' + shareUrl)"
            class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm font-semibold transition-all hover:scale-105 active:scale-95"
            title="Share via Email"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <span class="hidden sm:inline">Email</span>
          </a>
        </div>
      </div>

      <!-- Back link -->
      <p class="text-center text-sm text-gray-600">
        <a href="<?= $siteUrl ?>" class="hover:text-blue-500 dark:text-blue-400 transition">↑ Upload a new file</a>
      </p>

    </div>
  </main>

<script>
const { createApp, ref } = Vue;
createApp({
  setup() {
    const copied = ref(false);
    const shareUrl = <?= json_encode(DOWNLOAD_BASE . $id) ?>;
    const shareText = <?= json_encode('Download ' . $found['original_name'] . ' from ' . SITE_NAME) ?>;

    function copyShareLink() {
      const text = shareUrl;
      const markCopied = () => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(markCopied).catch(() => execCopy(text, markCopied));
      } else {
        execCopy(text, markCopied);
      }
    }

    function execCopy(text, cb) {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
      document.body.appendChild(ta);
      ta.focus(); ta.select();
      try { document.execCommand('copy'); } catch(_) {}
      document.body.removeChild(ta);
      cb();
    }

    return { copied, copyShareLink, shareUrl, shareText };
  }
}).mount('#app');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
