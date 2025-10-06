# Filament Test Tasks

Coverage Filament tests for the following domains:

- App\Filament\Resources\StrategyResource.php and 
- App\Filament\Resources\StrategyResource\*
- App\Filament\Resources\ForecastResource.php and 
- App\Filament\Resources\ForecastResource\*
- App\Filament\Widgets

Last updated: 2025-10-06 19:49

Conventions

- Prefer fast, isolated tests (do not hit DB unless strictly needed).
- Use 'Unit' tests, where no DB reading or writing is required, are in the 'Tests\Unit' namespace
- Feature tests use the `use DatabaseMigrations;` trait, are in the 'Tests\Feature' namespace.
- Acceptance criteria are listed per item; mark [x] only when all criteria for that class are satisfied.
- Follow `/.junie/guidelines.md`, run `composer all` before marking a task complete.

---

## Filament Domain

### Resource tests (Filament)

Goal: Ensure key Filament resources render and build datasets correctly. Prefer Feature tests rendering the hosting Livewire pages, binding fake Queries/Actions via the container.

Conventions:
- Use DatabaseMigrations trait in Feature resource tests.
- Authenticate a user (Filament expects an authenticated context).
- Bind fake Query/Action implementations using $this->app->instance().

### Widget tests (Filament)

Goal: Ensure key Filament widgets render and build datasets correctly. Prefer Feature tests rendering the hosting Livewire pages, binding fake Queries/Actions via the container.

Conventions:
- Use DatabaseMigrations trait in Feature widget tests.
- Authenticate a user (Filament expects an authenticated context).
- Bind fake Query/Action implementations using $this->app->instance().

Completed:
- [x] AgileChart (App\\Filament\\Widgets\\AgileChart)
  - tests/Feature/Filament/Widgets/AgileChartFeatureTest.php — verifies series, labels (midnight date formatting), and y-axis min snapping.
- [x] StrategyChart (App\\Filament\\Resources\\StrategyResource\\Widgets\\StrategyChart)
  - tests/Feature/Filament/Widgets/StrategyChartFeatureTest.php — renders ListStrategies with mocked StrategyManualSeriesQuery.
- [x] CostChart (App\\Filament\\Resources\\StrategyResource\\Widgets\\CostChart)
  - tests/Feature/Filament/Widgets/CostChartFeatureTest.php — renders ListStrategies with mocked EnergyCostBreakdownByDayQuery.

Planned (to add):
- [x] ElectricImportExportChart (StrategyResource\\Widgets\\ElectricImportExportChart) — bind fake ElectricImportExportSeriesQuery and assert render.
  - tests/Feature/Filament/Widgets/ElectricImportExportChartFeatureTest.php — renders ListStrategies with mocked ElectricImportExportSeriesQuery.
- [x] StrategyOverview (StrategyResource\\Widgets\\StrategyOverview) — bind fake StrategyPerformanceSummaryQuery and assert metrics presence.
  - tests/Feature/Filament/Widgets/StrategyOverviewFeatureTest.php — renders ListStrategies with mocked StrategyPerformanceSummaryQuery.
- [x] ForecastChartWidget (ForecastResource\\Widgets\\ForecastChartWidget) — bind fake query/action or seed minimal forecasts and assert render.
  - tests/Feature/Filament/Widgets/ForecastChartWidgetFeatureTest.php — renders ListForecasts with seeded Forecasts.
- [x] InverterChart (App\\Filament\\Widgets\\InverterChart) — bind fake InverterConsumptionRangeQuery and assert datasets.
  - tests/Feature/Filament/Widgets/InverterChartFeatureTest.php — renders widget with mocked InverterConsumptionRangeQuery.
- [x] InverterAverageConsumptionChart (App\\Filament\\Widgets\\InverterAverageConsumptionChart) — bind fake InverterConsumptionByTimeQuery and assert datasets.
  - tests/Feature/Filament/Widgets/InverterAverageConsumptionChartFeatureTest.php — renders widget with mocked InverterConsumptionByTimeQuery.
- [x] OctopusImportChart (App\\Filament\\Widgets\\OctopusImportChart) — bind fake OctopusImportAction and assert render without external calls.
  - tests/Feature/Filament/Widgets/OctopusImportChartFeatureTest.php — builds datasets and labels using seeded imports and rates.
- [x] OctopusExportChart (App\\Filament\\Widgets\\OctopusExportChart) — bind fake OctopusExportAction and assert render.
  - tests/Feature/Filament/Widgets/OctopusExportChartFeatureTest.php — renders widget with mocked OctopusExportAction and seeded export rates.
- [x] SolcastForecastChart (App\\Filament\\Widgets\\SolcastForecastChart) — bind fake ForecastAction and assert render.
- [x] SolcastActualChart (App\\Filament\\Widgets\\SolcastActualChart) — bind fake ActualForecastAction and assert render.
  - tests/Feature/Filament/Widgets/SolcastActualChartFeatureTest.php — renders widget; asserts update is triggered when stale and not called when fresh.
- [x] SolcastWithActualAndRealChart (App\\Filament\\Widgets\\SolcastWithActualAndRealChart) — bind fake actions and assert merged datasets.
- [x] ForecastChart (App\\Filament\\Widgets\\ForecastChart) — seed minimal forecasts or fake source, assert labels.
- [x] OctopusChart (App\\Filament\\Widgets\\OctopusChart) — base chart behaviors with faked actions.

Next step:
- Proceed to integration tests per docs/tasks.md. Start with Strategy generation end-to-end: from command dispatch to persisted Strategy with correct VO states.
    
Notes:
- Reuse existing Unit helper shims under tests/Unit/Filament/Widgets/* for accessing protected widget methods when needed.
- Keep tests fast; avoid external HTTP. Prefer container-bound fakes.
