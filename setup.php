<?php
/**
 * setup.php — HamCAM setup wizard
 *
 * First run (HAMCAM_SETUP_COMPLETE === false):
 *   Open to anyone on the LAN. Fill in the form, submit, and HamCAM
 *   is fully configured and ready to use. Don't leave this sitting
 *   open on an untrusted network for longer than it takes to fill in.
 *
 * After setup (HAMCAM_SETUP_COMPLETE === true):
 *   Acts as a protected "Settings" page — you must already be logged
 *   in (same session as camera.php) to view or change anything here.
 *
 * Writes three files: config.php, go2rtc.yaml, .env
 * (.env is read by docker-compose.yml on the host)
 */

require_once 'config.php';
session_start();

$IS_FIRST_RUN = !defined('HAMCAM_SETUP_COMPLETE') || HAMCAM_SETUP_COMPLETE !== true;

if (!$IS_FIRST_RUN && empty($_SESSION['hamcam_auth'])) {
    header('Location: index.php');
    exit;
}

define('TEMPLATE_CONFIG', __DIR__ . '/config.example.php');
define('TEMPLATE_GO2RTC', __DIR__ . '/go2rtc.example.yaml');
define('OUT_CONFIG', __DIR__ . '/config.php');
define('OUT_GO2RTC', __DIR__ . '/go2rtc.yaml');
define('OUT_ENV', __DIR__ . '/.env');

// ---------------------------------------------------------------- helpers --

// Escape a value for safe placement inside a single-quoted PHP string literal.
function php_escape(string $v): string {
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $v);
}

// Replace __TOKEN__ markers (used for quoted string defines).
function render_tokens(string $text, array $tokens): string {
    foreach ($tokens as $key => $value) {
        $text = str_replace("__{$key}__", $value, $text);
    }
    return $text;
}

// Replace a whole define('NAME', ...); line (used for unquoted bool/int defines,
// which can't safely be done with bare token substitution since the template
// has to be valid PHP on its own before any substitution happens).
function set_define(string $text, string $name, string $rawValue): string {
    $pattern = "/define\('" . preg_quote($name, '/') . "',\s*[^)]*\);/";
    return preg_replace($pattern, "define('{$name}', {$rawValue});", $text, 1);
}

function current_const(string $name, $default) {
    return defined($name) ? constant($name) : $default;
}

// Pull a couple of existing values back out of HLS_URL / .env so the form
// is pre-filled with real settings when reconfiguring, rather than guesses.
function parse_existing_env(): array {
    $env = [];
    if (is_file(OUT_ENV)) {
        foreach (file(OUT_ENV, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^([A-Z_]+)=(.*)$/', trim($line), $m)) {
                $env[$m[1]] = $m[2];
            }
        }
    }
    return $env;
}

// ------------------------------------------------------------- form state --

$errors = [];
$saved = false;
$generated_password_notice = '';

if ($IS_FIRST_RUN) {
    $d = [
        'camera_ip'        => '192.168.1.100',
        'camera_rtsp_port' => 554,
        'camera_user'      => 'hamcam',
        'camera_pass'      => '',
        'rtsp_stream_hd'   => 'stream1',
        'rtsp_stream_sd'   => 'stream2',
        'onvif_port'       => 2020,
        'go2rtc_host'      => '192.168.1.1',
        'go2rtc_port'      => 1984,
        'motion_always_on' => false,
        'motion_start'     => '20:00',
        'motion_end'       => '07:00',
        'motion_threshold' => 3000,
        'motion_cooldown'  => 30,
        'site_title'       => 'HamCAM',
        'site_subtitle'    => 'Live Hamster Observatory',
        'tz'               => 'UTC',
        'web_port'         => 8765,
    ];
} else {
    $env = parse_existing_env();
    $go2rtc_host = '';
    $go2rtc_port = (int)($env['GO2RTC_API_PORT'] ?? 1984);
    if (defined('HLS_URL') && HLS_URL && preg_match('#^https?://([^:/]+):(\d+)#', HLS_URL, $m)) {
        $go2rtc_host = $m[1];
        $go2rtc_port = (int)$m[2];
    }
    $d = [
        'camera_ip'        => current_const('CAMERA_IP', ''),
        'camera_rtsp_port' => current_const('CAMERA_RTSP_PORT', 554),
        'camera_user'      => current_const('CAMERA_USER', ''),
        'camera_pass'      => '', // never re-display the existing secret — blank = "keep current"
        'rtsp_stream_hd'   => current_const('RTSP_STREAM_HD', 'stream1'),
        'rtsp_stream_sd'   => current_const('RTSP_STREAM_SD', 'stream2'),
        'onvif_port'       => current_const('ONVIF_PORT', 2020),
        'go2rtc_host'      => $go2rtc_host,
        'go2rtc_port'      => $go2rtc_port,
        'motion_always_on' => (bool)current_const('MOTION_ALWAYS_ON', false),
        'motion_start'     => current_const('MOTION_ACTIVE_START', '20:00'),
        'motion_end'       => current_const('MOTION_ACTIVE_END', '07:00'),
        'motion_threshold' => (int)($env['MOTION_THRESHOLD'] ?? current_const('MOTION_THRESHOLD', 3000)),
        'motion_cooldown'  => (int)($env['MOTION_COOLDOWN'] ?? current_const('MOTION_COOLDOWN', 30)),
        'site_title'       => current_const('SITE_TITLE', 'HamCAM'),
        'site_subtitle'    => current_const('SITE_SUBTITLE', 'Live Hamster Observatory'),
        'tz'               => $env['TZ'] ?? 'UTC',
        'web_port'         => (int)($env['HAMCAM_PORT'] ?? 8765),
    ];
}

// ------------------------------------------------------------- form submit --

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = fn(string $k, string $default = '') => trim((string)($_POST[$k] ?? $default));

    $new_password     = $p('password');
    $camera_ip         = $p('camera_ip');
    $camera_rtsp_port  = (int)$p('camera_rtsp_port', '554');
    $camera_user       = $p('camera_user');
    $camera_pass       = $p('camera_pass');
    $rtsp_hd           = $p('rtsp_stream_hd', 'stream1');
    $rtsp_sd           = $p('rtsp_stream_sd', 'stream2');
    $onvif_port        = (int)$p('onvif_port', '2020');
    $go2rtc_host       = $p('go2rtc_host');
    $go2rtc_port       = (int)$p('go2rtc_port', '1984');
    $always_on         = isset($_POST['motion_always_on']);
    $motion_start      = $p('motion_start', '20:00');
    $motion_end        = $p('motion_end', '07:00');
    $motion_threshold  = (int)$p('motion_threshold', '3000');
    $motion_cooldown   = (int)$p('motion_cooldown', '30');
    $site_title        = $p('site_title', 'HamCAM');
    $site_subtitle     = $p('site_subtitle', 'Live Hamster Observatory');
    $tz                = $p('tz', 'UTC');
    $web_port          = (int)$p('web_port', '8765');

    if ($IS_FIRST_RUN && $new_password === '') {
        $errors[] = 'Please set an access password.';
    }
    if (!$IS_FIRST_RUN && $new_password !== '' && strlen($new_password) < 4) {
        $errors[] = 'New password is too short (4 characters minimum).';
    }
    if ($camera_ip === '') $errors[] = 'Camera IP address is required.';
    if ($camera_user === '') $errors[] = 'Camera RTSP username is required.';
    if ($camera_pass === '' && $IS_FIRST_RUN) $errors[] = 'Camera RTSP password is required.';
    if ($go2rtc_host === '') $errors[] = 'go2rtc host/IP is required.';
    if ($rtsp_hd === '') $errors[] = 'Main RTSP stream path is required.';

    foreach (['camera_user' => $camera_user, 'camera_pass' => $camera_pass] as $label => $val) {
        if ($val !== '' && preg_match('#[:/@]#', $val)) {
            $errors[] = "Camera username/password can't contain ':', '/', or '@' — HamCAM builds RTSP URLs without encoding them, so it will break the stream.";
            break;
        }
    }

    if (empty($errors)) {
        // Keep the existing camera password if the field was left blank on a re-edit.
        if ($camera_pass === '' && !$IS_FIRST_RUN) {
            $camera_pass = current_const('CAMERA_PASS', '');
        }

        // Keep the existing password hash unless a new password was actually entered.
        if ($new_password !== '') {
            $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        } else {
            $password_hash = current_const('HAMCAM_PASSWORD_HASH', '');
        }

        $hls_url = sprintf('http://%s:%d/cam/index.m3u8', $go2rtc_host, $go2rtc_port);

        $tokens = [
            'HAMCAM_PASSWORD_HASH' => php_escape($password_hash),
            'CAMERA_IP'            => php_escape($camera_ip),
            'CAMERA_USER'          => php_escape($camera_user),
            'CAMERA_PASS'          => php_escape($camera_pass),
            'RTSP_STREAM_HD'       => php_escape($rtsp_hd),
            'RTSP_STREAM_SD'       => php_escape($rtsp_sd),
            'HLS_URL'              => php_escape($hls_url),
            'MOTION_ACTIVE_START'  => php_escape($motion_start),
            'MOTION_ACTIVE_END'    => php_escape($motion_end),
            'SITE_TITLE'           => php_escape($site_title),
            'SITE_SUBTITLE'        => php_escape($site_subtitle),
        ];

        // --- config.php ---
        $config_text = render_tokens(file_get_contents(TEMPLATE_CONFIG), $tokens);
        $config_text = set_define($config_text, 'CAMERA_RTSP_PORT', (string)$camera_rtsp_port);
        $config_text = set_define($config_text, 'ONVIF_PORT', (string)$onvif_port);
        $config_text = set_define($config_text, 'MOTION_ALWAYS_ON', $always_on ? 'true' : 'false');
        $config_text = set_define($config_text, 'MOTION_THRESHOLD', (string)$motion_threshold);
        $config_text = set_define($config_text, 'MOTION_COOLDOWN', (string)$motion_cooldown);
        $config_text = set_define($config_text, 'HAMCAM_SETUP_COMPLETE', 'true');

        // --- go2rtc.yaml ---
        $go2rtc_tokens = $tokens + ['CAMERA_RTSP_PORT' => (string)$camera_rtsp_port];
        $go2rtc_text = render_tokens(file_get_contents(TEMPLATE_GO2RTC), $go2rtc_tokens);

        // --- .env (read by docker-compose.yml on the host) ---
        $env_text = implode("\n", [
            "TZ={$tz}",
            "HAMCAM_PORT={$web_port}",
            "GO2RTC_API_PORT={$go2rtc_port}",
            "GO2RTC_RTSP_PORT=8554",
            "MOTION_ACTIVE_START={$motion_start}",
            "MOTION_ACTIVE_END={$motion_end}",
            'MOTION_ALWAYS_ON=' . ($always_on ? 'true' : 'false'),
            "MOTION_THRESHOLD={$motion_threshold}",
            "MOTION_COOLDOWN={$motion_cooldown}",
            'MOTION_LOG_LEVEL=INFO',
            'MOTION_MAX_EVENTS=1000',
            '',
        ]);

        $write_ok = @file_put_contents(OUT_CONFIG, $config_text) !== false
                 && @file_put_contents(OUT_GO2RTC, $go2rtc_text) !== false
                 && @file_put_contents(OUT_ENV, $env_text) !== false;

        if ($write_ok) {
            $saved = true;
            if ($IS_FIRST_RUN) {
                // No need to make someone log in immediately after they just set the password.
                $_SESSION['hamcam_auth'] = true;
                $_SESSION['hamcam_time'] = time();
            }
        } else {
            $errors[] = 'Could not write config.php / go2rtc.yaml / .env — the web server needs '
                      . 'write permission on those files. See the README "Setup" section '
                      . '(usually: chmod 666 config.php go2rtc.yaml .env on the host).';
        }
    }

    if (!empty($errors)) {
        // Re-render the form with whatever was just submitted, not the stale defaults.
        $d = compact(
            'camera_ip', 'camera_rtsp_port', 'camera_user', 'camera_pass',
            'rtsp_stream_hd', 'rtsp_stream_sd', 'onvif_port', 'go2rtc_host',
            'go2rtc_port', 'motion_start', 'motion_end', 'motion_threshold',
            'motion_cooldown', 'site_title', 'site_subtitle', 'tz', 'web_port'
        );
        $d['rtsp_stream_hd'] = $rtsp_hd;
        $d['rtsp_stream_sd'] = $rtsp_sd;
        $d['motion_always_on'] = $always_on;
        $d['camera_pass'] = ''; // don't echo a failed password attempt back into the field
    }
}

function esc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function checked(bool $v): string { return $v ? 'checked' : ''; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HamCAM — <?= $IS_FIRST_RUN ? 'Setup Wizard' : 'Settings' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
<style>
  :root {
    --pink:     #ff85b3;
    --pink2:    #ffb3d1;
    --pink3:    #ffe0ee;
    --yellow:   #ffe066;
    --orange:   #ffb347;
    --mint:     #7ee8c8;
    --lavender: #c9a0ff;
    --coral:    #ff6b6b;
    --white:    #ffffff;
    --text:     #5a3060;
    --muted:    #b08abf;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Nunito', sans-serif;
    color: var(--text);
    min-height: 100dvh;
    background: linear-gradient(135deg, #fff0f8 0%, #ffe8f5 25%, #fff8e0 50%, #f0e8ff 75%, #fff0f8 100%);
    padding: 32px 16px 80px;
  }
  .wrap { max-width: 720px; margin: 0 auto; }
  h1 {
    font-family: 'Fredoka One', cursive;
    color: var(--pink);
    text-align: center;
    font-size: 2rem;
    margin-bottom: 4px;
  }
  .subtitle { text-align: center; color: var(--muted); margin-bottom: 28px; }
  .card {
    background: var(--white);
    border-radius: 20px;
    padding: 24px 28px;
    margin-bottom: 20px;
    box-shadow: 0 6px 20px rgba(255, 133, 179, 0.15);
  }
  .card h2 {
    font-family: 'Fredoka One', cursive;
    font-size: 1.1rem;
    color: var(--coral);
    margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px;
  }
  label {
    display: block;
    font-weight: 700;
    font-size: 0.85rem;
    margin: 14px 0 6px;
    color: var(--text);
  }
  label .hint { font-weight: 400; color: var(--muted); font-size: 0.8rem; }
  input[type=text], input[type=password], input[type=number] {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid var(--pink3);
    border-radius: 10px;
    font-size: 0.95rem;
    font-family: inherit;
    color: var(--text);
    background: #fffafd;
  }
  input:focus { outline: none; border-color: var(--pink); }
  .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
  @media (max-width: 560px) { .row, .row3 { grid-template-columns: 1fr; } }
  .checkbox-row {
    display: flex; align-items: center; gap: 10px;
    margin-top: 14px;
  }
  .checkbox-row input { width: 20px; height: 20px; }
  .checkbox-row label { margin: 0; }
  button[type=submit] {
    display: block;
    width: 100%;
    margin-top: 8px;
    padding: 14px;
    border: none;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--pink), var(--coral));
    color: white;
    font-family: 'Fredoka One', cursive;
    font-size: 1.05rem;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(255, 107, 107, 0.4);
  }
  button[type=submit]:hover { filter: brightness(1.05); }
  .errors {
    background: #fff0f0;
    border: 2px solid var(--coral);
    color: #c0392b;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 20px;
  }
  .errors li { margin-left: 18px; }
  .success {
    background: #effff6;
    border: 2px solid var(--mint);
    border-radius: 16px;
    padding: 24px;
    text-align: center;
  }
  .success h2 { font-family: 'Fredoka One', cursive; color: #1e9e6a; margin-bottom: 10px; }
  .success code {
    display: block;
    background: #1e1e2e;
    color: #a6e3a1;
    padding: 10px 14px;
    border-radius: 10px;
    margin: 12px 0;
    font-size: 0.9rem;
    overflow-x: auto;
  }
  .success a.btn, a.btn {
    display: inline-block;
    margin-top: 14px;
    padding: 12px 28px;
    background: var(--pink);
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 700;
  }
  .note {
    font-size: 0.82rem;
    color: var(--muted);
    margin-top: 4px;
  }
</style>
</head>
<body>
<div class="wrap">
  <h1>🐹 HamCAM</h1>
  <p class="subtitle"><?= $IS_FIRST_RUN ? 'Let\'s get your camera set up' : 'Settings' ?></p>

  <?php if ($saved): ?>
    <div class="card success">
      <h2>✅ Saved!</h2>
      <?php if ($IS_FIRST_RUN): ?>
        <p>HamCAM is configured. Your login password is now active.</p>
      <?php else: ?>
        <p>Your settings were updated.</p>
      <?php endif; ?>
      <p class="note">Page/login changes (password, site title, camera IP/creds) take effect
      immediately. Changes to the video relay, the web UI port, or motion-detector
      timing need the stack restarted to take effect:</p>
      <code>docker compose up -d</code>
      <a class="btn" href="camera.php">Go to dashboard →</a>
    </div>
  <?php else: ?>

    <?php if (!empty($errors)): ?>
      <div class="errors">
        <strong>Please fix the following:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <?php if ($IS_FIRST_RUN): ?>
      <div class="card" style="background:#fff8e0; box-shadow:none;">
        <p>👋 First time here — this page is open to anyone on your network until you
        finish setup. Fill this in now rather than leaving it for later.</p>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">

      <div class="card">
        <h2>🔒 Login</h2>
        <label>Access password <?= $IS_FIRST_RUN ? '' : '<span class="hint">(leave blank to keep your current password)</span>' ?></label>
        <input type="password" name="password" placeholder="<?= $IS_FIRST_RUN ? 'Choose a password' : '••••••••' ?>">
      </div>

      <div class="card">
        <h2>📷 Camera</h2>
        <div class="row">
          <div>
            <label>Camera IP address</label>
            <input type="text" name="camera_ip" value="<?= esc($d['camera_ip']) ?>" placeholder="192.168.1.100">
          </div>
          <div>
            <label>RTSP port</label>
            <input type="number" name="camera_rtsp_port" value="<?= esc($d['camera_rtsp_port']) ?>">
          </div>
        </div>
        <div class="row">
          <div>
            <label>RTSP username</label>
            <input type="text" name="camera_user" value="<?= esc($d['camera_user']) ?>">
          </div>
          <div>
            <label>RTSP password <?= $IS_FIRST_RUN ? '' : '<span class="hint">(blank = keep current)</span>' ?></label>
            <input type="password" name="camera_pass" value="" placeholder="<?= $IS_FIRST_RUN ? '' : '••••••••' ?>">
          </div>
        </div>
        <div class="row">
          <div>
            <label>Main (HD) stream path</label>
            <input type="text" name="rtsp_stream_hd" value="<?= esc($d['rtsp_stream_hd']) ?>" placeholder="stream1">
          </div>
          <div>
            <label>Sub (SD) stream path</label>
            <input type="text" name="rtsp_stream_sd" value="<?= esc($d['rtsp_stream_sd']) ?>" placeholder="stream2">
          </div>
        </div>
        <label>ONVIF port <span class="hint">(for PTZ controls)</span></label>
        <input type="number" name="onvif_port" value="<?= esc($d['onvif_port']) ?>" style="max-width:160px">
      </div>

      <div class="card">
        <h2>📡 Video relay (go2rtc)</h2>
        <p class="note">Usually the same machine running docker-compose.</p>
        <div class="row">
          <div>
            <label>go2rtc host/IP</label>
            <input type="text" name="go2rtc_host" value="<?= esc($d['go2rtc_host']) ?>" placeholder="192.168.1.1">
          </div>
          <div>
            <label>go2rtc API/HLS port</label>
            <input type="number" name="go2rtc_port" value="<?= esc($d['go2rtc_port']) ?>">
          </div>
        </div>
      </div>

      <div class="card">
        <h2>🐾 Motion detection</h2>
        <div class="checkbox-row">
          <input type="checkbox" id="always_on" name="motion_always_on" <?= checked($d['motion_always_on']) ?>>
          <label for="always_on" style="margin:0">Log motion 24/7 (instead of a nightly window)</label>
        </div>
        <div class="row" style="margin-top:14px">
          <div>
            <label>Window start (24h HH:MM)</label>
            <input type="text" name="motion_start" value="<?= esc($d['motion_start']) ?>" placeholder="20:00">
          </div>
          <div>
            <label>Window end (24h HH:MM)</label>
            <input type="text" name="motion_end" value="<?= esc($d['motion_end']) ?>" placeholder="07:00">
          </div>
        </div>
        <div class="row">
          <div>
            <label>Sensitivity threshold <span class="hint">(lower = more sensitive)</span></label>
            <input type="number" name="motion_threshold" value="<?= esc($d['motion_threshold']) ?>">
          </div>
          <div>
            <label>Cooldown between events (seconds)</label>
            <input type="number" name="motion_cooldown" value="<?= esc($d['motion_cooldown']) ?>">
          </div>
        </div>
      </div>

      <div class="card">
        <h2>🎨 Site</h2>
        <div class="row">
          <div>
            <label>Site title</label>
            <input type="text" name="site_title" value="<?= esc($d['site_title']) ?>">
          </div>
          <div>
            <label>Site subtitle</label>
            <input type="text" name="site_subtitle" value="<?= esc($d['site_subtitle']) ?>">
          </div>
        </div>
      </div>

      <div class="card">
        <h2>🐳 Docker</h2>
        <p class="note">These need <code>docker compose up -d</code> on the host to apply.</p>
        <div class="row">
          <div>
            <label>Timezone <span class="hint">(TZ database name)</span></label>
            <input type="text" name="tz" value="<?= esc($d['tz']) ?>" placeholder="UTC">
          </div>
          <div>
            <label>Web UI port</label>
            <input type="number" name="web_port" value="<?= esc($d['web_port']) ?>">
          </div>
        </div>
      </div>

      <button type="submit"><?= $IS_FIRST_RUN ? '🐹 Finish setup' : 'Save settings' ?></button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
