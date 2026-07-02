#!/usr/bin/env bash
# setup-workflow.sh — Drupal Spec Kit workflow setup
set -euo pipefail

PROJECT_ROOT="$(pwd)"
EXT_DIR="$PROJECT_ROOT/.specify/extensions/drupal"
CONFIG_FILE="$EXT_DIR/drupal-config.yml"
TEMPLATES_SRC="$EXT_DIR/templates"
BACKUP_DIR="$PROJECT_ROOT/.specify/templates/.backup-drupal"

log() { echo "drupal: $*"; }
fail() { echo "drupal: ERROR: $*" >&2; exit 1; }

[[ -d "$PROJECT_ROOT/web/core" ]] || fail "Drupal not installed. Run /speckit-drupal-install first."

# Defaults
INSTALL_TEMPLATES=true
INSTALL_DRUSH=true
CONFIG_SYNC_DIR="config/sync"
CUSTOM_MODULES="web/modules/custom"
CUSTOM_THEMES="web/themes/custom"
SCAFFOLD_DDEV=true
DDEV_PHP="8.3"
CONFIGURE_SETTINGS=true

if [[ -f "$CONFIG_FILE" ]]; then
  if command -v python3 >/dev/null 2>&1; then
    _cfg_out="$(python3 - "$CONFIG_FILE" <<'PY' 2>/dev/null || true
import sys
try:
    import yaml
except ImportError:
    sys.exit(0)
with open(sys.argv[1], encoding="utf-8") as f:
    cfg = yaml.safe_load(f) or {}
w = cfg.get("workflow") or {}
def emit(k, v):
    if isinstance(v, bool):
        print(f'{k}={"true" if v else "false"}')
    elif v is not None:
        print(f'{k}="{v}"')
for k in ("install_templates", "install_drush", "scaffold_ddev", "configure_settings_sync"):
    emit(k, w.get(k, True))
for k in ("config_sync_dir", "custom_modules_dir", "custom_themes_dir", "ddev_php_version"):
    emit(k, w.get(k))
PY
)"
    if [[ -n "$_cfg_out" ]]; then
      eval "$_cfg_out"
    fi
  fi
  # Fallback when PyYAML unavailable: simple grep for common keys
  if [[ -z "${install_templates:-}" ]]; then
    grep -q 'install_templates: false' "$CONFIG_FILE" 2>/dev/null && INSTALL_TEMPLATES=false || true
    grep -q 'install_drush: false' "$CONFIG_FILE" 2>/dev/null && INSTALL_DRUSH=false || true
    grep -q 'scaffold_ddev: false' "$CONFIG_FILE" 2>/dev/null && SCAFFOLD_DDEV=false || true
  fi
fi

INSTALL_TEMPLATES="${install_templates:-$INSTALL_TEMPLATES}"
INSTALL_DRUSH="${install_drush:-$INSTALL_DRUSH}"
SCAFFOLD_DDEV="${scaffold_ddev:-$SCAFFOLD_DDEV}"
CONFIGURE_SETTINGS="${configure_settings_sync:-$CONFIGURE_SETTINGS}"
CONFIG_SYNC_DIR="${config_sync_dir:-$CONFIG_SYNC_DIR}"
CUSTOM_MODULES="${custom_modules_dir:-$CUSTOM_MODULES}"
CUSTOM_THEMES="${custom_themes_dir:-$CUSTOM_THEMES}"
DDEV_PHP="${ddev_php_version:-$DDEV_PHP}"

# Drush
if [[ "$INSTALL_DRUSH" == "true" ]] && [[ ! -x "$PROJECT_ROOT/vendor/bin/drush" ]]; then
  log "Installing Drush..."
  composer require drush/drush --no-interaction
fi

# QR-ASSET checks (CssSelectorConverter for scoped asset verification)
if [[ -f "$PROJECT_ROOT/composer.json" ]] && command -v composer >/dev/null 2>&1; then
  if ! php -r "require '$PROJECT_ROOT/vendor/autoload.php'; exit(class_exists('Symfony\\\\Component\\\\CssSelector\\\\CssSelectorConverter') ? 0 : 1);" 2>/dev/null; then
    log "Installing symfony/css-selector for QR-ASSET-001 scoped checks..."
    (cd "$PROJECT_ROOT" && composer require symfony/css-selector:^7.0 --no-interaction)
  fi
fi

# Directories
mkdir -p "$PROJECT_ROOT/$CONFIG_SYNC_DIR"
mkdir -p "$PROJECT_ROOT/$CUSTOM_MODULES"
mkdir -p "$PROJECT_ROOT/$CUSTOM_THEMES"
touch "$PROJECT_ROOT/$CUSTOM_MODULES/.gitkeep"
touch "$PROJECT_ROOT/$CUSTOM_THEMES/.gitkeep"
log "Created: $CONFIG_SYNC_DIR, $CUSTOM_MODULES, $CUSTOM_THEMES"

# settings.php config sync
SETTINGS="$PROJECT_ROOT/web/sites/default/settings.php"
DEFAULT_SETTINGS="$PROJECT_ROOT/web/sites/default/default.settings.php"
if [[ "$CONFIGURE_SETTINGS" == "true" ]]; then
  if [[ ! -f "$SETTINGS" && -f "$DEFAULT_SETTINGS" ]]; then
    cp "$DEFAULT_SETTINGS" "$SETTINGS"
    log "Created settings.php from default"
  fi
  if [[ -f "$SETTINGS" ]] && ! grep -qE "^\s*\\\$settings\['config_sync_directory'\]\s*=" "$SETTINGS"; then
    SYNC_ABS="$PROJECT_ROOT/$CONFIG_SYNC_DIR"
    cat >> "$SETTINGS" <<EOF

// Spec Kit Drupal extension: config sync directory
\$settings['config_sync_directory'] = dirname(DRUPAL_ROOT) . '/config/sync';
EOF
    log "Appended config_sync_directory to settings.php"
  fi
fi

# DDEV
if [[ "$SCAFFOLD_DDEV" == "true" ]] && command -v ddev >/dev/null 2>&1; then
  if [[ ! -f "$PROJECT_ROOT/.ddev/config.yaml" ]]; then
    PROJECT_NAME="$(basename "$PROJECT_ROOT" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9-' '-' | sed 's/-\+/-/g; s/^-//; s/-$//')"
    [[ -n "$PROJECT_NAME" ]] || PROJECT_NAME="drupal-project"
    mkdir -p "$PROJECT_ROOT/.ddev"
    sed -e "s/{{PROJECT_NAME}}/$PROJECT_NAME/" \
        -e "s/{{PHP_VERSION}}/$DDEV_PHP/" \
        "$TEMPLATES_SRC/ddev-config.yaml" > "$PROJECT_ROOT/.ddev/config.yaml"
    log "Scaffolded .ddev/config.yaml (project: $PROJECT_NAME)"
    log "Run: ddev start"
  else
    log ".ddev/config.yaml already exists; skipping"
  fi
elif [[ "$SCAFFOLD_DDEV" == "true" ]]; then
  log "DDEV not on PATH; skipped .ddev scaffold"
fi

# Templates
if [[ "$INSTALL_TEMPLATES" == "true" && -d "$TEMPLATES_SRC" ]]; then
  mkdir -p "$BACKUP_DIR"
  for t in spec-template.md plan-template.md tasks-template.md; do
    if [[ -f "$PROJECT_ROOT/.specify/templates/$t" ]]; then
      cp "$PROJECT_ROOT/.specify/templates/$t" "$BACKUP_DIR/$t"
    fi
    if [[ -f "$TEMPLATES_SRC/$t" ]]; then
      cp "$TEMPLATES_SRC/$t" "$PROJECT_ROOT/.specify/templates/$t"
      log "Installed template: $t"
    fi
  done
  log "Original templates backed up to .specify/templates/.backup-drupal/"
fi

# Project-level Drupal context (.specify/drupal/)
if [[ -x "$EXT_DIR/scripts/bash/scaffold-project-context.sh" ]]; then
  bash "$EXT_DIR/scripts/bash/scaffold-project-context.sh"
fi

# Sync extension agent skills → .cursor/skills/
SYNC_SKILLS=true
if [[ -f "$CONFIG_FILE" ]] && grep -qE 'sync_cursor_skills:\s*false' "$CONFIG_FILE" 2>/dev/null; then
  SYNC_SKILLS=false
fi
if [[ "$SYNC_SKILLS" == "true" && -x "$EXT_DIR/scripts/bash/sync-cursor-skills.sh" ]]; then
  bash "$EXT_DIR/scripts/bash/sync-cursor-skills.sh"
fi

# Figma screenshot tooling (Playwright + pixelmatch)
if [[ -x "$EXT_DIR/scripts/bash/ensure-figma-node-deps.sh" ]] \
  && command -v node >/dev/null 2>&1 && command -v npm >/dev/null 2>&1; then
  bash "$EXT_DIR/scripts/bash/ensure-figma-node-deps.sh" "$EXT_DIR" || log "WARN: Figma npm deps skipped (install node/npm for QR-FIGMA-002)"
fi

# Project runbook at repo root (README-SPECKIT-DRUPAL.md)
RUNBOOK_TEMPLATE="$TEMPLATES_SRC/project-runbook-template.md"
RUNBOOK_OUT="$PROJECT_ROOT/README-SPECKIT-DRUPAL.md"
if [[ -f "$RUNBOOK_TEMPLATE" ]]; then
  PROJECT_NAME="$(basename "$PROJECT_ROOT")"
  [[ -n "$PROJECT_NAME" ]] || PROJECT_NAME="drupal-project"
  GENERATED_AT="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
  sed -e "s/{{PROJECT_NAME}}/$PROJECT_NAME/g" \
      -e "s/{{GENERATED_AT}}/$GENERATED_AT/g" \
      -e "s|{{CONFIG_SYNC_DIR}}|$CONFIG_SYNC_DIR|g" \
      "$RUNBOOK_TEMPLATE" > "$RUNBOOK_OUT"
  log "Generated README-SPECKIT-DRUPAL.md"
fi

# Greenfield pixel-perfect runbook at repo root (static copy from extension)
GREENFIELD_SRC="$EXT_DIR/GREENFIELD-RUNBOOK.md"
GREENFIELD_OUT="$PROJECT_ROOT/GREENFIELD-RUNBOOK.md"
if [[ -f "$GREENFIELD_SRC" ]]; then
  cp "$GREENFIELD_SRC" "$GREENFIELD_OUT"
  log "Copied GREENFIELD-RUNBOOK.md to project root"
fi

cat <<EOF

Drupal Spec Kit workflow ready.

Next:
  ddev start && ddev drush site:install -y    # if using DDEV
  /speckit-drupal-setup-mcp-tools             # optional: AI site building via MCP
  /speckit-specify "your feature"
  /speckit-plan
  /speckit-drupal-verify-plan

Project Drupal context (shared across features):
  .specify/drupal/data-model.md
  .specify/drupal/site-structure.md
  .specify/drupal/sites.yml

Runbook:
  README-SPECKIT-DRUPAL.md
  GREENFIELD-RUNBOOK.md
  .specify/extensions/drupal/RUNBOOK.md

EOF
