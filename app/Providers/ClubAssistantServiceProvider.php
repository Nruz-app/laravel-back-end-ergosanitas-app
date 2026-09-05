<?php

namespace App\Providers;

use App\Services\ClubAssistantService;
use Illuminate\Support\ServiceProvider;

class ClubAssistantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(ClubAssistantService::class, function ($app) {
            return new ClubAssistantService;
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
