# Solar Project Development Guidelines

This document provides guidelines for development on the Solar project. It includes build/configuration instructions, testing information, and additional development details.

## Build/Configuration Instructions

### Prerequisites
- Docker and Docker Compose (for using Laravel Sail)
- Composer (optional, can use Docker for Composer commands)

### Setup with Docker (Laravel Sail)

1. Clone the repository
2. If Composer is not installed locally, you can install dependencies using Docker:
   ```shell
   docker run --rm \
       -u "$(id -u):$(id -g)" \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php83-composer:latest \
       composer install --ignore-platform-reqs
   ```

3. Copy the `.env.example` file to `.env` and configure your environment variables:
   ```shell
   cp .env.example .env
   ```

4. Start the Docker containers:
   ```shell
   ./vendor/bin/sail up -d
   ```

5. Generate an application key:
   ```shell
   ./vendor/bin/sail artisan key:generate
   ```

6. Run database migrations and seed the database:
   ```shell
   ./vendor/bin/sail artisan migrate --seed
   ```
   
   Or to refresh the database:
   ```shell
   ./vendor/bin/sail artisan migrate:fresh --seed
   ```

7. Access the application at `https://solar.test` (requires local DNS configuration)

### Login Credentials
- Email: `test@example.com`
- Password: `password`

## Testing Information

### Testing Configuration

The project uses PHPUnit for testing. The configuration is in `phpunit.xml` at the root of the project. Key configuration points:

- Tests are organized into two suites: Unit and Feature
- Tests use an in-memory SQLite database for testing
- Environment variables are configured for testing in the `<php>` section of `phpunit.xml`

### Running Tests

To run all tests:
```shell
./vendor/bin/sail test
```

To run a specific test file:
```shell
./vendor/bin/sail test tests/path/to/TestFile.php
```

To run a specific test method:
```shell
./vendor/bin/sail test --filter=methodName
```

### Creating Tests

#### Unit Tests

Unit tests should be placed in the `tests/Unit` directory. They should extend `PHPUnit\Framework\TestCase` and focus on testing individual components in isolation.

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

Feature tests should be placed in the `tests/Feature` directory. They should extend `Tests\TestCase` and focus on testing the application as a whole, including HTTP requests, database interactions, etc.

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

For tests that require database interactions, use the `RefreshDatabase` trait to ensure a clean database state for each test:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    
    // Test methods...
}
```

## Additional Development Information

### Project Structure

The project follows the standard Laravel structure with some additions:

- `app/Filament`: Contains Filament admin panel resources and widgets
- `tests/Fixtures`: Contains test fixtures

### Key Packages

- **Filament Admin Panel**: Used for the admin interface. Documentation: https://filamentphp.com/docs/3.x/admin/installation
- **Laravel Livewire**: Included with Filament for reactive components. Documentation: https://laravel-livewire.com/

### Development Tools

- **Laravel Sail**: Docker-based development environment
- **IDE Helper**: Provides better IDE auto-completion for Laravel

### Code Style

The project follows PSR-12 coding standards. While not currently enforced through automated tools, it's recommended to follow these standards for consistency.

### Debugging

For debugging, you can use Laravel's built-in debugging tools:

```php
// Dump and continue
dump($variable);

// Dump and die
dd($variable);
```

### Docker Commands

Common Docker commands for this project:

- Start containers: `./vendor/bin/sail up -d`
- Stop containers: `./vendor/bin/sail down`
- Run Artisan commands: `./vendor/bin/sail artisan <command>`
- Run Composer commands: `./vendor/bin/sail composer <command>`
- Run NPM commands: `./vendor/bin/sail npm <command>`
