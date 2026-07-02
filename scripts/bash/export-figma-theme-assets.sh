#!/usr/bin/env bash
# export-figma-theme-assets.sh — write PNGs from figma-asset-manifest.yml to the theme
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FEATURE_DIR="${1:-}"

log() { echo "drupal: $*"; }
fail() { echo "drupal: ERROR: $*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage: export-figma-theme-assets.sh <FEATURE_DIR>

Download PNGs listed in specs/<feature>/figma-asset-manifest.yml into
web/themes/custom/<theme>/. Validates PNG magic bytes (QR-ASSET-004).

Populate the manifest during /speckit-plan using Figma MCP download_assets.
See templates/figma-asset-export.md.
EOF
}

[[ -n "$FEATURE_DIR" ]] || { usage; exit 1; }
[[ -f "$PROJECT_ROOT/$FEATURE_DIR/figma-asset-manifest.yml" ]] || fail "missing figma-asset-manifest.yml"

export PROJECT_ROOT
node "$SCRIPT_DIR/export-figma-theme-assets.mjs" "$FEATURE_DIR" "$PROJECT_ROOT"
