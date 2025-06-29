# Code Coverage

This document explains how to generate and view code coverage reports for the Solar project.

## What is Code Coverage?

Code coverage is a measure of how much of your code is executed during your tests. It helps identify areas of your codebase that aren't being tested, which could potentially contain bugs or unexpected behavior.

## Prerequisites

- Xdebug must be installed and enabled (already included in the Laravel Sail environment)
- PHPUnit must be configured for code coverage (already configured in `phpunit.xml`)

## Generating Code Coverage Reports

### Using Artisan Command

The easiest way to generate a code coverage report is to use the Artisan command:

```bash
# Generate HTML report (default)
./vendor/bin/sail artisan test:coverage

# Generate text report in the console
./vendor/bin/sail artisan test:coverage --format=text

# Generate Clover XML report
./vendor/bin/sail artisan test:coverage --format=clover

# Generate HTML report and open it in the browser
./vendor/bin/sail artisan test:coverage --open
```

### Using Composer Scripts

You can also use the Composer scripts to generate code coverage reports:

```bash
# Generate HTML report
./vendor/bin/sail composer test-coverage

# Generate text report in the console
./vendor/bin/sail composer test-coverage-text
```

## Viewing Code Coverage Reports

### HTML Report

The HTML report provides a detailed, interactive view of your code coverage. After generating the HTML report, you can:

1. Open it directly using the `--open` option with the Artisan command
2. Navigate to `coverage/html/index.html` in your browser

### Text Report

The text report provides a simple, console-based overview of your code coverage. It's displayed directly in the console after running the command.

### Clover XML Report

The Clover XML report is primarily used for integration with CI/CD systems and code coverage services like Codecov. It's generated at `coverage/clover.xml`.

## Code Coverage in GitHub Actions

Code coverage reports are automatically generated and uploaded to Codecov during GitHub Actions CI runs. This allows you to:

1. See code coverage trends over time
2. View code coverage directly in pull requests
3. Identify areas of your codebase that need more tests

## Tips for Improving Code Coverage

1. Focus on testing business logic and complex code paths
2. Use data providers to test multiple scenarios with a single test
3. Don't chase 100% coverage at the expense of meaningful tests
4. Consider using mutation testing to assess the quality of your tests
