<?php
/**
 * Expire Demo Links Cron Script - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Finds and marks expired demo links
 * Safe to run multiple times (idempotent)
 * 
 * Usage: php expire-demo-links.php
 * Cron: /15 * * * * /usr/bin/php /path/to/expire-demo-links.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line.');
}

// Load configuration
require_once __DIR__ . '/../config/config.php';

/**
 * Find and expire demo links
 */
function expireDemoLinks(): array
{
    try {
        $pdo = getDbConnection();
        
        // Find all active demo links that have expired
        // Only update those with status = 'active' to make it idempotent
        $stmt = $pdo->prepare("
            UPDATE demo_links
            SET status = 'expired',
                updated_at = NOW()
            WHERE status = 'active'
            AND expires_at < NOW()
        ");
        $stmt->execute();
        
        $expiredCount = $stmt->rowCount();
        
        // Get details of expired links for logging
        $detailsStmt = $pdo->prepare("
            SELECT id, lead_id, expires_at, views_count, max_views, created_at
            FROM demo_links
            WHERE status = 'expired'
            AND expires_at < NOW()
            AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ORDER BY id DESC
        ");
        $detailsStmt->execute();
        $expiredLinks = $detailsStmt->fetchAll();
        
        return [
            'success' => true,
            'count' => $expiredCount,
            'links' => $expiredLinks
        ];
        
    } catch (PDOException $e) {
        error_log("Database error in expire-demo-links.php: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'count' => 0,
            'links' => []
        ];
    } catch (Exception $e) {
        error_log("Error in expire-demo-links.php: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'count' => 0,
            'links' => []
        ];
    }
}

/**
 * Main execution
 */
function main(): void
{
    $startTime = microtime(true);
    $timestamp = date('Y-m-d H:i:s');
    
    echo "========================================\n";
    echo "Expire Demo Links Cron Script\n";
    echo "Started at: {$timestamp}\n";
    echo "========================================\n\n";
    
    $result = expireDemoLinks();
    
    if ($result['success']) {
        $count = $result['count'];
        
        echo "✓ Successfully processed expired demo links\n";
        echo "  Expired links: {$count}\n\n";
        
        if ($count > 0 && !empty($result['links'])) {
            echo "Expired Links Details:\n";
            echo str_repeat('-', 80) . "\n";
            printf("%-8s %-10s %-20s %-8s %-8s %-20s\n", 
                'ID', 'Lead ID', 'Expires At', 'Views', 'Max', 'Created At');
            echo str_repeat('-', 80) . "\n";
            
            foreach ($result['links'] as $link) {
                printf("%-8s %-10s %-20s %-8s %-8s %-20s\n",
                    $link['id'],
                    $link['lead_id'],
                    $link['expires_at'],
                    $link['views_count'],
                    $link['max_views'],
                    $link['created_at']
                );
            }
            echo str_repeat('-', 80) . "\n\n";
        } else {
            echo "  No expired links found.\n\n";
        }
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 4);
        echo "Execution time: {$executionTime} seconds\n";
        echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
        echo "========================================\n";
        
        // Exit with success code
        exit(0);
        
    } else {
        echo "✗ Error processing expired demo links\n";
        echo "  Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 4);
        echo "Execution time: {$executionTime} seconds\n";
        echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
        echo "========================================\n";
        
        // Exit with error code
        exit(1);
    }
}

// Run the script
main();

