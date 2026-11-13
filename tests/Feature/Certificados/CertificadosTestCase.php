<?php

namespace Tests\Feature\Certificados;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SPEC 07 — Base de los tests de descarga de certificados por RUT.
 *
 * Igual que CartFlowTestCase y ChatComercialTestCase: la conexión se fuerza a SQLite en memoria
 * ANTES de arrancar la aplicación, para no tocar nunca el MySQL remoto del .env. Se migran solo
 * las dos tablas nuevas de la spec. chequeo_cardiovascular, certificado_url y bioimpedancia
 * tienen en la BD real columnas que sus migraciones no crean (status, id_chequeo…), así que aquí
 * se crean con un esquema mínimo. Todos los datos son ficticios.
 */
abstract class CertificadosTestCase extends TestCase
{
    protected const RUT = '11111111-1';

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

        $this->assertSame('sqlite', config('database.default'), 'Los tests de certificados solo corren sobre SQLite');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/2026_11_11_120000_create_certificado_descargas_table.php',
                'database/migrations/2026_11_11_120100_create_bioimpedancia_analisis_ia_table.php',
            ],
        ])->assertExitCode(0);

        Schema::create('chequeo_cardiovascular', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 250);
            $table->string('rut', 20);
            $table->string('status', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('certificado_url', function (Blueprint $table) {
            $table->id();
            $table->string('rut_paciente', 20);
            $table->unsignedBigInteger('id_chequeo');
            $table->string('url_pdf', 250);
            $table->string('name_pdf', 100);
            $table->string('titulo', 100);
            $table->string('derivado_medico', 2)->nullable();
            $table->timestamps();
        });

        Schema::create('bioimpedancia', function (Blueprint $table) {
            $table->id();
            $table->string('rut');
            $table->string('nombre')->nullable();
            $table->string('club')->nullable();
            $table->string('archivo')->nullable();
            $table->date('fecha_prueba')->nullable();
            $table->timestamps();
        });
    }

    protected function crearChequeo(string $status = 'REVISION MEDICA', string $rut = self::RUT): int
    {
        return DB::table('chequeo_cardiovascular')->insertGetId([
            'nombre' => 'PACIENTE FICTICIO',
            'rut' => $rut,
            'status' => $status,
            'created_at' => '2026-05-04 10:00:00',
            'updated_at' => '2026-05-04 10:00:00',
        ]);
    }

    protected function crearCertificadoUrl(int $idChequeo, string $rut = self::RUT): void
    {
        DB::table('certificado_url')->insert([
            'rut_paciente' => $rut,
            'id_chequeo' => $idChequeo,
            'url_pdf' => 'https://api.ficticia.test/Certificado/'.$rut.'-'.$idChequeo.'.pdf',
            'name_pdf' => 'Paciente-Ficticio',
            'titulo' => 'Paciente Ficticio',
            'derivado_medico' => 'NO',
            'created_at' => '2026-05-04 12:00:00',
            'updated_at' => '2026-05-04 12:00:00',
        ]);
    }

    protected function crearBioimpedancia(
        string $rut = self::RUT,
        ?string $archivo = 'archivo-ficticio.pdf',
        ?string $fechaPrueba = '2026-06-30',
        string $creadaEl = '2026-06-30 09:00:00',
    ): int {
        return DB::table('bioimpedancia')->insertGetId([
            'rut' => $rut,
            'nombre' => 'Paciente Ficticio',
            'club' => 'club@ficticio.test',
            'archivo' => $archivo,
            'fecha_prueba' => $fechaPrueba,
            'created_at' => $creadaEl,
            'updated_at' => $creadaEl,
        ]);
    }

    protected function consultar(mixed $rut): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/certificados/consulta', $rut === null ? [] : ['rut' => $rut]);
    }
}
