# Laravel 12 Upgrade Tasks

This document outlines the tasks required to upgrade the Solar project from Laravel 11 to Laravel 12. Each task should be checked off as it is completed.

Run PHPUnit tests, PHPStan static analysis and PHP_CodeSniffer code quality. All
tests, static analysis and code quality should be good before marking a task as complete.

## High Impact Changes

1. [x] Update Dependencies
   - [x] Update `laravel/framework` to `^12.0` in composer.json
   - [x] Run `composer update` to install the new version and update dependencies

## Medium Impact Changes

2. [x] Review Carbon Usage
   - [x] Ensure all Carbon usage is compatible with Carbon 3.x (Laravel 12 removes support for Carbon 2.x)
   - [x] Test all date/time functionality after upgrading

3. [x] Review Container Dependency Resolution
   - [x] Check the Email value object and other classes with nullable class type parameters with default values
   - [x] Update code that relies on the container to resolve class instances with default values
   - [x] Example: The Email class constructor has a nullable CarbonImmutable parameter with a default value of null:
     ```php
     public function __construct(
         public readonly string $address,
         public readonly ?CarbonImmutable $verifiedAt = null
     ) {
         // ...
     }
     ```
     In Laravel 11, resolving this class through the container would inject a CarbonImmutable instance.
     In Laravel 12, it will respect the default value and set $verifiedAt to null.

## Low Impact Changes

4. [x] Review Filesystem Configuration
   - [x] Verify that the 'local' disk is explicitly defined in config/filesystems.php (it is currently defined)
   - [x] Note: In Laravel 12, if the 'local' disk is not explicitly defined, it defaults to storage/app/private instead of storage/app

5. [x] Review Other Potential Impact Areas
   - [x] Check for any usage of DatabaseTokenRepository (constructor now expects $expires in seconds, not minutes)
   - [x] Check for any usage of Blueprint constructor (signature has changed)

## Post-Upgrade Tasks

6. [x] Run Tests
   - [x] Run all tests to ensure the application works correctly with Laravel 12
   - [x] Fix any failing tests

7. [x] Review Laravel 12 Changes
   - [x] Review the changes in the laravel/laravel GitHub repository
   - [x] Update configuration files and other files as needed
