# Battery Start Percentage Task List

## Description

**Issue**: When recalculating strategies, the battery start percentage for the first period in the range (e.g., 16:00)
uses the current first strategy's `battery_percentage_manual ?? 100`, causing incorrect chaining. Instead, it should use
the **previous period's end battery percentage** (e.g., 15:30 end as 16:00 start) for accurate simulation across
periods.

**Current behavior** (CalculateBatteryCommandHandler.php):

- Queries strategies for date range [start=16:00 prev day, end=16:00 today].
- Sets `startBatteryPercentage($strategies->first()->battery_percentage_manual ?? 100)`.
- Iterates, calculates sequentially, persists each `save()`.

**TODOs confirmed correct**:

- Fetch prev strategy end battery.
- Optimize select fields.
- Batch upsert instead of per-save + transaction.
- DTO for `calculate()` result.

**Related files**:

- `app/Application/Commands/Strategy/CalculateBatteryCommandHandler.php`
- `app/Helpers/CalculateBatteryPercentage.php`
- `app/Domain/Strategy/Models/Strategy.php` (VO accessors)
- `docs/tasks.md` (mark [x] 1.1.12 when done)

## Acceptance Criteria

- Recalc works across days: first period start = true prior end %.
- No prev strategy → fallback 100%.
- Optimized: single prev query + range query (minimal fields).
- Batch persist (upsert or bulk update).
- Typed DTO integration.
- `composer all` green (tests, CS, PHPStan).
- Tests: repro failure, verify fix (seed prev/range strategies).

## Detailed Tasks

### 1. Implement DTO for battery results ✓

- [x] Create `app/Application/Commands/Strategy/DTOs/BatteryCalculationResult.php` (readonly, constructor props:
  `batteryPercentage`:int, `chargeAmount`/ `importAmount`/ `exportAmount`:float).
- [x] Update handler `calculateForStrategy`:
  ```
  $result = new BatteryCalculationResult(...$this->calculator->...->calculate());
  $strategy->battery_percentage1 = $result->batteryPercentage;
  // etc.
  ```
- [x] Test DTO (trivial; optional unit, covered by handler feature).

### 2. Fix battery start percentage fetch *

- [x] Add prev fetch before range query:
  ```
  $prevPeriod = (clone $start)->subMinutes(30)->format('Y-m-d H:i:s');
  $prevBattery = Strategy::where('period', $prevPeriod)
    ->where('user_id', $command->userId) // add user filter!
    ->value('battery_percentage_manual') ?? 100;
  $this->calculator->startBatteryPercentage($prevBattery);
  ```
- [x] Drop first strategy from chaining? No: still calc full range, but start correct.
- [x] Handle no prev: 100.

### 3. Optimize queries

- [x] Range query: select minimal:
  `'id', 'period', 'strategy_manual', 'consumption_manual', 'consumption_last_week', 'consumption_average', 'forecast_id'` (
  for VO & PV).
- [x] Use `with('forecast:id,pv_estimate')` minimal.

### 4. Batch persist

- [x] Collect updates in loop (set props on models).
- [x] After loop:
  `Strategy::upsert($strategies->map(fn($s) => ['id' => $s->id, 'battery_percentage1' => $s->battery_percentage1, ... ]), ['id'], ['battery_percentage1', 'battery_charge_amount', 'battery_percentage_manual', 'import_amount', 'export_amount']);`
- [x] Remove per-save/DB::transaction.

### 5. Tests

- [x] Feature: `tests/Feature/Application/Commands/Strategy/CalculateBatteryCommandHandlerTest.php`
    - Test prev fetch: seed prev strategy (15:30, battery=50), range starts 16:00 battery_manual=null → first calc
      starts@50.
    - Test chaining: verify sequential % updates.
    - Test no prev: first starts@100.
    - Test DTO unpack/assign.
    - Assert batch persist (count changes).
- [x] Update CommandBus mappings test if needed.
- [x] Update existing tests `tests/Unit/Helpers/CalculateBatteryPercentageTest.php` to handle return signature to DTO

### 6. Verification & Quality

- [x] Remove TODOs.
- [x] Run `composer all`.
- [x] Manual: seed data, UI recalc strategy, charts show correct battery chain.
- [x] PHPStan baseline if new issues.
- [x] Update `docs/tasks.md`: [x] 1.1.12.

**Estimated effort**: 4-6h (code 2h, tests 2h, verify 1h).
**Risks**: VO hydration if select minimal; upsert conflicts (use id primary).
