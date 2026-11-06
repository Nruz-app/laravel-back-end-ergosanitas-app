<?php

namespace Tests\Feature\CartFlow;

use App\Models\CartFlow\Comuna;
use App\Models\CartFlow\Cotizacion;
use App\Models\CartFlow\Pedido;
use App\Models\CartFlow\PedidoHistorial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * SPEC 05 — Mantenedor del carro para el Administrador. Datos ficticios, SQLite en memoria.
 */
class AdminCartFlowTest extends CartFlowTestCase
{
    private const FECHA = '2026-11-05';

    private string $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearAdministrador();
    }

    public function test_sin_administrador_responde_403(): void
    {
        foreach (['resumen', 'pedidos', 'cotizaciones', 'productos'] as $recurso) {
            $this->getJson("/api/cart-flow/admin/{$recurso}?user_email=nadie@example.com")->assertForbidden();
        }
    }

    public function test_lista_filtra_y_busca_por_rut(): void
    {
        $this->pedido('transferencia', '11111111-1');
        $this->pedido('efectivo', '22222222-2');

        $todos = $this->getJson("/api/cart-flow/admin/pedidos?user_email={$this->admin}")->assertOk();
        $this->assertSame(2, $todos->json('data.total'));

        $porRut = $this->getJson("/api/cart-flow/admin/pedidos?user_email={$this->admin}&q=22.222.222-2")->assertOk();
        $this->assertSame(1, $porRut->json('data.total'));
        $this->assertSame('efectivo', $porRut->json('data.items.0.medio_pago'));

        $porMedio = $this->getJson("/api/cart-flow/admin/pedidos?user_email={$this->admin}&medio_pago=transferencia&estado=pendiente_pago")->assertOk();
        $this->assertSame(1, $porMedio->json('data.total'));
    }

    public function test_confirmar_pago_de_transferencia_deja_historial(): void
    {
        $numero = $this->pedido('transferencia');

        $detalle = $this->getJson("/api/cart-flow/admin/pedidos/{$numero}?user_email={$this->admin}")->assertOk();
        $this->assertSame(['confirmar_pago', 'anular'], $detalle->json('data.acciones'));
        $this->assertSame('11111111-1', $detalle->json('data.pacientes.0.rut')); // el Administrador sí ve el RUT

        $this->putJson("/api/cart-flow/admin/pedidos/{$numero}/estado", ['user_email' => $this->admin, 'accion' => 'confirmar_pago'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'pagado')
            ->assertJsonPath('data.estado_pago', 'pagado')
            ->assertJsonPath('data.pagos.0.estado', 'aprobado')
            ->assertJsonCount(2, 'data.historial');

        $this->putJson("/api/cart-flow/admin/pedidos/{$numero}/estado", ['user_email' => $this->admin, 'accion' => 'confirmar'])
            ->assertOk()->assertJsonPath('data.estado', 'confirmado');

        $this->putJson("/api/cart-flow/admin/pedidos/{$numero}/estado", ['user_email' => $this->admin, 'accion' => 'atender'])
            ->assertOk()->assertJsonPath('data.estado', 'atendido')->assertJsonPath('data.acciones', []);

        $this->assertSame($this->admin, PedidoHistorial::latest('id')->value('user_email'));
    }

    public function test_confirmar_pago_de_webpay_responde_409(): void
    {
        $numero = $this->pedido('webpay');

        $this->putJson("/api/cart-flow/admin/pedidos/{$numero}/estado", ['user_email' => $this->admin, 'accion' => 'confirmar_pago'])
            ->assertStatus(409)->assertJsonPath('error', 'transicion_invalida');
    }

    public function test_anular_exige_motivo_y_libera_el_cupo(): void
    {
        $numero = $this->pedido('transferencia');
        $pedido = Pedido::where('numero', $numero)->first();
        $url = "/api/cart-flow/admin/pedidos/{$numero}/estado";

        $this->putJson($url, ['user_email' => $this->admin, 'accion' => 'anular'])->assertStatus(422);

        $this->putJson($url, ['user_email' => $this->admin, 'accion' => 'anular', 'motivo' => 'Cliente pidió cancelar'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'anulado')
            ->assertJsonPath('data.anulado_motivo', 'Cliente pidió cancelar');

        $this->assertSame(0, app(\App\Services\CartFlow\AgendaService::class)->usados($pedido->bloque_id, self::FECHA));
        $this->assertSame(1, Pedido::count()); // anular no borra
    }

    public function test_orden_medica_por_enlace_firmado(): void
    {
        Storage::fake('local');

        $numero = $this->pedido('transferencia', '11111111-1', 'LAB-302048', true);
        $orden = $this->getJson("/api/cart-flow/admin/pedidos/{$numero}?user_email={$this->admin}")
            ->json('data.pacientes.0.ordenes_medicas.0.id');

        $enlace = $this->postJson("/api/cart-flow/admin/ordenes/{$orden}/enlace", ['user_email' => $this->admin])
            ->assertOk()->json('data.url');

        $this->get($enlace)->assertOk()->assertHeader('content-disposition');
        $this->get($enlace.'x')->assertForbidden();
        $this->get("/api/cart-flow/admin/ordenes/{$orden}/descarga")->assertForbidden();
    }

    public function test_resumen_y_cotizaciones_y_catalogo(): void
    {
        $this->pedido('transferencia');
        $this->pedido('efectivo');

        $this->getJson("/api/cart-flow/admin/resumen?user_email={$this->admin}")
            ->assertOk()
            ->assertJsonPath('data.pedidos_hoy', 2)
            ->assertJsonPath('data.pendientes_pago.cantidad', 2)
            ->assertJsonPath('data.por_estado.pendiente_pago', 2);

        $cotizacion = Cotizacion::create([
            'comuna' => 'La Serena', 'servicio' => 'Operativo', 'contacto_nombre' => 'Contacto Ficticio',
            'contacto_email' => 'contacto@example.com', 'contacto_telefono' => '+56900000000', 'estado' => 'nueva',
        ]);

        $this->getJson("/api/cart-flow/admin/cotizaciones?user_email={$this->admin}&estado=nueva")->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/cart-flow/admin/cotizaciones/{$cotizacion->id}/estado", ['user_email' => $this->admin, 'estado' => 'contactada'])
            ->assertOk()->assertJsonPath('data.estado', 'contactada');

        // El catálogo del mantenedor incluye los ocultos y nunca los costos.
        $productos = $this->getJson("/api/cart-flow/admin/productos?user_email={$this->admin}")->assertOk();
        $this->assertCount(245, $productos->json('data'));
        $this->assertStringNotContainsString('costo_domilab', $productos->getContent());
        $this->getJson("/api/cart-flow/admin/productos?user_email={$this->admin}&visible=0")->assertOk();
    }

    /** Crea un pedido ficticio por el flujo público y devuelve su número. */
    private function pedido(string $medio, string $rut = '11111111-1', string $sku = 'PERF-BASICO', bool $conOrden = false): string
    {
        $comuna = Comuna::where('comuna', 'Maipú')->value('id');
        $carrito = $this->postJson('/api/cart-flow/carritos', ['comuna_id' => $comuna, 'direccion' => 'Calle Ficticia 123'])->json('data');
        $uuid = $carrito['uuid'];
        $paciente = $carrito['pacientes'][0]['id'];

        $this->postJson("/api/cart-flow/carritos/{$uuid}/lineas", ['paciente_id' => $paciente, 'sku' => $sku])->assertCreated();
        $this->putJson("/api/cart-flow/carritos/{$uuid}/pacientes/{$paciente}", [
            'nombre' => 'Paciente Ficticio', 'rut' => $rut, 'fecha_nac' => '1980-01-01', 'sexo' => 'M', 'consentimiento' => true,
        ])->assertOk();

        $bloque = $this->getJson("/api/cart-flow/agenda/bloques?carrito={$uuid}")->json('data.dias.0.bloques.0.id');

        $datos = [
            'fecha' => self::FECHA, 'bloque_id' => $bloque, 'medio_pago' => $medio,
            'contacto_nombre' => 'Contacto Ficticio', 'contacto_email' => 'contacto@example.com',
            'contacto_telefono' => '+56900000000', 'acepta_terminos' => '1',
        ];

        if ($conOrden) {
            $datos['ordenes'] = [$paciente => UploadedFile::fake()->create('orden.pdf', 50, 'application/pdf')];
        }

        return $this->post("/api/cart-flow/carritos/{$uuid}/checkout", $datos, ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.numero');
    }
}
