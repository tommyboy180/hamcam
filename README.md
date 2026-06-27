# HamCAM 🐹

A self-hosted live camera dashboard for keeping an eye on a hamster (or any
RTSP/ONVIF camera on your LAN) — live video, PTZ controls, motion-detection
logging, and snapshots, all running in Docker on your own network.

Built around a TP-Link Tapo C210, but anything that speaks RTSP + ONVIF
should work with minor tweaks.

## Features

- Password-gated live view with low-latency HLS streaming via [go2rtc](https://github.com/AlexxIT/go2rtc)
- MJPEG snapshot fallback if HLS isn't available
- PTZ (pan/tilt/zoom) controls proxied through ONVIF
- Camera feature toggles (night vision, motion detection, flip, audio, siren)
- Background motion detector (OpenCV) with a nightly or 24/7 logging window,
  SQLite event log, and saved snapshot thumbnails
- **In-browser setup wizard** — no manual config-file editing required
- Everything containerized: a PHP/Apache backend, a go2rtc video relay, and
  a Python motion detector, each as their own service

## How it works

There's no separate installer. The PHP backend builds and boots with
**placeholder** config, detects that it isn't configured yet, and bounces
every page to `setup.php` — an in-browser wizard — until you fill it in.
Once you submit the form, it writes real config files to disk and you're
straight into the dashboard. No SSH-ing in to hand-edit PHP files.

## Requirements

- Docker + Docker Compose
- A camera with RTSP and ONVIF enabled, reachable on your LAN

## Setup

**1. Clone this repo** onto the machine that will run the stack.







**2. Build and start the stack:**

```bash
docker compose up -d --build
```

This is the `docker-compose.yml` that ships in the repo:

```yaml
services:

  hamcam:
    build: .
    container_name: hamcam
    restart: unless-stopped
    ports:
      - "${HAMCAM_PORT:-8765}:80"
    volumes:
      - hamcam-data:/data                          # shared with motion detector
      - ./config.php:/var/www/html/config.php       # setup.php writes here
      - ./go2rtc.yaml:/var/www/html/go2rtc.yaml      # setup.php writes here too
      - ./.env:/var/www/html/.env                    # setup.php writes here too
    networks:
      - hamcam-net
    environment:
      - TZ=${TZ:-UTC}
    depends_on:
      - go2rtc

  go2rtc:
    image: alexxit/go2rtc:latest
    container_name: hamcam-go2rtc
    restart: unless-stopped
    ports:
      - "${GO2RTC_API_PORT:-1984}:1984"
      - "${GO2RTC_RTSP_PORT:-8554}:8554"
    volumes:
      - ./go2rtc.yaml:/config/go2rtc.yaml:ro
    networks:
      - hamcam-net
    environment:
      - TZ=${TZ:-UTC}

  motion:
    build: ./motion
    container_name: hamcam-motion
    restart: unless-stopped
    volumes:
      - hamcam-data:/data      # writes DB + snapshots here
    networks:
      - hamcam-net
    environment:
      - TZ=${TZ:-UTC}
      - RTSP_URL=rtsp://go2rtc:8554/cam          # go2rtc re-streams on 8554
      - DB_PATH=/data/motion.db
      - SNAP_DIR=/data/snapshots
      - ACTIVE_START=${MOTION_ACTIVE_START:-20:00}   # match config.php
      - ACTIVE_END=${MOTION_ACTIVE_END:-07:00}
      - ALWAYS_ON=${MOTION_ALWAYS_ON:-false}
      - THRESHOLD=${MOTION_THRESHOLD:-3000}
      - COOLDOWN=${MOTION_COOLDOWN:-30}
      - LOG_LEVEL=${MOTION_LOG_LEVEL:-INFO}
      - MAX_EVENTS=${MOTION_MAX_EVENTS:-1000}
    depends_on:
      - go2rtc

volumes:
  hamcam-data:

networks:
  hamcam-net:
    driver: bridge
```

Why three services instead of one:

| Service  | What it is                          | Why it's separate |
|----------|--------------------------------------|--------------------|
| `hamcam` | This repo's PHP/Apache app           | The web UI, the wizard, the API |
| `go2rtc` | [alexxit/go2rtc](https://github.com/AlexxIT/go2rtc) image | Turns your camera's RTSP feed into browser-friendly low-latency HLS |
| `motion` | A small Python/OpenCV script (`motion/`) | Watches the relayed stream and logs motion events independently of the web app |

`hamcam` is the only one you're "browsing to" — `go2rtc` and `motion` just
sit in the background doing their jobs.

**3. Open `http://<docker-host>:8765`** in a browser. Nothing is
configured yet, so you'll land straight on the **setup wizard** — fill in
your access password, camera IP/RTSP credentials, ONVIF port,
motion-detection preferences, and a couple of networking details, then
hit **Finish setup**.

That's it — the wizard writes `config.php`, `go2rtc.yaml`, and `.env` for
you, logs you straight in, and takes you to the dashboard.

> ⚠️ The wizard is open to anyone on your network until you finish it
> (there's no password yet to gate it). Don't leave it sitting unfinished
> on an untrusted network — fill it in right after first boot.

**4.** If you changed the **video relay**, **web UI port**, or **motion
detector** settings, apply them with:

```bash
docker compose up -d
```

(Login/password, camera IP/credentials, and site title take effect
immediately — no restart needed, since `config.php` is read fresh on
every page load. Only the things that affect container startup —
ports, env vars, the relay's own config file — need a restart.)

## Reconfiguring later

Once setup is complete, visiting `setup.php` again requires you to be
logged in first (same session as the dashboard) — it becomes a regular
**Settings** page rather than an open door. Leave the password or
camera-password fields blank to keep their current values; everything
else updates to whatever you type.

## Project layout

```
.
├── docker-compose.yml        # hamcam app + go2rtc + motion detector
├── dockerfile                  # PHP/Apache image for the app
├── config.example.php          # template -> config.php (gitignored)
├── go2rtc.example.yaml          # template -> go2rtc.yaml (gitignored)
├── .env.example                  # template -> .env (gitignored)
├── init.sh                        # one-time: creates the three files above
├── setup.php                       # the in-browser setup wizard / settings page
├── setup_guard.php                  # redirects to setup.php until configured
├── index.php                         # login page
├── camera.php                         # main dashboard (video, PTZ, controls)
├── cam_api.php                         # camera feature toggle API
├── onvif_proxy.php                      # PTZ/ONVIF SOAP proxy
├── snapshot.php                          # MJPEG snapshot proxy
├── motion_log.php                         # motion event log viewer
├── logout.php
└── motion/
    ├── detector.py                        # OpenCV motion detector
    └── dockerfile
```

## Security notes

- The login password is stored as a **bcrypt hash** in `config.php`
  (never plaintext), verified with `password_verify()`.
- Camera RTSP/ONVIF credentials are stored in plaintext in `config.php`
  and `go2rtc.yaml` — both gitignored, and that's the nature of needing
  to actively use them to connect to the camera. `config.php` is never
  served directly (Apache executes `.php` files rather than serving
  their source), but treat the file like any other secret.
- This is designed for a trusted LAN. If you expose it to the internet,
  put it behind a reverse proxy with HTTPS and consider IP allow-listing.
- The setup wizard is unauthenticated **only** during the initial
  first-run window, by necessity (there's no password yet to check
  against). Once setup completes it's gated behind the same login as
  the rest of the app.

## License

MIT — see [LICENSE](LICENSE). Swap or remove this if you'd rather use
something else.
