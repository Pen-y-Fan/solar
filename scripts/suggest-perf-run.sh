#!/usr/bin/env bash

# Suggest perf runs when diffs touch performance-sensitive paths.
# Usage:
#   bash scripts/suggest-perf-run.sh [<base_ref>]
#
# Examples:
#   bash scripts/suggest-perf-run.sh               # defaults to origin/main
#   bash scripts/suggest-perf-run.sh HEAD~1        # compare last commit
#   bash scripts/suggest-perf-run.sh origin/develop
#
# Exit codes:
#   0 — no suggestion (no relevant changes detected)
#   10 — suggestion: relevant changes detected

set -euo pipefail

BASE_REF=${1:-origin/main}

if ! git rev-parse --quiet --verify "$BASE_REF" >/dev/null; then
  echo "Base ref '$BASE_REF' not found. Make sure you've fetched refs (e.g., git fetch)." >&2
  exit 2
fi

# Collect changed files vs base
CHANGED_FILES=$(git diff --name-only --diff-filter=ACMR "$BASE_REF"...HEAD || true)

if [ -z "$CHANGED_FILES" ]; then
  echo "No changes vs $BASE_REF."
  exit 0
fi

# Paths that should trigger a perf run suggestion
PATTERNS=(
  '^app/Filament/'
  '^app/Application/Queries/'
  '^app/Domain/.*/Repositories/'
  '^routes/'
  '^tests/Performance/'
)

TRIGGERED=()
while IFS= read -r file; do
  for pat in "${PATTERNS[@]}"; do
    if [[ "$file" =~ $pat ]]; then
      TRIGGERED+=("$file")
      break
    fi
  done
done <<<"$CHANGED_FILES"

if [ ${#TRIGGERED[@]} -eq 0 ]; then
  # Nothing to suggest
  exit 0
fi

echo "Performance-sensitive changes detected vs $BASE_REF:" >&2
printf '  - %s\n' "${TRIGGERED[@]}" >&2
echo
cat <<'EONOTE'
Suggested action:
- Re-seed Medium dataset and run local perf suite to validate baselines:
    PERF_DATASET_SIZE=medium php artisan migrate:fresh --seed --seeder=PerformanceSeeder
    APP_URL=https://solar-dev.test VUS=5 DURATION=30s PERF_DATASET_SIZE=medium bash tests/Performance/run-all.sh
- If two consecutive runs are within tolerance, consider refreshing Medium baselines and document justification in docs/performance-testing.md.

Notes:
- Keep feature caches and downsampling flags OFF for baseline comparability.
- Route/config cache can be enabled only for targeted profiling sessions (not for baseline refresh).
- You can run this helper via: composer perf-suggest [<base_ref>]
EONOTE

exit 10
