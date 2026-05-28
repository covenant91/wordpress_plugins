#!/bin/bash
# Run from plugin root: bash build-zip.sh
set -e

PLUGIN_SLUG="wp-social-publisher"
VERSION=$(grep "Version:" wp-social-publisher.php | awk '{print $3}')
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building ${ZIP_NAME}..."

# Build exclude flags from .distignore
EXCLUDES=""
while IFS= read -r line; do
    [[ -z "$line" || "$line" == \#* ]] && continue
    EXCLUDES="$EXCLUDES --exclude=./${line}"
done < .distignore

# shellcheck disable=SC2086
zip -r "../${ZIP_NAME}" . $EXCLUDES

echo ""
echo "Done! Created: ../${ZIP_NAME}"
echo "Upload via WordPress Admin > Plugins > Add New > Upload Plugin"
