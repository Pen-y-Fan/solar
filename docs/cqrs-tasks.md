# CQRS Tasks (Phase 1)

Last updated: 2025-09-28

This document consolidates the CQRS-related tasks that were previously part of docs/tasks.md. It captures the detailed
checklists, acceptance criteria, and context for the CQRS rollout. Refer to this file for the full history and details
of our CQRS implementation.

---

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

- [x] 
    12. Additional CQRS increments (replanned)

  Context: We are introducing small CQRS increments to move write-heavy workflows behind Commands while keeping reads
  via Queries.

  The goal is to make these operations scriptable/schedulable and testable without coupling to Filament UI. Existing
  actions, widgets and charts should be updated to use the new commands and queries. New commands and queries should be
  added as needed for existing UI elements. They are not required for new UI elements at this time.

  Scope of UI impact (existing elements only):
    - Actions (Filament header actions):
        - StrategyResource: GenerateAction (already using Command), CopyConsumptionWeekAgoAction (updated),
          CalculateBatteryAction (updated).
        - Upcoming: RebalanceBatteryScheduleAction (new action wiring to a command) — see Future section if UI is new.
    - Widgets/Charts:
        - InverterAverageConsumptionChart: updated to use InverterConsumptionByTimeQuery (read-only).
        - InverterChart: to be updated to consume a new InverterConsumptionRangeQuery (planned below) instead of inline
          Eloquent.
        - StrategyResource widgets (StrategyOverview, StrategyChart, CostChart, ElectricImportExportChart): to
          progressively switch to dedicated Queries for aggregation where applicable.

        - [x] Add Command + Handler: CalculateBatteryCommand(+Handler) to encapsulate multi-row battery calculation
          currently in Filament CalculateBatteryAction; update UI action to dispatch via CommandBus.
        - [x] Add Command + Handler: CopyConsumptionWeekAgoCommand(+Handler) to wrap write logic in
          CopyConsumptionWeekAgoAction; update UI action to dispatch via CommandBus.
        - [x] Add Query: InverterConsumptionByTimeQuery to support any widgets/tables that aggregate inverter
          consumption (if applicable); add unit test and integrate where used.
        - [x] Add Bus mapping entries in AppServiceProvider for the new commands and cover with a CommandBusMappingsTest
          update.
        - [x] Add unit tests for the new handlers (happy/unhappy paths) and update any affected Feature tests.
        - [x] Feature coverage
            - [x] For at least one new command and one new query integration, add a feature test covering success and
              failure messaging in the UI.
            - [x] Done when:
                - [x] Feature test asserts UI shows success toast on success and error toast on failure path.

        - [x] Add Query: InverterConsumptionRangeQuery to support InverterChart with a clean read model.
            - Implement: app/Application/Queries/Energy/InverterConsumptionRangeQuery.php, add mapping in
              AppServiceProvider.
            - Affected UI: app/Filament/Widgets/InverterChart.php — refactor getDatabaseData() to call the query (
              time-range inputs).
            - Tests: tests/Unit/Application/Queries/InverterConsumptionRangeQueryTest.php covers response shape and
              ordering.
            - Done when: InverterChart reads exclusively via the query; no inline Eloquent in the widget.

        - [x] Add Query: StrategyPerformanceSummaryQuery returning KPIs (savings, export revenue, self-consumption) per
          day/week; integrate into StrategyOverview widget.
                    - Implement: app/Application/Queries/Strategy/StrategyPerformanceSummaryQuery.php and add mapping in
                    AppServiceProvider.
                    - Affected UI: app/Filament/Resources/StrategyResource/Widgets/StrategyOverview.php — replace inline totals
                      with this Query.
                    - Tests: tests/Unit/Application/Queries/StrategyPerformanceSummaryQueryTest.php covering KPI math and
                      empty-state.
                    - Done when: StrategyOverview computes all stats via the Query; remove inline reduce/sum logic.

        - [x] Add Query: EnergyCostBreakdownByDayQuery to provide stacked import/export/net costs for charts.
            - Implement: app/Application/Queries/Energy/EnergyCostBreakdownByDayQuery.php and add mapping in
              AppServiceProvider.
            - Affected UI: app/Filament/Resources/StrategyResource/Widgets/CostChart.php — replace mapping of Strategy
              rows with query output (valid_from, import_value_inc_vat, export_value_inc_vat, net_cost).
            - Tests: tests/Unit/Application/Queries/EnergyCostBreakdownByDayQueryTest.php validating aggregation and net
              cost.
            - Done when: CostChart reads via Query and computes averages from returned series; no direct Strategy
              mapping. Coverage is 100% for the query.

        - [x] Add Query: ElectricImportExportSeriesQuery to provide import/export and accumulative cost series for
          actuals.
            - Implement: app/Application/Queries/Energy/ElectricImportExportSeriesQuery.php and add mapping in
              AppServiceProvider.
            - Affected UI: app/Filament/Resources/StrategyResource/Widgets/ElectricImportExportChart.php — replace
              Eloquent logic with the query (input: date, limit=48), keep battery overlay.
            - Tests: tests/Unit/Application/Queries/ElectricImportExportSeriesQueryTest.php checking series fields and
              accumulation. Coverage is 100% for the query.

        - [x] Add Query: StrategyManualSeriesQuery to provide manual strategy import/export and accumulative cost series.
            - Implement: app/Application/Queries/Strategy/StrategyManualSeriesQuery.php and add unit test.
            - Affected UI: app/Filament/Resources/StrategyResource/Widgets/StrategyChart.php — replace inline aggregation with the query.
            - Tests: tests/Unit/Application/Queries/StrategyManualSeriesQueryTest.php covering accumulation, null handling.
        - [x] confirm all possible filament widgets/charts/actions and domain actions are using the new commands and not
          inline Eloquent. Expand plan as needed.
        - [x] Done when:
            - [x] All Filament widgets/charts are using the new queries.
            - [x] All possible filament actions and domain actions are using the new commands.
            - [x] All existing UI features are covered by Feature tests, using mock data where possible.

Implementation details and acceptance criteria for the above (expand as you work):
- [x] CalculateBatteryCommand
- [x] Inputs: strategy_id or date range; options to simulate vs persist.
- [x] Handler: validate inputs; DB::transaction; delegate to existing CalculateBatteryAction; return ActionResult; Log
start/end with timing.
- [x] Mapping: bind Command => Handler in SimpleCommandBus via AppServiceProvider.
- [x] Tests: unit happy/unhappy; if applicable, a small feature test for Filament action dispatch.
- [x] Done when:
- [x] Filament CalculateBattery UI calls $bus->dispatch(new CalculateBatteryCommand(...)).
- [x] Add a Feature test asserting the Filament action dispatches via CommandBus and shows success/error toasts.
- [x] Logs include "CalculateBatteryCommand started/finished" with ms timing.
- [x] Unit tests assert success and validation failure; optional feature test asserts toast messaging.
- [x] CopyConsumptionWeekAgoCommand
- [x] Inputs: date (day) to operate on; defaults to today.
- [x] Handler: parse date; transaction; iterate strategies for day; copy lastWeek -> manual where present; return
ActionResult; basic logging.
- [x] Mapping + Tests as above.
- [x] Done when:
- [x] Filament CopyConsumption UI dispatches the command via bus.
- [x] Failure path returns ActionResult::failure with messages and logs a warning.
- [x] RefreshForecastsCommand
- [x] Purpose: orchestrate ActualForecastAction then ForecastAction for a date or range.
- [x] Handler: sequential orchestration; avoid long transactions; aggregate messages; logging with timings.
- [x] Wire-up: exposed as an Artisan console command forecasts:refresh (see
app/Console/Commands/RefreshForecastsConsole.php). No UI action added yet by design.
- [x] Done when:
- [x] Artisan command (forecasts:refresh) runs and returns success/failure.
- [x] Unit tests cover both Actual and Forecast actions success/failure and aggregated messages.
- [x] Feature coverage
- [x] For at least one new command and one new query integration, add a feature test covering success and failure
messaging in the UI.
- [x] Done when:
- [x] Feature test asserts UI shows success toast on success and error toast on failure path.
