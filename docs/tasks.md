# Solar Project Improvement Tasks

Last updated: 2025-09-06 21:31

This document provides a comprehensive checklist of improvement tasks for the Solar project. Each task is marked with a
checkbox [ ] that can be checked off when completed.

Note on prioritization: Tasks are grouped and ordered to align with the phased approach in docs/plan.md (Phase 1:
Foundation and Security, Phase 2: Performance and Data Management, Phase 3: User Experience and Documentation). When
updating progress, ensure work proceeds broadly in this order unless justified otherwise.

## 1. Code Organization and Architecture

[x] **Implement Domain-Driven Design (DDD) principles**

- [x] Reorganize code into domain-specific modules
    - [x] Forecasting
    - [x] Strategy
    - [x] Energy
    - [x] Equipment
    - [x] User
- [x] Create clear boundaries between different domains (e.g., Forecasting, Strategy, Energy Import/Export)
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

- [x] Standardize input/output formats across all Actions
- [x] Implement consistent error handling in all Actions
- [x] Add proper validation for all Action inputs

[x] **Improve dependency injection**

- [x] Review service container bindings
- [x] Reduce direct instantiation of classes in favor of dependency injection
- [x] Create interfaces for key services to improve testability

[x] **Implement CQRS pattern for complex operations (Phase 1)**

- [ ] 
    1. Establish naming and directories

    - [x] Create app/Application/Commands and app/Application/Queries namespaces
    - [x] Define contracts: Command (marker) and CommandHandler interface
    - [x] Define a simple CommandBus interface and a synchronous implementation
- [ ] 
    2. Introduce a first command for an existing complex operation

    - [x] Create GenerateStrategyCommand with required inputs
    - [x] Create GenerateStrategyCommandHandler delegating to current GenerateStrategyAction and returning ActionResult
    - [x] Add validation and DB transaction handling in the handler
    - [x] Add domain events/logging from handler (optional)
- [ ] 
    3. Update a Filament Action to use the command

    - [x] Modify StrategyResource GenerateAction to dispatch command via bus
    - [x] Keep UI messaging based on ActionResult
- [ ] 
    4. Carve out read-side queries for complex listings

    - [x] Create Query class: AgileImportExportSeriesQuery returning optimized array/collection
    - [x] Update AgileChart widget to use the Query class
- [ ] 
    5. Migrate additional domain actions to commands (planned)

    - [x] Wrap AgileImport as ImportAgileRatesCommand(+Handler)
    - [x] Wrap AgileExport as ExportAgileRatesCommand(+Handler)
    - [x] Wrap Account as SyncOctopusAccountCommand(+Handler)
- [ ] 
    6. Introduce testing around commands and queries

    - [x] Unit tests for GenerateStrategyCommandHandler (happy path, validation failure)
    - [x] Unit/Feature tests for Query classes
    - [x] Unit tests for new Energy CommandHandlers (Import, Export, Account)
    - [x] Update existing tests to prefer sending commands over calling actions directly when applicable
- [ ] 
    7. Add a lightweight CommandBus (done) and consider middleware later
- [ ] 
    8. Document developer workflow

    - [x] Update docs/actions.md with CQRS guidance and examples
- [ ] 
    9. Incremental rollout & toggles

    - [ ] Adopt commands for new complex writes immediately; migrate existing features opportunistically
    - [ ] Keep Actions delegating to handlers to avoid wide changes
    - [ ] Track progress here as we complete each subtask
- [ ] 
    10. Next Phase 1 increments (small, verifiable)

    - [x] Migrate AgileChart update paths to use CommandBus for write operations (ImportAgileRatesCommand,
      ExportAgileRatesCommand) while keeping reads via query
    - [x] Add a Query for a second UI read path (e.g., latest N Strategy rows for dashboard) and cover with a test
    - [x] Add one Feature test asserting Filament GenerateAction dispatches the command and surfaces failure message
    - [x] Add CommandBus binding docs snippet in README or docs/actions.md for contributors
    - [x] Evaluate adding a simple logging middleware for CommandBus (design note only; no behavior change)

### Phase 1 CQRS — Additional small, verifiable tasks

- [ ] 
    11. Additional Phase 1 CQRS increments

    - [x] Add Query: StrategyDailySummaryQuery with unit test
    - [x] Integrate StrategyDailySummaryQuery into a small UI usage (widget/controller) to verify in practice
    - [x] Add unhappy-path tests for CommandHandlers: GenerateStrategy, ImportAgileRates, ExportAgileRates,
      SyncOctopusAccount (assert ActionResult::failure with messages)
    - [x] Add a convention test asserting CommandBus has mappings for core commands (GenerateStrategy,
      Import/ExportAgileRates, SyncOctopusAccount)
    - [x] Add basic logging in handlers (start/end + timing) without middleware;
        - [x] cover with a Log::spy based unit test
    - [x] Add docs/actions.md example showing a simple Filament component consuming a Query (read-only)

- [ ] 
    12. Additional CQRS increments

    - [x] Add Command + Handler: CalculateBatteryCommand(+Handler) to encapsulate multi-row battery calculation currently in Filament CalculateBatteryAction; update UI action to dispatch via CommandBus.
    - [ ] Add Command + Handler: CopyConsumptionWeekAgoCommand(+Handler) to wrap write logic in CopyConsumptionWeekAgoAction; update UI action to dispatch via CommandBus.
    - [ ] Add Query: InverterConsumptionByTimeQuery to support any widgets/tables that aggregate inverter consumption (if applicable); add unit test and integrate where used.
    - [ ] Add Command + Handler: RefreshForecastsCommand(+Handler) to coordinate ActualForecastAction and ForecastAction with transaction/logging; wire to any UI triggers or scheduler if present.
    - [x] Add Bus mapping entries in AppServiceProvider for the new commands and cover with a CommandBusMappingsTest update.
    - [x] Add unit tests for the new handlers (happy/unhappy paths) and update any affected Feature tests.
    - [ ] Add Command + Handler: RecalculateStrategyCostsCommand(+Handler) to recompute cost-related fields for a Strategy across a date range; ensure idempotency and transaction safety; update any maintenance UI to use the command.
    - [ ] Add Command + Handler: RebalanceBatteryScheduleCommand(+Handler) to generate/adjust charge-discharge schedule based on latest forecasts and tariffs; log decisions; wire to UI or scheduler if applicable.
    - [ ] Add Command + Handler: SyncInverterMetricsCommand(+Handler) to fetch and persist telemetry from inverter APIs; encapsulate retries and error handling; expose via scheduler/CLI.
    - [ ] Add Query: StrategyPerformanceSummaryQuery returning KPIs (savings, export revenue, self-consumption) per day/week; cover with unit tests and integrate into dashboard/widget.
    - [ ] Add Query: EnergyCostBreakdownByDayQuery to provide stacked costs (import/export/net) for charts; optimize with DB-level aggregation; unit test and integrate where used.
    - [ ] Add Command + Handler: BulkImportAgileRatesCommand(+Handler) to import a date range of rates efficiently with upsert; reuse existing single-day logic; guard against duplicates.
    - [ ] Add Command + Handler: PurgeOldForecastsCommand(+Handler) to archive or delete stale forecast rows beyond retention; include dry-run option and logging; add a Console command if needed.
    - [ ] Update Filament widgets/controllers to consume new Queries (read paths) and dispatch new Commands (write paths) without leaking implementation details.
    - [ ] Extend CommandBus mappings and add/adjust a convention test to assert handlers exist for all new commands.
    - [ ] Add Feature tests covering one UI flow per new command/query integration (happy and failure messaging paths).

    Implementation details and acceptance criteria for the above (expand as you work):
    - [x] CalculateBatteryCommand
      - [x] Inputs: strategy_id or date range; options to simulate vs persist.
      - [x] Handler: validate inputs; DB::transaction; delegate to existing CalculateBatteryAction; return ActionResult; Log start/end with timing.
      - [x] Mapping: bind Command => Handler in SimpleCommandBus via AppServiceProvider.
      - [x] Tests: unit happy/unhappy; if applicable, a small feature test for Filament action dispatch.
      - [ ] Done when:
        - [x] Filament CalculateBattery UI calls $bus->dispatch(new CalculateBatteryCommand(...)).
        - [ ] Add a Feature test asserting the Filament action dispatches via CommandBus and shows success/error toasts.
        - [x] Logs include "CalculateBatteryCommand started/finished" with ms timing.
        - [x] Unit tests assert success and validation failure; optional feature test asserts toast messaging.
    - [ ] CopyConsumptionWeekAgoCommand
      - [ ] Inputs: strategy_id and target date(s).
      - [ ] Handler: validate; transaction; delegate to CopyConsumptionWeekAgoAction; return ActionResult; basic logging.
      - [ ] Mapping + Tests as above.
      - [ ] Done when:
        - [ ] Filament CopyConsumption UI dispatches the command via bus.
        - [ ] Failure path returns ActionResult::failure with messages and logs a warning.
    - [ ] RefreshForecastsCommand
      - [ ] Purpose: orchestrate ActualForecastAction then ForecastAction for a date or range.
      - [ ] Handler: transaction boundary where safe; catch/report failures; return ActionResult with messages; logging.
      - [ ] Wire-up: expose via scheduler/Artisan and any existing UI triggers.
      - [ ] Done when:
        - [ ] Artisan command (e.g., forecasts:refresh) or scheduler uses the command.
        - [ ] Unit tests cover both Actual and Forecast actions success/failure and aggregated messages.
    - [ ] Queries (InverterConsumptionByTime, StrategyPerformanceSummary, EnergyCostBreakdownByDay)
      - [ ] Return DTOs/arrays optimized for widgets/tables; avoid side-effects; add unit tests.
      - [ ] Integrate into widgets/controllers where aggregation currently happens inline.
      - [ ] Done when:
        - [ ] At least one widget/controller is switched to the Query and unit test covers shape of response.
    - [ ] BulkImportAgileRatesCommand
      - [ ] Inputs: date range; tariff id.
      - [ ] Handler: chunk by day; reuse single-day import; upsert; guard duplicates; return ActionResult with counts.
      - [ ] Done when:
        - [ ] Command processes multi-day range and returns counts; duplicates are skipped (asserted in unit test).
    - [ ] PurgeOldForecastsCommand
      - [ ] Inputs: retention window; dry-run flag.
      - [ ] Handler: select candidates; optionally delete/archive; log summary; return ActionResult; optional Console command.
      - [ ] Done when:
        - [ ] Dry-run outputs candidate counts only; real run deletes/archives and returns counts; unit tests cover both.
    - [ ] Bus mappings and convention tests
      - [ ] Update AppServiceProvider to register new Command => Handler pairs.
      - [ ] Extend CommandBusMappingsTest to assert each new Command is mapped.
      - [ ] Done when:
        - [ ] Convention test fails if a new Command lacks a handler mapping.
    - [ ] Feature coverage
      - [ ] For at least one new command and one new query integration, add a feature test covering success and failure messaging in the UI.
      - [ ] Done when:
        - [ ] Feature test asserts UI shows success toast on success and error toast on failure path.

## 2. Testing and Quality Assurance

[ ] Foundation and Security (Phase 1 alignment)

- Ensure security and initial QA items are prioritized per docs/plan.md.

[ ] **Increase test coverage**

- [ ] Add unit tests for all Models
- [ ] Add unit tests for all Actions
- [ ] Add feature tests for all Filament resources
- [ ] Add integration tests for critical user flows

[x] **Implement automated code quality tools**

- [x] Set up PHP_CodeSniffer for code style enforcement
- [x] Configure PHPStan for static analysis
- [x] Add Larastan for Laravel-specific static analysis
- [x] Set up GitHub Actions for CI/CD

[ ] **Implement end-to-end testing**

- [ ] Set up Laravel Dusk for browser testing
- [ ] Create test scenarios for critical user journeys
- [ ] Add visual regression testing

[ ] **Add performance testing**

- [ ] Benchmark database queries
- [ ] Test application under load
- [ ] Identify and fix performance bottlenecks

## 3. Performance Optimization

Phase alignment: Phase 2 — Performance and Data Management (see docs/plan.md)

[ ] **Optimize database queries**

- [ ] Review and optimize Eloquent queries
- [ ] Add appropriate indexes to database tables
- [ ] Implement query caching where appropriate

[ ] **Implement caching strategy**

- [ ] Cache frequently accessed data
- [ ] Use Redis for cache storage
- [ ] Add Redis to laravel sail
- [ ] Implement cache invalidation strategy

[ ] **Optimize front-end assets**

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

- [ ] Review authentication and authorization mechanisms
- [ ] Check for CSRF, XSS, and SQL injection vulnerabilities
- [ ] Verify proper input validation throughout the application

[ ] **Implement API security best practices**

- [ ] Use API tokens or OAuth for authentication
- [ ] Rate limit API endpoints
- [ ] Implement proper CORS configuration

[ ] **Enhance data protection**

- [ ] Encrypt sensitive data at rest
- [ ] Ensure HTTPS is enforced
- [ ] Add self signed certificates for local development, using laravel sail
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

- [ ] Create guided tour for new users
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
- [ ] Add FAQ section

[ ] **Document development processes**

- [x] Create local setup guidelines (expand on existing guide in README.md)
- [x] Add troubleshooting guide

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

- [ ] Create policy for archiving old data
- [ ] Implement automated archiving process
- [ ] Provide interface for accessing archived data

[ ] **Add data visualization tools**

- [ ] Implement admin dashboard for data insights
- [ ] Add export options for reports
- [ ] Create custom reports for specific use cases

---

Completion criteria: Before checking any task as complete, ensure PHPUnit tests pass, PHPStan static analysis has no new
issues, and PHP_CodeSniffer reports compliance (see .junie/guidelines.md). Update this tasks list and reference
docs/plan.md for phase alignment when planning next steps.


