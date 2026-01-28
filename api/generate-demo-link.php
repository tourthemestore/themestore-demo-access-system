<?php
/**
 * Generate Demo Link Controller - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Generates secure random token (>=64 chars)
 * Stores token with lead_id
 * Sets expiry = NOW + 1 hour
 * max_views = 1
 * Returns demo URL
 */

header('Content-Type: application/json');

// Load configuration and shared demo link generator
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/demo-link-generator.php';

/**
 * Find lead by email or ID
 */
function findLead(PDO $pdo, ?string $email = null, ?int $leadId = null): ?array
{
    if ($leadId !== null) {
        $stmt = $pdo->prepare("SELECT id, email, company_name, status FROM leads_for_demo WHERE id = ? LIMIT 1");
        $stmt->execute([$leadId]);
    } elseif ($email !== null) {
        $stmt = $pdo->prepare("SELECT id, email, company_name, status FROM leads_for_demo WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
    } else {
        return null;
    }
    
    $lead = $stmt->fetch();
    return $lead ?: null;
}

/**
 * Check if lead has active demo link
 */
function hasActiveDemoLink(PDO $pdo, int $leadId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, token, status, expires_at, views_count, max_views
        FROM demo_links
        WHERE lead_id = ? 
        AND status = 'active'
        AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$leadId]);
    return $stmt->fetch() ?: null;
}

/**
 * Check if lead's most recent follow-up is rescheduled
 * Only allows re-registration if the LATEST follow-up has status 'rescheduled'
 */
function hasRescheduledFollowup(PDO $pdo, int $leadId): bool
{
    $stmt = $pdo->prepare("
        SELECT status
        FROM demo_followups
        WHERE lead_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$leadId]);
    $result = $stmt->fetch();
    
    // Only return true if the most recent follow-up is rescheduled
    return ($result && $result['status'] === 'rescheduled');
}

/**
 * Invalidate old active demo links for a lead
 */
function invalidateOldDemoLinks(PDO $pdo, int $leadId): void
{
    $stmt = $pdo->prepare("
        UPDATE demo_links
        SET status = 'expired'
        WHERE lead_id = ? 
        AND status = 'active'
        AND expires_at > NOW()
    ");
    $stmt->execute([$leadId]);
}

/**
 * Generate demo URL
 */
function generateDemoUrl(string $token): string
{
    // Get current protocol and host
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Get base path (project root)
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = dirname(dirname($scriptPath)); // Go up from api/ to root
    $basePath = rtrim($basePath, '/');
    
    // Construct demo URL pointing to watch.php in public folder
    $demoPath = $basePath . '/public/watch.php';
    $url = $protocol . '://' . $host . $demoPath . '?token=' . urlencode($token);
    
    return $url;
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
    $leadId = isset($input['lead_id']) ? (int) $input['lead_id'] : (isset($_POST['lead_id']) ? (int) $_POST['lead_id'] : null);
    
    // Validate input
    if (empty($email) && $leadId === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Either email or lead_id is required.'
        ]);
        exit;
    }
    
    // Validate email format if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format.'
        ]);
        exit;
    }
    
    // Get database connection
    $pdo = getDbConnection();
    
    // Find lead
    $lead = findLead($pdo, $email ?: null, $leadId);
    if (!$lead) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Lead not found.'
        ]);
        exit;
    }
    
    $foundLeadId = (int) $lead['id'];
    
    // Check if lead is verified
    if ($lead['status'] !== 'verified') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Lead must be verified before generating demo link. Please verify OTP first.'
        ]);
        exit;
    }
    
    // Check if lead has a rescheduled follow-up
    $isRescheduled = hasRescheduledFollowup($pdo, $foundLeadId);
    
    // Check if lead already has an active demo link
    $existingLink = hasActiveDemoLink($pdo, $foundLeadId);
    if ($existingLink) {
        // If rescheduled, allow creating a new demo link by invalidating old ones
        if ($isRescheduled) {
            // Invalidate old demo links to allow new one for rescheduled demo
            invalidateOldDemoLinks($pdo, $foundLeadId);
        } else {
            // Return existing link if not rescheduled
            $demoUrl = generateDemoUrl($existingLink['token']);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'You already have an active demo link.',
                'data' => [
                    'demo_link_id' => $existingLink['id'],
                    'demo_url' => $demoUrl,
                    'token' => $existingLink['token'],
                    'video_url' => VIMEO_EMBED_URL,
                    'expires_at' => $existingLink['expires_at'],
                    'max_views' => $existingLink['max_views']
                ]
            ]);
            exit;
        }
    }
    
    // Generate secure token (>=64 characters)
    $token = generateSecureToken(64);
    $tokenHash = hashToken($token);
    
    // Create demo link record with 60 minutes expiry
    $demoLinkId = createDemoLink($pdo, $foundLeadId, $token, $tokenHash, 1); // 1 hour = 60 minutes
    
    // Generate demo URL
    $demoUrl = generateDemoUrl($token);
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Demo link generated successfully.',
        'data' => [
            'demo_link_id' => $demoLinkId,
            'demo_url' => $demoUrl,
            'token' => $token, // Include token in response for immediate use
            'video_url' => VIMEO_EMBED_URL,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600), // 60 minutes from now
            'expires_in' => 3600, // 60 minutes in seconds
            'max_views' => 1
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in generate-demo-link.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Error in generate-demo-link.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}

