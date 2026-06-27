<?php
// ============================================================
//  HamCAM Configuration
// ============================================================
//
//  This is the TEMPLATE that ships in the repo. It is safe to copy
//  straight to config.php and boot the app with it — every page will
//  just redirect you to the setup wizard (setup.php) until you finish
//  filling in real values there.
//
//      cp config.example.php config.php
//      cp go2rtc.example.yaml go2rtc.yaml
//      cp .env.example .env
//      docker compose up -d --build
//
//  Then visit the site — the wizard takes it from there. Don't edit
//  this file's __TOKEN__ placeholders by hand; setup.php does that
//  for you (and stores a bcrypt hash of your password, not the
//  plaintext).
// ============================================================

// Flipped to true by setup.php once the wizard has been completed.
define('HAMCAM_SETUP_COMPLETE', false);

// --- Authentication ---
define('HAMCAM_PASSWORD_HASH', '__HAMCAM_PASSWORD_HASH__');  // bcrypt, set via setup.php
define('SESSION_TIMEOUT', 3600);                              // seconds (1 hour)

// --- Camera Settings ---
// Your camera's local IP and RTSP credentials
define('CAMERA_IP',        '__CAMERA_IP__');   // <-- set via setup.php
define('CAMERA_RTSP_PORT', 554);               // placeholder default until setup.php runs
define('CAMERA_USER',      '__CAMERA_USER__');
define('CAMERA_PASS',      '__CAMERA_PASS__');

// RTSP stream paths (Tapo C210 stream names)
define('RTSP_STREAM_HD', '__RTSP_STREAM_HD__');  // 2K main stream
define('RTSP_STREAM_SD', '__RTSP_STREAM_SD__');  // Sub stream (lower res)

// Full RTSP URL (used by hls-proxy / ffmpeg if needed)
define('RTSP_URL_HD', sprintf(
    'rtsp://%s:%s@%s:%d/%s',
    CAMERA_USER, CAMERA_PASS, CAMERA_IP, CAMERA_RTSP_PORT, RTSP_STREAM_HD
));

// --- HLS Proxy (go2rtc) ---
// go2rtc runs as a sidecar container and provides real-time HLS.
// Stream name "cam" matches the streams key in go2rtc.yaml.
// Set to '' to fall back to the MJPEG snapshot mode.
define('HLS_URL', '__HLS_URL__');

// MJPEG / Snapshot fallback URL (Tapo C210 snapshot endpoint)
define('SNAPSHOT_URL', 'http://' . CAMERA_IP . '/snapshot/stream0');

// --- PTZ / ONVIF ---
define('ONVIF_HOST', CAMERA_IP);
define('ONVIF_PORT', 2020);   // placeholder default until setup.php runs

// --- Motion Detection ---
// These mirror the env vars the `motion` container reads from .env —
// setup.php keeps both in sync so the UI never lies about the schedule.
define('MOTION_ACTIVE_START', '__MOTION_ACTIVE_START__');
define('MOTION_ACTIVE_END',   '__MOTION_ACTIVE_END__');
define('MOTION_ALWAYS_ON',    false);   // placeholder default until setup.php runs
define('MOTION_THRESHOLD',    3000);    // placeholder default until setup.php runs
define('MOTION_COOLDOWN',     30);      // placeholder default until setup.php runs

// --- Site ---
define('SITE_TITLE',    '__SITE_TITLE__');
define('SITE_SUBTITLE', '__SITE_SUBTITLE__');
