<#
.SYNOPSIS
    Build the frontend locally and upload it to the server via SCP.

.DESCRIPTION
    Runs npm install and npm run build in the frontend directory, then uploads
    the resulting backend/public/build/ folder to the remote server.

    Connection settings are read from .env in the project root.
    Copy .env.example to .env and fill in your values.

.EXAMPLE
    .\push-assets.ps1
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ── Load connection settings from .env ──────────────────

$Root = $PSScriptRoot
$EnvFile = Join-Path $Root ".env"

if (-not (Test-Path $EnvFile)) {
    Write-Error ".env not found. Copy .env.example to .env and fill in your connection details."
    exit 1
}

$EnvVars = @{}
foreach ($Line in Get-Content $EnvFile) {
    $Line = $Line.Trim()
    if ($Line -eq '' -or $Line.StartsWith('#')) { continue }
    $EqIndex = $Line.IndexOf('=')
    if ($EqIndex -lt 1) { continue }
    $Key = $Line.Substring(0, $EqIndex).Trim()
    $Value = $Line.Substring($EqIndex + 1).Trim().Trim('"').Trim("'")
    $EnvVars[$Key] = $Value
}

$RemoteUser = $EnvVars['REMOTE_USER']
$RemoteHost = $EnvVars['REMOTE_HOST']
$RemotePath = $EnvVars['REMOTE_PATH']
$RemotePort = if ($EnvVars['REMOTE_PORT']) { $EnvVars['REMOTE_PORT'] } else { "22" }

# ── Script ──────────────────────────────────────────────────────────

$Frontend = Join-Path $Root "frontend"
$BuildDir = Join-Path $Root "backend" | Join-Path -ChildPath "public" | Join-Path -ChildPath "build"

function Ok   { param([string]$Msg) Write-Host "  $Msg" -ForegroundColor Green }
function Fail { param([string]$Msg) Write-Error $Msg; exit 1 }

if (-not $RemoteUser -or -not $RemoteHost -or -not $RemotePath) {
    Fail "REMOTE_USER, REMOTE_HOST, and REMOTE_PATH must be set in .env."
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

# 3. Fix directory permissions (scp -r can strip execute bits)

Write-Host "Fixing directory permissions on server..."
ssh.exe -p $RemotePort "${RemoteUser}@${RemoteHost}" "chmod -R +rX '${RemotePath}/build'"
if ($LASTEXITCODE -ne 0) { Fail "chmod failed." }
Ok "Permissions fixed."

Write-Host ""
Ok "Done."
