#!/usr/bin/env bash
# zip-plugin.sh — Package woo-upsell-pro for WordPress deployment.
# Usage: bash scripts/zip-plugin.sh [version]
# Output: woo-upsell-pro-{version}.zip in project root.

set -euo pipefail

PLUGIN_SLUG="woo-upsell-pro"
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-$(grep "Version:" "$PLUGIN_DIR/$PLUGIN_SLUG.php" | head -1 | awk '{print $NF}')}"
OUTPUT="$PLUGIN_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

echo "Packaging $PLUGIN_SLUG v$VERSION..."

# Remove previous zip if exists.
[ -f "$OUTPUT" ] && rm "$OUTPUT"

# Create zip from project root, including only production files.
cd "$PLUGIN_DIR/.."

zip -r "$OUTPUT" "$PLUGIN_SLUG" \
  --exclude "$PLUGIN_SLUG/node_modules/*" \
  --exclude "$PLUGIN_SLUG/node_modules" \
  --exclude "$PLUGIN_SLUG/.git/*" \
  --exclude "$PLUGIN_SLUG/.git" \
  --exclude "$PLUGIN_SLUG/plans/*" \
  --exclude "$PLUGIN_SLUG/docs/*" \
  --exclude "$PLUGIN_SLUG/scripts/*" \
  --exclude "$PLUGIN_SLUG/public/js/src/*" \
  --exclude "$PLUGIN_SLUG/public/css/src/*" \
  --exclude "$PLUGIN_SLUG/admin/src/*" \
  --exclude "$PLUGIN_SLUG/repomix-output.xml" \
  --exclude "$PLUGIN_SLUG/package.json" \
  --exclude "$PLUGIN_SLUG/package-lock.json" \
  --exclude "$PLUGIN_SLUG/composer.json" \
  --exclude "$PLUGIN_SLUG/webpack.config.js" \
  --exclude "$PLUGIN_SLUG/**/.DS_Store" \
  --exclude "$PLUGIN_SLUG/*.map" \
  --exclude "$PLUGIN_SLUG/public/js/build/*.map" \
  --exclude "$PLUGIN_SLUG/public/css/*.map"

echo "Done: $OUTPUT"
echo "Size: $(du -sh "$OUTPUT" | cut -f1)"
