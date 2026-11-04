<?php

namespace App\Http\Controllers\CartFlow;

use App\Http\Controllers\Controller;
use App\Models\CartFlow\Carrito;
use App\Models\CartFlow\Pedido;
use App\Services\CartFlow\CarritoService;
use App\Services\CartFlow\CartFlowException;
use App\Services\CartFlow\CheckoutService;
use App\Services\CartFlow\PedidoService;
use Illuminate\Http\Request;

/**
 * SPEC 04 — Carro anonimo por uuid: direccion, plan, pacientes, lineas y checkout.
 * Toda respuesta de escritura devuelve el carro completo recalculado.
 */
class CarritoController extends Controller
{
    use RespuestasCartFlow;

    public function __construct(
        protected CarritoService $carritos,
        protected CheckoutService $checkout,
        protected PedidoService $pedidos,
    ) {}

    public function Store(Request $request)
    {
        return $this->responder('Error creando el carro', function () use ($request) {
            $datos = $this->validar($request->all(), $this->reglasCarrito());

            $carrito = $this->carritos->crear($datos);

            return $this->ok($this->carritos->serializar($carrito->fresh()), 'Carro creado', 201);
        });
    }

    public function Show(string $uuid)
    {
        return $this->responder('Error buscando el carro', function () use ($uuid) {
            $carrito = $this->carritos->buscar($uuid);

            if (! $carrito) {
                throw CartFlowException::noEncontrado('Carro no encontrado');
            }

            return $this->ok($this->carritos->serializar($carrito));
        });
    }

    public function Update(Request $request, string $uuid)
    {
        return $this->responder('Error actualizando el carro', function () use ($request, $uuid) {
            $carrito = $this->carritos->abierto($uuid);
            $datos = $this->validar($request->all(), $this->reglasCarrito());

            $this->carritos->actualizar($carrito, $datos);

            return $this->ok($this->carritos->serializar($carrito->fresh()), 'Carro actualizado');
        });
    }

    public function PacienteStore(Request $request, string $uuid)
    {
        return $this->responder('Error agregando el paciente', function () use ($request, $uuid) {
            $carrito = $this->carritos->abierto($uuid);
            $datos = $this->validar($request->all(), $this->reglasPaciente());

            if ($carrito->pacientes()->count() >= 10) {
                throw CartFlowException::validacion('Máximo 10 pacientes por dirección.', ['pacientes' => ['maximo']]);
            }

            $this->carritos->agregarPaciente($carrito, $datos);

            return $this->ok($this->carritos->serializar($carrito->fresh()), 'Paciente agregado', 201);
        });
    }

    public function PacienteUpdate(Request $request, string $uuid, int $id)
    {
        return $this->responder('Error actualizando el paciente', function () use ($request, $uuid, $id) {
            $carrito = $this->carritos->abierto($uuid);
            $paciente = $this->carritos->paciente($carrito, $id);
            $datos = $this->validar($request->all(), $this->reglasPaciente());

            $this->carritos->actualizarPaciente($paciente, $datos);

            return $this->ok($this->carritos->serializar($carrito->fresh()), 'Paciente actualizado');
        });
    }

    public function PacienteQuitar(string $uuid, int $id)
    {
        return $this->responder('Error quitando el paciente', function () use ($uuid, $id) {
            $carrito = $this->carritos->abierto($uuid);
            $this->carritos->quitarPaciente($carrito, $this->carritos->paciente($carrito, $id));

            return $this->ok($this->carritos->serializar($carrito->fresh()), 'Paciente quitado del carro');
        });
    }

    public function LineaStore(Request $request, string $uuid)
    {
        return $this->responder('Error agregando el producto', function () use ($request, $uuid) {
            $carrito = $this->carritos->abierto($uuid);
            $datos = $this->validar($request->all(), [
                'paciente_id' => ['required', 'integer'],
                'sku' => ['required', 'string', 'max:60'],
                'cantidad' => ['nullable', 'integer', 'min:1', 'max:20'],
            ]);

            $this->carritos->agregarLinea($carrito, (int) $datos['paciente_id'], $datos['sku'], (int) ($datos['cantidad'] ?? 1));

            return $this->ok($this->carritos->serializar($carrito->fresh()), 'Producto agregado al carro', 201);
        });
    }

    public function LineaUpdate(Request $request, string $uuid, int $id)
    {
        return $this->responder('Error actualizando el producto', function () use ($request, $uuid, $id) {
            $carrito = $this->carritos->abierto($uuid);
            $linea = $this->carritos->linea($carrito, $id);
            $datos = $this->validar($request->all(), ['cantidad' => ['required', 'integer', 'min:1', 'max:20']]);

            $this->carritos->actualizarLinea($linea, (int) $datos['cantidad']);

            return $this->ok($this->carritos->serializar($carrito->fresh()), 'Cantidad actualizada');
        });
    }

    public function LineaQuitar(string $uuid, int $id)
    {
        return $this->responder('Error quitando el producto', function () use ($uuid, $id) {
            $carrito = $this->carritos->abierto($uuid);
            $this->carritos->quitarLinea($this->carritos->linea($carrito, $id));

            return $this->ok($this->carritos->serializar($carrito->fresh()), 'Producto quitado del carro');
        });
    }

    /**
     * Multipart: fecha, bloque_id, medio_pago, contacto_*, acepta_terminos y ordenes[{paciente_id}].
     * 201 con el pedido y su token_consulta (el cliente lo necesita para consultar o pagar).
     */
    public function Checkout(Request $request, string $uuid)
    {
        return $this->responder('Error confirmando el pedido', function () use ($request, $uuid) {
            $carrito = $this->carritos->abierto($uuid);

            $datos = $this->validar($request->all(), [
                'fecha' => ['required', 'date_format:Y-m-d'],
                'bloque_id' => ['required', 'integer'],
                'medio_pago' => ['required', 'in:'.implode(',', Pedido::MEDIOS_PAGO)],
                'contacto_nombre' => ['required', 'string', 'max:250'],
                'contacto_email' => ['required', 'email', 'max:150'],
                'contacto_telefono' => ['required', 'string', 'max:20'],
                'direccion' => ['nullable', 'string', 'max:255'],
                'referencia' => ['nullable', 'string', 'max:255'],
                'observaciones' => ['nullable', 'string', 'max:1000'],
                'acepta_terminos' => ['required', 'accepted'],
                'ordenes' => ['nullable', 'array'],
                'ordenes.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);

            $ordenes = [];

            foreach ((array) $request->file('ordenes', []) as $pacienteId => $archivo) {
                $ordenes[(int) $pacienteId] = $archivo;
            }

            $resultado = $this->checkout->checkout($carrito, $datos, $ordenes);

            return $this->ok(
                $this->pedidos->serializar($resultado['pedido']->fresh()) + ['token_consulta' => $resultado['token_consulta']],
                'Pedido creado',
                201
            );
        });
    }

    private function reglasCarrito(): array
    {
        return [
            'comuna_id' => ['nullable', 'integer', 'exists:cf_comunas,id'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'plan' => ['nullable', 'in:'.implode(',', Carrito::PLANES)],
        ];
    }

    private function reglasPaciente(): array
    {
        return [
            'nombre' => ['nullable', 'string', 'max:250'],
            'rut' => ['nullable', 'string', 'max:20', 'regex:/^\d{1,2}\.?\d{3}\.?\d{3}-[0-9kK]$/'],
            'fecha_nac' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sexo' => ['nullable', 'in:F,M,f,m'],
            'prevision' => ['nullable', 'string', 'max:50'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'consentimiento' => ['nullable', 'boolean'],
            'apoderado_nombre' => ['nullable', 'string', 'max:250'],
            'apoderado_rut' => ['nullable', 'string', 'max:20'],
            'consentimiento_apoderado' => ['nullable', 'boolean'],
        ];
    }
}
