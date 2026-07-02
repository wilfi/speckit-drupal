#!/usr/bin/env bash
# check-figma-screenshot.sh — QR-FIGMA-002 visual regression vs Figma baselines
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FEATURE_DIR="${1:-}"

log() { echo "drupal: $*"; }
fail() { echo "drupal: QUALITY FAIL: $*" >&2; exit 1; }

[[ -n "$FEATURE_DIR" ]] || exit 0
[[ -f "$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml" ]] || exit 0

CHECKS_FILE="$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml"

ENABLED="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
\$e = \$c['figma']['screenshot']['enabled'] ?? false;
echo (\$e === false || \$e === 'false' || \$e === 0) ? 'false' : 'true';
" 2>/dev/null || echo "false")"

[[ "$ENABLED" == "true" ]] || {
  log "QR-FIGMA-002: screenshot diff disabled"
  exit 0
}

command -v npx >/dev/null 2>&1 || fail "QR-FIGMA-002: npx required for Playwright screenshot capture"

BASE_URL=""
if command -v ddev >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/.ddev/config.yaml" ]]; then
  BASE_URL="$(ddev describe 2>/dev/null | grep -oE 'https://[^ ]+\.ddev\.site' | head -1 || true)"
fi
[[ -n "$BASE_URL" ]] || BASE_URL="http://localhost"

BASELINES_DIR="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
require '$SCRIPT_DIR/figma-baseline-utils.php';
\$figma = figma_load_checks('$CHECKS_FILE');
echo figma_reference_baselines_subpath(\$figma);
")"
BASELINES_PATH="$PROJECT_ROOT/$FEATURE_DIR/$BASELINES_DIR"
ACTUAL_DIR="$(mktemp -d)"
DIFF_DIR="$BASELINES_PATH/diffs"
CONFIG_JSON="$(mktemp)"
trap 'rm -rf "$ACTUAL_DIR" "$CONFIG_JSON"' EXIT

php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
echo json_encode(\$c['figma'] ?? [], JSON_THROW_ON_ERROR);
" > "$CONFIG_JSON"

EXTENSION_ROOT="$PROJECT_ROOT/.specify/extensions/drupal"
run_node() {
  (cd "$EXTENSION_ROOT" && node "$@")
}

# Ensure Playwright chromium is available.
if ! (cd "$EXTENSION_ROOT" && npx playwright install chromium >/dev/null 2>&1); then
  log "QR-FIGMA-002: installing Playwright chromium..."
  (cd "$EXTENSION_ROOT" && npx playwright install chromium) || fail "QR-FIGMA-002: playwright install failed"
fi

log "QR-FIGMA-002: capturing screenshots from $BASE_URL"
run_node "$SCRIPT_DIR/capture-figma-screenshots.mjs" "$CONFIG_JSON" "$ACTUAL_DIR" "$BASE_URL" pages || fail "QR-FIGMA-002: screenshot capture failed"
run_node "$SCRIPT_DIR/capture-figma-screenshots.mjs" "$CONFIG_JSON" "$ACTUAL_DIR" "$BASE_URL" sections || fail "QR-FIGMA-002: section capture failed"

THRESHOLD="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
echo \$c['figma']['screenshot']['pixel_threshold'] ?? 0.15;
")"

MAX_FULL="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
echo \$c['figma']['screenshot']['max_diff_percent'] ?? 15;
")"

compare_pair() {
  local name="$1"
  local baseline="$2"
  local actual="$3"
  local max_pct="$4"
  local optional="${5:-false}"
  [[ -f "$baseline" ]] || {
    if [[ "$optional" == "true" ]]; then
      log "QR-FIGMA-002: WARN — $name baseline missing (optional — export Figma child node for atomic component)"
      return 0
    fi
    fail "QR-FIGMA-002: missing baseline $baseline (run export-figma-source-baselines.sh)"
  }
  [[ -f "$actual" ]] || fail "QR-FIGMA-002: missing actual screenshot $actual"
  local diff_out="$DIFF_DIR/${name}-diff.png"
  mkdir -p "$DIFF_DIR"
  if run_node "$SCRIPT_DIR/compare-screenshots.mjs" "$baseline" "$actual" "$diff_out" "$max_pct" "$THRESHOLD"; then
    log "QR-FIGMA-002: OK — $name within ${max_pct}% diff"
  else
    fail "QR-FIGMA-002: $name exceeds ${max_pct}% diff (see $diff_out)"
  fi
}

# Full page comparisons.
while IFS=$'\t' read -r baseline max_pct; do
  [[ -n "$baseline" ]] || continue
  name="${baseline%.png}"
  compare_pair "$name" "$BASELINES_PATH/$baseline" "$ACTUAL_DIR/$baseline" "${max_pct:-$MAX_FULL}"
done < <(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
\$max = \$c['figma']['screenshot']['max_diff_percent'] ?? 15;
foreach (\$c['figma']['screenshot']['pages'] ?? [] as \$p) {
  echo (\$p['baseline'] ?? ''), \"\\t\", (\$p['max_diff_percent'] ?? \$max), PHP_EOL;
}
")

# Section comparisons.
while IFS=$'\t' read -r name baseline max_pct optional; do
  [[ -n "$baseline" ]] || continue
  compare_pair "section-$name" "$BASELINES_PATH/$baseline" "$ACTUAL_DIR/$baseline" "$max_pct" "$optional"
done < <(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
foreach (\$c['figma']['screenshot']['sections'] ?? [] as \$s) {
  \$opt = (!empty(\$s['optional_baseline'])) ? 'true' : 'false';
  echo (\$s['name'] ?? 'section'), \"\\t\", (\$s['baseline'] ?? ''), \"\\t\", (\$s['max_diff_percent'] ?? 10), \"\\t\", \$opt, PHP_EOL;
}
")

log "QR-FIGMA-002: screenshot diff passed."

# QR-FIGMA-003 — Figma-sourced component baselines vs live captures.
COMPONENTS_ENABLED="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
\$comps = \$c['figma']['screenshot']['components'] ?? [];
\$e = \$comps['enabled'] ?? false;
echo (\$e === false || \$e === 'false' || \$e === 0) ? 'false' : 'true';
" 2>/dev/null || echo "false")"

if [[ "$COMPONENTS_ENABLED" == "true" ]]; then
  COMPONENTS_SUBDIR="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
echo \$c['figma']['screenshot']['components']['baselines_subdir'] ?? 'figma-baselines/components';
")"
  COMPONENTS_BASELINE_PATH="$PROJECT_ROOT/$FEATURE_DIR/$COMPONENTS_SUBDIR"
  COMPONENTS_ACTUAL_DIR="$(mktemp -d)"
  COMPONENTS_DIFF_DIR="$COMPONENTS_BASELINE_PATH/diffs"
  COMP_THRESHOLD="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
echo \$c['figma']['screenshot']['components']['pixel_threshold'] ?? (\$c['figma']['screenshot']['pixel_threshold'] ?? 0.1);
")"
  COMP_MAX="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
echo \$c['figma']['screenshot']['components']['max_diff_percent'] ?? 5;
")"

  log "QR-FIGMA-003: capturing component screenshots from $BASE_URL"
  run_node "$SCRIPT_DIR/capture-figma-screenshots.mjs" "$CONFIG_JSON" "$COMPONENTS_ACTUAL_DIR" "$BASE_URL" components || fail "QR-FIGMA-003: component capture failed"

  compare_component() {
    local name="$1"
    local baseline="$2"
    local actual="$3"
    local max_pct="$4"
    [[ -f "$baseline" ]] || fail "QR-FIGMA-003: missing Figma baseline $baseline (run export-figma-component-baselines.sh)"
    [[ -f "$actual" ]] || fail "QR-FIGMA-003: missing actual $actual"
    local diff_out="$COMPONENTS_DIFF_DIR/${name}-diff.png"
    mkdir -p "$COMPONENTS_DIFF_DIR"
    if run_node "$SCRIPT_DIR/compare-screenshots.mjs" "$baseline" "$actual" "$diff_out" "$max_pct" "$COMP_THRESHOLD"; then
      log "QR-FIGMA-003: OK — $name within ${max_pct}% diff"
    else
      fail "QR-FIGMA-003: $name exceeds ${max_pct}% diff (see $diff_out)"
    fi
  }

  while IFS=$'\t' read -r name baseline max_pct; do
    [[ -n "$baseline" ]] || continue
    compare_component "$name" "$COMPONENTS_BASELINE_PATH/$baseline" "$COMPONENTS_ACTUAL_DIR/$baseline" "${max_pct:-$COMP_MAX}"
  done < <(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
\$max = \$c['figma']['screenshot']['components']['max_diff_percent'] ?? 5;
foreach (\$c['figma']['screenshot']['components']['items'] ?? [] as \$item) {
  echo (\$item['name'] ?? 'component'), \"\\t\", (\$item['baseline'] ?? ''), \"\\t\", (\$item['max_diff_percent'] ?? \$max), PHP_EOL;
}
")

  rm -rf "$COMPONENTS_ACTUAL_DIR"
  log "QR-FIGMA-003: component pixel diff passed."
fi
