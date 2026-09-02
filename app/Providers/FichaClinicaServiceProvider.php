<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FichaClinicaService;

class FichaClinicaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
         $this->app->singleton(FichaClinicaService::class, function ($app) {
            return new FichaClinicaService();
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
