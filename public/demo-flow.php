<?php
/**
 * Demo Access Flow - Complete user journey
 * Lead → Demo Link → Video (no OTP)
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/demo-link-generator.php';

// Get email from session or URL
$email = $_SESSION['lead_email'] ?? $_GET['email'] ?? '';

// If no email, redirect to lead form
if (empty($email)) {
    header('Location: lead-form.php');
    exit;
}

// Get lead info
$lead = null;
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, email, company_name, status, view_count FROM leads_for_demo WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $lead = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error in demo-flow.php: " . $e->getMessage());
}

if (!$lead) {
    header('Location: lead-form.php');
    exit;
}

$leadId = (int) $lead['id'];

// Auto-verify if still pending (for leads that existed before the OTP removal)
if ($lead['status'] === 'pending') {
    try {
        $pdo->prepare("UPDATE leads_for_demo SET status = 'verified', updated_at = NOW() WHERE id = ?")->execute([$leadId]);
        $lead['status'] = 'verified';
    } catch (Exception $e) {
        error_log("Error auto-verifying lead in demo-flow.php: " . $e->getMessage());
    }
}

$isRescheduled = false;
$rescheduledMessage = '';

// Check if lead's most recent follow-up is rescheduled
try {
    $rescheduledStmt = $pdo->prepare("
        SELECT status, created_at
        FROM demo_followups
        WHERE lead_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $rescheduledStmt->execute([$leadId]);
    $rescheduledResult = $rescheduledStmt->fetch();
    
    $isRescheduled = ($rescheduledResult && $rescheduledResult['status'] === 'rescheduled');
    
    if ($isRescheduled) {
        $rescheduledMessage = 'Your demo has been rescheduled. You can generate a new demo link.';
    }
} catch (Exception $e) {
    error_log("Error checking rescheduled follow-up: " . $e->getMessage());
    $isRescheduled = false;
}

// Check view_count on lead (limit: 2 per email)
$maxAllowedViews = 2;
$totalViews = (int) ($lead['view_count'] ?? 0);
$viewsExhausted = ($totalViews >= $maxAllowedViews);

// Check for active demo link or generate one
$demoToken = null;
$currentStep = 'generate_demo';

if (!$viewsExhausted) {
    $stmt = $pdo->prepare("
        SELECT id, token, status, expires_at, created_at
        FROM demo_links
        WHERE lead_id = ?
          AND status = 'active'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$leadId]);
    $demoLink = $stmt->fetch();

    // Check if existing link is still valid (created_at + 60 min)
    if ($demoLink) {
        $createdTs = @strtotime($demoLink['created_at']);
        $isValid = ($createdTs !== false && time() <= $createdTs + 3600);
        if ($isValid && !$isRescheduled) {
            $currentStep = 'demo';
            $demoToken = $demoLink['token'];
        }
    }

    if ($currentStep !== 'demo') {
        // Auto-generate a new demo link
        if ($isRescheduled && $demoLink) {
            // Invalidate old links
            $pdo->prepare("UPDATE demo_links SET status = 'expired' WHERE lead_id = ? AND status = 'active'")->execute([$leadId]);
        }
        try {
            $token = generateSecureToken(64);
            $tokenHash = hashToken($token);
            createDemoLink($pdo, $leadId, $token, $tokenHash, 1);
            $currentStep = 'demo';
            $demoToken = $token;
        } catch (Exception $e) {
            error_log("Auto-generate demo link failed in demo-flow.php: " . $e->getMessage());
            $currentStep = 'generate_demo';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Access - ThemeStore</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }
        .container {
            background: white; border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px; max-width: 500px; width: 100%;
        }
        h1 { color: #333; margin-bottom: 10px; text-align: center; font-size: 24px; }
        .subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
        .message { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .message.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .demo-link-box {
            background: #f8f9fa; border: 2px dashed #667eea;
            padding: 20px; border-radius: 6px; text-align: center; margin-bottom: 20px;
        }
        .demo-link { font-size: 14px; word-break: break-all; color: #667eea; margin: 10px 0; }
        button {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 6px;
            font-size: 16px; font-weight: 600; cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        .btn-copy { margin-top: 10px; background: #28a745; }
        .btn-watch { margin-top: 10px; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Demo Access</h1>
        <p class="subtitle"><?php echo htmlspecialchars($lead['company_name'], ENT_QUOTES, 'UTF-8'); ?></p>

        <?php if ($isRescheduled && !empty($rescheduledMessage)): ?>
            <div class="message info" style="margin-bottom: 20px;">
                <?php echo htmlspecialchars($rescheduledMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($viewsExhausted): ?>
            <div class="message info" style="background: #f8d7da; border-color: #f5c6cb; color: #721c24;">
                <strong>Demo Access Limit Reached</strong><br><br>
                You have already used your <?php echo $maxAllowedViews; ?> allowed demo views for this email address.
                <br><br>
                If you need additional access, please contact our support team.
            </div>
        <?php elseif ($currentStep === 'demo' && $demoToken): ?>
            <div class="demo-link-box">
                <p style="font-weight: 600; margin-bottom: 10px;">Your Demo Link is Ready!</p>
                <div class="demo-link" id="demo-url"><?php echo htmlspecialchars('public/watch.php?token=' . $demoToken, ENT_QUOTES, 'UTF-8'); ?></div>
                <button type="button" class="btn-copy" onclick="copyDemoLink()">Copy Link</button>
                <button type="button" class="btn-watch" onclick="watchVideo()">Watch Video</button>
            </div>
            <div class="message info">
                This link is valid for <strong>60 minutes</strong> and can be used up to <strong>2 times</strong>.
                <?php $remainingViews = $maxAllowedViews - $totalViews; ?>
                <?php if ($remainingViews < $maxAllowedViews): ?>
                    <br>You have <strong><?php echo $remainingViews; ?></strong> view(s) remaining.
                <?php endif; ?>
                <?php if (defined('VIMEO_VIDEO_PASSWORD') && !empty(VIMEO_VIDEO_PASSWORD)): ?>
                    <br><br>
                    <strong>🔒 Video Password:</strong> The video is password-protected. Check your email for the password.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="message info">
                Unable to generate demo link. Please try again or contact support.
            </div>
            <button type="button" onclick="window.location.reload()">Try Again</button>
        <?php endif; ?>
    </div>

    <script>
        function copyDemoLink() {
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const url = window.location.origin + basePath + '/watch.php?token=<?php echo isset($demoToken) ? htmlspecialchars($demoToken, ENT_QUOTES, 'UTF-8') : ''; ?>';
            navigator.clipboard.writeText(url).then(() => {
                alert('Demo link copied to clipboard!');
            });
        }

        function watchVideo() {
            window.location.href = 'watch.php?token=<?php echo isset($demoToken) ? htmlspecialchars($demoToken, ENT_QUOTES, 'UTF-8') : ''; ?>';
        }
    </script>
</body>
</html>
