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

Always use the full quality suite during development:

- Preferred: run all quality checks (code style, static analysis, tests) in one go:

```shell
composer all
```

If you need to run only tests (e.g., while iterating locally), you can run:

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

### Browser Tests (Laravel Dusk)

Laravel Dusk provides full-browser end-to-end tests and complements our PHPUnit suites.

Setup:
- Install Dusk: `composer require --dev laravel/dusk`
- Scaffold: `php artisan dusk:install`
- Create `.env.dusk.local` with `APP_URL=https://solar.test` (or your local domain) and a Dusk test database config (sqlite is fine).
- Ensure Chrome/Chromedriver availability (Laravel Herd provides Chrome; Dusk can manage the driver). Prefer headless mode.

Running Dusk:
- All tests (headless): `php artisan dusk`
- Specific test: `php artisan dusk tests/Browser/StrategyGenerationTest.php`
- Filter by name: `php artisan dusk --filter=StrategyGenerationTest`
- Artifacts on failure: check `tests/Browser/screenshots` and `tests/Browser/console`.

Conventions:
- Keep scenarios deterministic and fast: seed minimal data, fake external services, and use `Carbon::setTestNow()` when needed.
- Use `QUEUE_CONNECTION=sync` for Dusk runs if the flow relies on queued jobs.
- See `docs/dusk-tasks.md` for the task list and acceptance criteria of this stage.

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
    public function testStringConcatenation(): void
    {
        $string1 = "Hello";
        $string2 = "World";
        
        $result = $string1 . " " . $string2;
        
        $this->assertSame("Hello World", $result);
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
    public function testTheApplicationReturnsASuccessfulResponse(): void
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
- `app/Application`: CQRS application layer
  - `Commands/*`: Command DTOs and their Handlers; `Commands/Bus/*` has the `CommandBus` and its implementation
  - `Queries/*`: Read-side query classes used by widgets/controllers
- `app/Domain`: Domain layer (by bounded context, e.g., Energy, Strategy)
  - Domain actions/use-cases, repositories, entities/value objects

See also:
- `docs/cqrs-tasks.md` — details and acceptance criteria for the CQRS rollout (Phase 1)
- `docs/future-cqrs-tasks.md` — planned follow-ups and future tasks

### Architecture: CQRS & Domain-Driven Design

- Use Commands for complex write operations (state changes) and dispatch them via the `CommandBus`.
  - Map Command => Handler pairs in `App\Providers\AppServiceProvider`.
- Use Query classes for read operations consumed by widgets/controllers.
- Keep domain logic under `app/Domain` and call it from handlers/queries.

Testing expectations:
- Domain code (under `app/Domain`) must have automated tests.
- Command Handlers: unit tests for happy/unhappy paths; feature tests for UI actions dispatching commands and surfacing messages.
- Query classes: unit tests covering data shape/edge-cases.
- Maintain a convention test to assert CommandBus mappings (see `tests/Unit/Application/Commands/CommandBusMappingsTest.php`).

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

Run PHPUnit tests, PHPStan static analysis, and PHP_CodeSniffer code quality, using `composer all` All tests, static
analysis, and code quality should be good before marking a task as complete.

Additional requirement for Domain code:
- Any new code added under the `app/Domain` directory must be covered by automated tests before marking a task as complete.
- Check test coverage using:
  - Full suite coverage (text): `composer test-coverage-text`, output is saved to `coverage/coverage.txt`
  - Filtered coverage for a specific test run, for example: `composer test-coverage-text -- --filter=EloquentInverterRepositoryTest`

Progress will be tracked by updating the checkboxes in `docs/tasks.md` as tasks are completed and tested. Each completed
task should be marked with [x] instead of [ ].

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.22
- filament/filament (FILAMENT) - v3
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v3
- larastan/larastan (LARASTAN) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11


## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== herd rules ===

## Laravel Herd

- The application is served by Laravel Herd and will be available at: https?://[kebab-case-project-dir].test. Use the `get-absolute-url` tool to generate URLs for the user to ensure valid URLs.
- You must not run any commands to make the site available via HTTP(s). It is _always_ available through Laravel Herd.


=== filament/core rules ===

## Filament
- Filament is used by this application, check how and where to follow existing application conventions.
- Filament is a Server-Driven UI (SDUI) framework for Laravel. It allows developers to define user interfaces in PHP using structured configuration objects. It is built on top of Livewire, Alpine.js, and Tailwind CSS.
- You can use the `search-docs` tool to get information from the official Filament documentation when needed. This is very useful for Artisan command arguments, specific code examples, testing functionality, relationship management, and ensuring you're following idiomatic practices.
- Utilize static `make()` methods for consistent component initialization.

### Artisan
- You must use the Filament specific Artisan commands to create new files or components for Filament. You can find these with the `list-artisan-commands` tool, or with `php artisan` and the `--help` option.
- Inspect the required options, always pass `--no-interaction`, and valid arguments for other options when applicable.

### Filament's Core Features
- Actions: Handle doing something within the application, often with a button or link. Actions encapsulate the UI, the interactive modal window, and the logic that should be executed when the modal window is submitted. They can be used anywhere in the UI and are commonly used to perform one-time actions like deleting a record, sending an email, or updating data in the database based on modal form input.
- Forms: Dynamic forms rendered within other features, such as resources, action modals, table filters, and more.
- Infolists: Read-only lists of data.
- Notifications: Flash notifications displayed to users within the application.
- Panels: The top-level container in Filament that can include all other features like pages, resources, forms, tables, notifications, actions, infolists, and widgets.
- Resources: Static classes that are used to build CRUD interfaces for Eloquent models. Typically live in `app/Filament/Resources`.
- Schemas: Represent components that define the structure and behavior of the UI, such as forms, tables, or lists.
- Tables: Interactive tables with filtering, sorting, pagination, and more.
- Widgets: Small component included within dashboards, often used for displaying data in charts, tables, or as a stat.

### Relationships
- Determine if you can use the `relationship()` method on form components when you need `options` for a select, checkbox, repeater, or when building a `Fieldset`:

<code-snippet name="Relationship example for Form Select" lang="php">
Forms\Components\Select::make('user_id')
    ->label('Author')
    ->relationship('author')
    ->required(),
</code-snippet>


## Testing
- It's important to test Filament functionality for user satisfaction.
- Ensure that you are authenticated to access the application within the test.
- Filament uses Livewire, so start assertions with `livewire()` or `Livewire::test()`.

### Example Tests

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1))
        ->searchTable($users->last()->email)
        ->assertCanSeeTableRecords($users->take(-1))
        ->assertCanNotSeeTableRecords($users->take($users->count() - 1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Howdy',
            'email' => 'howdy@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Howdy',
        'email' => 'howdy@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Multiple Panels (setup())" lang="php">
    use Filament\Facades\Filament;

    Filament::setCurrentPanel('app');
</code-snippet>

<code-snippet name="Calling an Action in a Test" lang="php">
    livewire(EditInvoice::class, [
        'invoice' => $invoice,
    ])->callAction('send');

    expect($invoice->refresh())->isSent()->toBeTrue();
</code-snippet>


=== filament/v3 rules ===

## Filament 3

## Version 3 Changes To Focus On
- Resources are located in `app/Filament/Resources/` directory.
- Resource pages (List, Create, Edit) are auto-generated within the resource's directory - e.g., `app/Filament/Resources/PostResource/Pages/`.
- Forms use the `Forms\Components` namespace for form fields.
- Tables use the `Tables\Columns` namespace for table columns.
- A new `Filament\Forms\Components\RichEditor` component is available.
- Form and table schemas now use fluent method chaining.
- Added `php artisan filament:optimize` command for production optimization.
- Requires implementing `FilamentUser` contract for production access control.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== livewire/core rules ===

## Livewire Core
- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>


## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>


    <code-snippet name="Testing a Livewire component exists within a page" lang="php">
        $this->get('/posts/create')
        ->assertSeeLivewire(CreatePost::class);
    </code-snippet>


=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2
- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
    - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
    - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
    - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
    - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives
- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine
- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks
- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
    Livewire.hook('request', ({ fail }) => {
        if (fail && fail.status === 419) {
            alert('Your session expired');
        }
    });

    Livewire.hook('message.failed', (message, component) => {
        console.error(message);
    });
});
</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== phpunit/core rules ===

## PHPUnit Core

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit <name>` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files, these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>