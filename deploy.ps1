<#
.SYNOPSIS
    One-step production deploy: run deploy.sh --no-build on the server over SSH,
    then push the frontend.

.DESCRIPTION
    Runs deploy.sh --no-build on the server (git pull, composer install, DB
    migrations, cache warmup), then runs push-assets.ps1 locally to rebuild the
    frontend and upload it via SCP.

    Connection settings are read from .env in the project root:
      REMOTE_USER, REMOTE_HOST, REMOTE_DEPLOY_DIR, REMOTE_PORT (default 22)

.EXAMPLE
    .\deploy.ps1
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ---- Load connection settings from .env -----------------

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
$DeployDir = $EnvVars['REMOTE_DEPLOY_DIR']
$RemotePort = if ($EnvVars['REMOTE_PORT']) { $EnvVars['REMOTE_PORT'] } else { "22" }

function Ok   { param([string]$Msg) Write-Host "  $Msg" -ForegroundColor Green }
function Warn { param([string]$Msg) Write-Host "  $Msg" -ForegroundColor Yellow }
function Fail { param([string]$Msg) Write-Error $Msg; exit 1 }

if (-not $RemoteUser -or -not $RemoteHost -or -not $DeployDir) {
    Fail "REMOTE_USER, REMOTE_HOST, and REMOTE_DEPLOY_DIR must be set in .env."
}

# ---- Preflight: remind about uncommitted or unpushed work

Write-Host ""
Write-Host "Preflight checks..."

$Dirty = git status --porcelain 2>$null
if ($LASTEXITCODE -eq 0 -and $Dirty) {
    Warn "$(@($Dirty).Count) changed file(s) in the working tree. The deploy uses only what is pushed to origin."
}

$Branch = git rev-parse --abbrev-ref HEAD 2>$null
$Ahead = $null
if ($Branch) {
    try { $Ahead = git rev-list --count "@{upstream}..HEAD" 2>$null } catch {}
    if (-not $Ahead) {
        try { $Ahead = git rev-list --count "origin/$Branch..HEAD" 2>$null } catch {}
    }
}
if ($Ahead -and [int]$Ahead -gt 0) {
    Warn "$Ahead unpushed commit(s) on '$Branch'. Run 'git push origin $Branch' or they won't be deployed."
}

# ---- Step 1/2: run deploy.sh --no-build on the server ---

Write-Host ""
Write-Host "Step 1/2 - Running deploy.sh --no-build on ${RemoteUser}@${RemoteHost} ..."

& ssh.exe -p $RemotePort "${RemoteUser}@${RemoteHost}" "bash '$DeployDir/deploy.sh' --no-build"
if ($LASTEXITCODE -ne 0) { Fail "Remote deploy.sh failed (exit code $LASTEXITCODE)." }
Ok "Server deploy complete."

# ---- Step 2/2: build and upload frontend assets ---------

Write-Host ""
Write-Host "Step 2/2 - Running push-assets.ps1 ..."

$PushAssets = Join-Path $Root "push-assets.ps1"
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $PushAssets
if ($LASTEXITCODE -ne 0) { Fail "push-assets.ps1 failed (exit code $LASTEXITCODE)." }
Ok "Assets pushed."

Write-Host ""
Write-Host "Deploy complete." -ForegroundColor Green