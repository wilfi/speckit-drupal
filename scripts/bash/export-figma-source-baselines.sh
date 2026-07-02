#!/usr/bin/env bash
# export-figma-source-baselines.sh — export PNG baselines from Figma API (QR-FIGMA-002 reference)
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EXTENSION_ROOT="$PROJECT_ROOT/.specify/extensions/drupal"
FEATURE_DIR="${1:-}"
REQUIRE=false

log() { echo "drupal: $*"; }
fail() { echo "drupal: ERROR: $*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage: export-figma-source-baselines.sh [OPTIONS] <FEATURE_DIR>

Export section/page PNG baselines from Figma (figma_node_id in figma-regions.yml /
figma-design-checks.yml) into specs/<feature>/figma-baselines/figma-source/.

Requires FIGMA_ACCESS_TOKEN or pre-placed PNGs in the output directory.

Options:
  --require   Fail if export cannot complete
  -h, --help
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --require) REQUIRE=true; shift ;;
    -h|--help) usage; exit 0 ;;
    *) FEATURE_DIR="$1"; shift ;;
  esac
done

[[ -n "$FEATURE_DIR" ]] || { usage; exit 1; }
CHECKS_FILE="$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml"
REGIONS_FILE="$PROJECT_ROOT/$FEATURE_DIR/figma-regions.yml"
[[ -f "$CHECKS_FILE" ]] || fail "missing figma-design-checks.yml"

bash "$SCRIPT_DIR/ensure-figma-node-deps.sh" "$EXTENSION_ROOT"

CONFIG_JSON="$(mktemp)"
trap 'rm -f "$CONFIG_JSON"' EXIT

php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
require '$SCRIPT_DIR/figma-baseline-utils.php';
\$figma = figma_load_checks('$CHECKS_FILE');
\$regions = is_file('$REGIONS_FILE') ? (Symfony\Component\Yaml\Yaml::parseFile('$REGIONS_FILE') ?: []) : [];
\$outRel = figma_reference_baselines_subpath(\$figma);
\$outAbs = '$PROJECT_ROOT/$FEATURE_DIR/' . \$outRel;
@mkdir(\$outAbs, 0755, true);
\$payload = figma_build_source_export_payload(\$figma, \$outAbs, \$regions);
echo json_encode(\$payload, JSON_THROW_ON_ERROR);
" > "$CONFIG_JSON"

ITEM_COUNT="$(node -e "const p=require('$CONFIG_JSON'); console.log((p.items||[]).length)")"
[[ "$ITEM_COUNT" -gt 0 ]] || fail "No figma_node_id entries — sync figma-regions.yml first"

OUT_DIR="$(node -e "console.log(require('$CONFIG_JSON').out_dir)")"
log "Exporting Figma source baselines ($ITEM_COUNT nodes) → $OUT_DIR"

if (cd "$EXTENSION_ROOT" && node "$SCRIPT_DIR/export-figma-component-baselines.mjs" "$CONFIG_JSON"); then
  log "Figma source baselines exported. Commit $OUT_DIR"
  exit 0
fi

if [[ "$REQUIRE" == true ]]; then
  fail "Figma source export failed — set FIGMA_ACCESS_TOKEN or place PNGs in $OUT_DIR"
fi
log "WARN: Figma source export skipped (set FIGMA_ACCESS_TOKEN or add PNGs manually to $OUT_DIR)"
exit 0
