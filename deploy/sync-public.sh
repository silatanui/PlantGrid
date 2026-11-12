#!/usr/bin/env bash
# Sync Laravel public assets into the cPanel subdomain document root.
# Run from ~/plantgrid_app after every git pull that changes public/build.
set -euo pipefail

APP_DIR="${HOME}/plantgrid_app"
DOCROOT="${HOME}/plantgrid.tanuisila.dev"

cp -a "${APP_DIR}/public/." "${DOCROOT}/"
cp "${APP_DIR}/deploy/cpanel-index.php" "${DOCROOT}/index.php"

# Keep storage symlink if the host allows it.
if ln -sfn ../plantgrid_app/storage/app/public "${DOCROOT}/storage" 2>/dev/null; then
  echo "storage symlink refreshed"
else
  echo "storage symlink skipped (ln disabled); ensure ${DOCROOT}/storage is usable"
fi

echo "Synced public/ → ${DOCROOT}"
echo "CSS/JS should now match plantgrid_app/public/build/manifest.json"
