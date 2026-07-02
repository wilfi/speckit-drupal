#!/usr/bin/env bash
# sync-cursor-skills.sh — copy Drupal extension agent skills into .cursor/skills/
set -euo pipefail

PROJECT_ROOT="$(pwd)"
EXT_DIR="$PROJECT_ROOT/.specify/extensions/drupal"
SRC="$EXT_DIR/.specify-dev/agent-commands/cursor-agent"
DEST="$PROJECT_ROOT/.cursor/skills"

log() { echo "drupal: $*"; }

[[ -d "$SRC" ]] || { log "No extension skills at $SRC — skip"; exit 0; }

mkdir -p "$DEST"
count=0
for skill_dir in "$SRC"/*/; do
  [[ -d "$skill_dir" ]] || continue
  name="$(basename "$skill_dir")"
  # speckit-implement is an overlay — merge into existing base skill, do not replace
  if [[ "$name" == "speckit-implement" ]]; then
    continue
  fi
  target="$DEST/$name"
  rm -rf "$target"
  cp -R "$skill_dir" "$target"
  log "Synced skill: $name"
  count=$((count + 1))
done

# Merge Drupal Figma gates into speckit-implement when base skill exists
IMPLEMENT_SKILL="$DEST/speckit-implement/SKILL.md"
OVERLAY="$SRC/speckit-implement/SKILL.md"
if [[ -f "$IMPLEMENT_SKILL" && -f "$OVERLAY" ]]; then
  if ! grep -q 'Drupal Extension — `/speckit-implement` Figma Gates' "$IMPLEMENT_SKILL" 2>/dev/null; then
    {
      echo ""
      echo "---"
      echo ""
      sed -n '10,$p' "$OVERLAY"
    } >> "$IMPLEMENT_SKILL"
    log "Appended Drupal implement overlay to speckit-implement/SKILL.md"
  fi
elif [[ ! -f "$IMPLEMENT_SKILL" && -f "$OVERLAY" ]]; then
  log "WARN: base speckit-implement/SKILL.md missing — run specify init; overlay not installed alone"
fi

log "Synced $count Drupal extension skill(s) to .cursor/skills/"
