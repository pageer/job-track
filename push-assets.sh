#!/usr/bin/env bash
#
# push-assets.sh — Build the frontend locally and upload it to the server via SCP.
#
# Usage:
#   ./push-assets.sh
#
# Prerequisites locally:
#   - Node.js 22+ with npm
#
# Edit the variables below before first run.

set -euo pipefail

# ── Connection settings (edit these) ────────────────────────────────

REMOTE_USER=""           # e.g. "youruser"
REMOTE_HOST=""           # e.g. "yourserver.com"
REMOTE_PATH=""           # e.g. "/home/youruser/job-track/backend/public/build"
REMOTE_PORT=22           # SSH port

# ── Script ──────────────────────────────────────────────────────────

ROOT="$(cd "$(dirname "$0")" && pwd)"
FRONTEND="$ROOT/frontend"
BUILD_DIR="$ROOT/backend/public/build"

ok()   { printf "\033[32m✓ %s\033[0m\n" "$1"; }
fail() { printf "\033[31m✗ %s\033[0m\n" "$1"; exit 1; }

if [ -z "$REMOTE_USER" ] || [ -z "$REMOTE_HOST" ] || [ -z "$REMOTE_PATH" ]; then
  fail "Edit this script and fill in REMOTE_USER, REMOTE_HOST, and REMOTE_PATH."
fi

# 1. Build frontend

cd "$FRONTEND"
npm install --no-audit --no-fund || fail "npm install failed."
npm run build || fail "Frontend build failed."
ok "Frontend built into backend/public/build/."

# 2. Upload via SCP

echo "Uploading $BUILD_DIR → ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH} ..."
scp -P "$REMOTE_PORT" -r "$BUILD_DIR" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}" || fail "SCP upload failed."
ok "Assets uploaded."

echo ""
ok "Done."
