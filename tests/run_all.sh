#!/usr/bin/env bash
# Full battery: fresh install → static → security → integrity audit → e2e suites → page suite.
cd "$(dirname "$0")/.." || exit 1
PORT="${PORT:-8899}"; LOG=/tmp/sf_battery_php.log
: > "$LOG"
php -d error_reporting=E_ALL -d display_errors=0 -d log_errors=1 -d error_log="$LOG" -d html_errors=0 \
    -S 127.0.0.1:$PORT -t . > /tmp/sf_battery_server.log 2>&1 &
SRV=$!
sleep 2
run() { echo "----- $* -----"; "$@" 2>&1 | tail -12; }
python3 tests/fresh_install.py
run php tests/static.php
run php tests/security_scan.php
run php tests/audit.php
run python3 tests/e2e_pipeline.py
run python3 tests/e2e_platform.py
run python3 tests/e2e_actions.py
run python3 tests/security_runtime.py
run python3 tests/e2e_pages.py
run python3 tests/feature_matrix.py
run python3 tests/e2e_new_features.py
run python3 tests/e2e_admin.py
kill $SRV 2>/dev/null
echo "----- PHP error log -----"
if [ -s "$LOG" ]; then echo "PHP ERRORS:"; head -25 "$LOG"; else echo "clean (0 bytes)"; fi
