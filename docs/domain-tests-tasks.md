# Domain Test Tasks

Domain coverage forValue Object (VO), Models and Actions

Last updated: 2025-09-28 20:48

Purpose: Ensure every Model and Action that uses a Value Object (VO) has focused unit tests verifying correct mapping,
invariants, accessors/mutators, and edge cases. This sub-task list prevents duplicate efforts by tracking VO-specific
tests per class.

Conventions

- Prefer fast, isolated tests (do not hit DB unless strictly needed).
- Use 'Unit' tests when no DB access is required.
- Feature tests use the `use DatabaseMigrations;` trait are 'Feature' tests.
- CommandHandler tests: keep in Unit if fully mocked with no DB coupling; move to Feature if they hit the DB or use migrations.
- Cover: round-trip mapping (attributes <-> VO), invariants/validation, null handling, boundary values, and
  helper/derived methods.
- Acceptance criteria are listed per item; mark [x] only when all criteria for that class are satisfied.
- Make sure you follow `.junie/guidelines.md`, run `composer all` before marking a task complete.

---

## Strategy Domain

### Model: App\Domain\Strategy\Models\Strategy

Value Objects: ConsumptionData, BatteryState, StrategyType, CostData

- [x] Tests: VO mapping and accessors/mutators for all Strategy VOs
    - Files:
        - tests/Unit/Domain/Strategy/StrategyModelTest.php
    - Acceptance criteria:
        - [x] ConsumptionData: negative and null values clamped/handled; getters and setters round-trip; best-estimate
          logic covered if used via model helpers.
        - [x] BatteryState: mapping of percentage, chargeAmount, manualPercentage; invalid ranges rejected; effective
          percentage logic verified when applicable.
        - [x] StrategyType: boolean attributes map to VO constants and back; manual flag maps to int/null; effective
          strategy logic verified when applicable.
        - [x] CostData: import/export values and consumption costs mapped; helper methods (net cost, comparisons)
          covered when exposed via model.

Notes: Current StrategyModelTest already covers core VO mapping and mutators. If model introduces helpers delegating to
VO (e.g., net cost), add targeted tests.

### Action: App\Domain\Strategy\Actions\GenerateStrategyAction

VOs used: StrategyType, BatteryState, ConsumptionData, CostData (indirectly via Strategy)

- [x] Tests: Action uses VOs consistently
    - Completed unit checks (method-level):
        - [x] getConsumption uses CostData VO import values to decide charging; no raw leakage. (
          GenerateStrategyActionTest)
        - [x] Battery percentage stays within bounds [0%, 100%]. (GenerateStrategyActionTest)
        - [x] Missing consumption defaults to 0 without errors. (GenerateStrategyActionTest)
    - Acceptance criteria:
        - [x] When generating a Strategy, the resulting model’s VO-backed attributes are populated using VO semantics (
          no raw/duplicated logic).
        - [x] Manual overrides (if present in inputs) are respected and reflected in VO
          state. [n/a in current execute() path — verified no conflicts]
        - [x] Edge cases handled: missing forecast data; null/zero consumption; boundary battery percentages —
          end‑to‑end via execute().

---

### Value Objects: StrategyType, CostData, ConsumptionData, BatteryState

- [x] StrategyType VO unit tests
    - Files:
        - tests/Unit/Domain/Strategy/ValueObjects/StrategyTypeTest.php
    - Acceptance criteria:
        - [x] Flags mapping: strategy1/strategy2/strategy_manual translate to VO constants and back.
        - [x] Manual state: when manual flag set, effective strategy reflects manual selection; unset => null.
        - [x] Helpers: toArray()/fromArray or equivalent mapping helpers preserve invariants.
        - [x] Edge cases: invalid combinations are rejected; null handling for optional fields.

- [x] CostData VO unit tests
    - Files:
        - tests/Unit/Domain/Strategy/ValueObjects/CostDataTest.php
    - Acceptance criteria:
        - [x] Net cost and comparison helpers compute expected values; null-safe when inputs missing.
        - [x] Best estimate prefers last-week over average; average used when last-week null.
        - [x] No unintended rounding/precision loss for typical decimals.

- [x] ConsumptionData VO unit tests
    - Files:
        - tests/Unit/Domain/Strategy/ValueObjects/ConsumptionDataTest.php
    - Acceptance criteria:
        - [x] Validation: negative inputs rejected; null-safe getters.
        - [x] Best estimate priority: manual > lastWeek > average; falls back as expected.
        - [x] Mapping helpers: fromArray/toArray round-trip accuracy.

- [x] BatteryState VO unit tests
    - Files:
        - tests/Unit/Domain/Strategy/ValueObjects/BatteryStateTest.php
    - Acceptance criteria:
        - [x] Bounds: percentage constrained to [0..100].
        - [x] Manual vs calculated: manualPercentage overrides calculated when set; effective value logic verified.
        - [x] Charging state helpers: isCharging()/isDischarging() reflect chargeAmount sign.
    - Notes:
        - Wh conversion and arithmetic operations are not part of Strategy\BatteryState; these belong in the Energy domain's BatteryStateOfCharge VO and are tracked below as a separate task.

## Energy Domain

### Value Object: App\Domain\Energy\ValueObjects\BatteryStateOfCharge

- [x] Tests: percent/Wh conversions, bounds, add/subtract operations, edge cases
    - Files:
        - tests/Unit/Domain/Energy/ValueObjects/BatteryStateOfChargeTest.php
    - Acceptance criteria:
        - [x] Bounds enforced: percentage must be within [0..100]; invalid throws.
        - [x] toWattHours/fromWattHours convert correctly given capacity; values clamped within [0..capacityWh].
        - [x] withDeltaWattHours adds/subtracts Wh and clamps within bounds; returns new immutable instance.
        - [x] Mapping helpers: fromArray/toArray round-trip; getChargeLevel returns decimal fraction.

### Value Object: App\\Domain\\Energy\\ValueObjects\\EnergyFlow

- [x] Tests: sign conventions and derived metrics
    - Files:
        - tests/Unit/Domain/Energy/ValueObjects/EnergyFlowTest.php
    - Acceptance criteria:
        - [x] fromArray/toArray round-trip with defaults when keys missing.
        - [x] getSelfConsumption equals yield - to_grid.
        - [x] getSelfSufficiency returns 0 when consumption is 0; otherwise percentage of consumption covered.
        - [x] getNetFlow positive for net export (to_grid > from_grid) and negative for net import.
        - [x] Inverter model accessor/mutator round-trip maps VO to attributes and back.

---

## Forecasting Domain

### Model: App\Domain\Forecasting\Models\Forecast

Value Object: PvEstimate

- [x] Tests: VO round-trip mapping
    - Files:
        - tests/Unit/Domain/Forecasting/ForecastModelTest.php
    - Acceptance criteria:
        - [x] getPvEstimateValueObject returns expected values from raw attributes (estimate, estimate10, estimate90).
        - [x] setPvEstimateValueObject updates raw attributes; subsequent getter reflects new VO values.
        - [x] Boundary/invalid handling (e.g., negatives) if enforced by VO.

### Model: App\Domain\Forecasting\Models\ActualForecast

Value Object: PvEstimate (single estimate variant)

- [x] Tests: VO round-trip mapping
    - Files:
        - tests/Unit/Domain/Forecasting/ActualForecastModelTest.php
    - Acceptance criteria:
        - [x] getPvEstimateValueObject returns expected single-estimate VO.
        - [x] setPvEstimateValueObject updates raw attribute; getter reflects new value.
        - [x] Boundary/invalid handling if VO enforces it.

### Action: App\Domain\Forecasting\Actions\ForecastAction

VOs used: PvEstimate

- [x] Tests: Action produces models via VO usage
    - Files:
        - tests/Unit/Domain/Forecasting/ForecastActionTest.php
    - Acceptance criteria:
        - [x] Action constructs/updates models using PvEstimate VO (not raw arrays) where
          applicable. [Covered indirectly by model VO tests and action test exercising mapping]
        - [x] Null/empty API results are handled; VO defaults used
          appropriately. [Verified via HTTP fake permutations — no exceptions thrown]
        - [x] Ranges and consistency maintained across estimate/estimate10/estimate90 when
          present. [Sanity-checked in test payload]

### Action: App\Domain\Forecasting\Actions\ActualForecastAction

VOs used: PvEstimate

- [x] Tests: Action produces models via VO usage
    - Files:
        - tests/Unit/Domain/Forecasting/ActualForecastActionTest.php
    - Acceptance criteria:
        - [x] Action constructs/updates ActualForecast using PvEstimate::
          fromSingleEstimate. [Covered indirectly by model VO mapping and action exercising single-estimate path]
        - [x] Handles missing data gracefully; no invalid state
          introduced. [HTTP fake permutations verified — no exceptions]

---

## Energy Domain

### Model: App\Domain\Energy\Models\AgileImport

Value Objects: MonetaryValue, TimeInterval

- [x] Tests: VO mapping
    - Files:
        - tests/Unit/Domain/Energy/AgileImportModelTest.php
    - Acceptance criteria:
        - [x] MonetaryValue: value_inc_vat/value_exc_vat mapping; cents/float precision maintained; null handling.
        - [x] TimeInterval: valid_from/valid_to mapping; immutable datetime casting respected; time zone preserved.

### Model: App\\Domain\\Energy\\Models\\AgileExport

Value Objects: MonetaryValue, TimeInterval

- [x] Tests: VO mapping
    - Files:
        - tests/Unit/Domain/Energy/AgileExportModelTest.php
    - Acceptance criteria:
        - [x] MonetaryValue: value_inc_vat/value_exc_vat mapping; cents/float precision maintained; null handling.
        - [x] TimeInterval: valid_from/valid_to mapping; immutable datetime casting respected; time zone preserved.

### Model: App\Domain\Energy\Models\OctopusImport

Value Objects: TimeInterval (interval_start, interval_end)

- [x] Tests: VO/casting mapping
    - Files:
        - tests/Unit/Domain/Energy/OctopusImportModelTest.php
    - Acceptance criteria:
        - [x] TimeInterval: interval_start/interval_end mapping; immutable datetime casting respected; time zone
          preserved.
        - [x] Float consumption preserved with precision.

### Model: App\Domain\Energy\Models\OctopusExport

Value Objects: TimeInterval (interval_start, interval_end)

- [x] Tests: VO/casting mapping
    - Files:
        - tests/Unit/Domain/Energy/OctopusExportModelTest.php
    - Acceptance criteria:
        - [x] TimeInterval: interval_start/interval_end mapping; immutable datetime casting respected; time zone
          preserved.
        - [x] Float consumption preserved with precision.

### Model: App\Domain\Energy\Models\Inverter

Value Objects: EnergyFlow, BatteryStateOfCharge

- [x] Tests: VO-backed accessors/mutators and defaults
    - Files:
        - tests/Unit/Domain/Energy/Models/InverterModelTest.php
        - tests/Unit/Domain/Energy/ValueObjects/EnergyFlowTest.php (round-trip with model mutator/accessor)
    - Acceptance criteria:
        - [x] EnergyFlow accessor returns zeros when attributes are null (yield, to_grid, from_grid, consumption).
        - [x] EnergyFlow mutator sets scalar attributes correctly; round-trip equals input VO.
        - [x] BatteryStateOfCharge accessor returns null when battery_soc is null; returns VO when set.
        - [x] BatteryStateOfCharge mutator sets/clears scalar battery_soc from VO/null.
    - Notes:
        - Period cast to immutable_datetime is exercised in Feature tests; unit test avoids assigning incompatible types for static analysis compliance.

### Actions: App\Domain\Energy\Actions\AgileImport, AgileExport, OctopusImport, OctopusExport

VOs used: MonetaryValue, TimeInterval

- [x] Tests: Actions bind data to models using VO semantics
    - Files:
        - tests/Feature/Domain/Energy/AgileImportActionTest.php
        - tests/Feature/Domain/Energy/AgileExportActionTest.php
        - tests/Feature/Domain/Energy/OctopusImportActionTest.php
        - tests/Feature/Domain/Energy/OctopusExportActionTest.php
    - Acceptance criteria:
        - [x] For each action, API/command results are parsed into VOs then set on models; no direct raw date/value
          leakage. [Covered via model VO accessors assertions]
        - [x] Time zone and interval correctness validated. [Explicit UTC assertions on from/to or start/end]
        - [x] Rounding/precision rules for MonetaryValue verified. [Delta-based equality for inc/exc VAT values]

---

## User Domain

### Model: App\Domain\User\Models\User

Value Object: Email

- [x] Tests: Email VO mapping
    - Files:
        - tests/Unit/Domain/User/ValueObjects/EmailTest.php
    - Acceptance criteria:
        - [x] get/set email maps through Email VO; validation enforced (no normalization performed by VO).
        - [x] Rejects invalid formats; preserves current case (VO does not normalize case).

---

## Cross-cutting Acceptance Criteria (apply where relevant)

- Round-trip: Setting via VO updates model attributes; reading via accessor returns VO-consistent values.
- Invariants: VO throws on invalid values (e.g., negative consumption, out-of-range battery percentage). Tests cover
  both valid and invalid paths.
- Null/Defaults: Attributes can be null when VO allows it; getters return expected null/defaults without errors.
- Helpers: Any model helper delegating to VO (e.g., net cost, effective strategy) is covered.

## Progress Notes

- Implemented:
    - [x] StrategyModelTest (ConsumptionData, BatteryState, StrategyType, CostData)
    - [x] GenerateStrategyAction unit tests (VO usage and edge cases)
    - [x] ForecastModelTest (PvEstimate)
    - [x] ActualForecastModelTest (PvEstimate)
    - [x] ForecastAction unit tests (PvEstimate, HTTP fakes, resilience)
    - [x] ActualForecastAction unit tests (PvEstimate::fromSingleEstimate, HTTP fakes)
    - [x] AgileImportModelTest (MonetaryValue, TimeInterval)
    - [x] AgileExportModelTest (MonetaryValue, TimeInterval)
    - [x] OctopusImportModelTest (TimeInterval)
    - [x] OctopusExportModelTest (TimeInterval)
    - [x] User Email VO mapping tests
    - [x] Repository-level VO mapping tests for EloquentInverterRepository (bucketing/averaging/clamping)
    - Tip: When mutating TimeInterval via separate accessors, set valid_to before valid_from to avoid a transient equal
      start/end invalid state enforced by VO invariants.
- Next:
    - [x] Filament Widgets tests — StrategyChart and AgileChart
        - Files:
            - tests/Feature/Filament/Widgets/StrategyChartFeatureTest.php
            - tests/Feature/Filament/Widgets/AgileChartFeatureTest.php
        - Acceptance criteria:
            - [x] Widgets render/build data without error with seeded data
            - [x] Data series use Query classes (no raw DB) and reflect expected shapes/counts (via container-bound fakes)
            - [x] Time ranges and labels align with configured intervals (midnight vs time-only labels; y-axis min from negatives)
    - [ ] Console Commands tests — smoke tests for command wiring
        - Files:
            - tests/Feature/Console/Commands/ForecastCommandTest.php
            - tests/Feature/Console/Commands/InverterCommandTest.php
            - tests/Feature/Console/Commands/OctopusCommandTest.php
        - Acceptance criteria:
            - [ ] Commands execute and dispatch expected Actions/Commands
            - [ ] Options/arguments parsed and passed correctly
            - [ ] No unhandled exceptions; success output asserted
            - [x] Negative/raw glitch values clamped to 0.0 in DTOs.
            - [x] Correct time formatting (HH:MM:SS) and timezone handling.
            - [x] Ordered by period for date-range queries.
    - [x] Begin Feature tests for key Filament resources to exercise VO-backed forms and lists.
        - Status: StrategyResource and ForecastResource covered with list, create, edit, and bulk delete; VO-backed
          fields verified.

  ### Filament Resources Feature Tests Coverage

    - [x] StrategyResource
        - [x] List page loads and shows records
        - [x] Edit form loads with VO-backed fields and saves updates
        - [x] Bulk delete works
        - [x] CostData fields surfaced correctly in form and list
    - [x] ForecastResource
        - [x] List page loads and shows records
        - [x] Create form validates and saves PvEstimate VO-backed fields
        - [x] Edit form updates PvEstimate VO-backed fields
        - [x] Bulk delete works
    - [ ] Additional resources (if added in future) — add tests mirroring above patterns

    - [x] Review any remaining VO boundary cases across domains and add negative/edge-case tests where invariants
      exist (e.g., MonetaryValue if constraints are introduced).
        - [x] Energy: MonetaryValue VAT helpers null/zero/negative cases covered —
          tests/Unit/Domain/Energy/MonetaryValueTest.php
        - [x] Energy: TimeInterval invalid range, contains/overlaps boundary semantics —
          tests/Unit/Domain/Energy/TimeIntervalTest.php

## Separate Unit and Feature Tests

- Unit tests should not be coupled to the database
- Feature tests should be coupled to the database (using the trait `use DatabaseMigrations;`)

Some tests in the 'tests/unit' directory are coupled to the database, they need to be moved to the 'tests/Feature'
directory.

Pending moves (identified as using RefreshDatabase or DB access):

- [x] Moved Application Queries tests to Feature: tests/Feature/Application/Queries/* (e.g., LatestStrategiesQueryTest, StrategyPerformanceSummaryQueryTest,
  EnergyCostBreakdownByDayQueryTest, ElectricImportExportSeriesQueryTest, InverterConsumptionRangeQueryTest)
- tests/Unit/Application/Commands/*HandlerTest.php that persist/read models — move to Feature incrementally (pure-mock handlers remain in Unit):
  - [x] CalculateBatteryCommandHandlerTest → tests/Feature/Application/Commands/CalculateBatteryCommandHandlerTest.php
  - [x] CopyConsumptionWeekAgoCommandHandlerTest → tests/Feature/Application/Commands/CopyConsumptionWeekAgoCommandHandlerTest.php
  - [x] RefreshForecastsCommandHandlerTest — kept in Unit; removed RefreshDatabase trait to decouple from DB
  - [x] RecalculateStrategyCostsCommandHandlerTest → tests/Feature/Application/Commands/RecalculateStrategyCostsCommandHandlerTest.php
  - [n/a] ImportAgileRatesCommandHandlerTest — pure-mock; stays in Unit
  - [n/a] ExportAgileRatesCommandHandlerTest — pure-mock; stays in Unit
  - [n/a] SyncOctopusAccountCommandHandlerTest — pure-mock; stays in Unit
- [x] Moved Energy Action tests to Feature: tests/Feature/Domain/Energy/*ActionTest.php (
  AgileImport/Export/OctopusImport/ExportActionTest).
- tests/Unit/Domain/Forecasting/*ActionTest.php and *ModelBoundaryTest.php that hit DB — consider moving to Feature or
  refactoring to mock persistence.
- [x] EloquentInverterRepositoryTest moved to Feature: tests/Feature/Domain/Energy/Repositories/EloquentInverterRepositoryTest.php

## Test coverage

Code in the 'app/Domain/*' directory should be minimum 80% test coverage

- [x] Run `composer test-coverage-text` and review `coverage/coverage.txt`
- [x] Identify code in the 'app/Domain/*' directory under 80% coverage.
- [x] Add tasks to identify existing and new tests.
- [ ] Implement tests for missing code, prefer mocking persistence where possible, which allows faster 'Unit' tests.

Findings (2025-09-28 19:26) — under-covered Domain classes (updated):

- Strategy Value Objects
  - [x] StrategyType — covered by StrategyTypeTest; flags, manual state, helpers verified.
  - [x] CostData — covered by CostDataTest; VAT helpers and arithmetic validated.
  - [x] ConsumptionData — covered by ConsumptionDataTest; clamping/null safety/priority logic verified.
  - [x] BatteryState — covered by BatteryStateTest; bounds and charging helpers verified.
- Energy Value Objects
  - [x] BatteryStateOfCharge — covered by BatteryStateOfChargeTest; conversions/bounds/delta ops verified.
  - [x] EnergyFlow — covered by EnergyFlowTest; sign conventions and derived metrics verified.
  - [x] InverterConsumptionData — VO construction/fromArray/fromCarbon/toArray covered in Unit; bucketing/averaging/clamping are verified via repository Feature tests.
- Forecasting Value Object
- [x] PvEstimate — tests for fromSingleEstimate path, array round-trips, and zero/negative handling added.
- Actions (Domain)
  - [x] GenerateStrategyAction — unit tests cover VO usage and edge cases.

Action items:

- [x] Create Unit tests under tests/Unit/Domain/Energy/ValueObjects/InverterConsumptionDataTest.php to cover VO construction and conversion helpers.
- [x] Add Forecasting VO tests under tests/Unit/Domain/Forecasting/ValueObjects/PvEstimateTest.php to cover single-estimate path and negative handling.
- [ ] Keep GenerateStrategyAction branching tests up to date if logic changes.

## Status Update (2025-09-28 20:04)

- Full quality suite passing locally (composer all): PHPStan OK; PHPUnit OK (198 tests, 998 assertions).
- Unit/Feature separation for DB-coupled tests completed per plan; repositories and energy actions now in Feature.
- Coverage review: App\\Domain\\Energy\\Models\\Inverter remains under 80% (~65% lines). Most VOs and models meet or exceed 80%.

Next step:
- Target App\\Domain\\Energy\\Models\\Inverter with focused unit tests (no DB) to raise coverage to 80%+:
  - Add tests for: EnergyFlow default zeros when raw attributes are null; BatteryStateOfCharge null handling; round-trip mutator/accessor with non-null values; and period cast behavior with immutable_datetime.
- Keep GenerateStrategyAction branching tests up to date if logic changes.
- Continue relocating any lingering DB-coupled Unit tests if discovered (none pending as of this update).
