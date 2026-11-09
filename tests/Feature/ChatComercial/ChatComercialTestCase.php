<?php

namespace Tests\Feature\ChatComercial;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SPEC 06 — Base de los tests del chat comercial «Ergosanito».
 *
 * Igual que CartFlowTestCase: la conexión se fuerza a SQLite en memoria ANTES de arrancar la
 * aplicación, para que estos tests nunca toquen el MySQL remoto del .env. Se migran solo las
 * tablas cf_* del catálogo y las dos del chat. La caché ya es «array» en phpunit.xml, así que
 * no hace falta la tabla cache.
 *
 * El catálogo es FICTICIO y tiene un producto con costo_domilab, nota_interna y precios por plan
 * reconocibles, para comprobar que nunca llegan al modelo.
 */
abstract class ChatComercialTestCase extends TestCase
{
    /** Valores trampa: si aparecen en el mensaje enviado a OpenAI, el contexto filtró datos internos. */
    protected const COSTO_INTERNO = 777123;

    protected const NOTA_INTERNA = 'NOTA-INTERNA-SECRETA';

    protected const PRECIO_TE_CUIDA = 555111;

    protected const PRECIO_FAMILIAR = 555222;

    protected const PRECIO_ADULTO_MAYOR = 555333;

    protected const EDAD_BENEFICIO = 99;

    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $clave => $valor) {
            putenv("{$clave}={$valor}");
            $_ENV[$clave] = $valor;
            $_SERVER[$clave] = $valor;
        }

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'), 'Los tests del chat comercial solo corren sobre SQLite');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/2026_11_04_120000_create_cf_catalogo_tables.php',
                'database/migrations/2026_11_09_120000_create_chat_comercial_sessions_table.php',
                'database/migrations/2026_11_09_120100_create_chat_comercial_history_table.php',
            ],
        ])->assertExitCode(0);

        $this->sembrarCatalogoFicticio();
    }

    private function sembrarCatalogoFicticio(): void
    {
        $ahora = now();

        DB::table('cf_productos')->insert([
            [
                'sku' => 'LAB-FICTICIO-1', 'tipo' => 'perfil_lab', 'categoria' => 'Laboratorio / Exámenes',
                'nombre' => 'Perfil lipídico de prueba', 'precio' => 9990,
                'costo_domilab' => self::COSTO_INTERNO, 'nota_interna' => self::NOTA_INTERNA,
                'precio_te_cuida' => self::PRECIO_TE_CUIDA, 'precio_familiar' => self::PRECIO_FAMILIAR,
                'precio_adulto_mayor' => self::PRECIO_ADULTO_MAYOR, 'edad_min_beneficio' => self::EDAD_BENEFICIO,
                'visible_tienda' => true, 'requiere_ayuno' => true, 'horas_ayuno' => 12,
                'preparacion' => 'Ayuno de 12 horas. Agua permitida.',
                'created_at' => $ahora, 'updated_at' => $ahora,
            ],
            [
                'sku' => 'SRV-FICTICIO-OCULTO', 'tipo' => 'servicio_domicilio', 'categoria' => 'Enfermería a domicilio',
                'nombre' => 'Servicio oculto de prueba', 'precio' => 12345,
                'costo_domilab' => null, 'nota_interna' => null,
                'precio_te_cuida' => null, 'precio_familiar' => null,
                'precio_adulto_mayor' => null, 'edad_min_beneficio' => null,
                'visible_tienda' => false, 'requiere_ayuno' => false, 'horas_ayuno' => null,
                'preparacion' => null,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ],
        ]);

        $zona = DB::table('cf_zonas')->insertGetId([
            'zona' => 1, 'cargo_servicio' => 5000, 'cargo_laboratorio' => 4000, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);

        DB::table('cf_comunas')->insert([
            ['comuna' => 'Providencia', 'zona_id' => $zona, 'a_cotizar' => false, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['comuna' => 'Arica', 'zona_id' => null, 'a_cotizar' => true, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        DB::table('cf_operativo_tramos')->insert([
            'sku_operativo' => 'OP-CHEQUEO-CV', 'pacientes_min' => 20, 'pacientes_max' => 49,
            'precio_rm' => 18000, 'precio_v_region_ohiggins' => 25000, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
    }

    /** Respuesta simulada de OpenAI con el texto indicado. */
    protected function respuestaOpenAI(string $texto): \OpenAI\Responses\Chat\CreateResponse
    {
        return \OpenAI\Responses\Chat\CreateResponse::fake([
            'choices' => [['message' => ['content' => $texto]]],
        ]);
    }

    protected function preguntar(string $prompt, ?string $sessionId = null): \Illuminate\Testing\TestResponse
    {
        $body = ['prompt' => $prompt];

        if ($sessionId !== null) {
            $body['sessionId'] = $sessionId;
        }

        return $this->postJson('/api/chat-comercial/as-question', $body);
    }
}
