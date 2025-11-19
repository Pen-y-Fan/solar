#!/usr/bin/env bash
set -euo pipefail

# Automate route/config cache A/B runs for k6 scenarios.
# Usage examples:
#   APP_URL=https://solar-dev.test VUS=5 DURATION=30s PERF_DATASET_SIZE=medium bash tests/Performance/cache-ab.sh
#   APP_URL=https://solar-dev.test VUS=8 DURATION=90s PERF_DATASET_SIZE=large OUT_DIR=perf-reports/cache-ab bash tests/Performance/cache-ab.sh
#
# Notes:
# - This script is local-only. It does not alter CI behavior.
# - It will clear caches, run scenarios (OFF), then warm route+config caches and run scenarios again (ON).
# - Results are saved under ${OUT_DIR:-perf-reports}/cache-ab/{off,on}/ as *.summary.json files, with dataset suffix when provided.

APP_URL_ENV=${APP_URL:-"https://solar-dev.test"}
VUS=${VUS:-5}
DURATION=${DURATION:-30s}
USE_BOOTSTRAP_AUTH=${USE_BOOTSTRAP_AUTH:-true}
DATASET_SIZE=${PERF_DATASET_SIZE:-}
BASE_OUT_DIR=${OUT_DIR:-"perf-reports"}
RUN_ALL=${RUN_ALL_SCRIPT:-"tests/Performance/run-all.sh"}

suffix=""
if [[ -n "${DATASET_SIZE}" ]]; then
  suffix=".${DATASET_SIZE}"
fi

# Ensure artisan available
if [[ ! -f artisan ]]; then
  echo "Run from project root (artisan not found)." >&2
  exit 1
fi

run_phase() {
  local phase="$1"  # off | on
  local out_dir="${BASE_OUT_DIR}/cache-ab/${phase}"
  printf "\n=== Phase: %s (output -> %s) ===\n\n" "$phase" "$out_dir"
  OUT_DIR="$out_dir" \
  APP_URL="$APP_URL_ENV" \
  VUS="$VUS" \
  DURATION="$DURATION" \
  USE_BOOTSTRAP_AUTH="$USE_BOOTSTRAP_AUTH" \
  PERF_DATASET_SIZE="$DATASET_SIZE" \
    bash "$RUN_ALL" || {
      echo "WARNING: run-all failed in phase '${phase}' (continuing to collect artifacts and run next phase)" >&2
    }
}

# OFF: clear caches
php artisan optimize:clear
run_phase off

# ON: warm route+config caches
php artisan route:clear || true
php artisan config:clear || true
php artisan route:cache
php artisan config:cache
run_phase on

# Restore dev/test friendly state: clear caches to avoid impacting later runs (e.g., PHPUnit)
php artisan optimize:clear || true

printf "\nDone. Compare summaries under: %s\n" "${BASE_OUT_DIR}/cache-ab/{off,on}"
