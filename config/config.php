<?php
/**
 * Configuration File - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Centralized database connectivity and configuration
 */

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Application timezone (set to India Standard Time)
// This affects all PHP date()/time() calls
date_default_timezone_set('Asia/Kolkata');

/**
 * Format a database datetime string for display in app timezone (IST).
 * Since MySQL session time_zone is set to '+05:30' in getDbConnection(),
 * MySQL converts TIMESTAMP values from UTC to IST when SELECTing.
 * So the datetime string we receive is already in IST, not UTC.
 * We just need to format it directly in IST timezone.
 *
 * @param string|null $datetimeStr Value from DB (e.g. created_at, expires_at) - already in IST
 * @param string $format PHP date format (default: d-m-Y h:i A)
 * @return string Formatted date or empty string
 */
function formatDbDateTime(?string $datetimeStr, string $format = 'd-m-Y h:i A'): string
{
    if ($datetimeStr === null || $datetimeStr === '') {
        return '';
    }
    try {
        // MySQL returns datetime strings in IST (because session time_zone = '+05:30')
        // So we parse it as IST directly, no conversion needed
        $istTz = new DateTimeZone('Asia/Kolkata');
        $dt = new DateTime($datetimeStr, $istTz);
        return $dt->format($format);
    } catch (Exception $e) {
        return '';
    }
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'tourthemestore_support');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Email configuration (update with your SMTP settings)
define('SMTP_HOST', 'mail.tourthemestore.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'info@tourthemestore.com');
define('SMTP_PASS', ']KRxvNgqYNyxp.^!');
define('SMTP_FROM_EMAIL', 'info@tourthemestore.com');
define('SMTP_FROM_NAME', 'ThemeStore Demo Access');

// Video configuration - Vimeo
define('VIMEO_VIDEO_ID', '1158330074');
define('VIMEO_EMBED_URL', 'https://player.vimeo.com/video/' . VIMEO_VIDEO_ID);
// Vimeo video password (required if video privacy is set to "Password")
// Set this to match the password configured in your Vimeo video settings
define('VIMEO_VIDEO_PASSWORD', 'info!@#1234'); // Add your Vimeo video password here

// Admin notification email: receives alerts when a lead abandons the demo video (closes without completing)
define('ADMIN_NOTIFICATION_EMAIL', 'info@tourthemestore.com'); // Change to your team email

/**
 * Get database connection (singleton pattern)
 * 
 * @return PDO Database connection instance
 * @throws PDOException If connection fails
 */
function getDbConnection(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Ensure MySQL uses the same timezone as PHP (IST)
        // So TIMESTAMP columns like created_at / expires_at are stored in local time
        $pdo->exec("SET time_zone = '+05:30'");
    }
    
    return $pdo;
}

