#!/usr/bin/env bash
# Builds dist/csi-cookie-consent.zip (the installable plugin) and dist/update.json
# (the manifest installed sites read to find updates).
set -euo pipefail
cd "$(dirname "$0")/.."

slug=csi-cookie-consent
version=$(sed -n 's/^ \* Version: *//p' "$slug.php" | tr -d '[:space:]')

rm -rf dist
mkdir -p "dist/$slug"
rsync -a --exclude-from=.distignore ./ "dist/$slug/"
(cd dist && zip -qr "$slug.zip" "$slug")
php bin/manifest.php "$version" > dist/update.json

echo "Built $slug $version:"
ls -l dist/"$slug".zip dist/update.json
