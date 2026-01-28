<?php
/**
 * Fix Demo Expiry Times - Web Accessible Version
 * ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Fixes demo links where expires_at is incorrect (before or equal to created_at)
 * Sets expires_at to created_at + 60 minutes (1 hour)
 * 
 * Access via: http://your-domain/fix-demo-expiry-web.php
 */

// Load configuration
require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Demo Expiry Times</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #5568d3;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            background: #f8f9fa;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Fix Demo Expiry Times</h1>
        <p>This script will fix all demo links where <code>expires_at</code> is incorrect (before or equal to <code>created_at</code>).</p>
        <p>It will set <code>expires_at</code> to <code>created_at + 60 minutes</code>.</p>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
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
                $fixedDetails = [];
                
                foreach ($invalidLinks as $link) {
                    try {
                        // Use MySQL DATE_ADD to set expires_at to exactly 60 minutes after created_at
                        $updateStmt = $pdo->prepare("
                            UPDATE demo_links
                            SET expires_at = DATE_ADD(created_at, INTERVAL 60 MINUTE),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$link['id']]);
                        
                        // Get the updated expiry time
                        $checkStmt = $pdo->prepare("SELECT expires_at FROM demo_links WHERE id = ?");
                        $checkStmt->execute([$link['id']]);
                        $updated = $checkStmt->fetch();
                        
                        $fixedCount++;
                        $fixedDetails[] = [
                            'id' => $link['id'],
                            'lead_id' => $link['lead_id'],
                            'created_at' => $link['created_at'],
                            'old_expires_at' => $link['expires_at'],
                            'new_expires_at' => $updated['expires_at']
                        ];
                        
                    } catch (PDOException $e) {
                        $errors[] = "Error fixing demo_link ID {$link['id']}: " . $e->getMessage();
                        error_log("Error fixing demo_link ID {$link['id']}: " . $e->getMessage());
                    }
                }
                
                echo '<div class="result success">';
                echo '<h3>✓ Fix Completed</h3>';
                echo '<p><strong>Total invalid demo links found:</strong> ' . count($invalidLinks) . '</p>';
                echo '<p><strong>Successfully fixed:</strong> ' . $fixedCount . '</p>';
                echo '<p><strong>Errors:</strong> ' . count($errors) . '</p>';
                
                if (!empty($fixedDetails)) {
                    echo '<h4>Fixed Records:</h4>';
                    echo '<pre>';
                    foreach ($fixedDetails as $detail) {
                        echo "ID {$detail['id']} (Lead: {$detail['lead_id']}):\n";
                        echo "  Created: {$detail['created_at']}\n";
                        echo "  Old Expires: {$detail['old_expires_at']}\n";
                        echo "  New Expires: {$detail['new_expires_at']}\n\n";
                    }
                    echo '</pre>';
                }
                
                if (!empty($errors)) {
                    echo '<h4>Errors:</h4>';
                    echo '<ul>';
                    foreach ($errors as $error) {
                        echo '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
                    }
                    echo '</ul>';
                }
                
                echo '</div>';
                
            } catch (PDOException $e) {
                echo '<div class="result error">';
                echo '<h3>✗ Database Error</h3>';
                echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
                echo '</div>';
            } catch (Exception $e) {
                echo '<div class="result error">';
                echo '<h3>✗ Error</h3>';
                echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
                echo '</div>';
            }
        } else {
            // Show current status
            try {
                $pdo = getDbConnection();
                
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM demo_links
                    WHERE expires_at <= created_at
                    OR expires_at IS NULL
                ");
                $checkStmt->execute();
                $result = $checkStmt->fetch();
                $invalidCount = $result['count'];
                
                if ($invalidCount > 0) {
                    echo '<div class="result info">';
                    echo '<p><strong>Found ' . $invalidCount . ' demo link(s) with incorrect expiry times.</strong></p>';
                    echo '<p>Click the button below to fix them.</p>';
                    echo '</div>';
                } else {
                    echo '<div class="result success">';
                    echo '<p><strong>✓ All demo links have correct expiry times!</strong></p>';
                    echo '</div>';
                }
                
            } catch (PDOException $e) {
                echo '<div class="result error">';
                echo '<p>Error checking database: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
                echo '</div>';
            }
        }
        ?>
        
        <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['fix'])): ?>
            <form method="POST">
                <button type="submit" name="fix" class="btn">Fix Demo Expiry Times</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

