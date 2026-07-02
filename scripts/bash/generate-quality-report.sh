#!/usr/bin/env bash
# generate-quality-report.sh — run quality checks and write specs/<feature>/quality-results.md
set -euo pipefail

PROJECT_ROOT="$(pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMMON_SH="$PROJECT_ROOT/.specify/scripts/bash/common.sh"
FEATURE_DIR_ARG="${1:-}"

log() { echo "drupal: $*"; }
fail() { echo "drupal: ERROR: $*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage: generate-quality-report.sh [FEATURE_DIR]

Runs verify-quality.sh for the active Spec Kit feature and writes
specs/<feature>/quality-results.md (stakeholder QA report).

Feature resolution (first match wins):
  1. FEATURE_DIR argument
  2. SPECIFY_FEATURE_DIRECTORY env var
  3. .specify/feature.json → feature_directory
EOF
}

[[ "${1:-}" == "-h" || "${1:-}" == "--help" ]] && { usage; exit 0; }

resolve_feature_dir() {
  if [[ -n "$FEATURE_DIR_ARG" ]]; then
    echo "$FEATURE_DIR_ARG"
    return 0
  fi

  if [[ -f "$COMMON_SH" ]]; then
    # shellcheck source=/dev/null
    source "$COMMON_SH"
    eval "$(get_feature_paths)" || return 1
    echo "$FEATURE_DIR"
    return 0
  fi

  if [[ -n "${SPECIFY_FEATURE_DIRECTORY:-}" ]]; then
    echo "$SPECIFY_FEATURE_DIRECTORY"
    return 0
  fi

  if [[ -f "$PROJECT_ROOT/.specify/feature.json" ]]; then
    local _dir
    _dir="$(grep -o '"feature_directory"[[:space:]]*:[[:space:]]*"[^"]*"' "$PROJECT_ROOT/.specify/feature.json" 2>/dev/null | sed 's/.*"\([^"]*\)"$/\1/' | head -1)"
    [[ -n "$_dir" ]] && echo "$_dir" && return 0
  fi

  return 1
}

FEATURE_DIR="$(resolve_feature_dir)" || fail "No active feature. Pass FEATURE_DIR or set .specify/feature.json feature_directory."

[[ "$FEATURE_DIR" != /* ]] && FEATURE_DIR="$PROJECT_ROOT/$FEATURE_DIR"
[[ -d "$FEATURE_DIR" ]] || fail "Feature directory not found: $FEATURE_DIR"
[[ -f "$FEATURE_DIR/quality-checks.yml" || -f "$FEATURE_DIR/plan.md" ]] \
  || fail "Not a feature directory (missing quality-checks.yml or plan.md): $FEATURE_DIR"

REL_FEATURE="${FEATURE_DIR#"$PROJECT_ROOT/"}"
REPORT_FILE="$FEATURE_DIR/quality-results.md"

log "Active feature: $REL_FEATURE"
log "Running quality verification and generating stakeholder report..."

"$SCRIPT_DIR/verify-quality.sh" "$REL_FEATURE"
[[ -f "$REPORT_FILE" ]] || fail "Report was not written: $REL_FEATURE/quality-results.md"

log "Stakeholder QA report: $REL_FEATURE/quality-results.md"
