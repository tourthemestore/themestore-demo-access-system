<?php
/**
 * PHPMailer Autoloader
 * Loads PHPMailer classes automatically
 */

spl_autoload_register(function ($class) {
    // PHPMailer namespace
    $prefix = 'PHPMailer\\PHPMailer\\';
    
    // Base directory for PHPMailer
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';
    
    // Check if the class uses the PHPMailer prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Not a PHPMailer class, skip
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

