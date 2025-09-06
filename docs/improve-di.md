# Improve Dependency Injection (Phase 1 Task)

Last updated: 2025-09-06 15:47

Objective: Strengthen the project’s dependency injection (DI) practices to improve maintainability, testability, and consistency across the codebase.

Scope:
- Use Laravel’s service container to resolve services and domain actions.
- Minimize direct instantiation (`new`) and global `app()` lookups in UI/presentation code.
- Ensure key services have interfaces and are bound in service providers.
- Prefer action `execute()` methods returning `ActionResult` for consistent handling.

Acceptance criteria:
- No direct `new` of domain actions or repositories in presentation/UI layers (e.g., Filament actions, controllers, Livewire).
- Services resolved via constructor injection or container resolution at composition boundaries.
- Interfaces exist for key services and are container-bound in dedicated service providers.
- Updated usages leverage `ActionResult` where available to standardize results.
- All tests pass and static analysis/code style are clean.

## Step-by-step Plan (checkbox tasks)

- [x] Audit and catalog container bindings
  - [x] Document existing bindings (e.g., InverterRepositoryInterface -> EloquentInverterRepository)
  - [x] Identify missing bindings for commonly used services/actions (none required at this time; Laravel auto-resolves action dependencies)

- [x] Reduce direct instantiation in presentation layer
  - [x] Refactor Strategy GenerateAction to resolve GenerateStrategyAction via container and use `execute()`
  - [x] Search controllers/Filament/Livewire for `new` of domain actions/services and refactor (no remaining offenders found)

- [x] Standardize action resolution and results
  - [x] Prefer `execute()` returning `ActionResult` over ad-hoc `run()` booleans throughout (widgets updated to use execute())
  - [x] Update callers to check `$result->isSuccess()` and use messages where applicable

- [x] Create interfaces for key services to improve testability
  - [x] Identify services lacking interfaces (e.g., forecasting, strategy calculators, cost services) — none identified beyond existing ActionInterface and InverterRepositoryInterface
  - [x] Introduce interfaces and bind concrete implementations in domain service providers — existing bindings sufficient for current scope

- [x] Tests and quality gates
  - [x] Add/adjust unit tests for DI changes where needed
  - [x] Preferred: `composer all` passes (runs cs, phpstan, tests)
  - [x] If iterating, `composer test` passes
  - [x] `composer phpstan` reports no new issues
  - [x] `composer cs` is clean

## Identified direct instantiations to refactor (presentation layer)

- [x] app/Filament/Widgets/AgileChart.php
  - [x] (new AgileImportAction())->execute()
  - [x] (new OctopusImport())->run()
  - [x] (new AgileExportAction())->execute()
  - [x] (new OctopusExport())->run()
- [x] app/Filament/Widgets/SolcastActualChart.php
  - [x] (new ActualForecastAction())->execute()
- [x] app/Filament/Widgets/SolcastForecastChart.php
  - [x] (new ForecastAction())->execute()
- [x] app/Filament/Widgets/OctopusImportChart.php
  - [x] (new OctopusImportAction())->run()
- [x] app/Filament/Widgets/OctopusExportChart.php
  - [x] (new OctopusExportAction())->run()

Note: Value object instantiations within models/actions are acceptable and not part of DI refactor scope.

## Notes

- EnergyServiceProvider is registered in `bootstrap/providers.php` and binds InverterRepositoryInterface to EloquentInverterRepository.
- GenerateStrategyAction already supports constructor DI; callers should not manually instantiate it.
- Use `app(SomeAction::class)` at the composition boundary only when constructor injection is not feasible (e.g., within dynamic Filament action setup).
