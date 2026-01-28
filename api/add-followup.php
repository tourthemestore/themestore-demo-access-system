<?php
/**
 * Add Follow-up - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Handles adding and updating follow-ups for leads
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
    
    // Get input data
    $leadId = isset($_POST['lead_id']) ? (int) $_POST['lead_id'] : 0;
    $followupType = $_POST['followup_type'] ?? 'note';
    $subject = trim($_POST['subject'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $followupDate = $_POST['followup_date'] ?? null;
    $status = $_POST['status'] ?? 'pending';
    $followupId = isset($_POST['followup_id']) ? (int) $_POST['followup_id'] : 0;
    
    // Validate lead ID
    if ($leadId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid lead ID'
        ]);
        exit;
    }
    
    // Validate followup type
    $validTypes = ['call', 'email', 'meeting', 'note', 'reminder', 'other'];
    if (!in_array($followupType, $validTypes)) {
        $followupType = 'note';
    }
    
    // Validate status
    $validStatuses = ['pending', 'completed', 'cancelled', 'rescheduled'];
    if (!in_array($status, $validStatuses)) {
        $status = 'pending';
    }
    
    // Check if lead exists
    $leadStmt = $pdo->prepare("SELECT id FROM leads_for_demo WHERE id = ? LIMIT 1");
    $leadStmt->execute([$leadId]);
    if (!$leadStmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Lead not found'
        ]);
        exit;
    }
    
    // Get current user (admin) - you can modify this to get from session
    $createdBy = $_POST['created_by'] ?? 'Admin';
    
    // Format followup date
    $followupDateFormatted = null;
    if (!empty($followupDate)) {
        $followupDateFormatted = date('Y-m-d H:i:s', strtotime($followupDate));
    }
    
    if ($followupId > 0) {
        // Update existing follow-up
        $updateStmt = $pdo->prepare("
            UPDATE demo_followups
            SET followup_type = ?,
                subject = ?,
                notes = ?,
                followup_date = ?,
                status = ?,
                completed_at = CASE WHEN ? = 'completed' AND completed_at IS NULL THEN NOW() ELSE completed_at END,
                updated_at = NOW()
            WHERE id = ? AND lead_id = ?
        ");
        $updateStmt->execute([
            $followupType,
            $subject ?: null,
            $notes ?: null,
            $followupDateFormatted,
            $status,
            $status,
            $followupId,
            $leadId
        ]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Follow-up updated successfully',
            'data' => [
                'followup_id' => $followupId
            ]
        ]);
    } else {
        // Create new follow-up
        $insertStmt = $pdo->prepare("
            INSERT INTO demo_followups (
                lead_id, followup_type, subject, notes, followup_date, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $leadId,
            $followupType,
            $subject ?: null,
            $notes ?: null,
            $followupDateFormatted,
            $status,
            $createdBy
        ]);
        
        $newFollowupId = (int) $pdo->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Follow-up added successfully',
            'data' => [
                'followup_id' => $newFollowupId
            ]
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in add-followup.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("Error in add-followup.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}

