<?php
/**
 * Save Query - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Handles saving client queries/chat messages during video viewing
 */

header('Content-Type: application/json');

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Get input data (support both POST and JSON body)
    $input = $_POST;
    if (empty($input) && !empty(file_get_contents('php://input'))) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }
    $token = trim(urldecode($input['token'] ?? ''));
    $queryText = trim($input['query'] ?? '');
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Token is required'
        ]);
        exit;
    }
    
    if (empty($queryText)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Query text is required'
        ]);
        exit;
    }
    
    // Look up demo link by token (no strict expiry check - allow submitting questions for valid token)
    $stmt = $pdo->prepare("
        SELECT id, lead_id, token_hash
        FROM demo_links
        WHERE token = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $demoLink = $stmt->fetch();
    
    if (!$demoLink) {
        $stmt = $pdo->prepare("SELECT id, lead_id, token_hash FROM demo_links ORDER BY created_at DESC");
        $stmt->execute();
        foreach ($stmt->fetchAll() as $link) {
            if (password_verify($token, $link['token_hash'])) {
                $demoLink = $link;
                break;
            }
        }
    }
    
    if (!$demoLink) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit;
    }
    
    $leadId = (int) $demoLink['lead_id'];
    $demoLinkId = (int) $demoLink['id'];
    
    // Save query
    $stmt = $pdo->prepare("
        INSERT INTO demo_queries (
            lead_id, demo_link_id, query_text, status
        ) VALUES (?, ?, ?, 'pending')
    ");
    $stmt->execute([$leadId, $demoLinkId, $queryText]);
    
    $queryId = (int) $pdo->lastInsertId();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Your query has been submitted. We will get back to you soon.',
        'data' => [
            'query_id' => $queryId
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in save-query.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Error in save-query.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}

