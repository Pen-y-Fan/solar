# Solar Project Improvement Future Tasks

## CQRS Improvements

### Future CQRS improvements (commands for upcoming/new UI elements)

Note: The following items introduce new UI elements (new actions/pages/widgets). Per scope, they are tracked separately
and are not required for the current pass focused on existing UI integrations. Implement when design/UX is ready.

- [x] Add Command + Handler: RefreshForecastsCommand(+Handler) to coordinate ActualForecastAction and ForecastAction
  with transaction/logging; wire to any UI triggers or scheduler if present.
    - [ ] plan for a UI trigger (optional)
    - [ ] Add Feature test asserting the Filament action dispatches via CommandBus and surfaces messages.
    - [ ] Done when:
        - [ ] UI trigger dispatches the command via CommandBus.
        - [ ] Unit tests cover both Actual and Forecast actions success/failure and aggregated messages.
- [x] Add Command + Handler: RecalculateStrategyCostsCommand(+Handler) to recompute cost-related fields for a Strategy
  across a date range; ensure idempotency and transaction safety; update any maintenance UI to use the command.
    - [ ] plan for a UI trigger (optional)
    - [ ] Add Feature test asserting the Filament action dispatches via CommandBus and surfaces messages.
    - [ ] Done when: expand.

- [ ] RebalanceBatteryScheduleCommand (+Handler)
    - Purpose: compute and persist an optimal charge/discharge schedule using latest forecasts and tariffs.
    - New UI: StrategyResource header action “Rebalance schedule” to trigger command; includes date filter usage and
      success/error toasts. Also expose schedules:rebalance Artisan command for headless/scheduled runs.
    - Tests: Unit tests (happy/unhappy) and a Feature test asserting the Filament action dispatches via CommandBus and
      surfaces messages.

- [ ] PurgeOldForecastsCommand (+Handler)
    - Purpose: archive or delete stale forecast rows beyond retention; include dry-run and logging.
    - New UI: Maintenance page or panel action with dry-run preview and confirmation for real run; show counts/ranges.
    - Tests: Unit tests cover dry-run and delete modes; Feature test ensures UI confirmation and messaging.

- [ ] ForecastsRefresh UI trigger (optional)
    - Purpose: Provide a dashboard button to run RefreshForecastsCommand from the UI (in addition to CLI).
    - New UI: Dashboard widget action or header action; use CommandBus; display success/error toasts.
    - Tests: Feature test covering success and failure messaging paths.
- [ ] Add Command + Handler: PurgeOldForecastsCommand(+Handler) to archive or delete stale forecast rows beyond
  retention; include dry-run option and logging.
    - Affected UI: Existing maintenance UI (if present) or temporary Filament action to trigger; otherwise CLI only.
    - Tests: Unit tests for dry-run and delete; optional feature test if a UI trigger exists.
- [ ] Add Command + Handler: BulkImportAgileRatesCommand(+Handler) to import a date range of rates efficiently with
  upsert; reuse single-day logic; guard against duplicates.
    - Affected UI: None directly; exposed via CLI only at this stage. Feature test optional; unit tests required.
    - [ ] BulkImportAgileRatesCommand
        - [ ] Inputs: date range; tariff id.
        - [ ] Handler: chunk by day; reuse single-day import; upsert; guard duplicates; return ActionResult with counts.
        - [ ] Done when:
            - [ ] Command processes multi-day range and returns counts; duplicates are skipped (asserted in unit test).
    - [ ] PurgeOldForecastsCommand
        - [ ] Inputs: retention window; dry-run flag.
        - [ ] Handler: select candidates; optionally delete/archive; log summary; return ActionResult; optional Console
          command.
        - [ ] Done when:
            - [ ] Dry-run outputs candidate counts only; real run deletes/archives and returns counts; unit tests cover
              both.
    - [ ] Bus mappings and convention tests
        - [ ] Update AppServiceProvider to register new Command => Handler pairs.
        - [ ] Extend CommandBusMappingsTest to assert each new Command is mapped.
        - [ ] Done when:
            - [ ] Convention test fails if a new Command lacks a handler mapping.
