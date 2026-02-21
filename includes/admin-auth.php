<?php
/**
 * Admin Auth - ThemeStore Demo Access System
 * Login via roles table (user_name + password).
 * Admin (emp_id = 0) sees all leads.
 * Sales (role_id = 6) sees only their assigned leads.
 * Logs login/logout to demo_leads_user_log table.
 */

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionKey = 'admin_user';

/**
 * Insert a row into demo_leads_user_log
 */
function logUserActivity(array $user, string $action): void
{
    try {
        $pdo = getDbConnection();
        $nowIST = date('Y-m-d H:i:s'); // PHP timezone is Asia/Kolkata
        $stmt = $pdo->prepare("
            INSERT INTO demo_leads_user_log (emp_id, user_name, emp_name, role, action, logged_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int) ($user['emp_id'] ?? 0),
            $user['username'] ?? '',
            $user['emp_name'] ?? '',
            !empty($user['is_admin']) ? 'Admin' : 'Sales',
            $action,
            $nowIST
        ]);
    } catch (Throwable $e) {
        error_log("logUserActivity error: " . $e->getMessage());
    }
}

// Handle logout first (before session check)
if (isset($_GET['logout'])) {
    if (!empty($_SESSION[$sessionKey])) {
        logUserActivity($_SESSION[$sessionKey], 'logout');
    }
    unset($_SESSION[$sessionKey]);
    session_destroy();
    $adminDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    header('Location: ' . $adminDir . '/index.php');
    exit;
}

// Already logged in — verify user is still active in DB before allowing access
if (!empty($_SESSION[$sessionKey])) {
    try {
        $pdo = getDbConnection();
        $checkStmt = $pdo->prepare("SELECT active_flag FROM roles WHERE user_name = ? LIMIT 1");
        $checkStmt->execute([$_SESSION[$sessionKey]['username'] ?? '']);
        $checkRow = $checkStmt->fetch();

        if (!$checkRow || strtolower(trim($checkRow['active_flag'] ?? '')) !== 'active') {
            logUserActivity($_SESSION[$sessionKey], 'logout');
            unset($_SESSION[$sessionKey]);
            session_destroy();
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $error = 'Your account has been deactivated. Please contact admin.';
        } else {
            $loggedInUser = $_SESSION[$sessionKey];
            return;
        }
    } catch (Throwable $e) {
        $loggedInUser = $_SESSION[$sessionKey];
        return;
    }
}

$error = null;

// POST: check credentials
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                SELECT emp_id, role_id, user_name, password, active_flag
                FROM roles
                WHERE user_name = ?
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Invalid username or password.';
            } elseif (strtolower(trim($user['active_flag'] ?? '')) !== 'active') {
                $error = 'This account is inactive. Please contact admin.';
            } else {
                // Check password — try direct match first, then password_verify
                $passwordMatch = false;
                if ($password === $user['password']) {
                    $passwordMatch = true;
                } elseif (password_verify($password, $user['password'])) {
                    $passwordMatch = true;
                }

                if (!$passwordMatch) {
                    $error = 'Invalid username or password.';
                } else {
                    // Only admin (emp_id=0) or sales (role_id=6) allowed
                    $empId = (int) $user['emp_id'];
                    $roleId = (int) $user['role_id'];
                    $isAdmin = ($empId === 0);
                    $isSales = ($roleId === 6);

                    if (!$isAdmin && !$isSales) {
                        $error = 'You do not have permission to access this area.';
                    } else {
                        // Get employee name from emp_master if available
                        $empName = $username;
                        try {
                            if ($empId > 0) {
                                $empStmt = $pdo->prepare("SELECT emp_name FROM emp_master WHERE emp_id = ? LIMIT 1");
                                $empStmt->execute([$empId]);
                                $empRow = $empStmt->fetch();
                                if ($empRow && !empty($empRow['emp_name'])) {
                                    $empName = $empRow['emp_name'];
                                }
                            } else {
                                $empName = 'Admin';
                            }
                        } catch (Throwable $e) {
                            // ignore, use username
                        }

                        $userData = [
                            'emp_id'   => $empId,
                            'role_id'  => $roleId,
                            'username' => $user['user_name'],
                            'emp_name' => $empName,
                            'is_admin' => $isAdmin,
                        ];

                        $_SESSION[$sessionKey] = $userData;

                        // Log login
                        logUserActivity($userData, 'login');

                        // Always redirect to dashboard after login
                        $adminDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                        header('Location: ' . $adminDir . '/index.php', true, 302);
                        exit;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("admin-auth login error: " . $e->getMessage());
            $error = 'Unable to authenticate. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .box { background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); padding: 32px; max-width: 380px; width: 100%; }
        h1 { font-size: 20px; color: #333; margin-bottom: 20px; text-align: center; }
        label { display: block; font-weight: 600; color: #444; margin-bottom: 6px; font-size: 14px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; margin-bottom: 16px; }
        input:focus { outline: none; border-color: #667eea; }
        button { width: 100%; padding: 12px; background: #667eea; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        button:hover { background: #5568d3; }
        .error { background: #fee; color: #c33; padding: 10px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="box">
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <form method="post" action="">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Enter username" autofocus required value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
<?php
exit;
