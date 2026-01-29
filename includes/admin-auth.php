<?php
/**
 * Admin Leads Auth - ThemeStore Demo Access System
 * Protects admin-leads.php and admin-lead-detail.php with password from config.
 * Password-only; no username.
 */

if (!defined('ADMIN_LEADS_PASSWORD')) {
    http_response_code(500);
    exit('ADMIN_LEADS_PASSWORD not configured.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionKey = 'admin_leads_authenticated';

// Already logged in
if (!empty($_SESSION[$sessionKey])) {
    return;
}

// POST: check password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = trim($_POST['admin_password'] ?? '');
    if ($submitted === ADMIN_LEADS_PASSWORD) {
        $_SESSION[$sessionKey] = true;
        $redirect = $_SERVER['REQUEST_URI'] ?? '/admin/admin-leads.php';
        header('Location: ' . $redirect, true, 302);
        exit;
    }
    $error = 'Incorrect password.';
}

$error = $error ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .box { background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); padding: 32px; max-width: 360px; width: 100%; }
        h1 { font-size: 20px; color: #333; margin-bottom: 20px; text-align: center; }
        label { display: block; font-weight: 600; color: #444; margin-bottom: 6px; font-size: 14px; }
        input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; margin-bottom: 16px; }
        input[type="password"]:focus { outline: none; border-color: #667eea; }
        button { width: 100%; padding: 12px; background: #667eea; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        button:hover { background: #5568d3; }
        .error { background: #fee; color: #c33; padding: 10px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Admin Login</h1>
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <form method="post" action="">
            <label for="admin_password">Password</label>
            <input type="password" id="admin_password" name="admin_password" placeholder="Enter password" autofocus required>
            <button type="submit">Continue</button>
        </form>
    </div>
</body>
</html>
<?php
exit;
