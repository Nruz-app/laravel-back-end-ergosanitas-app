<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ElectroCardiogramaService;


/*** NOTA IMPORTANTE : Agregar la clase Provedir al archivo bootstrap/providers.php ***/

class ElectroCardiogramaProvider extends ServiceProvider {

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(ElectroCardiogramaService::class, function ($app) {
            return new ElectroCardiogramaService();
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
