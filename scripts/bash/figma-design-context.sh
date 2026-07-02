#!/usr/bin/env bash
# figma-design-context.sh — scaffold design-context.md for a feature (Figma MCP fills content)
set -euo pipefail

PROJECT_ROOT="$(pwd)"
FEATURE_DIR="${1:-}"
FIGMA_URL="${2:-}"
EXT_DIR="$PROJECT_ROOT/.specify/extensions/drupal"
TEMPLATE="$EXT_DIR/templates/design-context-template.md"
CONFIG="$EXT_DIR/drupal-config.yml"

log() { echo "drupal-figma: $*"; }

if [[ -z "$FEATURE_DIR" ]]; then
  if [[ -f "$PROJECT_ROOT/.specify/feature.json" ]]; then
    _dir="$(grep -o '"feature_directory"[[:space:]]*:[[:space:]]*"[^"]*"' "$PROJECT_ROOT/.specify/feature.json" 2>/dev/null | sed 's/.*"\([^"]*\)"$/\1/' | head -1)"
    [[ -n "$_dir" ]] && FEATURE_DIR="$PROJECT_ROOT/$_dir"
  fi
fi

[[ -n "$FEATURE_DIR" && -d "$FEATURE_DIR" ]] || {
  log "Usage: figma-design-context.sh [feature-dir] [figma-url]"
  log "Or set .specify/feature.json feature_directory"
  exit 1
}

OUT="$FEATURE_DIR/design-context.md"
STRATEGY="brownfield"
BASE_THEME="olivero"

if [[ -f "$CONFIG" ]]; then
  _s="$(grep -E 'theme_strategy:' "$CONFIG" 2>/dev/null | head -1 | sed 's/.*: *//; s/"//g' | tr -d ' ')"
  [[ -n "$_s" ]] && STRATEGY="$_s"
  _b="$(grep -E 'base_theme:' "$CONFIG" 2>/dev/null | head -1 | sed 's/.*: *//; s/"//g' | tr -d ' ')"
  [[ -n "$_b" ]] && BASE_THEME="$_b"
fi

if [[ -f "$OUT" ]]; then
  log "design-context.md already exists: $OUT"
else
  [[ -f "$TEMPLATE" ]] || { log "Missing template: $TEMPLATE"; exit 1; }
  cp "$TEMPLATE" "$OUT"
  log "Scaffolded: $OUT"
fi

if [[ -n "$FIGMA_URL" ]]; then
  log "Figma URL: $FIGMA_URL"
  log "Next: run /speckit-drupal-figma-design with Figma MCP to fill design-context.md"
fi

log "Theme strategy: $STRATEGY (base: $BASE_THEME)"
log "Rules: templates/figma-design-rules.md"
