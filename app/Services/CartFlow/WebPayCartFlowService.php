<?php

namespace App\Services\CartFlow;

use App\Models\CartFlow\Pago;
use App\Models\CartFlow\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SPEC 04 — Webpay Plus para pedidos del carro, por HTTP (mismas credenciales que WebPayController,
 * leidas con config() y no con env()).
 *
 * A diferencia del flujo anterior:
 * - el monto sale de cf_pedidos.total, nunca del cliente;
 * - buy_order es unico por intento ("CF{pedido}-{pago}");
 * - el commit solo marca pagado si status = AUTHORIZED, response_code = 0 y el monto coincide;
 * - el pago se enlaza al pedido por id, no por RUT.
 */
class WebPayCartFlowService
{
    /** @return array{token:string, url:string} */
    public function iniciar(Pedido $pedido): array
    {
        if ($pedido->medio_pago !== 'webpay' || $pedido->estado !== 'pendiente_pago') {
            throw CartFlowException::conflicto('Este pedido no admite pago con Webpay.', 'pago_no_permitido');
        }

        $config = config('services.cart_flow');

        $faltan = array_keys(array_filter([
            'WEBPAY_URL' => empty($config['webpay_url']),
            'WEBPAY_ID' => empty($config['webpay_id']),
            'WEBPAY_SECRET' => empty($config['webpay_secret']),
        ]));

        if ($faltan !== []) {
            throw new CartFlowException('El pago con Webpay no está configurado (falta '.implode(', ', $faltan).').', 503, 'webpay_no_configurado');
        }

        // Sin WEBPAY_CART_FLOW_RETURN, el retorno es la ruta propia en el mismo host de la peticion.
        $retorno = $config['webpay_return'] ?: route('CartFlowWebPayRetorno');

        $pago = Pago::create([
            'pedido_id' => $pedido->id,
            'medio' => 'webpay',
            'monto' => $pedido->total,
            'estado' => 'iniciado',
        ]);

        $buyOrder = substr("CF{$pedido->id}-{$pago->id}", 0, 26);
        $pago->update(['buy_order' => $buyOrder]);

        $respuesta = Http::withHeaders($this->cabeceras($config))
            ->timeout(20)
            ->post($config['webpay_url'], [
                'buy_order' => $buyOrder,
                'session_id' => $pedido->numero,
                'amount' => $pedido->total,
                'return_url' => $retorno,
            ]);

        Log::info('cart-flow webpay create', [
            'pedido' => $pedido->numero,
            'buy_order' => $buyOrder,
            'ambiente' => str_contains((string) $config['webpay_url'], 'webpay3gint') ? 'int' : 'prod',
            'status' => $respuesta->status(),
        ]);

        if (! $respuesta->successful() || ! $respuesta->json('token')) {
            $pago->update(['estado' => 'error', 'payload' => ['http' => $respuesta->status(), 'body' => $respuesta->json()]]);
            Log::warning('cart-flow webpay create', ['pedido' => $pedido->numero, 'status' => $respuesta->status()]);

            throw new CartFlowException('No pudimos iniciar el pago con Webpay. Intenta nuevamente.', 502, 'webpay_error');
        }

        $pago->update(['token' => $respuesta->json('token')]);

        return ['token' => $respuesta->json('token'), 'url' => $respuesta->json('url')];
    }

    /**
     * Retorno de Transbank. Devuelve la URL del front a la que hay que redirigir.
     *
     * Con token_ws: se confirma (commit). Con TBK_TOKEN sin token_ws: el cliente anulo o expiro.
     */
    public function retorno(?string $tokenWs, ?string $tbkOrden): string
    {
        if ($tokenWs) {
            $pago = Pago::where('token', $tokenWs)->with('pedido')->first();

            if (! $pago) {
                return $this->urlFront(null, 'error');
            }

            if ($pago->estado !== 'iniciado') {
                return $this->urlFront($pago->pedido, $pago->estado === 'aprobado' ? 'aprobado' : 'rechazado');
            }

            return $this->urlFront($pago->pedido, $this->confirmar($pago) ? 'aprobado' : 'rechazado');
        }

        if ($tbkOrden) {
            $pago = Pago::where('buy_order', $tbkOrden)->with('pedido')->first();

            if ($pago && $pago->estado === 'iniciado') {
                $pago->update(['estado' => 'rechazado', 'status' => 'ABORTED']);
            }

            return $this->urlFront($pago?->pedido, 'cancelado');
        }

        return $this->urlFront(null, 'error');
    }

    /** Commit en Transbank y validacion del resultado. */
    public function confirmar(Pago $pago): bool
    {
        $config = config('services.cart_flow');

        try {
            $respuesta = Http::withHeaders($this->cabeceras($config))
                ->timeout(20)
                ->put(rtrim($config['webpay_url'], '/').'/'.$pago->token, []);
        } catch (\Throwable $e) {
            Log::error('cart-flow webpay commit', ['pago' => $pago->id, 'error' => $e->getMessage()]);
            $pago->update(['estado' => 'error']);

            return false;
        }

        $body = $respuesta->json() ?? [];
        $pedido = $pago->pedido;

        $aprobado = $respuesta->successful()
            && ($body['status'] ?? null) === 'AUTHORIZED'
            && (int) ($body['response_code'] ?? -1) === 0
            && (int) ($body['amount'] ?? 0) === (int) $pago->monto
            && (int) $pago->monto === (int) $pedido->total
            && ($body['buy_order'] ?? null) === $pago->buy_order;

        DB::transaction(function () use ($pago, $pedido, $body, $aprobado) {
            $pago->update([
                'status' => $body['status'] ?? null,
                'response_code' => isset($body['response_code']) ? (int) $body['response_code'] : null,
                'authorization_code' => $body['authorization_code'] ?? null,
                'payload' => $body,
                'estado' => $aprobado ? 'aprobado' : 'rechazado',
            ]);

            if ($aprobado) {
                $pedido->update(['estado' => 'pagado', 'estado_pago' => 'pagado']);
            } else {
                // El pedido sigue pendiente_pago: el cliente puede reintentar.
                $pedido->update(['estado_pago' => 'rechazado']);
            }
        });

        return $aprobado;
    }

    private function cabeceras(array $config): array
    {
        return [
            'Content-Type' => 'application/json',
            'Tbk-Api-Key-Id' => (string) $config['webpay_id'],
            'Tbk-Api-Key-Secret' => (string) $config['webpay_secret'],
        ];
    }

    private function urlFront(?Pedido $pedido, string $resultado): string
    {
        $base = rtrim((string) config('services.cart_flow.front_url'), '/').'/servicios/resultado';
        $query = ['pago' => $resultado];

        if ($pedido) {
            $query['pedido'] = $pedido->numero;
            $query['t'] = $pedido->token_consulta;
        }

        return $base.'?'.http_build_query($query);
    }
}
