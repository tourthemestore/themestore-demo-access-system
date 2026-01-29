<?php
/**
 * Admin Lead Detail Page - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Shows detailed lead information, OTP verification, demo links, and video activity timeline
 */

// Load configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

// Get lead ID from URL
$leadId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($leadId <= 0) {
    die('Invalid lead ID');
}

/**
 * Get lead information
 */
function getLeadInfo(int $leadId): ?array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM leads_for_demo WHERE id = ? LIMIT 1");
        $stmt->execute([$leadId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log("Database error in admin-lead-detail.php: " . $e->getMessage());
        return null;
    }
}

/**
 * Get OTP verification history
 */
function getOtpHistory(int $leadId): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM otp_verifications
            WHERE lead_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in admin-lead-detail.php: " . $e->getMessage());
        return [];
    }
}

/**
 * Get demo links for lead
 */
function getDemoLinks(int $leadId): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM demo_links
            WHERE lead_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in admin-lead-detail.php: " . $e->getMessage());
        return [];
    }
}

/**
 * Get video activity for lead
 */
function getVideoActivity(int $leadId): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT va.*, dl.token
            FROM video_activity va
            LEFT JOIN demo_links dl ON va.demo_link_id = dl.id
            WHERE va.lead_id = ?
            ORDER BY va.created_at DESC
        ");
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in admin-lead-detail.php: " . $e->getMessage());
        return [];
    }
}

/**
 * Get follow-ups for lead
 */
function getFollowups(int $leadId): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT *
            FROM demo_followups
            WHERE lead_id = ?
            ORDER BY followup_date DESC, created_at DESC
        ");
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in admin-lead-detail.php: " . $e->getMessage());
        return [];
    }
}

/**
 * Get single follow-up by ID
 */
function getFollowupById(int $followupId, int $leadId): ?array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT *
            FROM demo_followups
            WHERE id = ? AND lead_id = ?
            LIMIT 1
        ");
        $stmt->execute([$followupId, $leadId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log("Database error in admin-lead-detail.php: " . $e->getMessage());
        return null;
    }
}

/**
 * Get queries for lead
 */
function getQueries(int $leadId): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT dq.*, dl.token as demo_token
            FROM demo_queries dq
            LEFT JOIN demo_links dl ON dq.demo_link_id = dl.id
            WHERE dq.lead_id = ?
            ORDER BY dq.created_at DESC
        ");
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in admin-lead-detail.php: " . $e->getMessage());
        return [];
    }
}

/**
 * Build activity timeline
 */
function buildActivityTimeline(array $otpHistory, array $demoLinks, array $videoActivity, array $followups = [], array $queries = []): array
{
    $timeline = [];
    
    // Add OTP events
    foreach ($otpHistory as $otp) {
        $timeline[] = [
            'type' => 'otp',
            'timestamp' => strtotime($otp['created_at']),
            'date' => $otp['created_at'],
            'title' => 'OTP Sent',
            'description' => 'OTP code sent to email',
            'status' => $otp['status'],
            'data' => $otp
        ];
        
        if ($otp['verified_at']) {
            $timeline[] = [
                'type' => 'otp_verified',
                'timestamp' => strtotime($otp['verified_at']),
                'date' => $otp['verified_at'],
                'title' => 'OTP Verified',
                'description' => 'OTP successfully verified',
                'status' => 'verified',
                'data' => $otp
            ];
        }
    }
    
    // Add demo link events
    foreach ($demoLinks as $demo) {
        $timeline[] = [
            'type' => 'demo_link',
            'timestamp' => strtotime($demo['created_at']),
            'date' => $demo['created_at'],
            'title' => 'Demo Link Created',
            'description' => 'Demo access link generated',
            'status' => $demo['status'],
            'data' => $demo
        ];
        
        if ($demo['accessed_at']) {
            $timeline[] = [
                'type' => 'demo_accessed',
                'timestamp' => strtotime($demo['accessed_at']),
                'date' => $demo['accessed_at'],
                'title' => 'Demo Link Accessed',
                'description' => 'Demo link was accessed',
                'status' => $demo['status'],
                'data' => $demo
            ];
        }
    }
    
    // Add video activity events
    foreach ($videoActivity as $activity) {
        $timeline[] = [
            'type' => 'video_' . $activity['status'],
            'timestamp' => strtotime($activity['created_at']),
            'date' => $activity['created_at'],
            'title' => ucfirst($activity['status']) . ' Video',
            'description' => 'Video ' . $activity['status'] . ' - ' . $activity['progress_percentage'] . '% complete',
            'status' => $activity['status'],
            'data' => $activity
        ];
        
        if ($activity['last_progress_at']) {
            $timeline[] = [
                'type' => 'video_progress',
                'timestamp' => strtotime($activity['last_progress_at']),
                'date' => $activity['last_progress_at'],
                'title' => 'Video Progress Update',
                'description' => 'Progress: ' . $activity['progress_percentage'] . '% (' . $activity['duration_watched'] . 's)',
                'status' => 'progress',
                'data' => $activity
            ];
        }
        
        if ($activity['completed_at']) {
            $timeline[] = [
                'type' => 'video_completed',
                'timestamp' => strtotime($activity['completed_at']),
                'date' => $activity['completed_at'],
                'title' => 'Video Completed',
                'description' => 'Video watched to completion',
                'status' => 'completed',
                'data' => $activity
            ];
        }
    }
    
    // Add follow-up events
    foreach ($followups as $followup) {
        $timeline[] = [
            'type' => 'followup',
            'timestamp' => strtotime($followup['created_at']),
            'date' => $followup['created_at'],
            'title' => 'Follow-up: ' . ucfirst($followup['followup_type']),
            'description' => $followup['subject'] ?: ($followup['notes'] ? substr($followup['notes'], 0, 50) . '...' : 'No details'),
            'status' => $followup['status'],
            'data' => $followup
        ];
        
        if ($followup['followup_date']) {
            $timeline[] = [
                'type' => 'followup_scheduled',
                'timestamp' => strtotime($followup['followup_date']),
                'date' => $followup['followup_date'],
                'title' => 'Scheduled Follow-up: ' . ucfirst($followup['followup_type']),
                'description' => $followup['subject'] ?: 'Scheduled follow-up',
                'status' => $followup['status'],
                'data' => $followup
            ];
        }
    }
    
    // Add query events
    foreach ($queries as $query) {
        $timeline[] = [
            'type' => 'query',
            'timestamp' => strtotime($query['created_at']),
            'date' => $query['created_at'],
            'title' => 'Client Query',
            'description' => substr($query['query_text'], 0, 100) . (strlen($query['query_text']) > 100 ? '...' : ''),
            'status' => $query['status'],
            'data' => $query
        ];
        
        if ($query['admin_response']) {
            $timeline[] = [
                'type' => 'query_response',
                'timestamp' => strtotime($query['updated_at']),
                'date' => $query['updated_at'],
                'title' => 'Query Response',
                'description' => substr($query['admin_response'], 0, 100) . (strlen($query['admin_response']) > 100 ? '...' : ''),
                'status' => $query['status'],
                'data' => $query
            ];
        }
        
        if ($query['scheduled_call_date']) {
            $timeline[] = [
                'type' => 'query_scheduled',
                'timestamp' => strtotime($query['scheduled_call_date']),
                'date' => $query['scheduled_call_date'],
                'title' => 'Call Scheduled for Query',
                'description' => 'Call scheduled in response to query',
                'status' => 'scheduled',
                'data' => $query
            ];
        }
    }
    
    // Sort by timestamp (newest first)
    usort($timeline, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    return $timeline;
}

/**
 * Format status badge
 */
function formatStatusBadge(string $status, string $type = 'default'): string
{
    $badges = [
        'pending' => '<span class="badge badge-pending">Pending</span>',
        'verified' => '<span class="badge badge-verified">Verified</span>',
        'active' => '<span class="badge badge-active">Active</span>',
        'expired' => '<span class="badge badge-expired">Expired</span>',
        'blocked' => '<span class="badge badge-blocked">Blocked</span>',
        'failed' => '<span class="badge badge-failed">Failed</span>',
        'used' => '<span class="badge badge-used">Used</span>',
        'started' => '<span class="badge badge-started">Started</span>',
        'progress' => '<span class="badge badge-progress">Progress</span>',
        'completed' => '<span class="badge badge-completed">Completed</span>',
        'abandoned' => '<span class="badge badge-abandoned">Abandoned</span>',
        'rescheduled' => '<span class="badge badge-rescheduled">Rescheduled</span>',
        'answered' => '<span class="badge badge-answered">Answered</span>',
        'scheduled' => '<span class="badge badge-scheduled">Scheduled</span>',
        'resolved' => '<span class="badge badge-resolved">Resolved</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
}

// Get data
$lead = getLeadInfo($leadId);
if (!$lead) {
    die('Lead not found');
}

$otpHistory = getOtpHistory($leadId);
$demoLinks = getDemoLinks($leadId);
$videoActivity = getVideoActivity($leadId);
$followups = getFollowups($leadId);
$queries = getQueries($leadId);
$timeline = buildActivityTimeline($otpHistory, $demoLinks, $videoActivity, $followups, $queries);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Details - <?php echo htmlspecialchars($lead['company_name'], ENT_QUOTES, 'UTF-8'); ?> | ThemeStore Admin</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .back-link {
            color: white;
            text-decoration: none;
            opacity: 0.9;
            margin-bottom: 10px;
            display: inline-block;
        }
        .back-link:hover {
            opacity: 1;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .card h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-pending { background: #ffc107; color: #000; }
        .badge-verified { background: #28a745; color: white; }
        .badge-active { background: #17a2b8; color: white; }
        .badge-expired { background: #6c757d; color: white; }
        .badge-blocked { background: #dc3545; color: white; }
        .badge-failed { background: #dc3545; color: white; }
        .badge-used { background: #6c757d; color: white; }
        .badge-started { background: #17a2b8; color: white; }
        .badge-progress { background: #ffc107; color: #000; }
        .badge-completed { background: #28a745; color: white; }
        .badge-abandoned { background: #dc3545; color: white; }
        .badge-rescheduled { background: #17a2b8; color: white; }
        .badge-answered { background: #20c997; color: white; }
        .badge-scheduled { background: #0dcaf0; color: #000; }
        .badge-resolved { background: #28a745; color: white; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th {
            padding: 12px;
            text-align: left;
            background: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        tbody tr:hover {
            background: #f8f9fa;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            padding-left: 30px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }
        .timeline-item.otp::before { background: #17a2b8; box-shadow: 0 0 0 2px #17a2b8; }
        .timeline-item.otp_verified::before { background: #28a745; box-shadow: 0 0 0 2px #28a745; }
        .timeline-item.demo_link::before { background: #667eea; box-shadow: 0 0 0 2px #667eea; }
        .timeline-item.demo_accessed::before { background: #ffc107; box-shadow: 0 0 0 2px #ffc107; }
        .timeline-item.video_started::before { background: #17a2b8; box-shadow: 0 0 0 2px #17a2b8; }
        .timeline-item.video_progress::before { background: #ffc107; box-shadow: 0 0 0 2px #ffc107; }
        .timeline-item.video_completed::before { background: #28a745; box-shadow: 0 0 0 2px #28a745; }
        .timeline-item.followup::before { background: #6f42c1; box-shadow: 0 0 0 2px #6f42c1; }
        .timeline-item.followup_scheduled::before { background: #e83e8c; box-shadow: 0 0 0 2px #e83e8c; }
        .timeline-item.query::before { background: #fd7e14; box-shadow: 0 0 0 2px #fd7e14; }
        .timeline-item.query_response::before { background: #20c997; box-shadow: 0 0 0 2px #20c997; }
        .timeline-item.query_scheduled::before { background: #0dcaf0; box-shadow: 0 0 0 2px #0dcaf0; }
        .timeline-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .timeline-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .timeline-date {
            color: #999;
            font-size: 12px;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .progress-bar {
            height: 20px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="admin-leads.php" class="back-link">← Back to Leads</a>
            <h1><?php echo htmlspecialchars($lead['company_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Lead ID: <?php echo $lead['id']; ?> | <?php echo formatStatusBadge($lead['status']); ?></p>
        </div>

        <!-- Lead Information -->
        <div class="card">
            <h2>Lead Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Company Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($lead['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Location</span>
                    <span class="info-value"><?php echo htmlspecialchars($lead['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value">
                        <a href="mailto:<?php echo htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Mobile</span>
                    <span class="info-value"><?php echo htmlspecialchars($lead['mobile'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Campaign Source</span>
                    <span class="info-value"><?php echo htmlspecialchars($lead['campaign_source'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Interest</span>
                    <span class="info-value"><?php
                        $interest = $lead['interest'] ?? null;
                        if ($interest === 'interested') {
                            echo '<span class="badge badge-verified">👍 Interested</span>';
                        } elseif ($interest === 'not_interested') {
                            echo '<span class="badge badge-pending">👎 Not interested</span>';
                        } else {
                            echo '—';
                        }
                    ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Verification Status</span>
                    <span class="info-value"><?php echo formatStatusBadge($lead['status']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Created At</span>
                    <span class="info-value"><?php echo formatDbDateTime($lead['created_at']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Updated</span>
                    <span class="info-value"><?php echo formatDbDateTime($lead['updated_at']); ?></span>
                </div>
            </div>
        </div>

        <!-- OTP Verification History -->
        <div class="card">
            <h2>OTP Verification History</h2>
            <?php if (empty($otpHistory)): ?>
                <div class="no-data">No OTP verification attempts found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>Expires At</th>
                            <th>Verified At</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($otpHistory as $otp): ?>
                            <tr>
                                <td><?php echo $otp['id']; ?></td>
                                <td><?php echo formatStatusBadge($otp['status']); ?></td>
                                <td><?php echo $otp['attempts']; ?> / <?php echo $otp['max_attempts']; ?></td>
                                <td><?php echo formatDbDateTime($otp['expires_at']); ?></td>
                                <td><?php echo $otp['verified_at'] ? formatDbDateTime($otp['verified_at']) : 'N/A'; ?></td>
                                <td><?php echo formatDbDateTime($otp['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Demo Links -->
        <div class="card">
            <h2>Demo Links</h2>
            <?php if (empty($demoLinks)): ?>
                <div class="no-data">No demo links found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Expires At</th>
                            <th>Accessed At</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demoLinks as $demo): ?>
                            <tr>
                                <td><?php echo $demo['id']; ?></td>
                                <td><?php echo formatStatusBadge($demo['status']); ?></td>
                                <td><?php echo $demo['views_count']; ?> / <?php echo $demo['max_views']; ?></td>
                                <td><?php echo formatDbDateTime($demo['expires_at']); ?></td>
                                <td><?php echo $demo['accessed_at'] ? formatDbDateTime($demo['accessed_at']) : 'Never'; ?></td>
                                <td><?php echo formatDbDateTime($demo['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Video Activity -->
        <div class="card">
            <h2>Video Activity</h2>
            <?php if (empty($videoActivity)): ?>
                <div class="no-data">No video activity found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Duration Watched</th>
                            <th>Started At</th>
                            <th>Last Progress</th>
                            <th>Completed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videoActivity as $activity): ?>
                            <tr>
                                <td><?php echo $activity['id']; ?></td>
                                <td><?php echo formatStatusBadge($activity['status']); ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $activity['progress_percentage']; ?>%"></div>
                                    </div>
                                    <?php echo $activity['progress_percentage']; ?>%
                                </td>
                                <td><?php echo $activity['duration_watched']; ?>s</td>
                                <td><?php echo formatDbDateTime($activity['started_at']); ?></td>
                                <td><?php echo $activity['last_progress_at'] ? formatDbDateTime($activity['last_progress_at']) : 'N/A'; ?></td>
                                <td><?php echo $activity['completed_at'] ? formatDbDateTime($activity['completed_at']) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Client Queries -->
        <div class="card" id="clientQueriesCard">
            <h2>Client Queries</h2>
            <?php if (empty($queries)): ?>
                <div class="no-data">No queries found.</div>
            <?php else: ?>
                <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <button onclick="selectAllQueries()" style="background: #6c757d; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; margin-right: 10px;">Select All</button>
                        <button onclick="deselectAllQueries()" style="background: #6c757d; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; margin-right: 10px;">Deselect All</button>
                    </div>
                    <button onclick="openResponseModal()" id="respondBtn" style="background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;" disabled>Respond to Selected Queries</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox" onchange="toggleAllQueries(this)"></th>
                            <th>ID</th>
                            <th>Query</th>
                            <th>Status</th>
                            <th>Admin Response</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queries as $query): ?>
                            <tr>
                                <td><input type="checkbox" class="query-checkbox" value="<?php echo $query['id']; ?>" onchange="updateRespondButton()"></td>
                                <td><?php echo $query['id']; ?></td>
                                <td style="max-width: 300px; word-wrap: break-word;"><?php echo htmlspecialchars($query['query_text'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo formatStatusBadge($query['status']); ?></td>
                                <td style="max-width: 250px; word-wrap: break-word;"><?php echo htmlspecialchars($query['admin_response'] ?: 'No response yet', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo formatDbDateTime($query['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Response Modal -->
        <div id="responseModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 8px; padding: 30px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top: 0; margin-bottom: 20px; color: #333;">Respond to Selected Queries</h2>
                <div id="selectedQueriesList" style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px; max-height: 200px; overflow-y: auto;">
                    <!-- Selected queries will be listed here -->
                </div>
                <div style="margin-bottom: 20px;">
                    <label for="responseText" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Your Response:</label>
                    <textarea id="responseText" rows="6" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; font-size: 14px; resize: vertical;" placeholder="Enter your response to the selected queries..."></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button onclick="closeResponseModal()" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">Cancel</button>
                    <button onclick="sendBulkResponse()" style="background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">Send Response</button>
                </div>
            </div>
        </div>

        <!-- Follow-ups -->
        <div class="card">
            <h2>Follow-ups</h2>
            
            <!-- Add/Edit Follow-up Form -->
            <div class="followup-form" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px; font-size: 16px;" id="followupFormTitle">Add New Follow-up</h3>
                <form id="followupForm" onsubmit="saveFollowup(event)">
                    <input type="hidden" name="lead_id" value="<?php echo $leadId; ?>">
                    <input type="hidden" name="followup_id" id="followup_id" value="">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Type</label>
                            <select name="followup_type" id="followup_type" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="note">Note</option>
                                <option value="call">Call</option>
                                <option value="email">Email</option>
                                <option value="meeting">Meeting</option>
                                <option value="reminder">Reminder</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Status</label>
                            <select name="status" id="followup_status" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="rescheduled">Rescheduled</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Subject</label>
                        <input type="text" name="subject" id="followup_subject" placeholder="Follow-up subject" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Notes</label>
                        <textarea name="notes" id="followup_notes" rows="3" placeholder="Follow-up notes..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Scheduled Date/Time (Optional)</label>
                        <input type="datetime-local" name="followup_date" id="followup_date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" id="followupSubmitBtn" style="background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Add Follow-up</button>
                        <button type="button" id="followupCancelBtn" onclick="cancelEdit()" style="display: none; background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Cancel</button>
                    </div>
                </form>
            </div>
            
            <!-- Follow-ups List -->
            <?php if (empty($followups)): ?>
                <div class="no-data">No follow-ups found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Scheduled Date</th>
                            <th>Notes</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($followups as $followup): ?>
                            <tr>
                                <td><?php echo ucfirst($followup['followup_type']); ?></td>
                                <td><?php echo htmlspecialchars($followup['subject'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo formatStatusBadge($followup['status']); ?></td>
                                <td><?php echo $followup['followup_date'] ? formatDbDateTime($followup['followup_date']) : 'Not scheduled'; ?></td>
                                <td><?php echo htmlspecialchars($followup['notes'] ? substr($followup['notes'], 0, 50) . (strlen($followup['notes']) > 50 ? '...' : '') : 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($followup['created_by'] ?: 'Admin', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo formatDbDateTime($followup['created_at']); ?></td>
                                <td>
                                    <button onclick="editFollowup(<?php echo $followup['id']; ?>)" style="background: #17a2b8; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Activity Timeline -->
        <div class="card">
            <h2>Activity Timeline</h2>
            <?php if (empty($timeline)): ?>
                <div class="no-data">No activity timeline available.</div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($timeline as $item): ?>
                        <div class="timeline-item <?php echo htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="timeline-title"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="timeline-description"><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="timeline-date"><?php echo formatDbDateTime($item['date']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Follow-up data stored in PHP
        const followupsData = <?php echo json_encode($followups); ?>;
        
        function saveFollowup(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            fetch('../api/add-followup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(formData.get('followup_id') ? 'Follow-up updated successfully!' : 'Follow-up added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        function editFollowup(followupId) {
            // Find the follow-up in the data
            const followup = followupsData.find(f => f.id == followupId);
            
            if (!followup) {
                alert('Follow-up not found');
                return;
            }
            
            // Populate form with follow-up data
            document.getElementById('followup_id').value = followup.id;
            document.getElementById('followup_type').value = followup.followup_type;
            document.getElementById('followup_status').value = followup.status;
            document.getElementById('followup_subject').value = followup.subject || '';
            document.getElementById('followup_notes').value = followup.notes || '';
            
            // Format datetime-local (YYYY-MM-DDTHH:mm)
            if (followup.followup_date) {
                const date = new Date(followup.followup_date);
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                document.getElementById('followup_date').value = `${year}-${month}-${day}T${hours}:${minutes}`;
            } else {
                document.getElementById('followup_date').value = '';
            }
            
            // Update UI for edit mode
            document.getElementById('followupFormTitle').textContent = 'Edit Follow-up';
            document.getElementById('followupSubmitBtn').textContent = 'Update Follow-up';
            document.getElementById('followupCancelBtn').style.display = 'inline-block';
            
            // Scroll to form
            document.querySelector('.followup-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        function cancelEdit() {
            // Reset form
            document.getElementById('followupForm').reset();
            document.getElementById('followup_id').value = '';
            document.getElementById('followupFormTitle').textContent = 'Add New Follow-up';
            document.getElementById('followupSubmitBtn').textContent = 'Add Follow-up';
            document.getElementById('followupCancelBtn').style.display = 'none';
        }
        
        // Query response functions
        const queriesData = <?php echo json_encode($queries); ?>;
        
        function updateRespondButton() {
            const checkedBoxes = document.querySelectorAll('#clientQueriesCard .query-checkbox:checked');
            const respondBtn = document.getElementById('respondBtn');
            if (checkedBoxes.length > 0) {
                respondBtn.disabled = false;
                respondBtn.textContent = `Respond to Selected Queries (${checkedBoxes.length})`;
            } else {
                respondBtn.disabled = true;
                respondBtn.textContent = 'Respond to Selected Queries';
            }
        }
        
        function selectAllQueries() {
            document.querySelectorAll('#clientQueriesCard .query-checkbox').forEach(cb => cb.checked = true);
            const sel = document.getElementById('selectAllCheckbox');
            if (sel) sel.checked = true;
            updateRespondButton();
        }
        
        function deselectAllQueries() {
            document.querySelectorAll('#clientQueriesCard .query-checkbox').forEach(cb => cb.checked = false);
            const sel = document.getElementById('selectAllCheckbox');
            if (sel) sel.checked = false;
            updateRespondButton();
        }
        
        function toggleAllQueries(checkbox) {
            document.querySelectorAll('#clientQueriesCard .query-checkbox').forEach(cb => cb.checked = checkbox.checked);
            updateRespondButton();
        }
        
        function openResponseModal() {
            const checkedBoxes = document.querySelectorAll('#clientQueriesCard .query-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one query to respond to.');
                return;
            }
            
            // Build selected list from DOM (checkbox -> row -> ID + Query cells)
            const listContainer = document.getElementById('selectedQueriesList');
            const fragments = ['<strong>Selected Queries (' + checkedBoxes.length + '):</strong><br><br>'];
            checkedBoxes.forEach((cb) => {
                const row = cb.closest('tr');
                if (!row) return;
                const cells = row.querySelectorAll('td');
                const id = (cells[1] && cells[1].textContent.trim()) || cb.value;
                const queryText = (cells[2] && cells[2].textContent.trim()) || '';
                const display = queryText.length > 100 ? queryText.substring(0, 100) + '...' : queryText;
                fragments.push('<div style="margin-bottom: 10px; padding: 10px; background: white; border-left: 3px solid #17a2b8; border-radius: 4px;"><strong>Query #' + id + ':</strong> ' + escapeHtml(display) + '</div>');
            });
            listContainer.innerHTML = fragments.join('');
            
            // Clear previous response
            document.getElementById('responseText').value = '';
            
            // Show modal
            document.getElementById('responseModal').style.display = 'flex';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function closeResponseModal() {
            document.getElementById('responseModal').style.display = 'none';
        }
        
        function sendBulkResponse() {
            const checkedBoxes = document.querySelectorAll('#clientQueriesCard .query-checkbox:checked');
            const responseText = document.getElementById('responseText').value.trim();
            
            if (checkedBoxes.length === 0) {
                alert('Please select at least one query to respond to.');
                return;
            }
            
            if (!responseText) {
                alert('Please enter a response.');
                return;
            }
            
            const selectedIds = Array.from(checkedBoxes).map(cb => parseInt(cb.value));
            
            // Disable button during processing
            const sendBtn = document.querySelector('#responseModal button[onclick="sendBulkResponse()"]');
            const originalText = sendBtn.textContent;
            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';
            
            // Send ONE request with all query IDs and one response
            fetch('../api/bulk-respond-queries.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    query_ids: selectedIds,
                    admin_response: responseText
                })
            })
            .then(response => response.json())
            .then(data => {
                sendBtn.disabled = false;
                sendBtn.textContent = originalText;
                
                if (data.success) {
                    alert(data.message || 'Response saved and email sent successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to send response'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                sendBtn.disabled = false;
                sendBtn.textContent = originalText;
                alert('An error occurred. Please try again.');
            });
        }
        
    </script>
</body>
</html>

