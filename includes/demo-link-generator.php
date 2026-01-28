<?php
/**
 * Demo Link Generator - ThemeStore Demo Access System
 * Shared logic for creating demo links (used by demo-flow.php and api/generate-demo-link.php)
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Generate secure random token (>=64 characters)
 */
function generateSecureToken(int $length = 64): string
{
    $bytes = random_bytes($length);
    $token = base64_encode($bytes);
    $token = str_replace(['+', '/', '='], ['-', '_', ''], $token);
    if (strlen($token) < 64) {
        $additionalBytes = random_bytes(32);
        $additionalToken = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($additionalBytes));
        $token .= $additionalToken;
    }
    return substr($token, 0, max(64, $length));
}

/**
 * Hash token before storing
 */
function hashToken(string $token): string
{
    return password_hash($token, PASSWORD_DEFAULT);
}

/**
 * Create new demo link record
 */
function createDemoLink(PDO $pdo, int $leadId, string $token, string $tokenHash, int $expiryHours = 1): int
{
    // Insert with created_at auto-set by MySQL
    $stmt = $pdo->prepare("
        INSERT INTO demo_links (
            lead_id, token, token_hash, status, views_count, max_views, expires_at
        ) VALUES (?, ?, ?, 'active', 0, 1, NOW())
    ");
    $stmt->execute([$leadId, $token, $tokenHash]);
    
    $demoLinkId = (int) $pdo->lastInsertId();
    
    if ($demoLinkId <= 0) {
        throw new Exception("Failed to create demo link - no insert ID returned");
    }
    
    // Get the actual created_at value
    $getStmt = $pdo->prepare("SELECT created_at FROM demo_links WHERE id = ?");
    $getStmt->execute([$demoLinkId]);
    $row = $getStmt->fetch();
    
    if ($row) {
        // Calculate expires_at in PHP from the actual created_at
        $createdAt = $row['created_at'];
        $createdTs = strtotime($createdAt);
        $expiresTs = $createdTs + ($expiryHours * 3600);
        $expiresAt = date('Y-m-d H:i:s', $expiresTs);
        
        // Update with the calculated value
        $updateStmt = $pdo->prepare("
            UPDATE demo_links
            SET expires_at = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$expiresAt, $demoLinkId]);
        
        // Final verification
        $verifyStmt = $pdo->prepare("SELECT created_at, expires_at FROM demo_links WHERE id = ?");
        $verifyStmt->execute([$demoLinkId]);
        $verify = $verifyStmt->fetch();
        
        if ($verify) {
            $created = strtotime($verify['created_at']);
            $expires = strtotime($verify['expires_at']);
            $diff = $expires - $created;
            $expectedDiff = $expiryHours * 3600;
            
            if (abs($diff - $expectedDiff) > 10) {
                error_log("ERROR: expires_at still wrong after fix for demo_link_id: $demoLinkId " .
                         "(created: {$verify['created_at']}, expires: {$verify['expires_at']}, diff: {$diff}s)");
            }
        }
    }
    
    return $demoLinkId;
}
