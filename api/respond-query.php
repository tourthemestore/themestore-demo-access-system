<?php
/**
 * Respond to Query - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Handles admin responses to client queries and scheduling calls
 */

header('Content-Type: application/json');

// Load configuration
require_once __DIR__ . '/../config/config.php';

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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

/**
 * Send query response email to client
 */
function sendQueryResponseEmail(string $email, string $name, string $queryText, string $adminResponse): bool
{
    global $phpmailerAvailable;
    
    $subject = 'Response to Your Query - ThemeStore Demo Access';
    $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f5f5f5;
                }
                .email-wrapper {
                    max-width: 600px; 
                    margin: 0 auto; 
                    background-color: #ffffff;
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px 20px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 600;
                }
                .container { 
                    padding: 30px 20px; 
                }
                .greeting {
                    font-size: 16px;
                    margin-bottom: 20px;
                }
                .section-title {
                    font-size: 14px;
                    font-weight: 600;
                    color: #555;
                    margin-bottom: 10px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .query-box { 
                    background: #f8f9fa; 
                    padding: 20px; 
                    border-left: 4px solid #667eea; 
                    margin: 20px 0;
                    border-radius: 4px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .response-box { 
                    background: #e8f5e9; 
                    padding: 20px; 
                    border-left: 4px solid #4caf50; 
                    margin: 20px 0;
                    border-radius: 4px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .query-box p,
                .response-box p {
                    margin: 0;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                }
                .closing {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #e0e0e0;
                }
                .footer { 
                    background-color: #f8f9fa;
                    padding: 20px;
                    text-align: center;
                    font-size: 12px; 
                    color: #666;
                    border-top: 1px solid #e0e0e0;
                }
                .footer p {
                    margin: 5px 0;
                }
            </style>
        </head>
        <body>
            <div class='email-wrapper'>
                <div class='header'>
                    <h1>Response to Your Query</h1>
                </div>
                <div class='container'>
                    <div class='greeting'>
                        <p>Hello <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                        <p>Thank you for your question. Here is our response:</p>
                    </div>
                    
                    <div class='query-box'>
                        <div class='section-title'>Your Question</div>
                        <p>" . nl2br(htmlspecialchars($queryText, ENT_QUOTES, 'UTF-8')) . "</p>
                    </div>
                    
                    <div class='response-box'>
                        <div class='section-title'>Our Response</div>
                        <p>" . nl2br(htmlspecialchars($adminResponse, ENT_QUOTES, 'UTF-8')) . "</p>
                    </div>
                    
                    <div class='closing'>
                        <p>If you have any further questions, please feel free to reach out to us.</p>
                        <p><strong>Best regards,</strong><br>ThemeStore Demo Access Team</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " ThemeStore Demo Access. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
    ";
    $textBody = "Response to Your Query\n\n";
    $textBody .= "Your Question:\n" . $queryText . "\n\n";
    $textBody .= "Our Response:\n" . $adminResponse . "\n\n";
    $textBody .= "If you have any further questions, please feel free to reach out to us.\n\n";
    $textBody .= "Best regards,\nThemeStore Demo Access Team";
    
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
            $mail->SMTPDebug = 0;
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
            error_log("Query response email sent successfully via PHPMailer to: {$email}");
            return true;
        } catch (\Exception $e) {
            $errorMsg = $mail->ErrorInfo ?? $e->getMessage();
            error_log("PHPMailer error in respond-query.php for email {$email}: " . $errorMsg);
            // Fall through to PHP mail() fallback
        }
    }
    
    // Fallback to PHP mail() function
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    $result = mail($email, $subject, $htmlBody, $headers);
    
    if ($result) {
        error_log("Query response email sent successfully via PHP mail() to: {$email}");
    } else {
        error_log("PHP mail() function failed to send query response email to: {$email}");
    }
    
    return $result;
}

try {
    $pdo = getDbConnection();
    
    // Get input data
    $queryId = isset($_POST['query_id']) ? (int) $_POST['query_id'] : 0;
    $adminResponse = trim($_POST['admin_response'] ?? '');
    $status = $_POST['status'] ?? 'answered';
    $scheduledCallDate = $_POST['scheduled_call_date'] ?? null;
    
    // Log incoming data for debugging
    error_log("respond-query.php - Query ID: {$queryId}, Response length: " . strlen($adminResponse) . ", Status: {$status}");
    
    if ($queryId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid query ID'
        ]);
        exit;
    }
    
    if (empty($adminResponse)) {
        error_log("respond-query.php - WARNING: Empty admin_response for query ID: {$queryId}");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Admin response is required'
        ]);
        exit;
    }
    
    // Validate status
    $validStatuses = ['pending', 'answered', 'scheduled', 'resolved'];
    if (!in_array($status, $validStatuses)) {
        $status = 'answered';
    }
    
    // Format scheduled call date
    $scheduledCallDateFormatted = null;
    if (!empty($scheduledCallDate)) {
        // Handle both datetime-local format (YYYY-MM-DDTHH:mm) and standard format
        $dateStr = str_replace('T', ' ', $scheduledCallDate);
        $scheduledCallDateFormatted = date('Y-m-d H:i:s', strtotime($dateStr));
        $status = 'scheduled'; // Auto-set status to scheduled if date provided
    }
    
    // Update query
    $updateStmt = $pdo->prepare("
        UPDATE demo_queries
        SET admin_response = ?,
            status = ?,
            scheduled_call_date = ?,
            resolved_at = CASE WHEN ? = 'resolved' AND resolved_at IS NULL THEN NOW() ELSE resolved_at END,
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([
        $adminResponse,
        $status,
        $scheduledCallDateFormatted,
        $status,
        $queryId
    ]);
    
    // Verify the update
    $verifyStmt = $pdo->prepare("SELECT admin_response FROM demo_queries WHERE id = ?");
    $verifyStmt->execute([$queryId]);
    $verified = $verifyStmt->fetch();
    error_log("respond-query.php - Verified saved response length for query {$queryId}: " . strlen($verified['admin_response'] ?? ''));
    
    // If scheduled, create a follow-up automatically
    if ($status === 'scheduled' && $scheduledCallDateFormatted) {
        // Get lead_id from query
        $leadStmt = $pdo->prepare("SELECT lead_id FROM demo_queries WHERE id = ? LIMIT 1");
        $leadStmt->execute([$queryId]);
        $queryData = $leadStmt->fetch();
        
        if ($queryData) {
            $leadId = (int) $queryData['lead_id'];
            
            // Create follow-up for scheduled call
            $followupStmt = $pdo->prepare("
                INSERT INTO demo_followups (
                    lead_id, followup_type, subject, notes, followup_date, status, created_by
                ) VALUES (?, 'call', ?, ?, ?, 'pending', 'Admin')
            ");
            $followupStmt->execute([
                $leadId,
                'Scheduled Call - Query Response',
                'Call scheduled in response to client query. Query: ' . substr($adminResponse ?: 'No response provided', 0, 200),
                $scheduledCallDateFormatted
            ]);
        }
    }
    
    // Send email to client if response is provided
    $emailSent = false;
    $emailError = null;
    if (!empty($adminResponse)) {
        // Get lead email and query details
        $leadQueryStmt = $pdo->prepare("
            SELECT l.email, l.company_name, dq.query_text
            FROM demo_queries dq
            INNER JOIN leads_for_demo l ON dq.lead_id = l.id
            WHERE dq.id = ?
        ");
        $leadQueryStmt->execute([$queryId]);
        $leadQueryData = $leadQueryStmt->fetch();
        
        if ($leadQueryData && !empty($leadQueryData['email'])) {
            error_log("Attempting to send query response email to: {$leadQueryData['email']} for query ID: {$queryId}");
            $emailSent = sendQueryResponseEmail(
                $leadQueryData['email'],
                $leadQueryData['company_name'] ?: $leadQueryData['email'],
                $leadQueryData['query_text'],
                $adminResponse
            );
            
            if ($emailSent) {
                error_log("Query response email sent successfully to: {$leadQueryData['email']} for query ID: {$queryId}");
            } else {
                error_log("Failed to send query response email to: {$leadQueryData['email']} for query ID: {$queryId}");
                $emailError = "Email could not be sent";
            }
        } else {
            error_log("No email address found for query ID: {$queryId}");
            $emailError = "No email address found for this query";
        }
    }
    
    http_response_code(200);
    $message = 'Response saved successfully';
    if ($emailSent) {
        $message .= ' and sent to client via email';
    } elseif ($emailError) {
        $message .= ' (' . $emailError . ')';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'email_sent' => $emailSent
    ]);

} catch (PDOException $e) {
    error_log("Database error in respond-query.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Error in respond-query.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}

