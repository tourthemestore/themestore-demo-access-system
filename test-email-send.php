<?php
/**
 * Test Email Sending - ThemeStore Demo Access System
 * 
 * Tests email configuration and sending
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
} else {
    echo "PHPMailer not found at: {$vendorPath}<br>";
}

echo "<h2>Email Configuration Test</h2>";

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

if ($phpmailerAvailable) {
    echo "<h3>Testing PHPMailer Connection...</h3>";
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
        
        // Test email
        $testEmail = $_GET['email'] ?? SMTP_USER;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($testEmail);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Test Email - ThemeStore Demo Access';
        $mail->Body = '<h2>Test Email</h2><p>This is a test email to verify SMTP configuration.</p>';
        $mail->AltBody = 'This is a test email to verify SMTP configuration.';
        
        $mail->send();
        echo "<br><strong style='color: green;'>✓ Email sent successfully to: {$testEmail}</strong><br>";
        
    } catch (\Exception $e) {
        echo "<br><strong style='color: red;'>✗ SMTP Error:</strong> " . htmlspecialchars($mail->ErrorInfo ?? $e->getMessage()) . "<br>";
    }
} else {
    echo "<h3>Testing PHP mail() function...</h3>";
    $testEmail = $_GET['email'] ?? SMTP_FROM_EMAIL;
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    
    $result = mail($testEmail, 'Test Email', '<h2>Test Email</h2><p>This is a test email.</p>', $headers);
    
    if ($result) {
        echo "✓ PHP mail() function executed (check if email was received)<br>";
    } else {
        echo "✗ PHP mail() function failed<br>";
    }
}

echo "<br><hr>";
echo "<p><strong>Usage:</strong> Add ?email=your@email.com to test sending to a specific email</p>";

