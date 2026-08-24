<?php
$siteName = defined('SITE_NAME') ? SITE_NAME : 'FreeNetly';
$currentYear = date('Y');
?>

<!-- Footer -->
<footer class="border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/40 mt-auto">
  <div class="max-w-4xl mx-auto px-4 py-8">
    <!-- Links -->
    <div class="text-center space-y-3 mb-4">
      <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm">
        <a href="<?= SITE_URL ?>" class="text-gray-600 dark:text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition">Home</a>
        <span class="text-gray-400 dark:text-gray-600">·</span>
        <a href="<?= SITE_URL ?>/terms" class="text-gray-600 dark:text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition">Terms</a>
        <span class="text-gray-400 dark:text-gray-600">·</span>
        <a href="<?= SITE_URL ?>/privacy" class="text-gray-600 dark:text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition">Privacy</a>
        <span class="text-gray-400 dark:text-gray-600">·</span>
        <a href="<?= SITE_URL ?>/dmca" class="text-gray-600 dark:text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition">DMCA</a>
        <span class="text-gray-400 dark:text-gray-600">·</span>
        <a href="<?= SITE_URL ?>/about" class="text-gray-600 dark:text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition">About</a>
        <span class="text-gray-400 dark:text-gray-600">·</span>
        <a href="<?= SITE_URL ?>/contact" class="text-gray-600 dark:text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition">Contact</a>
      </nav>
    </div>
    
    <!-- Copyright -->
    <div class="text-center text-xs text-gray-500 dark:text-gray-600">
      <p>&copy; <?= $currentYear ?> <?= htmlspecialchars($siteName) ?>. All rights reserved.</p>
      <p class="mt-1">Free file sharing service · <a href="mailto:<?= SITE_EMAIL ?>" class="hover:text-blue-500 dark:hover:text-blue-400 transition"><?= SITE_EMAIL ?></a></p>
    </div>
  </div>
</footer>

</body>
</html>
