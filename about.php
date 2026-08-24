<?php
require_once __DIR__ . '/config.php';
$siteName = defined('SITE_NAME') ? SITE_NAME : 'FreeNetly';

// Set page variables for header
$pageTitle = 'About ' . $siteName . ' - Anonymous File Sharing Service';
$pageDescription = 'Learn about ' . $siteName . ', the free anonymous file sharing service. No registration required. Upload and share files up to 5GB securely and privately.';
$extraHead = '
  <meta name="keywords" content="about anonymous file sharing, free file hosting, secure file transfer, ' . htmlspecialchars($siteName) . '" />
  <link rel="canonical" href="' . SITE_URL . '/about" />
  
  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="' . SITE_URL . '/about" />
  <meta property="og:title" content="About ' . htmlspecialchars($siteName) . ' - Anonymous File Sharing" />
  <meta property="og:description" content="Learn about our free anonymous file sharing service. No registration required." />
  <meta property="og:image" content="' . SITE_URL . '/images/og-facebook.png" />
';

require_once __DIR__ . '/includes/header.php';
?>

<main class="max-w-4xl mx-auto px-6 py-12">
  
  <!-- Header -->
  <div class="mb-8">
    <a href="/" class="inline-flex items-center gap-2 text-blue-500 dark:text-blue-400 hover:text-blue-300 transition mb-4">
      ← Back to Home
    </a>
    <h1 class="text-4xl font-bold mb-2">About <?= htmlspecialchars($siteName) ?> - Anonymous File Sharing</h1>
    <p class="text-gray-500 dark:text-gray-500">Simple. Fast. Free. Anonymous.</p>
  </div>

  <!-- Content -->
  <div class="prose prose-invert max-w-none space-y-6">
    
    <section class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6">
      <h2 class="text-2xl font-semibold mb-3">What is <?= htmlspecialchars($siteName) ?>?</h2>
      <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
        <?= htmlspecialchars($siteName) ?> is a free, no-registration <strong>anonymous file sharing service</strong>. Upload any file up to <strong><?= MAX_FILE_SIZE_MB / 1024 ?> GB</strong>, get an instant download link, and share it with anyone. No account needed. No complicated setup. Just drag, drop, and share anonymously.
      </p>
    </section>

    <section class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6">
      <h2 class="text-2xl font-semibold mb-3">Why We Built This</h2>
      <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
        Too many file sharing services require accounts, subscriptions, or limit file sizes unnecessarily. We wanted something simple: upload, get a link, done. No tracking, no ads, no hassle.
      </p>
    </section>

    <section class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6">
      <h2 class="text-2xl font-semibold mb-3">Key Features</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-4 border border-gray-700">
          <div class="text-2xl mb-2">🚀</div>
          <div class="font-semibold text-blue-500 dark:text-blue-400 mb-1">No Registration</div>
          <div class="text-sm text-gray-500 dark:text-gray-500">No accounts, no email verification, zero barriers.</div>
        </div>
        <div class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-4 border border-gray-700">
          <div class="text-2xl mb-2">☁️</div>
          <div class="font-semibold text-green-400 mb-1">Cloud Storage</div>
          <div class="text-sm text-gray-500 dark:text-gray-500">Files backed by reliable cloud infrastructure.</div>
        </div>
        <div class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-4 border border-gray-700">
          <div class="text-2xl mb-2">📦</div>
          <div class="font-semibold text-purple-400 mb-1">Up to <?= MAX_FILE_SIZE_MB / 1024 ?> GB</div>
          <div class="text-sm text-gray-500 dark:text-gray-500">Share large files without compression.</div>
        </div>
        <div class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-4 border border-gray-700">
          <div class="text-2xl mb-2">🔒</div>
          <div class="font-semibold text-yellow-400 mb-1">Private Links</div>
          <div class="text-sm text-gray-500 dark:text-gray-500">Only people with the link can download.</div>
        </div>
        <div class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-4 border border-gray-700">
          <div class="text-2xl mb-2">⏱️</div>
          <div class="font-semibold text-red-400 mb-1">90-Day Retention</div>
          <div class="text-sm text-gray-500 dark:text-gray-500">Files expire 90 days after last download.</div>
        </div>
        <div class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-4 border border-gray-700">
          <div class="text-2xl mb-2">🎯</div>
          <div class="font-semibold text-indigo-400 mb-1">Auto-Cleanup</div>
          <div class="text-sm text-gray-500 dark:text-gray-500">Expired files automatically removed.</div>
        </div>
      </div>
    </section>

    <section class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6">
      <h2 class="text-2xl font-semibold mb-3">How It Works</h2>
      <ol class="space-y-3">
        <li class="flex items-start gap-3">
          <span class="flex-shrink-0 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold">1</span>
          <div>
            <div class="font-semibold text-gray-300">Upload Your File</div>
            <div class="text-sm text-gray-500 dark:text-gray-500">Drag & drop or click to select. Files up to <?= MAX_FILE_SIZE_MB / 1024 ?> GB supported.</div>
          </div>
        </li>
        <li class="flex items-start gap-3">
          <span class="flex-shrink-0 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold">2</span>
          <div>
            <div class="font-semibold text-gray-300">Get Your Link</div>
            <div class="text-sm text-gray-500 dark:text-gray-500">Instant download link generated upon upload completion.</div>
          </div>
        </li>
        <li class="flex items-start gap-3">
          <span class="flex-shrink-0 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold">3</span>
          <div>
            <div class="font-semibold text-gray-300">Share It</div>
            <div class="text-sm text-gray-500 dark:text-gray-500">Send the link to anyone via email, chat, or social media.</div>
          </div>
        </li>
      </ol>
    </section>

    <section class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6">
      <h2 class="text-2xl font-semibold mb-3">Technology Stack</h2>
      <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-3">
        <?= htmlspecialchars($siteName) ?> is built with modern, lightweight technologies:
      </p>
      <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 space-y-2 ml-4">
        <li><strong>PHP</strong> – Server-side processing</li>
        <li><strong>Vue.js 3</strong> – Reactive frontend interface</li>
        <li><strong>TailwindCSS</strong> – Modern, responsive styling</li>
        <li><strong>Cloud Storage API</strong> – Reliable file storage</li>
        <li><strong>Resumable Uploads</strong> – Smooth uploads with 16MB chunks</li>
        <li><strong>Bandwidth Failover</strong> – Auto-migration between accounts</li>
        <li><strong>Internal Cron</strong> – Automatic cleanup, no external dependencies</li>
      </ul>
    </section>

    <section class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6">
      <h2 class="text-2xl font-semibold mb-3">Fair Use Policy</h2>
      <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
        <?= htmlspecialchars($siteName) ?> is provided free of charge. We ask that you:
      </p>
      <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 space-y-2 ml-4">
        <li>Use the service responsibly and legally</li>
        <li>Respect copyright and intellectual property rights</li>
        <li>Don't upload malware, viruses, or harmful content</li>
        <li>Don't abuse the service with excessive uploads</li>
      </ul>
      <p class="text-gray-600 dark:text-gray-400 leading-relaxed mt-3">
        Violations may result in file removal or access restrictions.
      </p>
    </section>

    <section class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6">
      <h2 class="text-2xl font-semibold mb-3">Open Source</h2>
      <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
        <?= htmlspecialchars($siteName) ?> is built with transparency in mind. The codebase uses standard PHP practices and well-documented APIs. No obfuscation, no hidden tracking.
      </p>
    </section>

    <section class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6">
      <h2 class="text-2xl font-semibold mb-3">Get in Touch</h2>
      <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
        Have questions, feedback, or issues? Visit our <a href="contact" class="text-blue-500 dark:text-blue-400 hover:underline">Contact</a> page to reach us.
      </p>
    </section>

  </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
