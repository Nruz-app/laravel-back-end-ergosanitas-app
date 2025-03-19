<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Services\EstadisticasService;

/*** NOTA IMPORTANTE : Agregar la clase Provedir al archivo bootstrap/providers.php ***/

class EstadisticasProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(EstadisticasService::class, function ($app) {
            return new EstadisticasService();
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
