#!/bin/sh
# HamCAM first-run init script.
#
# Creates config.php, go2rtc.yaml, and .env from their *.example
# templates (if they don't already exist) and makes them writable so
# the in-browser setup wizard (setup.php) can fill them in.
#
# Usage:
#   ./init.sh
#   docker compose up -d --build
#
# Safe to run again later — it never overwrites files that already exist.

set -e
cd "$(dirname "$0")"

copy_if_missing() {
    src="$1"
    dest="$2"
    if [ -f "$dest" ]; then
        echo "  $dest already exists, skipping."
    else
        cp "$src" "$dest"
        echo "  created $dest"
    fi
}

echo "Setting up HamCAM config files..."
copy_if_missing config.example.php config.php
copy_if_missing go2rtc.example.yaml go2rtc.yaml
copy_if_missing .env.example .env

chmod 666 config.php go2rtc.yaml .env
echo "Done."
echo
echo "Next: docker compose up -d --build"
echo "Then open the site in a browser — the setup wizard will guide you through the rest."
