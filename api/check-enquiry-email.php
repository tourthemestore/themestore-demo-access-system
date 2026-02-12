<?php
/**
 * Check Enquiry Email - ThemeStore Demo Access System
 * 
 * Validates if email exists in enquiry_master and returns contact details.
 * Used by lead-form to auto-fill and validate before demo access.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';

try {
    $email = trim($_GET['email'] ?? $_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['found' => false, 'message' => 'Valid email required']);
        exit;
    }

    $pdo = getDbConnection();

    // Check enquiry_master table (email_id, mobile_no, company_name, city)
    $stmt = $pdo->prepare("
        SELECT mobile_no, company_name, city
        FROM enquiry_master
        WHERE email_id = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'found' => true,
            'mobile_no' => $row['mobile_no'] ?? '',
            'company_name' => $row['company_name'] ?? '',
            'city' => $row['city'] ?? ''
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
} catch (PDOException $e) {
    error_log("check-enquiry-email.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['found' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("check-enquiry-email.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['found' => false, 'message' => 'Error']);
}
