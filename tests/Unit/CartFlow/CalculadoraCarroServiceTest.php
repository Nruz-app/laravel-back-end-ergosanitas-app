<?php

namespace Tests\Unit\CartFlow;

use App\Models\CartFlow\Producto;
use App\Services\CartFlow\CalculadoraCarroService;
use App\Services\CartFlow\CatalogoCsv;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SPEC 04 — Los 10 ejemplos resueltos de la §5 de ERGO_SANITAS_SERVICIOS_Y_CARRO.md (Parte B),
 * calculados con el catalogo versionado en database/data/cart-flow/.
 *
 * No arranca Laravel ni toca base de datos: los productos se crean con Model::unguarded(), porque
 * fill() con $guarded consulta las columnas de la tabla (y phpunit.xml apunta al MySQL remoto).
 */
class CalculadoraCarroServiceTest extends TestCase
{
    private static array $productos = [];

    private static array $comunas = [];

    private CalculadoraCarroService $calculadora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculadora = new CalculadoraCarroService;

        if (self::$productos === []) {
            $csv = new CatalogoCsv;
            $dir = dirname(__DIR__, 3).'/database/data/cart-flow';

            foreach ($csv->productos($dir) as $sku => $fila) {
                self::$productos[$sku] = $fila['atributos'];
            }

            foreach ($csv->comunas($dir) as $comuna) {
                self::$comunas[$comuna['comuna']] = $comuna;
            }
        }
    }

    public static function ejemplos(): array
    {
        return [
            '1 Perfil Basico San Bernardo' => ['San Bernardo', 'ninguno', [['1980-01-01', ['PERF-BASICO']]], 34310, 35000],
            '2 Perfil Basico Nunoa' => ['Ñuñoa', 'ninguno', [['1980-01-01', ['PERF-BASICO']]], 39310, 40000],
            '3 Inyeccion IM Te Cuida Maipu' => ['Maipú', 'te_cuida', [['1980-01-01', ['SRV-INYECCION-INTRAMUSCULAR']]], 23000, 23000],
            '4 Pack HTA + Perfil Diabetico dos pacientes La Florida' => ['La Florida', 'ninguno', [
                ['1970-01-01', ['PACK-HIPERTENSION-ARTERIAL']],
                ['1972-01-01', ['PERF-DIABETICO']],
            ], 62760, 63000],
            '5 Hemograma + TSH El Bosque' => ['El Bosque', 'ninguno', [['1980-01-01', ['LAB-301045', 'LAB-303024']]], 26610, 27000],
            '6 Curacion simple El Bosque' => ['El Bosque', 'ninguno', [['1980-01-01', ['SRV-CURACION-SIMPLE-INCLUYE-INSUMOS-BASICOS']]], 30000, 30000],
            '7 Perfil Basico + curacion El Bosque' => ['El Bosque', 'ninguno', [['1980-01-01', ['PERF-BASICO', 'SRV-CURACION-SIMPLE-INCLUYE-INSUMOS-BASICOS']]], 54310, 55000],
            '8 MAPA adulto mayor Maipu' => ['Maipú', 'ninguno', [['1956-01-01', ['SRV-HOLTER-PRESION-ARTERIAL-MAPA-24-HRS']]], 60000, 60000],
            '9 Hemograma + Trigliceridos + TSH El Bosque' => ['El Bosque', 'ninguno', [['1980-01-01', ['LAB-301045', 'LAB-302064', 'LAB-303024']]], 28960, 29000],
            '10 Tolerancia a la glucosa San Bernardo' => ['San Bernardo', 'ninguno', [['1980-01-01', ['LAB-302048']]], 28760, 29000],
        ];
    }

    #[DataProvider('ejemplos')]
    public function test_ejemplos_de_la_especificacion(string $comuna, string $plan, array $pacientes, int $sinRedondeo, int $total): void
    {
        $resultado = $this->calcular($comuna, $plan, $pacientes);

        $this->assertSame($sinRedondeo, $resultado['desglose']['total_sin_redondeo']);
        $this->assertSame($total, $resultado['desglose']['total']);
        $this->assertSame($total - $sinRedondeo, $resultado['desglose']['ajuste_redondeo']);
        $this->assertSame([], $resultado['bloqueos']);
    }

    public function test_ejemplo_9_consolida_ayuno_de_12_horas_y_manana(): void
    {
        $resultado = $this->calcular('El Bosque', 'ninguno', [['1980-01-01', ['LAB-301045', 'LAB-302064', 'LAB-303024']]]);

        $preparacion = $resultado['pacientes'][0]['preparacion'];
        $this->assertTrue($preparacion['ayuno']);
        $this->assertSame(12, $preparacion['horas_ayuno']);
        $this->assertSame('Ayuno de 12 horas', $preparacion['indicaciones'][0]);
        $this->assertTrue($resultado['requiere_manana']);
        $this->assertSame(1460, $resultado['desglose']['toma_muestra']);
    }

    public function test_ejemplo_10_agrega_trutrol_una_vez_y_pide_orden(): void
    {
        $resultado = $this->calcular('San Bernardo', 'ninguno', [['1980-01-01', ['LAB-302048', 'LAB-303031']]]);

        $automaticas = array_filter($resultado['pacientes'][0]['lineas'], fn ($l) => $l['automatica']);
        $skus = array_column($automaticas, 'sku');

        $this->assertSame(4550, $resultado['desglose']['insumos']);
        $this->assertContains('LAB-1051', $skus);
        $this->assertCount(1, array_keys($skus, 'LAB-1051'));
        $this->assertTrue($resultado['requiere_orden_medica']);
    }

    public function test_segundo_paciente_no_paga_domicilio(): void
    {
        $uno = $this->calcular('Maipú', 'ninguno', [['1980-01-01', ['PERF-BASICO']]]);
        $dos = $this->calcular('Maipú', 'ninguno', [['1980-01-01', ['PERF-BASICO']], ['1981-01-01', ['PERF-BASICO']]]);

        $this->assertSame($uno['desglose']['cargo_domicilio'], $dos['desglose']['cargo_domicilio']);
    }

    public function test_plan_no_descuenta_laboratorio(): void
    {
        $resultado = $this->calcular('El Bosque', 'familiar', [['1980-01-01', ['PERF-BASICO']]]);

        $this->assertSame(19310, $resultado['desglose']['subtotal_lab']);
        $this->assertSame(0, $resultado['desglose']['descuento_plan']);
    }

    public function test_bajo_ticket_minimo_bloquea(): void
    {
        $resultado = $this->calcular('El Bosque', 'ninguno', [['1980-01-01', ['SRV-INYECCION-INTRAMUSCULAR']]]);

        // 10.000 + 10.000 de zona 1 = 20.000: justo el minimo, no bloquea.
        $this->assertSame([], $resultado['bloqueos']);

        $resultado = $this->calcular('El Bosque', 'te_cuida', [['1980-01-01', ['SRV-INYECCION-INTRAMUSCULAR']]]);
        $this->assertContains('ticket_minimo', array_column($resultado['bloqueos'], 'codigo'));
    }

    public function test_comuna_sin_zona_queda_a_cotizar(): void
    {
        $resultado = $this->calcular(null, 'ninguno', [['1980-01-01', ['PERF-BASICO']]], ['a_cotizar' => true, 'zona' => null, 'cargo_servicio' => 0, 'cargo_laboratorio' => 0]);

        $this->assertTrue($resultado['a_cotizar']);
        $this->assertSame(0, $resultado['desglose']['cargo_domicilio']);
        $this->assertContains('a_cotizar', array_column($resultado['bloqueos'], 'codigo'));
    }

    public function test_edad_y_sexo_bloquean_la_linea(): void
    {
        // PSA solo 50+ (PERF-HOMBRE-50: edad_min 50, sexo M).
        $resultado = $this->calcular('Maipú', 'ninguno', [['2000-01-01', ['PERF-HOMBRE-50'], 'F']]);
        $codigos = array_column($resultado['bloqueos'], 'codigo');

        $this->assertContains('edad_min', $codigos);
        $this->assertContains('sexo', $codigos);
    }

    public function test_operativo_no_entra_al_carro(): void
    {
        $resultado = $this->calcular('Maipú', 'ninguno', [['1980-01-01', ['EMP-LABORAL-BASICO']]]);

        $this->assertContains('solo_cotizacion', array_column($resultado['bloqueos'], 'codigo'));
    }

    /**
     * @param  array<int, array{0:string, 1:string[], 2?:string}>  $pacientes  [fecha_nac, skus, sexo?]
     */
    private function calcular(?string $comuna, string $plan, array $pacientes, ?array $comunaDatos = null): array
    {
        $datosComuna = $comunaDatos ?? [
            'a_cotizar' => self::$comunas[$comuna]['a_cotizar'],
            'zona' => self::$comunas[$comuna]['zona'],
            'cargo_servicio' => self::$comunas[$comuna]['cargo_servicio'],
            'cargo_laboratorio' => self::$comunas[$comuna]['cargo_laboratorio'],
        ];

        $entrada = [];

        foreach ($pacientes as $i => $p) {
            $entrada[] = [
                'id' => $i + 1,
                'fecha_nac' => $p[0],
                'sexo' => $p[2] ?? null,
                'lineas' => array_map(fn ($sku) => ['cantidad' => 1, 'producto' => $this->producto($sku)], $p[1]),
            ];
        }

        return $this->calculadora->calcular([
            'comuna' => $datosComuna,
            'plan' => $plan,
            'fecha' => Carbon::parse('2026-11-10'),
            'pacientes' => $entrada,
            'automaticos' => [
                'LAB-307011-TM' => $this->producto('LAB-307011-TM'),
                'LAB-1051' => $this->producto('LAB-1051'),
            ],
        ]);
    }

    private function producto(string $sku): Producto
    {
        $this->assertArrayHasKey($sku, self::$productos, "El SKU {$sku} no esta en el catalogo");

        return Producto::unguarded(fn () => new Producto(self::$productos[$sku]));
    }
}
