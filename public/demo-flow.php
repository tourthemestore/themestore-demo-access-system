<?php
/**
 * Demo Access Flow - Complete user journey
 * Lead → OTP → Demo Link → Video
 */

session_start();
require_once __DIR__ . '/../config/config.php';

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
    $stmt = $pdo->prepare("SELECT id, email, company_name, status FROM leads_for_demo WHERE email = ? LIMIT 1");
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
$currentStep = 'otp'; // otp, verify, demo, video
$isRescheduled = false;
$rescheduledMessage = '';

// Check if lead's most recent follow-up is rescheduled
// Only allow re-registration if the LATEST follow-up has status 'rescheduled'
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
    
    // Only set as rescheduled if the most recent follow-up is rescheduled
    $isRescheduled = ($rescheduledResult && $rescheduledResult['status'] === 'rescheduled');
    
    if ($isRescheduled) {
        $rescheduledMessage = 'Your demo has been rescheduled. You can generate a new demo link.';
    }
} catch (Exception $e) {
    error_log("Error checking rescheduled follow-up: " . $e->getMessage());
    $isRescheduled = false;
}

// Check current status
$demoToken = null;
if ($lead['status'] === 'verified') {
    // Check if there is an active, non-expired demo link
    $stmt = $pdo->prepare("
        SELECT id, token, status, expires_at 
        FROM demo_links 
        WHERE lead_id = ? 
          AND status = 'active' 
          AND expires_at > NOW()
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$leadId]);
    $demoLink = $stmt->fetch();
    
    if ($isRescheduled) {
        $currentStep = 'generate_demo';
    } elseif ($demoLink) {
        $currentStep = 'demo';
        $demoToken = $demoLink['token'];
    } else {
        // Auto-generate demo link so user goes directly to "Your Demo Link is Ready!" (no button click)
        require_once __DIR__ . '/../includes/demo-link-generator.php';
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
            max-width: 500px;
            width: 100%;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }
        .step {
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e0e0e0;
            position: relative;
            z-index: 1;
            font-weight: 600;
        }
        .step.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .step.completed {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
            font-size: 24px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="email"],
        input[type="number"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .message.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .demo-link-box {
            background: #f8f9fa;
            border: 2px dashed #667eea;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
        }
        .demo-link {
            font-size: 14px;
            word-break: break-all;
            color: #667eea;
            margin: 10px 0;
        }
        .btn-copy {
            margin-top: 10px;
            background: #28a745;
        }
        .btn-watch {
            margin-top: 10px;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step <?php echo $currentStep === 'otp' || $currentStep === 'verify' ? 'active' : 'completed'; ?>">1</div>
            <div class="step <?php echo $currentStep === 'generate_demo' || $currentStep === 'demo' ? 'active' : ($lead['status'] === 'verified' ? 'completed' : ''); ?>">2</div>
            <div class="step <?php echo $currentStep === 'video' ? 'active' : ''; ?>">3</div>
        </div>

        <h1>Demo Access</h1>
        <p class="subtitle"><?php echo htmlspecialchars($lead['company_name'], ENT_QUOTES, 'UTF-8'); ?></p>

        <!-- OTP Step -->
        <div id="otp-step" class="<?php echo $currentStep === 'otp' || $currentStep === 'verify' ? '' : 'hidden'; ?>">
            <h2 style="font-size: 18px; margin-bottom: 20px;">Step 1: Verify Your Email</h2>
            <div id="otp-message"></div>
            
            <div id="send-otp-section">
                <p style="margin-bottom: 20px; color: #666;">We'll send a 6-digit OTP to your email address.</p>
                <button type="button" onclick="sendOTP()">Send OTP</button>
            </div>

            <div id="verify-otp-section" class="hidden">
                <div class="form-group">
                    <label>Enter OTP</label>
                    <input type="number" id="otp-input" placeholder="000000" maxlength="6" min="0" max="999999">
                </div>
                <button type="button" onclick="verifyOTP()">Verify OTP</button>
                <button type="button" onclick="resendOTP()" style="margin-top: 10px; background: #6c757d;">Resend OTP</button>
            </div>
        </div>

        <!-- Demo Link Step -->
        <div id="demo-step" class="<?php echo $currentStep === 'generate_demo' || $currentStep === 'demo' ? '' : 'hidden'; ?>">
            <h2 style="font-size: 18px; margin-bottom: 20px;">Step 2: Get Your Demo Link</h2>
            <div id="demo-message"></div>
            
            <?php if ($isRescheduled && !empty($rescheduledMessage)): ?>
                <div class="message info" style="margin-bottom: 20px;">
                    <?php echo htmlspecialchars($rescheduledMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($currentStep === 'generate_demo'): ?>
                <p style="margin-bottom: 20px; color: #666;">
                    <?php if ($isRescheduled): ?>
                        Your demo has been rescheduled. Generate a new demo access link below.
                    <?php else: ?>
                        Generate your demo access link.
                    <?php endif; ?>
                </p>
                <button type="button" onclick="generateDemoLink()">
                    <?php echo $isRescheduled ? 'Generate New Demo Link' : 'Generate Demo Link'; ?>
                </button>
            <?php elseif ($currentStep === 'demo' && isset($demoToken)): ?>
                <div class="demo-link-box">
                    <p style="font-weight: 600; margin-bottom: 10px;">Your Demo Link is Ready!</p>
                    <div class="demo-link" id="demo-url"><?php echo htmlspecialchars('public/watch.php?token=' . $demoToken, ENT_QUOTES, 'UTF-8'); ?></div>
                    <button type="button" class="btn-copy" onclick="copyDemoLink()">Copy Link</button>
                    <button type="button" class="btn-watch" onclick="watchVideo()">Watch Video</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Video Step -->
        <div id="video-step" class="<?php echo $currentStep === 'video' ? '' : 'hidden'; ?>">
            <h2 style="font-size: 18px; margin-bottom: 20px;">Step 3: Watch Demo Video</h2>
            <p>Redirecting to video...</p>
        </div>
    </div>

    <script>
        const email = '<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>';

        function showMessage(elementId, message, type) {
            const el = document.getElementById(elementId);
            el.className = 'message ' + type;
            el.textContent = message;
            el.style.display = 'block';
        }

        function sendOTP() {
            showMessage('otp-message', 'Sending OTP...', 'info');
            
            fetch('../api/send-otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('otp-message', data.message || 'OTP sent to your email! Please check your inbox.', 'success');
                    document.getElementById('send-otp-section').classList.add('hidden');
                    document.getElementById('verify-otp-section').classList.remove('hidden');
                } else {
                    showMessage('otp-message', data.message || 'Failed to send OTP', 'error');
                }
            })
            .catch(error => {
                showMessage('otp-message', 'Error sending OTP. Please try again.', 'error');
            });
        }

        function verifyOTP() {
            const otp = document.getElementById('otp-input').value;
            
            if (!otp || otp.length !== 6) {
                showMessage('otp-message', 'Please enter a valid 6-digit OTP', 'error');
                return;
            }

            showMessage('otp-message', 'Verifying OTP...', 'info');
            
            fetch('../api/verify-otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email, otp: otp })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('otp-message', 'OTP verified successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage('otp-message', data.message || 'Invalid OTP', 'error');
                }
            })
            .catch(error => {
                showMessage('otp-message', 'Error verifying OTP. Please try again.', 'error');
            });
        }

        function resendOTP() {
            document.getElementById('verify-otp-section').classList.add('hidden');
            document.getElementById('send-otp-section').classList.remove('hidden');
            document.getElementById('otp-input').value = '';
            sendOTP();
        }

        function generateDemoLink() {
            showMessage('demo-message', 'Generating demo link...', 'info');
            
            fetch('../api/generate-demo-link.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    showMessage('demo-message', data.message || 'Failed to generate demo link', 'error');
                }
            })
            .catch(error => {
                showMessage('demo-message', 'Error generating demo link. Please try again.', 'error');
            });
        }

        function copyDemoLink() {
            // Get base path and construct watch.php URL
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

