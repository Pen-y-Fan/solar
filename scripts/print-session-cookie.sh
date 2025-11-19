#!/usr/bin/env bash
set -euo pipefail

# Print a valid Laravel session cookie for local performance tests.
#
# Usage:
#   bash scripts/print-session-cookie.sh            # prints: laravel_session=...
#   bash scripts/print-session-cookie.sh header     # prints: Cookie: laravel_session=...
#   bash scripts/print-session-cookie.sh curl       # prints: -H "Cookie: laravel_session=..."
#   APP_URL=https://solar-dev.test bash scripts/print-session-cookie.sh
#   INSECURE=true bash scripts/print-session-cookie.sh  # allow self-signed TLS (curl -k)
#
# Notes:
# - Requires the local-only auth bootstrap endpoint exposed in dev: GET /_auth/bootstrap
# - Ensure the DB is seeded (e.g., PerformanceSeeder) so the test user exists.

APP_URL="${APP_URL:-https://solar-dev.test}"
INSECURE_FLAG=""
if [[ "${INSECURE:-false}" == "true" ]]; then
  INSECURE_FLAG="-k"
fi

tmp_headers="$(mktemp)"
status="$(curl -sS -i ${INSECURE_FLAG} "${APP_URL%%/}/_auth/bootstrap" -o >(tee "$tmp_headers") -w "%{http_code}" || true)"

# Extract the laravel_session cookie from Set-Cookie header
cookie_line="$(grep -i '^Set-Cookie:' "$tmp_headers" | grep -i 'laravel_session=' | head -n1 || true)"
rm -f "$tmp_headers"

if [[ -z "${cookie_line}" ]]; then
  echo "Error: could not obtain laravel_session from ${APP_URL}/_auth/bootstrap (HTTP ${status})." >&2
  exit 1
fi

cookie="$(sed -E 's/^Set-Cookie:\s*([^;]+).*/\1/i' <<<"$cookie_line")"

format="${1:-plain}"
case "$format" in
  plain)
    echo "$cookie"
    ;;
  header)
    echo "Cookie: $cookie"
    ;;
  curl)
    echo "-H \"Cookie: $cookie\""
    ;;
  *)
    echo "$cookie"
    ;;
esac
