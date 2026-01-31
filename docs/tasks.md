# Solar Project Improvement Tasks

Last updated: 2025-11-19 22:01

This document provides a comprehensive checklist of improvement tasks for the Solar project. Each task is marked with a
checkbox [ ] that can be checked off when completed.

Note on prioritisation: Tasks are grouped and ordered to align with the phased approach in docs/plan.md (Phase 1:
Foundation and Security, Phase 2: Performance and Data Management, Phase 3: User Experience and Documentation). When
updating progress, ensure work proceeds broadly in this order unless justified otherwise.

## 1. Code Organisation and Architecture

[x] **Implement Domain-Driven Design (DDD) principles**

- [x] Reorganize code into domain-specific modules
    - [x] Forecasting
    - [x] Strategy
    - [x] Energy
    - [x] Equipment
    - [x] User
- [x] Create clear boundaries between different domains (e.g. Forecasting, Strategy, Energy Import/Export)
- [ ] Define value objects for domain concepts
    - [x]  app/Domain/Strategy/ValueObjects/StrategyType.php
    - [x] Update `Strategy` model to use `StrategyType` value object for `strategy1`, `strategy2`, and `strategy_manual`
      properties
    - [x] Add accessor and mutator methods in the `Strategy` model to convert between raw database values and the value
      object
    - [x] Update `GenerateStrategyAction` to use the `StrategyType` value object
    - [x] Update Filament forms in `StrategyResource` to work with the value object
    - [x]  app/Domain/Strategy/ValueObjects/CostData.php
    - [x] Update `Strategy` model to use `CostData` value object for cost-related properties
    - [x] Add accessor and mutator methods in the `Strategy` model to convert between raw database values and the value
      object
    - [x] Update actions that calculate or use cost data to work with the value object
    - [x] Update Filament forms in `StrategyResource` to work with the value object
    - [x]  app/Domain/Strategy/ValueObjects/ConsumptionData.php
    - [x] Update `Strategy` model to use `ConsumptionData` value object for consumption-related properties
    - [x] Add accessor and mutator methods in the `Strategy` model to convert between raw database values and the value
      object
    - [x] Update `CopyConsumptionWeekAgoAction` to use the `ConsumptionData` value object
    - [x] Update Filament forms in `StrategyResource` to work with the value object
    - [x]  app/Domain/Strategy/ValueObjects/BatteryState.php
    - [x] Update `Strategy` model to use `BatteryState` value object for battery-related properties
    - [x] Add accessor and mutator methods in the `Strategy` model to convert between raw database values and the value
      object
    - [x] Update `CalculateBatteryAction` to use the `BatteryState` value object
    - [x] Update Filament forms in `StrategyResource` to work with the value object
    - [x]  app/Domain/Forecasting/ValueObjects/PvEstimate.php
    - [x] Update `Forecast` and `ActualForecast` models to use `PvEstimate` value object for PV estimate properties
    - [x] Add accessor and mutator methods in both models to convert between raw database values and the value object
    - [x] Update `ForecastAction` and `ActualForecastAction` to use the `PvEstimate` value object
    - [x] Update Filament forms in `ForecastResource` to work with the value object
    - [x]  app/Domain/Energy/ValueObjects/MonetaryValue.php
    - [x] Update `AgileImport` and `AgileExport` models to use `MonetaryValue` value object for value properties
    - [x] Add accessor and mutator methods in both models to convert between raw database values and the value object
    - [x] Update energy import/export actions to use the `MonetaryValue` value object
    - [x] Update any Filament forms that display or edit monetary values
    - [x]  app/Domain/Energy/ValueObjects/TimeInterval.php
    - [x] Update `AgileImport` and `AgileExport` models to use `TimeInterval` value object for `valid_from` and
      `valid_to` properties
    - [x] Add accessor and mutator methods in both models to convert between raw database values and the value object
    - [x] Update energy import/export actions to use the `TimeInterval` value object
    - [x] Update any Filament forms that display or edit time intervals
    - [x]  app/Domain/User/ValueObjects/Email.php
    - [x] Update `User` model to use `Email` value object for email-related properties
    - [x] Add accessor and mutator methods in the `User` model to convert between raw database values and the value
      object
    - [x] Update authentication and user management code to use the `Email` value object
    - [x] Update any Filament forms that display or edit email addresses

[x] **Refactor Actions for consistency**

- [x] Standardise input/output formats across all Actions
- [x] Implement consistent error handling in all Actions
- [x] Add proper validation for all Action inputs

[x] **Improve dependency injection**

- [x] Review service container bindings
- [x] Reduce direct instantiation of classes in favour of dependency injection
- [x] Create interfaces for key services to improve testability

[x] **Implement CQRS pattern for complex operations — Completed**

- [x] Phase 1 CQRS introduced Commands for complex writes and Queries for reads.
- [x] Filament actions are updated to dispatch commands via CommandBus.
- [x] Widgets/Charts updated to use dedicated Queries.
- [x] Comprehensive tests and developer docs are added.

Details and the full checklist have been moved to docs/cqrs-tasks.md.

## 1.1 User requirements

Tasks based on the [User Requirements Document](./user-requests.md).

### 1.1.1 Forecasting — Solcast API Allowance

- [x] Implement a unified Solcast API allowance policy (daily cap, per-endpoint min intervals, global backoff, DB row
  lock) — see docs/solcast-api-allowance-task.md

### 1.1.2 Bug: Correct Solcast forecasts charts: Solis should be corrected to Solcast.

- [x] Investigate all charts that display 'Forecast' and 'ActualForecast' model data. Update any Solis reference to
  Solcast.

### 1.1.3 Make the Agile cost chart interactive

- [x] Dashboard — Make the Agile cost chart interactive, see `docs/make-the-agile-cost-chart-interactive.md` for the
  full story.

### 1.1.3.1 Make the cost chart interactive

Once the dashboard chart is interactive, also update the similar chart in strategy

- [x] Strategy — Make the cost chart interactive, the strategy cost chart is almost identical to the Agile cost chart.
  Note: a future requirement is to make it interactive from 7PM, so it needs to be a different chart.

### 1.1.4 Monthly large dataset performance checks

- [x] Add monthly large dataset performance checks — see `docs/monthly-large-dataset-performance-checks.md` for the full
  checklist

### 1.1.5 Fix CI build (tests and code-quality)

- [x] The CI build for tests is failing — see `docs/ci-build-fixes-task-list.md`
- [x] The CI code-quality check is not consistent — see `docs/ci-build-fixes-task-list.md`

### 1.1.6 Improve the strategy charts and widgets

- [x] The forecast charts currently display the current day, it should be extended to display from 4pm
  the previous day to 4pm (16:00) GMT the current day
- [x] Change the drop-down to display the period start and end times.

### 1.1.7 Update the strategy generator

- [x] The strategy generator needs to align with the charts, which now display from 4pm the previous day to 4pm (16:00)
  GMT the current day

### 1.1.8 Consolidate strategy generator

- [x] The strategy generator can include the consumption from last week.

### 1.1.9 Monthly large dataset performance checks Jan 2026

- [x] Add monthly large dataset performance checks — see `docs/monthly-large-dataset-performance-checks.md` for the full
  checklist

### 1.1.10 The battery cost is sometimes negative when full

- [x] Fix `CalculateBatteryPercentage` calculate method

### 1.1.11 Update the strategy filter

- [x] Update the strategy filter to display the correct strategy for the current or next day, 4pm to 4pm period.

### 1.1.12 Battery start percentage

- [x] the battery start percentage should be 15:30 end battery percentage, the 16:00 end battery percentage will then be
  correctly calculated.

### 1.1.13 Improve the strategy algorithm

- [x] Detailed TDD tasks: [improve-the-strategy-algorithm.md](improve-the-strategy-algorithm.md)

### 1.1.14 Helper or API

- [x] Solis has an API, see [Solis API](user-requests.md#solis-api). API access has been requested and granted.
- [ ] Create a command which will call the Solis API to update the charge start and end times for each period.

## 1.1.15 Solis API download inverter list PoC

- [x] Create a command which will call the Solis API to get the inverter list. See `docs/solis-api-poc.md` for more
  details.

### 1.1.16 Install Laravel Boost

- [x] Confirm installation instructions from `docs/boost.md`
- [x] `composer require laravel/boost --dev`
- [x] `php artisan boost:install`
- [x] Add MCP config file for AI Pro
- [x] Update composer to automatically update boost on update
- [x] Update README.md and guidelines.md and/or .env.example if a new config is needed

## 2. Testing and Quality Assurance

[ ] Foundation and Security (Phase 1 alignment)

- Ensure security and initial QA items are prioritised per `docs/plan.md`.

[ ] **Increase test coverage**

- [x] Add unit tests for all Models
    - [x] Strategy domain: Strategy model VO mapping (ConsumptionData, BatteryState, StrategyType, CostData)
    - [x] Forecasting domain: Forecast model VO mapping (PvEstimate)
    - [x] Forecasting domain: ActualForecast model VO mapping (PvEstimate)
    - [x] Energy domain: AgileImport model VO mapping (MonetaryValue, TimeInterval)
    - [x] Energy domain: AgileExport model VO mapping (MonetaryValue, TimeInterval)
    - [x] Energy domain: OctopusImport model VO mapping (TimeInterval)
    - [x] Energy domain: OctopusExport model VO mapping (TimeInterval)
    - [x] Energy domain: Inverter model VO mapping (EnergyFlow, BatteryStateOfCharge)
    - [x] User domain: User model VO mapping (Email)
- [x] Add unit tests for all Actions
    - [x] Strategy: GenerateStrategyAction uses VOs consistently
    - [x] Forecasting: ForecastAction uses PvEstimate VO
    - [x] Forecasting: ActualForecastAction uses PvEstimate VO
    - [x] Energy: AgileImport, AgileExport, OctopusImport, OctopusExport actions use MonetaryValue/TimeInterval VOs
- [x] Add unit tests for Repositories
    - [x] Energy: EloquentInverterRepository returns InverterConsumptionData with correct averages, clamping, and time
      periods

Note: Progress — repository-level VO mapping tests are added for EloquentInverterRepository. VO edge-case unit tests
added for Energy VOs (MonetaryValue VAT helpers; TimeInterval invalid ranges/overlaps/contains). Feature tests for key
Filament resources (StrategyResource, ForecastResource) implemented exercising VO-backed forms and lists. Unit-testing
milestone for VO mapping and core Actions is now complete and passing `composer all`. Next step — proceed with
integration tests for critical flows and plan E2E coverage.

- [x] Relocated DB-coupled Energy Action tests from Unit to Feature (tests/Feature/Domain/Energy/*ActionTest.php)
- [x] Relocated DB-coupled Application Queries tests from Unit to Feature (tests/Feature/Application/Queries/*)
- [x] Review CommandHandler tests and relocate only DB-coupled ones to Feature
    - [x] CalculateBatteryCommandHandlerTest moved to tests/Feature/Application/Commands/
    - [x] CopyConsumptionWeekAgoCommandHandlerTest moved to tests/Feature/Application/Commands/
    - [x] RefreshForecastsCommandHandlerTest remains in Unit (mock-only, no DB coupling)
    - [x] RecalculateStrategyCostsCommandHandlerTest moved to tests/Feature/Application/Commands/
    - [x] ImportAgileRatesCommandHandlerTest remains in Unit (mock-only, no DB coupling)
    - [x] ExportAgileRatesCommandHandlerTest remains in Unit (mock-only, no DB coupling)
    - [x] SyncOctopusAccountCommandHandlerTest remains in Unit (mock-only, no DB coupling)
- [ ] Add feature tests for all Filament resources
    - [x] StrategyResource: list/edit/delete and VO-backed form fields
    - [x] ForecastResource: list/create/edit/delete and PvEstimate VO in form/table
    - [ ] Additional resources (add tests when new resources are introduced)
- [ ] Add integration tests for critical user flows
    - [ ] Strategy generation end-to-end: from command dispatch to persisted Strategy with correct VO states
    - [ ] Energy import/export cost calculation daily summary end-to-end

- [ ] Coverage improvement (Domain min 80%) — identified under-covered classes
    - Strategy Value Objects
        - [x] StrategyType — add unit tests for flags mapping, manual state, effective strategy helpers, edge-cases
        - [x] CostData — add tests for VAT-inclusive/exclusive helpers, arithmetic helpers, zero/negative handling
        - [x] ConsumptionData — add tests for clamping negatives, null safety, derived totals where applicable
        - [x] BatteryState — add tests for bounds [0..100], manual vs calculated percentage, charge/discharge helpers
    - Energy Value Objects
        - [x] BatteryStateOfCharge — add tests for percentage/Wh conversions, bounds, add/subtract operations
        - [x] EnergyFlow — add tests for import/export sign conventions, derived metrics, and zero-consumption case
        - [x] InverterConsumptionData — unit tests added for VO helpers; repository Feature tests cover
          bucketing/averaging/clamping
    - Forecasting Value Object
    - [x] PvEstimate — unit tests added for fromSingleEstimate path, array round-trips, and zero/negative handling
    - Actions (Domain)
        - [x] GenerateStrategyAction — add unit tests to hit branching logic without DB
    - Filament Widgets
        - [x] StrategyResource\Widgets\StrategyChart — add feature tests for dataset building and label ranges
        - [x] Filament\Widgets\AgileChart — add feature tests for series construction and time windows
        - [x] StrategyResource\\Widgets\\ElectricImportExportChart — add feature test ensuring render with faked
          ElectricImportExportSeriesQuery
        - [x] StrategyResource\\Widgets\\StrategyOverview — add feature test ensuring render with faked
          StrategyPerformanceSummaryQuery
        - [x] ForecastResource\\Widgets\\ForecastChartWidget — add feature test ensuring render with seeded Forecasts
        - [x] Filament\\Widgets\\InverterChart — add feature test ensuring render with faked
          InverterConsumptionRangeQuery
        - [x] Filament\\Widgets\\InverterAverageConsumptionChart — add feature test ensuring render with faked
          InverterConsumptionByTimeQuery
        - [x] Filament\\Widgets\\OctopusImportChart — add feature test ensuring render with faked OctopusImportAction
          and seeded rates/imports
        - [x] Filament\\Widgets\\SolcastActualChart — add feature test ensuring render; triggers update when stale and
          not when fresh
        - [x] Filament\\Widgets\\SolcastWithActualAndRealChart — add feature test ensuring merged datasets with faked
          actions
        - [x] Filament\\Widgets\\ForecastChart — add feature test asserting labels from minimal seeded forecasts or
          faked source
        - [x] Filament\\Widgets\\OctopusChart — add base chart behaviour tests with faked actions

  Next step: Filament widget coverage complete. Begin performance testing as outlined in docs/performance-testing.md.
  After establishing baselines and CI smoke checks, proceed to integration tests per this document.
    - Console Commands
        - [x] Forecast, Inverter, Octopus console commands — add smoke tests asserting dispatch and options parsing

[x] **Implement automated code quality tools**

- [x] Set up PHP_CodeSniffer for code style enforcement
- [x] Configure PHPStan for static analysis
- [x] Add Larastan for Laravel-specific static analysis
- [x] Set up GitHub Actions for CI/CD

[x] **Add performance testing**

See `docs/performance-testing.md` for full details and scripts

Next step (updated 2025‑11‑19 21:43): Entered Maintenance cadence. Medium/Large runs are not required today. On relevant
changes, re‑seed Medium and run perf suite; refresh baselines only when two consecutive runs are within tolerance and
record justification. Monthly Large advisory remains local/docs‑only on the first Monday (UTC). CI PR smoke remains
informational‑only. Quality suite (`composer all`) green as of 21:43.

- Status: Baselines are unchanged; no remediation is open. DB index verification completed based on recent profiling
  (no new indexes required).
- [x] Medium re‑seed and local k6 runs completed for Dashboard, Forecasts, Inverter, Strategies, and
  Strategy‑Generation (VUS=5, 30s)
- [x] Committed new `tests/Performance/baselines/dashboard.medium.baseline.json`
- [x] Updated `tests/Performance/baselines/strategies.medium.baseline.json` (improved p.95; error rate 0%)
- [x] Updated `tests/Performance/baselines/inverter.medium.baseline.json` (p.95 stable within tolerance across two runs;
  error rate 0%)
- [x] Validated `forecasts.medium.baseline.json` and `strategy-generation.medium.baseline.json` against Tolerance Policy
  via two Medium runs; both within tolerance and left unchanged by policy (0% error rate)
- [x] Perf maintenance SOP established — Champion: Michael Pritchard; cadence: Medium re‑baseline on relevant merges
  (24–48h) and monthly Large advisory (local, docs‑only).

- [x] Benchmark database queries
- [x] Test application under load
    - CI k6 smoke test in place (non-blocking) with PR comment summaries — see `.github/workflows/performance.yml`
    - Local scenarios documented in `tests/Performance/README.md`
- [x] Create remediation tasks for top 3 bottlenecks (forecasts, inverter, strategies) — tracked below and in
  `docs/performance-testing.md`
- [ ] Identify and fix performance bottlenecks
    - [ ] Forecasts scenario — profile queries; eliminate N+1 in chart/summary queries; add/select indices on time/user
      foreign keys; consider caching hot aggregates
        - [x] Task: Enable DB query logging per `docs/perf-profiling.md` and capture slow queries
        - [x] Task: Add/verify indexes on `forecasts(valid_from)`, `forecasts(user_id)` (schema uses
          `forecasts.period_end` UNIQUE; no `user_id`/`valid_from` columns — no additional indexes needed)
        - [x] Task: Add eager loading in Forecast widgets/queries to remove N+1 (reviewed: no N+1 observed; no change
          needed)
        - [x] Task: Consider caching aggregated series for hot windows (e.g. last 24h) (assessed; feature cache behind
          flags, off by default)
        - [ ] Task: Medium p95 regression follow‑up — re‑profile with query logging, verify no local interference
          (browser tabs, background load), re‑run Medium once; if still high, file targeted remediation (e.g.
          pre‑aggregations)
    - [ ] Inverter scenario — profile widget/JSON endpoints; eager-load relations; verify indices on timestamps and
      device IDs; consider downsampling for charts
        - [x] Task: Profile JSON endpoints; record SQL count and worst queries
        - [x] Task: Verify/ add indexes on `inverters(timestamp)`, `inverters(device_id)` (schema uses
          `inverters.period` UNIQUE; no `device_id` column — no additional indexes needed)
        - [x] Task: Eager-load related device/metrics where applicable (reviewed: no N+1 observed on current paths)
        - [x] Task: Evaluate downsampling for long-range charts (toggles added; measured impact locally)
        - [x] Task: Medium p95 regression follow‑up — re‑profile inverter widget endpoints; confirm downsampling toggles
          remain OFF; re‑run Medium; consider tightening select columns if needed (stable across two runs; baseline
          updated)
    - [x] Strategies scenario — profile index and edit views; add eager loading for related models; index frequently
      filtered columns; cache computed summaries (Validation was completed on 2025‑11‑18; no N+1 or slow queries >
      100 ms observed; baselines unchanged)
        - [x] Task: Profile index/edit pages including related XHR calls
        - [x] Task: Add eager loading for related models (user, forecasts, costs) to avoid N+1 (reviewed: no N+1
          observed on current queries)
        - [x] Task: Add/verify indexes on frequently filtered columns (schema uses `strategies.period` UNIQUE; no
          additional filters identified during profiling)
        - [x] Task: Cache computed summaries where safe (implemented behind `FEATURE_CACHE_STRAT_SUMMARY`)
    - [ ] Strategy generation scenario — remediate errors under load (k6)
        - [x] Task: Validate/toggle Livewire path (`STRAT_GEN_LIVEWIRE=true`) and set `LIVEWIRE_ENDPOINT`/
          `LIVEWIRE_PAYLOAD_BASE64` accordingly (Livewire optional; prefer local helper `/_perf/generate-strategy` for
          perf runs)
        - [x] Task: Ensure CSRF extraction/submission in k6 helper for generation POST is correct (not required for
          a local helper path; a Livewire path remains documented and optional)
        - [x] Task: Add small backoff or single-flight behaviour in the k6 scenario to avoid concurrent duplicate
          generation requests
        - [x] Task: Review server-side guards/rate limiting for generation; document expected behaviour under concurrent
          requests (local-only route; CSRF disabled; no rate-limit middleware; documented in perf plan)
        - [x] Task: Medium p95 variance follow‑up — profile strategy verification/index endpoints and generation POST
          path; confirm single‑flight behaviour; re‑run two consecutive Medium runs; evaluate minor optimisations if
          the persistence layer shows hotspots

## 3. Performance Optimisation

Phase alignment: Phase 2 — Performance and Data Management (see docs/plan.md)

[x] DB index verification is completed based on profiling; no new indexes are required at this time.

[ ] **Optimize database queries**

- [ ] Review and optimize Eloquent queries
- [ ] Add appropriate indexes to database tables
- [ ] Implement query caching where appropriate

[ ] **Implement caching strategy**

- [ ] Cache frequently accessed data
- [ ] Use Redis for cache storage
- [ ] Add Redis to Laravel sail
- [ ] Implement cache invalidation strategy

[ ] **Low‑risk, feature‑flagged optimizations (from performance-testing Findings)**

- [x] Implement `FEATURE_CACHE_FORECAST_CHART` with `FORECAST_CHART_TTL` (default 60s); cache prepared Chart.js dataset
  in `ForecastChart` widget
- [x] Implement `FEATURE_CACHE_STRAT_SUMMARY` with `STRAT_SUMMARY_TTL` (default 10 m); cache
  `StrategyPerformanceSummaryQuery` by day range
- [x] Add optional downsampling toggles for chart endpoints: `FORECAST_DOWNSAMPLE`, `FORECAST_BUCKET_MINUTES`,
  `INVERTER_DOWNSAMPLE`, `INVERTER_BUCKET_MINUTES`
- [x] Re‑baseline Medium after enabling above flags locally; keep Large advisory only

[ ] **k6 scenario stability**

- [x] De-flak forecasts scenario warmup: add a small retry / backoff for “dashboard reachable (200)” or soften to
  informational during warmup; ensure auth bootstrap timing is robust

[ ] **Optimise front-end assets**

- [ ] Minify and bundle JavaScript and CSS
- [ ] Optimize image loading
- [ ] Implement lazy loading for components

[ ] **Implement queue system for background processing**

- [ ] Move time-consuming operations to queued jobs
- [ ] Configure queue workers and supervisors
- [ ] Add monitoring for queue health

## 4. Security Enhancements

Phase alignment: Phase 1 — Foundation and Security (see docs/plan.md)

[ ] **Conduct security audit**

- [ ] Review authentication and authorisation mechanisms
- [ ] Check for CSRF, XSS, and SQL injection vulnerabilities
- [ ] Verify proper input validation throughout the application

[ ] **Implement API security best practices**

- [ ] Use API tokens or OAuth for authentication
- [ ] Rate limit API endpoints
- [ ] Implement proper CORS configuration

[ ] **Enhance data protection**

- [ ] Encrypt sensitive data at rest
- [ ] Ensure HTTPS is enforced
- [ ] Add self-signed certificates for local development, using laravel sail
- [ ] Implement proper data backup strategy

[ ] **Add security headers**

- [ ] Configure Content Security Policy (CSP)
- [ ] Add X-XSS-Protection, X-Content-Type-Options headers
- [ ] Implement Subresource Integrity (SRI) for external resources

## 5. User Experience Improvements

Phase alignment: Phase 3 — User Experience and Documentation (see docs/plan.md)

[ ] **Enhance Filament admin interface**

- [ ] Create custom dashboard widgets for key metrics
- [ ] Improve form validation feedback
- [ ] Add bulk actions for common operations

[ ] **Add user onboarding flow**

- [ ] Create a guided tour for new users
- [ ] Add contextual help throughout the interface
- [ ] Develop documentation for common tasks

[ ] **Implement notifications system**

- [ ] Add in-app notifications for important events

## 6. Documentation

Phase alignment: Phase 3 — User Experience and Documentation (see docs/plan.md)

[ ] **Improve code documentation**

- [ ] Document complex algorithms and business logic
- [ ] Create architecture diagrams

[ ] **Create user documentation**

- [ ] Write user guides for common tasks
- [ ] Add an FAQ section

[ ] **Document development processes**

- [x] Create local setup guidelines (expand on an existing guide in README.md)
- [x] Add a troubleshooting guide

## 7. Data Management

Phase alignment: Phase 2 — Performance and Data Management (see docs/plan.md)

[ ] **Implement data validation and sanitization**

- [ ] Add validation rules for all user inputs
- [ ] Sanitize data before storage
- [ ] Implement data integrity checks

[ ] **Improve data import/export functionality**

- [ ] Add support for more file formats
- [ ] Implement progress tracking for large imports
- [ ] Add validation for imported data

[ ] **Implement data archiving strategy**

- [ ] Create a policy for archiving old data
- [ ] Implement an automated archiving process
- [ ] Provide an interface for accessing archived data

[ ] **Add data visualization tools**

- [ ] Implement admin dashboard for data insights
- [ ] Add export options for reports
- [ ] Create custom reports for specific use cases

---

Completion criteria: Before checking any task as complete, ensure PHPUnit tests pass, PHPStan static analysis has no new
issues, and PHP_CodeSniffer reports compliance (see .junie/guidelines.md). Update this tasks list and reference
docs/plan.md for phase alignment when planning next steps.


