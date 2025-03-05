<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Services\ChequeoCardiovascularService;

class ChequeoCardiovascularProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(ChequeoCardiovascularService::class, function ($app) {
            return new ChequeoCardiovascularService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

}
