<?php

namespace App\Providers;

use App\Services\CartFlow\CatalogoService;
use App\Services\ChatComercialService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * SPEC 06 — Chat comercial «Ergosanito».
 *
 * Registra el servicio como singleton y el limitador con nombre «chat-comercial» que usa la ruta
 * pública (primer throttle del repo). El limitador es por IP solo como protección de costo; la
 * memoria del chat es por sessionId, no por IP.
 */
class ChatComercialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatComercialService::class, fn ($app) => new ChatComercialService(
            $app->make(CatalogoService::class)
        ));
    }

    public function boot(): void
    {
        RateLimiter::for('chat-comercial', function (Request $request) {
            $respuesta429 = fn (Request $request, array $headers) => response()->json([
                'message' => 'Estás enviando muchos mensajes seguidos. Espera un momento y vuelve a intentarlo.',
            ], 429, $headers);

            return [
                Limit::perMinute(20)->by('chat-comercial:min:'.$request->ip())->response($respuesta429),
                Limit::perDay(200)->by('chat-comercial:dia:'.$request->ip())->response($respuesta429),
            ];
        });
    }
}
