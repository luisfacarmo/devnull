#!/usr/bin/env bash
set -euo pipefail

# DevNull — Deploy script
# Usage: sudo bash /opt/devnull/scripts/deploy.sh [--with-daemon]
#
# Runs on the server (LibraryOfAlexandria). Does everything:
# 1. git pull
# 2. NC app disable/enable (forces migration)
# 3. Apache restart
# 4. Optionally installs/restarts the Python daemon

DEVNULL_DIR="/opt/devnull"
NC_OCC="sudo -u www-data php /var/www/nextcloud/occ"
WITH_DAEMON=false

# Parse args
for arg in "$@"; do
    case $arg in
        --with-daemon) WITH_DAEMON=true ;;
    esac
done

echo "=== DevNull Deploy ==="
echo ""

# 1. Pull
echo "[1/5] git pull..."
cd "$DEVNULL_DIR"
git pull --ff-only
echo ""

# 2. NC app cycle
echo "[2/5] Nextcloud app disable/enable..."
$NC_OCC app:disable devnull 2>/dev/null || true
$NC_OCC app:enable devnull
echo ""

# 3. Apache restart
echo "[3/5] Restarting Apache (OPcache flush)..."
systemctl restart apache2
echo ""

# 4. Validate
echo "[4/5] Validating..."
VERSION=$($NC_OCC app:list 2>/dev/null | grep -oP 'devnull: \K[0-9.]+' || echo "unknown")
echo "  App version: $VERSION"

TABLES=$(mysql -N nextcloud -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='nextcloud' AND table_name LIKE 'oc_devnull%';" 2>/dev/null || echo "?")
echo "  DB tables: $TABLES"
echo ""

# 5. Daemon (optional)
if [ "$WITH_DAEMON" = true ]; then
    echo "[5/5] Deploying daemon..."
    DAEMON_DIR="$DEVNULL_DIR/daemon"

    # Create venv if needed
    if [ ! -d "$DAEMON_DIR/.venv" ]; then
        echo "  Creating virtualenv..."
        python3 -m venv "$DAEMON_DIR/.venv"
    fi

    # Install/update
    echo "  Installing daemon..."
    "$DAEMON_DIR/.venv/bin/pip" install -q -e "$DAEMON_DIR"

    # .env
    if [ ! -f "$DAEMON_DIR/.env" ]; then
        echo "  Creating .env from example..."
        cp "$DAEMON_DIR/.env.example" "$DAEMON_DIR/.env"
        TOKEN=$(openssl rand -hex 32)
        sed -i "s/change-me-to-a-secure-random-string/$TOKEN/" "$DAEMON_DIR/.env"
        echo "  Generated token: $TOKEN"
        echo "  Registering token in Nextcloud..."
        $NC_OCC config:app:set devnull daemon_token --value="$TOKEN"
    fi

    # systemd
    if [ ! -f /etc/systemd/system/devnull-daemon.service ]; then
        echo "  Installing systemd service..."
        cp "$DAEMON_DIR/systemd/devnull-daemon.service" /etc/systemd/system/
        systemctl daemon-reload
    fi

    echo "  Restarting daemon..."
    systemctl restart devnull-daemon
    sleep 2
    if systemctl is-active --quiet devnull-daemon; then
        echo "  Daemon: running"
    else
        echo "  Daemon: FAILED (check: journalctl -u devnull-daemon)"
    fi
else
    echo "[5/5] Daemon: skipped (use --with-daemon to install)"
fi

echo ""
echo "=== Deploy complete ==="
echo "  Version: $VERSION"
echo "  Rollback: git checkout v0.3.0-stable"
echo ""
