<?php
/**
 * Save Interest - ThemeStore Demo Access System
 * PHP 8 - No Framework
 *
 * Saves lead interest (Interested / Not interested) from the watch page.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$allowed = ['interested', 'not_interested'];
$input = $_POST;
if (empty($input) && !empty(file_get_contents('php://input'))) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}
$token = trim(urldecode($input['token'] ?? ''));
$interest = trim(strtolower($input['interest'] ?? ''));

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token is required']);
    exit;
}
if (!in_array($interest, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Interest must be "interested" or "not_interested"']);
    exit;
}

try {
    $pdo = getDbConnection();
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
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }

    $leadId = (int) $demoLink['lead_id'];

    $stmt = $pdo->prepare("
        UPDATE leads_for_demo
        SET interest = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$interest, $leadId]);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your feedback.',
        'interest' => $interest,
    ]);
} catch (PDOException $e) {
    error_log("Database error in save-interest.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
} catch (Exception $e) {
    error_log("Error in save-interest.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
