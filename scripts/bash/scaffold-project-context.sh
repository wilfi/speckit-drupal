#!/usr/bin/env bash
# scaffold-project-context.sh — create .specify/drupal/ project context from templates
set -euo pipefail

PROJECT_ROOT="$(pwd)"
EXT_DIR="$PROJECT_ROOT/.specify/extensions/drupal"
TEMPLATES="$EXT_DIR/templates"
OUT_DIR="$PROJECT_ROOT/.specify/drupal"

log() { echo "drupal: $*"; }

mkdir -p "$OUT_DIR"

install_if_missing() {
  local dest="$1"
  local src="$2"
  local label="$3"
  if [[ -f "$dest" ]]; then
    log "Keep existing: ${dest#"$PROJECT_ROOT/"}"
    return 0
  fi
  [[ -f "$src" ]] || return 0
  cp "$src" "$dest"
  log "Created: ${dest#"$PROJECT_ROOT/"} ($label)"
}

install_if_missing "$OUT_DIR/README.md" "$TEMPLATES/project-context-README-template.md" "index"
install_if_missing "$OUT_DIR/data-model.md" "$TEMPLATES/project-data-model-template.md" "data model"
install_if_missing "$OUT_DIR/site-structure.md" "$TEMPLATES/project-site-structure-template.md" "site structure"
install_if_missing "$OUT_DIR/sites.yml" "$TEMPLATES/project-sites-template.yml" "sites manifest"

log "Project Drupal context: .specify/drupal/"
