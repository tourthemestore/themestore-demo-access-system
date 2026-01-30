<?php
/**
 * Token Validator - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Validates demo access tokens
 */

// Load configuration
require_once __DIR__ . '/../config/config.php';

/**
 * Check if demo link is expired (created_at + 60 minutes).
 * Uses created_at and time() to avoid datetime parsing / timezone issues.
 */
function isDemoLinkExpired(string $createdAt): bool
{
    $createdAt = trim($createdAt ?? '');
    if ($createdAt === '') {
        return true;
    }
    $createdTs = @strtotime($createdAt);
    if ($createdTs === false) {
        return true;
    }
    return time() > $createdTs + 3600; // 60 minutes
}

/**
 * Validate demo token
 * 
 * @param string $token The token to validate
 * @return array|false Returns demo link row if valid, false otherwise
 */
function validateDemoToken(string $token): array|false
{
    try {
        $pdo = getDbConnection();
        $token = trim($token);
        if ($token === '') {
            return false;
        }
        
        // Find by plain token first (no expiry filter - check in PHP for reliability)
        $stmt = $pdo->prepare("
            SELECT id, lead_id, token, token_hash, status, views_count, max_views, 
                   expires_at, accessed_at, created_at
            FROM demo_links
            WHERE token = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $demoLink = $stmt->fetch();
        
        if ($demoLink && !isDemoLinkExpired($demoLink['created_at'] ?? '')) {
            return $demoLink;
        }
        
        // Fallback: Try hash verification
        $stmt = $pdo->prepare("
            SELECT id, lead_id, token, token_hash, status, views_count, max_views, 
                   expires_at, accessed_at, created_at
            FROM demo_links
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $demoLinks = $stmt->fetchAll();
        
        foreach ($demoLinks as $demoLink) {
            if (password_verify($token, $demoLink['token_hash']) && !isDemoLinkExpired($demoLink['created_at'] ?? '')) {
                return $demoLink;
            }
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Database error in validateDemoToken: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Error in validateDemoToken: " . $e->getMessage());
        return false;
    }
}

