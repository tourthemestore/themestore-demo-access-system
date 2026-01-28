<?php
/**
 * Fix Demo Expiry Times - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Fixes demo links where expires_at is incorrect (before or equal to created_at)
 * Sets expires_at to created_at + 60 minutes (1 hour)
 */

// Load configuration
require_once __DIR__ . '/../config/config.php';

try {
    $pdo = getDbConnection();
    
    // Find demo links with invalid expiry times
    $stmt = $pdo->prepare("
        SELECT id, lead_id, created_at, expires_at, status
        FROM demo_links
        WHERE expires_at <= created_at
        OR expires_at IS NULL
        ORDER BY id DESC
    ");
    $stmt->execute();
    $invalidLinks = $stmt->fetchAll();
    
    $fixedCount = 0;
    $errors = [];
    
    foreach ($invalidLinks as $link) {
        try {
            // Use MySQL DATE_ADD to set expires_at to exactly 60 minutes after created_at
            // This ensures consistency and avoids timezone issues
            $updateStmt = $pdo->prepare("
                UPDATE demo_links
                SET expires_at = DATE_ADD(created_at, INTERVAL 60 MINUTE),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$link['id']]);
            
            // Get the updated expiry time for logging
            $checkStmt = $pdo->prepare("SELECT expires_at FROM demo_links WHERE id = ?");
            $checkStmt->execute([$link['id']]);
            $updated = $checkStmt->fetch();
            
            $fixedCount++;
            echo "Fixed demo_link ID {$link['id']}: Set expires_at to {$updated['expires_at']} (created_at: {$link['created_at']}, was: {$link['expires_at']})\n";
            
        } catch (PDOException $e) {
            $errors[] = "Error fixing demo_link ID {$link['id']}: " . $e->getMessage();
            error_log("Error fixing demo_link ID {$link['id']}: " . $e->getMessage());
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Total invalid demo links found: " . count($invalidLinks) . "\n";
    echo "Successfully fixed: {$fixedCount}\n";
    echo "Errors: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "- {$error}\n";
        }
    }
    
} catch (PDOException $e) {
    error_log("Database error in fix-demo-expiry.php: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    error_log("Error in fix-demo-expiry.php: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

