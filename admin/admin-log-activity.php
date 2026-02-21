<?php
/**
 * Admin User Activity Log - ThemeStore Demo Access System
 * Shows login/logout log from demo_leads_user_log table.
 * Admin only. With pagination and userwise filter.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

// Only admin can access this page
if (empty($loggedInUser['is_admin'])) {
    die('Access denied. Admin only.');
}

$perPage = 25;

/**
 * Get distinct users for the dropdown filter.
 */
function getLogUsers(): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT DISTINCT emp_id, user_name, emp_name
            FROM demo_leads_user_log
            ORDER BY emp_name ASC, user_name ASC
        ");
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get total count of logs with filters.
 */
function getLogCount(?string $dateFrom, ?string $dateTo, ?string $userId): int
{
    try {
        $pdo = getDbConnection();
        $conditions = [];
        $params = [];

        if (!empty($dateFrom)) {
            $conditions[] = "DATE(logged_at) >= ?";
            $params[] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $conditions[] = "DATE(logged_at) <= ?";
            $params[] = $dateTo;
        }
        if ($userId !== null && $userId !== '') {
            $conditions[] = "emp_id = ?";
            $params[] = (int) $userId;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM demo_leads_user_log $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Get paginated user activity logs with filters.
 */
function getUserActivityLogs(?string $dateFrom, ?string $dateTo, ?string $userId, int $offset, int $limit): array
{
    try {
        $pdo = getDbConnection();
        $conditions = [];
        $params = [];

        if (!empty($dateFrom)) {
            $conditions[] = "DATE(logged_at) >= ?";
            $params[] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $conditions[] = "DATE(logged_at) <= ?";
            $params[] = $dateTo;
        }
        if ($userId !== null && $userId !== '') {
            $conditions[] = "emp_id = ?";
            $params[] = (int) $userId;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = $pdo->prepare("
            SELECT id, emp_id, user_name, emp_name, role, action, logged_at
            FROM demo_leads_user_log
            $where
            ORDER BY logged_at DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log("getUserActivityLogs error: " . $e->getMessage());
        return [];
    }
}

// Filters
$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to'] ?? null;
$userId   = $_GET['user_id'] ?? null;
$page     = max(1, (int) ($_GET['page'] ?? 1));

if (!empty($dateFrom)) $dateFrom = date('Y-m-d', strtotime($dateFrom));
if (!empty($dateTo))   $dateTo   = date('Y-m-d', strtotime($dateTo));

// Data
$logUsers    = getLogUsers();
$totalLogs   = getLogCount($dateFrom, $dateTo, $userId);
$totalPages  = max(1, (int) ceil($totalLogs / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset      = ($page - 1) * $perPage;
$activityLogs = getUserActivityLogs($dateFrom, $dateTo, $userId, $offset, $perPage);

$hasFilters = !empty($dateFrom) || !empty($dateTo) || ($userId !== null && $userId !== '');

// Build query string helper for pagination links (preserves filters)
function buildQs(int $pg): string {
    $params = $_GET;
    $params['page'] = $pg;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Log | ThemeStore Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
        }
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .header h1 { font-size: 22px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .header a { color: #fff; text-decoration: underline; font-size: 14px; }
        .content { padding: 20px 25px; }
        .filter-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
        }
        .filter-group input[type="date"],
        .filter-group select {
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            min-width: 160px;
        }
        .btn {
            display: inline-block;
            padding: 8px 18px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-primary:hover { background: #5568d3; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .btn-secondary:hover { background: #5a6268; }
        .filter-active { background: #e7f3ff; border-color: #667eea; }
        .stats {
            display: flex;
            gap: 20px;
            margin-top: 12px;
        }
        .stat-item { text-align: center; }
        .stat-value { font-size: 22px; font-weight: 700; }
        .stat-label { font-size: 12px; opacity: 0.85; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        tr:hover { background: #f8f9fa; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-login { background: #d4edda; color: #155724; }
        .badge-logout { background: #f8d7da; color: #721c24; }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #888;
            font-size: 15px;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            text-decoration: none;
            color: #333;
        }
        .pagination a:hover { background: #667eea; color: #fff; border-color: #667eea; }
        .pagination .active { background: #667eea; color: #fff; border-color: #667eea; font-weight: 700; }
        .pagination .disabled { color: #aaa; pointer-events: none; }
        .page-info {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-row">
                <h1>User Login / Logout Log</h1>
                <div>
                    <a href="index.php">&larr; Dashboard</a>
                    &nbsp;|&nbsp;
                    <a href="?logout=1">Logout</a>
                </div>
            </div>
            <p>Track when users logged in, logged out, or closed the browser</p>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $totalLogs; ?></div>
                    <div class="stat-label">Total Entries</div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="filter-section <?php echo $hasFilters ? 'filter-active' : ''; ?>">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <label for="user_id">User</label>
                        <select id="user_id" name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($logUsers as $u): ?>
                                <option value="<?php echo (int) $u['emp_id']; ?>"
                                    <?php echo ($userId !== null && (int) $userId === (int) $u['emp_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($u['emp_name'] ?: $u['user_name']) . ' (' . $u['user_name'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <?php if ($hasFilters): ?>
                            <a href="admin-log-activity.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (empty($activityLogs)): ?>
                <div class="no-data">No log entries found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityLogs as $idx => $log): ?>
                            <tr>
                                <td><?php echo $offset + $idx + 1; ?></td>
                                <td><?php echo htmlspecialchars($log['emp_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($log['user_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($log['role'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($log['action'] === 'login'): ?>
                                        <span class="badge badge-login">Login</span>
                                    <?php else: ?>
                                        <span class="badge badge-logout">Logout</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDbDateTime($log['logged_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo buildQs(1); ?>">&laquo;</a>
                        <a href="<?php echo buildQs($page - 1); ?>">&lsaquo; Prev</a>
                    <?php else: ?>
                        <span class="disabled">&laquo;</span>
                        <span class="disabled">&lsaquo; Prev</span>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage   = min($totalPages, $page + 2);
                    if ($startPage > 1) echo '<span class="disabled">...</span>';
                    for ($p = $startPage; $p <= $endPage; $p++):
                    ?>
                        <?php if ($p === $page): ?>
                            <span class="active"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a href="<?php echo buildQs($p); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor;
                    if ($endPage < $totalPages) echo '<span class="disabled">...</span>';
                    ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo buildQs($page + 1); ?>">Next &rsaquo;</a>
                        <a href="<?php echo buildQs($totalPages); ?>">&raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Next &rsaquo;</span>
                        <span class="disabled">&raquo;</span>
                    <?php endif; ?>
                </div>
                <div class="page-info">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?> &middot; Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo $totalLogs; ?> entries
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        var isInternalNav = false;
        document.addEventListener('click', function(e) {
            var link = e.target.closest('a[href]');
            if (link) isInternalNav = true;
        }, true);
        document.addEventListener('submit', function() {
            isInternalNav = true;
        }, true);
        function sendLogout() {
            if (isInternalNav) return;
            var url = new URL('../api/admin-log-activity.php', window.location.href).href;
            var data = JSON.stringify({ action: 'logout' });
            navigator.sendBeacon(url, new Blob([data], { type: 'application/json' }));
        }
        window.addEventListener('pagehide', sendLogout);
        window.addEventListener('beforeunload', sendLogout);
    })();
    </script>
</body>
</html>
