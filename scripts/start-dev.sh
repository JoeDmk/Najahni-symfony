#!/usr/bin/env bash
# start-dev.sh — Start Symfony dev server + ngrok tunnel
# Usage: bash scripts/start-dev.sh
#
# Prerequisites:
#   1. Install ngrok: https://ngrok.com/download
#   2. Authenticate:  ngrok config add-authtoken YOUR_TOKEN
#   3. Claim a free static domain at https://dashboard.ngrok.com/domains
#   4. Set APP_PUBLIC_URL in .env.local:
#        APP_PUBLIC_URL=https://YOUR-DOMAIN.ngrok-free.app

set -e
PORT=${1:-8000}
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# --- Read APP_PUBLIC_URL from .env.local ---
DOMAIN=""
if [ -f "$PROJECT_DIR/.env.local" ]; then
    DOMAIN=$(grep -oP '^APP_PUBLIC_URL=https?://\K.+' "$PROJECT_DIR/.env.local" | tr -d '[:space:]' || true)
fi

if [ -z "$DOMAIN" ]; then
    echo "[!] APP_PUBLIC_URL not set in .env.local"
    echo "    1. Get a free static domain: https://dashboard.ngrok.com/domains"
    echo "    2. Add to .env.local:  APP_PUBLIC_URL=https://your-domain.ngrok-free.app"
    echo ""
    echo "Starting Symfony only (no tunnel)..."
    cd "$PROJECT_DIR"
    php -S "127.0.0.1:$PORT" -t public
    exit
fi

echo "=== Najahni Dev Server ==="
echo "  Symfony : http://127.0.0.1:$PORT"
echo "  Public  : https://$DOMAIN"
echo "  QR codes will point to https://$DOMAIN"
echo ""

cleanup() {
    echo ""
    echo "[cleanup] Stopping Symfony server..."
    kill "$PHP_PID" 2>/dev/null || true
    exit
}
trap cleanup EXIT INT TERM

# --- Start Symfony in background ---
cd "$PROJECT_DIR"
php -S "127.0.0.1:$PORT" -t public &
PHP_PID=$!

# --- Start ngrok (foreground) ---
echo "[ngrok] Starting tunnel to port $PORT with domain $DOMAIN ..."
ngrok http "$PORT" --domain "$DOMAIN"
