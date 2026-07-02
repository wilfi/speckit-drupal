#!/usr/bin/env bash
# finalize-implement.sh — post-implement Drupal ops
set -euo pipefail

PROJECT_ROOT="$(pwd)"
CONFIG_FILE="$PROJECT_ROOT/.specify/extensions/drupal/drupal-config.yml"

CACHE_REBUILD=true
CONFIG_EXPORT=false
PROVISION_ADMIN=false
DRUSH_ROOT="web"

log() { echo "drupal: $*"; }

if [[ -f "$CONFIG_FILE" ]] && command -v python3 >/dev/null 2>&1; then
  eval "$(python3 - "$CONFIG_FILE" <<'PY'
import sys, yaml
with open(sys.argv[1], encoding="utf-8") as f:
    cfg = yaml.safe_load(f) or {}
p = cfg.get("post_implement") or {}
for k, d in (("cache_rebuild", True), ("config_export", False), ("provision_admin", False)):
    v = p.get(k, d)
    print(f'{k}={"true" if v else "false"}')
if p.get("drush_root"):
    print(f'drush_root="{p["drush_root"]}"')
PY
)"
  CACHE_REBUILD="${cache_rebuild:-true}"
  CONFIG_EXPORT="${config_export:-false}"
  PROVISION_ADMIN="${provision_admin:-false}"
  DRUSH_ROOT="${drush_root:-web}"
fi

FEATURE_DIR=""
if [[ -f "$PROJECT_ROOT/.specify/feature.json" ]]; then
  FEATURE_DIR="$(grep -o '"feature_directory"[[:space:]]*:[[:space:]]*"[^"]*"' "$PROJECT_ROOT/.specify/feature.json" 2>/dev/null | sed 's/.*"\([^"]*\)"$/\1/' | head -1)"
fi

DRUSH="$PROJECT_ROOT/vendor/bin/drush"
if [[ ! -x "$DRUSH" ]]; then
  log "Drush not installed; run /speckit-drupal-setup first. Skipping finalize ops."
  exit 0
fi

if [[ "$PROVISION_ADMIN" == "true" && -n "$FEATURE_DIR" && -f "$PROJECT_ROOT/$FEATURE_DIR/drupal-admin-checklist.yml" ]]; then
  log "Provisioning admin components from $FEATURE_DIR/drupal-admin-checklist.yml..."
  "$DRUSH" -r "$PROJECT_ROOT/$DRUSH_ROOT" php:script "$PROJECT_ROOT/.specify/extensions/drupal/scripts/bash/provision-admin-components.php" "$FEATURE_DIR" || exit 1
elif [[ "$PROVISION_ADMIN" == "true" ]]; then
  log "provision_admin enabled but no checklist at $FEATURE_DIR/drupal-admin-checklist.yml — skipping"
fi

if [[ "$CACHE_REBUILD" == "true" ]]; then
  log "Rebuilding caches..."
  "$DRUSH" -r "$PROJECT_ROOT/$DRUSH_ROOT" cr
fi

if [[ "$CONFIG_EXPORT" == "true" ]]; then
  log "Exporting configuration..."
  "$DRUSH" -r "$PROJECT_ROOT/$DRUSH_ROOT" config:export -y
  log "Review and commit config/sync/ if changed"
else
  log "Config export disabled (set post_implement.config_export: true to enable)"
fi

# Quality rules (QR-PERF-001, QR-A11Y-001)
RUN_QUALITY=true
if [[ -f "$CONFIG_FILE" ]]; then
  grep -qE 'run_verify:\s*false' "$CONFIG_FILE" 2>/dev/null && RUN_QUALITY=false || true
  grep -qE 'quality_rules:.*enabled:\s*false' "$CONFIG_FILE" 2>/dev/null && RUN_QUALITY=false || true
fi

TRY_EXPORT="$PROJECT_ROOT/.specify/extensions/drupal/scripts/bash/try-export-figma-baselines.sh"
if [[ -x "$TRY_EXPORT" && -n "$FEATURE_DIR" && -f "$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml" ]]; then
  REQUIRE_FLAG=""
  grep -qE 'require_baselines_at_polish:\s*true' "$CONFIG_FILE" 2>/dev/null && REQUIRE_FLAG="--require"
  bash "$TRY_EXPORT" --when=polish_only $REQUIRE_FLAG "$FEATURE_DIR" || exit 1
fi

if [[ "$RUN_QUALITY" == "true" && -x "$PROJECT_ROOT/.specify/extensions/drupal/scripts/bash/verify-quality.sh" ]]; then
  log "Running quality rules verification..."
  if [[ -n "$FEATURE_DIR" ]]; then
    "$PROJECT_ROOT/.specify/extensions/drupal/scripts/bash/verify-quality.sh" "$FEATURE_DIR" || exit 1
    if [[ -f "$PROJECT_ROOT/$FEATURE_DIR/quality-results.md" ]]; then
      log "Stakeholder QA report: $FEATURE_DIR/quality-results.md"
    fi
  else
    "$PROJECT_ROOT/.specify/extensions/drupal/scripts/bash/verify-quality.sh" || exit 1
  fi
fi

log "Finalize complete."
