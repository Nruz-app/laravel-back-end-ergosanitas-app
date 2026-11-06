<?php

namespace App\Console\Commands;

use App\Services\CartFlow\ImportadorCatalogoService;
use Illuminate\Console\Command;

/**
 * SPEC 04 — Importa el catalogo del carro desde los CSV del mantenedor.
 *
 * Por defecto lee database/data/cart-flow/ (copia versionada SIN costos). Para cargar tambien
 * costo_domilab y nota_interna, apuntar --dir a la carpeta privada con los CSV completos.
 */
class CartFlowImportarCatalogo extends Command
{
    protected $signature = 'cart-flow:importar-catalogo {--dir= : Carpeta con productos.csv, componentes_packs.csv, comunas_zonas.csv y operativos_tramos.csv}';

    protected $description = 'Carga o actualiza zonas, comunas, productos, componentes y tramos del carro (no pisa visible_tienda)';

    public function handle(ImportadorCatalogoService $importador): int
    {
        $dir = $this->option('dir') ?: database_path('data/cart-flow');

        if (! is_dir($dir)) {
            $this->error("No existe la carpeta {$dir}");

            return self::FAILURE;
        }

        $resumen = $importador->importar($dir);

        $this->table(['Concepto', 'Cantidad'], [
            ['Zonas', $resumen['zonas']],
            ['Comunas', $resumen['comunas']],
            ['Productos nuevos', $resumen['productos_nuevos']],
            ['Productos actualizados (visible_tienda intacto)', $resumen['productos_actualizados']],
            ['Componentes de packs', $resumen['componentes']],
            ['Tramos de operativos', $resumen['tramos']],
        ]);

        foreach ($resumen['omitidos'] as $omitido) {
            $this->warn("Componente omitido (SKU inexistente): {$omitido}");
        }

        return self::SUCCESS;
    }
}
