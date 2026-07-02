#!/usr/bin/env bash
# verify-plan.sh — check Drupal plan sections
set -euo pipefail

PROJECT_ROOT="$(pwd)"
PLAN="${1:-}"

if [[ -z "$PLAN" ]]; then
  if [[ -f "$PROJECT_ROOT/.specify/feature.json" ]]; then
    _dir="$(grep -o '"feature_directory"[[:space:]]*:[[:space:]]*"[^"]*"' "$PROJECT_ROOT/.specify/feature.json" 2>/dev/null | sed 's/.*"\([^"]*\)"$/\1/' | head -1)"
    [[ -n "$_dir" && -f "$PROJECT_ROOT/$_dir/plan.md" ]] && PLAN="$PROJECT_ROOT/$_dir/plan.md"
  fi
fi
if [[ -z "$PLAN" ]]; then
  PLAN="$(find "$PROJECT_ROOT/specs" -path '*/plan.md' -type f 2>/dev/null | xargs ls -t 2>/dev/null | head -1 || true)"
fi

log() { echo "drupal: $*"; }
fail() { echo "drupal: FAIL: $*" >&2; exit 1; }

[[ -n "$PLAN" && -f "$PLAN" ]] || fail "No plan.md found. Pass path or run /speckit-plan first."

REQUIRED=(
  "Technical Context"
  "Drupal Quality Rules"
  "Drupal Architecture"
  "Config Strategy"
)

MISSING=()
for section in "${REQUIRED[@]}"; do
  if ! grep -q "^## ${section}" "$PLAN"; then
    MISSING+=("$section")
  fi
done

if [[ ${#MISSING[@]} -gt 0 ]]; then
  fail "Plan missing sections: ${MISSING[*]}. Add Drupal template sections to $PLAN"
fi

# Drupal quality rule references (mandatory in plan template)
for token in "QR-PERF" "QR-A11Y" "QR-SMOKE" "QR-CSS"; do
  if ! grep -q "$token" "$PLAN"; then
    fail "Plan missing '$token' quality rule reference. Use Drupal plan-template.md"
  fi
done

# Ambiguities section required when plan mentions carousel, menus, or seed scripts.
if grep -qiE 'slick|carousel|seed|setup-.*\.php|menu_link|mcp_tools' "$PLAN"; then
  if ! grep -q "^## Ambiguities Resolved" "$PLAN"; then
    fail "Plan missing '## Ambiguities Resolved' section (required when carousel/menus/seed/MCP mentioned)"
  fi
fi

# Resolve feature directory from plan path
FEATURE_DIR="$(dirname "$PLAN")"
SPEC_FILE="$FEATURE_DIR/spec.md"
DESIGN_CTX="$FEATURE_DIR/design-context.md"
FIGMA_CHECKS="$FEATURE_DIR/figma-design-checks.yml"

is_figma_feature() {
  [[ -f "$DESIGN_CTX" ]] && return 0
  grep -qiE 'figma\.com|design-context\.md|QR-FIGMA|Figma Design Parity' "$PLAN" && return 0
  [[ -f "$SPEC_FILE" ]] && grep -qiE 'figma\.com|UX & Design \(Figma\)' "$SPEC_FILE" && return 0
  return 1
}

if is_figma_feature; then
  for token in "QR-FIGMA" "figma-design-checks"; do
    if ! grep -qi "$token" "$PLAN"; then
      fail "Figma feature: plan missing '$token' reference. Add Figma Design Parity section from plan-template.md"
    fi
  done
  if [[ ! -f "$FIGMA_CHECKS" ]]; then
    fail "Figma feature: missing $FIGMA_CHECKS — copy templates/figma-design-checks-template.yml during /speckit-plan"
  fi
  REGIONS_FILE="$FEATURE_DIR/figma-regions.yml"
  if [[ ! -f "$REGIONS_FILE" ]] && ! grep -q '^## Layout & Regions' "$DESIGN_CTX" 2>/dev/null; then
    fail "Figma feature: missing $REGIONS_FILE and no Layout & Regions in design-context.md — run /speckit-drupal-figma-design"
  fi
  log "Figma design checks present: $FIGMA_CHECKS"
fi

log "Plan verification passed: $PLAN"
