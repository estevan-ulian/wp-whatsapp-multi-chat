#!/usr/bin/env bash
# Builds the installable plugin zip (build/wp-whatsapp-multi-chat.zip).
# Used by `composer build` locally and by the release workflow, so the zip
# tested locally is the same one sites download through "Update now".
set -euo pipefail

SLUG="wp-whatsapp-multi-chat"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$ROOT/build"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

cd "$ROOT"

echo "→ Building frontend assets"
(
  cd frontend
  [ -d node_modules ] || npm ci
  npm run build
)

echo "→ Copying runtime files (see .distignore)"
mkdir -p "$STAGE/$SLUG"
# The project's vendor/ holds dev tools (PHPUnit); the package gets its own
# production-only install below, leaving the working copy untouched.
rsync -a --exclude-from=.distignore --exclude=/vendor ./ "$STAGE/$SLUG/"

echo "→ Installing production PHP dependencies"
cp composer.json composer.lock "$STAGE/$SLUG/"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts \
  --working-dir="$STAGE/$SLUG"
rm "$STAGE/$SLUG/composer.json" "$STAGE/$SLUG/composer.lock"

# Without vendor/ the updater does not load and the site stops receiving
# updates; without the frontend build the chat widget does not render.
for required in vendor/autoload.php assets/frontend/js/wp-whatsapp-multi-chat-plugin.js; do
  if [ ! -f "$STAGE/$SLUG/$required" ]; then
    echo "Error: $required missing from package" >&2
    exit 1
  fi
done

echo "→ Creating zip"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
# WordPress installs into the zip's root folder, so it must be the plugin slug
# for an update to replace the existing install in place.
(cd "$STAGE" && zip -rq "$BUILD_DIR/$SLUG.zip" "$SLUG")

echo "✅ Build successful: build/$SLUG.zip"
