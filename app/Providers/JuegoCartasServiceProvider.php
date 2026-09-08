<?php

namespace App\Providers;

use App\Services\JuegoCartasService;
use Illuminate\Support\ServiceProvider;

class JuegoCartasServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(JuegoCartasService::class, function ($app) {
            return new JuegoCartasService;
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
