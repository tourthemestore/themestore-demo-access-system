<?php
/**
 * Send OTP Controller - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Generates, hashes, stores, and emails OTP with 10-minute expiry
 * Limits OTP attempts to 3
 */

header('Content-Type: application/json');

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Load PHPMailer if available, otherwise use PHP mail() function
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
} else {
    error_log("PHPMailer not found at: {$vendorPath}");
}

/**
 * Generate 6-digit OTP
 */
function generateOtp(): string
{
    return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Hash OTP using password_hash
 */
function hashOtp(string $otp): string
{
    return password_hash($otp, PASSWORD_DEFAULT);
}

/**
 * Find lead by email
 */
function findLeadByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare("SELECT id, email, company_name FROM leads_for_demo WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $lead = $stmt->fetch();
    return $lead ?: null;
}

/**
 * Check existing OTP and attempts
 */
function checkExistingOtp(PDO $pdo, int $leadId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, attempts, max_attempts, status, expires_at
        FROM otp_verifications
        WHERE lead_id = ? 
        AND status IN ('pending', 'failed')
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$leadId]);
    return $stmt->fetch() ?: null;
}

/**
 * Invalidate old pending and failed OTPs for a lead (before sending a new one).
 * Must invalidate both 'pending' and 'failed' so that on "Resend OTP" after an
 * invalid attempt we don't leave the failed OTP in play; the new OTP gets a
 * full 10-minute window.
 */
function invalidateOldOtps(PDO $pdo, int $leadId): void
{
    $stmt = $pdo->prepare("
        UPDATE otp_verifications
        SET status = 'expired'
        WHERE lead_id = ?
        AND status IN ('pending', 'failed')
    ");
    $stmt->execute([$leadId]);
}

/**
 * Create new OTP record
 * Expiry is always 10 minutes from now, using IST for consistency with DB.
 */
function createOtpRecord(PDO $pdo, int $leadId, string $otp, string $otpHash): int
{
    $tz = new DateTimeZone('Asia/Kolkata');
    $expires = (new DateTime('now', $tz))->modify('+600 seconds');
    $expiresAt = $expires->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        INSERT INTO otp_verifications (
            lead_id, otp_code, otp_hash, attempts, max_attempts, 
            status, expires_at
        ) VALUES (?, ?, ?, 0, 3, 'pending', ?)
    ");
    $stmt->execute([$leadId, $otp, $otpHash, $expiresAt]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Send OTP via email using PHPMailer or PHP mail() fallback
 */
function sendOtpEmail(string $email, string $name, string $otp): bool
{
    global $phpmailerAvailable;
    
    // Check if Vimeo password is configured
    $vimeoPassword = defined('VIMEO_VIDEO_PASSWORD') && !empty(VIMEO_VIDEO_PASSWORD) ? VIMEO_VIDEO_PASSWORD : null;
    
    $subject = 'Your OTP for ThemeStore Demo Access';
    
    // Build password section if configured
    $passwordSection = '';
    if ($vimeoPassword) {
        $passwordSection = "
                <div style='background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                    <h3 style='color: #856404; margin-top: 0;'>🔒 Video Password Required</h3>
                    <p style='color: #856404; margin-bottom: 10px;'><strong>Important:</strong> The demo video is password-protected. You will need to enter the password when the video player loads.</p>
                    <div style='background: #fff; border: 2px dashed #ffc107; padding: 15px; text-align: center; margin: 15px 0; border-radius: 6px;'>
                        <p style='margin: 0; color: #856404; font-size: 14px; font-weight: bold;'>Video Password:</p>
                        <p style='margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #667eea; letter-spacing: 3px;'>" . htmlspecialchars($vimeoPassword, ENT_QUOTES, 'UTF-8') . "</p>
                    </div>
                    <p style='color: #856404; margin-top: 10px; margin-bottom: 0; font-size: 13px;'>Please keep this password secure and do not share it with anyone.</p>
                </div>
        ";
    }
    
    $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .otp-box { background: #f4f4f4; border: 2px dashed #667eea; 
                           padding: 20px; text-align: center; margin: 20px 0; 
                           font-size: 32px; font-weight: bold; color: #667eea; 
                           letter-spacing: 5px; }
                .warning { color: #e74c3c; font-size: 14px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Your OTP for Demo Access</h2>
                <p>Hello " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ",</p>
                <p>Your One-Time Password (OTP) for accessing the ThemeStore demo is:</p>
                <div class='otp-box'>" . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . "</div>
                <p>This OTP will expire in <strong>10 minutes</strong>.</p>
                " . $passwordSection . "
                <p class='warning'><strong>Important:</strong> Do not share this OTP with anyone. You have a maximum of 3 attempts to verify.</p>
                <p>If you did not request this OTP, please ignore this email.</p>
                <hr>
                <p style='font-size: 12px; color: #666;'>This is an automated message. Please do not reply to this email.</p>
            </div>
        </body>
        </html>
    ";
    
    $textBody = "Your OTP for ThemeStore Demo Access is: {$otp}. This OTP will expire in 10 minutes. You have a maximum of 3 attempts to verify.";
    if ($vimeoPassword) {
        $textBody .= "\n\nIMPORTANT: The demo video is password-protected. Video Password: {$vimeoPassword}\nPlease enter this password when the video player loads. Keep this password secure and do not share it.";
    }
    
    if ($phpmailerAvailable) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 2; // Enable debugging to see what's happening
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug: $str");
            };
            
            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($email, $name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            
            $mail->send();
            error_log("OTP email sent successfully to: {$email}");
            return true;
        } catch (\Exception $e) {
            $errorMsg = $mail->ErrorInfo ?? $e->getMessage();
            error_log("PHPMailer error in send-otp.php: " . $errorMsg);
            error_log("SMTP Config - Host: " . SMTP_HOST . ", Port: " . SMTP_PORT . ", User: " . SMTP_USER);
            // Fall through to PHP mail() fallback
        }
    } else {
        error_log("PHPMailer not available, using PHP mail() fallback");
    }
    
    // Fallback to PHP mail() function
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    $result = mail($email, $subject, $htmlBody, $headers);
    
    if (!$result) {
        error_log("PHP mail() function failed to send OTP email to: " . $email);
    }
    
    return $result;
}

/**
 * Main execution
 */
try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use POST.'
        ]);
        exit;
    }
    
    // Get email from request
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? $_POST['email'] ?? '';
    
    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email is required.'
        ]);
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format.'
        ]);
        exit;
    }
    
    // Get database connection
    $pdo = getDbConnection();
    
    // Find lead by email
    $lead = findLeadByEmail($pdo, $email);
    if (!$lead) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Lead not found. Please submit the lead form first.'
        ]);
        exit;
    }
    
    $leadId = (int) $lead['id'];
    $leadName = $lead['company_name'] ?? $lead['email'];
    
    // Check for existing pending OTP
    $existingOtp = checkExistingOtp($pdo, $leadId);
    
    if ($existingOtp) {
        // Check if attempts exceeded
        if ($existingOtp['attempts'] >= $existingOtp['max_attempts']) {
            // Update status to blocked
            $stmt = $pdo->prepare("
                UPDATE otp_verifications 
                SET status = 'blocked' 
                WHERE id = ?
            ");
            $stmt->execute([$existingOtp['id']]);
            
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Maximum OTP attempts exceeded. Please request a new OTP.'
            ]);
            exit;
        }
        
        // OTP still valid or not: we always invalidate old and create new on resend.
        // No early exit; fall through to invalidate + create.
    }

    // Invalidate old pending and failed OTPs, then create new with full 10‑min expiry
    invalidateOldOtps($pdo, $leadId);
    
    // Generate new OTP
    $otp = generateOtp();
    $otpHash = hashOtp($otp);
    
    // Create OTP record
    $otpId = createOtpRecord($pdo, $leadId, $otp, $otpHash);
    
    // Send OTP via email
    $emailSent = sendOtpEmail($email, $leadName, $otp);
    
    if (!$emailSent) {
        error_log("Failed to send OTP email to: {$email}. OTP: {$otp}");
        
        // Don't expose OTP - just show error message
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send OTP email. Please check your email configuration. Error logged for administrator.'
        ]);
        exit;
    }
    
    // Success response - OTP sent via email
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'OTP has been sent to your email address. Please check your inbox.',
        'data' => [
            'otp_id' => $otpId,
            'expires_in' => 600, // 10 minutes in seconds
            'max_attempts' => 3
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in send-otp.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Error in send-otp.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}

