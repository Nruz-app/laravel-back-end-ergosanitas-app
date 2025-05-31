<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\IncidentesService;

class IncidenciaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(IncidentesService::class, function ($app) {
            return new IncidentesService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
