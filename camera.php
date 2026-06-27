<?php
require_once 'config.php';
require_once 'setup_guard.php';
session_start();
if (empty($_SESSION['hamcam_auth']) || (time() - ($_SESSION['hamcam_time']??0)) > SESSION_TIMEOUT) {
    session_destroy(); header('Location: index.php'); exit;
}
$_SESSION['hamcam_time'] = time();

// Always use the proxied path — browser never needs to know the internal go2rtc IP
// go2rtc proxied through Apache at /go2rtc/
$use_go2rtc = !empty(HLS_URL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>HamCAM -- Live!</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.4.12/hls.min.js"></script>
<style>
  :root {
    --pink:     #ff85b3;
    --pink2:    #ffb3d1;
    --pink3:    #ffe0ee;
    --pink4:    #fff0f8;
    --yellow:   #ffe066;
    --orange:   #ffb347;
    --mint:     #7ee8c8;
    --mint2:    #e0fff7;
    --lavender: #c9a0ff;
    --lav2:     #f0e8ff;
    --coral:    #ff6b6b;
    --white:    #ffffff;
    --bg:       #fff5fb;
    --text:     #5a3060;
    --muted:    #b08abf;
    --border:   #ffd6ea;
    --radius:   18px;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    background: var(--bg);
    font-family: 'Nunito', sans-serif;
    color: var(--text);
    min-height: 100dvh;
    overflow-x: hidden;
    position: relative;
  }
  body::before {
    content: '';
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background:
      radial-gradient(ellipse 60% 40% at 10% 15%, rgba(255,181,215,0.2) 0%, transparent 70%),
      radial-gradient(ellipse 50% 40% at 90% 80%, rgba(201,160,255,0.18) 0%, transparent 70%),
      radial-gradient(ellipse 40% 40% at 50% 50%, rgba(126,232,200,0.1) 0%, transparent 70%);
  }

  /* -- Header -- */
  header {
    position: sticky; top: 0; z-index: 100;
    background: var(--white);
    border-bottom: 3px solid var(--pink3);
    padding: 10px 16px;
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    box-shadow: 0 4px 20px rgba(255,133,179,0.12);
  }
  .header-left { display: flex; align-items: center; gap: 10px; }
  .logo-ham {
    width: 38px; height: 38px; flex-shrink: 0;
    animation: logoBounce 2.2s ease-in-out infinite;
    filter: drop-shadow(0 3px 6px rgba(255,133,179,0.4));
  }
  @keyframes logoBounce {
    0%,100% { transform: translateY(0) rotate(-5deg); }
    50%      { transform: translateY(-6px) rotate(5deg); }
  }
  h1 {
    font-family: 'Fredoka One', cursive;
    font-size: 1.7rem; color: var(--pink);
    text-shadow: 2px 2px 0 var(--pink2);
    line-height: 1;
  }
  .live-pill {
    display: flex; align-items: center; gap: 5px;
    background: linear-gradient(135deg, var(--pink), var(--orange));
    color: white; border-radius: 20px;
    font-family: 'Fredoka One', cursive;
    font-size: 0.75rem; letter-spacing: 0.06em;
    padding: 4px 11px;
    box-shadow: 0 3px 10px rgba(255,133,179,0.4);
    animation: pillPulse 2s ease-in-out infinite;
  }
  @keyframes pillPulse {
    0%,100% { box-shadow: 0 3px 10px rgba(255,133,179,0.4); }
    50%      { box-shadow: 0 3px 22px rgba(255,133,179,0.75); }
  }
  .live-dot { width: 7px; height: 7px; border-radius: 50%; background: white; animation: dotBlink 1s infinite; }
  @keyframes dotBlink { 0%,100%{opacity:1;} 50%{opacity:0.3;} }
  .header-right { display: flex; align-items: center; gap: 6px; }
  .hbtn {
    background: var(--pink3); border: 2px solid var(--border);
    border-radius: 12px; width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.2s; text-decoration: none;
    color: var(--pink);
  }
  .hbtn:hover { background: var(--pink2); transform: scale(1.1) rotate(-5deg); }
  .hbtn svg { width: 18px; height: 18px; }

  /* -- Layout -- */
  .layout {
    position: relative; z-index: 1;
    display: grid; grid-template-columns: 1fr;
    gap: 14px; padding: 14px;
    max-width: 1400px; margin: 0 auto;
  }
  @media(min-width:900px){ .layout { grid-template-columns: 1fr 290px; } }

  /* -- Video Panel -- */
  .video-wrap {
    background: #1a0a20;
    border-radius: 22px;
    border: 3px solid var(--pink2);
    overflow: hidden; position: relative;
    aspect-ratio: 16/9;
    box-shadow: 0 8px 40px rgba(255,133,179,0.25), 0 0 0 6px var(--pink3);
  }
  #cam-video {
    width: 100%; height: 100%;
    object-fit: contain; display: block;
  }
  .video-topbar {
    position: absolute; top: 10px; left: 10px; right: 10px;
    display: flex; justify-content: space-between; pointer-events: none; z-index: 5;
  }
  .vbadge {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 10px;
    font-family: 'Fredoka One', cursive;
    font-size: 0.7rem; letter-spacing: 0.05em;
    color: white; padding: 4px 10px;
    display: flex; align-items: center; gap: 5px;
  }
  .vbadge.pink { background: rgba(255,133,179,0.7); border-color: rgba(255,133,179,0.5); }
  .vbadge svg { width: 12px; height: 12px; }
  .ham-corner {
    position: absolute; bottom: 8px; right: 10px; z-index: 5;
    pointer-events: none;
    animation: hamCorner 3s ease-in-out infinite;
    filter: drop-shadow(0 2px 5px rgba(0,0,0,0.4));
  }
  .ham-corner svg { width: 42px; height: 42px; }
  @keyframes hamCorner {
    0%,100% { transform: rotate(-5deg) scale(1); }
    50%      { transform: rotate(5deg) scale(1.15); }
  }
  .stream-error {
    display: none; position: absolute; inset: 0;
    background: linear-gradient(135deg, #fff0f8, #f0e8ff);
    align-items: center; justify-content: center;
    flex-direction: column; gap: 14px; text-align: center;
  }
  .stream-error.show { display: flex; }
  .stream-error svg { width: 70px; height: 70px; animation: heroWiggle 2s infinite; }
  @keyframes heroWiggle { 0%,100%{transform:rotate(-8deg);}50%{transform:rotate(8deg);} }
  .stream-error p { font-family:'Fredoka One',cursive; font-size:1.1rem; color:var(--text); }
  .stream-error small { color: var(--muted); font-size: 0.78rem; }

  /* Quick actions */
  .quick-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
  .qbtn {
    background: var(--white); border: 2.5px solid var(--border);
    border-radius: 14px; color: var(--text);
    font-family: 'Nunito', sans-serif; font-weight: 800;
    font-size: 0.78rem; padding: 8px 14px;
    cursor: pointer; display: flex; align-items: center; gap: 6px;
    transition: all 0.18s; white-space: nowrap;
    -webkit-tap-highlight-color: transparent;
  }
  .qbtn svg { width: 15px; height: 15px; flex-shrink: 0; }
  .qbtn:hover { background: var(--pink3); border-color: var(--pink); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255,133,179,0.25); }
  .qbtn:active { transform: translateY(1px); }
  .qbtn.primary {
    background: linear-gradient(135deg,var(--pink),var(--orange));
    color: white; border-color: transparent;
    box-shadow: 0 3px 0 #cc5c87;
  }
  .qbtn.primary:hover { opacity: 0.92; }

  /* -- Side panel -- */
  .side { display: flex; flex-direction: column; gap: 12px; margin-top: 14px; }
  @media(min-width:900px){ .side { margin-top: 0; } }
  .pcard {
    background: var(--white);
    border: 2.5px solid var(--border); border-radius: var(--radius);
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(255,133,179,0.07);
    transition: box-shadow 0.2s;
  }
  .pcard:hover { box-shadow: 0 6px 28px rgba(255,133,179,0.14); }
  .pcard-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 14px; cursor: pointer;
    user-select: none; -webkit-user-select: none;
    transition: background 0.2s;
  }
  .pcard-header:hover { background: var(--pink3); }
  .pcard-header-title {
    display: flex; align-items: center; gap: 8px;
    font-family: 'Fredoka One', cursive; font-size: 0.95rem; color: var(--text);
  }
  .pcard-header-title svg { width: 18px; height: 18px; }
  .chevron {
    width: 16px; height: 16px; color: var(--muted);
    transition: transform 0.25s; flex-shrink: 0;
  }
  .pcard-header.open .chevron { transform: rotate(180deg); }
  .pcard-body { padding: 14px; display: none; }
  .pcard-body.open { display: block; }

  /* -- PTZ -- */
  .ptz-label {
    font-family: 'Fredoka One', cursive; font-size: 0.82rem; color: var(--muted);
    text-align: center; margin-bottom: 8px;
  }
  .ptz-grid {
    display: grid; grid-template-columns: repeat(3,1fr);
    gap: 6px; max-width: 190px; margin: 0 auto 14px;
  }
  .ptz-btn {
    background: var(--pink3); border: 2px solid var(--border);
    border-radius: 12px; aspect-ratio: 1;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.15s;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation; user-select: none;
  }
  .ptz-btn svg { width: 22px; height: 22px; }
  .ptz-btn:hover  { background: var(--pink2); border-color: var(--pink); transform: scale(1.1); }
  .ptz-btn:active { transform: scale(0.9); background: var(--pink); }
  .ptz-btn.center { background: var(--lav2); border-color: var(--lavender); cursor: default; }
  .ptz-btn.center:hover { transform: none; background: var(--lav2); }
  .ptz-zoom { display: flex; gap: 6px; max-width: 190px; margin: 0 auto; }
  .ptz-zoom .ptz-btn { flex: 1; aspect-ratio: unset; padding: 10px; gap: 5px; font-family:'Fredoka One',cursive; font-size:0.88rem; color:var(--text); }
  .ptz-speed { margin-top: 14px; }
  .ptz-speed-label {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 0.75rem; font-weight: 800; color: var(--muted); margin-bottom: 7px;
  }
  .ptz-speed-label svg { width: 16px; height: 16px; }
  input[type=range] {
    -webkit-appearance: none; width: 100%; height: 5px;
    background: var(--border); border-radius: 3px; outline: none;
  }
  input[type=range]::-webkit-slider-thumb {
    -webkit-appearance: none; width: 19px; height: 19px; border-radius: 50%;
    background: linear-gradient(135deg, var(--pink), var(--orange));
    cursor: pointer; box-shadow: 0 2px 6px rgba(255,133,179,0.5);
  }

  /* -- Settings -- */
  .setting-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 0; border-bottom: 1.5px solid var(--pink3); gap: 8px;
  }
  .setting-row:last-child { border-bottom: none; }
  .setting-label { font-size: 0.84rem; font-weight: 800; color: var(--text); }
  .setting-label small { display: block; font-size: 0.68rem; font-weight: 700; color: var(--muted); margin-top: 1px; }
  .group-title {
    font-family: 'Fredoka One', cursive; font-size: 0.75rem; color: var(--pink);
    letter-spacing: 0.06em; text-transform: uppercase;
    margin: 4px 0 8px; display: flex; align-items: center; gap: 5px;
  }
  .group-title svg { width: 14px; height: 14px; }
  .tog { position: relative; width: 42px; height: 24px; flex-shrink: 0; }
  .tog input { display: none; }
  .tog-track {
    position: absolute; inset: 0; background: var(--border);
    border-radius: 12px; cursor: pointer; transition: background 0.25s;
  }
  .tog input:checked + .tog-track { background: linear-gradient(135deg, var(--pink), var(--orange)); }
  .tog-track::after {
    content: ''; position: absolute; left: 3px; top: 3px;
    width: 18px; height: 18px; border-radius: 50%; background: white;
    transition: transform 0.25s; box-shadow: 0 1px 4px rgba(0,0,0,0.2);
  }
  .tog input:checked + .tog-track::after { transform: translateX(18px); }
  select {
    background: var(--pink3); border: 2px solid var(--border);
    border-radius: 10px; color: var(--text);
    font-family: 'Nunito', sans-serif; font-weight: 800;
    font-size: 0.75rem; padding: 5px 8px; outline: none; cursor: pointer;
  }

  /* -- Status -- */
  .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .stat-box {
    background: var(--pink3); border: 2px solid var(--border);
    border-radius: 12px; padding: 10px 12px; text-align: center;
  }
  .stat-lbl { font-size: 0.62rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
  .stat-val { font-family: 'Fredoka One', cursive; font-size: 1rem; color: var(--pink); }

  /* -- Toast -- */
  .toast {
    position: fixed; bottom: 20px; left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: white; border: 2.5px solid var(--pink2);
    border-radius: 14px; padding: 10px 20px;
    font-weight: 800; font-size: 0.82rem; color: var(--text);
    z-index: 999; transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s;
    opacity: 0; pointer-events: none; white-space: nowrap;
    box-shadow: 0 8px 24px rgba(255,133,179,0.3);
    display: flex; align-items: center; gap: 7px;
  }
  .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
  .toast svg { width: 16px; height: 16px; flex-shrink: 0; }

  .info-note {
    background: linear-gradient(135deg, var(--mint2), var(--lav2));
    border: 2px solid var(--mint);
    border-radius: 12px; padding: 10px 14px;
    font-size: 0.78rem; font-weight: 800; color: var(--text);
    margin-bottom: 10px; display: flex; align-items: flex-start; gap: 8px;
  }
  .info-note svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; }

  .side-deco {
    position: fixed; pointer-events: none; z-index: 0;
    animation: floatDrift ease-in-out infinite alternate; opacity: 0.45;
  }
  .side-deco svg { width: 30px; height: 30px; }
  @keyframes floatDrift {
    from { transform: translateY(0) rotate(-6deg); }
    to   { transform: translateY(-16px) rotate(6deg); }
  }

  /* -- Motion panel -- */
  .motion-event {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 8px 0; border-bottom: 1.5px solid var(--pink3);
  }
  .motion-event:last-child { border-bottom: none; }
  .motion-thumb {
    width: 72px; height: 48px; border-radius: 8px;
    object-fit: cover; flex-shrink: 0; cursor: pointer;
    border: 2px solid var(--border);
    background: #1a0a20;
    transition: border-color .2s;
  }
  .motion-thumb:hover { border-color: var(--pink); }
  .motion-thumb-placeholder {
    width: 72px; height: 48px; border-radius: 8px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--pink3), var(--lav2));
    display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--border);
  }
  .motion-thumb-placeholder svg { width: 22px; height: 22px; opacity: .5; }
  .motion-info { flex: 1; min-width: 0; }
  .motion-time {
    font-family: 'Fredoka One', cursive; font-size: .82rem;
    color: var(--text); margin-bottom: 2px;
  }
  .motion-score { font-size: .68rem; font-weight: 800; color: var(--muted); }
  .motion-score span { color: var(--pink); }
  .motion-window-badge {
    display: inline-flex; align-items: center; gap: 5px;
    border-radius: 8px; font-size: .68rem; font-weight: 800;
    padding: 3px 8px; margin-bottom: 8px;
  }
  .motion-window-badge.active { background: var(--mint2); color: #2a7a5a; border: 1.5px solid var(--mint); }
  .motion-window-badge.inactive { background: var(--pink3); color: var(--muted); border: 1.5px solid var(--border); }
  .motion-window-dot { width: 6px; height: 6px; border-radius: 50%; }
  .motion-window-badge.active .motion-window-dot { background: var(--mint); animation: dotBlink 2s infinite; }
  .motion-window-badge.inactive .motion-window-dot { background: var(--muted); }
  .motion-stats-mini {
    display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 10px;
  }
  .motion-stat-box {
    background: var(--pink3); border: 2px solid var(--border);
    border-radius: 10px; padding: 7px 10px; text-align: center;
  }
  .motion-stat-box .n { font-family: 'Fredoka One', cursive; font-size: .95rem; color: var(--pink); }
  .motion-stat-box .l { font-size: .6rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
  .motion-empty { text-align: center; padding: 20px 0; color: var(--muted); font-weight: 800; font-size: .82rem; }
  .motion-empty svg { width: 40px; height: 40px; margin: 0 auto 8px; display: block; opacity: .4; }

  /* Lightbox (reused for motion thumbs) */
  .mlightbox {
    display: none; position: fixed; inset: 0;
    background: rgba(26,10,32,.92); z-index: 999;
    align-items: center; justify-content: center; padding: 20px;
  }
  .mlightbox.show { display: flex; flex-direction: column; gap: 10px; }
  .mlightbox img { max-width: 90vw; max-height: 80vh; border-radius: 12px;
    border: 3px solid var(--pink2); box-shadow: 0 20px 60px rgba(0,0,0,.6); }
  .mlightbox-info { background: rgba(255,255,255,.95); border-radius: 10px;
    padding: 8px 16px; font-weight: 800; font-size: .82rem; color: var(--text); }
  .mlightbox-close { position: absolute; top: 14px; right: 14px;
    background: var(--pink); border: none; border-radius: 50%;
    width: 38px; height: 38px; cursor: pointer; color: white; font-size: 18px;
    display: flex; align-items: center; justify-content: center; }

  /* -- Motion Panel -- */
  .motion-window-badge {
    display: inline-flex; align-items: center; gap: 5px;
    border-radius: 8px; font-size: .68rem; font-weight: 800;
    padding: 3px 8px; margin-bottom: 8px;
    border: 1.5px solid var(--mint);
    background: var(--mint2); color: #2a7a5a;
  }
  .motion-window-badge.inactive {
    background: var(--pink3); color: var(--muted);
    border-color: var(--border);
  }
  .motion-window-dot {
    width: 6px; height: 6px; border-radius: 50%; background: var(--mint);
    animation: dotBlink 2s ease-in-out infinite;
  }
  .motion-window-badge.inactive .motion-window-dot {
    background: var(--muted); animation: none;
  }
  .motion-stats-mini {
    display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 10px;
  }
  .motion-stat-box {
    background: var(--pink3); border: 2px solid var(--border);
    border-radius: 10px; padding: 7px 10px; text-align: center;
  }
  .motion-stat-box .msn { font-family: 'Fredoka One', cursive; font-size: .95rem; color: var(--pink); }
  .motion-stat-box .msl { font-size: .6rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
  .motion-event {
    display: flex; gap: 8px; align-items: flex-start;
    padding: 7px 0; border-bottom: 1.5px solid var(--pink3);
  }
  .motion-event:last-child { border-bottom: none; }
  .motion-thumb {
    width: 70px; height: 46px; border-radius: 7px;
    object-fit: cover; flex-shrink: 0; cursor: pointer;
    border: 2px solid var(--border); background: #1a0a20;
    transition: border-color .2s, transform .15s;
  }
  .motion-thumb:hover { border-color: var(--pink); transform: scale(1.05); }
  .motion-thumb-placeholder {
    width: 70px; height: 46px; border-radius: 7px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--pink3), var(--lav2));
    display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--border);
  }
  .motion-thumb-placeholder svg { width: 20px; height: 20px; opacity: .45; }
  .motion-info { flex: 1; min-width: 0; }
  .motion-time { font-family: 'Fredoka One', cursive; font-size: .78rem; color: var(--text); margin-bottom: 1px; }
  .motion-score { font-size: .65rem; font-weight: 800; color: var(--muted); }
  .motion-score span { color: var(--pink); }
  .motion-empty {
    text-align: center; padding: 18px 0;
    color: var(--muted); font-weight: 800; font-size: .8rem;
  }
  .motion-empty svg { width: 36px; height: 36px; margin: 0 auto 6px; display: block; opacity: .35; }

  /* Motion snapshot lightbox */
  .mlb {
    display: none; position: fixed; inset: 0;
    background: rgba(26,10,32,.93); z-index: 9999;
    align-items: center; justify-content: center;
    flex-direction: column; gap: 10px; padding: 20px;
  }
  .mlb.show { display: flex; }
  .mlb img { max-width: 92vw; max-height: 78vh; border-radius: 12px;
    border: 3px solid var(--pink2); box-shadow: 0 20px 60px rgba(0,0,0,.7); }
  .mlb-info { background: rgba(255,255,255,.95); border-radius: 10px;
    padding: 7px 16px; font-weight: 800; font-size: .8rem; color: var(--text); }
  .mlb-close { position: absolute; top: 14px; right: 14px;
    background: var(--pink); border: none; border-radius: 50%;
    width: 36px; height: 36px; cursor: pointer; color: white; font-size: 17px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 3px 10px rgba(255,133,179,.5); }
  .mlb-close:hover { background: var(--orange); }
</style>
</head>
<body>

<div class="side-deco" style="top:18%;left:3px;animation-duration:4s">
  <svg viewBox="0 0 30 30"><path d="M15 4 L17 11 L25 11 L19 16 L21 23 L15 19 L9 23 L11 16 L5 11 L13 11Z" fill="#ffe066"/></svg>
</div>
<div class="side-deco" style="top:45%;right:3px;animation-duration:5.2s;animation-delay:-2s">
  <svg viewBox="0 0 30 30"><path d="M15 27 C7 21 3 16 3 11 C3 7 6.5 4 10 4 C12.5 4 14 6 15 8 C16 6 17.5 4 20 4 C23.5 4 27 7 27 11 C27 16 23 21 15 27Z" fill="#ff85b3"/></svg>
</div>
<div class="side-deco" style="bottom:25%;left:3px;animation-duration:4.5s;animation-delay:-1s">
  <svg viewBox="0 0 30 30"><circle cx="15" cy="15" r="4" fill="#ffe066"/><ellipse cx="15" cy="6" rx="4" ry="5.5" fill="#ffb3d1"/><ellipse cx="15" cy="24" rx="4" ry="5.5" fill="#ffb3d1"/><ellipse cx="6" cy="15" rx="5.5" ry="4" fill="#ffb3d1"/><ellipse cx="24" cy="15" rx="5.5" ry="4" fill="#ffb3d1"/></svg>
</div>
<div class="side-deco" style="bottom:12%;right:3px;animation-duration:3.8s;animation-delay:-.5s">
  <svg viewBox="0 0 30 30"><ellipse cx="15" cy="17" rx="8" ry="6" fill="#c9a0ff"/><circle cx="9" cy="12" r="3.2" fill="#c9a0ff"/><circle cx="15" cy="10" r="3.2" fill="#c9a0ff"/><circle cx="21" cy="12" r="3.2" fill="#c9a0ff"/></svg>
</div>

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
    <h1>HamCAM</h1>
    <div class="live-pill"><span class="live-dot"></span>LIVE 2K</div>
  </div>
  <div class="header-right">
<button class="hbtn" onclick="toggleFullscreen()" title="Fullscreen">
      <svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M2 6V2h4M12 2h4v4M16 12v4h-4M6 16H2v-4"/></svg>
    </button>
    <button class="hbtn" onclick="refreshStream()" title="Refresh">
      <svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3v4h-4"/><path d="M2 15v-4h4"/><path d="M15.5 10a7 7 0 1 1-1.3-5.5L16 7"/></svg>
    </button>
    <a class="hbtn" href="logout.php" title="Logout">
      <svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3H3a1 1 0 00-1 1v10a1 1 0 001 1h4M12 13l4-4-4-4M16 9H7"/></svg>
    </a>
  </div>
</header>

<div class="layout">
  <div>
    <?php if (empty(HLS_URL)): ?>
    <div class="info-note">
      <svg viewBox="0 0 18 18" fill="none" stroke="#7ee8c8" stroke-width="1.8" stroke-linecap="round"><circle cx="9" cy="9" r="7"/><path d="M9 8v5M9 6v.5"/></svg>
      <span><strong>Snapshot mode active.</strong> Enable go2rtc and set HLS_URL in config.php for real-time 2K streaming!</span>
    </div>
    <?php endif; ?>

    <div class="video-wrap" id="video-panel">
      <div class="video-topbar">
        <div style="display:flex;gap:6px;">
          <span class="vbadge pink" id="res-badge">
            <svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="1.4"><rect x="1" y="2" width="10" height="8" rx="1.5"/><path d="M4 2V1M8 2V1"/></svg>
            2K
          </span>
          <span class="vbadge" id="time-badge"></span>
        </div>
        <span class="vbadge" id="fps-badge">HLS</span>
      </div>

      <div class="ham-corner">
        <svg viewBox="0 0 42 42" fill="none">
          <circle cx="21" cy="18" r="13" fill="#FFCBA4"/>
          <ellipse cx="12" cy="20" rx="5.5" ry="4.5" fill="#FFB085"/>
          <ellipse cx="30" cy="20" rx="5.5" ry="4.5" fill="#FFB085"/>
          <ellipse cx="12" cy="9" rx="4.5" ry="4" fill="#FFCBA4"/>
          <ellipse cx="12" cy="9" rx="3" ry="2" fill="#FF9EC0"/>
          <ellipse cx="30" cy="9" rx="4.5" ry="4" fill="#FFCBA4"/>
          <ellipse cx="30" cy="9" rx="3" ry="2" fill="#FF9EC0"/>
          <circle cx="16.5" cy="17" r="3.5" fill="white"/>
          <circle cx="25.5" cy="17" r="3.5" fill="white"/>
          <circle cx="17" cy="17" r="2.2" fill="#2D1040"/>
          <circle cx="26" cy="17" r="2.2" fill="#2D1040"/>
          <circle cx="17.8" cy="16.2" r="0.8" fill="white"/>
          <circle cx="26.8" cy="16.2" r="0.8" fill="white"/>
          <ellipse cx="21" cy="22" rx="2" ry="1.5" fill="#FF85B3"/>
          <path d="M17.5 26 Q21 29.5 24.5 26" stroke="#FF85B3" stroke-width="1.6" stroke-linecap="round" fill="none"/>
          <ellipse cx="21" cy="33" rx="11" ry="9" fill="#FFCBA4"/>
        </svg>
      </div>

      <div class="stream-error" id="stream-error">
        <svg viewBox="0 0 70 70" fill="none">
          <circle cx="35" cy="30" r="22" fill="#FFCBA4"/>
          <ellipse cx="20" cy="33" rx="8" ry="7" fill="#FFB085"/>
          <ellipse cx="50" cy="33" rx="8" ry="7" fill="#FFB085"/>
          <circle cx="28" cy="28" r="5" fill="white"/><circle cx="42" cy="28" r="5" fill="white"/>
          <circle cx="28.5" cy="28" r="3" fill="#2D1040"/><circle cx="42.5" cy="28" r="3" fill="#2D1040"/>
          <ellipse cx="35" cy="37" rx="3" ry="2.5" fill="#FF85B3"/>
          <path d="M30 45 Q35 41 40 45" stroke="#FF85B3" stroke-width="2" stroke-linecap="round" fill="none"/>
          <ellipse cx="26" cy="35" rx="2" ry="3" fill="#aad4ff" opacity="0.7"/>
        </svg>
        <p>Stream unavailable!</p>
        <small>Check camera connection or enable go2rtc</small>
        <button class="qbtn primary" onclick="refreshStream()" style="margin-top:8px;">
          <svg viewBox="0 0 16 16" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v3h-3"/><path d="M2 13v-3h3"/><path d="M13.5 9a6 6 0 1 1-1.1-4.7L14 6"/></svg>
          Retry
        </button>
      </div>

      <?php if (!empty(HLS_URL)): ?>
      <iframe id="cam-frame"
        src="/go2rtc/stream.html?src=cam&video=mp4&audio=mp4"
        style="width:100%;height:100%;border:none;display:block;background:#000;"
        allowfullscreen
        allow="autoplay; fullscreen"
        onload="document.getElementById('fps-badge').textContent='LIVE'"
        onerror="handleStreamError()">
      </iframe>
      <?php else: ?>
      <div id="snapshot-wrap" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
        <img id="snapshot-img" src="snapshot.php?t=<?=time()?>"
             style="max-width:100%;max-height:100%;object-fit:contain;"
             onerror="handleStreamError()" alt="HamCAM Live Feed">
      </div>
      <?php endif; ?>
    </div>

    <div class="quick-actions">
      <button class="qbtn primary" onclick="toggleStream()" id="pause-btn">
        <svg viewBox="0 0 16 16" fill="white"><rect x="3" y="2" width="4" height="12" rx="1"/><rect x="9" y="2" width="4" height="12" rx="1"/></svg>
        Pause
      </button>
      <button class="qbtn" onclick="setQuality('HD')">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="1" y="3" width="14" height="10" rx="1.5"/><path d="M5 7v2M7 7v2M9 7h2.5a1 1 0 000-2H9v4"/></svg>
        2K HD
      </button>
      <button class="qbtn" onclick="setQuality('SD')">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="1" y="3" width="14" height="10" rx="1.5"/><path d="M5 9.5C5 9.5 5 10 6 10s1.5-.5 1.5-1-1.5-1-1.5-1.5S6 6 7 6s1 .5 1 .5M10 6v4h2"/></svg>
        SD
      </button>
      <button class="qbtn" onclick="captureSnapshot()">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="1" y="4" width="14" height="10" rx="1.5"/><circle cx="8" cy="9" r="2.5"/><path d="M5 4l1-2h4l1 2"/></svg>
        Snap
      </button>

    </div>
  </div>

  <div class="side">
    <div class="pcard">
      <div class="pcard-header open" onclick="togglePanel(this)">
        <div class="pcard-header-title">
          <svg viewBox="0 0 18 18" fill="none" stroke="var(--pink)" stroke-width="1.7" stroke-linecap="round"><circle cx="9" cy="9" r="3"/><path d="M9 2v2M9 14v2M2 9h2M14 9h2M4.2 4.2l1.4 1.4M12.4 12.4l1.4 1.4M12.4 4.2l-1.4 1.4M4.2 12.4l1.4 1.4"/></svg>
          PTZ Controls
        </div>
        <svg class="chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6l4 4 4-4"/></svg>
      </div>
      <div class="pcard-body open">
        <div class="ptz-label">Pan &amp; Tilt</div>
        <div class="ptz-grid">
          <button class="ptz-btn" onclick="ptzMove('up-left')">
            <svg viewBox="0 0 22 22" fill="none" stroke="var(--pink)" stroke-width="2.2" stroke-linecap="round"><path d="M6 16 L6 6 L16 6M6 6 l10 10"/></svg>
          </button>
          <button class="ptz-btn" onclick="ptzMove('up')">
            <svg viewBox="0 0 22 22" fill="none" stroke="var(--pink)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 16V6M7 10l4-4 4 4"/></svg>
          </button>
          <button class="ptz-btn" onclick="ptzMove('up-right')">
            <svg viewBox="0 0 22 22" fill="none" stroke="var(--pink)" stroke-width="2.2" stroke-linecap="round"><path d="M16 16 L16 6 L6 6M16 6 l-10 10"/></svg>
          </button>
          <button class="ptz-btn" onclick="ptzMove('left')">
            <svg viewBox="0 0 22 22" fill="none" stroke="var(--pink)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 11H6M10 7l-4 4 4 4"/></svg>
          </button>
          <button class="ptz-btn center">
            <svg viewBox="0 0 22 22" fill="none">
              <circle cx="11" cy="11" r="7" fill="#FFCBA4"/>
              <circle cx="8.5" cy="10" r="2" fill="white"/><circle cx="13.5" cy="10" r="2" fill="white"/>
              <circle cx="8.8" cy="10" r="1.2" fill="#2D1040"/><circle cx="13.8" cy="10" r="1.2" fill="#2D1040"/>
              <ellipse cx="11" cy="13" rx="1.5" ry="1.1" fill="#FF85B3"/>
              <path d="M9 15 Q11 17 13 15" stroke="#FF85B3" stroke-width="1.2" stroke-linecap="round" fill="none"/>
            </svg>
          </button>
          <button class="ptz-btn" onclick="ptzMove('right')">
            <svg viewBox="0 0 22 22" fill="none" stroke="var(--pink)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 11h10M12 7l4 4-4 4"/></svg>
          </button>
          <button class="ptz-btn" onclick="ptzMove('down-left')">
            <svg viewBox="0 0 22 22" fill="none" stroke="var(--pink)" stroke-width="2.2" stroke-linecap="round"><path d="M6 6 L6 16 L16 16M6 16 l10-10"/></svg>
          </button>
          <button class="ptz-btn" onclick="ptzMove('down')">
            <svg viewBox="0 0 22 22" fill="none" stroke="var(--pink)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 6v10M7 12l4 4 4-4"/></svg>
          </button>
          <button class="ptz-btn" onclick="ptzMove('down-right')">
            <svg viewBox="0 0 22 22" fill="none" stroke="var(--pink)" stroke-width="2.2" stroke-linecap="round"><path d="M16 6 L16 16 L6 16M16 16 l-10-10"/></svg>
          </button>
        </div>
        <div class="ptz-zoom" style="margin-top:12px;">
          <button class="ptz-btn" onclick="ptzHome()" style="flex:1;aspect-ratio:unset;padding:10px;gap:5px;font-family:'Fredoka One',cursive;font-size:0.88rem;color:var(--text);">
            <svg viewBox="0 0 18 18" fill="none" stroke="var(--pink)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l6-6 6 6M5 8v7h3v-4h4v4h3V8"/></svg>
            Home
          </button>
        </div>
        <div class="ptz-speed">
          <div class="ptz-speed-label">
            <div style="display:flex;align-items:center;gap:4px;">
              <svg viewBox="0 0 16 16" fill="none" stroke="var(--muted)" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v4l2.5 2"/></svg>
              Speed
            </div>
            <span id="speed-val">5</span>
            <svg viewBox="0 0 16 16" fill="none" stroke="var(--pink)" stroke-width="1.5" stroke-linecap="round"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
          </div>
          <input type="range" min="1" max="10" value="5" id="ptz-speed"
                 oninput="document.getElementById('speed-val').textContent=this.value">
        </div>
      </div>
    </div>

    <div class="pcard">
      <div class="pcard-header" onclick="togglePanel(this)">
        <div class="pcard-header-title">
          <svg viewBox="0 0 18 18" fill="none" stroke="var(--pink)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="2.5"/><path d="M9 1v2M9 15v2M1 9h2M15 9h2M3.2 3.2l1.4 1.4M13.4 13.4l1.4 1.4M13.4 3.2l-1.4 1.4M3.2 13.4l1.4 1.4"/></svg>
          Settings
        </div>
        <svg class="chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6l4 4 4-4"/></svg>
      </div>
      <div class="pcard-body">


        <div class="group-title">
          <svg viewBox="0 0 14 14" fill="none" stroke="var(--pink)" stroke-width="1.5" stroke-linecap="round"><rect x="1" y="3" width="12" height="8" rx="1.5"/><path d="M4 3V2M10 3V2"/></svg>
          Stream
        </div>
        <div class="setting-row">
          <div class="setting-label">Quality</div>
          <select onchange="setQuality(this.value)">
            <option value="HD">2K Main</option>
            <option value="SD">Sub SD</option>
          </select>
        </div>
      </div>
    </div>

    <div class="pcard">
      <div class="pcard-header" onclick="togglePanel(this)">
        <div class="pcard-header-title">
          <svg viewBox="0 0 18 18" fill="none" stroke="var(--pink)" stroke-width="1.7" stroke-linecap="round"><path d="M4 14V10M8 14V6M12 14V4M16 14V8"/></svg>
          Status
        </div>
        <svg class="chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6l4 4 4-4"/></svg>
      </div>
      <div class="pcard-body">
        <div class="stat-grid">
          <div class="stat-box"><div class="stat-lbl">Camera</div><div class="stat-val" id="s-cam">Online</div></div>
          <div class="stat-box"><div class="stat-lbl">Resolution</div><div class="stat-val" id="s-res">2K</div></div>
          <div class="stat-box"><div class="stat-lbl">Uptime</div><div class="stat-val" id="s-uptime">0:00</div></div>
          <div class="stat-box"><div class="stat-lbl">IP</div><div class="stat-val" style="font-size:.7rem;"><?= CAMERA_IP ?></div></div>
        </div>
        <div style="margin-top:10px;">
          <button class="qbtn" style="width:100%;justify-content:center;" onclick="checkStatus()">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v3h-3"/><path d="M2 13v-3h3"/><path d="M13.5 9a6 6 0 1 1-1.1-4.7L14 6"/></svg>
            Refresh Status
          </button>
        </div>
      </div>
    </div>

    <div class="pcard">
      <div class="pcard-header" onclick="togglePanel(this)">
        <div class="pcard-header-title">
          <svg viewBox="0 0 18 18" fill="none" stroke="var(--pink)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="3"/><path d="M9 1v2M9 15v2M1 9h2M15 9h2M3.2 3.2l1.4 1.4M13.4 13.4l1.4 1.4M13.4 3.2l-1.4 1.4M3.2 13.4l1.4 1.4"/></svg>
          ONVIF / Advanced
        </div>
        <svg class="chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6l4 4 4-4"/></svg>
      </div>
      <div class="pcard-body">
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
          <button class="qbtn" onclick="sendOnvif('reboot')">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3v3h-3"/><path d="M12 9a5 5 0 1 1-1-3.5L13 6"/></svg>
            Reboot Cam
          </button>
          <button class="qbtn" onclick="sendOnvif('preset_save')">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 14H3a1 1 0 01-1-1V3a1 1 0 011-1h8l2 2v9a1 1 0 01-1 1z"/><path d="M5 14V9h6v5M5 2v3h4"/></svg>
            Save Preset
          </button>
          <button class="qbtn" onclick="sendOnvif('preset_load')">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="8" cy="8" r="3"/><path d="M8 2v1M8 13v1M2 8h1M13 8h1"/></svg>
            Load Preset
          </button>
        </div>
        <div style="margin-top:10px;font-size:0.7rem;font-weight:800;color:var(--muted);line-height:1.9;">
          ONVIF: <?= ONVIF_HOST ?>:<?= ONVIF_PORT ?><br>
          RTSP: <?= CAMERA_IP ?>:<?= CAMERA_RTSP_PORT ?>
        </div>
      </div>
    </div>
    <!-- Motion Log Panel -->
    <div class="pcard">
      <div class="pcard-header open" onclick="togglePanel(this)">
        <div class="pcard-header-title">
          <svg viewBox="0 0 18 18" fill="none" stroke="var(--pink)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 3a6 6 0 100 12A6 6 0 009 3z"/>
            <path d="M9 6v3.5l2.5 1.5"/>
          </svg>
          Motion Log
        </div>
        <svg class="chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6l4 4 4-4"/></svg>
      </div>
      <div class="pcard-body open">
        <!-- Active window indicator -->
        <div class="motion-window-badge inactive" id="mp-badge">
          <span class="motion-window-dot"></span>
          <span id="mp-badge-label">Checking...</span>
        </div>
        <!-- Mini stats -->
        <div class="motion-stats-mini">
          <div class="motion-stat-box">
            <div class="msn" id="mp-today">--</div>
            <div class="msl">Today</div>
          </div>
          <div class="motion-stat-box">
            <div class="msn" id="mp-total">--</div>
            <div class="msl">All Time</div>
          </div>
        </div>
        <!-- Recent events -->
        <div id="mp-list"><div class="motion-empty">Loading...</div></div>
        <!-- View full log -->
        <a href="motion_log.php" style="display:block;margin-top:10px;">
          <button class="qbtn" style="width:100%;justify-content:center;">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="1" y="2" width="14" height="12" rx="1.5"/><path d="M4 6h8M4 9h6M4 12h4"/></svg>
            View Full Log
          </button>
        </a>
      </div>
    </div>

  </div>
</div>

<!-- Motion snapshot lightbox -->
<div class="mlb" id="mlb" onclick="mlbClose(event)">
  <button class="mlb-close" onclick="mlbClose()">&#x2715;</button>
  <img id="mlb-img" src="" alt="Motion snapshot">
  <div class="mlb-info" id="mlb-info"></div>
</div>

<div class="toast" id="toast">
  <svg viewBox="0 0 16 16" fill="none" stroke="var(--pink)" stroke-width="1.8" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 6v4M8 11v.5"/></svg>
  <span id="toast-msg"></span>
</div>

<script>
// ============================================================
// HamCAM camera.php - Main JavaScript
// Version: 2.1
// ============================================================

var CFG = {
  snapUrl: 'snapshot.php'
};

// Clock
function clock() {
  var el = document.getElementById('time-badge');
  if (el) el.textContent = new Date().toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
}
setInterval(clock, 1000);
clock();

// Uptime counter
var secs = 0;
setInterval(function() {
  secs++;
  var h = Math.floor(secs / 3600);
  var m = String(Math.floor((secs % 3600) / 60)).padStart(2, '0');
  var s = String(secs % 60).padStart(2, '0');
  var el = document.getElementById('s-uptime');
  if (el) el.textContent = h + ':' + m + ':' + s;
}, 1000);

// Toast notification
var _tt;
function toast(msg, dur) {
  dur = dur || 2500;
  var msgEl = document.getElementById('toast-msg');
  var el    = document.getElementById('toast');
  if (!msgEl || !el) return;
  msgEl.textContent = msg;
  el.classList.add('show');
  clearTimeout(_tt);
  _tt = setTimeout(function() { el.classList.remove('show'); }, dur);
}

// Accordion panel toggle
function togglePanel(h) {
  h.classList.toggle('open');
  if (h.nextElementSibling) h.nextElementSibling.classList.toggle('open');
}

// Stream control
function handleStreamError() {
  var el = document.getElementById('stream-error');
  if (el) el.classList.add('show');
}

function refreshStream() {
  var el = document.getElementById('stream-error');
  if (el) el.classList.remove('show');
  var f = document.getElementById('cam-frame');
  if (f) { var s = f.src; f.src = ''; f.src = s; }
  toast('Stream refreshed!');
}

var streamOn = true;
function toggleStream() {
  streamOn = !streamOn;
  var f = document.getElementById('cam-frame');
  if (f) f.style.visibility = streamOn ? 'visible' : 'hidden';
  var btn = document.getElementById('pause-btn');
  if (btn) btn.innerHTML = streamOn
    ? '<svg viewBox="0 0 16 16" fill="white"><rect x="3" y="2" width="4" height="12" rx="1"/><rect x="9" y="2" width="4" height="12" rx="1"/></svg> Pause'
    : '<svg viewBox="0 0 16 16" fill="white"><path d="M4 2l10 6-10 6z"/></svg> Play';
  toast(streamOn ? 'Stream resumed!' : 'Stream paused!');
}

function setQuality(q) {
  var rb = document.getElementById('res-badge');
  var sr = document.getElementById('s-res');
  if (rb) rb.innerHTML = '<svg viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="1.4"><rect x="1" y="2" width="10" height="8" rx="1.5"/><path d="M4 2V1M8 2V1"/></svg>' + (q === 'HD' ? '2K' : 'SD');
  if (sr) sr.textContent = q === 'HD' ? '2K' : 'SD';
  toast('Quality: ' + (q === 'HD' ? '2K Main' : 'SD Sub'));
}

function captureSnapshot() {
  toast('Capturing snapshot...');
  fetch('motion_log.php?action=manual_snap')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.ok) {
        toast('Snapshot logged! Check Motion Log.');
        // Refresh the motion panel
        if (typeof mpLoad === 'function') mpLoad();
      } else {
        toast('Snapshot failed: ' + (d.error || 'unknown error'));
      }
    })
    .catch(function(e) { toast('Snapshot error: ' + e.message); });
}

// PTZ controls
function speed() {
  var el = document.getElementById('ptz-speed');
  return el ? parseInt(el.value) : 5;
}

var DIRS = {
  'up':         {p:0,  t:1},
  'down':       {p:0,  t:-1},
  'left':       {p:-1, t:0},
  'right':      {p:1,  t:0},
  'up-left':    {p:-1, t:1},
  'up-right':   {p:1,  t:1},
  'down-left':  {p:-1, t:-1},
  'down-right': {p:1,  t:-1}
};

function ptzMove(dir) {
  var v = DIRS[dir] || {p:0, t:0};
  var s = speed();
  toast('PTZ ' + dir.replace('-', ' ') + ' (spd ' + s + ')');
  fetch('onvif_proxy.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action:'move', pan:v.p, tilt:v.t, speed:s})
  }).catch(function(){});
}

function ptzHome() {
  toast('Going home!');
  fetch('onvif_proxy.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action:'home'})
  }).catch(function(){});
}

// Camera API toggles
function apiToggle(f, v) {
  toast((v ? 'ON' : 'OFF') + ' ' + f.replace(/_/g, ' '));
  fetch('cam_api.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({feature:f, value:v})
  }).catch(function(){});
}

function checkStatus() {
  toast('Checking camera...');
  fetch('cam_api.php?action=status')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var el = document.getElementById('s-cam');
      if (el) el.textContent = d.online ? 'Online' : 'Offline';
      toast(d.online ? 'Camera is online!' : 'Camera offline');
    })
    .catch(function() {
      var el = document.getElementById('s-cam');
      if (el) el.textContent = 'Unknown';
      toast('Status check failed');
    });
}

function sendOnvif(a) {
  toast('ONVIF: ' + a.replace(/_/g, ' '));
  fetch('onvif_proxy.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action:a})
  }).catch(function(){});
}

// Fullscreen
function toggleFullscreen() {
  var p = document.getElementById('video-panel');
  if (!p) return;
  if (!document.fullscreenElement) {
    if (p.requestFullscreen) p.requestFullscreen();
  } else {
    if (document.exitFullscreen) document.exitFullscreen();
  }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
  var tag = document.activeElement.tagName;
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
  var map = {
    'ArrowUp':    function() { ptzMove('up'); },
    'ArrowDown':  function() { ptzMove('down'); },
    'ArrowLeft':  function() { ptzMove('left'); },
    'ArrowRight': function() { ptzMove('right'); },
    'h':          function() { ptzHome(); },
    'f':          function() { toggleFullscreen(); },
    'r':          function() { refreshStream(); },
    ' ':          function() { toggleStream(); }
  };
  if (map[e.key]) { e.preventDefault(); map[e.key](); }
});
</script>
<script>
// HamCAM motion panel JS - v1.4
var MP_START  = '<?= MOTION_ACTIVE_START ?>';
var MP_END    = '<?= MOTION_ACTIVE_END ?>';
var MP_ALWAYS = <?= MOTION_ALWAYS_ON ? 'true' : 'false' ?>;

function mpInWindow() {
  if (MP_ALWAYS) return true;
  var now = new Date();
  var cur = now.getHours() * 60 + now.getMinutes();
  var sp = MP_START.split(':'), ep = MP_END.split(':');
  var s = parseInt(sp[0]) * 60 + parseInt(sp[1]);
  var e = parseInt(ep[0]) * 60 + parseInt(ep[1]);
  return s > e ? (cur >= s || cur <= e) : (cur >= s && cur <= e);
}

function mpUpdateBadge() {
  var badge = document.getElementById('mp-badge');
  var label = document.getElementById('mp-badge-label');
  if (!badge || !label) return;
  var active = mpInWindow();
  badge.className = 'motion-window-badge' + (active ? '' : ' inactive');
  label.textContent = active
    ? ('Monitoring ' + MP_START + ' - ' + MP_END)
    : ('Active ' + MP_START + ' - ' + MP_END);
}
mpUpdateBadge();
setInterval(mpUpdateBadge, 60000);

function mpLoad() {
  fetch('motion_log.php?action=stats')
    .then(function(r) { return r.json(); })
    .then(function(sd) {
      var e1 = document.getElementById('mp-today');
      var e2 = document.getElementById('mp-total');
      if (e1) e1.textContent = (sd.today != null) ? sd.today : '--';
      if (e2) e2.textContent = (sd.total != null) ? sd.total : '--';
    }).catch(function(){});

  fetch('motion_log.php?action=events&filter=all&page=1')
    .then(function(r) { return r.json(); })
    .then(function(ed) {
      var list = document.getElementById('mp-list');
      if (!list) return;
      if (!ed.events || ed.events.length === 0) {
        list.innerHTML = '<div class="motion-empty">No events yet</div>';
        return;
      }
      var html = '';
      var recent = ed.events.slice(0, 5);
      for (var i = 0; i < recent.length; i++) {
        var ev = recent[i];
        var dt = new Date(ev.ts * 1000);
        var isToday = new Date().toDateString() === dt.toDateString();
        var dateLbl = isToday ? 'Today' : dt.toLocaleDateString('en-US', {month:'short', day:'numeric'});
        var timeLbl = dt.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
        var thumbHtml;
        if (ev.snapshot) {
          var snapSrc = 'motion_log.php?action=snapshot&file=' + encodeURIComponent(ev.snapshot);
          var caption = dateLbl + ' ' + timeLbl;
          thumbHtml = '<img class="motion-thumb" src="' + snapSrc + '" loading="lazy"'
            + ' data-src="' + snapSrc + '" data-cap="' + caption + '" data-score="' + ev.score + '"'
            + ' onclick="mlbFromImg(this)" alt="snap">';
        } else {
          thumbHtml = '<div class="motion-thumb-placeholder">'
            + '<svg viewBox="0 0 22 22" fill="none"><circle cx="11" cy="8" r="5" fill="#FFCBA4"/>'
            + '<ellipse cx="11" cy="17" rx="5" ry="4" fill="#FFCBA4"/></svg></div>';
        }
        html += '<div class="motion-event">' + thumbHtml
          + '<div class="motion-info">'
          + '<div class="motion-time">' + dateLbl + ' &bull; ' + timeLbl + '</div>'
          + '<div class="motion-score">Score: <span>' + ev.score.toLocaleString() + '</span></div>'
          + '</div></div>';
      }
      list.innerHTML = html;
    }).catch(function() {
      var list = document.getElementById('mp-list');
      if (list) list.innerHTML = '<div class="motion-empty">Detector not running</div>';
    });
}
mpLoad();
setInterval(mpLoad, 30000);

// Motion lightbox
function mlbFromImg(img) {
  mlbOpen(img.getAttribute('data-src'), img.getAttribute('data-cap'), parseInt(img.getAttribute('data-score')));
}
function mlbOpen(src, time, score) {
  var img = document.getElementById('mlb-img');
  var info = document.getElementById('mlb-info');
  var lb = document.getElementById('mlb');
  if (img) img.src = src;
  if (info) info.textContent = time + '  |  Score: ' + score.toLocaleString();
  if (lb) lb.classList.add('show');
}
function mlbClose(e) {
  var lb = document.getElementById('mlb');
  if (!lb) return;
  if (!e || e.target === lb || (e.target && e.target.classList.contains('mlb-close'))) {
    lb.classList.remove('show');
  }
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    var lb = document.getElementById('mlb');
    if (lb) lb.classList.remove('show');
  }
});
</script>
</body>
</html>