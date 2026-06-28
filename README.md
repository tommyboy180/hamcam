# HamCAM 🐹

A self-hosted live camera dashboard for keeping an eye on a hamster (or any
RTSP/ONVIF camera on your LAN) — live video, PTZ controls, motion-detection
logging, and snapshots, all running in Docker on your own network.

Built around a TP-Link Tapo C210, but anything that speaks RTSP + ONVIF
should work with minor tweaks.

<center><img width="505" height="636" alt="image" src="https://github.com/user-attachments/assets/7b529885-6f64-4a25-8665-fa48407a6168" />

<img width="1459" height="892" alt="image" src="https://github.com/user-attachments/assets/efcdf426-c40a-429f-99b1-c4a9a68e18f2" /></center>



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

`config.php`, `go2rtc.yaml`, and `.env` all ship in the repo with working
**example** values, so the stack boots cleanly with zero setup. The app
detects that those are just placeholders and bounces every page to
`setup.php` — an in-browser wizard — until you fill in your real camera
details. Submit the form and it rewrites all three files in place; you're
straight into the dashboard. One command, one page, done.

## Setup

```bash
docker compose up -d --build
```

That's the whole thing. This is the `docker-compose.yml` that ships in the
repo:

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

| Service  | What it is                          | Why it's separate |
|----------|--------------------------------------|--------------------|
| `hamcam` | This repo's PHP/Apache app           | The web UI, the wizard, the API |
| `go2rtc` | [alexxit/go2rtc](https://github.com/AlexxIT/go2rtc) image | Turns your camera's RTSP feed into browser-friendly low-latency HLS |
| `motion` | A small Python/OpenCV script (`motion/`) | Watches the relayed stream and logs motion events independently of the web app |

`hamcam` is the only one you're "browsing to" — `go2rtc` and `motion` just
sit in the background doing their jobs.

Then **open `http://<docker-host>:8765`**. Nothing real is configured yet,
so you'll land straight on the **setup wizard** — fill in your access
password, camera IP/RTSP credentials, ONVIF port, motion-detection
preferences, and a couple of networking details, then hit **Finish setup**.

That's it — the wizard rewrites `config.php`, `go2rtc.yaml`, and `.env`,
tells go2rtc to reload itself (more on that below), logs you straight in,
and takes you to the dashboard.

> ⚠️ The wizard is open to anyone on your network until you finish it
> (there's no password yet to gate it). Don't leave it sitting unfinished
> on an untrusted network — fill it in right after first boot.

If you changed the **motion detector settings**, **web UI port**, or
**timezone**, apply them with:

```bash
docker compose up -d
```

(Login/password, camera IP/credentials, and site title take effect
immediately — no restart needed, since `config.php` is read fresh on
every page load.)

### Why go2rtc doesn't need a manual restart

go2rtc only reads `go2rtc.yaml` once, at process startup — editing the
file on disk doesn't do anything on its own. Rather than make you run
`docker compose restart go2rtc` every time you change camera settings,
the wizard calls go2rtc's own `POST /api/restart` endpoint right after
saving, which makes it reload its config and restart **in-process**
(no Docker involved — just an HTTP call over the Compose network). The
success page tells you whether that worked; if go2rtc happens to be
unreachable for a moment, it falls back to suggesting the manual
restart command.

Note this doesn't apply to `${HAMCAM_PORT}` or `${TZ}` — those are
Docker-level settings (port bindings, container environment) that no
in-app API call can change; they genuinely need `docker compose up -d`
to take effect.

## Reconfiguring later

Once setup is complete, visiting `setup.php` again requires you to be
logged in first (same session as the dashboard) — it becomes a regular
**Settings** page rather than an open door. Leave the password or
camera-password fields blank to keep their current values; everything
else updates to whatever you type.

## A note on git

`config.php` and `go2rtc.yaml` are tracked files, not gitignored — that's
what makes the zero-setup boot possible (Docker needs them to exist as
real files before it can bind-mount them in; if they didn't already
exist, Docker would create them as empty *directories* instead, which is
the directory-vs-file error this design avoids entirely).

The tradeoff: once the wizard writes your real camera credentials and
password hash into them, `git status` will show those as local changes.
If you ever do `git add -A`/`git commit` from this same checkout for some
other reason (pulling in a code fix, say), make sure you don't sweep
those up too. If you'd rather git ignore your local edits to these two
files specifically while keeping them tracked for everyone else's fresh
clone, run this once after cloning:

```bash
git update-index --skip-worktree config.php go2rtc.yaml
```

(`.env` doesn't carry secrets — just port/timezone/motion-timing — so
it's not a concern either way.)

## Project layout

```
.
├── docker-compose.yml        # hamcam app + go2rtc + motion detector
├── dockerfile                  # PHP/Apache image for the app
├── config.php                   # ships with example values; wizard rewrites it
├── go2rtc.yaml                   # ships with example values; wizard rewrites it
├── .env                            # ships with sane defaults
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
  and `go2rtc.yaml` — that's the nature of needing to actively use them
  to connect to the camera. Neither file is ever served directly (Apache
  executes `.php` files rather than serving their source; `go2rtc.yaml`
  isn't under the web root at all), but treat them like any other secret
  — see "A note on git" above.
- This is designed for a trusted LAN. If you expose it to the internet,
  put it behind a reverse proxy with HTTPS and consider IP allow-listing.
- The setup wizard is unauthenticated **only** during the initial
  first-run window, by necessity (there's no password yet to check
  against). Once setup completes it's gated behind the same login as
  the rest of the app.

## License

MIT — see [LICENSE](LICENSE). Swap or remove this if you'd rather use
something else.
