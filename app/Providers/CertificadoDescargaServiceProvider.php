<?php

namespace App\Providers;

use App\Services\BioimpedanciaService;
use App\Services\CertificadoDescargaService;
use App\Services\ChequeoCardiovascularPDFService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * SPEC 07 — Descarga de certificados por RUT.
 *
 * Registra el servicio como singleton y el limitador «certificados» de las rutas públicas: la
 * consulta por RUT permite enumerar pacientes, así que se limita por IP. No se amplía
 * CertificadoProvider porque su servicio factura; este dominio solo lee.
 */
class CertificadoDescargaServiceProvider extends ServiceProvider
{
    /** Consultas por minuto y por IP. */
    public const CONSULTAS_POR_MINUTO = 10;

    /** Descargas firmadas por minuto y por IP. */
    public const DESCARGAS_POR_MINUTO = 30;

    public function register(): void
    {
        $this->app->singleton(CertificadoDescargaService::class, fn ($app) => new CertificadoDescargaService(
            $app->make(ChequeoCardiovascularPDFService::class),
            $app->make(BioimpedanciaService::class),
        ));
    }

    public function boot(): void
    {
        RateLimiter::for('certificados', function (Request $request) {
            $respuesta429 = fn (Request $request, array $headers) => response()->json([
                'success' => false,
                'message' => 'Demasiadas consultas seguidas. Espera un momento y vuelve a intentarlo.',
                'data' => null,
            ], 429, $headers);

            if ($request->routeIs('CertificadosConsulta')) {
                return Limit::perMinute(self::CONSULTAS_POR_MINUTO)
                    ->by('certificados:consulta:'.$request->ip())
                    ->response($respuesta429);
            }

            return Limit::perMinute(self::DESCARGAS_POR_MINUTO)
                ->by('certificados:descarga:'.$request->ip())
                ->response($respuesta429);
        });
    }
}
