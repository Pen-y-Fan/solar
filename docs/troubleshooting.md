# Troubleshooting Guide

This guide lists common issues encountered during local development and CI, along with steps to resolve them. Follow the Solar Project Development Guidelines in `.junie/guidelines.md` for commands and best practices.

## Table of Contents
- Environment and Setup
- Database and Migrations
- Testing (PHPUnit)
- Static Analysis (PHPStan / Larastan)
- Code Style (PHP_CodeSniffer)
- Filament / Livewire Issues
- Caching and Queues
- SSL / HTTPS in Local Dev
- Docker / Sail Notes

## Environment and Setup
- Missing .env or app key
  - Symptom: `No application encryption key has been specified.`
  - Fix:
    - cp .env.example .env
    - php artisan key:generate
- Composer not installed or wrong PHP version
  - Ensure PHP >= 8.2 and Composer are installed (Laravel Herd recommended).
- Vendor autoload not found
  - Run: composer install

## Database and Migrations
- SQLite in-memory tests fail to connect
  - Ensure phpunit.xml sets DB_CONNECTION=sqlite and DB_DATABASE=:memory:.
- Local database errors
  - Run migrations: php artisan migrate --seed
  - To reset: php artisan migrate:fresh --seed

## Testing (PHPUnit)
- Tests fail intermittently
  - Use RefreshDatabase in feature tests that touch the DB.
  - Ensure factories/seeders are up-to-date.
- Running specific tests
  - php vendor/bin/phpunit tests/path/ToTest.php
  - php vendor/bin/phpunit --filter=testMethodName

## Static Analysis (PHPStan / Larastan)
- New PHPStan errors after changes
  - Run: composer phpstan
  - Prefer fixing issues over expanding baseline. If intentional and justified, update baseline: composer phpstan-baseline
- Larastan specific
  - Ensure proper type hints, return types, and use of container/DI.

## Code Style (PHP_CodeSniffer)
- CS violations prevent CI success
  - Run: composer cs to check and composer cs-fix to auto-fix.
  - Follow PSR-12 and project conventions.

## Filament / Livewire Issues
- Components not rendering or JS errors
  - Run: php artisan view:clear && php artisan cache:clear
  - Ensure Livewire scripts/styles are included and Vite is built.
- Form model binding issues with Value Objects
  - Confirm accessors/mutators convert between raw values and VOs as per domain design.

## Caching and Queues
- Stale data
  - Clear cache: php artisan cache:clear
- Queue jobs not processing
  - Ensure queue connection configured; run worker: php artisan queue:work

## SSL / HTTPS in Local Dev
- Cannot access https://solar.test
  - Configure local DNS (Laravel Herd) and trust local certificates.
  - For Sail-based HTTPS, generate self-signed certs and configure Nginx accordingly.

## Docker / Sail Notes
- Containers fail to start
  - Check docker-compose.yml services and ports; ensure no conflicts.
  - Run: ./vendor/bin/sail up -d (if Sail is used)

## General Tips
- After changing env or config, run: php artisan config:clear
- Use dump()/dd() for debugging; remove before committing.
- Keep tests, static analysis, and code style clean before marking tasks complete.
