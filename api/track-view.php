<?php
/**
 * Track View Endpoint - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Increments demo link views on first play
 */

header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token-validator.php';

/**
 * Increment demo link views on first play
 */
function incrementDemoLinkViews(int $demoLinkId): bool
{
    try {
        $pdo = getDbConnection();
        
        // Check current views
        $checkStmt = $pdo->prepare("
            SELECT views_count, max_views
            FROM demo_links
            WHERE id = ?
        ");
        $checkStmt->execute([$demoLinkId]);
        $link = $checkStmt->fetch();
        
        if (!$link) {
            return false;
        }
        
        // Only increment if not at max views
        if ($link['views_count'] < $link['max_views']) {
            $stmt = $pdo->prepare("
                UPDATE demo_links
                SET views_count = views_count + 1,
                    accessed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$demoLinkId]);
            
            // Check if max views reached
            if (($link['views_count'] + 1) >= $link['max_views']) {
                $updateStmt = $pdo->prepare("
                    UPDATE demo_links
                    SET status = 'used', updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$demoLinkId]);
            }
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Database error in incrementDemoLinkViews: " . $e->getMessage());
        return false;
    }
}

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
        exit;
    }
    
    // Get token from GET parameter
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Token is required.'
        ]);
        exit;
    }
    
    // Validate token
    $demoLink = validateDemoToken($token);
    
    if ($demoLink === false) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token.'
        ]);
        exit;
    }
    
    // Increment views
    $success = incrementDemoLinkViews($demoLink['id']);
    
    if ($success) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'View tracked successfully.'
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Maximum views already reached.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in track-view.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while tracking the view.'
    ]);
}

