# Solar Project Improvement Tasks

Last updated: 2025-10-06 19:49

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

[x] **Implement CQRS pattern for complex operations — Completed**

- [x] Phase 1 CQRS introduced Commands for complex writes and Queries for reads.
- [x] Filament actions updated to dispatch commands via CommandBus.
- [x] Widgets/Charts updated to use dedicated Queries.
- [x] Comprehensive tests and developer docs added.

Details and the full checklist have been moved to docs/cqrs-tasks.md.

    ## 2. Testing and Quality Assurance

[ ] Foundation and Security (Phase 1 alignment)

- Ensure security and initial QA items are prioritized per `docs/plan.md`.

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
  - [x] Energy: EloquentInverterRepository returns InverterConsumptionData with correct averages, clamping, and time periods

Note: Progress — repository-level VO mapping tests added for EloquentInverterRepository. VO edge-case unit tests added for Energy VOs (MonetaryValue VAT helpers; TimeInterval invalid ranges/overlaps/contains). Feature tests for key Filament resources (StrategyResource, ForecastResource) implemented exercising VO-backed forms and lists. Unit-testing milestone for VO mapping and core Actions is now complete and passing `composer all`. Next step — proceed with integration tests for critical flows and plan E2E coverage.
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
    - [x] BatteryStateOfCharge — add tests for percent/Wh conversions, bounds, add/subtract operations, edge cases
    - [x] EnergyFlow — add tests for import/export sign conventions, derived metrics, and zero-consumption case
    - [x] InverterConsumptionData — unit tests added for VO helpers; repository Feature tests cover bucketing/averaging/clamping
  - Forecasting Value Object
  - [x] PvEstimate — unit tests added for fromSingleEstimate path, array round-trips, and zero/negative handling
  - Actions (Domain)
    - [x] GenerateStrategyAction — add unit tests to hit branching logic without DB
  - Filament Widgets
    - [x] StrategyResource\Widgets\StrategyChart — add feature tests for dataset building and label ranges
    - [x] Filament\Widgets\AgileChart — add feature tests for series construction and time windows
    - [x] StrategyResource\\Widgets\\ElectricImportExportChart — add feature test ensuring render with faked ElectricImportExportSeriesQuery
    - [x] StrategyResource\\Widgets\\StrategyOverview — add feature test ensuring render with faked StrategyPerformanceSummaryQuery
    - [x] ForecastResource\\Widgets\\ForecastChartWidget — add feature test ensuring render with seeded Forecasts
    - [x] Filament\\Widgets\\InverterChart — add feature test ensuring render with faked InverterConsumptionRangeQuery
    - [x] Filament\\Widgets\\InverterAverageConsumptionChart — add feature test ensuring render with faked InverterConsumptionByTimeQuery
    - [x] Filament\\Widgets\\OctopusImportChart — add feature test ensuring render with faked OctopusImportAction and seeded rates/imports
    - [x] Filament\\Widgets\\SolcastActualChart — add feature test ensuring render; triggers update when stale and not when fresh
    - [x] Filament\\Widgets\\SolcastWithActualAndRealChart — add feature test ensuring merged datasets with faked actions
    - [x] Filament\\Widgets\\ForecastChart — add feature test asserting labels from minimal seeded forecasts or faked source
    - [x] Filament\\Widgets\\OctopusChart — add base chart behavior tests with faked actions

  Next step: Filament widget coverage complete. Review remaining Filament resource tests or proceed to integration tests per this document.
  - Console Commands
    - [x] Forecast, Inverter, Octopus console commands — add smoke tests asserting dispatch and options parsing

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


