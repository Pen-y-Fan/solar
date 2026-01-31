# Solar

This is a **personal project** to experiment with Solar panel APIs for my home solar setup. This is a very specific
project, it requires a **Solis inverter** and energy supplied by **Octopus energy**.

- [Solcast](https://docs.solcast.com.au/) estimate forecast based on cloud cover
- [Agile import and export tariff](https://www.guylipman.com/octopus/api_guide.html) also
  see [Agile costs](https://developer.octopus.energy/docs/api/)
- [Historic usage](https://octopus.energy/dashboard/new/accounts/personal-details/api-access)
- Import downloadable data from the Solis inverter Excel (xls) file
- Calculate cost for the previous month
- Calculate rolling average usage per 30 minutes
- Forecast battery and solar usage with cost(s)
- Battery charging strategy
- Create a comparison with Octopus Outgoing (currently 15p/kwh export)
    - https://api.octopus.energy/v1/products/OUTGOING-VAR-24-10-26/electricity-tariffs/E-1R-OUTGOING-VAR-24-10-26-K/standard-unit-rates/
- update the output from the "OctopusAccount command" `app:octopus-account` to display the current tariffs.
- See [user request](./docs/user-requests.md) for other suggested improvements.

## Requirements

This is a Laravel 12 project. The requirements are the same as a
new [Laravel 12 project](https://laravel.com/docs/12.x/installation).

- PHP 8.2 or higher
- Composer
- Laravel Herd (recommended) or Docker

Recommended:

- [Git](https://git-scm.com/downloads)

## Clone

See [Cloning a repository](https://help.github.com/en/articles/cloning-a-repository) for details on how to create a
local copy of this project.

e.g.

```sh
git clone git@github.com:Pen-y-Fan/solar.git
```

## Install

Install all the dependencies using Composer.

```sh
cd solar
composer install
```

## Performance Testing

See the performance testing plan and quick-start instructions:

- `docs/performance-testing.md` — overarching plan, scenarios, CI gate, and thresholds
- `tests/Performance/README.md` — how to run k6 locally or via Docker
- `docs/perf-profiling.md` — developer guide for SQL query logging, Telescope/Clockwork, and caching checks

Quick smoke example (Docker):

```sh
docker run --rm -i -e APP_URL=https://solar.test grafana/k6 run - < tests/Performance/dashboard.k6.js
```

Seed a realistic dataset before running perf scenarios:

```sh
php artisan migrate:fresh --seed --seeder=PerformanceSeeder
```

If Composer is not installed locally, see **Docker (optional)** below.

### Local Setup with Laravel Herd

Laravel Herd (recommended on macOS):

1. Install Laravel Herd from https://herd.laravel.com/
2. Ensure PHP 8.2+ and Composer are enabled in Herd settings
3. Map a site to this project directory with the domain <solar.test>
4. Trust Herd’s certificate so https://solar.test works locally

Quickstart (first time):

```sh
cp .env.example .env
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan key:generate
php artisan migrate --seed
```

Then open https://solar.test and login with:

- Email: test@example.com
- Password: password

If the domain is changed or different, update the APP_URL in `.env` accordingly.

## Create .env

Create an `.env` file from `.env.example`

```shell script
cp .env.example .env
```

## Configure Laravel

Configure the Laravel **.env** as per the local setup:

```ini
APP_NAME = Solar

APP_URL = https://solar.test

# Sign up for Solcast API: https://docs.solcast.com.au/
SOLCAST_API_KEY =
SOLCAST_RESOURCE_ID =

# Existing customers can generate a key: https://octopus.energy/dashboard/new/accounts/personal-details/api-access
OCTOPUS_API_KEY =
OCTOPUS_ACCOUNT =
OCTOPUS_EXPORT_MPAN =
OCTOPUS_IMPORT_MPAN =
OCTOPUS_EXPORT_SERIAL_NUMBER =
OCTOPUS_IMPORT_SERIAL_NUMBER =

# Solis API access (optional). Solis customers and sign up for support and request an API for personal
# use: https://solis-service.solisinverters.com
SOLIS_KEY_ID =
SOLIS_KEY_SECRET =
SOLIS_API_URL = https://www.soliscloud.com:13333
```

## Create the database

The **sqlite** database will need to be manually created e.g.

```shell
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

This Laravel 12 project is configured to use **sqlite**, other databases are supported by Laravel.  
The **2025_02_26_174701_add_auto_cost_calculator_to_strategies_table.php** migration is specific to sqlite, it will
need to be modified to use other databases.

### Solcast

The app will display actual and forecast data based on the solar panel's location;
a [free account](https://toolkit.solcast.com.au/register) can be created for home user hobbyists.

Once registered, enter the **API key** and **resource id** in the **.env** file.

#### Solcast API Allowance configuration

When using a hobbyist account, there is a maximum allowance of eight calls per day to fetch the Solcast API data. The
app can be set to tune how often it calls Solcast and how the daily cap/backoff works via these `.env`
variables (defaults shown):

```ini
# Combined daily cap across forecast + actual requests
SOLCAST_DAILY_CAP = 6
# ISO-8601 durations for minimum intervals and 429 backoff
SOLCAST_FORECAST_MIN_INTERVAL = PT4H
SOLCAST_ACTUAL_MIN_INTERVAL = PT8H
SOLCAST_429_BACKOFF = PT8H
# Reset window timezone (IANA tz) for daily allowance reset
SOLCAST_RESET_TZ = UTC
```

These are read from `config/solcast.php` under the `allowance` section. See `docs/solcast-api-allowance.md` for the full
policy and rationale.

### Octopus energy

Existing customers can generate a key: https://octopus.energy/dashboard/new/accounts/personal-details/api-access

Once generated, update the OCTOPUS_ACCOUNT, API_KEY, MPANs, and SERIAL_NUMBERs in the **.env** file.

### Solis API access (optional)

Solis customers and sign up for support and request an API for personal use: https://solis-service.solisinverters.com

The process is to sign up for support and request an API for home use. Once signed up and requested, Solcast will end an
email granting the request. Follow the instructions in the email to complete the process.

SOLIS_KEY_ID=
SOLIS_KEY_SECRET=
SOLIS_API_URL=https://www.soliscloud.com:13333

Without Solis API access:

- the inverter report Microsoft Excel document will need to be uploaded regularly.
- Charging strategies will need to be manually input in the Solis cloud website.

## Generate APP_KEY

Generate an APP_KEY using the artisan command:

```shell script
php artisan key:generate
```

## Install Database

This project uses models and seeders to generate the tables for the database.

```shell
php artisan migrate --seed
# or if previously migrated: 
php artisan migrate:fresh --seed
```

## Login

Access the application at `https://solar.test` (requires local DNS configuration in Laravel Herd)

Login with the seeded user:

- Email: **test@example.com**
- Password: **password**

## CLI/Artisan Commands

The project provides a few artisan commands to run data refreshes and maintenance tasks. Run `php artisan list` to see
all, or the following key commands:

- Forecasting (Solcast):
    - `php artisan app:forecast [--force]`
        - Fetches Solcast forecast and estimated actual via the Command Bus.
        - `--force` bypasses only the per-endpoint minimum-interval check. Policy still
          enforces the daily cap and active backoff.
    - `php artisan forecasts:refresh {--date=}`
        - Refresh both actual and future forecasts for the optional date (YYYY-MM-DD); defaults to today. Uses Command
          Bus.
- Octopus Energy:
    - `php artisan app:octopus`
        - Fetch usage/cost data and Agile tariffs.
    - `php artisan app:octopus-account`
        - Fetch and log account details.
- Inverter import:
    - `php artisan app:inverter`
        - Import Solis inverter XLS files from `storage/app/uploads/`.
- Maintenance:
    - `php artisan solcast:prune-logs {--days=}`
        - Prune `solcast_allowance_logs` older than the retention window (default 14 days, or override with `--days`).

Notes:

- All Solcast requests are governed by `SolcastAllowanceService` (daily cap, per-endpoint min intervals, and 429
  backoff). There is a maximum of eight requests per day (UTC) using a free hobbyist plan.
 
## Packages

The following packages have been used:

- [Filament admin panel](https://filamentphp.com/docs/3.x/admin/installation) - Admin panel restricted to authenticated
  users.
- [Livewire](https://laravel-livewire.com/) – Included with Filament.

## Architecture: CQRS and Domain-Driven Design

This project uses a pragmatic CQRS (Command Query Responsibility Segregation) approach alongside Domain-Driven
Design (DDD) for complex operations. In short:

- Writes (state-changing operations) go through Commands handled by dedicated Handlers via a CommandBus.
- Reads (reporting/queries) are executed by Query classes returning read-optimised data structures.
- Domain logic (entities, repositories, value objects, and actions/use-cases) lives under app/Domain and is exercised
  from Commands/Queries.

Key locations:

- Commands & Bus:
    - app/Application/Commands/* (Command objects and Handlers)
    - app/Application/Commands/Bus/* (CommandBus interfaces/implementation)
    - Handler mappings are registered in App\Providers\AppServiceProvider.
- Queries (read side):
    - app/Application/Queries/* (query classes grouped by feature area)
- Domain (DDD):
    - app/Domain/* (by bounded context, e.g., Energy, Strategy), including Actions, Repositories, and supporting classes

Usage example (dispatching a command):

```php
use App\Application\Commands\Bus\CommandBus;
use App\Application\Commands\Strategy\GenerateStrategyCommand;

$bus = app(CommandBus::class);
$result = $bus->dispatch(new GenerateStrategyCommand($strategyId, $dateFrom, $dateTo));

if ($result->failed()) {
    // handle error message(s)
}
```

Further reading and task history:

- docs/cqrs-tasks.md — details and acceptance criteria for the CQRS rollout (Phase 1)
- docs/future-cqrs-tasks.md — planned follow-ups and future commands/queries

### Development Tools

- [Laravel Herd](https://herd.laravel.com/) - Mac-based development environment for PHP and Laravel
- [IDE Helper](https://github.com/barryvdh/laravel-ide-helper) – Provides better IDE auto-completion for Laravel

### Docker (optional)

Laravel Sail has been installed as a composer package, if composer is not installed locally, it can be run using:

```shell
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php83-composer:latest \
    composer install --ignore-platform-reqs
```

Once the **.env** file and database have already been configured, the project can be run:

```shell
./vendor/bin/sail up -d
```

Refer to the [Laravel sail documentation](https://laravel.com/docs/12.x/sail) for full details, generally `php` can be
replaced with `./vendor/bin/sail` in the commands below.

## Code Quality Tools

This project includes several code quality tools to help maintain code standards and identify potential issues.

### Running Code Quality Tools

#### PSR-12 Code Style

The project follows PSR-12 coding standards, enforced through PHP_CodeSniffer.

To check code style compliance:

```shell
composer cs
```

To automatically fix code style issues:

```shell
composer cs-fix
```

#### Laravel Pint

Laravel Pint is an opinionated PHP code style fixer based on PHP-CS-Fixer.

To run Pint:

```shell
php artisan pint
```

To run Pint and show all files that would be modified:

```shell
php artisan pint --test
```

#### Static Analysis

PHPStan is used for static analysis to detect potential errors.

To run static analysis:

```shell
composer phpstan
```

To generate a PHPStan baseline:

```shell
composer phpstan-baseline
```

### Testing

The project uses PHPUnit for testing. The configuration is in `phpunit.xml` at the root of the project.

To run all tests:

```shell
composer test
```

To run tests with a coverage report (HTML):

```shell
composer test-coverage
```

To run tests with a coverage report, in text format, which is saved to coverage/coverage.txt

```shell
composer test-coverage-text
```

To run a specific test file:

```shell
php vendor/bin/phpunit tests/path/to/TestFile.php
```

To run a specific test method:

```shell
php vendor/bin/phpunit --filter=methodName
```

### Run All Quality Checks

To run code style check, static analysis, and test coverage in sequence:

```shell
composer all
```

## Debugging

For debugging, Laravel has built-in debugging tools:

```php
// Dump and continue
dump($variable);

// Dump and die
dd($variable);
```

## Filament Chart.js plugins (Agile/Cost charts interactivity)

The Agile and Strategy Cost charts use additional Chart.js plugins for interactivity:

- `chartjs-plugin-zoom` for zooming and panning
- `chartjs-plugin-annotation` for the current-period indicator
- A small custom helper plugin to aggregate tooltip content and wire the global Reset Zoom event

How it works:

- Plugins are provided to Filament by pushing them onto `window.filamentChartJsPlugins` in
  `resources/js/filament-chart-js-plugins.js` (per the Filament docs: widgets/charts → Using custom Chart.js plugins).
- The JS file is included in the Vite build via `vite.config.js` (see the `input` array) and registered for the App
  panel
  in `App\\Providers\\Filament\\AppPanelProvider` using `Js::make(...)->module()` with `Vite::asset(...)`.

Local development steps:

1. Install dependencies once:

   ```shell
   npm install
   ```

2. Run the dev server (recommended during UI work):

   ```shell
   npm run dev
   ```

   Alternatively, build for production:

   ```shell
   npm run build
   ```

3. If plugins are change `resources/js/filament-chart-js-plugins.js`, hard‑refresh the browser (Cmd+Shift+R) to bypass
   cache.

Verifying in the browser:

- Open DevTools Console and check `window.filamentChartJsPlugins` contains `zoom`, `annotation`, and an object with
  `id: 'solarTooltipHelper'`.
- On a page with charts, mouse‑wheel/pinch to zoom on the X‑axis, drag to pan, and click the “Reset Zoom” button in the
  widget header to restore the full range.

## Contributing

This is a **personal project**. Contributions are **not** required. Anyone interested is welcome to fork or clone for
their own use.

## Credits

- [Michael Pritchard \(AKA Pen-y-Fan\)](https://github.com/pen-y-fan) original project

## License

MIT Licence (MIT). Please see [Licence File](LICENSE.md) for more information.
