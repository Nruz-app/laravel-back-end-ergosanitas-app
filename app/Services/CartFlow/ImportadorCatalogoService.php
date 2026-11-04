<?php

namespace App\Services\CartFlow;

use App\Models\CartFlow\Comuna;
use App\Models\CartFlow\OperativoTramo;
use App\Models\CartFlow\Producto;
use App\Models\CartFlow\ProductoComponente;
use App\Models\CartFlow\Zona;
use Illuminate\Support\Facades\DB;

/**
 * SPEC 04 — Carga o actualiza el catalogo desde los CSV del mantenedor.
 *
 * Reglas del §7 de la especificacion de negocio:
 * - upsert por SKU: actualiza precios, nombres y textos;
 * - NO sobrescribe visible_tienda de productos existentes (lo decide el dueno en el mantenedor);
 * - los productos nuevos entran con el visible_tienda del CSV (ya trae los OCULTOS_INICIALES);
 * - columnas ausentes del CSV (costo_domilab, nota_interna en la copia versionada) no se tocan.
 * Nada se borra: un SKU que desaparece del CSV queda como esta (ocultarlo es decision del dueno).
 */
class ImportadorCatalogoService
{
    public function __construct(private CatalogoCsv $csv) {}

    /** @return array{zonas:int, comunas:int, productos_nuevos:int, productos_actualizados:int, componentes:int, tramos:int, omitidos:string[]} */
    public function importar(string $dir): array
    {
        $productosCsv = $this->csv->productos($dir);
        $comunasCsv = $this->csv->comunas($dir);
        $componentesCsv = $this->csv->componentes($dir);
        $tramosCsv = $this->csv->tramos($dir);

        return DB::transaction(function () use ($productosCsv, $comunasCsv, $componentesCsv, $tramosCsv) {
            $resumen = ['zonas' => 0, 'comunas' => 0, 'productos_nuevos' => 0, 'productos_actualizados' => 0, 'componentes' => 0, 'tramos' => 0, 'omitidos' => []];

            // Zonas: una por numero de zona, con los cargos de sus comunas.
            $zonas = [];

            foreach ($comunasCsv as $fila) {
                if ($fila['zona'] === null || $fila['a_cotizar']) {
                    continue;
                }

                $zonas[$fila['zona']] = Zona::updateOrCreate(
                    ['zona' => $fila['zona']],
                    ['cargo_servicio' => (int) $fila['cargo_servicio'], 'cargo_laboratorio' => (int) $fila['cargo_laboratorio']]
                );
            }

            $resumen['zonas'] = count($zonas);

            foreach ($comunasCsv as $fila) {
                Comuna::updateOrCreate(['comuna' => $fila['comuna']], [
                    'zona_id' => $fila['zona'] !== null && isset($zonas[$fila['zona']]) && ! $fila['a_cotizar'] ? $zonas[$fila['zona']]->id : null,
                    'km_ida_vuelta' => $fila['km_ida_vuelta'],
                    'a_cotizar' => $fila['a_cotizar'],
                    'comuna_lejana_chequeo_cv' => $fila['comuna_lejana_chequeo_cv'],
                ]);
                $resumen['comunas']++;
            }

            $ids = [];

            foreach ($productosCsv as $sku => $fila) {
                $atributos = $fila['atributos'];
                $producto = Producto::where('sku', $sku)->first();

                if ($producto) {
                    unset($atributos['visible_tienda']);
                    $producto->fill($atributos)->save();
                    $resumen['productos_actualizados']++;
                } else {
                    $producto = Producto::create($atributos + ['visible_tienda' => false]);
                    $resumen['productos_nuevos']++;
                }

                $ids[$sku] = $producto->id;
            }

            foreach ($componentesCsv as $fila) {
                $pack = $ids[$fila['sku_pack']] ?? null;
                $componente = $ids[$fila['sku_componente']] ?? null;

                if (! $pack || ! $componente) {
                    $resumen['omitidos'][] = "{$fila['sku_pack']} → {$fila['sku_componente']}";

                    continue;
                }

                ProductoComponente::updateOrCreate(
                    ['pack_id' => $pack, 'componente_id' => $componente],
                    ['orden' => $fila['orden']]
                );
                $resumen['componentes']++;
            }

            foreach ($tramosCsv as $fila) {
                OperativoTramo::updateOrCreate(
                    ['sku_operativo' => $fila['sku_operativo'], 'pacientes_min' => $fila['pacientes_min']],
                    $fila
                );
                $resumen['tramos']++;
            }

            return $resumen;
        });
    }
}
