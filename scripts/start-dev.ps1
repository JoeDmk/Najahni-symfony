# start-dev.ps1 — Start Symfony dev server + ngrok tunnel
# Usage: .\scripts\start-dev.ps1
#
# Prerequisites:
#   1. Install ngrok: https://ngrok.com/download  (or: choco install ngrok / winget install ngrok)
#   2. Authenticate:  ngrok config add-authtoken YOUR_TOKEN
#   3. Claim a free static domain at https://dashboard.ngrok.com/domains
#   4. Set APP_PUBLIC_URL in .env.local:
#        APP_PUBLIC_URL=https://YOUR-DOMAIN.ngrok-free.app

param(
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

# --- Read APP_PUBLIC_URL from .env.local ---
$envLocal = Join-Path $PSScriptRoot ".." ".env.local"
$domain = ""
if (Test-Path $envLocal) {
    $match = Select-String -Path $envLocal -Pattern "^APP_PUBLIC_URL=https?://(.+)" | Select-Object -First 1
    if ($match) {
        $domain = $match.Matches[0].Groups[1].Value.Trim()
    }
}

if (-not $domain) {
    Write-Host "[!] APP_PUBLIC_URL not set in .env.local" -ForegroundColor Yellow
    Write-Host "    1. Get a free static domain: https://dashboard.ngrok.com/domains"
    Write-Host "    2. Add to .env.local:  APP_PUBLIC_URL=https://your-domain.ngrok-free.app"
    Write-Host ""
    Write-Host "Starting Symfony only (no tunnel)..." -ForegroundColor Cyan
    php -S "127.0.0.1:$Port" -t public
    exit
}

Write-Host "=== Najahni Dev Server ===" -ForegroundColor Green
Write-Host "  Symfony : http://127.0.0.1:$Port"
Write-Host "  Public  : https://$domain"
Write-Host "  QR codes will point to https://$domain"
Write-Host ""

# --- Start Symfony in background ---
$symfony = Start-Process php -ArgumentList "-S", "127.0.0.1:$Port", "-t", "public" `
    -WorkingDirectory (Join-Path $PSScriptRoot "..") `
    -PassThru -NoNewWindow

# --- Start ngrok ---
Write-Host "[ngrok] Starting tunnel to port $Port with domain $domain ..." -ForegroundColor Cyan
try {
    ngrok http $Port --domain $domain
} finally {
    Write-Host "`n[cleanup] Stopping Symfony server (PID $($symfony.Id))..." -ForegroundColor Yellow
    Stop-Process -Id $symfony.Id -Force -ErrorAction SilentlyContinue
}
