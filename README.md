# Solar

This is a **personal project** to experiment with Solar panel APIs for my home solar setup. This is a very specific
project, it requires a **Solis inverter** and energy supplied by **Octopus energy**.

- [Solcast](https://docs.solcast.com.au/) estimate forecast based on cloud cover
- [Agile import and export tariff](https://www.guylipman.com/octopus/api_guide.html) also
  see [Agile costs](https://developer.octopus.energy/docs/api/)
- [Historic usage](https://octopus.energy/dashboard/new/accounts/personal-details/api-access)
- Import downloadable data from Solis inverter Excel (xls) file
- Calculate cost for the previous month
- Calculate rolling average usage per 30 minutes
- Forecast battery and solar usage with cost(s)
- Battery charging strategy

## Bug fix

The strategy chart currently has a hard coded minimum of 0, if any costs are below 0 the minimum should be to the nearest -5 see Agile cost chart on the dashboard for an example 

## Requirements

This is a Laravel 11 project. The requirements are the same as a
new [Laravel 11 project](https://laravel.com/docs/11.x/installation).

- [PHP 8.2+](https://www.php.net/downloads.php)
- [Composer](https://getcomposer.org)

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

Install all the dependencies using composer

```sh
cd solar
composer install
```

If `composer` is not installed locally see **Docker (optional)** below.

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
OCTOPUS_EXPORT_MPAN =
OCTOPUS_IMPORT_MPAN =
OCTOPUS_EXPORT_SERIAL_NUMBER =
OCTOPUS_IMPORT_SERIAL_NUMBER =
```

Laravel 11 can use many databases, by default the database is **sqlite**, update the **.env** file as required, for
**MySQL** uncomment the keys and enter your settings

```ini
DB_HOST = 127.0.0.1
DB_PORT = 3306
DB_DATABASE = solar
DB_USERNAME = root
DB_PASSWORD =
```

## Create the database

The **sqlite** database will need to be manually created e.g.

```shell
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

Alternatively for MySQL, create an empty database **solar**, using your database user **root**:

```shell
mysql -u root
CREATE DATABASE solar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit
```

### Solcast

The app will display actual and forecast data based on your solar panel's location,
a [free account](https://toolkit.solcast.com.au/register) can be created for home user hobbyists.

Once registered enter your **API key** and **resource id** in the **.env** file.

### Octopus energy

Existing customers can generate a key: https://octopus.energy/dashboard/new/accounts/personal-details/api-access

Once generated update your API_KEY, MPANs and SERIAL_NUMBERs in the **.env** file.

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

Open the site **https://solar.test**

Login with the seeded user:

- Email: **test@example.com**
- Password: **password**

## Packages

The following packages have been used:

- [Filament admin panel](https://filamentphp.com/docs/3.x/admin/installation) - Admin panel restricted to authenticated
  users.
- [Laravel Livewire](https://laravel-livewire.com/) - Included with Filament.

### Dev Tooling

- [IDE helper](https://github.com/barryvdh/laravel-ide-helper) - helper for IDE auto-completion

### Docker (optional)

Laravel Sail has been installed as a composer package, if composer is not installed locally it can be installed using:

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
sail up -d
```


<!--
- [Easy Coding Standard (ECS)](https://github.com/symplify/easy-coding-standard) - Preferred coding standard for this
  project, set to PSR-12 plus other standards.
- [Larastan](https://github.com/nunomaduro/larastan) - Static analysis for Laravel using PhpStan.
- [Rector](https://github.com/rectorphp/rector) - Automatic code update - set to Laravel 10 and PHPUnit 10.
- [Parallel-Lint](https://github.com/php-parallel-lint/PHP-Parallel-Lint) - This application checks syntax of PHP files
  in parallel
- [Laravel debug bar](https://github.com/barryvdh/laravel-debugbar) - debug bar for views, shows models, db calls etc.
- [GrumPHP](https://github.com/phpro/grumphp) - pre-commit hook to run the above tools before committing code
-->

## Contributing

This is a **personal project**. Contributions are **not** required. Anyone interested in developing this project are
welcome to fork or clone for your own use.

## Credits

- [Michael Pritchard \(AKA Pen-y-Fan\)](https://github.com/pen-y-fan) original project

## License

MIT License (MIT). Please see [License File](LICENSE.md) for more information.
