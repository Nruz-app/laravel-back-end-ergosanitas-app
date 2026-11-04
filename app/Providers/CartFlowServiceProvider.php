<?php

namespace App\Providers;

use App\Console\Commands\CartFlowImportarCatalogo;
use App\Services\CartFlow\AgendaService;
use App\Services\CartFlow\CalculadoraCarroService;
use App\Services\CartFlow\CarritoService;
use App\Services\CartFlow\CatalogoCsv;
use App\Services\CartFlow\CatalogoService;
use App\Services\CartFlow\CheckoutService;
use App\Services\CartFlow\ImportadorCatalogoService;
use App\Services\CartFlow\PedidoService;
use App\Services\CartFlow\WebPayCartFlowService;
use Illuminate\Support\ServiceProvider;

/**
 * SPEC 04 — Servicios del carro (servicio-cart-flow), todos singleton.
 */
class CartFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CalculadoraCarroService::class, fn () => new CalculadoraCarroService);
        $this->app->singleton(CatalogoCsv::class, fn () => new CatalogoCsv);
        $this->app->singleton(CatalogoService::class, fn () => new CatalogoService);
        $this->app->singleton(AgendaService::class, fn () => new AgendaService);
        $this->app->singleton(PedidoService::class, fn () => new PedidoService);
        $this->app->singleton(WebPayCartFlowService::class, fn () => new WebPayCartFlowService);

        $this->app->singleton(ImportadorCatalogoService::class, fn ($app) => new ImportadorCatalogoService(
            $app->make(CatalogoCsv::class)
        ));

        $this->app->singleton(CarritoService::class, fn ($app) => new CarritoService(
            $app->make(CalculadoraCarroService::class),
            $app->make(CatalogoService::class),
        ));

        $this->app->singleton(CheckoutService::class, fn ($app) => new CheckoutService(
            $app->make(CarritoService::class),
            $app->make(AgendaService::class),
        ));
    }

    /**
     * bootstrap/app.php pasa routes/console.php a withRouting(), y con eso Laravel ya no
     * auto-descubre app/Console/Commands: el comando se registra aqui.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CartFlowImportarCatalogo::class]);
        }
    }
}
