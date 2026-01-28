<?php
/**
 * Lead Form - ThemeStore Demo Access System
 * PHP 8 - No Framework
 */

session_start();

// Load configuration
require_once __DIR__ . '/../config/config.php';

// CSRF Token generation and validation
function generateCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Validation functions
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateMobile(string $mobile): bool
{
    // Remove spaces, dashes, and parentheses
    $cleaned = preg_replace('/[\s\-\(\)]/', '', $mobile);
    // Check if it contains only digits and optional + at start
    return preg_match('/^\+?\d{10,15}$/', $cleaned) === 1;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Initialize variables
$errors = [];
$success = false;
$formData = [
    'company_name' => '',
    'location' => '',
    'email' => '',
    'mobile' => '',
    'campaign_source' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {
        // Get and sanitize form data
        $formData['company_name'] = sanitizeInput($_POST['company_name'] ?? '');
        $formData['location'] = sanitizeInput($_POST['location'] ?? '');
        $formData['email'] = sanitizeInput($_POST['email'] ?? '');
        $formData['mobile'] = sanitizeInput($_POST['mobile'] ?? '');
        $formData['campaign_source'] = sanitizeInput($_POST['campaign_source'] ?? '');

        // Server-side validation
        if (empty($formData['company_name'])) {
            $errors[] = 'Company Name is required.';
        } elseif (strlen($formData['company_name']) > 255) {
            $errors[] = 'Company Name must not exceed 255 characters.';
        }

        if (empty($formData['location'])) {
            $errors[] = 'Location is required.';
        } elseif (strlen($formData['location']) > 255) {
            $errors[] = 'Location must not exceed 255 characters.';
        }

        if (empty($formData['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!validateEmail($formData['email'])) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($formData['email']) > 255) {
            $errors[] = 'Email must not exceed 255 characters.';
        }

        if (empty($formData['mobile'])) {
            $errors[] = 'Mobile number is required.';
        } elseif (!validateMobile($formData['mobile'])) {
            $errors[] = 'Please enter a valid mobile number (10-15 digits).';
        } elseif (strlen($formData['mobile']) > 20) {
            $errors[] = 'Mobile number must not exceed 20 characters.';
        }

        if (strlen($formData['campaign_source']) > 255) {
            $errors[] = 'Campaign source must not exceed 255 characters.';
        }

        // If no validation errors, save to database
        if (empty($errors)) {
            try {
                // Get database connection
                $pdo = getDbConnection();

                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM leads_for_demo WHERE email = ?");
                $stmt->execute([$formData['email']]);
                if ($stmt->fetch()) {
                    $errors[] = 'This email address is already registered.';
                } else {
                    // Insert lead with prepared statement
                    $stmt = $pdo->prepare("
                        INSERT INTO leads_for_demo (company_name, location, email, mobile, campaign_source, status)
                        VALUES (?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $formData['company_name'],
                        $formData['location'],
                        $formData['email'],
                        $formData['mobile'],
                        $formData['campaign_source'] ?: null
                    ]);

                    $success = true;
                    // Store email in session for demo flow
                    $_SESSION['lead_email'] = $formData['email'];
                    // Regenerate CSRF token after successful submission
                    unset($_SESSION['csrf_token']);
                    
                    // Redirect to demo flow after 2 seconds
                    header("Refresh: 2; url=demo-flow.php?email=" . urlencode($formData['email']));
                }
            } catch (PDOException $e) {
                // Log error in production (don't expose database details)
                error_log("Database error in lead-form.php: " . $e->getMessage());
                error_log("Error code: " . $e->getCode());
                error_log("SQL State: " . $e->errorInfo[0] ?? 'N/A');
                
                // Show more detailed error for debugging (remove in production)
                $errorMessage = 'An error occurred while processing your request. Please try again later.';
                if (ini_get('display_errors')) {
                    $errorMessage .= ' Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                }
                $errors[] = $errorMessage;
            } catch (Exception $e) {
                error_log("General error in lead-form.php: " . $e->getMessage());
                $errors[] = 'An error occurred while processing your request. Please try again later.';
            }
        }
    }
}

// Generate CSRF token for form
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Form - ThemeStore Demo Access</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        .required {
            color: #e74c3c;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .error-message ul {
            margin-left: 20px;
        }
        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        button[type="submit"] {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        button[type="submit"]:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Request Demo Access</h1>

        <?php if ($success): ?>
            <div class="success-message">
                Thank you! Your information has been submitted successfully.<br>
                Redirecting to verification...
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-group">
                <label for="company_name">
                    Company Name <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="company_name" 
                    name="company_name" 
                    value="<?php echo htmlspecialchars($formData['company_name'], ENT_QUOTES, 'UTF-8'); ?>"
                    required
                    maxlength="255"
                >
            </div>

            <div class="form-group">
                <label for="location">
                    Location <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="location" 
                    name="location" 
                    value="<?php echo htmlspecialchars($formData['location'], ENT_QUOTES, 'UTF-8'); ?>"
                    required
                    maxlength="255"
                >
            </div>

            <div class="form-group">
                <label for="email">
                    Email <span class="required">*</span>
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>"
                    required
                    maxlength="255"
                >
            </div>

            <div class="form-group">
                <label for="mobile">
                    Mobile <span class="required">*</span>
                </label>
                <input 
                    type="tel" 
                    id="mobile" 
                    name="mobile" 
                    value="<?php echo htmlspecialchars($formData['mobile'], ENT_QUOTES, 'UTF-8'); ?>"
                    required
                    maxlength="20"
                    placeholder="e.g., +1234567890"
                >
            </div>

            <input 
                type="hidden" 
                name="campaign_source" 
                value="<?php echo htmlspecialchars($formData['campaign_source'], ENT_QUOTES, 'UTF-8'); ?>"
            >

            <button type="submit">Submit</button>
        </form>
    </div>
</body>
</html>

