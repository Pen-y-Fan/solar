#!/usr/bin/env bash
set -euo pipefail

APP_URL_ENV=${APP_URL:-"https://solar-dev.test"}
VUS=${VUS:-5}
DURATION=${DURATION:-30s}
USE_BOOTSTRAP_AUTH=${USE_BOOTSTRAP_AUTH:-true}
OUT_DIR=${OUT_DIR:-"perf-reports"}
DATASET_SIZE=${PERF_DATASET_SIZE:-}

# Strategy generation Livewire flags (optional)
STRAT_GEN_LIVEWIRE=${STRAT_GEN_LIVEWIRE:-false}
LIVEWIRE_ENDPOINT=${LIVEWIRE_ENDPOINT:-}
LIVEWIRE_PAYLOAD_BASE64=${LIVEWIRE_PAYLOAD_BASE64:-}
STRATEGY_PERIOD=${STRATEGY_PERIOD:-today}
STRAT_GEN_CONCURRENT_POSTS=${STRAT_GEN_CONCURRENT_POSTS:-false}

mkdir -p "$OUT_DIR"

has_k6() {
  command -v k6 >/dev/null 2>&1
}

suffix=""
if [[ -n "${DATASET_SIZE}" ]]; then
  suffix=".${DATASET_SIZE}"
fi

echo "Running k6 scenarios against ${APP_URL_ENV} with VUS=${VUS} DURATION=${DURATION} (USE_BOOTSTRAP_AUTH=${USE_BOOTSTRAP_AUTH}) PERF_DATASET_SIZE=${DATASET_SIZE:-none}"

run_scenario() {
  local name="$1"
  local file="tests/Performance/${name}.k6.js"
  local out_json="${OUT_DIR}/${name}${suffix}.summary.json"

  if [[ ! -f "$file" ]]; then
    echo "Missing scenario file: $file" >&2
    return 1
  fi

  echo "\n==> ${name}${suffix}"

  if has_k6; then
    APP_URL="$APP_URL_ENV" \
    VUS="$VUS" \
    DURATION="$DURATION" \
    USE_BOOTSTRAP_AUTH="$USE_BOOTSTRAP_AUTH" \
    PERF_DATASET_SIZE="${DATASET_SIZE}" \
    STRAT_GEN_LIVEWIRE="$STRAT_GEN_LIVEWIRE" \
    LIVEWIRE_ENDPOINT="$LIVEWIRE_ENDPOINT" \
    LIVEWIRE_PAYLOAD_BASE64="$LIVEWIRE_PAYLOAD_BASE64" \
    STRATEGY_PERIOD="$STRATEGY_PERIOD" \
    STRAT_GEN_CONCURRENT_POSTS="$STRAT_GEN_CONCURRENT_POSTS" \
      k6 run --summary-export "$out_json" "$file"
  else
    echo "k6 binary not found, using Docker grafana/k6..."
    docker run --rm \
      -e APP_URL="$APP_URL_ENV" \
      -e VUS="$VUS" \
      -e DURATION="$DURATION" \
      -e USE_BOOTSTRAP_AUTH="$USE_BOOTSTRAP_AUTH" \
      -e PERF_DATASET_SIZE="${DATASET_SIZE}" \
      -e STRAT_GEN_LIVEWIRE="$STRAT_GEN_LIVEWIRE" \
      -e LIVEWIRE_ENDPOINT="$LIVEWIRE_ENDPOINT" \
      -e LIVEWIRE_PAYLOAD_BASE64="$LIVEWIRE_PAYLOAD_BASE64" \
      -e STRATEGY_PERIOD="$STRATEGY_PERIOD" \
      -e STRAT_GEN_CONCURRENT_POSTS="$STRAT_GEN_CONCURRENT_POSTS" \
      -v "$(pwd)":"/work" \
      -w "/work" \
      grafana/k6 run --summary-export "$out_json" "$file"
  fi
}

# Core scenarios to refresh baselines per plan
run_scenario forecasts
run_scenario inverter
run_scenario strategies

# Optional extras
# run_scenario dashboard
run_scenario strategy-generation

echo "\nAll done. Summaries saved to ${OUT_DIR}/"
