#!/bin/bash
# Package a release for the Nextcloud App Store
#
# Usage: ./scripts/package-release.sh [version]
# Example: ./scripts/package-release.sh 0.1.0

set -e

VERSION="${1:-$(grep '<version>' app/appinfo/info.xml | sed 's/.*<version>\(.*\)<\/version>.*/\1/')}"
RELEASE_NAME="devnull-${VERSION}"
OUTDIR="dist"

echo "==> Packaging DevNull v${VERSION}"

# Clean
rm -rf "${OUTDIR}"
mkdir -p "${OUTDIR}"

# Build frontend (if node_modules exists)
if [ -d "app/node_modules" ]; then
    echo "==> Building frontend..."
    cd app && npm run build && cd ..
fi

# Package (only the app/ directory, renamed to devnull/)
echo "==> Creating tarball..."
tar -czf "${OUTDIR}/${RELEASE_NAME}.tar.gz" \
    --transform "s,^app/,devnull/," \
    --exclude="app/node_modules" \
    --exclude="app/tests" \
    --exclude="app/.gitignore" \
    app/

echo "==> Package created: ${OUTDIR}/${RELEASE_NAME}.tar.gz"

# Sign (if key exists)
if [ -f "devnull.key" ]; then
    echo "==> Signing..."
    openssl dgst -sha512 -sign devnull.key "${OUTDIR}/${RELEASE_NAME}.tar.gz" | openssl base64 > "${OUTDIR}/${RELEASE_NAME}.signature"
    echo "==> Signature: ${OUTDIR}/${RELEASE_NAME}.signature"
else
    echo "==> Skipping signature (devnull.key not found)"
fi

echo "==> Done! Ready to upload to apps.nextcloud.com"
