#!/usr/bin/env bash
# export-figma-component-baselines.sh — export Figma node PNGs for QR-FIGMA-003
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FEATURE_DIR="${1:-}"

log() { echo "drupal: $*"; }
fail() { echo "drupal: ERROR: $*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage: export-figma-component-baselines.sh <FEATURE_DIR>

Exports PNG baselines from Figma for each screenshot.components item in
figma-design-checks.yml. Requires FIGMA_ACCESS_TOKEN or uses Figma MCP asset URLs
when export-figma-component-baselines.mjs finds cached node exports.

Set FIGMA_ACCESS_TOKEN for CI: https://www.figma.com/developers/api#access-tokens
EOF
}

[[ -n "$FEATURE_DIR" ]] || { usage; exit 1; }
CHECKS_FILE="$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml"
[[ -f "$CHECKS_FILE" ]] || fail "missing figma-design-checks.yml"

EXTENSION_ROOT="$PROJECT_ROOT/.specify/extensions/drupal"
command -v node >/dev/null 2>&1 || fail "node required"

php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS_FILE');
\$figma = \$c['figma'] ?? [];
\$comps = \$figma['screenshot']['components'] ?? [];
if (empty(\$comps['enabled']) && empty(\$comps['items'])) {
  exit(0);
}
\$subdir = \$comps['baselines_subdir'] ?? 'figma-baselines/components';
\$out = '$PROJECT_ROOT/$FEATURE_DIR/' . trim(\$subdir, '/');
\$payload = [
  'file_key' => \$figma['file_key'] ?? '',
  'out_dir' => \$out,
  'items' => \$comps['items'] ?? [],
];
echo json_encode(\$payload, JSON_THROW_ON_ERROR);
" > /tmp/figma-component-export.json

ITEM_COUNT="$(node -e "const p=require('/tmp/figma-component-export.json'); console.log((p.items||[]).length)")"
[[ "$ITEM_COUNT" -gt 0 ]] || { log "No screenshot.components items configured"; exit 0; }

OUT_DIR="$(node -e "console.log(require('/tmp/figma-component-export.json').out_dir)")"
mkdir -p "$OUT_DIR"
log "Exporting Figma component baselines to $OUT_DIR"
(cd "$EXTENSION_ROOT" && node "$SCRIPT_DIR/export-figma-component-baselines.mjs" /tmp/figma-component-export.json) || fail "Figma component export failed"
log "Component baselines exported. Commit $OUT_DIR when satisfied."
