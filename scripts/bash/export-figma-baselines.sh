#!/usr/bin/env bash
# export-figma-baselines.sh — refresh committed screenshot baselines from live site
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FEATURE_DIR="${1:-}"

log() { echo "drupal: $*"; }
fail() { echo "drupal: ERROR: $*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage: export-figma-baselines.sh <FEATURE_DIR>

Captures Playwright screenshots from the local DDEV site into
specs/<feature>/figma-baselines/live/ (live-site regression captures; not QR-FIGMA-002 reference when baseline_source: figma).
EOF
}

[[ -n "$FEATURE_DIR" ]] || { usage; exit 1; }
[[ -f "$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml" ]] || fail "missing figma-design-checks.yml"

CHECKS_FILE="$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml"
BASELINES_DIR="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
require '$SCRIPT_DIR/figma-baseline-utils.php';
\$figma = figma_load_checks('$CHECKS_FILE');
echo figma_live_baselines_subpath(\$figma);
")"
OUT_DIR="$PROJECT_ROOT/$FEATURE_DIR/$BASELINES_DIR"
CONFIG_JSON="$(mktemp)"
trap 'rm -f "$CONFIG_JSON"' EXIT

php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
echo json_encode(\$c['figma'] ?? [], JSON_THROW_ON_ERROR);
" > "$CONFIG_JSON"

BASE_URL=""
if command -v ddev >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/.ddev/config.yaml" ]]; then
  BASE_URL="$(ddev describe 2>/dev/null | grep -oE 'https://[^ ]+\.ddev\.site' | head -1 || true)"
fi
[[ -n "$BASE_URL" ]] || fail "DDEV base URL not found"

EXTENSION_ROOT="$PROJECT_ROOT/.specify/extensions/drupal"
bash "$SCRIPT_DIR/ensure-figma-node-deps.sh" "$EXTENSION_ROOT"

mkdir -p "$OUT_DIR"
log "Exporting baselines to $OUT_DIR from $BASE_URL"
(cd "$EXTENSION_ROOT" && node "$SCRIPT_DIR/capture-figma-screenshots.mjs" "$CONFIG_JSON" "$OUT_DIR" "$BASE_URL" pages)
(cd "$EXTENSION_ROOT" && node "$SCRIPT_DIR/capture-figma-screenshots.mjs" "$CONFIG_JSON" "$OUT_DIR" "$BASE_URL" sections)
log "Baselines exported. Commit $OUT_DIR when satisfied."
