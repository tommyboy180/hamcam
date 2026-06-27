<?php
/**
 * snapshot.php
 * Proxies the MJPEG snapshot from the Tapo C210 so the browser
 * doesn't need direct camera access (keeps camera on LAN only).
 */
require_once 'config.php';
require_once 'setup_guard.php';
session_start();

if (empty($_SESSION['hamcam_auth'])) {
    http_response_code(403);
    exit('Forbidden');
}

$download = !empty($_GET['download']);

// Build authenticated snapshot URL
$url = sprintf(
    'http://%s:%s@%s/snapshot/stream0',
    urlencode(CAMERA_USER),
    urlencode(CAMERA_PASS),
    CAMERA_IP
);

$ctx = stream_context_create([
    'http' => [
        'timeout'       => 5,
        'ignore_errors' => true,
    ]
]);

$data = @file_get_contents($url, false, $ctx);

if ($data === false || strlen($data) < 100) {
    // Return a 1x1 placeholder on failure
    http_response_code(503);
    header('Content-Type: image/jpeg');
    // tiny black 1x1 JPEG
    echo base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAAUCAABAAEEAiIA/8QAFAABAAAAAAAAAAAAAAAAAAAACP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AKwAB/9k=');
    exit;
}

if ($download) {
    header('Content-Disposition: attachment; filename="hamcam_' . time() . '.jpg"');
}

header('Content-Type: image/jpeg');
header('Cache-Control: no-store, no-cache');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($data));
echo $data;