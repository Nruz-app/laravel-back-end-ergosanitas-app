<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Services\CertificadoService;

/*** NOTA IMPORTANTE : Agregar la clase Provedir al archivo bootstrap/providers.php ***/

class CertificadoProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(CertificadoService::class, function ($app) {
            return new CertificadoService();
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
