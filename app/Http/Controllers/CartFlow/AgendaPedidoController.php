<?php

namespace App\Http\Controllers\CartFlow;

use App\Http\Controllers\Controller;
use App\Services\CartFlow\AgendaService;
use App\Services\CartFlow\CarritoService;
use App\Services\CartFlow\CartFlowException;
use App\Services\CartFlow\PedidoService;
use App\Services\CartFlow\WebPayCartFlowService;
use Illuminate\Http\Request;

/**
 * SPEC 04 — Agenda por bloques, consulta de pedido, Webpay y solicitudes de cotizacion.
 */
class AgendaPedidoController extends Controller
{
    use RespuestasCartFlow;

    public function __construct(
        protected AgendaService $agenda,
        protected CarritoService $carritos,
        protected PedidoService $pedidos,
        protected WebPayCartFlowService $webpay,
    ) {}

    /** Bloques disponibles para el carro: filtra por su zona y por la franja de manana si la exige. */
    public function Bloques(Request $request)
    {
        return $this->responder('Error consultando la agenda', function () use ($request) {
            $datos = $this->validar($request->query(), [
                'carrito' => ['required', 'uuid'],
                'desde' => ['nullable', 'date_format:Y-m-d'],
                'hasta' => ['nullable', 'date_format:Y-m-d'],
            ]);

            $carrito = $this->carritos->abierto($datos['carrito']);
            $calculo = $this->carritos->calcular($carrito);

            if ($calculo['a_cotizar']) {
                throw CartFlowException::validacion('Esta comuna se cotiza aparte: no hay agenda en línea.', ['comuna' => ['a_cotizar']]);
            }

            [$desde, $hasta] = $this->agenda->rango($this->carritos->reglas(), $datos['desde'] ?? null, $datos['hasta'] ?? null);

            return $this->ok([
                'requiere_manana' => $calculo['requiere_manana'],
                'dias' => $this->agenda->disponibles($carrito->comuna?->zona_id, $calculo['requiere_manana'], $desde, $hasta),
            ]);
        });
    }

    public function PedidoShow(Request $request, string $numero)
    {
        return $this->responder('Error buscando el pedido', function () use ($request, $numero) {
            $pedido = $this->pedidos->buscar($numero, $request->query('t'));

            return $this->ok($this->pedidos->serializar($pedido));
        });
    }

    /** Crea la transaccion Webpay por el total del pedido. Devuelve {token, url} para el form POST. */
    public function WebPay(Request $request, string $numero)
    {
        return $this->responder('Error iniciando el pago', function () use ($request, $numero) {
            $pedido = $this->pedidos->buscar($numero, $request->input('t'));

            return $this->ok($this->webpay->iniciar($pedido), 'Transacción creada');
        });
    }

    /**
     * Retorno de Transbank (GET o POST). Nunca responde JSON: siempre redirige al front con el resultado.
     */
    public function WebPayRetorno(Request $request)
    {
        try {
            $url = $this->webpay->retorno($request->input('token_ws'), $request->input('TBK_ORDEN_COMPRA'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('cart-flow webpay retorno', ['error' => $e->getMessage()]);
            $url = rtrim((string) config('services.cart_flow.front_url'), '/').'/servicios/resultado?pago=error';
        }

        return redirect()->away($url);
    }

    public function CotizacionStore(Request $request)
    {
        return $this->responder('Error enviando la solicitud de cotización', function () use ($request) {
            $datos = $this->validar($request->all(), [
                'organizacion' => ['nullable', 'string', 'max:250'],
                'comuna' => ['required', 'string', 'max:100'],
                'personas' => ['nullable', 'integer', 'min:1', 'max:100000'],
                'servicio' => ['required', 'string', 'max:255'],
                'sku' => ['nullable', 'string', 'max:60'],
                'fecha_tentativa' => ['nullable', 'date_format:Y-m-d'],
                'contacto_nombre' => ['required', 'string', 'max:250'],
                'contacto_email' => ['required', 'email', 'max:150'],
                'contacto_telefono' => ['required', 'string', 'max:20'],
                'mensaje' => ['nullable', 'string', 'max:2000'],
            ]);

            $cotizacion = $this->pedidos->cotizar($datos);

            return $this->ok(['id' => $cotizacion->id, 'estado' => $cotizacion->estado], 'Solicitud enviada: te contactaremos pronto', 201);
        });
    }
}
