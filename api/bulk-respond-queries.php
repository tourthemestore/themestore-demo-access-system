<?php
/**
 * Bulk Respond to Queries - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Handles bulk responses to multiple queries - sends ONE email with all queries and one response
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
 * Send bulk query response email to client
 */
function sendBulkQueryResponseEmail(array $queries, string $email, string $name, string $adminResponse): bool
{
    global $phpmailerAvailable;
    
    $subject = 'Response to Your Queries - ThemeStore Demo Access';
    
    // Build queries list HTML
    $queriesHtml = '';
    foreach ($queries as $index => $query) {
        $queriesHtml .= "
            <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #667eea; margin: 15px 0; border-radius: 4px;'>
                <div style='font-weight: 600; color: #555; margin-bottom: 8px; font-size: 13px;'>Question " . ($index + 1) . ":</div>
                <div style='color: #333; white-space: pre-wrap; word-wrap: break-word;'>" . nl2br(htmlspecialchars($query['query_text'], ENT_QUOTES, 'UTF-8')) . "</div>
            </div>
        ";
    }
    
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
                .response-box { 
                    background: #e8f5e9; 
                    padding: 20px; 
                    border-left: 4px solid #4caf50; 
                    margin: 20px 0;
                    border-radius: 4px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
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
                    <h1>Response to Your Queries</h1>
                </div>
                <div class='container'>
                    <div class='greeting'>
                        <p>Hello <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                        <p>Thank you for your questions. Here is our response:</p>
                    </div>
                    
                    <div>
                        <div class='section-title'>Your Questions</div>
                        {$queriesHtml}
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
    
    $textBody = "Response to Your Queries\n\n";
    $textBody .= "Hello {$name},\n\n";
    $textBody .= "Thank you for your questions. Here is our response:\n\n";
    $textBody .= "Your Questions:\n";
    foreach ($queries as $index => $query) {
        $textBody .= "\nQuestion " . ($index + 1) . ":\n" . $query['query_text'] . "\n";
    }
    $textBody .= "\n\nOur Response:\n" . $adminResponse . "\n\n";
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
            error_log("Bulk query response email sent successfully via PHPMailer to: {$email} for " . count($queries) . " queries");
            return true;
        } catch (\Exception $e) {
            $errorMsg = $mail->ErrorInfo ?? $e->getMessage();
            error_log("PHPMailer error in bulk-respond-queries.php for email {$email}: " . $errorMsg);
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
        error_log("Bulk query response email sent successfully via PHP mail() to: {$email} for " . count($queries) . " queries");
    } else {
        error_log("PHP mail() function failed to send bulk query response email to: {$email}");
    }
    
    return $result;
}

try {
    $pdo = getDbConnection();
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    $queryIds = $input['query_ids'] ?? [];
    $adminResponse = trim($input['admin_response'] ?? '');
    
    if (empty($queryIds) || !is_array($queryIds)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Query IDs are required'
        ]);
        exit;
    }
    
    if (empty($adminResponse)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Admin response is required'
        ]);
        exit;
    }
    
    // Convert to integers
    $queryIds = array_map('intval', $queryIds);
    $queryIds = array_filter($queryIds, function($id) { return $id > 0; });
    
    if (empty($queryIds)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid query IDs'
        ]);
        exit;
    }
    
    // Get all queries and their lead information
    $placeholders = implode(',', array_fill(0, count($queryIds), '?'));
    $queriesStmt = $pdo->prepare("
        SELECT dq.id, dq.query_text, dq.lead_id, l.email, l.company_name
        FROM demo_queries dq
        INNER JOIN leads_for_demo l ON dq.lead_id = l.id
        WHERE dq.id IN ({$placeholders})
    ");
    $queriesStmt->execute($queryIds);
    $queries = $queriesStmt->fetchAll();
    
    if (empty($queries)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No queries found'
        ]);
        exit;
    }
    
    // Group queries by lead (email) - in case multiple leads have queries selected
    $queriesByEmail = [];
    foreach ($queries as $query) {
        $email = $query['email'];
        if (!isset($queriesByEmail[$email])) {
            $queriesByEmail[$email] = [
                'email' => $email,
                'name' => $query['company_name'] ?: $query['email'],
                'queries' => []
            ];
        }
        $queriesByEmail[$email]['queries'][] = $query;
    }
    
    // Update all queries with the response
    $updateStmt = $pdo->prepare("
        UPDATE demo_queries
        SET admin_response = ?,
            status = 'answered',
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $updated = 0;
    foreach ($queryIds as $queryId) {
        $updateStmt->execute([$adminResponse, $queryId]);
        $updated++;
    }
    
    // Send ONE email per unique email address (with all their queries)
    $emailsSent = 0;
    $emailsFailed = 0;
    
    foreach ($queriesByEmail as $emailData) {
        $emailSent = sendBulkQueryResponseEmail(
            $emailData['queries'],
            $emailData['email'],
            $emailData['name'],
            $adminResponse
        );
        
        if ($emailSent) {
            $emailsSent++;
        } else {
            $emailsFailed++;
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Response saved to {$updated} query/queries. " . 
                     ($emailsSent > 0 ? "Sent {$emailsSent} email(s) with all queries and response." : '') .
                     ($emailsFailed > 0 ? " {$emailsFailed} email(s) failed to send." : ''),
        'queries_updated' => $updated,
        'emails_sent' => $emailsSent,
        'emails_failed' => $emailsFailed
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in bulk-respond-queries.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Error in bulk-respond-queries.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}

