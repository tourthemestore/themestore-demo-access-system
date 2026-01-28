<?php
/**
 * Update Leads Table Script
 * Adds missing columns to the leads_for_demo table
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';

echo "<h2>Update Leads Table</h2>";

try {
    $pdo = getDbConnection();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Check current table structure
    echo "<h3>Current table structure:</h3>";
    $stmt = $pdo->query("DESCRIBE leads_for_demo");
    $currentColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Existing columns: " . implode(', ', $currentColumns) . "</p>";
    
    // Columns to add
    $columnsToAdd = [
        'company_name' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'location' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'mobile' => "VARCHAR(20) NOT NULL DEFAULT ''",
        'campaign_source' => "VARCHAR(255) DEFAULT NULL"
    ];
    
    echo "<h3>Adding missing columns...</h3>";
    
    // Add columns in order, updating currentColumns as we go
    $updatedColumns = $currentColumns;
    
    foreach ($columnsToAdd as $columnName => $columnDefinition) {
        if (!in_array($columnName, $updatedColumns)) {
            try {
                // Determine position based on what exists
                $position = '';
                if ($columnName === 'company_name') {
                    $position = 'AFTER `id`';
                } elseif ($columnName === 'location') {
                    // Check if company_name exists, otherwise add after id
                    if (in_array('company_name', $updatedColumns)) {
                        $position = 'AFTER `company_name`';
                    } else {
                        $position = 'AFTER `id`';
                    }
                } elseif ($columnName === 'mobile') {
                    // Add after email if it exists, otherwise at end
                    if (in_array('email', $updatedColumns)) {
                        $position = 'AFTER `email`';
                    } else {
                        $position = '';
                    }
                } elseif ($columnName === 'campaign_source') {
                    // Add after mobile if it exists, otherwise after email
                    if (in_array('mobile', $updatedColumns)) {
                        $position = 'AFTER `mobile`';
                    } elseif (in_array('email', $updatedColumns)) {
                        $position = 'AFTER `email`';
                    } else {
                        $position = '';
                    }
                }
                
                $sql = "ALTER TABLE `leads_for_demo` ADD COLUMN `{$columnName}` {$columnDefinition}";
                if ($position) {
                    $sql .= " {$position}";
                }
                
                $pdo->exec($sql);
                $updatedColumns[] = $columnName; // Update our tracking array
                echo "<p style='color: green;'>✓ Added column: {$columnName}</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>✗ Failed to add column {$columnName}: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p>SQL: " . htmlspecialchars($sql) . "</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠ Column {$columnName} already exists</p>";
        }
    }
    
    // Verify final structure
    echo "<h3>Final table structure:</h3>";
    $stmt = $pdo->query("DESCRIBE leads_for_demo");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($finalColumns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if all required columns exist
    $requiredColumns = ['company_name', 'location', 'email', 'mobile', 'campaign_source', 'status'];
    $existingColumnNames = array_column($finalColumns, 'Field');
    $missingColumns = array_diff($requiredColumns, $existingColumnNames);
    
    if (empty($missingColumns)) {
        echo "<p style='color: green; font-weight: bold;'>✓ All required columns exist! The table is ready to use.</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ Missing columns: " . implode(', ', $missingColumns) . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
}

echo "<hr>";
echo "<p><a href='../public/lead-form.php'>Back to Lead Form</a></p>";

