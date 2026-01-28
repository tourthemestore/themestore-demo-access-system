<?php
/**
 * Track Video Activity Endpoint - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Receives AJAX events, validates token, and saves to video_activity table
 */

header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token-validator.php';

/**
 * Get client IP address
 */
function getClientIp(): string
{
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle comma-separated IPs (from proxies)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get or create video activity record
 */
function getOrCreateVideoActivity(PDO $pdo, int $demoLinkId, int $leadId): ?int
{
    // Check if activity record already exists for this demo link
    $stmt = $pdo->prepare("
        SELECT id
        FROM video_activity
        WHERE demo_link_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$demoLinkId]);
    $activity = $stmt->fetch();
    
    if ($activity) {
        return (int) $activity['id'];
    }
    
    // Create new activity record
    $stmt = $pdo->prepare("
        INSERT INTO video_activity (demo_link_id, lead_id, status, started_at)
        VALUES (?, ?, 'started', NOW())
    ");
    $stmt->execute([$demoLinkId, $leadId]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Save video activity event
 */
function saveVideoActivity(
    PDO $pdo,
    int $demoLinkId,
    int $leadId,
    string $eventType,
    float $progressPercentage,
    int $durationWatched,
    string $ipAddress
): bool
{
    try {
        // Get or create activity record
        $activityId = getOrCreateVideoActivity($pdo, $demoLinkId, $leadId);
        
        if (!$activityId) {
            return false;
        }
        
        // Validate event type
        $validEventTypes = ['started', 'progress', 'completed', 'abandoned'];
        if (!in_array($eventType, $validEventTypes)) {
            return false;
        }
        
        // Prepare update based on event type
        switch ($eventType) {
            case 'started':
                $stmt = $pdo->prepare("
                    UPDATE video_activity
                    SET status = 'started',
                        progress_percentage = ?,
                        duration_watched = ?,
                        started_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$progressPercentage, $durationWatched, $activityId]);
                break;
                
            case 'progress':
                $stmt = $pdo->prepare("
                    UPDATE video_activity
                    SET status = 'progress',
                        progress_percentage = ?,
                        duration_watched = ?,
                        last_progress_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$progressPercentage, $durationWatched, $activityId]);
                break;
                
            case 'completed':
                $stmt = $pdo->prepare("
                    UPDATE video_activity
                    SET status = 'completed',
                        progress_percentage = ?,
                        duration_watched = ?,
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$progressPercentage, $durationWatched, $activityId]);
                break;
                
            case 'abandoned':
                $stmt = $pdo->prepare("
                    UPDATE video_activity
                    SET status = 'abandoned',
                        progress_percentage = ?,
                        duration_watched = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$progressPercentage, $durationWatched, $activityId]);
                break;
        }
        
        // Note: IP address storage would require adding an 'ip_address' column to video_activity table
        // For now, we log it separately or you can add the column to the schema
        error_log("Video activity tracked - Event: {$eventType}, IP: {$ipAddress}, Activity ID: {$activityId}");
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Database error in saveVideoActivity: " . $e->getMessage());
        return false;
    }
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
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data.'
        ]);
        exit;
    }
    
    // Validate required fields
    $token = $input['token'] ?? '';
    $token = trim(urldecode($token)); // Decode URL encoding and trim whitespace
    $eventType = $input['event_type'] ?? '';
    $progressPercentage = isset($input['progress_percentage']) ? (float) $input['progress_percentage'] : 0.0;
    $durationWatched = isset($input['duration_watched']) ? (int) $input['duration_watched'] : 0;
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Token is required.'
        ]);
        exit;
    }
    
    if (empty($eventType)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Event type is required.'
        ]);
        exit;
    }
    
    // Validate event type
    $validEventTypes = ['started', 'progress', 'completed', 'abandoned'];
    if (!in_array($eventType, $validEventTypes)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid event type. Must be one of: ' . implode(', ', $validEventTypes)
        ]);
        exit;
    }
    
    // Validate progress percentage
    if ($progressPercentage < 0 || $progressPercentage > 100) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Progress percentage must be between 0 and 100.'
        ]);
        exit;
    }
    
    // Get database connection
    $pdo = getDbConnection();
    
    // For tracking, we only need to know which demo_link / lead this token belongs to.
    // We do NOT want strict expiry/status checks here, because that would block
    // tracking events right at the expiry boundary and break analytics.
    // Try to find by plain token first, then fall back to hash verification
    
    // First try: Look up by plain token (fastest)
    $stmt = $pdo->prepare("
        SELECT id, lead_id, token_hash
        FROM demo_links
        WHERE token = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $demoLink = $stmt->fetch();
    
    // If not found by plain token, try hash verification (slower but more secure)
    if (!$demoLink) {
        $stmt = $pdo->prepare("
            SELECT id, lead_id, token_hash
            FROM demo_links
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $allLinks = $stmt->fetchAll();
        
        foreach ($allLinks as $link) {
            if (password_verify($token, $link['token_hash'])) {
                $demoLink = $link;
                break;
            }
        }
    }
    
    if (!$demoLink) {
        error_log("Token lookup failed for tracking - token: " . substr($token, 0, 20) . "...");
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid token for tracking.'
        ]);
        exit;
    }
    
    $demoLinkId = (int) $demoLink['id'];
    $leadId = (int) $demoLink['lead_id'];
    
    // Get client IP address
    $ipAddress = getClientIp();
    
    // Save video activity
    $success = saveVideoActivity(
        $pdo,
        $demoLinkId,
        $leadId,
        $eventType,
        $progressPercentage,
        $durationWatched,
        $ipAddress
    );
    
    if ($success) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Video activity tracked successfully.',
            'data' => [
                'event_type' => $eventType,
                'progress_percentage' => $progressPercentage,
                'duration_watched' => $durationWatched,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save video activity.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in track-video-activity.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Error in track-video-activity.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}

