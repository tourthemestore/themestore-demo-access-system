<?php
/**
 * Test Query Response Email - ThemeStore Demo Access System
 * 
 * Tests email sending for query responses
 */

require_once __DIR__ . '/config/config.php';

// Load PHPMailer
$phpmailerAvailable = false;
$vendorPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    try {
        require_once $vendorPath;
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $phpmailerAvailable = true;
        }
    } catch (Exception $e) {
        echo "PHPMailer autoload failed: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>Query Response Email Test</h2>";

if ($phpmailerAvailable) {
    echo "✓ PHPMailer is installed<br>";
} else {
    echo "✗ PHPMailer not found<br>";
}

echo "<strong>SMTP Configuration:</strong><br>";
echo "Host: " . SMTP_HOST . "<br>";
echo "Port: " . SMTP_PORT . "<br>";
echo "User: " . SMTP_USER . "<br>";
echo "From Email: " . SMTP_FROM_EMAIL . "<br>";
echo "From Name: " . SMTP_FROM_NAME . "<br><br>";

// Test email parameters
$testEmail = $_GET['email'] ?? SMTP_FROM_EMAIL;
$testQuery = "This is a test query to verify email functionality.";
$testResponse = "This is a test response to verify that emails are being sent correctly.";

echo "<h3>Testing Query Response Email...</h3>";
echo "Sending test email to: <strong>{$testEmail}</strong><br><br>";

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
        $mail->SMTPDebug = 2; // Show full debug output
        $mail->Debugoutput = function($str, $level) {
            echo htmlspecialchars($str) . "<br>";
        };
        
        // Test email content
        $subject = 'Test: Response to Your Query - ThemeStore Demo Access';
        $htmlBody = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .query-box { background: #f4f4f4; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0; }
                    .response-box { background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50; margin: 20px 0; }
                    .footer { font-size: 12px; color: #666; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>Response to Your Query</h2>
                    <p>Hello Test User,</p>
                    <p>Thank you for your question. Here is our response:</p>
                    
                    <div class='query-box'>
                        <strong>Your Question:</strong><br>
                        " . nl2br(htmlspecialchars($testQuery, ENT_QUOTES, 'UTF-8')) . "
                    </div>
                    
                    <div class='response-box'>
                        <strong>Our Response:</strong><br>
                        " . nl2br(htmlspecialchars($testResponse, ENT_QUOTES, 'UTF-8')) . "
                    </div>
                    
                    <p>If you have any further questions, please feel free to reach out to us.</p>
                    
                    <div class='footer'>
                        <p>Best regards,<br>ThemeStore Demo Access Team</p>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        $textBody = "Response to Your Query\n\n";
        $textBody .= "Your Question:\n" . $testQuery . "\n\n";
        $textBody .= "Our Response:\n" . $testResponse . "\n\n";
        $textBody .= "If you have any further questions, please feel free to reach out to us.\n\n";
        $textBody .= "Best regards,\nThemeStore Demo Access Team";
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($testEmail);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        
        $mail->send();
        echo "<br><strong style='color: green;'>✓ Test email sent successfully to: {$testEmail}</strong><br>";
        echo "<p style='color: green;'><strong>Please check the inbox to verify the email was received.</strong></p>";
        
    } catch (\Exception $e) {
        echo "<br><strong style='color: red;'>✗ SMTP Error:</strong> " . htmlspecialchars($mail->ErrorInfo ?? $e->getMessage()) . "<br>";
    }
} else {
    echo "<h3>Testing PHP mail() function...</h3>";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    
    $result = mail($testEmail, $subject, $htmlBody, $headers);
    
    if ($result) {
        echo "✓ PHP mail() function executed (check if email was received)<br>";
    } else {
        echo "✗ PHP mail() function failed<br>";
    }
}

echo "<br><hr>";
echo "<p><strong>Usage:</strong> Add ?email=your@email.com to test sending to a specific email</p>";
echo "<p><strong>Note:</strong> This is a test email. In production, each query response sends one email per client query.</p>";

