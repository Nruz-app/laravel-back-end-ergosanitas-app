<?php

namespace Tests\Feature\CartFlow;

use Database\Seeders\CartFlowSeeder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SPEC 04 — Base de los tests de feature del carro.
 *
 * phpunit.xml apunta al MySQL remoto (.env). Para que estos tests NUNCA toquen esa base, la
 * conexion se fuerza a SQLite en memoria antes de arrancar la aplicacion, y solo se migran las
 * tablas cf_* (las demas migraciones y los SP no existen en SQLite).
 */
abstract class CartFlowTestCase extends TestCase
{
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

        $this->assertSame('sqlite', config('database.default'), 'Los tests del carro solo corren sobre SQLite');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        // Miercoles 2026-11-04: la primera fecha agendable es el jueves 2026-11-05 (dia ISO 4).
        Carbon::setTestNow('2026-11-04 10:00:00');

        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/2026_11_04_120000_create_cf_catalogo_tables.php',
                'database/migrations/2026_11_04_120100_create_cf_agenda_carrito_tables.php',
                'database/migrations/2026_11_04_120200_create_cf_pedido_pago_tables.php',
                'database/migrations/2026_11_05_120000_create_cf_pedido_historial_table.php',
            ],
        ])->assertExitCode(0);

        $this->seed(CartFlowSeeder::class);
    }

    /**
     * users_metadata y perfiles no tienen migración en el repo: se crean mínimas para que
     * esAdministrador() funcione en SQLite. Devuelve el email del Administrador ficticio.
     */
    protected function crearAdministrador(string $email = 'admin@example.com'): string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('perfiles')) {
            \Illuminate\Support\Facades\Schema::create('perfiles', function ($t) {
                $t->id();
                $t->string('nombre');
            });
            \Illuminate\Support\Facades\Schema::create('users_metadata', function ($t) {
                $t->id();
                $t->string('user_email');
                $t->unsignedBigInteger('perfiles_id');
            });
        }

        $perfil = \Illuminate\Support\Facades\DB::table('perfiles')->insertGetId(['nombre' => 'Administrador']);
        \Illuminate\Support\Facades\DB::table('users_metadata')->insert(['user_email' => $email, 'perfiles_id' => $perfil]);

        return $email;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
