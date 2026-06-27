<?php
// ============================================================
// HamCAM motion_log.php
// Version: 1.2
// ============================================================
require_once 'config.php';
require_once 'setup_guard.php';
session_start();
if (empty($_SESSION['hamcam_auth']) || (time() - ($_SESSION['hamcam_time']??0)) > SESSION_TIMEOUT) {
    session_destroy(); header('Location: index.php'); exit;
}
$_SESSION['hamcam_time'] = time();

// -- DB connection --------------------------------------------------------------
$db_path  = '/data/motion.db';
$snap_dir = '/data/snapshots';
$db = null;
$db_available = file_exists($db_path);
if ($db_available) {
    try {
        $db = new PDO("sqlite:$db_path", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Exception $e) {
        $db_available = false;
    }
}

// -- API endpoints (JSON) -------------------------------------------------------
$action = $_GET['action'] ?? '';

if ($action === 'events') {
    header('Content-Type: application/json');
    if (!$db) { echo json_encode(['events'=>[],'total'=>0]); exit; }
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $limit   = 50;
    $offset  = ($page - 1) * $limit;
    $filter  = $_GET['filter'] ?? 'all';  // all | today | week
    $where   = '1=1';
    if ($filter === 'today') {
        $start = strtotime('today midnight');
        $where = "ts >= $start";
    } elseif ($filter === 'week') {
        $start = strtotime('-7 days');
        $where = "ts >= $start";
    }
    $total  = $db->query("SELECT COUNT(*) FROM events WHERE $where")->fetchColumn();
    $rows   = $db->query(
        "SELECT id, ts, ts_human, snapshot, score FROM events WHERE $where ORDER BY ts DESC LIMIT $limit OFFSET $offset"
    )->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['events' => $rows, 'total' => (int)$total, 'page' => $page, 'limit' => $limit]);
    exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$db) { echo json_encode(['ok'=>false]); exit; }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $snap = $db->query("SELECT snapshot FROM events WHERE id=$id")->fetchColumn();
        if ($snap && file_exists("$snap_dir/$snap")) unlink("$snap_dir/$snap");
        $db->exec("DELETE FROM events WHERE id=$id");
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$db) { echo json_encode(['ok'=>false]); exit; }
    // Delete all snapshots
    foreach (glob("$snap_dir/motion_*.jpg") ?: [] as $f) unlink($f);
    $db->exec("DELETE FROM events");
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'snapshot') {
    $file = basename($_GET['file'] ?? '');
    $path = "$snap_dir/$file";
    if ($file && file_exists($path) && preg_match('/^motion_\d+\.jpg$/', $file)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: max-age=86400');
        readfile($path);
    } else {
        http_response_code(404);
    }
    exit;
}

// Manual snapshot trigger from the Snap button
if ($action === 'manual_snap') {
    header('Content-Type: application/json');
    if (!$db) { echo json_encode(['ok'=>false,'error'=>'DB not available']); exit; }

    // Fetch snapshot directly from camera (same logic as snapshot.php)
    $cam_url   = sprintf('http://%s:%s@%s/snapshot/stream0',
        urlencode(CAMERA_USER), urlencode(CAMERA_PASS), CAMERA_IP);
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
    $snap_data = @file_get_contents($cam_url, false, $ctx);
    $filename  = null;
    $ts        = time();

    if ($snap_data && strlen($snap_data) > 500) {
        // Looks like a real JPEG
        @mkdir($snap_dir, 0755, true);
        $filename = 'motion_' . $ts . '_manual.jpg';
        file_put_contents("$snap_dir/$filename", $snap_data);
    }

    $ts_human = date('Y-m-d H:i:s', $ts);
    try {
        $stmt = $db->prepare(
            'INSERT INTO events (ts, ts_human, snapshot, score, notes) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$ts, $ts_human, $filename, 0, 'Manual snapshot']);
        echo json_encode(['ok' => true, 'ts_human' => $ts_human, 'snapshot' => $filename]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'stats') {
    header('Content-Type: application/json');
    if (!$db) { echo json_encode(['today'=>0,'week'=>0,'total'=>0]); exit; }
    $today = $db->query("SELECT COUNT(*) FROM events WHERE ts >= ".strtotime('today midnight'))->fetchColumn();
    $week  = $db->query("SELECT COUNT(*) FROM events WHERE ts >= ".strtotime('-7 days'))->fetchColumn();
    $total = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
    $last  = $db->query("SELECT ts_human FROM events ORDER BY ts DESC LIMIT 1")->fetchColumn();
    echo json_encode(compact('today','week','total','last'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HamCAM -- Motion Log</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
<style>
  :root {
    --pink:#ff85b3;--pink2:#ffb3d1;--pink3:#ffe0ee;--pink4:#fff0f8;
    --yellow:#ffe066;--orange:#ffb347;--mint:#7ee8c8;--mint2:#e0fff7;
    --lavender:#c9a0ff;--lav2:#f0e8ff;--coral:#ff6b6b;
    --white:#fff;--bg:#fff5fb;--text:#5a3060;--muted:#b08abf;--border:#ffd6ea;
    --radius:14px;
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--bg);font-family:'Nunito',sans-serif;color:var(--text);min-height:100dvh;}
  body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
    background:radial-gradient(ellipse 60% 40% at 10% 15%,rgba(255,181,215,.18) 0%,transparent 70%),
               radial-gradient(ellipse 50% 40% at 90% 80%,rgba(201,160,255,.15) 0%,transparent 70%);}

  /* Header */
  header{position:sticky;top:0;z-index:100;background:var(--white);
    border-bottom:3px solid var(--pink3);padding:10px 16px;
    display:flex;align-items:center;justify-content:space-between;gap:10px;
    box-shadow:0 4px 20px rgba(255,133,179,.12);}
  .header-left{display:flex;align-items:center;gap:10px;}
  .logo-ham{width:36px;height:36px;flex-shrink:0;animation:logoBounce 2.2s ease-in-out infinite;
    filter:drop-shadow(0 3px 6px rgba(255,133,179,.4));}
  @keyframes logoBounce{0%,100%{transform:translateY(0) rotate(-5deg);}50%{transform:translateY(-5px) rotate(5deg);}}
  h1{font-family:'Fredoka One',cursive;font-size:1.6rem;color:var(--pink);text-shadow:2px 2px 0 var(--pink2);line-height:1;}
  .header-right{display:flex;align-items:center;gap:6px;}
  .hbtn{background:var(--pink3);border:2px solid var(--border);border-radius:11px;
    width:36px;height:36px;display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all .2s;text-decoration:none;color:var(--pink);}
  .hbtn:hover{background:var(--pink2);transform:scale(1.1) rotate(-5deg);}
  .hbtn svg{width:17px;height:17px;}

  /* Layout */
  .page{position:relative;z-index:1;max-width:1200px;margin:0 auto;padding:16px;}

  /* Stats row */
  .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:18px;}
  .stat-card{background:var(--white);border:2.5px solid var(--border);border-radius:var(--radius);
    padding:14px 16px;text-align:center;box-shadow:0 4px 16px rgba(255,133,179,.07);}
  .stat-card .num{font-family:'Fredoka One',cursive;font-size:2rem;color:var(--pink);line-height:1;}
  .stat-card .lbl{font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-top:3px;}

  /* Controls bar */
  .controls{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
  .filter-btn{background:var(--white);border:2.5px solid var(--border);border-radius:12px;
    color:var(--text);font-family:'Nunito',sans-serif;font-weight:800;font-size:.78rem;
    padding:7px 14px;cursor:pointer;transition:all .18s;}
  .filter-btn:hover,.filter-btn.active{background:var(--pink3);border-color:var(--pink);color:var(--pink);}
  .filter-btn.active{box-shadow:0 2px 8px rgba(255,133,179,.25);}
  .danger-btn{background:var(--white);border:2.5px solid #ffd6d6;border-radius:12px;
    color:var(--coral);font-family:'Nunito',sans-serif;font-weight:800;font-size:.78rem;
    padding:7px 14px;cursor:pointer;transition:all .18s;margin-left:auto;}
  .danger-btn:hover{background:#fff0f0;border-color:var(--coral);}

  /* Event grid */
  .event-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;}
  .event-card{background:var(--white);border:2.5px solid var(--border);border-radius:var(--radius);
    overflow:hidden;box-shadow:0 4px 16px rgba(255,133,179,.07);
    transition:transform .18s,box-shadow .18s;position:relative;}
  .event-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(255,133,179,.18);}
  .event-thumb{width:100%;aspect-ratio:16/9;object-fit:cover;background:#1a0a20;display:block;cursor:pointer;}
  .event-thumb-placeholder{width:100%;aspect-ratio:16/9;background:linear-gradient(135deg,var(--pink3),var(--lav2));
    display:flex;align-items:center;justify-content:center;}
  .event-thumb-placeholder svg{width:40px;height:40px;opacity:.5;}
  .event-info{padding:10px 12px;}
  .event-time{font-family:'Fredoka One',cursive;font-size:.92rem;color:var(--text);margin-bottom:3px;}
  .event-meta{display:flex;align-items:center;justify-content:space-between;}
  .event-score{font-size:.7rem;font-weight:800;color:var(--muted);}
  .event-score span{color:var(--pink);}
  .del-btn{background:none;border:none;cursor:pointer;color:var(--muted);padding:2px;
    border-radius:6px;transition:color .15s,background .15s;display:flex;}
  .del-btn:hover{color:var(--coral);background:#fff0f0;}
  .del-btn svg{width:14px;height:14px;}

  /* Empty state */
  .empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
  .empty-state svg{width:80px;height:80px;margin:0 auto 16px;display:block;opacity:.4;}
  .empty-state h2{font-family:'Fredoka One',cursive;font-size:1.4rem;color:var(--muted);margin-bottom:8px;}
  .empty-state p{font-size:.85rem;font-weight:700;max-width:320px;margin:0 auto;}

  /* Pagination */
  .pagination{display:flex;justify-content:center;gap:8px;margin-top:20px;}
  .page-btn{background:var(--white);border:2.5px solid var(--border);border-radius:10px;
    color:var(--text);font-family:'Nunito',sans-serif;font-weight:800;font-size:.82rem;
    padding:7px 14px;cursor:pointer;transition:all .18s;}
  .page-btn:hover,.page-btn.active{background:var(--pink3);border-color:var(--pink);}
  .page-btn:disabled{opacity:.4;cursor:not-allowed;}

  /* Lightbox */
  .lightbox{display:none;position:fixed;inset:0;background:rgba(26,10,32,.92);z-index:999;
    align-items:center;justify-content:center;padding:20px;}
  .lightbox.show{display:flex;}
  .lightbox img{max-width:90vw;max-height:85vh;border-radius:12px;
    box-shadow:0 20px 60px rgba(0,0,0,.6);border:3px solid var(--pink2);}
  .lightbox-close{position:absolute;top:16px;right:16px;background:var(--pink);
    border:none;border-radius:50%;width:40px;height:40px;cursor:pointer;
    display:flex;align-items:center;justify-content:center;color:white;font-size:20px;}
  .lightbox-info{position:absolute;bottom:20px;left:50%;transform:translateX(-50%);
    background:rgba(255,255,255,.95);border-radius:10px;padding:8px 16px;
    font-weight:800;font-size:.82rem;color:var(--text);white-space:nowrap;}

  /* Toast */
  .toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%) translateY(80px);
    background:white;border:2.5px solid var(--pink2);border-radius:12px;padding:10px 20px;
    font-weight:800;font-size:.82rem;color:var(--text);z-index:9999;
    transition:transform .3s cubic-bezier(.34,1.56,.64,1),opacity .3s;
    opacity:0;pointer-events:none;box-shadow:0 8px 24px rgba(255,133,179,.3);}
  .toast.show{transform:translateX(-50%) translateY(0);opacity:1;}

  /* Active window badge */
  .window-badge{display:inline-flex;align-items:center;gap:6px;
    background:var(--mint2);border:2px solid var(--mint);border-radius:10px;
    font-size:.72rem;font-weight:800;color:#2a7a5a;padding:5px 10px;}
  .window-dot{width:7px;height:7px;border-radius:50%;background:var(--mint);
    animation:dotBlink 2s ease-in-out infinite;}
  @keyframes dotBlink{0%,100%{opacity:1;}50%{opacity:.3;}}
  .window-badge.inactive{background:var(--pink3);border-color:var(--border);color:var(--muted);}
  .window-badge.inactive .window-dot{background:var(--muted);animation:none;}
</style>
</head>
<body>
<header>
  <div class="header-left">
    <svg class="logo-ham" viewBox="0 0 38 38" fill="none">
      <circle cx="19" cy="16" r="12" fill="#FFCBA4"/>
      <ellipse cx="19" cy="28" rx="10" ry="8" fill="#FFCBA4"/>
      <ellipse cx="10" cy="18" rx="5" ry="4" fill="#FFB085"/>
      <ellipse cx="28" cy="18" rx="5" ry="4" fill="#FFB085"/>
      <ellipse cx="10.5" cy="8" rx="4.5" ry="4" fill="#FFCBA4"/>
      <ellipse cx="10.5" cy="8" rx="3" ry="2.2" fill="#FF9EC0"/>
      <ellipse cx="27.5" cy="8" rx="4.5" ry="4" fill="#FFCBA4"/>
      <ellipse cx="27.5" cy="8" rx="3" ry="2.2" fill="#FF9EC0"/>
      <circle cx="15" cy="15" r="3" fill="white"/>
      <circle cx="23" cy="15" r="3" fill="white"/>
      <circle cx="15.5" cy="15" r="1.8" fill="#2D1040"/>
      <circle cx="23.5" cy="15" r="1.8" fill="#2D1040"/>
      <circle cx="16" cy="14.2" r="0.7" fill="white"/>
      <circle cx="24" cy="14.2" r="0.7" fill="white"/>
      <ellipse cx="19" cy="19" rx="1.8" ry="1.4" fill="#FF85B3"/>
      <path d="M16 22 Q19 25 22 22" stroke="#FF85B3" stroke-width="1.4" stroke-linecap="round" fill="none"/>
    </svg>
    <h1>Motion Log</h1>
    <div id="window-badge" class="window-badge inactive">
      <span class="window-dot"></span>
      <span id="window-label">Checking...</span>
    </div>
  </div>
  <div class="header-right">
    <a class="hbtn" href="camera.php" title="Back to camera">
      <svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="16" height="12" rx="1.5"/><circle cx="9" cy="9" r="3"/></svg>
    </a>
    <a class="hbtn" href="logout.php" title="Logout">
      <svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3H3a1 1 0 00-1 1v10a1 1 0 001 1h4M12 13l4-4-4-4M16 9H7"/></svg>
    </a>
  </div>
</header>

<div class="page">

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card"><div class="num" id="stat-today">--</div><div class="lbl">Today</div></div>
    <div class="stat-card"><div class="num" id="stat-week">--</div><div class="lbl">This Week</div></div>
    <div class="stat-card"><div class="num" id="stat-total">--</div><div class="lbl">All Time</div></div>
    <div class="stat-card"><div class="num" id="stat-last" style="font-size:1rem;padding-top:4px;">--</div><div class="lbl">Last Event</div></div>
  </div>

  <!-- Controls -->
  <div class="controls">
    <button class="filter-btn active" onclick="setFilter('all',this)">All</button>
    <button class="filter-btn" onclick="setFilter('today',this)">Today</button>
    <button class="filter-btn" onclick="setFilter('week',this)">This Week</button>
    <button class="danger-btn" onclick="confirmClear()">
      Clear All
    </button>
  </div>

  <!-- Event grid -->
  <div class="event-grid" id="event-grid">
    <div class="empty-state" id="loading-state">
      <svg viewBox="0 0 80 80" fill="none">
        <circle cx="40" cy="40" r="28" fill="#FFCBA4"/>
        <circle cx="32" cy="36" r="5" fill="white"/><circle cx="48" cy="36" r="5" fill="white"/>
        <circle cx="32.5" cy="36" r="3" fill="#2D1040"/><circle cx="48.5" cy="36" r="3" fill="#2D1040"/>
        <ellipse cx="40" cy="46" rx="3" ry="2.5" fill="#FF85B3"/>
        <path d="M35 52 Q40 56 45 52" stroke="#FF85B3" stroke-width="2" stroke-linecap="round" fill="none"/>
      </svg>
      <h2>Loading events...</h2>
    </div>
  </div>

  <!-- Pagination -->
  <div class="pagination" id="pagination"></div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
  <button class="lightbox-close" onclick="closeLightbox()">&#x2715;</button>
  <img id="lightbox-img" src="" alt="Motion snapshot">
  <div class="lightbox-info" id="lightbox-info"></div>
</div>

<div class="toast" id="toast"></div>

<script>
let currentFilter = 'all';
let currentPage   = 1;

// -- Active window check --------------------------------------------------------
const ACTIVE_START = '<?= MOTION_ACTIVE_START ?? '20:00' ?>';
const ACTIVE_END   = '<?= MOTION_ACTIVE_END   ?? '07:00' ?>';
const ALWAYS_ON    = <?= (MOTION_ALWAYS_ON ?? false) ? 'true' : 'false' ?>;

function inActiveWindow() {
  if (ALWAYS_ON) return true;
  const now   = new Date();
  const cur   = now.getHours() * 60 + now.getMinutes();
  const [sh,sm] = ACTIVE_START.split(':').map(Number);
  const [eh,em] = ACTIVE_END.split(':').map(Number);
  const start = sh * 60 + sm, end = eh * 60 + em;
  return start > end ? (cur >= start || cur <= end) : (cur >= start && cur <= end);
}

function updateWindowBadge() {
  const badge = document.getElementById('window-badge');
  const label = document.getElementById('window-label');
  const active = inActiveWindow();
  badge.className = 'window-badge' + (active ? '' : ' inactive');
  label.textContent = active
    ? `Monitoring ${ACTIVE_START} - ${ACTIVE_END}`
    : `Inactive until ${ACTIVE_START}`;
}
updateWindowBadge();
setInterval(updateWindowBadge, 60000);

// -- Stats ----------------------------------------------------------------------
async function loadStats() {
  try {
    const r = await fetch('motion_log.php?action=stats');
    const d = await r.json();
    document.getElementById('stat-today').textContent = d.today;
    document.getElementById('stat-week').textContent  = d.week;
    document.getElementById('stat-total').textContent = d.total;
    document.getElementById('stat-last').textContent  = d.last
      ? d.last.split(' ')[1]   // just show time portion
      : 'Never';
  } catch(e) {}
}
loadStats();

// -- Events ---------------------------------------------------------------------
async function loadEvents(page = 1) {
  currentPage = page;
  const grid = document.getElementById('event-grid');
  grid.innerHTML = '';

  try {
    const r = await fetch(`motion_log.php?action=events&filter=${currentFilter}&page=${page}`);
    const d = await r.json();

    if (d.events.length === 0) {
      grid.innerHTML = `
        <div class="empty-state" style="grid-column:1/-1">
          <svg viewBox="0 0 80 80" fill="none">
            <circle cx="40" cy="40" r="28" fill="#FFCBA4"/>
            <circle cx="32" cy="36" r="5" fill="white"/><circle cx="48" cy="36" r="5" fill="white"/>
            <circle cx="32.8" cy="36" r="3" fill="#2D1040"/><circle cx="48.8" cy="36" r="3" fill="#2D1040"/>
            <ellipse cx="40" cy="48" rx="3" ry="2.5" fill="#FF85B3"/>
            <path d="M35 54 Q40 50 45 54" stroke="#FF85B3" stroke-width="2" stroke-linecap="round" fill="none"/>
          </svg>
          <h2>No events yet!</h2>
          <p>Motion events will appear here once the detector is running and something moves.</p>
        </div>`;
      document.getElementById('pagination').innerHTML = '';
      return;
    }

    d.events.forEach(ev => {
      const card = document.createElement('div');
      card.className = 'event-card';
      const thumbHtml = ev.snapshot
        ? `<img class="event-thumb" src="motion_log.php?action=snapshot&file=${encodeURIComponent(ev.snapshot)}"
               alt="Motion snapshot" onclick="openLightbox(this.src,'${ev.ts_human}',${ev.score})"
               loading="lazy">`
        : `<div class="event-thumb-placeholder">
             <svg viewBox="0 0 40 40" fill="none"><circle cx="20" cy="18" r="10" fill="#FFCBA4"/>
             <ellipse cx="20" cy="30" rx="8" ry="6" fill="#FFCBA4"/>
             <circle cx="16" cy="16" r="3" fill="white"/><circle cx="24" cy="16" r="3" fill="white"/>
             <circle cx="16.5" cy="16" r="1.8" fill="#2D1040"/><circle cx="24.5" cy="16" r="1.8" fill="#2D1040"/>
             <ellipse cx="20" cy="21" rx="1.5" ry="1.2" fill="#FF85B3"/>
             </svg></div>`;

      // Format date nicely
      const dt     = new Date(ev.ts * 1000);
      const isToday = new Date().toDateString() === dt.toDateString();
      const dateLbl = isToday ? 'Today' : dt.toLocaleDateString('en-US',{month:'short',day:'numeric'});
      const timeLbl = dt.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit'});

      card.innerHTML = `
        ${thumbHtml}
        <div class="event-info">
          <div class="event-time">${dateLbl} &bull; ${timeLbl}</div>
          <div class="event-meta">
            <div class="event-score">Activity: <span>${ev.score.toLocaleString()}</span></div>
            <button class="del-btn" onclick="deleteEvent(${ev.id},this)" title="Delete">
              <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                <path d="M2 4h10M5 4V2h4v2M6 7v4M8 7v4M3 4l.8 8h6.4L11 4"/>
              </svg>
            </button>
          </div>
        </div>`;
      grid.appendChild(card);
    });

    // Pagination
    const totalPages = Math.ceil(d.total / d.limit);
    const pag = document.getElementById('pagination');
    pag.innerHTML = '';
    if (totalPages > 1) {
      const prev = document.createElement('button');
      prev.className = 'page-btn'; prev.textContent = 'Prev';
      prev.disabled = page <= 1;
      prev.onclick = () => loadEvents(page - 1);
      pag.appendChild(prev);

      for (let p = Math.max(1, page-2); p <= Math.min(totalPages, page+2); p++) {
        const btn = document.createElement('button');
        btn.className = 'page-btn' + (p === page ? ' active' : '');
        btn.textContent = p;
        btn.onclick = () => loadEvents(p);
        pag.appendChild(btn);
      }

      const next = document.createElement('button');
      next.className = 'page-btn'; next.textContent = 'Next';
      next.disabled = page >= totalPages;
      next.onclick = () => loadEvents(page + 1);
      pag.appendChild(next);
    }

  } catch(e) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1"><h2>Error loading events</h2><p>${e.message}</p></div>`;
  }
}
loadEvents();

// Auto-refresh every 30s
setInterval(() => { loadEvents(currentPage); loadStats(); }, 30000);

// -- Filter ---------------------------------------------------------------------
function setFilter(f, btn) {
  currentFilter = f;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  loadEvents(1);
}

// -- Delete ---------------------------------------------------------------------
async function deleteEvent(id, btn) {
  btn.closest('.event-card').style.opacity = '0.4';
  const fd = new FormData(); fd.append('id', id);
  await fetch('motion_log.php?action=delete', {method:'POST', body:fd});
  btn.closest('.event-card').remove();
  loadStats();
  toast('Event deleted');
}

// -- Clear all ------------------------------------------------------------------
function confirmClear() {
  if (!confirm('Delete all motion events and snapshots? This cannot be undone.')) return;
  fetch('motion_log.php?action=clear', {method:'POST'})
    .then(() => { loadEvents(1); loadStats(); toast('All events cleared'); });
}

// -- Lightbox -------------------------------------------------------------------
function openLightbox(src, time, score) {
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox-info').textContent = `${time}  |  Activity score: ${score.toLocaleString()}`;
  document.getElementById('lightbox').classList.add('show');
}
function closeLightbox(e) {
  if (!e || e.target === document.getElementById('lightbox') || e.target.className === 'lightbox-close') {
    document.getElementById('lightbox').classList.remove('show');
  }
}

// -- Toast ----------------------------------------------------------------------
let _tt;
function toast(msg, dur=2500) {
  const el = document.getElementById('toast');
  el.textContent = msg; el.classList.add('show');
  clearTimeout(_tt); _tt = setTimeout(() => el.classList.remove('show'), dur);
}
</script>
</body>
</html>