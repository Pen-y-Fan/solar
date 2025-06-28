<?php

declare(strict_types=1);

namespace App\Domain\Energy\Providers;

use App\Domain\Energy\Repositories\EloquentInverterRepository;
use App\Domain\Energy\Repositories\InverterRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class EnergyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(InverterRepositoryInterface::class, EloquentInverterRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
