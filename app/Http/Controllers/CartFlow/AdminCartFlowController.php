<?php

namespace App\Http\Controllers\CartFlow;

use App\Http\Controllers\Controller;
use App\Models\CartFlow\Pedido;
use App\Services\CartFlow\AdminPedidoService;
use App\Services\CartFlow\CartFlowException;
use App\Services\CartFlow\CatalogoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * SPEC 05 — Mantenedor «Carrito Compra» del Administrador.
 *
 * Todas las acciones exigen un user_email de perfil Administrador (mismo control que la Spec 03;
 * no es autenticación). La descarga de la orden médica va por URL firmada y temporal.
 */
class AdminCartFlowController extends Controller
{
    use RespuestasCartFlow;

    public function __construct(
        protected AdminPedidoService $admin,
        protected CatalogoService $catalogo,
    ) {}

    public function Resumen(Request $request)
    {
        return $this->responder('Error calculando el resumen', function () use ($request) {
            $this->exigirAdministrador($request);

            return $this->ok($this->admin->resumen());
        });
    }

    public function Pedidos(Request $request)
    {
        return $this->responder('Error listando los pedidos', function () use ($request) {
            $this->exigirAdministrador($request);

            $filtros = $this->validar($request->query(), [
                'user_email' => ['required', 'email'],
                'estado' => ['nullable', 'in:'.implode(',', Pedido::ESTADOS)],
                'estado_pago' => ['nullable', 'in:pendiente,pagado,rechazado'],
                'medio_pago' => ['nullable', 'in:'.implode(',', Pedido::MEDIOS_PAGO)],
                'desde' => ['nullable', 'date_format:Y-m-d'],
                'hasta' => ['nullable', 'date_format:Y-m-d'],
                'por' => ['nullable', 'in:visita,creacion'],
                'q' => ['nullable', 'string', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            return $this->ok($this->admin->listar($filtros));
        });
    }

    public function Pedido(Request $request, string $numero)
    {
        return $this->responder('Error buscando el pedido', function () use ($request, $numero) {
            $this->exigirAdministrador($request);

            return $this->ok($this->admin->detalle($this->admin->buscar($numero)));
        });
    }

    public function PedidoEstado(Request $request, string $numero)
    {
        return $this->responder('Error cambiando el estado del pedido', function () use ($request, $numero) {
            $email = $this->exigirAdministrador($request);

            $datos = $this->validar($request->all(), [
                'user_email' => ['required', 'email'],
                'accion' => ['required', 'in:'.implode(',', AdminPedidoService::ACCIONES)],
                'motivo' => ['nullable', 'string', 'max:500'],
            ]);

            $pedido = $this->admin->cambiarEstado($this->admin->buscar($numero), $datos['accion'], $email, $datos['motivo'] ?? null);

            return $this->ok($this->admin->detalle($pedido), 'Pedido actualizado');
        });
    }

    public function OrdenEnlace(Request $request, int $id)
    {
        return $this->responder('Error generando el enlace de la orden médica', function () use ($request, $id) {
            $this->exigirAdministrador($request);

            return $this->ok($this->admin->enlaceOrden($id));
        });
    }

    /** Ruta con middleware `signed`: sin firma válida Laravel responde 403 antes de llegar aquí. */
    public function OrdenDescarga(int $id)
    {
        $orden = $this->admin->orden($id);

        if (! $orden || ! Storage::disk('local')->exists($orden->path)) {
            abort(404);
        }

        return Storage::disk('local')->download($orden->path, $orden->nombre_original);
    }

    public function Cotizaciones(Request $request)
    {
        return $this->responder('Error listando las cotizaciones', function () use ($request) {
            $this->exigirAdministrador($request);

            $datos = $this->validar($request->query(), [
                'user_email' => ['required', 'email'],
                'estado' => ['nullable', 'in:'.implode(',', AdminPedidoService::ESTADOS_COTIZACION)],
                'q' => ['nullable', 'string', 'max:100'],
            ]);

            return $this->ok($this->admin->listarCotizaciones($datos['estado'] ?? null, $datos['q'] ?? null));
        });
    }

    public function CotizacionEstado(Request $request, int $id)
    {
        return $this->responder('Error cambiando el estado de la cotización', function () use ($request, $id) {
            $this->exigirAdministrador($request);

            $datos = $this->validar($request->all(), [
                'user_email' => ['required', 'email'],
                'estado' => ['required', 'in:'.implode(',', AdminPedidoService::ESTADOS_COTIZACION)],
            ]);

            return $this->ok($this->admin->cambiarEstadoCotizacion($id, $datos['estado']), 'Cotización actualizada');
        });
    }

    public function Productos(Request $request)
    {
        return $this->responder('Error listando el catálogo', function () use ($request) {
            $this->exigirAdministrador($request);

            $datos = $this->validar($request->query(), [
                'user_email' => ['required', 'email'],
                'q' => ['nullable', 'string', 'max:100'],
                'categoria' => ['nullable', 'string', 'max:120'],
                'visible' => ['nullable', 'in:0,1,true,false'],
            ]);

            $visible = isset($datos['visible']) ? filter_var($datos['visible'], FILTER_VALIDATE_BOOLEAN) : null;

            return $this->ok($this->admin->listarProductos($datos['q'] ?? null, $datos['categoria'] ?? null, $visible));
        });
    }

    /** user_email por body o query; 403 si no es Administrador. Devuelve el email validado. */
    private function exigirAdministrador(Request $request): string
    {
        $email = (string) $request->input('user_email', $request->query('user_email', ''));

        if (! $this->catalogo->esAdministrador($email)) {
            throw new CartFlowException('Solo un Administrador puede ver el carro de compras.', 403, 'forbidden');
        }

        return $email;
    }
}
