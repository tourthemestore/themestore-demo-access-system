<?php
/**
 * Lead Form - ThemeStore Demo Access System
 * PHP 8 - No Framework
 */

session_start();

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Show detailed errors when ?debug=1 or form submitted with debug (remove in production)
$showDebugErrors = (isset($_GET['debug']) && $_GET['debug'] === '1') 
    || (isset($_POST['debug']) && $_POST['debug'] === '1');

// CSRF Token generation and validation
function generateCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Validation functions
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateMobile(string $mobile): bool
{
    // Remove spaces, dashes, and parentheses
    $cleaned = preg_replace('/[\s\-\(\)]/', '', $mobile);
    // Check if it contains only digits and optional + at start
    return preg_match('/^\+?\d{10,15}$/', $cleaned) === 1;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Load PHPMailer if available
$phpmailerAvailable = false;
$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorPath)) {
    try {
        require_once $vendorPath;
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $phpmailerAvailable = true;
        }
    } catch (Exception $e) {
        error_log("PHPMailer autoload failed: " . $e->getMessage());
    }
}

/**
 * Send email with demo link + video password (replaces OTP email)
 */
function sendDemoLinkEmail(string $email, string $name, string $demoUrl): bool
{
    global $phpmailerAvailable;

    $vimeoPassword = defined('VIMEO_VIDEO_PASSWORD') && !empty(VIMEO_VIDEO_PASSWORD) ? VIMEO_VIDEO_PASSWORD : null;

    $subject = 'Your ThemeStore Demo Access Link';

    $passwordSection = '';
    if ($vimeoPassword) {
        $passwordSection = "
            <div style='background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                <h3 style='color: #856404; margin-top: 0;'>🔒 Video Password</h3>
                <p style='color: #856404; margin-bottom: 10px;'>The demo video is password-protected. Enter this password when the video player prompts you.</p>
                <div style='background: #fff; border: 2px dashed #ffc107; padding: 15px; text-align: center; margin: 15px 0; border-radius: 6px;'>
                    <p style='margin: 0; color: #856404; font-size: 14px; font-weight: bold;'>Video Password:</p>
                    <p style='margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #667eea; letter-spacing: 3px;'>" . htmlspecialchars($vimeoPassword, ENT_QUOTES, 'UTF-8') . "</p>
                </div>
            </div>";
    }

    $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Your Demo Access Link</h2>
                <p>Hello " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ",</p>
                <p>Thank you for your interest in ThemeStore. Here is your demo access link:</p>
                <div style='background: #f4f4f4; border: 2px dashed #667eea; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px;'>
                    <a href='" . htmlspecialchars($demoUrl, ENT_QUOTES, 'UTF-8') . "' style='color: #667eea; font-size: 16px; font-weight: bold; word-break: break-all;'>Click here to watch the demo</a>
                </div>
                <p><strong>Note:</strong> This link is valid for <strong>3 hours</strong> and can be used up to <strong>3 times</strong>.</p>
                {$passwordSection}
                <p>If you did not request this, please ignore this email.</p>
                <hr>
                <p style='font-size: 12px; color: #666;'>This is an automated message. Please do not reply to this email.</p>
            </div>
        </body>
        </html>";

    $textBody = "Hello {$name},\n\nYour ThemeStore demo access link: {$demoUrl}\n\nThis link is valid for 3 hours and can be used up to 3 times.";
    if ($vimeoPassword) {
        $textBody .= "\n\nVideo Password: {$vimeoPassword}\nEnter this password when the video player prompts you.";
    }

    if ($phpmailerAvailable) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0;
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            $mail->send();
            error_log("Demo link email sent to: {$email}");
            return true;
        } catch (\Exception $e) {
            error_log("PHPMailer error sending demo link email: " . ($mail->ErrorInfo ?? $e->getMessage()));
        }
    }

    // Fallback to PHP mail()
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    return mail($email, $subject, $htmlBody, $headers);
}

// Initialize variables
$errors = [];
$success = false;
$formData = [
    'company_name' => '',
    'location' => '',
    'email' => '',
    'mobile' => '',
    'campaign_source' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {
        // Get and sanitize form data
        $formData['company_name'] = sanitizeInput($_POST['company_name'] ?? '');
        $formData['location'] = sanitizeInput($_POST['location'] ?? '');
        $formData['email'] = sanitizeInput($_POST['email'] ?? '');
        $formData['mobile'] = sanitizeInput($_POST['mobile'] ?? '');
        $formData['campaign_source'] = sanitizeInput($_POST['campaign_source'] ?? '');

        // Validate email first
        if (empty($formData['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!validateEmail($formData['email'])) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($formData['email']) > 255) {
            $errors[] = 'Email must not exceed 255 characters.';
        }

        // Validate email exists in enquiry_master (required for demo access)
        if (empty($errors)) {
            try {
                $pdo = getDbConnection();
                $enqStmt = $pdo->prepare("SELECT mobile_no, company_name, city FROM enquiry_master WHERE email_id = ? LIMIT 1");
                $enqStmt->execute([$formData['email']]);
                $enquiry = $enqStmt->fetch();
                if (!$enquiry) {
                    $errors[] = 'This email is not registered with us.';
                } else {
                    // Use enquiry_master data (overrides any submitted values)
                    $formData['mobile'] = trim($enquiry['mobile_no'] ?? '');
                    $formData['company_name'] = trim($enquiry['company_name'] ?? '');
                    $formData['location'] = trim($enquiry['city'] ?? '');
                }
            } catch (Throwable $e) {
                error_log("lead-form enquiry_master check: " . $e->getMessage());
                $errors[] = ($showDebugErrors || ini_get('display_errors'))
                    ? 'Unable to validate email: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                    : 'Unable to validate email. Please try again later.';
            }
        }

        // Contact no, company name, city are non-mandatory (from enquiry_master when found)
        if (!empty($formData['mobile']) && !validateMobile($formData['mobile'])) {
            $errors[] = 'Please enter a valid mobile number (10-15 digits).';
        } elseif (strlen($formData['mobile']) > 20) {
            $errors[] = 'Mobile number must not exceed 20 characters.';
        }
        if (strlen($formData['company_name']) > 255) {
            $errors[] = 'Company name must not exceed 255 characters.';
        }
        if (strlen($formData['location']) > 255) {
            $errors[] = 'City must not exceed 255 characters.';
        }

        if (strlen($formData['campaign_source']) > 255) {
            $errors[] = 'Campaign source must not exceed 255 characters.';
        }

        // If no validation errors, save to database
        if (empty($errors)) {
            try {
                // Get database connection
                $pdo = getDbConnection();

                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id, status, view_count FROM leads_for_demo WHERE email = ?");
                $stmt->execute([$formData['email']]);
                $existingLead = $stmt->fetch();
                if ($existingLead) {
                    // Check view_count on lead record (allow 4 when rescheduled)
                    $existingViewCount = (int) ($existingLead['view_count'] ?? 0);
                    $reschedStmt = $pdo->prepare("SELECT status FROM demo_followups WHERE lead_id = ? ORDER BY created_at DESC, id DESC LIMIT 1");
                    $reschedStmt->execute([$existingLead['id']]);
                    $reschedRow = $reschedStmt->fetch();
                    $isRescheduled = ($reschedRow && $reschedRow['status'] === 'rescheduled');
                    $maxViews = $isRescheduled ? 4 : 3;

                    if ($existingViewCount >= $maxViews) {
                        $errors[] = 'This email has already used the maximum allowed demo views (' . $maxViews . '). Please contact support if you need additional access.';
                    } else {
                        // Still has views remaining — redirect to demo flow
                        $_SESSION['lead_email'] = $formData['email'];
                        header("Location: demo-flow.php?email=" . urlencode($formData['email']));
                        exit;
                    }
                } else {
                    // Generate next ID in code (no AUTO_INCREMENT on table)
                    $maxIdStmt = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM leads_for_demo");
                    $nextId = (int) $maxIdStmt->fetchColumn();

                    // Insert lead as verified (no OTP step)
                    $stmt = $pdo->prepare("
                        INSERT INTO leads_for_demo (id, company_name, location, email, mobile, campaign_source, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'verified')
                    ");
                    $stmt->execute([
                        $nextId,
                        $formData['company_name'] ?: '',
                        $formData['location'] ?: '',
                        $formData['email'],
                        $formData['mobile'] ?: '',
                        $formData['campaign_source'] ?: null
                    ]);

                    $newLeadId = $nextId;

                    // Auto-generate demo link
                    require_once __DIR__ . '/../includes/demo-link-generator.php';
                    $token = generateSecureToken(64);
                    $tokenHash = hashToken($token);
                    createDemoLink($pdo, $newLeadId, $token, $tokenHash, 3);

                    // Build demo watch URL
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                    $demoUrl = $protocol . '://' . $host . $basePath . '/watch.php?token=' . urlencode($token);

                    // Send email with demo link + video password
                    sendDemoLinkEmail(
                        $formData['email'],
                        $formData['company_name'],
                        $demoUrl
                    );

                    $success = true;
                    // Store email in session for demo flow
                    $_SESSION['lead_email'] = $formData['email'];
                    // Regenerate CSRF token after successful submission
                    unset($_SESSION['csrf_token']);
                    
                    // Redirect to demo flow after 2 seconds
                    header("Refresh: 2; url=demo-flow.php?email=" . urlencode($formData['email']));
                }
            } catch (Throwable $e) {
                error_log("lead-form.php error: " . $e->getMessage());
                error_log("lead-form.php trace: " . $e->getTraceAsString());
                $errors[] = ($showDebugErrors || ini_get('display_errors'))
                    ? 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                    : 'An error occurred while processing your request. Please try again later.';
            }
        }
    }
}

// Generate CSRF token for form
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Form - ThemeStore Demo Access</title>
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
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
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
        .required {
            color: #e74c3c;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus {
            outline: none;
            border-color: #667eea;
        }
        input[readonly] {
            background: #f5f5f5;
            color: #666;
            cursor: not-allowed;
        }
        .field-hint {
            font-size: 12px;
            color: #28a745;
            margin-top: 4px;
        }
        .field-error {
            font-size: 12px;
            color: #dc3545;
            margin-top: 4px;
        }
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .error-message ul {
            margin-left: 20px;
        }
        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        button[type="submit"] {
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
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        button[type="submit"]:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Request Demo Access</h1>

        <?php if ($success): ?>
            <div class="success-message">
                Thank you! Your demo access link has been sent to your email.<br>
                Redirecting to your demo...
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate id="leadForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($showDebugErrors): ?><input type="hidden" name="debug" value="1"><?php endif; ?>

            <div class="form-group">
                <label for="email">
                    Email <span class="required">*</span>
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>"
                    required
                    maxlength="255"
                    placeholder="Enter your registered email"
                    autocomplete="email"
                >
                <div id="email-hint" class="field-hint" style="display:none;"></div>
                <div id="email-error" class="field-error" style="display:none;"></div>
            </div>

            <div class="form-group">
                <label for="mobile">Contact No</label>
                <input 
                    type="tel" 
                    id="mobile" 
                    name="mobile" 
                    value="<?php echo htmlspecialchars($formData['mobile'], ENT_QUOTES, 'UTF-8'); ?>"
                    maxlength="20"
                    placeholder="e.g., +1234567890"
                    readonly
                >
            </div>

            <div class="form-group">
                <label for="company_name">Company Name</label>
                <input 
                    type="text" 
                    id="company_name" 
                    name="company_name" 
                    value="<?php echo htmlspecialchars($formData['company_name'], ENT_QUOTES, 'UTF-8'); ?>"
                    maxlength="255"
                    readonly
                >
            </div>

            <div class="form-group">
                <label for="location">City</label>
                <input 
                    type="text" 
                    id="location" 
                    name="location" 
                    value="<?php echo htmlspecialchars($formData['location'], ENT_QUOTES, 'UTF-8'); ?>"
                    maxlength="255"
                    readonly
                >
            </div>

            <input 
                type="hidden" 
                name="campaign_source" 
                value="<?php echo htmlspecialchars($formData['campaign_source'], ENT_QUOTES, 'UTF-8'); ?>"
            >

            <button type="submit" id="submitBtn">Submit</button>
        </form>

        <script>
        (function() {
            const emailInput = document.getElementById('email');
            const mobileInput = document.getElementById('mobile');
            const companyInput = document.getElementById('company_name');
            const cityInput = document.getElementById('location');
            const emailHint = document.getElementById('email-hint');
            const emailError = document.getElementById('email-error');
            const submitBtn = document.getElementById('submitBtn');
            let emailValidated = false;

            function clearFrozenFields() {
                mobileInput.value = '';
                companyInput.value = '';
                cityInput.value = '';
            }

            function checkEmail() {
                const email = emailInput.value.trim();
                emailHint.style.display = 'none';
                emailError.style.display = 'none';
                emailValidated = false;

                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    clearFrozenFields();
                    return;
                }

                submitBtn.disabled = true;
                fetch('../api/check-enquiry-email.php?email=' + encodeURIComponent(email))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.found) {
                            mobileInput.value = data.mobile_no || '';
                            companyInput.value = data.company_name || '';
                            cityInput.value = data.city || '';
                            emailHint.textContent = 'Details loaded from our records.';
                            emailHint.style.display = 'block';
                            emailHint.style.color = '#28a745';
                            emailValidated = true;
                        } else {
                            clearFrozenFields();
                            emailError.textContent = 'This email is not registered with us.';
                            emailError.style.display = 'block';
                        }
                    })
                    .catch(function() {
                        clearFrozenFields();
                        emailError.textContent = 'Could not verify email. Please try again.';
                        emailError.style.display = 'block';
                    })
                    .finally(function() {
                        submitBtn.disabled = false;
                    });
            }

            emailInput.addEventListener('blur', function() {
                checkEmail();
            });

            emailInput.addEventListener('input', function() {
                emailHint.style.display = 'none';
                emailError.style.display = 'none';
                clearFrozenFields();
            });
        })();
        </script>
    </div>
</body>
</html>

