<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\UserMetadataService;

class UserMetadataProvider extends ServiceProvider
{
     /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(UserMetadataService::class, function ($app) {
            return new UserMetadataService();
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
