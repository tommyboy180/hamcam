<?php
require_once 'config.php';
require_once 'setup_guard.php';
session_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = trim($_POST['password'] ?? '');
    if (password_verify($pw, HAMCAM_PASSWORD_HASH)) {
        $_SESSION['hamcam_auth'] = true;
        $_SESSION['hamcam_time'] = time();
        header('Location: camera.php');
        exit;
    } else {
        $error = 'Wrong password! Try again (>_<)';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HamCAM -- Login</title>
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
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
    cursor: default;
  }

  /* Animated gradient bg */
  .bg {
    position: fixed; inset: 0; z-index: 0;
    background: linear-gradient(135deg, #fff0f8 0%, #ffe8f5 25%, #fff8e0 50%, #f0e8ff 75%, #fff0f8 100%);
    background-size: 400% 400%;
    animation: bgShift 10s ease-in-out infinite alternate;
  }
  @keyframes bgShift {
    0%   { background-position: 0% 50%; }
    100% { background-position: 100% 50%; }
  }

  /* Floating pastel circles */
  .bubbles { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
  .bubble {
    position: absolute; border-radius: 50%; opacity: 0.35;
    animation: floatUp linear infinite;
  }
  @keyframes floatUp {
    0%   { transform: translateY(110vh); opacity: 0; }
    10%  { opacity: 0.35; }
    90%  { opacity: 0.35; }
    100% { transform: translateY(-20vh); opacity: 0; }
  }

  /* Floating SVG decorations */
  .floaters { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
  .floater {
    position: absolute;
    animation: floatDrift ease-in-out infinite alternate;
    filter: drop-shadow(0 4px 10px rgba(255,133,179,0.35));
  }
  @keyframes floatDrift {
    from { transform: translateY(0px) rotate(-8deg); }
    to   { transform: translateY(-22px) rotate(8deg); }
  }

  /* Sparkle dots */
  .sparkle {
    position: fixed; border-radius: 50%;
    animation: sparklePop ease-in-out infinite;
    pointer-events: none; z-index: 1;
  }
  @keyframes sparklePop {
    0%,100% { transform: scale(0.4); opacity: 0.25; }
    50%      { transform: scale(1.8); opacity: 0.8; }
  }

  /* Card */
  .card {
    position: relative; z-index: 10;
    background: var(--white);
    border-radius: 32px;
    padding: 44px 38px 36px;
    width: min(440px, 92vw);
    box-shadow:
      0 0 0 4px var(--pink2),
      0 20px 60px rgba(255,133,179,0.28),
      0 5px 0 0 var(--pink);
    animation: cardPop 0.6s cubic-bezier(0.34,1.56,0.64,1) both;
  }
  @keyframes cardPop {
    from { opacity: 0; transform: translateY(48px) scale(0.88); }
    to   { opacity: 1; transform: none; }
  }

  /* Hamster SVG hero */
  .hamster-hero {
    text-align: center; margin-bottom: 8px;
    animation: heroWiggle 3s ease-in-out infinite;
  }
  @keyframes heroWiggle {
    0%,100% { transform: rotate(-4deg) scale(1); }
    50%      { transform: rotate(4deg) scale(1.06); }
  }
  .hamster-hero svg {
    width: 120px; height: 120px;
    filter: drop-shadow(0 8px 18px rgba(255,133,179,0.5));
  }

  h1 {
    font-family: 'Fredoka One', cursive;
    font-size: 2.9rem; color: var(--pink);
    text-align: center;
    text-shadow: 3px 3px 0 var(--pink2), 6px 6px 0 rgba(255,133,179,0.18);
    margin-bottom: 4px;
  }

  .tagline {
    text-align: center; font-size: 0.82rem; font-weight: 800;
    color: var(--muted); margin-bottom: 26px; letter-spacing: 0.05em;
  }

  /* Paw divider (all SVG) */
  .divider {
    display: flex; align-items: center; gap: 10px; margin-bottom: 22px;
  }
  .divider::before, .divider::after {
    content: ''; flex: 1; height: 2px; border-radius: 2px;
    background: linear-gradient(90deg, transparent, var(--pink2), transparent);
  }

  label {
    display: block; font-size: 0.82rem; font-weight: 800;
    color: var(--text); margin-bottom: 8px; letter-spacing: 0.04em;
    display: flex; align-items: center; gap: 6px;
  }

  .input-wrap { position: relative; margin-bottom: 18px; }
  .input-wrap input {
    width: 100%; background: #fff5fa;
    border: 2.5px solid var(--pink2); border-radius: 14px;
    color: var(--text); font-family: 'Nunito', sans-serif;
    font-size: 1rem; font-weight: 700;
    padding: 14px 52px 14px 18px; outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
  }
  .input-wrap input:focus {
    border-color: var(--pink);
    box-shadow: 0 0 0 4px rgba(255,133,179,0.15);
    transform: scale(1.01);
  }
  .input-wrap input::placeholder { color: var(--pink2); }

  .toggle-pw {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: var(--pink3); border: none; border-radius: 8px;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: transform 0.2s, background 0.2s;
  }
  .toggle-pw:hover { transform: translateY(-50%) scale(1.15); background: var(--pink2); }
  .toggle-pw svg { width: 16px; height: 16px; }

  .error-msg {
    background: #fff0f3; border: 2px solid #ffb3c4; border-radius: 12px;
    color: var(--coral); font-weight: 700; font-size: 0.85rem;
    padding: 10px 14px; margin-bottom: 16px; text-align: center;
    animation: wobble 0.45s ease;
    display: flex; align-items: center; justify-content: center; gap: 8px;
  }
  @keyframes wobble {
    0%,100%{ transform: rotate(0deg); }
    25%    { transform: rotate(-3deg); }
    75%    { transform: rotate(3deg); }
  }

  .btn {
    width: 100%;
    background: linear-gradient(135deg, var(--pink) 0%, var(--orange) 100%);
    color: white; font-family: 'Fredoka One', cursive; font-size: 1.25rem;
    letter-spacing: 0.05em; border: none; border-radius: 16px; padding: 16px;
    cursor: pointer;
    box-shadow: 0 5px 0 #cc5c87, 0 8px 24px rgba(255,133,179,0.4);
    transition: transform 0.12s, box-shadow 0.12s;
    position: relative; overflow: hidden;
    display: flex; align-items: center; justify-content: center; gap: 10px;
  }
  .btn::after {
    content: ''; position: absolute; inset: 0; border-radius: inherit;
    background: linear-gradient(180deg, rgba(255,255,255,0.28) 0%, transparent 55%);
    pointer-events: none;
  }
  .btn:hover  { transform: translateY(-2px); box-shadow: 0 7px 0 #cc5c87, 0 14px 30px rgba(255,133,179,0.5); }
  .btn:active { transform: translateY(3px); box-shadow: 0 2px 0 #cc5c87; }
  .btn svg { width: 22px; height: 22px; flex-shrink: 0; }

  /* Bouncing paw prints (SVG) */
  .paw-row {
    display: flex; justify-content: center; gap: 10px; margin-top: 18px;
  }
  .paw-row svg {
    width: 22px; height: 22px;
    animation: pawHop 1.8s ease-in-out infinite;
    filter: drop-shadow(0 2px 4px rgba(255,133,179,0.4));
  }
  .paw-row svg:nth-child(2) { animation-delay: .22s; }
  .paw-row svg:nth-child(3) { animation-delay: .44s; }
  @keyframes pawHop {
    0%,100% { transform: translateY(0) rotate(-10deg); }
    50%      { transform: translateY(-7px) rotate(10deg); }
  }

  .footer {
    text-align: center; margin-top: 10px;
    font-size: 0.72rem; font-weight: 700; color: var(--muted);
    display: flex; align-items: center; justify-content: center; gap: 6px;
  }
  .footer svg { width: 12px; height: 12px; }
</style>
</head>
<body>
<div class="bg"></div>
<div class="bubbles" id="bubbles"></div>

<!-- SVG floating decorations - flowers, stars, hearts, no emojis -->
<div class="floaters" id="floaters"></div>
<div id="sparkles"></div>

<div class="card">

  <!-- Anime-style hamster hero (pure SVG) -->
  <div class="hamster-hero">
    <svg viewBox="0 0 120 124" fill="none" xmlns="http://www.w3.org/2000/svg">
      <!-- shadow -->
      <ellipse cx="60" cy="118" rx="26" ry="6" fill="#FFB3D1" opacity="0.35"/>
      <!-- body -->
      <ellipse cx="60" cy="82" rx="32" ry="26" fill="#FFCBA4"/>
      <!-- tummy -->
      <ellipse cx="60" cy="86" rx="18" ry="16" fill="#FFE4C4"/>
      <!-- left chubby paw -->
      <ellipse cx="30" cy="96" rx="10" ry="8" fill="#FFB085"/>
      <!-- right chubby paw -->
      <ellipse cx="90" cy="96" rx="10" ry="8" fill="#FFB085"/>
      <!-- paw toes left -->
      <circle cx="25" cy="93" r="3" fill="#FFCBA4"/>
      <circle cx="30" cy="91" r="3" fill="#FFCBA4"/>
      <circle cx="35" cy="93" r="3" fill="#FFCBA4"/>
      <!-- paw toes right -->
      <circle cx="85" cy="93" r="3" fill="#FFCBA4"/>
      <circle cx="90" cy="91" r="3" fill="#FFCBA4"/>
      <circle cx="95" cy="93" r="3" fill="#FFCBA4"/>
      <!-- tail -->
      <ellipse cx="89" cy="84" rx="7" ry="5" fill="#FFD6B0" transform="rotate(20 89 84)"/>
      <!-- head -->
      <circle cx="60" cy="48" r="32" fill="#FFCBA4"/>
      <!-- left ear outer -->
      <ellipse cx="34" cy="22" rx="13" ry="11" fill="#FFCBA4"/>
      <!-- left ear inner -->
      <ellipse cx="34" cy="22" rx="8" ry="6.5" fill="#FF9EC0"/>
      <!-- right ear outer -->
      <ellipse cx="86" cy="22" rx="13" ry="11" fill="#FFCBA4"/>
      <!-- right ear inner -->
      <ellipse cx="86" cy="22" rx="8" ry="6.5" fill="#FF9EC0"/>
      <!-- cheek pouches (the classic chubster look) -->
      <ellipse cx="31" cy="54" rx="14" ry="12" fill="#FFB889"/>
      <ellipse cx="89" cy="54" rx="14" ry="12" fill="#FFB889"/>
      <!-- cheek blush -->
      <ellipse cx="31" cy="56" rx="9" ry="6" fill="#FF85B3" opacity="0.35"/>
      <ellipse cx="89" cy="56" rx="9" ry="6" fill="#FF85B3" opacity="0.35"/>
      <!-- eyes white -->
      <circle cx="47" cy="45" r="8.5" fill="white"/>
      <circle cx="73" cy="45" r="8.5" fill="white"/>
      <!-- pupils -->
      <circle cx="48.5" cy="45" r="5.5" fill="#2D1040"/>
      <circle cx="74.5" cy="45" r="5.5" fill="#2D1040"/>
      <!-- eye shine big -->
      <circle cx="50.5" cy="43" r="2" fill="white"/>
      <circle cx="76.5" cy="43" r="2" fill="white"/>
      <!-- eye shine small -->
      <circle cx="47.5" cy="47" r="1" fill="white" opacity="0.7"/>
      <circle cx="73.5" cy="47" r="1" fill="white" opacity="0.7"/>
      <!-- nose -->
      <ellipse cx="60" cy="57" rx="4.5" ry="3.5" fill="#FF85B3"/>
      <!-- nostrils -->
      <circle cx="58" cy="57" r="1.2" fill="#E06090" opacity="0.7"/>
      <circle cx="62" cy="57" r="1.2" fill="#E06090" opacity="0.7"/>
      <!-- happy mouth -->
      <path d="M52 63 Q60 70 68 63" stroke="#FF85B3" stroke-width="2.8" stroke-linecap="round" fill="none"/>
      <!-- tiny camera held in paws -->
      <rect x="46" y="100" width="28" height="20" rx="5" fill="#FF85B3"/>
      <rect x="46" y="100" width="28" height="20" rx="5" stroke="#E0609A" stroke-width="1.5" fill="none"/>
      <circle cx="60" cy="110" r="6.5" fill="white"/>
      <circle cx="60" cy="110" r="4" fill="#FFD6EA"/>
      <circle cx="60" cy="110" r="2" fill="#E0609A" opacity="0.6"/>
      <rect x="65" y="102" width="6" height="3.5" rx="1.5" fill="white"/>
      <!-- lens highlight -->
      <circle cx="58" cy="108" r="1.2" fill="white" opacity="0.8"/>
      <!-- star sparkles around hamster -->
      <path d="M18 36 L19.5 32 L21 36 L25 37.5 L21 39 L19.5 43 L18 39 L14 37.5Z" fill="#FFE066" opacity="0.9"/>
      <path d="M98 28 L99 25 L100 28 L103 29 L100 30 L99 33 L98 30 L95 29Z" fill="#FF85B3" opacity="0.9"/>
      <path d="M104 70 L105 67 L106 70 L109 71 L106 72 L105 75 L104 72 L101 71Z" fill="#C9A0FF" opacity="0.9"/>
    </svg>
  </div>

  <h1>HamCAM</h1>
  <p class="tagline">* Live Hamster Observatory * 24/7 *</p>

  <!-- SVG paw divider -->
  <div class="divider">
    <!-- paw print SVG -->
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
      <ellipse cx="11" cy="14" rx="6" ry="5" fill="#FFB3D1"/>
      <circle cx="6.5" cy="9" r="2.5" fill="#FFB3D1"/>
      <circle cx="11" cy="7.5" r="2.5" fill="#FFB3D1"/>
      <circle cx="15.5" cy="9" r="2.5" fill="#FFB3D1"/>
    </svg>
  </div>

  <?php if ($error): ?>
    <div class="error-msg">
      <svg width="16" height="16" viewBox="0 0 16 16"><circle cx="8" cy="8" r="7" fill="#ff6b6b" opacity="0.2"/><circle cx="8" cy="8" r="7" stroke="#ff6b6b" stroke-width="1.5" fill="none"/><path d="M8 5v4M8 11v.5" stroke="#ff6b6b" stroke-width="1.8" stroke-linecap="round"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <label for="password">
      <!-- key icon -->
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="6" cy="6.5" r="4" stroke="#b08abf" stroke-width="1.6" fill="none"/><path d="M9.5 9.5L14 14M9.5 12H12M10.5 13.5H12.5" stroke="#b08abf" stroke-width="1.6" stroke-linecap="round"/></svg>
      Secret Hamster Password
    </label>
    <div class="input-wrap">
      <input type="password" id="password" name="password"
             placeholder="Psst... enter password here" autofocus required>
      <button type="button" class="toggle-pw" onclick="togglePw()" title="Show/hide password">
        <!-- eye icon -->
        <svg id="eye-icon" viewBox="0 0 16 16" fill="none" stroke="#ff85b3" stroke-width="1.6" stroke-linecap="round">
          <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/>
          <circle cx="8" cy="8" r="2.2"/>
        </svg>
      </button>
    </div>
    <button type="submit" class="btn">
      Enter HamCAM World!
      <!-- arrow right -->
      <svg viewBox="0 0 22 22" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 11h14M13 6l5 5-5 5"/>
      </svg>
    </button>
  </form>

  <!-- Bouncing paw prints -->
  <div class="paw-row">
    <!-- paw x3 -->
    <svg viewBox="0 0 22 22" fill="#FFB3D1"><ellipse cx="11" cy="15" rx="6" ry="4.5"/><circle cx="6.5" cy="9.5" r="2.4"/><circle cx="11" cy="8" r="2.4"/><circle cx="15.5" cy="9.5" r="2.4"/></svg>
    <svg viewBox="0 0 22 22" fill="#FF85B3"><ellipse cx="11" cy="15" rx="6" ry="4.5"/><circle cx="6.5" cy="9.5" r="2.4"/><circle cx="11" cy="8" r="2.4"/><circle cx="15.5" cy="9.5" r="2.4"/></svg>
    <svg viewBox="0 0 22 22" fill="#FFB3D1"><ellipse cx="11" cy="15" rx="6" ry="4.5"/><circle cx="6.5" cy="9.5" r="2.4"/><circle cx="11" cy="8" r="2.4"/><circle cx="15.5" cy="9.5" r="2.4"/></svg>
  </div>

  <div class="footer">
    <!-- lock icon -->
    <svg viewBox="0 0 12 12" fill="none" stroke="#b08abf" stroke-width="1.4"><rect x="1.5" y="5.5" width="9" height="6" rx="1.5"/><path d="M3.5 5.5V3.5a2.5 2.5 0 015 0v2"/></svg>
    Secured &bull; Live 2K Feed &bull; Hamster Magic
    <svg viewBox="0 0 12 12" fill="none" stroke="#b08abf" stroke-width="1.4"><rect x="1.5" y="5.5" width="9" height="6" rx="1.5"/><path d="M3.5 5.5V3.5a2.5 2.5 0 015 0v2"/></svg>
  </div>
</div>

<script>
const colors = ['#ffb3d1','#ffe066','#b3f0e0','#d4b3ff','#ffcba4','#ff85b3','#ffd6ea'];

// Floating pastel bubbles
const bc = document.getElementById('bubbles');
for (let i = 0; i < 20; i++) {
  const b = document.createElement('div'); b.className = 'bubble';
  const sz = 18 + Math.random() * 55;
  b.style.cssText = `width:${sz}px;height:${sz}px;left:${Math.random()*100}%;bottom:-${sz}px;background:${colors[Math.floor(Math.random()*colors.length)]};animation-duration:${8+Math.random()*12}s;animation-delay:-${Math.random()*16}s;`;
  bc.appendChild(b);
}

// Sparkle dots
const sc = document.getElementById('sparkles');
for (let i = 0; i < 24; i++) {
  const s = document.createElement('div'); s.className = 'sparkle';
  const sz = 4 + Math.random() * 9;
  s.style.cssText = `left:${Math.random()*100}%;top:${Math.random()*100}%;width:${sz}px;height:${sz}px;background:${colors[Math.floor(Math.random()*colors.length)]};animation-duration:${1.4+Math.random()*3}s;animation-delay:-${Math.random()*5}s;`;
  sc.appendChild(s);
}

// Floating SVG decorations — flowers, stars, hearts (no emojis)
const floaterShapes = [
  // flower
  `<svg width="40" height="40" viewBox="0 0 40 40"><g transform="translate(20,20)"><circle cx="0" cy="0" r="5" fill="#ffe066"/>${[0,60,120,180,240,300].map(a=>`<ellipse cx="${Math.cos(a*Math.PI/180)*11}" cy="${Math.sin(a*Math.PI/180)*11}" rx="5" ry="7" fill="#ff85b3" transform="rotate(${a} ${Math.cos(a*Math.PI/180)*11} ${Math.sin(a*Math.PI/180)*11})"/>`).join('')}</g></svg>`,
  // 4-petal flower
  `<svg width="38" height="38" viewBox="0 0 38 38"><g transform="translate(19,19)"><circle cx="0" cy="0" r="5" fill="#ffe066"/>${[0,90,180,270].map(a=>`<ellipse cx="${Math.cos(a*Math.PI/180)*10}" cy="${Math.sin(a*Math.PI/180)*10}" rx="5" ry="8" fill="#ffb3d1" transform="rotate(${a} ${Math.cos(a*Math.PI/180)*10} ${Math.sin(a*Math.PI/180)*10})"/>`).join('')}</g></svg>`,
  // heart
  `<svg width="34" height="34" viewBox="0 0 34 34"><path d="M17 29 C8 22 3 17 3 12 C3 7 7 4 11 4 C14 4 16 6 17 8 C18 6 20 4 23 4 C27 4 31 7 31 12 C31 17 26 22 17 29Z" fill="#ff85b3"/></svg>`,
  // star
  `<svg width="36" height="36" viewBox="0 0 36 36"><path d="M18 4 L21 14 L32 14 L23 21 L26 31 L18 25 L10 31 L13 21 L4 14 L15 14Z" fill="#ffe066"/></svg>`,
  // small flower lavender
  `<svg width="30" height="30" viewBox="0 0 30 30"><g transform="translate(15,15)">${[0,72,144,216,288].map(a=>`<ellipse cx="${Math.cos(a*Math.PI/180)*8}" cy="${Math.sin(a*Math.PI/180)*8}" rx="4" ry="6" fill="#c9a0ff" transform="rotate(${a} ${Math.cos(a*Math.PI/180)*8} ${Math.sin(a*Math.PI/180)*8})"/>`).join('')}<circle cx="0" cy="0" r="4" fill="#ffe066"/></g></svg>`,
  // diamond
  `<svg width="28" height="28" viewBox="0 0 28 28"><path d="M14 3 L25 14 L14 25 L3 14Z" fill="#b3f0e0"/></svg>`,
];

const positions = [
  {top:'7%',left:'4%',dur:'4.2s'},{top:'11%',right:'6%',dur:'5.5s',delay:'-1s'},
  {top:'66%',left:'3%',dur:'4.8s',delay:'-2s'},{top:'72%',right:'4%',dur:'3.8s',delay:'-.5s'},
  {top:'38%',left:'1.5%',dur:'6s',delay:'-3s'},{top:'46%',right:'2%',dur:'5s',delay:'-1.5s'},
  {top:'87%',left:'22%',dur:'4.2s',delay:'-2.5s'},{top:'85%',right:'18%',dur:'5.8s',delay:'-.8s'},
];
const fc = document.getElementById('floaters');
positions.forEach((p, i) => {
  const d = document.createElement('div');
  d.className = 'floater';
  Object.assign(d.style, {
    top: p.top||'', left: p.left||'', right: p.right||'',
    animationDuration: p.dur, animationDelay: p.delay||'0s',
  });
  d.innerHTML = floaterShapes[i % floaterShapes.length];
  fc.appendChild(d);
});

// Show/hide password
let pwVisible = false;
function togglePw() {
  pwVisible = !pwVisible;
  document.getElementById('password').type = pwVisible ? 'text' : 'password';
}

// Click burst — pastel circles
document.addEventListener('click', e => {
  for (let i = 0; i < 8; i++) {
    const sp = document.createElement('div');
    const sz = 6 + Math.random() * 8;
    sp.style.cssText = `position:fixed;left:${e.clientX}px;top:${e.clientY}px;width:${sz}px;height:${sz}px;border-radius:50%;background:${colors[Math.floor(Math.random()*colors.length)]};pointer-events:none;z-index:9999;transition:all 0.65s ease-out;transform:translate(-50%,-50%);`;
    document.body.appendChild(sp);
    const angle = (i / 8) * Math.PI * 2;
    const dist = 40 + Math.random() * 50;
    setTimeout(() => {
      sp.style.left = (e.clientX + Math.cos(angle) * dist) + 'px';
      sp.style.top  = (e.clientY + Math.sin(angle) * dist) + 'px';
      sp.style.opacity = '0';
      sp.style.transform = 'translate(-50%,-50%) scale(0)';
    }, 10);
    setTimeout(() => sp.remove(), 750);
  }
});
</script>
</body>
</html><!-- V1.1 -->