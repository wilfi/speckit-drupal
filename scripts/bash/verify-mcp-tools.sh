#!/usr/bin/env bash
# verify-mcp-tools.sh — list MCP tools and warn when read tools are missing
set -euo pipefail

PROJECT_ROOT="$(pwd)"
USE_DDEV=true

log() { echo "drupal: $*"; }
warn() { echo "drupal: WARN: $*" >&2; }

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

[[ -x "$PROJECT_ROOT/vendor/bin/drush" ]] || command -v ddev >/dev/null 2>&1 || {
  warn "Drush not found — skip MCP tools verification"
  exit 0
}

if ! drush_cmd pm:list --status=enabled --filter=mcp_tools 2>/dev/null | grep -qE '(Enabled|enabled)'; then
  warn "mcp_tools not enabled — run /speckit-drupal-setup-mcp-tools"
  exit 0
fi

log "MCP tools inventory (via drush mcp-tools:list-available):"
output="$(drush_cmd mcp-tools:list-available 2>/dev/null || drush_cmd php:eval "echo 'list-unavailable';" 2>/dev/null || true)"
printf '%s\n' "$output"

EXPECTED_READ=(
  "mcp_tools_get_menus"
  "mcp_tools_get_menu_tree"
  "mcp_structure_list_content_types"
  "mcp_config_changes"
)

missing=0
for tool in "${EXPECTED_READ[@]}"; do
  if ! grep -qF "$tool" <<< "$output"; then
    warn "Expected read tool not listed: $tool (enable Development preset at /admin/config/services/mcp-tools)"
    missing=$((missing + 1))
  fi
done

if [[ "$missing" -gt 0 ]]; then
  warn "$missing expected read tool(s) missing — use drush php:eval fallback per templates/mcp-tooling-guide.md"
  exit 1
fi

log "MCP read tools check passed."
