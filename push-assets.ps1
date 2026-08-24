<#
.SYNOPSIS
    Build the frontend locally and upload it to the server via SCP.

.DESCRIPTION
    Runs npm install and npm run build in the frontend directory, then uploads
    the resulting backend/public/build/ folder to the remote server.

    Edit the variables in the Connection Settings section below before first use.

.EXAMPLE
    .\push-assets.ps1
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ── Connection settings (edit these) ────────────────────────────────

$RemoteUser = ""           # e.g. "youruser"
$RemoteHost = ""           # e.g. "yourserver.com"
$RemotePath = ""           # e.g. "/home/youruser/job-track/backend/public/build"
$RemotePort = 22           # SSH port

# ── Script ──────────────────────────────────────────────────────────

$Root        = $PSScriptRoot
$Frontend    = Join-Path $Root "frontend"
$BuildDir    = Join-Path $Root "backend" | Join-Path -ChildPath "public" | Join-Path -ChildPath "build"

function Ok   { param([string]$Msg) Write-Host "  $Msg" -ForegroundColor Green }
function Fail { param([string]$Msg) Write-Error $Msg; exit 1 }

if (-not $RemoteUser -or -not $RemoteHost -or -not $RemotePath) {
    Fail "Edit this script and fill in `$RemoteUser, `$RemoteHost, and `$RemotePath."
}

# 1. Build frontend

Write-Host "Building frontend..."
Push-Location $Frontend
try {
    npm install --no-audit --no-fund
    if ($LASTEXITCODE -ne 0) { Fail "npm install failed." }

    npm run build
    if ($LASTEXITCODE -ne 0) { Fail "Frontend build failed." }
}
finally {
    Pop-Location
}
Ok "Frontend built into backend/public/build/."

# 2. Upload via SCP

Write-Host "Uploading $BuildDir -> ${RemoteUser}@${RemoteHost}:${RemotePath} ..."
scp.exe -P $RemotePort -r $BuildDir "${RemoteUser}@${RemoteHost}:${RemotePath}"
if ($LASTEXITCODE -ne 0) { Fail "SCP upload failed." }
Ok "Assets uploaded."

Write-Host ""
Ok "Done."
