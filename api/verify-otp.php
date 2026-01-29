<?php
/**
 * Verify OTP Controller - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Validates OTP against hashed value
 * Checks expiry and attempts
 * Marks lead as verified
 */

header('Content-Type: application/json');

// Load configuration
require_once __DIR__ . '/../config/config.php';

/**
 * Find lead by email
 */
function findLeadByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare("SELECT id, email, company_name, status FROM leads_for_demo WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $lead = $stmt->fetch();
    return $lead ?: null;
}

/**
 * Get latest pending OTP for a lead
 */
function getLatestPendingOtp(PDO $pdo, int $leadId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, otp_hash, attempts, max_attempts, status, expires_at, verified_at, created_at
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
 * Check if OTP is expired using created_at + 10 minutes.
 * We use created_at (never updated) and PHP time() to avoid any
 * datetime parsing / timezone mismatch that could wrongly mark valid OTPs expired.
 */
function isOtpExpired(string $createdAt): bool
{
    $createdAt = trim($createdAt ?? '');
    if ($createdAt === '') {
        return true;
    }
    $createdTs = @strtotime($createdAt);
    if ($createdTs === false) {
        return true;
    }
    return time() > $createdTs + 600;
}

/**
 * Verify OTP against hash
 */
function verifyOtp(string $otp, string $hash): bool
{
    return password_verify($otp, $hash);
}

/**
 * Increment OTP attempts
 */
function incrementOtpAttempts(PDO $pdo, int $otpId, int $currentAttempts, int $maxAttempts): void
{
    $newAttempts = $currentAttempts + 1;
    $status = ($newAttempts >= $maxAttempts) ? 'blocked' : 'failed';
    
    $stmt = $pdo->prepare("
        UPDATE otp_verifications
        SET attempts = ?, status = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newAttempts, $status, $otpId]);
}

/**
 * Mark OTP as verified
 */
function markOtpAsVerified(PDO $pdo, int $otpId): void
{
    $stmt = $pdo->prepare("
        UPDATE otp_verifications
        SET status = 'verified', verified_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$otpId]);
}

/**
 * Mark lead as verified
 */
function markLeadAsVerified(PDO $pdo, int $leadId): void
{
    $stmt = $pdo->prepare("
        UPDATE leads_for_demo
        SET status = 'verified', updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$leadId]);
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
    
    // Get input from request
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? $_POST['email'] ?? '';
    $otp = $input['otp'] ?? $_POST['otp'] ?? '';
    
    // Validate input
    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email is required.'
        ]);
        exit;
    }
    
    if (empty($otp)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'OTP is required.'
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
    
    // Validate OTP format (6 digits)
    if (!preg_match('/^\d{6}$/', $otp)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid OTP format. OTP must be 6 digits.'
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
    
    // Check if lead is already verified
    if ($lead['status'] === 'verified') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'This lead is already verified.'
        ]);
        exit;
    }
    
    // Get latest pending OTP
    $otpRecord = getLatestPendingOtp($pdo, $leadId);
    if (!$otpRecord) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No pending OTP found. Please request a new OTP.'
        ]);
        exit;
    }
    
    // Check if OTP is already verified
    if ($otpRecord['status'] === 'verified') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'This OTP has already been verified.'
        ]);
        exit;
    }
    
    // Check if OTP is blocked
    if ($otpRecord['status'] === 'blocked') {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Maximum OTP attempts exceeded. Please request a new OTP.'
        ]);
        exit;
    }
    
    // Check if OTP is expired (created_at + 10 min; same OTP, retries don't change it)
    if (isOtpExpired($otpRecord['created_at'] ?? '')) {
        // Update status to expired
        $stmt = $pdo->prepare("
            UPDATE otp_verifications
            SET status = 'expired', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$otpRecord['id']]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'OTP has expired. Please request a new OTP.'
        ]);
        exit;
    }
    
    // Verify OTP against hash
    $isValid = verifyOtp($otp, $otpRecord['otp_hash']);
    
    if (!$isValid) {
        // Increment attempts
        $currentAttempts = (int) $otpRecord['attempts'];
        $maxAttempts = (int) $otpRecord['max_attempts'];
        
        incrementOtpAttempts($pdo, $otpRecord['id'], $currentAttempts, $maxAttempts);
        
        $remainingAttempts = $maxAttempts - ($currentAttempts + 1);
        
        if ($remainingAttempts <= 0) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid OTP. Maximum attempts exceeded. Please request a new OTP.'
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid OTP. ' . $remainingAttempts . ' attempt(s) remaining.',
                'data' => [
                    'remaining_attempts' => $remainingAttempts
                ]
            ]);
        }
        exit;
    }
    
    // OTP is valid - mark as verified
    markOtpAsVerified($pdo, $otpRecord['id']);
    markLeadAsVerified($pdo, $leadId);
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully. Your lead has been verified.',
        'data' => [
            'lead_id' => $leadId,
            'email' => $email,
            'verified_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in verify-otp.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Error in verify-otp.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}

