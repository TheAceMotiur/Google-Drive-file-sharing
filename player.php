<?php
/**
 * player.php — Video player page using Video.js
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Get file ID from URL parameter
$id = trim($_GET['id'] ?? '');

// Demo mode - show instructions if no ID provided
if (empty($id)) {
    showDemoPage();
    exit;
}

if (!preg_match('/^[a-f0-9]{12}$/', $id)) {
    die('Invalid video link.');
}

// Get file information
$file = getFileById($id);

if ($file === null) {
    die('Video not found or link has expired.');
}

function showDemoPage() {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Player Demo - FreeNetly</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }
        
        .demo-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .demo-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .demo-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .demo-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .demo-content {
            padding: 40px;
        }
        
        .demo-section {
            margin-bottom: 30px;
        }
        
        .demo-section h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .demo-section p {
            line-height: 1.6;
            color: #555;
            margin-bottom: 10px;
        }
        
        .code-block {
            background: #f5f5f5;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 10px 0;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .feature-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }
        
        .feature-item strong {
            color: #667eea;
            display: block;
            margin-bottom: 5px;
        }
        
        .btn-demo {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 20px;
        }
        
        .btn-demo:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            color: #856404;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .demo-header h1 {
                font-size: 2rem;
            }
            
            .demo-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="demo-container">
        <div class="demo-header">
            <h1>🎬 Video Player</h1>
            <p>Simple HTML5 Video Player with Video.js</p>
        </div>
        
        <div class="demo-content">
            <div class="demo-section">
                <h2>Features</h2>
                <div class="features">
                    <div class="feature-item">
                        <strong>Responsive Design</strong>
                        <span>Works on all devices</span>
                    </div>
                    <div class="feature-item">
                        <strong>Speed Control</strong>
                        <span>0.5x to 2x playback</span>
                    </div>
                    <div class="feature-item">
                        <strong>Full Screen</strong>
                        <span>Immersive viewing</span>
                    </div>
                    <div class="feature-item">
                        <strong>Download Option</strong>
                        <span>Save videos locally</span>
                    </div>
                </div>
            </div>
            
            <div class="demo-section">
                <h2>How to Use</h2>
                <p>To play a video, access the player with a file ID parameter:</p>
                <div class="code-block">
                    player.php?id=YOUR_FILE_ID
                </div>
                <p>Example:</p>
                <div class="code-block">
                    player.php?id=abc123def456
                </div>
            </div>
            
            <div class="demo-section">
                <h2>Embed in Your Website</h2>
                <p>Use an iframe to embed the player:</p>
                <div class="code-block">
&lt;iframe src="player.php?id=YOUR_FILE_ID" 
        width="100%" 
        height="500" 
        frameborder="0" 
        allowfullscreen&gt;
&lt;/iframe&gt;
                </div>
            </div>
            
            <div class="note">
                <strong>📝 Note:</strong> Upload a video file first to get a valid file ID. The player supports MP4, WebM, and other HTML5 video formats.
            </div>
            
            <div style="text-align: center;">
                <a href="upload.php" class="btn-demo">Upload a Video</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

$fileName = htmlspecialchars($file['original_name']);
$mimeType = $file['mime'] ?? 'video/mp4';
$streamUrl = 'stream.php?id=' . urlencode($id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $fileName; ?> - Video Player</title>
    
    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #000;
            color: #fff;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }
        
        .video-container {
            margin-bottom: 20px;
        }
        
        .video-js {
            width: 100%;
            height: auto;
            aspect-ratio: 16/9;
        }
        
        .video-info {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
        }
        
        .video-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
            word-wrap: break-word;
        }
        
        .video-meta {
            color: #aaa;
            font-size: 0.9rem;
        }
        
        .download-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .download-btn:hover {
            background: #0056b3;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .video-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="video-container">
            <video 
                id="my-video" 
                class="video-js vjs-big-play-centered" 
                controls 
                preload="auto"
                data-setup='{"fluid": true, "responsive": true}'
            >
                <source src="<?php echo htmlspecialchars($streamUrl); ?>" type="<?php echo htmlspecialchars($mimeType); ?>">
                <p class="vjs-no-js">
                    To view this video please enable JavaScript, and consider upgrading to a
                    web browser that <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
                </p>
            </video>
        </div>
        
        <div class="video-info">
            <h1 class="video-title"><?php echo $fileName; ?></h1>
            <div class="video-meta">
                <?php if (!empty($file['size'])): ?>
                    <span>Size: <?php echo formatFileSize($file['size']); ?></span>
                <?php endif; ?>
            </div>
            <a href="<?php echo htmlspecialchars($streamUrl); ?>&download=1" class="download-btn">
                Download Video
            </a>
        </div>
    </div>
    
    <!-- Video.js JavaScript -->
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    
    <script>
        // Initialize Video.js player
        var player = videojs('my-video', {
            controls: true,
            autoplay: false,
            preload: 'auto',
            fluid: true,
            responsive: true,
            playbackRates: [0.5, 1, 1.5, 2],
            controlBar: {
                volumePanel: {
                    inline: false
                }
            }
        });
        
        // Add error handling
        player.on('error', function() {
            var error = player.error();
            console.error('Video.js error:', error);
        });
        
        // Log when video is ready
        player.ready(function() {
            console.log('Video player is ready');
        });
    </script>
</body>
</html>
<?php

/**
 * Format file size to human readable format
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
