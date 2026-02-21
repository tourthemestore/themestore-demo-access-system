<?php
/**
 * Admin Dashboard - ThemeStore Demo Access System
 * Main admin page with navigation to all admin sections.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$uName = htmlspecialchars($loggedInUser['emp_name'] ?? $loggedInUser['username'] ?? '', ENT_QUOTES, 'UTF-8');
$uRole = !empty($loggedInUser['is_admin']) ? 'Admin' : 'Sales';
$isAdmin = !empty($loggedInUser['is_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | ThemeStore</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            width: 100%;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 28px 30px;
            border-radius: 12px 12px 0 0;
        }
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 24px; font-weight: 700; }
        .header p { opacity: 0.9; font-size: 14px; margin-top: 6px; }
        .user-info { font-size: 14px; text-align: right; }
        .user-info a { color: #fff; text-decoration: underline; }
        .cards {
            background: #fff;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .card {
            display: block;
            padding: 24px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            text-decoration: none;
            color: #333;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 14px;
        }
        .card-icon-leads { background: #e7f3ff; color: #667eea; }
        .card-icon-log { background: #fef3e2; color: #e67e22; }
        .card h3 {
            font-size: 17px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .card p {
            font-size: 13px;
            color: #777;
            line-height: 1.5;
        }
        .card-arrow {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #ccc;
            transition: color 0.2s;
        }
        .card:hover .card-arrow { color: #667eea; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-row">
                <div>
                    <h1>Dashboard</h1>
                    <p>ThemeStore Demo Access System</p>
                </div>
                <div class="user-info">
                    <strong><?php echo $uName; ?></strong><br>
                    <?php echo $uRole; ?> &nbsp;|&nbsp;
                    <a href="?logout=1">Logout</a>
                </div>
            </div>
        </div>
        <div class="cards">
            <a href="admin-leads.php" class="card">
                <div class="card-icon card-icon-leads">&#128203;</div>
                <h3>Leads Management</h3>
                <p>View and manage all leads, demo links, video activity and verification status.</p>
                <span class="card-arrow">&rarr;</span>
            </a>
            <?php if ($isAdmin): ?>
            <a href="admin-log-activity.php" class="card">
                <div class="card-icon card-icon-log">&#128221;</div>
                <h3>User Activity Log</h3>
                <p>Track user login/logout times. See who accessed the system and when.</p>
                <span class="card-arrow">&rarr;</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        var isInternalNav = false;
        document.addEventListener('click', function(e) {
            if (e.target.closest('a[href]')) isInternalNav = true;
        }, true);
        function sendLogout() {
            if (isInternalNav) return;
            var url = new URL('../api/admin-log-activity.php', window.location.href).href;
            navigator.sendBeacon(url, new Blob([JSON.stringify({ action: 'logout' })], { type: 'application/json' }));
        }
        window.addEventListener('pagehide', sendLogout);
        window.addEventListener('beforeunload', sendLogout);
    })();
    </script>
</body>
</html>
