#!/usr/bin/env bash
# generate-site-context.sh — collect live Drupal site context and scaffold .specify/drupal/
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUNDLE="${PROJECT_ROOT}/.specify/drupal/generated/site-context-bundle.json"
USE_DDEV=true
MODE="collect"

log() { echo "drupal: $*"; }
fail() { echo "drupal: ERROR: $*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage: generate-site-context.sh [--bundle PATH] [--mcp-bundle-only]

Collect live Drupal site structure and write .specify/drupal/ project context.

Default: Drush collector (fast, reliable — no MCP required).

Options:
  --bundle PATH         Use existing JSON bundle instead of collecting
  --mcp-bundle-only     Skip Drush; require bundle from /generate-site-context MCP skill
  -h, --help            Show help
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    -h|--help) usage; exit 0 ;;
    --bundle)
      shift
      [[ $# -gt 0 ]] || fail "--bundle requires a path"
      BUNDLE="$1"
      MODE="bundle"
      ;;
    --mcp-bundle-only) MODE="mcp" ;;
    *) fail "Unknown option: $1" ;;
  esac
  shift
done

[[ -n "$BUNDLE" ]] || fail "Bundle path empty"

if [[ -f "$PROJECT_ROOT/.specify/extensions/drupal/drupal-config.yml" ]]; then
  grep -qE 'use_ddev:\s*false' "$PROJECT_ROOT/.specify/extensions/drupal/drupal-config.yml" 2>/dev/null && USE_DDEV=false || true
fi

drush_cmd() {
  if [[ "$USE_DDEV" == "true" ]] && command -v ddev >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/.ddev/config.yaml" ]]; then
    ddev drush "$@"
  else
    "$PROJECT_ROOT/vendor/bin/drush" -r "$PROJECT_ROOT/web" "$@"
  fi
}

if [[ -x "$SCRIPT_DIR/scaffold-project-context.sh" ]]; then
  bash "$SCRIPT_DIR/scaffold-project-context.sh"
fi

mkdir -p "$(dirname "$BUNDLE")"

case "$MODE" in
  collect)
  log "Collecting site context via Drush..."
  [[ -x "$PROJECT_ROOT/vendor/bin/drush" ]] || command -v ddev >/dev/null 2>&1 || fail "Drush/DDEV required"
  COLLECT_SCRIPT="$SCRIPT_DIR/collect-site-context-drush.php"
  BUNDLE_REL="${BUNDLE#"$PROJECT_ROOT/"}"
  if [[ "$USE_DDEV" == "true" ]] && command -v ddev >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/.ddev/config.yaml" ]]; then
    drush_cmd php:script ".specify/extensions/drupal/scripts/bash/collect-site-context-drush.php" "$BUNDLE_REL" || fail "Drush collection failed"
  else
    drush_cmd php:script "$COLLECT_SCRIPT" "$BUNDLE" || fail "Drush collection failed"
  fi
  ;;
  mcp)
  [[ -f "$BUNDLE" ]] || fail "Bundle not found: $BUNDLE — run /generate-site-context (MCP) first"
  log "Using MCP-collected bundle: $BUNDLE"
  ;;
  bundle)
  [[ -f "$BUNDLE" ]] || fail "Bundle not found: $BUNDLE"
  log "Using bundle: $BUNDLE"
  ;;
esac

export PROJECT_ROOT="$PROJECT_ROOT"
php "$SCRIPT_DIR/write-site-context.php" "$BUNDLE" "$PROJECT_ROOT"

log "Site context scaffold complete."
log "Updated: .specify/drupal/{data-model,site-structure,site-status}.md sites.yml"
