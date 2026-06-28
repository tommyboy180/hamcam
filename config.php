<?php
// ============================================================
//  HamCAM Configuration
// ============================================================
//
//  This file ships with example values so the app boots cleanly
//  out of the box. Don't edit it by hand — open the site in a
//  browser and use the setup wizard (it redirects you there
//  automatically until HAMCAM_SETUP_COMPLETE is true below).
//  The wizard rewrites this whole file when you save.
// ============================================================

// Flipped to true by the setup wizard once you've completed it.
define('HAMCAM_SETUP_COMPLETE', false);

// --- Authentication ---
define('HAMCAM_PASSWORD_HASH', '');             // bcrypt, set via the wizard
define('SESSION_TIMEOUT', 3600);                 // seconds (1 hour)

// --- Camera Settings ---
// Your camera's local IP and RTSP credentials
define('CAMERA_IP',        '192.168.1.100');   // <-- set via the wizard
define('CAMERA_RTSP_PORT', 554);
define('CAMERA_USER',      'admin');
define('CAMERA_PASS',      'changeme');

// RTSP stream paths (Tapo C210 stream names)
define('RTSP_STREAM_HD', 'stream1');   // 2K main stream
define('RTSP_STREAM_SD', 'stream2');   // Sub stream (lower res)

// Full RTSP URL (used by hls-proxy / ffmpeg if needed)
define('RTSP_URL_HD', sprintf(
    'rtsp://%s:%s@%s:%d/%s',
    rawurlencode(CAMERA_USER), rawurlencode(CAMERA_PASS), CAMERA_IP, CAMERA_RTSP_PORT, RTSP_STREAM_HD
));

// --- HLS Proxy (go2rtc) ---
// go2rtc runs as a sidecar container and provides real-time HLS.
// Stream name "cam" matches the streams key in go2rtc.yaml.
// Set to '' to fall back to the MJPEG snapshot mode.
define('HLS_URL', 'http://192.168.1.1:1984/cam/index.m3u8');

// MJPEG / Snapshot fallback URL (Tapo C210 snapshot endpoint)
define('SNAPSHOT_URL', 'http://' . CAMERA_IP . '/snapshot/stream0');

// --- PTZ / ONVIF ---
define('ONVIF_HOST', CAMERA_IP);
define('ONVIF_PORT', 2020);

// --- Motion Detection ---
// These mirror the env vars the `motion` container reads from .env —
// the wizard keeps both in sync so the UI never lies about the schedule.
define('MOTION_ACTIVE_START', '20:00');
define('MOTION_ACTIVE_END',   '07:00');
define('MOTION_ALWAYS_ON',    false);   // set true to log 24/7
define('MOTION_THRESHOLD',    3000);    // pixel change score to trigger (lower = more sensitive)
define('MOTION_COOLDOWN',     30);      // seconds between events (prevents spam)

// --- Site ---
define('SITE_TITLE',    'HamCAM');
define('SITE_SUBTITLE', 'Live Hamster Observatory');
