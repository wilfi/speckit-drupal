#!/usr/bin/env bash
# verify-foundational.sh — gate Phase 2 (foundational) before user story implementation
set -euo pipefail

PROJECT_ROOT="$(pwd)"
FEATURE_DIR=""
CHECKLIST=""
DRUSH_ROOT="web"
USE_DDEV=true

log() { echo "drupal: $*"; }
fail() { echo "drupal: FOUNDATIONAL FAIL: $*" >&2; exit 1; }
warn() { echo "drupal: WARN: $*" >&2; }

usage() {
  cat <<'EOF'
Usage: verify-foundational.sh [FEATURE_DIR]

Verifies contrib modules, exported config, and active Drupal entities before Phase 3+.
Requires specs/<feature>/foundational-checklist.yml (copy from foundational-checklist-template.yml).

Exit 0 = pass; non-zero = block /speckit-implement from proceeding to user stories.
EOF
}

resolve_feature_dir() {
  if [[ -n "$FEATURE_DIR" && -d "$PROJECT_ROOT/$FEATURE_DIR" ]]; then
    echo "$PROJECT_ROOT/$FEATURE_DIR"
    return
  fi
  if [[ -f "$PROJECT_ROOT/.specify/feature.json" ]]; then
    local _dir
    _dir="$(grep -o '"feature_directory"[[:space:]]*:[[:space:]]*"[^"]*"' "$PROJECT_ROOT/.specify/feature.json" 2>/dev/null | sed 's/.*"\([^"]*\)"$/\1/' | head -1)"
    if [[ -n "$_dir" && -d "$PROJECT_ROOT/$_dir" ]]; then
      echo "$PROJECT_ROOT/$_dir"
      return
    fi
  fi
  local _latest
  _latest="$(find "$PROJECT_ROOT/specs" -name 'foundational-checklist.yml' -type f 2>/dev/null | xargs ls -t 2>/dev/null | head -1 || true)"
  if [[ -n "$_latest" ]]; then
    dirname "$_latest"
    return
  fi
  echo ""
}

drush_cmd() {
  if [[ "$USE_DDEV" == "true" ]] && command -v ddev >/dev/null 2>&1 && [[ -f "$PROJECT_ROOT/.ddev/config.yaml" ]]; then
    ddev drush "$@"
  else
    "$PROJECT_ROOT/vendor/bin/drush" -r "$PROJECT_ROOT/$DRUSH_ROOT" "$@"
  fi
}

[[ "${1:-}" == "-h" || "${1:-}" == "--help" ]] && { usage; exit 0; }

FEATURE_DIR="${1:-}"
RESOLVED="$(resolve_feature_dir)"
[[ -n "$RESOLVED" ]] || fail "No feature directory found. Pass path or set .specify/feature.json"

CHECKLIST="$RESOLVED/foundational-checklist.yml"
[[ -f "$CHECKLIST" ]] || fail "Missing $CHECKLIST — copy templates/foundational-checklist-template.yml during /speckit-plan"

CONFIG_FILE="$PROJECT_ROOT/.specify/extensions/drupal/drupal-config.yml"
if [[ -f "$CONFIG_FILE" ]]; then
  grep -qE 'use_ddev:\s*false' "$CONFIG_FILE" 2>/dev/null && USE_DDEV=false || true
  _root="$(grep -E 'drush_root:' "$CONFIG_FILE" 2>/dev/null | head -1 | sed 's/.*: *//' | sed 's/"//g' | sed 's/#.*//' | tr -d ' ')"
  [[ -n "$_root" ]] && DRUSH_ROOT="$_root"
fi

SYNC_DIR="$PROJECT_ROOT/config/sync"
[[ -d "$SYNC_DIR" ]] || fail "config/sync/ not found at $SYNC_DIR"

[[ -x "$PROJECT_ROOT/vendor/bin/drush" ]] || command -v ddev >/dev/null 2>&1 || fail "Drush not found (vendor/bin/drush or ddev)"

log "Foundational gate: $(basename "$RESOLVED")"
log "Checklist: $CHECKLIST"

emit_checklist_checks() {
  local file="$1"
  local section="" subsection=""
  local theme_name="" theme_default="false" require_import="false"
  local figma_enabled="false" figma_baselines_dir="figma-baselines" figma_manifest=""
  local lib_path="" lib_modules=""
  local -a config_entities_list=()

  while IFS= read -r raw || [[ -n "$raw" ]]; do
    local line="${raw%%#*}"
    line="$(printf '%s' "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
    [[ -z "${line//[[:space:]]/}" ]] && continue

    if [[ "$line" =~ ^([a-z_]+):[[:space:]]*$ ]]; then
      if [[ "$section" == "required_libraries" && "${BASH_REMATCH[1]}" == "when_modules" ]]; then
        subsection="when_modules"
        continue
      fi
      if [[ "$section" == "required_libraries" && -n "$lib_path" ]]; then
        printf 'CHECK_LIBRARY|%s|%s\n' "$lib_path" "$lib_modules"
        lib_path=""
        lib_modules=""
      fi
      section="${BASH_REMATCH[1]}"
      subsection=""
      continue
    fi

    if [[ "$line" =~ ^([a-z_]+):[[:space:]]*(.+)$ ]]; then
      local key="${BASH_REMATCH[1]}"
      local val="${BASH_REMATCH[2]}"
      val="${val%\"}"; val="${val#\"}"
      val="${val%\'}"; val="${val#\'}"

      if [[ -z "$section" && "$key" == "require_config_import" ]]; then
        require_import="$val"
      fi
      if [[ "$section" == "theme" ]]; then
        [[ "$key" == "name" ]] && theme_name="$val"
        [[ "$key" == "must_be_default" ]] && theme_default="$val"
      fi
      if [[ "$section" == "figma" ]]; then
        [[ "$key" == "enabled" ]] && figma_enabled="$val"
        [[ "$key" == "baselines_dir" ]] && figma_baselines_dir="$val"
        [[ "$key" == "asset_manifest" ]] && figma_manifest="$val"
      fi
      if [[ "$section" == "content_entities" ]]; then
        subsection="$key"
      fi
      if [[ "$section" == "figma" && "$key" == "required_baselines" ]]; then
        subsection="required_baselines"
      fi
      if [[ "$section" == "required_libraries" && "$key" == "path" ]]; then
        if [[ -n "$lib_path" ]]; then
          printf 'CHECK_LIBRARY|%s|%s\n' "$lib_path" "$lib_modules"
        fi
        lib_path="$val"
        lib_modules=""
        subsection=""
      fi
      continue
    fi

    if [[ "$line" =~ ^-[[:space:]]+(.+)$ ]]; then
      local item="${BASH_REMATCH[1]}"
      item="${item%\"}"; item="${item#\"}"
      item="${item%\'}"; item="${item#\'}"

      case "$section" in
        contrib_modules) printf 'CHECK_MODULE|%s\n' "$item" ;;
        config_entities)
          config_entities_list+=("$item")
          printf 'CHECK_CONFIG_ENTITY|%s\n' "$item"
          ;;
        config_files) printf 'CHECK_CONFIG_FILE|%s\n' "$item" ;;
        content_entities)
          case "$subsection" in
            node_types) printf 'CHECK_NODE_TYPE|%s\n' "$item" ;;
            vocabularies) printf 'CHECK_VOCABULARY|%s\n' "$item" ;;
          esac
          ;;
        required_libraries)
          if [[ "$item" =~ ^path:[[:space:]]*(.+)$ ]]; then
            if [[ -n "$lib_path" ]]; then
              printf 'CHECK_LIBRARY|%s|%s\n' "$lib_path" "$lib_modules"
            fi
            lib_path="${BASH_REMATCH[1]}"
            lib_modules=""
            subsection=""
          elif [[ "$subsection" == "when_modules" ]]; then
            if [[ -n "$lib_modules" ]]; then
              lib_modules="${lib_modules},${item}"
            else
              lib_modules="$item"
            fi
          fi
          ;;
        figma)
          if [[ "$subsection" == "required_baselines" ]]; then
            printf 'CHECK_FIGMA_BASELINE|%s\n' "$item"
          fi
          ;;
      esac
      continue
    fi

    if [[ "$section" == "required_libraries" && "$line" == "when_modules:" ]]; then
      subsection="when_modules"
    fi
  done < "$file"

  if [[ "$section" == "required_libraries" && -n "$lib_path" ]]; then
    printf 'CHECK_LIBRARY|%s|%s\n' "$lib_path" "$lib_modules"
  fi

  if [[ -n "$theme_name" ]]; then
    printf 'CHECK_THEME|%s|%s\n' "$theme_name" "$theme_default"
  fi
  if [[ "$require_import" == "true" ]]; then
    local ent
    for ent in "${config_entities_list[@]}"; do
      printf 'CHECK_IMPORTED|%s\n' "$ent"
    done
  fi
  if [[ "$figma_enabled" == "true" ]]; then
    printf 'CHECK_FIGMA_ENABLED|%s|%s\n' "$figma_baselines_dir" "$figma_manifest"
  fi
}

emit_checklist_checks "$CHECKLIST" | while IFS= read -r line; do
  case "${line%%|*}" in
    CHECK_MODULE)
      mod="${line#*|}"
      if ! drush_cmd pm:list --status=enabled --type=module --filter="$mod" 2>/dev/null | grep -qE '(Enabled|enabled)'; then
        fail "Contrib module not enabled: $mod (composer require + drush en)"
      fi
      log "OK module enabled: $mod"
      ;;
    CHECK_CONFIG_FILE)
      rel="${line#*|}"
      path="$SYNC_DIR/$rel"
      [[ -f "$path" ]] || fail "Missing config export: config/sync/$rel"
      log "OK config file: $rel"
      ;;
    CHECK_CONFIG_ENTITY)
      ent="${line#*|}"
      file="$SYNC_DIR/${ent}.yml"
      [[ -f "$file" ]] || fail "Missing config/sync/${ent}.yml (export after site building)"
      log "OK config entity file: ${ent}.yml"
      ;;
    CHECK_NODE_TYPE)
      bundle="${line#*|}"
      if ! drush_cmd php:eval "exit(\Drupal\node\Entity\NodeType::load('$bundle') ? 0 : 1);" 2>/dev/null; then
        fail "Node type '$bundle' not in active Drupal config — run: drush config:import -y && drush cr"
      fi
      log "OK node type active: $bundle"
      ;;
    CHECK_VOCABULARY)
      vid="${line#*|}"
      if ! drush_cmd php:eval "exit(\Drupal\taxonomy\Entity\Vocabulary::load('$vid') ? 0 : 1);" 2>/dev/null; then
        fail "Vocabulary '$vid' not in active Drupal — run: drush config:import -y && drush cr"
      fi
      log "OK vocabulary active: $vid"
      ;;
    CHECK_THEME)
      rest="${line#CHECK_THEME|}"
      name="${rest%%|*}"
      must_default="${rest#*|}"
      if [[ "$must_default" == "true" ]]; then
        current="$(drush_cmd config:get system.theme default --format=string 2>/dev/null | tr -d "'\" " || true)"
        [[ "$current" == "$name" ]] || fail "Default theme is '$current', expected '$name' (drush config:set system.theme default $name -y)"
        log "OK default theme: $name"
      fi
      theme_path="$PROJECT_ROOT/$DRUSH_ROOT/themes/custom/$name/$name.info.yml"
      [[ -f "$theme_path" ]] || fail "Theme scaffold missing: $theme_path"
      log "OK theme scaffold: $name"
      ;;
    CHECK_IMPORTED)
      ent="${line#*|}"
      status="$(drush_cmd config:status "$ent" --format=string 2>/dev/null || echo "unknown")"
      if echo "$status" | grep -qiE 'only in sync|Only in sync'; then
        fail "Config '$ent' not imported (Only in sync dir) — run: drush config:import -y && drush cr"
      fi
      log "OK config imported: $ent"
      ;;
    CHECK_LIBRARY)
      rest="${line#CHECK_LIBRARY|}"
      lib_rel="${rest%%|*}"
      lib_mods="${rest#*|}"
      lib_file="$PROJECT_ROOT/$DRUSH_ROOT/$lib_rel"
      skip_lib=false
      if [[ -n "$lib_mods" ]]; then
        IFS=',' read -ra mods <<< "$lib_mods"
        for mod in "${mods[@]}"; do
          [[ -z "$mod" ]] && continue
          if ! drush_cmd pm:list --status=enabled --type=module --filter="$mod" 2>/dev/null | grep -qE '(Enabled|enabled)'; then
            log "SKIP library (module $mod not enabled): $lib_rel"
            skip_lib=true
            break
          fi
        done
      fi
      if [[ "$skip_lib" == "true" ]]; then
        :
      elif [[ ! -f "$lib_file" ]]; then
        fail "Missing library file: $lib_rel (see templates/contrib-libraries.md)"
      else
        log "OK library file: $lib_rel"
      fi
      ;;
    CHECK_FIGMA_ENABLED)
      rest="${line#CHECK_FIGMA_ENABLED|}"
      baselines_dir="${rest%%|*}"
      manifest="${rest#*|}"
      checks_file="$RESOLVED/figma-design-checks.yml"
      [[ -f "$checks_file" ]] || fail "figma.enabled: missing $checks_file — copy figma-design-checks-template.yml during /speckit-plan"
      base_path="$RESOLVED/$baselines_dir"
      [[ -d "$base_path" ]] || fail "figma.enabled: missing baselines directory $base_path"
      _ref_subpath="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
require '$PROJECT_ROOT/.specify/extensions/drupal/scripts/bash/figma-baseline-utils.php';
\$figma = figma_load_checks('$checks_file');
echo figma_reference_baselines_subpath(\$figma);
" 2>/dev/null || echo "$baselines_dir/figma-source")"
      _ref_path="$RESOLVED/$_ref_subpath"
      [[ -d "$_ref_path" ]] || fail "figma.enabled: missing Figma source baselines dir $_ref_path — run export-figma-source-baselines.sh"
      log "OK figma baselines dir: $_ref_subpath"
      export _FIGMA_REF_SUBPATH="$_ref_subpath"
      if [[ -n "$manifest" ]]; then
        [[ -f "$RESOLVED/$manifest" ]] || fail "figma.enabled: missing asset manifest $RESOLVED/$manifest"
        log "OK figma asset manifest: $manifest"
      fi
      ;;
    CHECK_FIGMA_BASELINE)
      baseline_file="${line#CHECK_FIGMA_BASELINE|}"
      _figma_ref="${_FIGMA_REF_SUBPATH:-}"
      if [[ -z "$_figma_ref" ]]; then
        checks_file="$RESOLVED/figma-design-checks.yml"
        _figma_ref="$(php -r "
require '$PROJECT_ROOT/vendor/autoload.php';
require '$PROJECT_ROOT/.specify/extensions/drupal/scripts/bash/figma-baseline-utils.php';
\$figma = figma_load_checks('$checks_file');
echo figma_reference_baselines_subpath(\$figma);
" 2>/dev/null || echo 'figma-baselines/figma-source')"
      fi
      full="$RESOLVED/${_figma_ref}/$baseline_file"
      [[ -f "$full" ]] || fail "figma.enabled: missing Figma source baseline PNG $full — run export-figma-source-baselines.sh"
      log "OK figma baseline: $baseline_file"
      ;;
  esac
done

php "$PROJECT_ROOT/.specify/extensions/drupal/scripts/bash/check-entity-form-displays.php" "$SYNC_DIR" \
  || fail "QR-CONFIG-001: bundle fields must be visible on default form displays (see drupal-quality-rules.md)"

log "Foundational verification passed: $(basename "$RESOLVED")"
log "Safe to proceed to Phase 3 (user stories)"
