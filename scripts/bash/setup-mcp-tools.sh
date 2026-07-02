#!/usr/bin/env bash
# setup-mcp-tools.sh — Install drupal/mcp_tools and wire Cursor MCP (STDIO via Drush)
set -euo pipefail

PROJECT_ROOT="$(pwd)"
EXT_DIR="$PROJECT_ROOT/.specify/extensions/drupal"
CONFIG_FILE="$EXT_DIR/drupal-config.yml"
CURSOR_MCP="$PROJECT_ROOT/.cursor/mcp.json"
DRUSH_ROOT="web"
USE_DDEV=true
COMPOSER_CONSTRAINT="^1.0@beta"
SCOPE="read,write"
EXEC_UID=1
GATEWAY=false
CURSOR_SERVER_NAME="drupal"

# Default site-building submodules (enable only what you need in drupal-config.yml)
DEFAULT_SUBMODULES=(
  mcp_tools
  mcp_tools_stdio
  mcp_tools_structure
  mcp_tools_views
  mcp_tools_blocks
  mcp_tools_menus
  mcp_tools_users
  mcp_tools_content
  mcp_tools_media
  mcp_tools_config
  mcp_tools_theme
  mcp_tools_webform
)

log() { echo "drupal: $*"; }
fail() { echo "drupal: ERROR: $*" >&2; exit 1; }
warn() { echo "drupal: WARN: $*" >&2; }

usage() {
  cat <<'EOF'
Usage: setup-mcp-tools.sh [--no-cursor-config]

Installs drupal/mcp_tools, enables configured submodules, and merges Cursor MCP config.

Configure via .specify/extensions/drupal/drupal-config.yml → mcp_tools:
  enabled, composer_constraint, submodules, scope, uid, cursor_server_name

Local dev only — not for production write access.
EOF
}

SKIP_CURSOR=false
[[ "${1:-}" == "--no-cursor-config" ]] && SKIP_CURSOR=true
[[ "${1:-}" == "-h" || "${1:-}" == "--help" ]] && { usage; exit 0; }

[[ -d "$PROJECT_ROOT/web/core" ]] || fail "Drupal not installed. Run /speckit-drupal-install first."

read_config() {
  [[ -f "$CONFIG_FILE" ]] || return 0

  local _c _s _u _g _n
  _c="$(grep -A20 '^mcp_tools:' "$CONFIG_FILE" 2>/dev/null | grep 'composer_constraint:' | head -1 | sed 's/.*: *//; s/"//g; s/#.*//' | tr -d ' ')"
  [[ -n "$_c" ]] && COMPOSER_CONSTRAINT="$_c"

  _s="$(grep -A20 '^mcp_tools:' "$CONFIG_FILE" 2>/dev/null | grep 'scope:' | head -1 | sed 's/.*: *//; s/"//g; s/#.*//' | tr -d ' ')"
  [[ -n "$_s" ]] && SCOPE="$_s"

  _u="$(grep -A20 '^mcp_tools:' "$CONFIG_FILE" 2>/dev/null | grep 'uid:' | head -1 | sed 's/.*: *//; s/#.*//' | tr -d ' ')"
  [[ -n "$_u" ]] && EXEC_UID="$_u"

  grep -A20 '^mcp_tools:' "$CONFIG_FILE" 2>/dev/null | grep -q 'gateway: true' && GATEWAY=true || true

  _n="$(grep -A20 '^mcp_tools:' "$CONFIG_FILE" 2>/dev/null | grep 'cursor_server_name:' | head -1 | sed 's/.*: *//; s/"//g; s/#.*//' | tr -d ' ')"
  [[ -n "$_n" ]] && CURSOR_SERVER_NAME="$_n"

  local _path
  _path="$(grep -A20 '^mcp_tools:' "$CONFIG_FILE" 2>/dev/null | grep 'cursor_config:' | head -1 | sed 's/.*: *//; s/"//g; s/#.*//' | tr -d ' ')"
  [[ -n "$_path" ]] && CURSOR_MCP="$PROJECT_ROOT/$_path"

  grep -qE 'use_ddev:\s*false' "$CONFIG_FILE" 2>/dev/null && USE_DDEV=false || true
  _root="$(grep -E 'drush_root:' "$CONFIG_FILE" 2>/dev/null | head -1 | sed 's/.*: *//' | sed 's/"//g' | sed 's/#.*//' | tr -d ' ')"
  [[ -n "$_root" ]] && DRUSH_ROOT="$_root"
}

drush_cmd() {
  if [[ "$USE_DDEV" == "true" ]] && command -v ddev >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/.ddev/config.yaml" ]]; then
    ddev drush "$@"
  else
    "$PROJECT_ROOT/vendor/bin/drush" -r "$PROJECT_ROOT/$DRUSH_ROOT" "$@"
  fi
}

composer_cmd() {
  if [[ "$USE_DDEV" == "true" ]] && command -v ddev >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/.ddev/config.yaml" ]]; then
    ddev composer "$@"
  else
    composer "$@"
  fi
}

module_enabled() {
  local mod="$1"
  drush_cmd pm:list --status=enabled --type=module --filter="$mod" 2>/dev/null | grep -qE '(Enabled|enabled)'
}

read_config

log "Installing drupal/mcp_tools (${COMPOSER_CONSTRAINT}) and drupal/tool (^1.0@alpha)..."
if ! composer_cmd show drupal/mcp_tools >/dev/null 2>&1; then
  composer_cmd require "drupal/mcp_tools:${COMPOSER_CONSTRAINT}" "drupal/tool:^1.0@alpha" --no-interaction
else
  log "drupal/mcp_tools already present in composer.json"
  if ! composer_cmd show drupal/tool >/dev/null 2>&1; then
    composer_cmd require "drupal/tool:^1.0@alpha" --no-interaction
  fi
fi

# Build enable list from config submodules or defaults
ENABLE_MODULES=()
if [[ -f "$CONFIG_FILE" ]] && grep -q '^[[:space:]]*- mcp_tools' "$CONFIG_FILE" 2>/dev/null; then
  while IFS= read -r line; do
    mod="$(echo "$line" | sed 's/^[[:space:]]*- //; s/#.*//; s/[[:space:]]*$//')"
    [[ -n "$mod" ]] && ENABLE_MODULES+=("$mod")
  done < <(grep -A50 '^  submodules:' "$CONFIG_FILE" 2>/dev/null | grep '^[[:space:]]*- mcp_tools' || true)
fi
if [[ ${#ENABLE_MODULES[@]} -eq 0 ]]; then
  ENABLE_MODULES=("${DEFAULT_SUBMODULES[@]}")
fi

# Skip webform submodule if webform not installed
if ! composer_cmd show drupal/webform >/dev/null 2>&1; then
  _filtered=()
  for m in "${ENABLE_MODULES[@]}"; do
    [[ "$m" == "mcp_tools_webform" ]] && warn "Skipping mcp_tools_webform (drupal/webform not installed)" && continue
    _filtered+=("$m")
  done
  ENABLE_MODULES=("${_filtered[@]}")
fi

log "Enabling MCP Tools modules: ${ENABLE_MODULES[*]}"
drush_cmd en "${ENABLE_MODULES[@]}" -y
drush_cmd cr

log "MCP Tools admin UI: /admin/config/services/mcp-tools"
log "Choose preset: Development (local), Staging (config-only), Production (read-only)"

if [[ "$SKIP_CURSOR" == "true" ]]; then
  log "Skipped Cursor MCP config (--no-cursor-config)"
  exit 0
fi

mkdir -p "$(dirname "$CURSOR_MCP")"

# Prefer official client-config from mcp_tools (DDEV-aware)
_CLIENT_JSON=""
if _out="$(drush_cmd mcp-tools:client-config --scope="$SCOPE" 2>/dev/null)"; then
  _CLIENT_JSON="$_out"
  log "Generated MCP client config via drush mcp-tools:client-config"
fi

export MCP_CLIENT_JSON="${_CLIENT_JSON}"
python3 - "$CURSOR_MCP" "$CURSOR_SERVER_NAME" "$PROJECT_ROOT" "$SCOPE" "$EXEC_UID" "$GATEWAY" "$USE_DDEV" <<'PY'
import json, sys, os

cursor_path, server_name, project_root, scope, uid, gateway, use_ddev = sys.argv[1:8]
client_json = os.environ.get("MCP_CLIENT_JSON", "").strip()

existing = {"mcpServers": {}}
path = os.path.abspath(cursor_path)
if os.path.isfile(path):
    with open(path, encoding="utf-8") as f:
        existing = json.load(f)
servers = existing.setdefault("mcpServers", {})

entry = None
if client_json:
    try:
        parsed = json.loads(client_json)
        if isinstance(parsed, dict) and "mcpServers" in parsed:
            entry = next(iter(parsed["mcpServers"].values()), None)
        elif isinstance(parsed, dict) and parsed.get("command"):
            entry = parsed
    except json.JSONDecodeError:
        pass

if entry is None:
    args = ["drush", "mcp-tools:serve", "--quiet", f"--uid={uid}", f"--scope={scope}"]
    if gateway.lower() == "true":
        args.append("--gateway")
    if use_ddev.lower() == "true" and os.path.isfile(os.path.join(project_root, ".ddev/config.yaml")):
        entry = {"command": "ddev", "args": args, "cwd": project_root}
    else:
        drush = os.path.join(project_root, "vendor/bin/drush")
        entry = {"command": drush, "args": args[1:], "cwd": project_root}
else:
    # client-config may emit container paths; Cursor on host needs project root for ddev
    if entry.get("command") == "ddev" and use_ddev.lower() == "true":
        entry["cwd"] = project_root

servers[server_name] = entry
os.makedirs(os.path.dirname(path), exist_ok=True)
with open(path, "w", encoding="utf-8") as f:
    json.dump(existing, f, indent=2)
    f.write("\n")
print(f"Wrote Cursor MCP server '{server_name}' → {path}")
PY

cat <<EOF

MCP Tools ready for local site building.

Cursor:
  1. Reload MCP in Cursor (Settings → MCP → refresh, or restart Cursor)
  2. Server name: ${CURSOR_SERVER_NAME}
  3. Config file: ${CURSOR_MCP#"$PROJECT_ROOT"/}

Workflow (see templates/mcp-tools-workflow.md):
  - Use MCP for [SB] tasks (content types, Views, Webform, blocks)
  - Then: ddev drush config:export -y && verify-foundational.sh
  - Theme [TH] and QA [DO] tasks stay in /speckit-implement

Command: /speckit-drupal-setup-mcp-tools
Docs:    .specify/extensions/drupal/templates/mcp-tools-workflow.md

EOF
