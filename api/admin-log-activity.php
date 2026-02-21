<?php
/**
 * Admin Log Activity - ThemeStore Demo Access System
 * Logs login/logout events. Called via JS sendBeacon on window close.
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action !== 'logout') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$user = $_SESSION['admin_user'] ?? null;
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $pdo = getDbConnection();
    $nowIST = date('Y-m-d H:i:s'); // PHP timezone is Asia/Kolkata
    $stmt = $pdo->prepare("
        INSERT INTO demo_leads_user_log (emp_id, user_name, emp_name, role, action, logged_at)
        VALUES (?, ?, ?, ?, 'logout', ?)
    ");
    $stmt->execute([
        (int) $user['emp_id'],
        $user['username'] ?? '',
        $user['emp_name'] ?? '',
        !empty($user['is_admin']) ? 'Admin' : 'Sales',
        $nowIST
    ]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log("admin-log-activity error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false]);
}
