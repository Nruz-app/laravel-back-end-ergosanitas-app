<?php

namespace App\Providers;

use App\Services\HomeImagenService;
use Illuminate\Support\ServiceProvider;

class HomeImagenServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(HomeImagenService::class, function ($app) {
            return new HomeImagenService;
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
