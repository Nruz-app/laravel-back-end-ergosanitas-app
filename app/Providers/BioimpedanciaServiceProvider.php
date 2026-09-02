<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\BioimpedanciaService;

class BioimpedanciaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(BioimpedanciaService::class, function ($app) {
            return new BioimpedanciaService();
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
