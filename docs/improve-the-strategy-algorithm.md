# Improve the Strategy Algorithm

## Detailed TDD Checklist

This document provides a granular, TDD-based checklist for implementing the strategy algorithm improvements. Tasks are
designed to be ticked off [x] as completed, with tests passing `composer all` at each milestone. Follow Domain testing
guidelines: new Domain code requires unit tests, coverage >80%.

### Prerequisites

- [x] Run `composer all` baseline (save coverage.txt for Domain/Strategy).
- [x] Review current `GenerateStrategyAction::generate()`: threshold charging, 3 passes cons, upsert flags/cons/costs.
- [x] Review `CalculateBatteryCommandHandler`: per-period battery kWh sim using `CalculateBatteryPercentage` 
      helper (cons_manual, pv_estimate, strategy_manual flag).

### 1. Refactor `generate()` for readability and idempotency [Unit tests first]

- [x] **Unit test** `findOrCreateStrategyData()`: false if no forecast/avg_cons data; true if >=15 Strategy periods
  exist (populate $baseData=cons/costs); create base if <15 (forecast import/export, avg_cons, weekago_cons).
  *Populate $this->errors on fail.*
- [x] Implement minimal `findOrCreateStrategyData()` to pass test (extract base upsert logic).
- [x] **Unit test** idempotency: 2nd `generate()` call skips base if >=15 periods.
- [x] Refactor `generate()`: call findOrCreate first, early return false + errors if fail.
- [x] Run `composer test-coverage-text -- --filter=GenerateStrategyActionTest` >80% local.

### 2. Introduce reusable Battery/Cost Simulator [Extract to Domain service]

- [x] **Unit test** new `app/Domain/Strategy/Services/StrategyCostCalculator::calculateTotalCost(array $periods)`: input
  periods=[time, cons_kwh, pv_kwh, import_price, export_price, charging_flag]; start_battery=% ;
  returns ['total_cost' => float, 'end_battery' => %, 'battery_states' => array].
  *Reuse/duplicate `CalculateBatteryPercentage` logic or extract common BatterySim trait/service.*
- [x] Implement stub sim (no kWh logic, constant cost).
- [x] **Unit test** full sim: match `CalculateBatteryPercentage` output for sample period (cons, pv,
  charging=true/false).
- [x] Integrate real battery logic (chain `CalculateBatteryPercentage` or inline extracted).
- [x] **Unit test** baseline case: all flags false, start=100, end>=90 ok, total_cost=sum(import_kwh * price -
  export_kwh * price).
- [x] **Unit test** optimized case: flags true at cheap slots, end>=90.
- [x] Domain coverage >80% `composer test-coverage-text -- --filter=*StrategyCostCalculator`.

### 3. Baseline Strategy (charging before 16:00 to reach > 90% battery)
✓

- [x] **Unit test** `calculateBaselineCosts()`: set flags=false all, start_battery=100 (from prev period end or
  default), use avg_cons, forecast pv/prices; call sim, assert cost/end_battery.
- [x] Implement using StrategyCostCalculator. Info: it takes 4 periods to fully charge the battery from 10% to 100%,
  when the battery is at 100% and set to charge, it will maintain 100%. Consumption will be from PV then grid.
- [x] **Unit test** fail if end_battery <90 (populate errors).
- [x] Strategy 'strategy2' is baseline

### 4. Optimized Strategy (smart charging)

- [x] **Unit test** `calculateStrategyCosts()`: identify cheapest 6 import_price periods using `DateUtils` (night:16:
  00-08:00 3x, day:08:00-16:00 3x), set charging=true there; sim with avg_cons, pv/prices, start=100; assert
  cost/end_battery.
- [x] Implement cheapest selection (sort filter by time ranges).
- [x] Strategy 'strategy1' is optimized
- [x] **Unit test** fail if end_battery <90.
- [x] **Unit test** best = lower cost one copied to Stretegy 'strategy_manual'.

### 5. Upsert Best Strategy

- [x] **Unit test** `upsertStrategy()`: set strategy1=optimized flags/cost/battery (if valid), strategy2=baseline,
  strategy_manual=best flags (only upsert if null/missing), update base cons/costs if needed.
- [x] Implement, dispatch `StrategyGenerated` event post-upsert.
- [x] Refactor `generate()`: chain findOrCreate > baseline > optimized > upsert > return true.

### 6. Integrate Battery Calculation via Event

- [x] **Feature test** new Listener `HandleStrategyGenerated`: on `StrategyGenerated` dispatch `CalculateBatteryCommand`
  for the date range.
- [x] Register listener in `AppServiceProvider` or `EventServiceProvider`.
- [x] **Feature test** GenerateStrategyCommandHandler: after success, event fired, battery updated.

### 7. UI/End-to-End Verification

- [x] **Feature test** Filament `StrategyResource::GenerateAction`: dispatches command, shows success, data updated.
- [x] Manual: login test@example.com/password, https://solar-dev.test/admin/strategies, Generate, verify charts/battery.
- [x] Run `composer all` full suite green.

### 8. Cleanup & Polish

- [x] Remove redundant passes in current `getConsumption`.
- [x] Update KDoc/comments match codebase style (minimal).
- [x] `composer cs-fix`; `composer phpstan`.
- [x] Update this checklist in `docs/tasks.md` 1.1.13.

**Final verification:** `composer all` passes, Domain/Strategy coverage >85%, perf stable (docs/performance-testing.md).

### 9. Refactor StrategyCostCalculator to use StrategyType Enum

- [x] **Unit test first**: Create `app/Domain/Strategy/Enums/StrategyType.php` Enum with cases `ManualStrategy`, `Strategy1` (optimised), `Strategy2` (baseline). Default `ManualStrategy`. Test instantiation/usage.
- [x] Implement `StrategyType` Enum.
- [x] **Unit test** `StrategyCostCalculatorRequest` constructor accepts `StrategyType` instead of `bool $isOptimised`; defaults `ManualStrategy`; validate type safety.
- [x] Refactor `StrategyCostCalculatorRequest`: replace `bool $isOptimised` with `StrategyType $strategyType = StrategyType::ManualStrategy`; update PHPDoc. Tests fail → fix.
- [x] **Unit test** `StrategyCostCalculator::calculateTotalCost(StrategyType::Strategy1)` uses `$strategy->strategy1` as `$chargeStrategy`; `Strategy2` → `strategy2`; `ManualStrategy` → `strategy_manual`. Matches prior bool `true/false` behavior. End battery/cost asserts.
- [x] Refactor `StrategyCostCalculator`: `$chargeStrategy = match($request->strategyType) { StrategyType::Strategy1 => $strategy->strategy1, StrategyType::Strategy2 => $strategy->strategy2, StrategyType::ManualStrategy => $strategy->strategy_manual };` Remove bool usage. Tests fail → fix.
- [x] **Unit test** `GenerateStrategyAction::calculateBaselineCosts()` passes `StrategyType::Strategy2`; succeeds (>=90% end battery), sets costs.
- [x] **Unit test** `GenerateStrategyAction::calculateStrategyCosts()` passes `StrategyType::Strategy1`; succeeds/fails appropriately.
- [x] Update `calculateBaselineCosts()` / `calculateStrategyCosts()`: pass `StrategyType::Strategy2` / `StrategyType::Strategy1` to calculator. Update all action tests.
- [x] Update existing tests: `StrategyCostCalculatorTest*`, `GenerateStrategyActionTest*` to use new request with Enum; add coverage for all cases.
- [x] Run `composer all`: CS, PHPStan, tests green; Domain/Strategy coverage >80%.
- [x] Manual: Admin panel Generate → verify baseline/optimised/manual costs/battery match previous.

- [x] **Final verification:** `composer all` passes, no regressions.

### 10. Add calculateFinalCost() to populate/enhance manual_strategy and refactor upsertStrategy

- [x] **Unit test first**: `testCalculateFinalCostPopulatesManualWithBestWhenNull()`: all `strategy_manual` null, `optimizedCost < baselineCost` → copy `strategy1` flags to `strategy_manual`; calc `StrategyType::ManualStrategy`, enhance strategies with:
  ```
  'battery_percentage1'       => $batteryResult->batteryPercentage,
  'battery_charge_amount'     => $batteryResult->chargeAmount,
  'battery_percentage_manual' => $batteryResult->batteryPercentage,
  'import_amount'             => $batteryResult->importAmount,
  'export_amount'             => $batteryResult->exportAmount,
  ```
  Returns `true`.

- [x] **Unit test**: `testCalculateFinalCostPreservesExistingManualFlags()`: existing `true` not overwritten.

- [x] Stub `calculateFinalCost()`: Tests fail when 

- [x] **Unit test**: cost calc `ManualStrategy`, `endBattery >=90`.

- [x] Implement populate flags:
  ```
  $isOptimizedBetter = $this->optimizedCost < $this->baselineCost;
  foreach ($this->baseStrategy as $strategy) {
    $strategy->strategy_manual ?: ($isOptimizedBetter ? $strategy->strategy1 : $strategy->strategy2);
  }
  ```

- [x] Integrate `StrategyCostCalculator::calculateTotalCost(ManualStrategy)`.

- [x] **Unit test**: enhancement sets 5 fields correctly.

- [x] Implement enhancement: loop $calcResult->batteryResults, set fields on $strategy.

- [x] Update `generate()`: call after `calculateStrategyCosts()` check.

- [x] **Unit test** `upsertStrategy()` refactor: no best copy; dirty `toArray()` upsert on `id`.

- [x] Refactor `upsertStrategy()`: foreach dirty collect `toArray()`, `Strategy::upsert($updates, 'id', columns)`.

- [x] `composer test-coverage-text -- --filter=*GenerateStrategyActionTest` >80%.

- [x] Run `composer all`: green.

- [x] **Final verification:** `composer all` passes, no regressions.
