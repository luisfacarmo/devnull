#!/bin/bash
# DevNull — Development setup script
#
# Run on the Nextcloud server to install and enable the app.
# Prerequisites: git, php, npm (or node 18+), composer
#
# Usage:
#   git clone https://github.com/luisfacarmo/devnull.git /tmp/devnull
#   cd /tmp/devnull && bash scripts/setup-dev.sh

set -e

NEXTCLOUD_PATH="${NEXTCLOUD_PATH:-/var/www/nextcloud}"
APP_PATH="${NEXTCLOUD_PATH}/apps/devnull"

echo "==> DevNull setup"
echo "    Nextcloud: ${NEXTCLOUD_PATH}"
echo "    App path:  ${APP_PATH}"

# 1. Symlink app/ into Nextcloud apps directory
if [ -L "${APP_PATH}" ]; then
    echo "==> Symlink already exists, removing..."
    rm "${APP_PATH}"
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ln -s "${SCRIPT_DIR}/app" "${APP_PATH}"
echo "==> Symlinked ${SCRIPT_DIR}/app -> ${APP_PATH}"

# 2. Install PHP dependencies (if any)
if [ -f "${SCRIPT_DIR}/app/composer.json" ]; then
    echo "==> Installing PHP dependencies..."
    cd "${SCRIPT_DIR}/app"
    composer install --no-dev --optimize-autoloader 2>/dev/null || echo "    (no composer deps)"
fi

# 3. Install and build frontend
echo "==> Installing frontend dependencies..."
cd "${SCRIPT_DIR}/app"
npm ci --production=false
echo "==> Building frontend..."
npm run build

# 4. Install polkit rule for udisks2
POLKIT_RULE="/etc/polkit-1/rules.d/99-devnull-udisks2.rules"
if [ ! -f "${POLKIT_RULE}" ]; then
    echo "==> Installing polkit rule (requires sudo)..."
    sudo tee "${POLKIT_RULE}" > /dev/null << 'EOF'
polkit.addRule(function(action, subject) {
    if ((action.id == "org.freedesktop.udisks2.filesystem-mount" ||
         action.id == "org.freedesktop.udisks2.filesystem-unmount-others") &&
        subject.user == "www-data") {
        return polkit.Result.YES;
    }
});
EOF
    echo "    Created ${POLKIT_RULE}"
else
    echo "    Polkit rule already exists"
fi

# 5. Create mount base directory
MOUNT_BASE="/media/devnull"
if [ ! -d "${MOUNT_BASE}" ]; then
    echo "==> Creating mount directory..."
    sudo mkdir -p "${MOUNT_BASE}"
    sudo chown www-data:www-data "${MOUNT_BASE}"
fi

# 6. Enable the app in Nextcloud
echo "==> Enabling devnull app..."
sudo -u www-data php "${NEXTCLOUD_PATH}/occ" app:enable devnull

# 7. Run migrations
echo "==> Running database migrations..."
sudo -u www-data php "${NEXTCLOUD_PATH}/occ" migrations:migrate devnull

echo ""
echo "==> Done! DevNull is installed."
echo "    Open your Nextcloud and look for 'DevNull' in the navigation."
echo ""
echo "    To install system dependencies:"
echo "    sudo apt install udisks2 util-linux ntfs-3g exfatprogs hfsprogs"
