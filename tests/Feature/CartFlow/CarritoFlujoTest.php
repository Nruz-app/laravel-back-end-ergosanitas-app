<?php

namespace Tests\Feature\CartFlow;

use App\Models\CartFlow\BloqueAgenda;
use App\Models\CartFlow\Comuna;
use App\Models\CartFlow\Pedido;
use App\Models\CartFlow\Producto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * SPEC 04 — Flujo completo del carro con datos ficticios: catalogo → carro → agenda → checkout →
 * consulta → Webpay. Ningun dato es real.
 */
class CarritoFlujoTest extends CartFlowTestCase
{
    private const FECHA = '2026-11-05';

    public function test_catalogo_publico_no_expone_costos_ni_ocultos(): void
    {
        $respuesta = $this->getJson('/api/cart-flow/catalogo')->assertOk()->assertJsonPath('success', true);

        $json = $respuesta->getContent();
        $this->assertStringNotContainsString('costo_domilab', $json);
        $this->assertStringNotContainsString('nota_interna', $json);

        $skus = array_column($respuesta->json('data.productos'), 'sku');
        $this->assertContains('PERF-BASICO', $skus);
        $this->assertNotContains('LAB-1051', $skus); // TRUTROL oculto en la carga inicial
        $this->assertNotContains('LAB-307011-TM', $skus); // la toma nunca se vende suelta

        $this->getJson('/api/cart-flow/comunas')->assertOk()->assertJsonCount(50, 'data');
    }

    public function test_flujo_completo_con_transferencia(): void
    {
        Storage::fake('local');

        $carrito = $this->crearCarrito('El Bosque');
        $uuid = $carrito['uuid'];
        $pacienteId = $carrito['pacientes'][0]['id'];

        $this->agregar($uuid, $pacienteId, 'PERF-BASICO');
        $respuesta = $this->agregar($uuid, $pacienteId, 'SRV-CURACION-SIMPLE-INCLUYE-INSUMOS-BASICOS');

        // Ejemplo 7 de la especificacion.
        $respuesta->assertJsonPath('data.desglose.total', 55000)
            ->assertJsonPath('data.desglose.cargo_domicilio', 15000)
            ->assertJsonPath('data.requiere_manana', true);

        // Laboratorio con ayuno: la agenda solo ofrece manana.
        $agenda = $this->getJson("/api/cart-flow/agenda/bloques?carrito={$uuid}")->assertOk();
        $franjas = collect($agenda->json('data.dias'))->pluck('bloques')->flatten(1)->pluck('franja')->unique()->values()->all();
        $this->assertSame(['manana'], $franjas);
        $this->assertSame(self::FECHA, $agenda->json('data.dias.0.fecha'));

        $bloque = $agenda->json('data.dias.0.bloques.0.id');

        // Sin datos del paciente no hay checkout.
        $this->checkout($uuid, $bloque)->assertStatus(422)->assertJsonPath('success', false);

        $this->datosPaciente($uuid, $pacienteId);

        $pedido = $this->checkout($uuid, $bloque)
            ->assertCreated()
            ->assertJsonPath('data.desglose.total', 55000)
            ->assertJsonPath('data.estado', 'pendiente_pago')
            ->assertJsonPath('data.agenda.bloque', '07:30-10:00');

        $numero = $pedido->json('data.numero');
        $token = $pedido->json('data.token_consulta');
        $this->assertMatchesRegularExpression('/^ES-2026-\d{6}$/', $numero);

        // El carro quedo convertido: no admite cambios.
        $this->agregar($uuid, $pacienteId, 'LAB-301045')->assertStatus(409);

        // Sin token el pedido no existe; con token si, y sin RUT en la respuesta.
        $this->getJson("/api/cart-flow/pedidos/{$numero}")->assertNotFound();
        $consulta = $this->getJson("/api/cart-flow/pedidos/{$numero}?t={$token}")->assertOk();
        $this->assertStringNotContainsString('11111111-1', $consulta->getContent());
        $this->assertSame('Ayuno de 8 horas', $consulta->json('data.pacientes.0.preparacion.indicaciones.0'));
    }

    public function test_bloque_lleno_responde_409(): void
    {
        $bloque = BloqueAgenda::where('dia_semana', 4)->where('franja', 'manana')->orderBy('hora_inicio')->first();
        $bloque->update(['cupo' => 1]);

        foreach ([201, 409] as $esperado) {
            $carrito = $this->crearCarrito('Maipú');
            $this->agregar($carrito['uuid'], $carrito['pacientes'][0]['id'], 'PERF-BASICO');
            $this->datosPaciente($carrito['uuid'], $carrito['pacientes'][0]['id']);

            $this->checkout($carrito['uuid'], $bloque->id)->assertStatus($esperado);
        }
    }

    public function test_orden_medica_obligatoria_y_menor_con_apoderado(): void
    {
        Storage::fake('local');

        $carrito = $this->crearCarrito('San Bernardo');
        $uuid = $carrito['uuid'];
        $pacienteId = $carrito['pacientes'][0]['id'];
        $this->agregar($uuid, $pacienteId, 'LAB-302048')->assertJsonPath('data.desglose.total', 29000);

        $this->putJson("/api/cart-flow/carritos/{$uuid}/pacientes/{$pacienteId}", [
            'nombre' => 'Paciente Ficticio Menor', 'rut' => '22222222-2', 'fecha_nac' => '2012-03-01',
            'sexo' => 'F', 'consentimiento' => true,
        ])->assertOk();

        $bloque = $this->primerBloque($uuid);
        $errores = $this->checkout($uuid, $bloque)->assertStatus(422)->json('error');
        $this->assertArrayHasKey("pacientes.{$pacienteId}.orden_medica", $errores);
        $this->assertArrayHasKey("pacientes.{$pacienteId}.apoderado", $errores);

        $this->putJson("/api/cart-flow/carritos/{$uuid}/pacientes/{$pacienteId}", [
            'apoderado_nombre' => 'Apoderado Ficticio', 'apoderado_rut' => '33333333-3', 'consentimiento_apoderado' => true,
        ])->assertOk();

        $this->checkout($uuid, $bloque, [
            'ordenes' => [$pacienteId => UploadedFile::fake()->create('orden.pdf', 100, 'application/pdf')],
        ])->assertCreated()->assertJsonPath('data.pacientes.0.orden_medica_adjunta', true);

        $this->assertCount(1, Storage::disk('local')->allFiles('cart-flow/ordenes'));
    }

    public function test_operativo_y_comuna_fuera_de_rm(): void
    {
        $carrito = $this->crearCarrito('Maipú');
        $this->agregar($carrito['uuid'], $carrito['pacientes'][0]['id'], 'EMP-LABORAL-BASICO')->assertNotFound(); // oculto

        // Aunque el dueno lo muestre, un operativo se cotiza: no entra al carro.
        Producto::where('sku', 'EMP-LABORAL-BASICO')->update(['visible_tienda' => true]);
        $this->agregar($carrito['uuid'], $carrito['pacientes'][0]['id'], 'EMP-LABORAL-BASICO')
            ->assertStatus(422)->assertJsonPath('error.sku.0', 'solo_cotizacion');

        $fuera = Comuna::whereNull('zona_id')->first();
        $this->assertNotNull($fuera, 'El catalogo trae comunas fuera de RM');

        $carrito = $this->postJson('/api/cart-flow/carritos', ['comuna_id' => $fuera->id])->assertCreated();
        $carrito->assertJsonPath('data.a_cotizar', true);
        $this->getJson("/api/cart-flow/agenda/bloques?carrito={$carrito->json('data.uuid')}")->assertStatus(422);

        $this->postJson('/api/cart-flow/cotizaciones', [
            'comuna' => $fuera->comuna, 'servicio' => 'Operativo chequeo cardiovascular', 'personas' => 40,
            'contacto_nombre' => 'Contacto Ficticio', 'contacto_email' => 'contacto@example.com', 'contacto_telefono' => '+56900000000',
        ])->assertCreated();
    }

    public function test_producto_oculto_sale_del_carro_y_reimportar_respeta_visibilidad(): void
    {
        $carrito = $this->crearCarrito('El Bosque');
        $uuid = $carrito['uuid'];
        $this->agregar($uuid, $carrito['pacientes'][0]['id'], 'LAB-301045');

        Producto::where('sku', 'LAB-301045')->update(['visible_tienda' => false]);

        $this->getJson("/api/cart-flow/carritos/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.avisos.0.codigo', 'producto_no_disponible')
            ->assertJsonCount(0, 'data.pacientes.0.lineas');

        // Por el comando, que es como se reimporta en el hosting (y prueba que esta registrado).
        $this->artisan('cart-flow:importar-catalogo')->assertExitCode(0);

        $this->assertFalse(Producto::where('sku', 'LAB-301045')->value('visible_tienda'));
        $this->assertSame(245, Producto::count());
    }

    public function test_webpay_cobra_el_total_del_pedido_y_valida_el_commit(): void
    {
        config(['services.cart_flow.webpay_url' => 'https://webpay.test/transactions',
            'services.cart_flow.webpay_return' => 'https://api.test/api/cart-flow/webpay/retorno',
            'services.cart_flow.front_url' => 'https://front.test']);

        $carrito = $this->crearCarrito('Maipú');
        $uuid = $carrito['uuid'];
        $this->agregar($uuid, $carrito['pacientes'][0]['id'], 'PERF-BASICO');
        $this->datosPaciente($uuid, $carrito['pacientes'][0]['id']);

        $pedido = $this->checkout($uuid, $this->primerBloque($uuid), ['medio_pago' => 'webpay'])->assertCreated();
        $numero = $pedido->json('data.numero');
        $token = $pedido->json('data.token_consulta');
        $total = $pedido->json('data.desglose.total');
        $buyOrder = null;

        $intento = 0;

        // Un solo fake (Http::fake acumula stubs): el intento 1 devuelve un commit con monto alterado.
        Http::fake(function ($request) use (&$buyOrder, &$intento, $total) {
            if ($request->method() === 'POST') {
                $intento++;
                $buyOrder = $request['buy_order'];
                $this->assertSame($total, $request['amount']); // el monto sale del pedido

                return Http::response(['token' => "tok-{$intento}", 'url' => 'https://webpay.test/pay']);
            }

            $monto = $intento === 1 ? $total - 1000 : $total;

            return Http::response(['status' => 'AUTHORIZED', 'response_code' => 0, 'amount' => $monto, 'buy_order' => $buyOrder, 'authorization_code' => '1213']);
        });

        $this->postJson("/api/cart-flow/pedidos/{$numero}/webpay")->assertNotFound(); // sin token de consulta
        $this->postJson("/api/cart-flow/pedidos/{$numero}/webpay", ['t' => $token])
            ->assertOk()->assertJsonPath('data.token', 'tok-1');

        $this->get('/api/cart-flow/webpay/retorno?token_ws=tok-1')->assertRedirectContains('pago=rechazado');
        $this->assertSame('pendiente_pago', Pedido::where('numero', $numero)->value('estado'));

        // Segundo intento: commit correcto.
        $this->postJson("/api/cart-flow/pedidos/{$numero}/webpay", ['t' => $token])->assertOk();
        $this->get('/api/cart-flow/webpay/retorno?token_ws=tok-2')
            ->assertRedirect("https://front.test/servicios/resultado?pago=aprobado&pedido={$numero}&t={$token}");

        $this->assertSame('pagado', Pedido::where('numero', $numero)->value('estado'));
    }

    public function test_visibilidad_exige_administrador_y_deja_registro(): void
    {
        // users_metadata y perfiles no tienen migracion: se crean minimas solo para este test.
        Schema::create('perfiles', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
        });
        Schema::create('users_metadata', function (Blueprint $t) {
            $t->id();
            $t->string('user_email');
            $t->unsignedBigInteger('perfiles_id');
        });
        $perfil = DB::table('perfiles')->insertGetId(['nombre' => 'Administrador']);
        DB::table('users_metadata')->insert(['user_email' => 'admin@example.com', 'perfiles_id' => $perfil]);

        $url = '/api/cart-flow/admin/productos/PERF-BASICO/visibilidad';

        $this->putJson($url, ['user_email' => 'nadie@example.com', 'visible_tienda' => false])->assertForbidden();

        $this->putJson($url, ['user_email' => 'admin@example.com', 'visible_tienda' => false])
            ->assertOk()->assertJsonPath('data.visible_tienda', false);

        $this->assertDatabaseHas('cf_visibilidad_log', ['user_email' => 'admin@example.com', 'anterior' => 1, 'nuevo' => 0]);

        // Activar un no publicable pide confirmacion.
        $this->putJson('/api/cart-flow/admin/productos/PACK-CONTROL-TACO-BASE/visibilidad', ['user_email' => 'admin@example.com', 'visible_tienda' => true])
            ->assertStatus(409)->assertJsonPath('error', 'requiere_confirmacion');
    }

    private function crearCarrito(string $comuna): array
    {
        $id = Comuna::where('comuna', $comuna)->value('id');

        return $this->postJson('/api/cart-flow/carritos', ['comuna_id' => $id, 'direccion' => 'Calle Ficticia 123'])
            ->assertCreated()
            ->json('data');
    }

    private function agregar(string $uuid, int $pacienteId, string $sku)
    {
        return $this->postJson("/api/cart-flow/carritos/{$uuid}/lineas", ['paciente_id' => $pacienteId, 'sku' => $sku]);
    }

    private function datosPaciente(string $uuid, int $pacienteId): void
    {
        $this->putJson("/api/cart-flow/carritos/{$uuid}/pacientes/{$pacienteId}", [
            'nombre' => 'Paciente Ficticio', 'rut' => '11111111-1', 'fecha_nac' => '1980-01-01',
            'sexo' => 'M', 'prevision' => 'Fonasa', 'consentimiento' => true,
        ])->assertOk();
    }

    private function primerBloque(string $uuid): int
    {
        return $this->getJson("/api/cart-flow/agenda/bloques?carrito={$uuid}")->json('data.dias.0.bloques.0.id');
    }

    private function checkout(string $uuid, int $bloque, array $extra = [])
    {
        return $this->post("/api/cart-flow/carritos/{$uuid}/checkout", $extra + [
            'fecha' => self::FECHA,
            'bloque_id' => $bloque,
            'medio_pago' => 'transferencia',
            'contacto_nombre' => 'Contacto Ficticio',
            'contacto_email' => 'contacto@example.com',
            'contacto_telefono' => '+56900000000',
            'acepta_terminos' => '1',
        ], ['Accept' => 'application/json']);
    }
}
