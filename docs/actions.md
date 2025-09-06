# 

This project standardizes domain action execution using a small contract and result object.

- Contract: `App\Support\Actions\Contracts\ActionInterface` with a single `execute(): ActionResult` method.
- Result wrapper: `App\Support\Actions\ActionResult` for success/failure, message, optional data payload.
- Error handling: Implementations should catch recoverable exceptions and return `ActionResult::failure($message)`; unrecoverable errors may still bubble up.

Initial migration:
- Forecasting: `App\Domain\Forecasting\Actions\ForecastAction` and `ActualForecastAction` implement `ActionInterface` and return `ActionResult`.
- Energy: `App\Domain\Energy\Actions\AgileImport` and `AgileExport` implement `ActionInterface` and return `ActionResult`.
- Call sites should invoke `->execute()` instead of custom `run()` methods as actions are migrated.

Action inventory and migration status:
- [x] App\Domain\Forecasting\Actions\ForecastAction
- [x] App\Domain\Forecasting\Actions\ActualForecastAction
- [x] App\Domain\Energy\Actions\AgileImport
- [x] App\Domain\Energy\Actions\AgileExport
- [x] App\Domain\Energy\Actions\OctopusImport
- [x] App\Domain\Energy\Actions\OctopusExport
- [x] App\Domain\Energy\Actions\Account
- [x] App\Domain\Strategy\Actions\GenerateStrategyAction
- N/A App\Domain\Strategy\Actions\CalculateBatteryAction (Filament UI Action, not a domain Action)
- N/A App\Domain\Strategy\Actions\CopyConsumptionWeekAgoAction (Filament UI Action, not a domain Action)
- N/A App\Filament\Resources\StrategyResource\Action\GenerateAction (Filament UI Action)

Testing:
- `tests/Unit/Domain/Forecasting/ForecastActionTest.php` and `tests/Unit/Domain/Forecasting/ActualForecastActionTest.php` demonstrate how to fake HTTP and assert the standardized result.
- `tests/Unit/Domain/Energy/AgileImportActionTest.php` and `tests/Unit/Domain/Energy/AgileExportActionTest.php` cover Agile actions.

Next steps:
- Iteratively migrate the remaining domain Actions to implement the interface and return `ActionResult`.
- Update callers to use `execute()`; keep a deprecated `run()` delegator if needed for backward compatibility.
- Unify input validation at the beginning of `execute()` and return meaningful failure codes/messages.
