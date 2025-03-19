<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Services\ChequeoCardiovascularPDFService;

/*** NOTA IMPORTANTE : Agregar la clase Provedir al archivo bootstrap/providers.php ***/


class ChequeoCardiovascularPDFProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(ChequeoCardiovascularPDFService::class, function ($app) {
            return new ChequeoCardiovascularPDFService();
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
