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
- update the output from Account commend to display current tariffs.

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
local copy of this project on your computer.

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

If Composer is not installed locally see **Docker (optional)** below.

### Local Setup with Laravel Herd

If you use Laravel Herd (recommended on macOS):

1. Install Laravel Herd from https://herd.laravel.com/
2. Ensure PHP 8.2+ and Composer are enabled in Herd settings
3. Map a site to this project directory with the domain solar.test
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

If you change the domain, update APP_URL in .env accordingly.

## Create .env

Create an `.env` file from `.env.example`

```shell script
cp .env.example .env
```

## Configure Laravel

Configure the Laravel **.env** as per you local setup. e.g.

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
```

This Laravel 12 project is configured to use **sqlite**, other databases are supported by Laravel.  
The **2025_02_26_174701_add_auto_cost_calculator_to_strategies_table.php** migration is specific to sqlite, it will 
need to be modified to use other databases.

## Create the database

The **sqlite** database will need to be manually created e.g.

```shell
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

### Solcast

The app will display actual and forecast data based on your solar panel's location,
a [free account](https://toolkit.solcast.com.au/register) can be created for home user hobbyists.

Once registered, enter your **API key** and **resource id** in the **.env** file.

### Octopus energy

Existing customers can generate a key: https://octopus.energy/dashboard/new/accounts/personal-details/api-access

Once generated, update your OCTOPUS_ACCOUNT, API_KEY, MPANs and SERIAL_NUMBERs in the **.env** file.

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

## Packages

The following packages have been used:

- [Filament admin panel](https://filamentphp.com/docs/3.x/admin/installation) - Admin panel restricted to authenticated
  users.
- [Laravel Livewire](https://laravel-livewire.com/) - Included with Filament.

## Architecture: CQRS and Domain-Driven Design

This project uses a pragmatic CQRS (Command Query Responsibility Segregation) approach alongside Domain-Driven Design (DDD) for complex operations. In short:
- Writes (state-changing operations) go through Commands handled by dedicated Handlers via a CommandBus.
- Reads (reporting/queries) are executed by Query classes returning read-optimized data structures.
- Domain logic (entities, repositories, value objects, and actions/use-cases) lives under app/Domain and is exercised from Commands/Queries.

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
- [IDE Helper](https://github.com/barryvdh/laravel-ide-helper) - Provides better IDE auto-completion for Laravel

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

Preferred during development: run the full quality suite (code style, static analysis, and tests) in one go:

```shell
composer all
```

If you need to run only tests:

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

For debugging, you can use Laravel's built-in debugging tools:

```php
// Dump and continue
dump($variable);

// Dump and die
dd($variable);
```

## Contributing

This is a **personal project**. Contributions are **not** required. Anyone interested is welcome to fork or clone for 
your own use.

## Credits

- [Michael Pritchard \(AKA Pen-y-Fan\)](https://github.com/pen-y-fan) original project

## License

MIT License (MIT). Please see [License File](LICENSE.md) for more information.
