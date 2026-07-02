#!/usr/bin/env bash
# ensure-figma-node-deps.sh — npm + Playwright for Figma screenshot scripts
set -euo pipefail

PROJECT_ROOT="$(pwd)"
EXT_DIR="${1:-$PROJECT_ROOT/.specify/extensions/drupal}"

log() { echo "drupal: $*"; }
fail() { echo "drupal: ERROR: $*" >&2; exit 1; }

[[ -d "$EXT_DIR" ]] || fail "extension dir not found: $EXT_DIR"
command -v node >/dev/null 2>&1 || fail "node required for Figma screenshot capture"
command -v npm >/dev/null 2>&1 || fail "npm required for Figma screenshot capture"

if [[ ! -f "$EXT_DIR/package.json" ]]; then
  fail "missing $EXT_DIR/package.json"
fi

if [[ ! -d "$EXT_DIR/node_modules/playwright" ]]; then
  log "Installing Figma script dependencies (playwright, pixelmatch, pngjs)..."
  (cd "$EXT_DIR" && npm install --no-fund --no-audit)
fi

if command -v npx >/dev/null 2>&1; then
  (cd "$EXT_DIR" && npx playwright install chromium) >/dev/null 2>&1 \
    || (cd "$EXT_DIR" && npx playwright install chromium)
fi
