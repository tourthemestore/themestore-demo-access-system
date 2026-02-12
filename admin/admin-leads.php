<?php
/**
 * Admin Leads Page - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Lists all leads with verification status, demo expiry, and video completion
 */

// Load configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

/**
 * Get all leads with related data
 * @param string|null $dateFrom Start date (Y-m-d format)
 * @param string|null $dateTo End date (Y-m-d format)
 */
function getAllLeads(?string $dateFrom = null, ?string $dateTo = null): array
{
    try {
        $pdo = getDbConnection();
        
        // Build WHERE clause for date filtering
        $whereClause = '';
        $params = [];
        
        if (!empty($dateFrom) && !empty($dateTo)) {
            $whereClause = "WHERE DATE(l.created_at) BETWEEN :date_from AND :date_to";
            $params[':date_from'] = $dateFrom;
            $params[':date_to'] = $dateTo;
        } elseif (!empty($dateFrom)) {
            $whereClause = "WHERE DATE(l.created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        } elseif (!empty($dateTo)) {
            $whereClause = "WHERE DATE(l.created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        // Query to get all leads with demo link and video activity data
        $sql = "
            SELECT 
                l.id,
                l.company_name,
                l.location,
                l.email,
                l.mobile,
                l.campaign_source,
                l.status as verification_status,
                l.created_at as lead_created_at,
                l.updated_at as lead_updated_at,
                dl.id as demo_link_id,
                dl.status as demo_status,
                dl.expires_at as demo_expires_at,
                dl.created_at as demo_created_at,
                dl.views_count,
                dl.max_views,
                dl.accessed_at as demo_accessed_at,
                va.id as video_activity_id,
                va.status as video_status,
                va.progress_percentage,
                va.duration_watched,
                va.completed_at as video_completed_at
            FROM leads_for_demo l
            LEFT JOIN demo_links dl ON l.id = dl.lead_id AND dl.id = (
                SELECT id FROM demo_links 
                WHERE lead_id = l.id 
                ORDER BY created_at DESC 
                LIMIT 1
            )
            LEFT JOIN (
                SELECT va1.*
                FROM video_activity va1
                WHERE va1.id = (
                    SELECT va2.id
                    FROM video_activity va2
                    WHERE va2.lead_id = va1.lead_id
                    ORDER BY va2.progress_percentage DESC, va2.updated_at DESC
                    LIMIT 1
                )
            ) va ON va.lead_id = l.id
            {$whereClause}
            ORDER BY l.created_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Database error in admin-leads.php: " . $e->getMessage());
        return [];
    }
}

/**
 * Format verification status with badge
 */
function formatVerificationStatus(string $status): string
{
    $badges = [
        'pending' => '<span class="badge badge-pending">Pending</span>',
        'verified' => '<span class="badge badge-verified">Verified</span>',
        'active' => '<span class="badge badge-active">Active</span>',
        'expired' => '<span class="badge badge-expired">Expired</span>',
        'blocked' => '<span class="badge badge-blocked">Blocked</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Format demo expiry status (times shown in IST via formatDbDateTime)
 */
function formatDemoExpiry(?string $expiresAt, ?string $demoStatus, ?string $createdAt = null): string
{
    if (empty($expiresAt)) {
        return '<span class="text-muted">No demo link</span>';
    }
    
    $tz = new DateTimeZone('Asia/Kolkata');
    $expiresDt = new DateTime($expiresAt, $tz);
    $expiresTimestamp = $expiresDt->getTimestamp();
    $createdTimestamp = null;
    if (!empty($createdAt)) {
        $createdDt = new DateTime($createdAt, $tz);
        $createdTimestamp = $createdDt->getTimestamp();
    }
    $now = time();
    $isExpired = $expiresTimestamp < $now;
    
    // Validate: expiry should be after creation (if creation time is available)
    $isValidExpiry = true;
    if ($createdTimestamp !== null && $expiresTimestamp <= $createdTimestamp) {
        $isValidExpiry = false;
    }
    
    $formattedExpiryDate = formatDbDateTime($expiresAt);
    
    $statusBadge = '';
    if ($demoStatus === 'active') {
        $statusBadge = ' <span class="badge badge-active">Active</span>';
    } elseif ($demoStatus === 'expired') {
        $statusBadge = ' <span class="badge badge-expired">Expired</span>';
    } elseif ($demoStatus === 'used') {
        $statusBadge = ' <span class="badge badge-used">Used</span>';
    }
    
    // Show both created and expiry times for clarity (IST)
    $displayText = '';
    if (!empty($createdAt)) {
        $formattedCreatedDate = formatDbDateTime($createdAt);
        $displayText = '<div style="font-size: 11px; color: #666; margin-bottom: 2px;">Created: ' . htmlspecialchars($formattedCreatedDate, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    
    // Highlight if expiry is invalid (before or equal to creation)
    if (!$isValidExpiry) {
        $displayText .= '<div style="color: #dc3545;"><strong>⚠ Expires: ' . htmlspecialchars($formattedExpiryDate, ENT_QUOTES, 'UTF-8') . '</strong> <small>(Invalid - before creation)</small></div>';
    } else {
        $displayText .= '<div>Expires: <strong>' . htmlspecialchars($formattedExpiryDate, ENT_QUOTES, 'UTF-8') . '</strong></div>';
    }
    
    if ($isExpired) {
        return '<span class="text-expired">' . $displayText . '</span>' . $statusBadge;
    } else {
        return '<span class="text-active">' . $displayText . '</span>' . $statusBadge;
    }
}

/**
 * Format video completion percentage
 */
function formatVideoCompletion($progressPercentage, $videoStatus): string
{
    // Normalize inputs
    $progressPercentage = ($progressPercentage !== null && $progressPercentage !== '') ? (float) $progressPercentage : null;
    $videoStatus = ($videoStatus !== null && $videoStatus !== '') ? trim($videoStatus) : null;
    
    // If status is completed, always show 100%
    if ($videoStatus === 'completed') {
        return '<div class="progress-container">
                    <div class="progress-bar progress-complete" style="width: 100%"></div>
                    <span class="progress-text">100% Complete</span>
                </div>';
    }

    // If status is abandoned (closed window without completing)
    if ($videoStatus === 'abandoned') {
        $pct = ($progressPercentage !== null && $progressPercentage > 0) ? round($progressPercentage, 1) : 0;
        return '<div class="progress-container">
                    <div class="progress-bar progress-low" style="width: ' . min($pct, 100) . '%"></div>
                    <span class="progress-text">' . htmlspecialchars($pct, ENT_QUOTES, 'UTF-8') . '% — Abandoned</span>
                </div>';
    }
    
    // If no video activity exists at all (both are null/empty)
    if ($progressPercentage === null && empty($videoStatus)) {
        return '<span class="text-muted">Not started</span>';
    }
    
    // If we have a status but progress is null or 0, treat as 0%
    if ($progressPercentage === null || $progressPercentage <= 0) {
        $percentage = 0;
    } else {
        $percentage = round($progressPercentage, 1);
    }
    
    // Cap at 100%
    if ($percentage > 100) {
        $percentage = 100;
    }
    
    // Determine bar class based on percentage
    $barClass = 'progress-bar';
    if ($percentage >= 100) {
        $barClass .= ' progress-complete';
        return '<div class="progress-container">
                    <div class="progress-bar progress-complete" style="width: 100%"></div>
                    <span class="progress-text">100% Complete</span>
                </div>';
    } elseif ($percentage >= 50) {
        $barClass .= ' progress-high';
    } elseif ($percentage >= 25) {
        $barClass .= ' progress-medium';
    } else {
        $barClass .= ' progress-low';
    }
    
    return '<div class="progress-container">
                <div class="progress-bar ' . htmlspecialchars($barClass, ENT_QUOTES, 'UTF-8') . '" style="width: ' . htmlspecialchars($percentage, ENT_QUOTES, 'UTF-8') . '%"></div>
                <span class="progress-text">' . htmlspecialchars($percentage, ENT_QUOTES, 'UTF-8') . '%</span>
            </div>';
}

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

// Validate and sanitize date inputs
if (!empty($dateFrom)) {
    $dateFrom = date('Y-m-d', strtotime($dateFrom));
}
if (!empty($dateTo)) {
    $dateTo = date('Y-m-d', strtotime($dateTo));
}

// Get all leads with filters
$leads = getAllLeads($dateFrom, $dateTo);
$totalLeads = count($leads);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Leads Management | ThemeStore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header .stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .header .stat-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 6px;
        }
        .header .stat-value {
            font-size: 24px;
            font-weight: bold;
        }
        .header .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        thead {
            background: #f8f9fa;
        }
        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #dee2e6;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        tbody tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-pending {
            background: #ffc107;
            color: #000;
        }
        .badge-verified {
            background: #28a745;
            color: white;
        }
        .badge-active {
            background: #17a2b8;
            color: white;
        }
        .badge-expired {
            background: #6c757d;
            color: white;
        }
        .badge-blocked {
            background: #dc3545;
            color: white;
        }
        .badge-used {
            background: #6c757d;
            color: white;
        }
        .text-muted {
            color: #6c757d;
        }
        .text-expired {
            color: #dc3545;
        }
        .text-active {
            color: #28a745;
        }
        .progress-container {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
        }
        .progress-bar {
            height: 20px;
            background: #e9ecef;
            border-radius: 4px;
            min-width: 50px;
            position: relative;
        }
        .progress-bar.progress-low {
            background: #dc3545;
        }
        .progress-bar.progress-medium {
            background: #ffc107;
        }
        .progress-bar.progress-high {
            background: #17a2b8;
        }
        .progress-bar.progress-complete {
            background: #28a745;
        }
        .progress-text {
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .email-link {
            color: #667eea;
            text-decoration: none;
        }
        .email-link:hover {
            text-decoration: underline;
        }
        tbody tr {
            cursor: default;
        }
        tbody tr:hover {
            background: #f0f4ff;
        }
        tbody tr td:nth-child(2) a {
            display: block;
            padding: 2px 0;
            font-weight: 500;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
        }
        .filter-group input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            min-width: 150px;
        }
        .filter-group input[type="date"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .filter-active {
            background: #e7f3ff;
            border-color: #667eea;
        }
        .filter-info {
            margin-top: 10px;
            padding: 10px;
            background: #fff;
            border-radius: 4px;
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Leads Management</h1>
            <p>View and manage all leads, verification status, demo links, and video activity</p>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $totalLeads; ?></div>
                    <div class="stat-label">Total Leads</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo count(array_filter($leads, fn($l) => $l['verification_status'] === 'verified')); ?></div>
                    <div class="stat-label">Verified</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo count(array_filter($leads, fn($l) => !empty($l['demo_link_id']))); ?></div>
                    <div class="stat-label">With Demo Links</div>
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="filter-section <?php echo (!empty($dateFrom) || !empty($dateTo)) ? 'filter-active' : ''; ?>">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <?php if (!empty($dateFrom) || !empty($dateTo)): ?>
                            <a href="admin-leads.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if (!empty($dateFrom) || !empty($dateTo)): ?>
                    <div class="filter-info">
                        <strong>Filtered by:</strong> 
                        <?php 
                        if (!empty($dateFrom) && !empty($dateTo)) {
                            echo date('d-m-Y', strtotime($dateFrom)) . ' to ' . date('d-m-Y', strtotime($dateTo));
                        } elseif (!empty($dateFrom)) {
                            echo 'From ' . date('d-m-Y', strtotime($dateFrom));
                        } elseif (!empty($dateTo)) {
                            echo 'Until ' . date('d-m-Y', strtotime($dateTo));
                        }
                        ?>
                        <span style="margin-left: 15px;">Showing <?php echo $totalLeads; ?> lead(s)</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($leads)): ?>
                <div class="no-data">
                    <p>No leads found.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Company Name</th>
                            <th>Location</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Verification Status</th>
                            <th>Demo Expiry</th>
                            <th>Video Completion</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lead['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <a href="admin-lead-detail.php?id=<?php echo $lead['id']; ?>" class="email-link" style="text-decoration: none; color: #667eea; font-weight: 500;">
                                        <?php echo htmlspecialchars($lead['company_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($lead['location'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8'); ?>" class="email-link">
                                        <?php echo htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($lead['mobile'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo formatVerificationStatus($lead['verification_status']); ?></td>
                                <td><?php echo formatDemoExpiry($lead['demo_expires_at'], $lead['demo_status'], $lead['demo_created_at'] ?? null); ?></td>
                                <td><?php echo formatVideoCompletion($lead['progress_percentage'] ?? null, $lead['video_status'] ?? null); ?></td>
                                <td><?php echo formatDbDateTime($lead['lead_created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

