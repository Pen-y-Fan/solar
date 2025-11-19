#!/usr/bin/env bash
set -euo pipefail

# Run Strategies resource performance scenario with sensible defaults.
#
# Usage examples:
#   APP_URL=https://solar-dev.test ./scripts/run-strategies-perf.sh
#   VUS=5 DURATION=30s ./scripts/run-strategies-perf.sh --seed-medium
#   PERF_PROFILE=true ./scripts/run-strategies-perf.sh --repeat 2
#
# Options:
#   --seed-medium    Re-seed the Medium dataset before running
#   --repeat N       Repeat the k6 run N times (default 1)
#   --no-clear       Skip php artisan optimize:clear
#
# Env:
#   APP_URL             Target base URL (default: https://solar-dev.test)
#   VUS                 Virtual users (default: 5)
#   DURATION            Test duration (default: 30s)
#   PERF_PROFILE        When "true", enables SQL profiling in the app (affects logs)
#   USE_BOOTSTRAP_AUTH  Leave default (true). Scripts auto-login to obtain laravel_session.

APP_URL="${APP_URL:-https://solar-dev.test}"
VUS="${VUS:-5}"
DURATION="${DURATION:-30s}"
PERF_PROFILE="${PERF_PROFILE:-false}"
USE_BOOTSTRAP_AUTH="${USE_BOOTSTRAP_AUTH:-true}"

REPEAT=1
SEED_MEDIUM=false
CLEAR_OPTIMIZE=true

while [[ $# -gt 0 ]]; do
  case "$1" in
    --seed-medium)
      SEED_MEDIUM=true
      shift
      ;;
    --repeat)
      REPEAT=${2:-1}
      shift 2
      ;;
    --no-clear)
      CLEAR_OPTIMIZE=false
      shift
      ;;
    -h|--help)
      grep '^#' "$0" | sed -E 's/^# ?//'
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      exit 2
      ;;
  esac
done

echo "== Strategies Perf Run =="
echo "APP_URL=$APP_URL VUS=$VUS DURATION=$DURATION PERF_PROFILE=$PERF_PROFILE USE_BOOTSTRAP_AUTH=$USE_BOOTSTRAP_AUTH"

if [[ "$CLEAR_OPTIMIZE" == "true" ]]; then
  echo "- Clearing caches (route/config/views)..."
  php artisan optimize:clear >/dev/null
fi

# Ensure feature flags are disabled for deterministic runs
export FEATURE_CACHE_FORECAST_CHART=false
export FEATURE_CACHE_STRAT_SUMMARY=false
export FORECAST_DOWNSAMPLE=false
export INVERTER_DOWNSAMPLE=false

if [[ "$SEED_MEDIUM" == "true" ]]; then
  echo "- Reseeding Medium dataset..."
  PERF_DATASET_SIZE=medium php artisan migrate:fresh --seed --seeder=PerformanceSeeder
fi

export APP_URL VUS DURATION PERF_PROFILE USE_BOOTSTRAP_AUTH

SCRIPT="tests/Performance/strategies.k6.js"

run_k6() {
  if command -v k6 >/dev/null 2>&1; then
    k6 run "$SCRIPT"
  else
    echo "- Local k6 not found; using Docker grafana/k6..."
    docker run --rm -i \
      -e APP_URL \
      -e VUS \
      -e DURATION \
      -e PERF_PROFILE \
      -e USE_BOOTSTRAP_AUTH \
      -v "$(pwd)":"/work" -w "/work" \
      grafana/k6 run "$SCRIPT"
  fi
}

for i in $(seq 1 "$REPEAT"); do
  echo "- Run $i/$REPEAT"
  run_k6
done

echo "== Done =="
