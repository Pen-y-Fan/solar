# Actions and CQRS in Solar

This project standardizes domain action execution using a small contract and result object, and progressively introduces CQRS for complex write operations.

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

CQRS guidance:
- Use Commands for state changes that span multiple aggregates or require transactions/validation.
- Create a Command (immutable DTO) and a CommandHandler that returns ActionResult.
- UI (Filament Actions, Controllers) should dispatch commands via CommandBus where available.
- Use Query classes/services for complex reads used by widgets/tables; keep them side-effect free.

Examples introduced:
- Command: `App\Application\Commands\Strategy\GenerateStrategyCommand` (+ `GenerateStrategyCommandHandler`).
- CommandBus: `App\Application\Commands\Bus\SimpleCommandBus` bound to `CommandBus` in AppServiceProvider.
- Query: `App\Application\Queries\Energy\AgileImportExportSeriesQuery` used by `AgileChart` widget.

CommandBus binding and usage snippet:

- Binding map (AppServiceProvider):

```php
/** @var \Illuminate\Contracts\Container\Container $app */
$bus = new \App\Application\Commands\Bus\SimpleCommandBus($app);
$bus->register(\App\Application\Commands\Strategy\GenerateStrategyCommand::class, \App\Application\Commands\Strategy\GenerateStrategyCommandHandler::class);
$bus->register(\App\Application\Commands\Energy\ImportAgileRatesCommand::class, \App\Application\Commands\Energy\ImportAgileRatesCommandHandler::class);
$bus->register(\App\Application\Commands\Energy\ExportAgileRatesCommand::class, \App\Application\Commands\Energy\ExportAgileRatesCommandHandler::class);
$bus->register(\App\Application\Commands\Energy\SyncOctopusAccountCommand::class, \App\Application\Commands\Energy\SyncOctopusAccountCommandHandler::class);
```

- Dispatching in UI/Services:

```php
/** @var \App\Application\Commands\Bus\CommandBus $bus */
$bus = app(\App\Application\Commands\Bus\CommandBus::class);
$result = $bus->dispatch(new \App\Application\Commands\Strategy\GenerateStrategyCommand(period: 'today'));
if (! $result->isSuccess()) {
    // Handle failure (show notification, log, etc.)
}
```

Design note: CommandBus logging middleware (optional)

- Purpose: Provide simple observability around command execution without changing behavior.
- Concept: Wrap CommandBus->dispatch in a middleware pipeline where each middleware receives the Command and a next() callable.
- Example interface (not implemented yet):

```php
interface CommandMiddleware {
    public function handle(\App\Application\Commands\Contracts\Command $command, callable $next): \App\Support\Actions\ActionResult;
}
```

- Example logging middleware outline:

```php
final class LoggingMiddleware implements CommandMiddleware {
    public function handle(Command $command, callable $next): ActionResult
    {
        \Log::info('Dispatching command', ['command' => $command::class]);
        $start = microtime(true);
        $result = $next($command);
        \Log::info('Command handled', [
            'command' => $command::class,
            'success' => $result->isSuccess(),
            'ms' => (int) ((microtime(true) - $start) * 1000),
        ]);
        return $result;
    }
}
```

- Wiring idea: In a future iteration, extend SimpleCommandBus to accept an array of middlewares and wrap handler->handle() with them. For Phase 1 we only document the design.

Example: Consuming a Query in a simple controller or Filament widget

- Controller example (read-only):

```php
use App\Application\Queries\Strategy\StrategyDailySummaryQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class StrategyDailySummaryController
{
    public function __invoke(Request $request, StrategyDailySummaryQuery $query)
    {
        $date = $request->query('date');
        $day = $date ? Carbon::parse($date, 'Europe/London') : now('Europe/London');
        return response()->json($query->run($day));
    }
}
```

- Filament widget outline:

```php
final class StrategySummaryWidget extends \Filament\Widgets\Widget
{
    protected static string $view = 'filament.widgets.strategy-summary';

    public function getViewData(): array
    {
        /** @var StrategyDailySummaryQuery $query */
        $query = app(StrategyDailySummaryQuery::class);
        return [ 'summary' => $query->run(now('Europe/London')) ];
    }
}
```

Next steps:
- Iteratively migrate additional complex Actions to Commands.
- Prefer CommandBus dispatch in UI for writes; keep Actions as shims during migration.
- Add tests around CommandHandlers and Query classes.
