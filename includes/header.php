<?php
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config.php';
}
$siteName = defined('SITE_NAME') ? SITE_NAME : 'FreeNetly';
$siteUrl = defined('SITE_URL') ? SITE_URL : '/';
?>
<!DOCTYPE html>
<html lang="en" class="<?= $_COOKIE['theme'] ?? 'dark' ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' – ' : '' ?><?= htmlspecialchars($siteName) ?></title>
  <?php if (isset($pageDescription)): ?>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>" />
  <?php endif; ?>
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="<?= $siteUrl ?>/images/icon.png" />
  <link rel="shortcut icon" type="image/png" href="<?= $siteUrl ?>/images/icon.png" />
  <link rel="apple-touch-icon" href="<?= $siteUrl ?>/images/icon.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            brand: { DEFAULT: '#3b82f6', dark: '#2563eb' }
          }
        }
      }
    }
  </script>
  <style>
    [v-cloak] { display: none; }
    
    /* Theme transitions */
    * {
      transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    }
  </style>
  <?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body class="bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 min-h-screen antialiased">

<!-- Header -->
<header class="border-b border-gray-200 dark:border-gray-800 bg-white/80 dark:bg-gray-900/60 backdrop-blur sticky top-0 z-50">
  <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
    <a href="<?= $siteUrl ?>" class="flex items-center gap-2 text-xl font-bold tracking-tight">
      <span class="text-blue-500 dark:text-blue-400"><?= htmlspecialchars($siteName) ?></span>
    </a>
    
    <div class="flex items-center gap-3">
      <!-- Theme Switcher -->
      <button 
        onclick="toggleTheme()"
        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
        title="Toggle theme"
        aria-label="Toggle theme"
      >
        <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/>
        </svg>
        <svg class="w-5 h-5 block dark:hidden" fill="currentColor" viewBox="0 0 20 20">
          <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
        </svg>
      </button>
      
      <?php if (isset($headerRight)): ?>
        <?= $headerRight ?>
      <?php endif; ?>
    </div>
  </div>
</header>

<script>
// Theme management
function toggleTheme() {
  const html = document.documentElement;
  const currentTheme = html.classList.contains('dark') ? 'dark' : 'light';
  const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
  
  html.classList.remove('dark', 'light');
  html.classList.add(newTheme);
  
  // Save preference
  document.cookie = `theme=${newTheme}; path=/; max-age=31536000; SameSite=Lax`;
}

// Initialize theme from cookie or system preference
(function() {
  const savedTheme = document.cookie.split('; ').find(row => row.startsWith('theme='))?.split('=')[1];
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const theme = savedTheme || (prefersDark ? 'dark' : 'light');
  document.documentElement.classList.add(theme);
})();
</script>
