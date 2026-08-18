#!/bin/sh
# Restarts the container if it stays unresponsive without ever crashing
# (e.g. all php-fpm workers wedged). Docker's own restart policy only fires
# on process exit, so a hung-but-alive process is invisible to it — this
# watchdog closes that gap by killing supervisord (pid 1) after enough
# consecutive failures, which exits the container and lets
# `restart: unless-stopped` bring it back up clean.
set -u

URL="${WATCHDOG_URL:-http://127.0.0.1:8080/up}"
INTERVAL="${WATCHDOG_INTERVAL:-15}"
MAX_FAILURES="${WATCHDOG_MAX_FAILURES:-5}"
failures=0

while true; do
    sleep "$INTERVAL"

    if wget -q -T 5 -O /dev/null "$URL"; then
        failures=0
        continue
    fi

    failures=$((failures + 1))
    echo "healthcheck-watchdog: health check failed ($failures/$MAX_FAILURES)" >&2

    if [ "$failures" -ge "$MAX_FAILURES" ]; then
        echo "healthcheck-watchdog: giving up after $MAX_FAILURES consecutive failures, restarting container" >&2
        kill -TERM 1
        exit 1
    fi
done
