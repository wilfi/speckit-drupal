#!/usr/bin/env bash
# install-drupal.sh
#
# Install the latest Drupal recommended project into the current workspace.
#
# Usage: install-drupal.sh
#
# Reads config from .specify/extensions/drupal/drupal-config.yml

set -euo pipefail

PROJECT_ROOT="$(pwd)"
EXT_DIR="$PROJECT_ROOT/.specify/extensions/drupal"
CONFIG_FILE="$EXT_DIR/drupal-config.yml"

DEFAULT_TEMPLATE="drupal/recommended-project"
DEFAULT_PHP_MIN="8.3"
DEFAULT_PRESERVE=".specify .cursor .git specs"

log() { echo "drupal: $*"; }
fail() { echo "drupal: ERROR: $*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------

PROJECT_TEMPLATE="$DEFAULT_TEMPLATE"
PHP_MIN="$DEFAULT_PHP_MIN"
PRESERVE_DIRS=($DEFAULT_PRESERVE)
MIN_STABILITY="stable"
PREFER_STABLE="true"

if [[ -f "$CONFIG_FILE" ]]; then
  _python=""
  if command -v python3 >/dev/null 2>&1; then
    _python="python3"
  elif command -v python >/dev/null 2>&1; then
    _python="python"
  fi

  if [[ -n "$_python" ]]; then
    eval "$($_python - "$CONFIG_FILE" <<'PY'
import sys
try:
    import yaml
except ImportError:
    sys.exit(0)

with open(sys.argv[1], encoding="utf-8") as f:
    cfg = yaml.safe_load(f) or {}

def emit(key, val):
    if val is None:
        return
    if isinstance(val, bool):
        print(f'{key}={"true" if val else "false"}')
    elif isinstance(val, list):
        print(f'{key}="{" ".join(str(x) for x in val)}"')
    else:
        print(f'{key}="{val}"')

emit("PROJECT_TEMPLATE", cfg.get("project_template"))
emit("PHP_MIN", cfg.get("php_min_version"))
emit("MIN_STABILITY", cfg.get("minimum_stability"))
emit("PREFER_STABLE", cfg.get("prefer_stable"))
preserve = cfg.get("preserve_dirs")
if preserve:
    emit("PRESERVE_DIRS", preserve)
PY
)"
  fi
fi

# ---------------------------------------------------------------------------
# Prerequisites
# ---------------------------------------------------------------------------

command -v composer >/dev/null 2>&1 || fail "composer not found. Install Composer: https://getcomposer.org/"
command -v php >/dev/null 2>&1 || fail "php not found."

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
version_ge() {
  php -r "exit(version_compare('$1', '$2', '>=') ? 0 : 1);"
}
version_ge "$PHP_VERSION" "$PHP_MIN" || fail "PHP $PHP_MIN+ required (found $PHP_VERSION)."

# ---------------------------------------------------------------------------
# Already installed?
# ---------------------------------------------------------------------------

if [[ -f "$PROJECT_ROOT/composer.json" ]] && grep -q '"drupal/core"' "$PROJECT_ROOT/composer.json" 2>/dev/null; then
  fail "Drupal appears already installed (drupal/core in composer.json). Remove it first or use a fresh directory."
fi

if [[ -d "$PROJECT_ROOT/web/core" ]]; then
  fail "Drupal appears already installed (web/core exists). Remove it first or use a fresh directory."
fi

# ---------------------------------------------------------------------------
# Install via Composer into temp, then merge
# ---------------------------------------------------------------------------

TMPDIR="$(mktemp -d "${TMPDIR:-/tmp}/drupal-install.XXXXXX")"
trap 'rm -rf "$TMPDIR"' EXIT

log "Creating Drupal project from $PROJECT_TEMPLATE (latest stable)..."
log "Temporary directory: $TMPDIR"

COMPOSER_ARGS=(
  create-project
  "$PROJECT_TEMPLATE"
  "$TMPDIR"
  --no-interaction
  --no-install
)

if [[ -n "${MIN_STABILITY:-}" ]]; then
  COMPOSER_ARGS+=(--stability="$MIN_STABILITY")
fi

composer "${COMPOSER_ARGS[@]}"

log "Running composer install..."
composer install --working-dir="$TMPDIR" --no-interaction

# ---------------------------------------------------------------------------
# Merge into project root (preserve Spec Kit dirs)
# ---------------------------------------------------------------------------

should_skip() {
  local name="$1"
  for preserved in "${PRESERVE_DIRS[@]}"; do
    if [[ "$name" == "$preserved" ]]; then
      return 0
    fi
  done
  return 1
}

log "Merging Drupal files into $PROJECT_ROOT ..."

shopt -s dotglob nullglob
for item in "$TMPDIR"/*; do
  base="$(basename "$item")"
  if should_skip "$base"; then
    log "Preserving existing: $base"
    continue
  fi

  dest="$PROJECT_ROOT/$base"
  if [[ -d "$item" ]]; then
    mkdir -p "$dest"
    # rsync if available, else cp -R
    if command -v rsync >/dev/null 2>&1; then
      rsync -a "$item"/ "$dest"/
    else
      cp -R "$item"/. "$dest"/
    fi
  else
    cp "$item" "$dest"
  fi
  log "Installed: $base"
done
shopt -u dotglob

# ---------------------------------------------------------------------------
# Report version
# ---------------------------------------------------------------------------

DRUPAL_VERSION=""
if [[ -f "$PROJECT_ROOT/web/core/lib/Drupal.php" ]]; then
  DRUPAL_VERSION="$(php -r "
    require '$PROJECT_ROOT/web/autoload.php';
    echo \\Drupal::VERSION;
  " 2>/dev/null || true)"
fi

if [[ -z "$DRUPAL_VERSION" && -f "$PROJECT_ROOT/vendor/drupal/core/lib/Drupal.php" ]]; then
  DRUPAL_VERSION="$(grep -o 'const VERSION = .*' "$PROJECT_ROOT/vendor/drupal/core/lib/Drupal.php" | head -1 | sed "s/.*'\([^']*\)'.*/\1/")"
fi

log "Drupal installation complete."
if [[ -n "$DRUPAL_VERSION" ]]; then
  log "Installed version: $DRUPAL_VERSION"
fi

cat <<EOF

Next steps:
  1. Point your web server at: $PROJECT_ROOT/web
  2. Browser install: open /core/install.php
     Or CLI install:
       composer require drush/drush
       vendor/bin/drush -r web site:install --yes
       vendor/bin/drush -r web user:login

EOF
