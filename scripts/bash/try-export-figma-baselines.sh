#!/usr/bin/env bash
# try-export-figma-baselines.sh — export Figma source + optional live baselines when site is ready
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EXT_DIR="$PROJECT_ROOT/.specify/extensions/drupal"
CONFIG_FILE="$EXT_DIR/drupal-config.yml"
FEATURE_DIR=""
WHEN=""
REQUIRE=false
REFRESH=false

log() { echo "drupal: $*"; }
skip() { log "SKIP export: $*"; exit 0; }
fail() { echo "drupal: FAIL: $*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage: try-export-figma-baselines.sh [OPTIONS] [FEATURE_DIR]

Export figma-baselines/figma-source/ from Figma API (QR-FIGMA-002 reference) and,
when DDEV renders, optional live captures to figma-baselines/live/. Exits 0 when skipped.

Options:
  --when=MODE       plan | after_seed | after_theme_story | polish_only | manual
  --require         Fail if export cannot run (used at polish gate)
  --refresh         Overwrite existing baseline PNGs
  -h, --help
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --when=*) WHEN="${1#*=}"; shift ;;
    --require) REQUIRE=true; shift ;;
    --refresh) REFRESH=true; shift ;;
    -h|--help) usage; exit 0 ;;
    *) FEATURE_DIR="$1"; shift ;;
  esac
done

resolve_feature_dir() {
  if [[ -n "$FEATURE_DIR" && -d "$PROJECT_ROOT/$FEATURE_DIR" ]]; then
    echo "$FEATURE_DIR"
    return
  fi
  if [[ -f "$PROJECT_ROOT/.specify/feature.json" ]]; then
    local _dir
    _dir="$(grep -o '"feature_directory"[[:space:]]*:[[:space:]]*"[^"]*"' "$PROJECT_ROOT/.specify/feature.json" 2>/dev/null | sed 's/.*"\([^"]*\)"$/\1/' | head -1)"
    [[ -n "$_dir" && -d "$PROJECT_ROOT/$_dir" ]] && { echo "$_dir"; return; }
  fi
  local _latest
  _latest="$(find "$PROJECT_ROOT/specs" -name 'figma-design-checks.yml' -type f 2>/dev/null | xargs ls -t 2>/dev/null | head -1 || true)"
  [[ -n "$_latest" ]] && { dirname "${_latest#$PROJECT_ROOT/}"; return; }
  echo ""
}

read_config() {
  AUTO=true
  AUTO_FIGMA_SOURCE=true
  EXPORT_WHEN="after_theme_story"
  REQUIRE_POLISH=true
  BASELINE_SOURCE="figma"
  PRIMARY="/"
  if [[ -f "$CONFIG_FILE" ]] && command -v python3 >/dev/null 2>&1; then
    _cfg_out="$(python3 - "$CONFIG_FILE" <<'PY' 2>/dev/null || true
import sys
try:
    import yaml
except ImportError:
    sys.exit(0)
with open(sys.argv[1], encoding="utf-8") as f:
    cfg = yaml.safe_load(f) or {}
fig = (cfg.get("figma") or {})
def b(k, d):
    v = fig.get(k, d)
    print(f'{k}={"true" if v else "false"}' if isinstance(v, bool) else f'{k}="{v}"')
b("auto_export_baselines", True)
b("auto_export_figma_source", True)
print(f'export_when="{fig.get("export_when", "after_theme_story")}"')
b("require_baselines_at_polish", True)
print(f'baseline_source="{fig.get("baseline_source", "figma")}"')
urls = ((cfg.get("quality_rules") or {}).get("performance") or {}).get("check_urls") or ["/"]
print(f'primary_url="{urls[0]}"')
PY
)"
    [[ -n "$_cfg_out" ]] && eval "$_cfg_out"
  fi
  [[ -z "$WHEN" ]] && WHEN="$EXPORT_WHEN"
}

resolve_baseline_paths() {
  REFERENCE_BASELINES_DIR="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
require '$SCRIPT_DIR/figma-baseline-utils.php';
\$figma = figma_load_checks('$CHECKS');
echo figma_reference_baselines_subpath(\$figma);
")"
  REFERENCE_BASELINES_PATH="$PROJECT_ROOT/$FEATURE_DIR/$REFERENCE_BASELINES_DIR"
  LIVE_BASELINES_DIR="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
require '$SCRIPT_DIR/figma-baseline-utils.php';
\$figma = figma_load_checks('$CHECKS');
echo figma_live_baselines_subpath(\$figma);
")"
  LIVE_BASELINES_PATH="$PROJECT_ROOT/$FEATURE_DIR/$LIVE_BASELINES_DIR"
  mkdir -p "$REFERENCE_BASELINES_PATH" "$LIVE_BASELINES_PATH"
}

count_missing_reference() {
  local missing=0
  while IFS= read -r base; do
    [[ -n "$base" ]] || continue
    if [[ ! -f "$REFERENCE_BASELINES_PATH/$base" ]] || [[ "$REFRESH" == "true" ]]; then
      missing=$((missing + 1))
    fi
  done < <(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS');
\$s = \$c['figma']['screenshot'] ?? [];
foreach (\$s['pages'] ?? [] as \$p) { if (!empty(\$p['baseline'])) echo \$p['baseline'], PHP_EOL; }
foreach (\$s['sections'] ?? [] as \$sec) { if (!empty(\$sec['baseline'])) echo \$sec['baseline'], PHP_EOL; }
")
  echo "$missing"
}

FEATURE_DIR="$(resolve_feature_dir)"
[[ -n "$FEATURE_DIR" ]] || { [[ "$REQUIRE" == true ]] && fail "No feature directory"; skip "no feature directory"; }

CHECKS="$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml"
[[ -f "$CHECKS" ]] || { [[ "$REQUIRE" == true ]] && fail "missing figma-design-checks.yml"; skip "missing figma-design-checks.yml"; }

read_config
resolve_baseline_paths

[[ "$AUTO" == "true" || "$WHEN" != "manual" ]] || skip "export_when=manual"

# --- Figma source baselines (QR-FIGMA-002 reference) ---
ref_missing="$(count_missing_reference)"
if [[ "$ref_missing" -gt 0 && ("$AUTO_FIGMA_SOURCE" == "true" || "$BASELINE_SOURCE" != "live") ]]; then
  log "Exporting Figma source baselines ($ref_missing file(s)) for $FEATURE_DIR"
  if [[ "$REQUIRE" == "true" ]]; then
    bash "$SCRIPT_DIR/export-figma-source-baselines.sh" --require "$FEATURE_DIR"
  else
    bash "$SCRIPT_DIR/export-figma-source-baselines.sh" "$FEATURE_DIR" || log "WARN: Figma source export skipped (set FIGMA_ACCESS_TOKEN)"
  fi
fi

# --- Live-site captures (optional regression; not QR-FIGMA-002 reference when baseline_source: figma) ---
if [[ "$AUTO" != "true" && "$BASELINE_SOURCE" == "figma" ]]; then
  log "Live baseline export skipped (baseline_source: figma)"
  exit 0
fi

live_missing=0
while IFS= read -r base; do
  [[ -n "$base" ]] || continue
  if [[ ! -f "$LIVE_BASELINES_PATH/$base" ]] || [[ "$REFRESH" == "true" ]]; then
    live_missing=$((live_missing + 1))
  fi
done < <(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS');
\$s = \$c['figma']['screenshot'] ?? [];
foreach (\$s['pages'] ?? [] as \$p) { if (!empty(\$p['baseline'])) echo \$p['baseline'], PHP_EOL; }
foreach (\$s['sections'] ?? [] as \$sec) { if (!empty(\$sec['baseline'])) echo \$sec['baseline'], PHP_EOL; }
")

[[ "$live_missing" -gt 0 ]] || skip "all live baselines present (use --refresh to overwrite)"

BASE_URL=""
if command -v ddev >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/.ddev/config.yaml" ]]; then
  if ! ddev describe >/dev/null 2>&1; then
    [[ "$REQUIRE" == "true" && "$BASELINE_SOURCE" == "live" ]] && fail "DDEV not running"
    skip "DDEV not running (live export only)"
  fi
  BASE_URL="$(ddev describe 2>/dev/null | grep -oE 'https://[^ ]+\.ddev\.site' | head -1 || true)"
fi
[[ -n "$BASE_URL" ]] || {
  [[ "$REQUIRE" == "true" && "$BASELINE_SOURCE" == "live" ]] && fail "DDEV base URL not found"
  skip "DDEV base URL not found (live export only)"
}

HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' "${BASE_URL}${primary_url:-/}" 2>/dev/null || echo "000")"
if [[ "$HTTP_CODE" != "200" ]]; then
  [[ "$REQUIRE" == "true" && "$BASELINE_SOURCE" == "live" ]] && fail "primary URL HTTP $HTTP_CODE (expected 200)"
  skip "primary URL HTTP $HTTP_CODE — theme/seed not ready (live export only)"
fi

log "Exporting live baselines ($WHEN) — $live_missing file(s) to $LIVE_BASELINES_PATH"
bash "$SCRIPT_DIR/export-figma-baselines.sh" "$FEATURE_DIR" || {
  [[ "$REQUIRE" == "true" && "$BASELINE_SOURCE" == "live" ]] && fail "export-figma-baselines.sh failed"
  skip "live export failed (site may not render sections yet)"
}

if php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
\$c = Yaml::parseFile('$CHECKS');
\$e = \$c['figma']['screenshot']['components']['enabled'] ?? false;
exit(\$e ? 0 : 1);
" 2>/dev/null; then
  bash "$SCRIPT_DIR/export-figma-component-baselines.sh" "$FEATURE_DIR" 2>/dev/null || log "WARN: component baseline export skipped"
fi

log "Baselines ready: Figma source → $REFERENCE_BASELINES_PATH; live → $LIVE_BASELINES_PATH"
