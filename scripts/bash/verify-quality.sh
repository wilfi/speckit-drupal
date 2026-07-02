#!/usr/bin/env bash
# verify-quality.sh — enforce Drupal extension performance, smoke, a11y rules
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$PROJECT_ROOT/.specify/extensions/drupal/drupal-config.yml"
RULES_DOC="$PROJECT_ROOT/.specify/extensions/drupal/templates/drupal-quality-rules.md"
FEATURE_DIR_ARG="${1:-}"

ENABLED=true
MAX_LOAD_SECONDS=2
A11Y_STANDARD="WCAG2AA"
MAX_A11Y_ERRORS=0
DEFER_A11Y_TO_FIGMA=true
USE_DDEV=true
CHECK_URLS=()
SMOKE_ENABLED=true

log() { echo "drupal: $*"; }
QUALITY_GATE_PASSED=true
QUALITY_REPORT_WRITTEN=false
QUALITY_LOG=""
WRITE_QUALITY_REPORT=true

write_quality_report() {
  [[ "$WRITE_QUALITY_REPORT" == "true" ]] || return 0
  [[ "$QUALITY_REPORT_WRITTEN" == "true" ]] && return 0
  [[ -n "${FEATURE_DIR:-}" ]] || return 0
  [[ -n "${QUALITY_LOG:-}" && -f "$QUALITY_LOG" ]] || return 0
  local gate_status=passed
  [[ "$QUALITY_GATE_PASSED" == "true" ]] || gate_status=failed
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/write-quality-report.php" "$FEATURE_DIR" "$QUALITY_LOG" "$gate_status" 2>/dev/null || true
  QUALITY_REPORT_WRITTEN=true
  if [[ "$gate_status" == "passed" ]]; then
    log "Next: summarize $FEATURE_DIR/quality-results.md (speckit-drupal-quality-report)"
  else
    log "Gate failed — review P0 items in $FEATURE_DIR/quality-results.md (speckit-drupal-quality-report)"
  fi
}

fail() {
  QUALITY_GATE_PASSED=false
  echo "drupal: QUALITY FAIL: $*" >&2
  write_quality_report
  exit 1
}
warn() { echo "drupal: WARN: $*" >&2; }

usage() {
  cat <<'EOF'
Usage: verify-quality.sh [FEATURE_DIR] [path ...]

Runs QR-PERF, QR-SMOKE, QR-LIB, QR-JS, QR-CSS, QR-THEME, QR-FIGMA-000/001/002/003, QR-ASSET-001/002/004/005/006, and QR-A11Y checks.
FEATURE_DIR optional — merges specs/<feature>/quality-checks.yml when present.
Additional path arguments override configured check_urls.
EOF
}

[[ "${1:-}" == "-h" || "${1:-}" == "--help" ]] && { usage; exit 0; }

# Shift FEATURE_DIR if it looks like a feature directory.
CLI_PATHS=()
if [[ -n "$FEATURE_DIR_ARG" && ( -f "$PROJECT_ROOT/$FEATURE_DIR_ARG/quality-checks.yml" || -d "$PROJECT_ROOT/$FEATURE_DIR_ARG" && -f "$PROJECT_ROOT/$FEATURE_DIR_ARG/plan.md" ) ]]; then
  FEATURE_DIR="$FEATURE_DIR_ARG"
  shift
else
  FEATURE_DIR=""
fi
CLI_PATHS=("$@")

if [[ -f "$CONFIG_FILE" ]]; then
  grep -qE '^\s*enabled:\s*false' "$CONFIG_FILE" 2>/dev/null && ENABLED=false || true
  grep -qE 'use_ddev:\s*false' "$CONFIG_FILE" 2>/dev/null && USE_DDEV=false || true
fi

[[ "$ENABLED" == "true" ]] || { log "Quality rules disabled"; exit 0; }

[[ -f "$RULES_DOC" ]] && log "Rules reference: templates/drupal-quality-rules.md"

# Resolve feature dir from .specify/feature.json when not passed.
if [[ -z "$FEATURE_DIR" && -f "$PROJECT_ROOT/.specify/feature.json" ]]; then
  _dir="$(grep -o '"feature_directory"[[:space:]]*:[[:space:]]*"[^"]*"' "$PROJECT_ROOT/.specify/feature.json" 2>/dev/null | sed 's/.*"\([^"]*\)"$/\1/' | head -1)"
  [[ -n "$_dir" && -f "$PROJECT_ROOT/$_dir/quality-checks.yml" ]] && FEATURE_DIR="$_dir"
fi

load_quality_config() {
  if [[ ! -f "$PROJECT_ROOT/vendor/autoload.php" ]]; then
    echo '{}'
    return
  fi
  PROJECT_ROOT="$PROJECT_ROOT" php "$SCRIPT_DIR/parse-quality-config.php" "${FEATURE_DIR:-}" 2>/dev/null || echo '{}'
}

QUALITY_JSON="$(load_quality_config)"

read_config_value() {
  local key="$1"
  local default="${2:-}"
  php -r "
    \$j = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
    \$keys = explode('.', '$key');
    \$v = \$j;
    foreach (\$keys as \$k) {
      if (!is_array(\$v) || !array_key_exists(\$k, \$v)) { echo '$default'; exit(0); }
      \$v = \$v[\$k];
    }
    if (is_array(\$v)) { echo '$default'; } elseif (is_bool(\$v)) { echo \$v ? 'true' : 'false'; } else { echo \$v; }
  " 2>/dev/null || echo "$default"
}

export QUALITY_JSON
MAX_LOAD_SECONDS="$(read_config_value performance.max_load_seconds 2)"
A11Y_STANDARD="$(read_config_value accessibility.standard WCAG2AA)"
MAX_A11Y_ERRORS="$(read_config_value accessibility.max_errors 0)"
DEFER_A11Y_TO_FIGMA="$(read_config_value accessibility.defer_to_figma true)"
FIGMA_SOURCE_OF_TRUTH="$(read_config_value figma.source_of_truth true)"
WRITE_QUALITY_REPORT="$(read_config_value quality_report.enabled true)"
_smoke_enabled="$(read_config_value smoke.enabled true)"
[[ "$_smoke_enabled" == "false" || "$_smoke_enabled" == "0" ]] && SMOKE_ENABLED=false || SMOKE_ENABLED=true

if [[ "$DEFER_A11Y_TO_FIGMA" == "true" || "$FIGMA_SOURCE_OF_TRUTH" == "true" ]]; then
  _FIGMA_PRECEDENCE_ACTIVE=true
else
  _FIGMA_PRECEDENCE_ACTIVE=false
fi

if [[ ${#CLI_PATHS[@]} -gt 0 ]]; then
  CHECK_URLS=("${CLI_PATHS[@]}")
else
  CHECK_URLS=()
  while IFS= read -r _url; do
    [[ -n "$_url" ]] && CHECK_URLS+=("$_url")
  done < <(php -r "
    \$j = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
    \$urls = \$j['performance']['check_urls'] ?? ['/'];
    foreach (\$urls as \$u) { echo \$u, PHP_EOL; }
  " 2>/dev/null || echo "/")
fi
if [[ ${#CHECK_URLS[@]} -eq 0 ]]; then
  CHECK_URLS=("/")
fi

QUALITY_LOG="$(mktemp)"
trap 'write_quality_report' EXIT
exec 3>&1 4>&2
exec 1> >(tee -a "$QUALITY_LOG" >&3)
exec 2> >(tee -a "$QUALITY_LOG" >&4)

if [[ "${_FIGMA_PRECEDENCE_ACTIVE:-false}" == "true" ]]; then
  log "QR-FIGMA-000: Figma design is source of truth — pa11y failures are warnings only"
fi

ddev_base_url() {
  command -v ddev >/dev/null 2>&1 || return 0
  [[ -f "$PROJECT_ROOT/.ddev/config.yaml" ]] || return 0
  ddev describe 2>/dev/null | grep -oE 'https://[^ ]+\.ddev\.site' | head -1 || true
}

resolve_url() {
  local path="$1"
  local base="${2:-}"
  if [[ "$path" =~ ^https?:// ]]; then
    echo "$path"
    return
  fi
  [[ "$path" != /* ]] && path="/$path"
  if [[ -n "$base" ]]; then
    echo "${base%/}${path}"
    return
  fi
  echo "http://localhost${path}"
}

fetch_page() {
  local url="$1"
  local tmp
  tmp="$(mktemp)"
  local code
  code="$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 5 --max-time 30 "$url" 2>/dev/null || echo "000")"
  echo "$code|$tmp"
}

check_http_status() {
  local url="$1"
  local code="$2"
  log "QR-SMOKE-001: $url → HTTP $code"
  [[ "$code" == "200" ]] || fail "QR-SMOKE-001: $url returned HTTP $code (expected 200)"
}

check_forbidden_strings() {
  local url="$1"
  local body_file="$2"
  local strings
  strings="$(php -r "
    \$j = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
    \$global = \$j['smoke']['forbidden_strings'] ?? [];
    \$path = '$url';
    \$parsed = parse_url(\$path, PHP_URL_PATH) ?: '/';
    \$pageForbidden = [];
    foreach (\$j['smoke']['pages'] ?? [] as \$page) {
      if ((\$page['path'] ?? '') === \$parsed) {
        \$pageForbidden = \$page['must_not_contain'] ?? [];
        break;
      }
    }
    foreach (array_unique(array_merge(\$global, \$pageForbidden)) as \$s) {
      echo \$s, PHP_EOL;
    }
  ")"
  while IFS= read -r str; do
    [[ -z "$str" ]] && continue
    if grep -qF "$str" "$body_file"; then
      fail "QR-SMOKE-002: forbidden string '$str' found on $url"
    fi
    log "QR-SMOKE-002: OK — '$str' absent on $url"
  done <<< "$strings"
}

check_must_contain() {
  local url="$1"
  local body_file="$2"
  php -r "
    \$j = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
    \$parsed = parse_url('$url', PHP_URL_PATH) ?: '/';
    foreach (\$j['smoke']['pages'] ?? [] as \$page) {
      if ((\$page['path'] ?? '') !== \$parsed) continue;
      \$body = file_get_contents('$body_file');
      foreach (\$page['must_contain'] ?? [] as \$marker) {
        if (strpos(\$body, \$marker) === false) {
          fwrite(STDERR, \"QR-SMOKE-003: missing marker '\$marker' on $url\\n\");
          exit(1);
        }
        fwrite(STDOUT, \"drupal: QR-SMOKE-003: OK — '\$marker' present on $url\\n\");
      }
      foreach (\$page['min_occurrences'] ?? [] as \$marker => \$min) {
        \$count = substr_count(\$body, \$marker);
        if (\$count < (int) \$min) {
          fwrite(STDERR, \"QR-SMOKE-003: '\$marker' count \$count < \$min on $url\\n\");
          exit(1);
        }
        fwrite(STDOUT, \"drupal: QR-SMOKE-003: OK — '\$marker' count \$count >= \$min on $url\\n\");
      }
    }
  " || fail "QR-SMOKE-003: content markers failed on $url"
}

check_duplicate_nav_links() {
  local url="$1"
  local body_file="$2"
  php -r "
    \$body = file_get_contents('$body_file');
    if (!preg_match_all('/<nav[^>]*>.*?<\\/nav>/is', \$body, \$navs)) {
      exit(0);
    }
    foreach (\$navs[0] as \$nav) {
      if (!preg_match_all('/<a[^>]*>([^<]+)<\\/a>/i', \$nav, \$m)) continue;
      \$texts = array_map('trim', \$m[1]);
      \$seen = [];
      foreach (\$texts as \$t) {
        \$key = strtolower(\$t);
        if (\$key === '') continue;
        if (isset(\$seen[\$key])) {
          fwrite(STDERR, \"QR-SMOKE-004: duplicate nav link '\$t' on $url\\n\");
          exit(1);
        }
        \$seen[\$key] = true;
      }
    }
    fwrite(STDOUT, \"drupal: QR-SMOKE-004: OK — no duplicate nav link text on $url\\n\");
  " || fail "QR-SMOKE-004: duplicate navigation links on $url"
}

check_duplicate_menu_uris() {
  local url="$1"
  local body_file="$2"
  php -r "
    \$body = file_get_contents('$body_file');
    if (!preg_match('/<div[^>]*class=\"[^\"]*site-header__nav[^\"]*\"[^>]*>(.*?)<\\/div>\\s*<div[^>]*class=\"[^\"]*site-header__actions/s', \$body, \$navMatch)) {
      exit(0);
    }
    \$nav = \$navMatch[1];
    if (!preg_match_all('/<a[^>]+href=\"([^\"]+)\"[^>]*>/i', \$nav, \$m)) {
      exit(0);
    }
    \$seen = [];
    foreach (\$m[1] as \$href) {
      \$href = trim(html_entity_decode(\$href));
      if (\$href === '' || \$href === '#') continue;
      \$key = preg_replace('#^https?://[^/]+#', '', \$href);
      \$key = rtrim(\$key, '/') ?: '/';
      if (isset(\$seen[\$key])) {
        fwrite(STDERR, \"QR-SMOKE-005: duplicate nav href '\$href' on $url\\n\");
        exit(1);
      }
      \$seen[\$key] = true;
    }
    fwrite(STDOUT, \"drupal: QR-SMOKE-005: OK — no duplicate nav hrefs on $url\\n\");
  " || fail "QR-SMOKE-005: duplicate navigation URIs on $url"
}

check_figma_nav_active_state() {
  local url="$1"
  local body_file="$2"
  php -r "
    \$parsed = parse_url('$url', PHP_URL_PATH) ?: '/';
    if (\$parsed !== '/') {
      exit(0);
    }
    \$body = file_get_contents('$body_file');
    if (!preg_match('/<div[^>]*class=\"[^\"]*site-header__nav[^\"]*\"[^>]*>(.*?)<\\/div>\\s*<div[^>]*class=\"[^\"]*site-header__actions/s', \$body, \$navMatch)) {
      fwrite(STDERR, \"QR-SMOKE-006: site-header__nav not found on $url\\n\");
      exit(1);
    }
    \$nav = \$navMatch[1];
    \$inactive = ['menu-item--figma-recipes', 'menu-item--figma-tips', 'menu-item--figma-about'];
    foreach (\$inactive as \$class) {
      if (preg_match('/<li[^>]*class=\"[^\"]*' . preg_quote(\$class, '/') . '[^\"]*\"[^>]*>[\\s\\S]*?<a[^>]*class=\"[^\"]*\\bis-active\\b/s', \$nav)) {
        fwrite(STDERR, \"QR-SMOKE-006: \$class must not have is-active on front page ($url)\\n\");
        exit(1);
      }
    }
    if (!preg_match('/<li[^>]*class=\"[^\"]*menu-item--figma-home[^\"]*\"[^>]*>[\\s\\S]*?<a[^>]*class=\"[^\"]*\\bis-active\\b/s', \$nav)) {
      fwrite(STDERR, \"QR-SMOKE-006: menu-item--figma-home missing is-active on front page ($url)\\n\");
      exit(1);
    }
    fwrite(STDOUT, \"drupal: QR-SMOKE-006: OK — only Home nav link active on $url\\n\");
  " || fail "QR-SMOKE-006: Figma nav active state failed on $url"
}

check_header_search_icon() {
  local url="$1"
  local body_file="$2"
  php -r "
    \$body = file_get_contents('$body_file');
    if (!preg_match('/<a[^>]*class=\"[^\"]*site-header__search[^\"]*\"[^>]*>([\\s\\S]*?)<\\/a>/i', \$body, \$m)) {
      fwrite(STDERR, \"QR-SMOKE-007: site-header__search link missing on $url\\n\");
      exit(1);
    }
    \$inner = \$m[1];
    if (!preg_match('/<img[^>]+src=\"[^\"]*icon-search\\.(svg|png)[^\"]*\"[^>]*>/i', \$inner, \$img)) {
      fwrite(STDERR, \"QR-SMOKE-007: search icon img with icon-search.svg missing on $url\\n\");
      exit(1);
    }
    if (!preg_match('/\\bwidth=\"21\"/i', \$img[0]) || !preg_match('/\\bheight=\"21\"/i', \$img[0])) {
      fwrite(STDERR, \"QR-SMOKE-007: search icon must be 21×21 per Figma 144:25/144:26 on $url\\n\");
      exit(1);
    }
    fwrite(STDOUT, \"drupal: QR-SMOKE-007: OK — header search icon markup on $url\\n\");
  " || fail "QR-SMOKE-007: header search icon check failed on $url"
}

check_smoke_icon_markers() {
  local url="$1"
  local body_file="$2"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php -r "
    \$rules = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
    \$markers = \$rules['smoke']['icon_markers'] ?? [];
    if (!is_array(\$markers) || \$markers === []) {
      exit(0);
    }
    \$path = parse_url('$url', PHP_URL_PATH) ?: '/';
    \$body = file_get_contents('$body_file');
    foreach (\$markers as \$marker) {
      if (!is_array(\$marker)) {
        continue;
      }
      \$id = \$marker['id'] ?? 'QR-SMOKE-ICON';
      \$markerPath = \$marker['path'] ?? '/';
      if (\$markerPath !== \$path) {
        continue;
      }
      \$container = preg_quote((string) (\$marker['container_class'] ?? ''), '/');
      if (\$container === '') {
        continue;
      }
      if (!preg_match('/<[^>]*class=\"[^\"]*' . \$container . '[^\"]*\"[^>]*>([\\s\\S]*?)<\\/[^>]+>/i', \$body, \$block)) {
        fwrite(STDERR, \"\$id: container .$container not found on $url\\n\");
        exit(1);
      }
      \$scope = \$block[1];
      \$pattern = (string) (\$marker['img_pattern'] ?? '');
      if (\$pattern === '') {
        continue;
      }
      preg_match_all('/<img[^>]+src=\"[^\"]*' . \$pattern . '[^\"]*\"[^>]*>/i', \$scope, \$imgs);
      \$min = (int) (\$marker['min_count'] ?? 1);
      if (count(\$imgs[0] ?? []) < \$min) {
        fwrite(STDERR, \"\$id: expected >= \$min img matching /\$pattern/ in .$container on $url\\n\");
        exit(1);
      }
      \$w = \$marker['width'] ?? null;
      \$h = \$marker['height'] ?? null;
      if (\$w !== null && \$h !== null) {
        foreach (\$imgs[0] as \$tag) {
          if (!preg_match('/\\bwidth=\"' . (int) \$w . '\"/i', \$tag) || !preg_match('/\\bheight=\"' . (int) \$h . '\"/i', \$tag)) {
            fwrite(STDERR, \"\$id: img in .$container must be \" . (int) \$w . '×' . (int) \$h . \" on $url\\n\");
            exit(1);
          }
        }
      }
      fwrite(STDOUT, \"drupal: \$id: OK — icon markup in .$container on $url\\n\");
    }
  " || fail "QR-SMOKE-008/009: Figma icon marker checks failed on $url"
}

check_content_image_scopes() {
  local url="$1"
  local body_file="$2"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-content-image-scopes.php" "$body_file" "$url" \
    || fail "QR-SMOKE-010: content image scope checks failed on $url"
}

check_theme_template_assets() {
  local theme_enabled
  theme_enabled="$(read_config_value theme.enabled true)"
  [[ "$theme_enabled" == "false" || "$theme_enabled" == "0" ]] && return 0

  local theme_dir
  theme_dir="$(read_config_value css.theme_dir web/themes/custom)"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-theme-template-assets.php" "$theme_dir" \
    || fail "QR-THEME-001: theme template asset checks failed"
}

check_figma_asset_manifest() {
  [[ -n "${FEATURE_DIR:-}" ]] || return 0
  [[ -f "$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml" ]] || return 0
  local theme_dir
  theme_dir="$(read_config_value css.theme_dir web/themes/custom)"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-figma-asset-manifest.php" "$FEATURE_DIR" "$theme_dir" \
    || fail "QR-ASSET-005: figma-asset-manifest check failed"
}

check_figma_atomic_components() {
  [[ -n "${FEATURE_DIR:-}" ]] || return 0
  [[ -f "$PROJECT_ROOT/$FEATURE_DIR/figma-asset-manifest.yml" ]] || return 0
  local auto_sync
  auto_sync="$(read_config_value figma.auto_sync_atomic_manifest true)"
  if [[ "$auto_sync" != "false" && "$auto_sync" != "0" && -f "$SCRIPT_DIR/sync-figma-atomic-manifest.php" ]]; then
    export PROJECT_ROOT="$PROJECT_ROOT"
    php "$SCRIPT_DIR/sync-figma-atomic-manifest.php" --feature="$FEATURE_DIR" \
      || fail "QR-ASSET-006: atomic manifest sync failed"
  fi
  local theme_dir
  theme_dir="$(read_config_value css.theme_dir web/themes/custom)"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-figma-atomic-components.php" "$FEATURE_DIR" "$theme_dir" \
    || fail "QR-ASSET-006: atomic Figma component manifest check failed"
}

check_webform_templates() {
  local theme_enabled
  theme_enabled="$(read_config_value theme.enabled true)"
  [[ "$theme_enabled" == "false" || "$theme_enabled" == "0" ]] && return 0

  local theme_dir
  theme_dir="$(read_config_value css.theme_dir web/themes/custom)"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-webform-templates.php" "$theme_dir" \
    || fail "QR-THEME-003: webform template structure checks failed"
}

check_composite_form_css() {
  local css_enabled
  css_enabled="$(read_config_value css.enabled true)"
  [[ "$css_enabled" == "false" || "$css_enabled" == "0" ]] && return 0

  local theme_dir
  theme_dir="$(read_config_value css.theme_dir web/themes/custom)"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-composite-form-css.php" "$theme_dir" \
    || fail "QR-CSS-015/016: composite form CSS checks failed"
}

check_composite_form_smoke() {
  local url="$1"
  local body_file="$2"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-composite-form-smoke.php" "$body_file" "$url" \
    || fail "QR-SMOKE-011: composite form structure checks failed"
}

check_views_list_css() {
  local body_file="${1:-}"
  local css_enabled
  css_enabled="$(read_config_value css.enabled true)"
  [[ "$css_enabled" == "false" || "$css_enabled" == "0" ]] && return 0

  local theme_dir
  theme_dir="$(read_config_value css.theme_dir web/themes/custom)"
  export PROJECT_ROOT="$PROJECT_ROOT"

  local args=("$theme_dir")
  [[ -n "$body_file" && -f "$body_file" ]] && args+=("$body_file")

  php "$SCRIPT_DIR/check-views-list-css.php" "${args[@]}" || fail "QR-CSS-001–003: views list CSS checks failed"
}

check_section_layout_css() {
  local css_enabled
  css_enabled="$(read_config_value css.enabled true)"
  [[ "$css_enabled" == "false" || "$css_enabled" == "0" ]] && return 0

  local theme_dir
  theme_dir="$(read_config_value css.theme_dir web/themes/custom)"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-section-layout-css.php" "$theme_dir" || fail "QR-CSS-004: section max-width checks failed"
}

check_component_padding_css() {
  local css_enabled
  css_enabled="$(read_config_value css.enabled true)"
  [[ "$css_enabled" == "false" || "$css_enabled" == "0" ]] && return 0

  local theme_dir
  theme_dir="$(read_config_value css.theme_dir web/themes/custom)"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-component-padding-css.php" "$theme_dir" || fail "QR-CSS-005/007: component style checks failed"
}

check_section_margin_alignment() {
  local css_enabled
  css_enabled="$(read_config_value css.enabled true)"
  [[ "$css_enabled" == "false" || "$css_enabled" == "0" ]] && return 0

  local theme_dir
  theme_dir="$(read_config_value css.theme_dir web/themes/custom)"
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-section-margin-alignment.php" "$theme_dir" || fail "QR-CSS-006: section margin alignment checks failed"
}

check_figma_design() {
  local body_file="${1:-}"
  [[ -n "$body_file" && -f "$body_file" ]] || return 0
  [[ -n "$FEATURE_DIR" && -f "$PROJECT_ROOT/$FEATURE_DIR/figma-design-checks.yml" ]] || return 0
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-figma-design.php" "$body_file" "$FEATURE_DIR" || fail "QR-FIGMA-001: Figma design parity checks failed"
}

check_page_assets() {
  local url="$1"
  local body_file="${2:-}"
  local assets_enabled
  assets_enabled="$(read_config_value assets.enabled true)"
  [[ "$assets_enabled" == "false" || "$assets_enabled" == "0" ]] && return 0
  [[ -n "$body_file" && -f "$body_file" ]] || return 0
  export PROJECT_ROOT="$PROJECT_ROOT"
  php "$SCRIPT_DIR/check-page-assets.php" "$body_file" "$url" "${FEATURE_DIR:-}" || fail "QR-ASSET-001: page/component asset checks failed"
}

check_figma_screenshot() {
  [[ -n "$FEATURE_DIR" ]] || return 0
  [[ -f "$SCRIPT_DIR/check-figma-screenshot.sh" ]] || return 0
  bash "$SCRIPT_DIR/check-figma-screenshot.sh" "$FEATURE_DIR" || fail "QR-FIGMA-002: Figma screenshot diff failed"
}

check_libraries() {
  local base="$1"
  local libs
  libs="$(php -r "
    \$j = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
    foreach (\$j['smoke']['libraries'] ?? [] as \$lib) {
      echo \$lib, PHP_EOL;
    }
  ")"
  while IFS= read -r lib; do
    [[ -z "$lib" ]] && continue
    local lib_url
    lib_url="$(resolve_url "$lib" "$base")"
    local code
    code="$(curl -o /dev/null -s -w '%{http_code}' --connect-timeout 5 --max-time 15 "$lib_url" 2>/dev/null || echo "000")"
    log "QR-LIB-001: $lib_url → HTTP $code"
    [[ "$code" == "200" ]] || fail "QR-LIB-001: library not reachable: $lib_url (HTTP $code)"
  done <<< "$libs"
}

check_js_behaviors() {
  local base="$1"
  local path="$2"
  local url
  url="$(resolve_url "$path" "$base")"
  local behaviors
  behaviors="$(php -r "
    \$j = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
    foreach (\$j['smoke']['js_behaviors'] ?? [] as \$b) {
      echo \$b, PHP_EOL;
    }
  ")"
  [[ -n "$behaviors" ]] || return 0

  local html_file js_urls
  html_file="$(mktemp)"
  curl -sS "$url" -o "$html_file" 2>/dev/null || fail "QR-JS-001: could not fetch $url for JS check"

  js_urls=()
  while IFS= read -r _js; do
    [[ -n "$_js" ]] && js_urls+=("$_js")
  done < <(grep -oE 'src="[^"]+\.js[^"]*"' "$html_file" | sed 's/src="//;s/"$//' || true)
  rm -f "$html_file"

  [[ ${#js_urls[@]} -gt 0 ]] || fail "QR-JS-001: no JS files found on $url"

  local combined
  combined="$(mktemp)"
  : > "$combined"
  local js_url resolved
  for js_url in "${js_urls[@]}"; do
    resolved="$(resolve_url "$js_url" "$base")"
    curl -sS "$resolved" >> "$combined" 2>/dev/null || true
  done

  while IFS= read -r behavior; do
    [[ -z "$behavior" ]] && continue
    if grep -qF "$behavior" "$combined"; then
      log "QR-JS-001: OK — behavior '$behavior' found in page JS"
    else
      rm -f "$combined"
      fail "QR-JS-001: behavior '$behavior' not found in aggregated JS for $url"
    fi
  done <<< "$behaviors"
  rm -f "$combined"
}

check_load_time() {
  local url="$1"
  local max="$2"
  command -v curl >/dev/null 2>&1 || fail "curl required for QR-PERF-001"
  local elapsed
  elapsed="$(curl -o /dev/null -s -w '%{time_total}' --connect-timeout 5 --max-time "$((max + 10))" "$url" 2>/dev/null || echo "999")"
  log "QR-PERF-001: $url → ${elapsed}s (max ${max}s)"
  awk -v e="$elapsed" -v m="$max" 'BEGIN { exit !(e+0 <= m+0) }' || fail "$url exceeded ${max}s load budget (${elapsed}s)"
}

is_arm64_host() {
  local arch
  arch="$(uname -m 2>/dev/null || true)"
  [[ "$arch" =~ ^(arm64|aarch64)$ ]]
}

is_ddev_a11y_env_error() {
  local output="$1"
  grep -qiE 'ld-linux-x86-64|multi-arch|multiarch|Failed to launch the browser|x86 program on an arm64|Dynamic loader not found' <<< "$output"
}

check_a11y_host() {
  local url="$1"
  command -v npx >/dev/null 2>&1 || { warn "npx unavailable — skip QR-A11Y-001"; return 0; }
  log "QR-A11Y-001: pa11y $url ($A11Y_STANDARD, max $MAX_A11Y_ERRORS errors)"
  npx --yes pa11y "$url" --standard "$A11Y_STANDARD" --threshold "$MAX_A11Y_ERRORS"
}

check_a11y_ddev() {
  local path="${1:-/}"
  local url="$2"
  local internal_path="$path"
  local output=""

  [[ "$internal_path" == "/" ]] && internal_path="https://web/"
  if [[ ! "$internal_path" =~ ^https?:// ]]; then
    internal_path="https://web${internal_path}"
  fi

  log "QR-A11Y-001: pa11y $internal_path ($A11Y_STANDARD) via DDEV"
  if output="$(ddev exec npx --yes pa11y "$internal_path" --standard "$A11Y_STANDARD" --threshold "$MAX_A11Y_ERRORS" 2>&1)"; then
    printf '%s\n' "$output"
    return 0
  fi

  if is_ddev_a11y_env_error "$output"; then
    warn "DDEV pa11y unavailable (browser/arch mismatch) — retrying on host against $url"
    check_a11y_host "$url"
    return $?
  fi

  printf '%s\n' "$output" >&2
  return 1
}

check_a11y() {
  local path="$1"
  local url="$2"
  local rc=0

  if [[ "$USE_DDEV" != "true" ]] || [[ ! -f "$PROJECT_ROOT/.ddev/config.yaml" ]] || ! command -v ddev >/dev/null 2>&1; then
    check_a11y_host "$url" || rc=$?
  elif is_arm64_host; then
    warn "ARM64 host detected — using host pa11y against $url (DDEV container pa11y is unreliable on Apple Silicon)"
    check_a11y_host "$url" || rc=$?
  else
    check_a11y_ddev "$path" "$url" || rc=$?
  fi

  if [[ "$rc" -ne 0 ]]; then
    if [[ "$DEFER_A11Y_TO_FIGMA" == "true" || "$FIGMA_SOURCE_OF_TRUTH" == "true" ]]; then
      warn "QR-A11Y-001: pa11y reported errors — tolerated under QR-FIGMA-000 (Figma design is source of truth)"
      return 0
    fi
    fail "QR-A11Y-001: accessibility checks failed"
  fi
}

run_smoke_for_url() {
  local path="$1"
  local url="$2"
  local base="$3"

  [[ "$SMOKE_ENABLED" == "true" ]] || return 0

  local result code body_file
  result="$(fetch_page "$url")"
  code="${result%%|*}"
  body_file="${result#*|}"
  trap 'rm -f "$body_file"' RETURN

  check_http_status "$url" "$code"
  check_forbidden_strings "$url" "$body_file"
  check_must_contain "$url" "$body_file"
  check_duplicate_nav_links "$url" "$body_file"
  check_duplicate_menu_uris "$url" "$body_file"
  check_figma_nav_active_state "$url" "$body_file"
  check_header_search_icon "$url" "$body_file"
  check_smoke_icon_markers "$url" "$body_file"
  check_content_image_scopes "$url" "$body_file"
  check_composite_form_smoke "$url" "$body_file"
  check_views_list_css "$body_file"
  check_figma_design "$body_file"
  check_page_assets "$url" "$body_file"
}

BASE="$(ddev_base_url)"

if [[ "$SMOKE_ENABLED" == "true" ]]; then
  check_libraries "$BASE"
  check_views_list_css ""
  check_section_layout_css
  check_component_padding_css
  check_composite_form_css
  check_section_margin_alignment
  check_figma_asset_manifest
  check_figma_atomic_components
  check_theme_template_assets
  check_webform_templates
fi

for path in "${CHECK_URLS[@]}"; do
  url="$(resolve_url "$path" "$BASE")"
  run_smoke_for_url "$path" "$url" "$BASE"
  check_load_time "$url" "$MAX_LOAD_SECONDS"
  if [[ "$SMOKE_ENABLED" == "true" ]]; then
    check_js_behaviors "$BASE" "$path"
  fi
  check_a11y "$path" "$url"
done

check_figma_screenshot

log "Quality verification passed."
