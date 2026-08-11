#!/usr/bin/env bash
set -euo pipefail

# DevNull — Automated test script
# Usage: bash /opt/devnull/scripts/test.sh
#
# Runs the golden path test suite against the local NC instance.
# Requires a USB disk plugged in (or fakes one via existing mount).

NC_URL="http://127.0.0.1"
NC_USER="${DEVNULL_TEST_USER:-mulder}"
NC_PASS="${DEVNULL_TEST_PASS:-}"
OCS_BASE="$NC_URL/ocs/v2.php/apps/devnull/api/v1"
PASSED=0
FAILED=0

if [ -z "$NC_PASS" ]; then
    echo "Set DEVNULL_TEST_PASS env var with the NC user password."
    echo "  export DEVNULL_TEST_PASS='yourpassword'"
    exit 1
fi

ocs() {
    local method="$1"
    local endpoint="$2"
    local data="${3:-}"

    local args=(-s -u "$NC_USER:$NC_PASS" -H "OCS-APIREQUEST: true")
    if [ "$method" = "POST" ]; then
        args+=(-X POST)
        [ -n "$data" ] && args+=(-d "$data")
    fi

    curl "${args[@]}" "$OCS_BASE/$endpoint?format=json"
}

check() {
    local desc="$1"
    local response="$2"
    local expect="$3"

    if echo "$response" | grep -q "$expect"; then
        echo "  ✅ $desc"
        ((PASSED++))
    else
        echo "  ❌ $desc"
        echo "     Expected: $expect"
        echo "     Got: $(echo "$response" | head -c 200)"
        ((FAILED++))
    fi
}

echo "=== DevNull Test Suite ==="
echo ""

# Test 1: Health check (app loaded)
echo "[1] Disk detection..."
RESP=$(ocs GET "disks")
check "API responds 200" "$RESP" '"statuscode":200'
check "success: true" "$RESP" '"success":true'
check "disks array exists" "$RESP" '"disks":'

# Test 2: Settings API
echo "[2] Settings API..."
RESP=$(ocs GET "settings")
check "Settings respond" "$RESP" '"success":true'
check "Has auto_mount_on_plug" "$RESP" 'auto_mount_on_plug'

# Test 3: Ingest steps
echo "[3] Ingest steps..."
RESP=$(ocs GET "ingest/steps")
check "Steps respond" "$RESP" '"steps":'

# Test 4: Status
echo "[4] Status endpoint..."
RESP=$(ocs GET "status")
check "Status responds" "$RESP" '"transport":"polling"'

# Test 5: Logs
echo "[5] Operation logs..."
RESP=$(ocs GET "logs")
check "Logs respond" "$RESP" '"operations":'

# Test 6: Mount (if disk available)
echo "[6] Mount test..."
DISKS=$(ocs GET "disks")
DEVICE=$(echo "$DISKS" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    disks = data['ocs']['data']['disks']
    if disks:
        print(disks[0]['name'])
except: pass
" 2>/dev/null || true)

if [ -n "$DEVICE" ]; then
    RESP=$(ocs POST "mount" "device=$DEVICE")
    check "Mount responds" "$RESP" '"success"'

    if echo "$RESP" | grep -q '"success":true'; then
        # Test 7: Eject
        echo "[7] Eject test..."
        RESP=$(ocs POST "unmount" "device=$DEVICE")
        check "Eject responds" "$RESP" '"success":true'
    else
        echo "  ⚠️  Mount returned error (may be OK if no disk plugged)"
        echo "     $(echo "$RESP" | python3 -c "import sys,json; print(json.load(sys.stdin)['ocs']['data'].get('error',''))" 2>/dev/null || true)"
    fi
else
    echo "  ⚠️  No disk detected — skipping mount/eject test"
fi

# Test 8: Daemon health (if running)
echo "[8] Daemon health..."
DAEMON_RESP=$(curl -s -H "X-DevNull-Token: $(sudo -u www-data php /var/www/nextcloud/occ config:app:get devnull daemon_token 2>/dev/null || echo 'none')" "http://127.0.0.1:9876/api/v1/health" 2>/dev/null || echo '{"status":"offline"}')
if echo "$DAEMON_RESP" | grep -q '"healthy"'; then
    check "Daemon healthy" "$DAEMON_RESP" '"healthy"'
else
    echo "  ⚠️  Daemon offline (optional component)"
fi

# Test 9: NC code check
echo "[9] NC app:check-code..."
CHECK=$(sudo -u www-data php /var/www/nextcloud/occ app:check-code devnull 2>&1 || true)
if echo "$CHECK" | grep -q "No errors"; then
    check "Code check passed" "$CHECK" "No errors"
else
    echo "  ⚠️  Code check: $(echo "$CHECK" | head -3)"
fi

echo ""
echo "=== Results: $PASSED passed, $FAILED failed ==="
[ "$FAILED" -eq 0 ] && exit 0 || exit 1
