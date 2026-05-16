<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CertificadoService;
use App\Services\EstadisticasService;

class CertificadoProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CertificadoService::class, function ($app) {

            return new CertificadoService(
                $app->make(EstadisticasService::class)
            );

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
