<?php
/**
 * Watch Video Page - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Validates token and displays video player
 * Tracks video activity and increments views on first play
 */

// Load required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token-validator.php';

/**
 * Increment demo link views on first play
 */
function incrementDemoLinkViews(int $demoLinkId): bool
{
    try {
        $pdo = getDbConnection();
        
        // Check current views
        $checkStmt = $pdo->prepare("
            SELECT views_count, max_views
            FROM demo_links
            WHERE id = ?
        ");
        $checkStmt->execute([$demoLinkId]);
        $link = $checkStmt->fetch();
        
        if (!$link) {
            return false;
        }
        
        // Only increment if not at max views
        if ($link['views_count'] < $link['max_views']) {
            $stmt = $pdo->prepare("
                UPDATE demo_links
                SET views_count = views_count + 1,
                    accessed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$demoLinkId]);
            
            // Check if max views reached
            if (($link['views_count'] + 1) >= $link['max_views']) {
                $updateStmt = $pdo->prepare("
                    UPDATE demo_links
                    SET status = 'used', updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$demoLinkId]);
            }
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Database error in incrementDemoLinkViews: " . $e->getMessage());
        return false;
    }
}

// Get token from URL (PHP automatically decodes $_GET, just trim)
$token = trim($_GET['token'] ?? '');

// Validate token
$demoLink = false;
$errorMessage = '';
$isExpired = false;
$isUsed = false;

if (empty($token)) {
    $errorMessage = 'Access token is required. Please use a valid demo link.';
} else {
    $demoLink = validateDemoToken($token);
    
    if ($demoLink === false) {
        // Check if token exists but is expired or used
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                SELECT status, expires_at, views_count, max_views
                FROM demo_links
                WHERE token_hash = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            
            // We need to check all tokens to find the matching one
            $allStmt = $pdo->prepare("
                SELECT id, status, expires_at, views_count, max_views, expires_at
                FROM demo_links
            ");
            $allStmt->execute();
            $allLinks = $allStmt->fetchAll();
            
            $foundLink = null;
            foreach ($allLinks as $link) {
                // We can't verify without hash, so we'll check status based on common failure reasons
                // This is a fallback - the validateDemoToken should handle most cases
            }
            
            $isExpired = true;
            $errorMessage = 'Your demo access has expired or the link is no longer valid. Please request a new demo link.';
        } catch (Exception $e) {
            $errorMessage = 'Invalid or expired access token. Please request a new demo link.';
        }
    } else {
        // Allow access as long as token is valid and not expired
        // Multiple views/refreshes are allowed within the 60-minute window
        // No need to check views_count - expiry time is the only restriction
    }
}

// If token is valid and not used, show video
$showVideo = ($demoLink !== false && !$isUsed);

// Get current lead interest (for watch page UI)
$leadInterest = null;
if ($showVideo && $demoLink) {
    try {
        $pdo = getDbConnection();
        $st = $pdo->prepare("SELECT interest FROM leads_for_demo WHERE id = ? LIMIT 1");
        $st->execute([$demoLink['lead_id']]);
        $row = $st->fetch();
        $leadInterest = isset($row['interest']) ? $row['interest'] : null;
    } catch (Exception $e) {
        $leadInterest = null;
    }
}

// Get Vimeo password if configured (needed in both HTML and JavaScript sections)
$vimeoPassword = defined('VIMEO_VIDEO_PASSWORD') && !empty(VIMEO_VIDEO_PASSWORD) ? VIMEO_VIDEO_PASSWORD : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $showVideo ? 'Watch Demo Video' : 'Access Denied'; ?> - ThemeStore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 900px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }
        .video-wrapper {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            outline: none;
        }
        .error-message {
            background: #fee;
            border: 2px solid #fcc;
            color: #c33;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 16px;
            line-height: 1.6;
        }
        .error-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .info-message {
            background: #e3f2fd;
            border: 2px solid #90caf9;
            color: #1565c0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            text-align: center;
        }
        .mobile-notice {
            display: none;
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
            border: 2px solid #ffc107;
            color: #5d4e37;
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .mobile-notice.is-visible {
            display: flex;
        }
        .mobile-notice-icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        .mobile-notice-text {
            flex: 1;
            min-width: 200px;
        }
        .mobile-notice-text strong {
            display: block;
            margin-bottom: 2px;
            color: #4a3f2f;
        }
        .mobile-notice-dismiss {
            background: #ffc107;
            color: #5d4e37;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            flex-shrink: 0;
        }
        .mobile-notice-dismiss:hover {
            background: #ffb300;
        }
        .loading {
            text-align: center;
            color: #666;
            padding: 40px;
            font-size: 16px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($showVideo): ?>
            <h1>ThemeStore Demo Video</h1>

            <!-- Mobile device notice: watch on Desktop/Laptop for better performance -->
            <div id="mobile-notice" class="mobile-notice">
                <span class="mobile-notice-icon">💻</span>
                <div class="mobile-notice-text">
                    <strong>For better performance</strong>
                    Watch this demo on a Desktop or Laptop for the best experience.
                </div>
                <button type="button" id="mobile-notice-dismiss" class="mobile-notice-dismiss" aria-label="Dismiss">Continue on mobile</button>
            </div>

            <div class="video-wrapper">
                <?php
                // Build embed URL with all parameters
                $embedParams = [
                    'autoplay' => 0,
                    'controls' => 1,
                    'muted' => 0,
                    'responsive' => 1,
                    'title' => 0,
                    'byline' => 0,
                    'portrait' => 0,
                    'badge' => 0,
                    'like' => 0,
                    'share' => 0,
                    'watchlater' => 0,
                    // Privacy-friendly mode; also reduces personalized UI like Like/Watch Later
                    'dnt' => 1,
                    'color' => '667eea',
                    'transparent' => 0
                ];
                
                // Add password if configured (Vimeo will prompt for it)
                $embedUrl = VIMEO_EMBED_URL . '?' . http_build_query($embedParams);
                ?>
                <iframe
                    id="vimeoPlayer"
                    src="<?php echo htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    frameborder="0"
                    allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;">
                </iframe>
               
                <div id="vimeo-error" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: white;">
                    <p style="font-size: 18px; margin-bottom: 10px;">Video unavailable</p>
                    <p style="font-size: 14px;">Please check Vimeo video settings to allow embedding</p>
                </div>
            </div>

            <!-- Interest selection (Interested / Not interested) -->
            <div id="interest-section" data-initial-interest="<?php echo $leadInterest ? htmlspecialchars($leadInterest, ENT_QUOTES, 'UTF-8') : ''; ?>" style="margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                <p style="margin: 0 0 12px 0; font-weight: 600; color: #333; font-size: 15px;">Did the demo meet your expectations to move forward?</p>
                <div id="interest-buttons" style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <button type="button" id="btn-interested" class="interest-btn" data-interest="interested" style="padding: 12px 24px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; border: 2px solid #28a745; background: #fff; color: #28a745; transition: all 0.2s;">👍 Interested</button>
                    <button type="button" id="btn-not-interested" class="interest-btn" data-interest="not_interested" style="padding: 12px 24px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; border: 2px solid #dc3545; background: #fff; color: #dc3545; transition: all 0.2s;">👎 Not interested</button>
                </div>
                <p id="interest-feedback" style="display: none; margin: 12px 0 0 0; font-size: 14px; color: #155724; font-weight: 500;"></p>
            </div>

            <div class="info-message">
                <strong>Note:</strong> This demo link is valid for 60 minutes and can be used up to 2 times.
                <?php if (defined('VIMEO_VIDEO_PASSWORD') && !empty(VIMEO_VIDEO_PASSWORD)): ?>
                    <br><br>
                    <strong style="color: #856404;">🔒 Video Password:</strong> The video is password-protected. 
                    Check your email for the password. 
                    <br><span style="font-size: 12px; color: #666;">Enter the password when prompted by the video player.</span>
                <?php endif; ?>
            </div>
            
            <!-- Chatbox for Queries -->
            <div id="chatbox-container" style="position: fixed; bottom: 20px; right: 20px; width: 350px; max-height: 500px; z-index: 1000;">
                <div id="chatbox-toggle" style="background: #667eea; color: white; padding: 15px 20px; border-radius: 25px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px;">
                    <span style="font-weight: 600;">💬 Have Questions?</span>
                    <span id="chatbox-badge" style="background: #ff4444; color: white; border-radius: 50%; width: 20px; height: 20px; display: none; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">!</span>
                </div>
                
                <div id="chatbox-window" style="display: none; background: white; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); margin-top: 10px; overflow: hidden; flex-direction: column; max-height: 450px;">
                    <div style="background: #667eea; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 16px;">Ask Your Questions</h3>
                        <button id="chatbox-close" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer; padding: 0; width: 24px; height: 24px;">×</button>
                    </div>
                    
                    <div id="chatbox-messages" style="padding: 15px; overflow-y: auto; max-height: 300px; min-height: 200px; background: #f8f9fa;">
                        <div style="color: #666; font-size: 13px; text-align: center; padding: 20px;">
                            Ask any questions about the demo. We'll get back to you soon!
                        </div>
                    </div>
                    
                    <div style="padding: 15px; border-top: 1px solid #e0e0e0; background: white;">
                        <textarea id="query-input" placeholder="Type your question here..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; resize: none; min-height: 60px; font-family: inherit; font-size: 14px; margin-bottom: 10px;"></textarea>
                        <button id="send-query-btn" style="width: 100%; padding: 10px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">Send Question</button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <h1>Access Denied</h1>
            <div class="error-message">
                <div class="error-icon">⚠️</div>
                <div><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="info-message">
                If you believe this is an error, please contact support or request a new demo link.
            </div>
        <?php endif; ?>
    </div>

    <?php if ($showVideo): ?>
    <!-- Vimeo Player API -->
    <script src="https://player.vimeo.com/api/player.js"></script>
    <style>
        #chatbox-container {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .chat-message {
            margin-bottom: 15px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.4;
        }
        .chat-message.user {
            background: #667eea;
            color: white;
            margin-left: 20px;
            text-align: right;
        }
        .chat-message.system {
            background: #e9ecef;
            color: #333;
            margin-right: 20px;
            text-align: left;
        }
        #query-input:focus {
            outline: none;
            border-color: #667eea;
        }
        #send-query-btn:hover {
            background: #5568d3 !important;
        }
        #send-query-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .interest-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .interest-btn:disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }
        /* Hide Vimeo player buttons */
        .video-wrapper {
            overflow: hidden;
        }
        /* Hide buttons that might appear outside iframe */
        .video-wrapper iframe + * {
            display: none !important;
        }
    </style>
    <style id="vimeo-button-hider">
        /* This will be injected into iframe if possible */
    </style>
    <script>
        // Mobile device notice: suggest Desktop/Laptop for better performance
        (function() {
            function isMobileDevice() {
                var ua = navigator.userAgent || navigator.vendor || '';
                var mobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|mobile|CriOS|FxiOS/i.test(ua);
                var small = typeof window.matchMedia !== 'undefined' && window.matchMedia('(max-width: 768px)').matches;
                return mobile || small;
            }
            var notice = document.getElementById('mobile-notice');
            var dismissBtn = document.getElementById('mobile-notice-dismiss');
            if (notice && isMobileDevice()) {
                try {
                    if (sessionStorage.getItem('mobile-notice-dismissed')) return;
                } catch (e) {}
                notice.classList.add('is-visible');
                if (dismissBtn) {
                    dismissBtn.addEventListener('click', function() {
                        notice.classList.remove('is-visible');
                        try { sessionStorage.setItem('mobile-notice-dismissed', '1'); } catch (e) {}
                    });
                }
            }
        })();

        (function() {
            const iframe = document.getElementById('vimeoPlayer');
            
            if (!iframe) {
                console.error('Vimeo iframe not found');
                return;
            }
            
            // Wait for iframe to load before initializing player
            let player = null;
            let playerInitialized = false;
            let hasPlayed = false;
            let viewTracked = false;
            let progressTrackingInterval = null;
            let lastTrackedProgress = 0;
            
            function initializePlayer() {
                if (playerInitialized) return;
                
                try {
                    player = new Vimeo.Player(iframe);
                    playerInitialized = true;
                    
                    // Wait for player to be ready before setting up events
                    // Add a delay to ensure player is fully initialized
                    setTimeout(function() {
                        if (player) {
                            player.ready().then(function() {
                                console.log('Vimeo player ready');
                                setupPlayerEvents();
                            }).catch(function(error) {
                                console.error('Player ready error:', error);
                                // Still try to setup events after a delay
                                setTimeout(setupPlayerEvents, 2000);
                            });
                        }
                    }, 1500);
                } catch (error) {
                    console.error('Error initializing Vimeo player:', error);
                    // Retry after a short delay
                    setTimeout(initializePlayer, 1000);
                }
            }
            
            // Wait for iframe to load before initializing
            iframe.addEventListener('load', function() {
                setTimeout(initializePlayer, 2000);
            });
            
            // Also try to initialize after DOM is ready (with longer delay)
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(initializePlayer, 2000);
                });
            } else {
                setTimeout(initializePlayer, 2000);
            }
            
            function setupPlayerEvents() {
                if (!player) {
                    console.error('Player not initialized');
                    return;
                }
                
                setupVideoTracking();
            }
            
            function setupVideoTracking() {
                if (!player) return;
                
                // Track first play
                player.on('play', function() {
                if (!hasPlayed && !viewTracked) {
                    hasPlayed = true;
                    viewTracked = true;
                    
                    // Increment views on first play
                    fetch('../api/track-view.php?token=<?php echo htmlspecialchars(urlencode($token), ENT_QUOTES, 'UTF-8'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    }).catch(function(error) {
                        console.error('Error tracking view:', error);
                    });
                    
                    // Track video start
                    trackVideoEvent('started', 0, 0);
                    
                    // Start progress tracking
                    progressTrackingInterval = setInterval(function() {
                        player.getCurrentTime().then(function(seconds) {
                            player.getDuration().then(function(duration) {
                                const progress = (seconds / duration) * 100;
                                const currentTime = Math.floor(seconds);
                                
                                // Track progress every 15 seconds
                                if (currentTime >= lastTrackedProgress + 15) {
                                    lastTrackedProgress = Math.floor(currentTime / 15) * 15;
                                    trackVideoEvent('progress', progress, currentTime);
                                }
                            });
                        });
                    }, 1000);
                }
            });
            
                // Track video completion
                player.on('ended', function() {
                    if (!player) return;
                    player.getDuration().then(function(duration) {
                        trackVideoEvent('completed', 100, Math.floor(duration));
                        if (progressTrackingInterval) {
                            clearInterval(progressTrackingInterval);
                        }
                    }).catch(function(error) {
                        console.error('Error getting duration:', error);
                    });
                });
                
                // Handle errors
                player.on('error', function(error) {
                    console.error('Vimeo player error:', error);
                    const errorDiv = document.getElementById('vimeo-error');
                    if (errorDiv) {
                        errorDiv.style.display = 'block';
                    }
                });
            }
            
            // Track video events
            function trackVideoEvent(eventType, progressPercentage, durationWatched) {
                fetch('../api/track-video-activity.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: '<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>',
                        event_type: eventType,
                        progress_percentage: progressPercentage,
                        duration_watched: durationWatched
                    })
                }).catch(function(error) {
                    console.error('Error tracking video event:', error);
                });
            }
            
            
            // Hide Vimeo buttons using CSS injection (if cross-origin allows)
            function hideVimeoButtons() {
                try {
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    if (iframeDoc) {
                        // Create style element to hide buttons
                        const style = iframeDoc.createElement('style');
                        style.textContent = `
                            /* Hide like button */
                            button[aria-label*="like" i],
                            button[aria-label*="Like" i],
                            .vp-controls button[data-title*="like" i],
                            .vp-controls button[data-title*="Like" i],
                            a[href*="like"],
                            /* Hide share button */
                            button[aria-label*="share" i],
                            button[aria-label*="Share" i],
                            .vp-controls button[data-title*="share" i],
                            .vp-controls button[data-title*="Share" i],
                            a[href*="share"],
                            /* Hide watch later button */
                            button[aria-label*="watch later" i],
                            button[aria-label*="Watch Later" i],
                            .vp-controls button[data-title*="watch later" i],
                            .vp-controls button[data-title*="Watch Later" i],
                            a[href*="watch-later"],
                            /* Hide social buttons container */
                            .vp-social,
                            .vp-social-buttons,
                            .vp-controls-social {
                                display: none !important;
                                visibility: hidden !important;
                                opacity: 0 !important;
                                width: 0 !important;
                                height: 0 !important;
                                overflow: hidden !important;
                            }
                        `;
                        iframeDoc.head.appendChild(style);
                    }
                } catch(e) {
                    // Cross-origin restriction - expected, buttons will be hidden by URL params
                    console.log('Cannot access iframe content (cross-origin) - buttons hidden via URL parameters');
                }
            }
            
           // Try to hide buttons after iframe loads
iframe.addEventListener('load', function() {
    setTimeout(hideVimeoButtons, 1000);
    setTimeout(hideVimeoButtons, 3000);
    setTimeout(hideVimeoButtons, 5000);
});

// Remove this block completely (or comment it out):
// // Also try when player is ready
// player.ready().then(function() {
//     setTimeout(hideVimeoButtons, 1000);
// });
        })();
        
        // Chatbox functionality
        (function() {
            const chatboxToggle = document.getElementById('chatbox-toggle');
            const chatboxWindow = document.getElementById('chatbox-window');
            const chatboxClose = document.getElementById('chatbox-close');
            const queryInput = document.getElementById('query-input');
            const sendBtn = document.getElementById('send-query-btn');
            const messagesContainer = document.getElementById('chatbox-messages');
            const token = '<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>';
            
            let isOpen = false;
            
            chatboxToggle.addEventListener('click', function() {
                isOpen = !isOpen;
                chatboxWindow.style.display = isOpen ? 'flex' : 'none';
                if (isOpen) {
                    queryInput.focus();
                }
            });
            
            chatboxClose.addEventListener('click', function() {
                isOpen = false;
                chatboxWindow.style.display = 'none';
            });
            
            function addMessage(text, isUser) {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'chat-message ' + (isUser ? 'user' : 'system');
                messageDiv.textContent = text;
                messagesContainer.appendChild(messageDiv);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            
            function sendQuery() {
                const query = queryInput.value.trim();
                
                if (!query) {
                    alert('Please enter your question');
                    return;
                }
                
                sendBtn.disabled = true;
                sendBtn.textContent = 'Sending...';
                
                const formData = new FormData();
                formData.append('token', token);
                formData.append('query', query);
                
                fetch('../api/save-query.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addMessage(query, true);
                        addMessage('Thank you! Your question has been submitted. We will get back to you soon.', false);
                        queryInput.value = '';
                    } else {
                        alert('Error: ' + (data.message || 'Failed to send question'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                })
                .finally(() => {
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Send Question';
                });
            }
            
            sendBtn.addEventListener('click', sendQuery);
            
            queryInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendQuery();
                }
            });
        })();

        // Interest (Interested / Not interested) buttons
        (function() {
            const section = document.getElementById('interest-section');
            const buttons = document.querySelectorAll('.interest-btn');
            const feedback = document.getElementById('interest-feedback');
            const buttonsWrap = document.getElementById('interest-buttons');
            const token = '<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>';
            const initial = (section && section.getAttribute('data-initial-interest')) || '';

            function setSelected(value) {
                buttons.forEach(function(btn) {
                    btn.disabled = true;
                });
                if (buttonsWrap) buttonsWrap.style.display = 'none';
                if (feedback) {
                    feedback.style.display = 'block';
                    feedback.textContent = 'You selected: ' + (value === 'interested' ? '👍 Interested' : '👎 Not interested');
                    feedback.style.color = value === 'interested' ? '#155724' : '#721c24';
                }
            }

            if (initial === 'interested' || initial === 'not_interested') {
                setSelected(initial);
                return;
            }

            buttons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const interest = btn.getAttribute('data-interest');
                    if (!interest || !token) return;
                    btn.disabled = true;
                    buttons.forEach(function(b) { b.disabled = true; });

                    const formData = new FormData();
                    formData.append('token', token);
                    formData.append('interest', interest);

                    fetch('../api/save-interest.php', { method: 'POST', body: formData })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                setSelected(interest);
                            } else {
                                alert('Error: ' + (data.message || 'Could not save.'));
                                buttons.forEach(function(b) { b.disabled = false; });
                            }
                        })
                        .catch(function(err) {
                            console.error(err);
                            alert('An error occurred. Please try again.');
                            buttons.forEach(function(b) { b.disabled = false; });
                        });
                });
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>

