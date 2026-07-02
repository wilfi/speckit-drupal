#!/usr/bin/env bash
# setup-feature-artifacts.sh — scaffold per-feature quality + Figma YAML artifacts
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FEATURE_DIR=""
FORCE=false
EXPORT_BASELINES=false

log() { echo "drupal: $*"; }
fail() { echo "drupal: FAIL: $*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage: setup-feature-artifacts.sh [OPTIONS] [FEATURE_DIR]

Scaffold or merge specs/<feature>/ quality + Figma artifacts from extension templates:
  - quality-checks.yml (assets.enabled: true for Figma features)
  - figma-design-checks.yml (from design-context.md file_key / node_id)
  - figma-asset-manifest.yml
  - foundational-checklist.yml figma block
  - figma-baselines/ directory

Runs automatically via after_plan hook (/speckit-drupal-setup-feature-artifacts).

Options:
  --force              Overwrite figma-design-checks.yml from template
  --export-baselines   Also run export-figma-baselines.sh (needs DDEV + themed site)
  -h, --help           Show this help
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --force) FORCE=true; shift ;;
    --export-baselines) EXPORT_BASELINES=true; shift ;;
    -h|--help) usage; exit 0 ;;
    *)
      FEATURE_DIR="$1"
      shift
      ;;
  esac
done

[[ -x "$PROJECT_ROOT/vendor/bin/drush" || -f "$PROJECT_ROOT/composer.json" ]] \
  || fail "Composer vendor/ required (Symfony YAML). Run composer install."

ARGS=(--feature="$FEATURE_DIR")
[[ "$FORCE" == "true" ]] && ARGS+=(--force)
[[ "$EXPORT_BASELINES" == "true" ]] && ARGS+=(--export-baselines)

export PROJECT_ROOT
php "$SCRIPT_DIR/setup-feature-artifacts.php" "${ARGS[@]}"
