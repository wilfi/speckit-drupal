#!/usr/bin/env bash
# verify-figma-section.sh — QR-FIGMA-002 for named sections; writes JSON report for fix-loop
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FEATURE_DIR=""
SECTION_FILTER=""

log() { echo "drupal: $*"; }
fail() { echo "drupal: QUALITY FAIL: $*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage: verify-figma-section.sh <FEATURE_DIR> [section-slug ...]

Verify screenshot diff for one or more sections (default: all sections).
Writes specs/<feature>/figma-baselines/reports/latest.json and figma-fix-queue.md.

Exit 0 = pass; non-zero = at least one section failed.
EOF
}

[[ $# -ge 1 ]] || { usage; exit 1; }
FEATURE_DIR="$1"
shift
if [[ $# -gt 0 ]]; then
  SECTION_FILTER="$(IFS=,; echo "$*")"
fi
export SECTION_FILTER

CHECKS="$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml"
[[ -f "$CHECKS" ]] || fail "missing figma-design-checks.yml"

REPORT_DIR="$PROJECT_ROOT/$FEATURE_DIR/figma-baselines/reports"
mkdir -p "$REPORT_DIR"

BASE_URL=""
if command -v ddev >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/.ddev/config.yaml" ]]; then
  BASE_URL="$(ddev describe 2>/dev/null | grep -oE 'https://[^ ]+\.ddev\.site' | head -1 || true)"
fi
[[ -n "$BASE_URL" ]] || BASE_URL="http://localhost"

EXTENSION_ROOT="$PROJECT_ROOT/.specify/extensions/drupal"
CONFIG_JSON="$(mktemp)"
ACTUAL_DIR="$(mktemp -d)"
trap 'rm -f "$CONFIG_JSON"; rm -rf "$ACTUAL_DIR"' EXIT

php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS');
\$figma = \$c['figma'] ?? [];
\$filter = array_filter(array_map('trim', explode(',', getenv('SECTION_FILTER') ?: '')));
if (\$filter !== []) {
  \$figma['screenshot']['pages'] = [];
  \$figma['screenshot']['components']['enabled'] = false;
  \$figma['screenshot']['sections'] = array_values(array_filter(
    \$figma['screenshot']['sections'] ?? [],
    fn(\$s) => in_array(\$s['name'] ?? '', \$filter, true)
  ));
}
echo json_encode(\$figma, JSON_THROW_ON_ERROR);
" > "$CONFIG_JSON"

bash "$SCRIPT_DIR/ensure-figma-node-deps.sh" "$EXTENSION_ROOT"

BASELINES_DIR="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
require '$SCRIPT_DIR/figma-baseline-utils.php';
\$figma = figma_load_checks('$CHECKS');
echo figma_reference_baselines_subpath(\$figma);
")"
BASELINES_PATH="$PROJECT_ROOT/$FEATURE_DIR/$BASELINES_DIR"
DIFF_DIR="$BASELINES_PATH/diffs"
THRESHOLD="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
echo Yaml::parseFile('$CHECKS')['figma']['screenshot']['pixel_threshold'] ?? 0.1;
")"

run_node() { (cd "$EXTENSION_ROOT" && node "$@"); }

log "Capturing sections from $BASE_URL"
run_node "$SCRIPT_DIR/capture-figma-screenshots.mjs" "$CONFIG_JSON" "$ACTUAL_DIR" "$BASE_URL" sections \
  || fail "section capture failed"

FAIL_COUNT=0
RESULT_FILE="$(mktemp)"
: > "$RESULT_FILE"

while IFS=$'\t' read -r name baseline max_pct selector; do
  [[ -n "$baseline" ]] || continue
  diff_out="$DIFF_DIR/${name}-diff.png"
  mkdir -p "$DIFF_DIR"
  status="pass"
  if [[ ! -f "$BASELINES_PATH/$baseline" ]]; then
    status="missing_baseline"
    FAIL_COUNT=$((FAIL_COUNT + 1))
    log "FAIL section-$name: missing baseline $baseline (run export-figma-source-baselines.sh)"
  elif run_node "$SCRIPT_DIR/compare-screenshots.mjs" "$BASELINES_PATH/$baseline" "$ACTUAL_DIR/$baseline" "$diff_out" "$max_pct" "$THRESHOLD" 2>/dev/null; then
    log "OK section-$name"
  else
    status="fail"
    FAIL_COUNT=$((FAIL_COUNT + 1))
    log "FAIL section-$name exceeds ${max_pct}% (see $diff_out)"
  fi
  printf '%s\t%s\t%s\t%s\t%s\n' "$name" "$status" "$selector" "$baseline" "${diff_out#$PROJECT_ROOT/}" >> "$RESULT_FILE"
done < <(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS');
\$filter = array_filter(array_map('trim', explode(',', getenv('SECTION_FILTER') ?: '')));
foreach (\$c['figma']['screenshot']['sections'] ?? [] as \$s) {
  \$name = \$s['name'] ?? 'section';
  if (\$filter !== [] && !in_array(\$name, \$filter, true)) continue;
  echo \$name, \"\\t\", (\$s['baseline'] ?? ''), \"\\t\", (\$s['max_diff_percent'] ?? 1), \"\\t\", (\$s['selector'] ?? ''), PHP_EOL;
}
")

export PROJECT_ROOT FEATURE_DIR FAIL_COUNT RESULT_FILE
php <<'PHPEOF'
<?php
$projectRoot = getenv('PROJECT_ROOT');
$featureDir = getenv('FEATURE_DIR');
$resultFile = getenv('RESULT_FILE');
$failCount = (int) getenv('FAIL_COUNT');
$rows = [];
if (is_file($resultFile)) {
  foreach (file($resultFile) as $line) {
    $p = explode("\t", trim($line));
    if (count($p) < 5) {
      continue;
    }
    $rows[] = [
      'slug' => $p[0],
      'status' => $p[1],
      'selector' => $p[2],
      'baseline' => $p[3],
      'diff_image' => $p[4],
    ];
  }
}
$report = [
  'timestamp' => gmdate('c'),
  'feature_dir' => $featureDir,
  'failed_count' => $failCount,
  'sections' => $rows,
];
$dir = $projectRoot . '/' . $featureDir . '/figma-baselines/reports';
@mkdir($dir, 0755, true);
file_put_contents($dir . '/latest.json', json_encode($report, JSON_PRETTY_PRINT));
$md = "# Figma fix queue\n\n**Generated**: {$report['timestamp']}\n\n";
$md .= "| Section | Status | Diff | Selector |\n|---------|--------|------|----------|\n";
foreach ($rows as $r) {
  $md .= '| ' . $r['slug'] . ' | ' . $r['status'] . ' | ' . $r['diff_image'] . ' | `' . $r['selector'] . '` |' . "\n";
}
file_put_contents($projectRoot . '/' . $featureDir . '/figma-fix-queue.md', $md);
PHPEOF

[[ "$FAIL_COUNT" -eq 0 ]] || fail "$FAIL_COUNT section(s) failed — see $FEATURE_DIR/figma-fix-queue.md"
log "Section verify passed ($FEATURE_DIR/figma-baselines/reports/latest.json)"
