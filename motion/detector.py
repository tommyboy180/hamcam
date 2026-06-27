# ============================================================
# HamCAM detector.py
# Version: 1.2
# ============================================================
#!/usr/bin/env python3
"""
HamCAM Motion Detector
Reads RTSP stream from go2rtc, detects motion via frame differencing,
logs events to SQLite, saves thumbnail snapshots, respects time windows.
"""

import cv2
import sqlite3
import os
import time
import json
import logging
from datetime import datetime, time as dtime
from pathlib import Path

# -- Config (overridden by env vars set in docker-compose) ----------------------
RTSP_URL      = os.environ.get('RTSP_URL',      'rtsp://go2rtc:8554/cam')
DB_PATH       = os.environ.get('DB_PATH',        '/data/motion.db')
SNAP_DIR      = os.environ.get('SNAP_DIR',       '/data/snapshots')
ACTIVE_START  = os.environ.get('ACTIVE_START',   '20:00')   # 8pm
ACTIVE_END    = os.environ.get('ACTIVE_END',     '07:00')   # 7am
THRESHOLD     = int(os.environ.get('THRESHOLD',  '3000'))   # changed pixels to trigger
COOLDOWN_SEC  = int(os.environ.get('COOLDOWN',   '30'))     # min seconds between events
ALWAYS_ON     = os.environ.get('ALWAYS_ON',      'false').lower() == 'true'
MAX_EVENTS    = int(os.environ.get('MAX_EVENTS', '1000'))   # trim DB after this many
LOG_LEVEL     = os.environ.get('LOG_LEVEL',      'INFO')

logging.basicConfig(
    level=getattr(logging, LOG_LEVEL),
    format='%(asctime)s [HamMotion] %(levelname)s: %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
log = logging.getLogger('hammotion')

# Suppress ffmpeg/OpenCV decoder noise (corrupt frames are handled gracefully)
os.environ['OPENCV_LOG_LEVEL'] = 'ERROR'
os.environ['OPENCV_FFMPEG_LOGLEVEL'] = '-8'  # AV_LOG_QUIET

# -- DB setup -------------------------------------------------------------------
def init_db(db_path: str) -> sqlite3.Connection:
    Path(db_path).parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(db_path, check_same_thread=False)
    conn.execute('''
        CREATE TABLE IF NOT EXISTS events (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            ts        INTEGER NOT NULL,
            ts_human  TEXT NOT NULL,
            snapshot  TEXT,
            score     INTEGER,
            notes     TEXT
        )
    ''')
    conn.execute('CREATE INDEX IF NOT EXISTS idx_ts ON events(ts)')
    conn.commit()
    return conn

# -- Time window check ----------------------------------------------------------
def parse_time(s: str) -> dtime:
    h, m = map(int, s.split(':'))
    return dtime(h, m)

def in_active_window() -> bool:
    if ALWAYS_ON:
        return True
    now  = datetime.now().time().replace(second=0, microsecond=0)
    start = parse_time(ACTIVE_START)
    end   = parse_time(ACTIVE_END)
    if start > end:   # overnight window e.g. 20:00 ? 07:00
        return now >= start or now <= end
    return start <= now <= end

# -- Snapshot -------------------------------------------------------------------
def save_snapshot(frame, ts: int) -> str | None:
    try:
        Path(SNAP_DIR).mkdir(parents=True, exist_ok=True)
        filename = f'motion_{ts}.jpg'
        path = os.path.join(SNAP_DIR, filename)
        cv2.imwrite(path, frame, [cv2.IMWRITE_JPEG_QUALITY, 75])
        return filename
    except Exception as e:
        log.warning(f'Snapshot save failed: {e}')
        return None

# -- Trim old events ------------------------------------------------------------
def trim_events(conn: sqlite3.Connection):
    count = conn.execute('SELECT COUNT(*) FROM events').fetchone()[0]
    if count > MAX_EVENTS:
        to_delete = count - MAX_EVENTS
        # Also delete their snapshot files
        old = conn.execute(
            'SELECT snapshot FROM events ORDER BY ts ASC LIMIT ?', (to_delete,)
        ).fetchall()
        for (snap,) in old:
            if snap:
                try:
                    os.remove(os.path.join(SNAP_DIR, snap))
                except FileNotFoundError:
                    pass
        conn.execute(
            'DELETE FROM events WHERE id IN (SELECT id FROM events ORDER BY ts ASC LIMIT ?)',
            (to_delete,)
        )
        conn.commit()
        log.info(f'Trimmed {to_delete} old events')

# -- Main detection loop --------------------------------------------------------
def run():
    log.info(f'Starting HamCAM motion detector')
    log.info(f'RTSP: {RTSP_URL}')
    log.info(f'Active window: {ACTIVE_START} ? {ACTIVE_END} (always_on={ALWAYS_ON})')
    log.info(f'Threshold: {THRESHOLD} pixels | Cooldown: {COOLDOWN_SEC}s')

    conn = init_db(DB_PATH)
    last_event = 0
    prev_gray  = None
    retry_wait = 5

    while True:
        log.info(f'Connecting to RTSP stream...')
        cap = cv2.VideoCapture(RTSP_URL, cv2.CAP_FFMPEG)
        cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)

        if not cap.isOpened():
            log.error(f'Cannot open RTSP stream, retrying in {retry_wait}s')
            time.sleep(retry_wait)
            retry_wait = min(retry_wait * 2, 60)
            continue

        retry_wait = 5
        log.info('Stream connected, monitoring for motion...')
        consecutive_failures = 0

        while True:
            ret, frame = cap.read()
            if not ret:
                consecutive_failures += 1
                if consecutive_failures > 10:
                    log.warning('Too many read failures, reconnecting...')
                    break
                time.sleep(0.5)
                continue

            consecutive_failures = 0

            # Skip processing if outside active window
            if not in_active_window():
                prev_gray = None
                time.sleep(5)
                continue

            # Downscale for faster processing
            small  = cv2.resize(frame, (640, 360))
            gray   = cv2.cvtColor(small, cv2.COLOR_BGR2GRAY)
            gray   = cv2.GaussianBlur(gray, (21, 21), 0)

            if prev_gray is None:
                prev_gray = gray
                continue

            # Frame difference
            delta  = cv2.absdiff(prev_gray, gray)
            thresh = cv2.threshold(delta, 25, 255, cv2.THRESH_BINARY)[1]
            thresh = cv2.dilate(thresh, None, iterations=2)
            score  = int(cv2.countNonZero(thresh))

            prev_gray = gray

            now = time.time()
            # Log score periodically so we can tune the threshold
            if score > 100:  # only log when something is happening
                log.debug(f'Frame score={score} (threshold={THRESHOLD})')
            if score >= THRESHOLD and (now - last_event) >= COOLDOWN_SEC:
                last_event = now
                ts       = int(now)
                ts_human = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                snapshot = save_snapshot(frame, ts)

                conn.execute(
                    'INSERT INTO events (ts, ts_human, snapshot, score) VALUES (?,?,?,?)',
                    (ts, ts_human, snapshot, score)
                )
                conn.commit()
                trim_events(conn)

                log.info(f'MOTION DETECTED | score={score} | {ts_human} | snap={snapshot}')

            # ~10fps is plenty for motion detection
            time.sleep(0.1)

        cap.release()
        time.sleep(2)

if __name__ == '__main__':
    run()