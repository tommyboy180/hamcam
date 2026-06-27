<?php
/**
 * cam_api.php
 * Handles camera feature toggles (night vision, motion detect, etc.)
 * and status checks via the Tapo local API.
 *
 * Tapo C210 supports a local JSON API on port 80/443.
 * Some features also map to ONVIF imaging settings.
 */
require_once 'config.php';
require_once 'setup_guard.php';
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['hamcam_auth'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Forbidden']);
    exit;
}

// --- Tapo Local API helper ----------------------------------------------------
// The C210 uses a simple HTTP POST to /` with JSON.
// Authentication: Basic auth with camera credentials.
function tapo_request(array $payload): array {
    $url = 'http://' . CAMERA_IP . '/';
    $json = json_encode($payload);
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        =>
                "Content-Type: application/json\r\n" .
                "Authorization: Basic " . base64_encode(CAMERA_USER . ':' . CAMERA_PASS) . "\r\n",
            'content'       => $json,
            'timeout'       => 4,
            'ignore_errors' => true,
        ]
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return ['error_code' => -1];
    return json_decode($resp, true) ?? ['error_code' => -1];
}

// --- Simple ping check --------------------------------------------------------
function camera_online(): bool {
    $sock = @fsockopen(CAMERA_IP, 80, $e, $em, 2);
    if ($sock) { fclose($sock); return true; }
    return false;
}

// --- Dispatch -----------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'status') {
        echo json_encode([
            'ok'     => true,
            'online' => camera_online(),
            'ip'     => CAMERA_IP,
        ]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Unknown GET action']);
    exit;
}

// POST – feature toggle
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$feature = $body['feature'] ?? '';
$value   = $body['value']   ?? false;

$result = ['ok' => true, 'feature' => $feature, 'value' => $value];

// Map feature names to Tapo API parameters
// Note: Exact method names depend on firmware. These match common C210 firmware.
switch ($feature) {

    case 'night_vision':
        $mode = $value ? 'on' : 'off';
        tapo_request(['method' => 'setNightVisionModeConfig',
                      'params' => ['night_vision_mode' => $mode]]);
        break;

    case 'motion_detect':
        tapo_request(['method' => 'setDetectionConfig',
                      'params' => ['motion_det' => ['enabled' => $value]]]);
        break;

    case 'flip_h':
        tapo_request(['method' => 'setImageFlipConfig',
                      'params' => ['flip_type' => $value ? 'CENTER' : 'OFF']]);
        break;

    case 'flip_v':
        // Tapo typically combines flip_h + flip_v in one call; here simplified
        tapo_request(['method' => 'setImageFlipConfig',
                      'params' => ['flip_type' => $value ? 'CENTER' : 'OFF']]);
        break;

    case 'audio':
        tapo_request(['method' => 'setAudioConfig',
                      'params' => ['microphone_mute' => !$value]]);
        break;

    case 'siren':
        // Trigger alarm/siren
        tapo_request(['method' => 'setAlarmConfig',
                      'params' => ['enabled' => true, 'alarm_type' => 0, 'duration' => 5]]);
        break;

    default:
        $result = ['ok' => false, 'error' => "Unknown feature: $feature"];
}

echo json_encode($result);