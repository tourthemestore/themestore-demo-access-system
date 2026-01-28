<?php
/**
 * PHPMailer Installation Script
 * Downloads and installs PHPMailer manually
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Install PHPMailer</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;max-width:800px;margin:0 auto;}";
echo ".success{color:green;font-weight:bold;padding:10px;background:#d4edda;border:1px solid #c3e6cb;border-radius:5px;margin:10px 0;}";
echo ".error{color:red;font-weight:bold;padding:10px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:5px;margin:10px 0;}";
echo ".info{color:blue;padding:10px;background:#d1ecf1;border:1px solid #bee5eb;border-radius:5px;margin:10px 0;}</style></head><body>";
echo "<h2>PHPMailer Installation</h2>";

$vendorDir = __DIR__ . '/vendor';
$phpmailerDir = $vendorDir . '/phpmailer/phpmailer';

// Check if already installed
if (file_exists($vendorDir . '/autoload.php') && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<div class='success'>✓ PHPMailer is already installed!</div>";
    echo "<p><a href='../test-email-send.php'>Test Email Configuration</a></p>";
    echo "</body></html>";
    exit;
}

// Check if vendor directory exists
if (!is_dir($vendorDir)) {
    if (!mkdir($vendorDir, 0755, true)) {
        echo "<div class='error'>✗ Failed to create vendor directory. Please create it manually with write permissions.</div>";
        echo "</body></html>";
        exit;
    }
}

echo "<div class='info'>";
echo "<h3>Manual Installation Instructions:</h3>";
echo "<p><strong>Option 1: Using Composer (Recommended)</strong></p>";
echo "<ol>";
echo "<li>Install Composer: <a href='https://getcomposer.org/download/' target='_blank'>Download Composer</a></li>";
echo "<li>Open terminal in this directory</li>";
echo "<li>Run: <code>composer require phpmailer/phpmailer</code></li>";
echo "</ol>";

echo "<p><strong>Option 2: Manual Download</strong></p>";
echo "<ol>";
echo "<li>Download PHPMailer: <a href='https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip' target='_blank'>Download ZIP</a></li>";
echo "<li>Extract the ZIP file</li>";
echo "<li>Copy the 'src' folder to: <code>vendor/phpmailer/phpmailer/</code></li>";
echo "<li>Create autoload.php in vendor folder (see below)</li>";
echo "</ol>";

echo "<p><strong>Option 3: Quick Download Script</strong></p>";
echo "<p>Click the button below to automatically download and install PHPMailer:</p>";

if (isset($_GET['download']) && $_GET['download'] === '1') {
    echo "<div class='info'>Downloading PHPMailer...</div>";
    
    $zipUrl = 'https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip';
    $zipFile = __DIR__ . '/phpmailer-master.zip';
    
    // Download the ZIP file
    $zipContent = @file_get_contents($zipUrl);
    if ($zipContent === false) {
        echo "<div class='error'>✗ Failed to download PHPMailer. Please download manually.</div>";
    } else {
        file_put_contents($zipFile, $zipContent);
        
        // Extract ZIP
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo(__DIR__);
            $zip->close();
            unlink($zipFile);
            
            // Move files to vendor directory
            $extractedDir = __DIR__ . '/PHPMailer-master';
            if (is_dir($extractedDir)) {
                if (!is_dir($phpmailerDir)) {
                    mkdir($phpmailerDir, 0755, true);
                }
                
                // Copy src directory
                if (is_dir($extractedDir . '/src')) {
                    copyDirectory($extractedDir . '/src', $phpmailerDir);
                }
                
                // Clean up
                deleteDirectory($extractedDir);
                
                // Create autoload.php
                createAutoload($vendorDir);
                
                echo "<div class='success'>✓ PHPMailer installed successfully!</div>";
                echo "<p><a href='../test-email-send.php'>Test Email Configuration</a></p>";
            } else {
                echo "<div class='error'>✗ Extraction failed. Please install manually.</div>";
            }
        } else {
            echo "<div class='error'>✗ Failed to extract ZIP file. Please install manually.</div>";
        }
    }
} else {
    echo "<p><a href='?download=1' style='display:inline-block;padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:5px;'>Download & Install PHPMailer</a></p>";
}

echo "</div>";

function copyDirectory($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            if (is_dir($src . '/' . $file)) {
                copyDirectory($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

function createAutoload($vendorDir) {
    $autoloadContent = '<?php
// PHPMailer Autoloader
spl_autoload_register(function ($class) {
    $prefix = \'PHPMailer\\\\PHPMailer\\\\\';
    $base_dir = __DIR__ . \'/phpmailer/phpmailer/\';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace(\'\\\\\', \'/\', $relative_class) . \'.php\';
    
    if (file_exists($file)) {
        require $file;
    }
});
';
    file_put_contents($vendorDir . '/autoload.php', $autoloadContent);
}

echo "<hr>";
echo "<p><a href='../public/demo-flow.php'>Back to Demo Flow</a> | <a href='../test-email-send.php'>Test Email</a></p>";
echo "</body></html>";

