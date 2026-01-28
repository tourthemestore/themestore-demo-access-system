<?php
/**
 * Video Stream Controller - ThemeStore Demo Access System
 * PHP 8 - No Framework
 * 
 * Securely streams MP4 video with token validation
 * Supports HTTP Range requests for video seeking
 */

// Load required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/token-validator.php';

/**
 * Get video file system path from URL or use direct path
 * Converts URL to file system path for cPanel
 */
function getVideoFilePath(): string|false
{
    $videoPath = DEMO_VIDEO_PATH;
    
    // If it's a URL, convert to file system path
    if (strpos($videoPath, 'http://') === 0 || strpos($videoPath, 'https://') === 0) {
        // Extract path from URL
        $parsedUrl = parse_url($videoPath);
        $urlPath = $parsedUrl['path'] ?? '';
        
        // For cPanel: convert /demo_video/file.mp4 to /home/username/public_html/demo_video/file.mp4
        // Or if outside public_html: /home/username/demo_video/file.mp4
        // Try common locations
        $possiblePaths = [
            $_SERVER['DOCUMENT_ROOT'] . $urlPath, // Inside public_html
            dirname($_SERVER['DOCUMENT_ROOT']) . $urlPath, // Outside public_html, same level
            str_replace('/public_html', '', $_SERVER['DOCUMENT_ROOT']) . $urlPath, // Parent of public_html
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // If URL path conversion fails, try direct file system path
        // Update this with your actual file system path
        $directPath = 'https://www.tourthemestore.com/demo_video/Themestore-Video-Voice+Recording.mp4';
        if (file_exists($directPath)) {
            return $directPath;
        }
        
        return false;
    }
    
    // Already a file system path
    if (file_exists($videoPath)) {
        return $videoPath;
    }
    
    return false;
}

/**
 * Parse HTTP Range header
 */
function parseRangeHeader(string $rangeHeader, int $fileSize): array|false
{
    if (empty($rangeHeader)) {
        return false;
    }
    
    // Range header format: bytes=start-end
    if (preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
        $start = (int) $matches[1];
        $end = !empty($matches[2]) ? (int) $matches[2] : $fileSize - 1;
        
        // Validate range
        if ($start < 0 || $start >= $fileSize) {
            return false;
        }
        
        if ($end >= $fileSize) {
            $end = $fileSize - 1;
        }
        
        if ($start > $end) {
            return false;
        }
        
        return ['start' => $start, 'end' => $end];
    }
    
    return false;
}

/**
 * Stream video file
 */
function streamVideo(string $filePath, array|false $range = false): void
{
    $fileSize = filesize($filePath);
    $fileHandle = fopen($filePath, 'rb');
    
    if (!$fileHandle) {
        http_response_code(500);
        die('Error opening video file');
    }
    
    // Set basic headers
    header('Content-Type: video/mp4');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Handle Range request
    if ($range !== false) {
        $start = $range['start'];
        $end = $range['end'];
        $length = $end - $start + 1;
        
        // Set partial content headers
        http_response_code(206); // Partial Content
        header('Content-Length: ' . $length);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        
        // Seek to start position
        fseek($fileHandle, $start);
        
        // Stream in chunks
        $remaining = $length;
        $chunkSize = 8192; // 8KB chunks
        
        while ($remaining > 0 && !feof($fileHandle)) {
            $readSize = min($chunkSize, $remaining);
            $chunk = fread($fileHandle, $readSize);
            echo $chunk;
            flush();
            $remaining -= $readSize;
        }
    } else {
        // Stream entire file
        $chunkSize = 8192; // 8KB chunks
        
        while (!feof($fileHandle)) {
            $chunk = fread($fileHandle, $chunkSize);
            echo $chunk;
            flush();
        }
    }
    
    // Close file handle
    fclose($fileHandle);
}

/**
 * Update demo link access tracking
 */
function updateDemoLinkAccess(int $demoLinkId): void
{
    try {
        $pdo = getDbConnection();
        
        // Increment views_count and update accessed_at
        $stmt = $pdo->prepare("
            UPDATE demo_links
            SET views_count = views_count + 1,
                accessed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$demoLinkId]);
        
        // Check if max views reached and update status
        $checkStmt = $pdo->prepare("
            SELECT views_count, max_views
            FROM demo_links
            WHERE id = ?
        ");
        $checkStmt->execute([$demoLinkId]);
        $link = $checkStmt->fetch();
        
        if ($link && $link['views_count'] >= $link['max_views']) {
            $updateStmt = $pdo->prepare("
                UPDATE demo_links
                SET status = 'used', updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$demoLinkId]);
        }
    } catch (PDOException $e) {
        error_log("Database error in updateDemoLinkAccess: " . $e->getMessage());
        // Don't fail the stream if tracking fails
    }
}

/**
 * Main execution
 */
try {
    // Get token from GET parameter
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(403);
        die('Access denied. Token required.');
    }
    
    // Validate token
    $demoLink = validateDemoToken($token);
    
    if ($demoLink === false) {
        http_response_code(403);
        die('Access denied. Invalid or expired token.');
    }
    
    // Get video file path
    $videoFilePath = getVideoFilePath();
    
    if ($videoFilePath === false || !file_exists($videoFilePath)) {
        error_log("Video file not found: " . DEMO_VIDEO_PATH);
        http_response_code(404);
        die('Video file not found.');
    }
    
    // Check if file is readable
    if (!is_readable($videoFilePath)) {
        error_log("Video file not readable: " . $videoFilePath);
        http_response_code(500);
        die('Error accessing video file.');
    }
    
    // Parse Range header if present
    $rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';
    $range = false;
    
    if (!empty($rangeHeader)) {
        $fileSize = filesize($videoFilePath);
        $range = parseRangeHeader($rangeHeader, $fileSize);
    }
    
    // Update access tracking (track before streaming to ensure it's counted)
    updateDemoLinkAccess($demoLink['id']);
    
    // Stream the video
    streamVideo($videoFilePath, $range);
    
    exit;
    
} catch (Exception $e) {
    error_log("Error in stream.php: " . $e->getMessage());
    http_response_code(500);
    die('An error occurred while streaming the video.');
}

