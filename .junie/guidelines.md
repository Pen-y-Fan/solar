# Solar Project Development Guidelines

This document provides guidelines for development on the Solar project. It includes build/configuration instructions,
testing information, and additional development details.

## Role

You are a senior developer on the Solar project. Your role is to implement new features, fix bugs, and improve the 
project's code quality. You should be familiar with the project's architecture and code style. You should also be 
familiar with the Laravel framework and its ecosystem. You should be able to complete tasks in the project's 
[task list](../docs/tasks.md) according to the [plan](../docs/plan.md) and following this `.junie/guidelines.md`

## Build/Configuration Instructions

### Prerequisites

- PHP 8.2 or higher (managed by Laravel Herd)
- Composer (managed by Laravel Herd)
- Laravel Herd installed on your Mac

### Setup with Laravel Herd

1. Clone the repository

2. Install dependencies using Composer:
   ```shell
   composer install
   ```

3. Copy the `.env.example` file to `.env` and configure your environment variables:
   ```shell
   cp .env.example .env
   ```

4. Generate an application key:
   ```shell
   php artisan key:generate
   ```

5. Run database migrations and seed the database:
   ```shell
   php artisan migrate --seed
   ```

   Or to refresh the database:
   ```shell
   php artisan migrate:fresh --seed
   ```

6. Access the application at `https://solar.test` (requires local DNS configuration in Laravel Herd)

### Login Credentials

- Email: `test@example.com`
- Password: `password`

## Testing Information

### Testing Configuration

The project uses PHPUnit for testing. The configuration is in `phpunit.xml` at the root of the project. Key
configuration points:

- Tests are organized into two suites: Unit and Feature
- Tests use an in-memory SQLite database for testing
- Environment variables are configured for testing in the `<php>` section of `phpunit.xml`

### Running Tests

To run all tests:

```shell
composer test
```

To run tests with coverage report (HTML):

```shell
composer test-coverage
```

To run tests with coverage report (text output):

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

### Creating Tests

#### Unit Tests

Unit tests should be placed in the `tests/Unit` directory. They should extend `PHPUnit\Framework\TestCase` and focus on
testing individual components in isolation.

Example:

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_string_concatenation(): void
    {
        $string1 = "Hello";
        $string2 = "World";
        
        $result = $string1 . " " . $string2;
        
        $this->assertEquals("Hello World", $result);
    }
}
```

#### Feature Tests

Feature tests should be placed in the `tests/Feature` directory. They should extend `Tests\TestCase` and focus on
testing the application as a whole, including HTTP requests, database interactions, etc.

Example:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/welcome');

        $response->assertStatus(200);
    }
}
```

### Database Testing

For tests that require database interactions, use the `RefreshDatabase` trait to ensure a clean database state for each
test:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    
    // Test methods...
}
```

## Code Quality and Static Analysis

The project uses several tools to maintain code quality:

### PSR-12 Code Style

The project follows PSR-12 coding standards, enforced through PHP_CodeSniffer.

To check code style compliance:

```shell
composer cs
```

To automatically fix code style issues:

```shell
composer cs-fix
```

### Static Analysis

PHPStan is used for static analysis to detect potential errors.

To run static analysis:

```shell
composer phpstan
```

To generate a PHPStan baseline:

```shell
composer phpstan-baseline
```

### Run All Quality Checks

To run code style check, static analysis, and test coverage in sequence:

```shell
composer all
```

## Additional Development Information

### Project Structure

The project follows the standard Laravel structure with some additions:

- `app/Filament`: Contains Filament admin panel resources and widgets
- `tests/Fixtures`: Contains test fixtures

### Key Packages

- **Filament Admin Panel**: Used for the admin interface.
  Documentation: https://filamentphp.com/docs/3.x/admin/installation
- **Laravel Livewire**: Included with Filament for reactive components. Documentation: https://laravel-livewire.com/

### Development Tools

- **Laravel Herd**: Mac-based development environment for PHP and Laravel
- **IDE Helper**: Provides better IDE auto-completion for Laravel

### Debugging

For debugging, you can use Laravel's built-in debugging tools:

```php
// Dump and continue
dump($variable);

// Dump and die
dd($variable);
```

## Progress Tracking

Run PHPUnit tests, PHPStan static analysis, and PHP_CodeSniffer code quality. All
tests, static analysis, and code quality should be good before marking a task as complete.

Progress will be tracked by updating the checkboxes in `docs/tasks.md` as tasks are completed and tested. Each completed
task should be marked with [x] instead of [ ]. 
