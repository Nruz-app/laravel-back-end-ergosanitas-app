<?php

namespace App\Services\CartFlow;

use App\Models\CartFlow\Comuna;
use App\Models\CartFlow\Producto;
use App\Models\CartFlow\Regla;
use App\Models\CartFlow\VisibilidadLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SPEC 04 — Lectura del catalogo publico y reglas del carro.
 * Solo publica productos con visible_tienda; la toma de muestra nunca se vende suelta.
 */
class CatalogoService
{
    /** Orden de categorias en la tienda (§5.1 de la especificacion); las demas van despues. */
    private const ORDEN_CATEGORIAS = [
        'Laboratorio / Perfiles preventivos',
        'Laboratorio / Packs',
        'Laboratorio / Exámenes',
        'Enfermería a domicilio',
        'Cardiovascular',
        'Vacunas y medicamentos',
        'Eventos y primeros auxilios',
    ];

    public function comunas(): Collection
    {
        return Comuna::with('zona')
            ->where('activo', true)
            ->orderBy('comuna')
            ->get()
            ->map(fn (Comuna $c) => $this->comunaPublica($c));
    }

    public function comunaPublica(?Comuna $c): ?array
    {
        if (! $c) {
            return null;
        }

        return [
            'id' => $c->id,
            'comuna' => $c->comuna,
            'zona' => $c->zona?->zona,
            'a_cotizar' => $c->esACotizar(),
            'cargo_servicio' => $c->esACotizar() ? null : $c->zona?->cargo_servicio,
            'cargo_laboratorio' => $c->esACotizar() ? null : $c->zona?->cargo_laboratorio,
        ];
    }

    /** @return array{productos: Collection, categorias: string[]} */
    public function listar(?string $categoria, ?string $tipo, ?string $q): array
    {
        $productos = Producto::visibles()
            ->where('tipo', '!=', 'toma_muestra')
            ->when($categoria, fn ($query) => $query->where('categoria', $categoria))
            ->when($tipo, fn ($query) => $query->where('tipo', $tipo))
            ->when($q, fn ($query) => $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($q).'%']))
            ->orderBy('nombre')
            ->get();

        $categorias = Producto::visibles()
            ->where('tipo', '!=', 'toma_muestra')
            ->distinct()
            ->pluck('categoria')
            ->sortBy(fn ($c) => [array_search($c, self::ORDEN_CATEGORIAS, true) === false ? 99 : array_search($c, self::ORDEN_CATEGORIAS, true), $c])
            ->values()
            ->all();

        return ['productos' => $productos, 'categorias' => $categorias];
    }

    public function detalle(string $sku): ?Producto
    {
        return Producto::visibles()->where('sku', $sku)->with('componentes:id,sku,nombre,tipo')->first();
    }

    /** cf_reglas como array clave => valor, para mezclar con los valores por defecto. */
    public function reglas(): array
    {
        return Regla::all()->pluck('valor', 'clave')->all();
    }

    /**
     * Productos que el carro agrega solo, aunque esten ocultos: la toma de muestra y los insumos
     * de agrega_automatico (TRUTROL).
     *
     * @return array<string, Producto>
     */
    public function automaticos(array $reglas): array
    {
        $skus = Producto::whereNotNull('agrega_automatico')->distinct()->pluck('agrega_automatico')->all();
        $skus[] = $reglas['toma_muestra']['sku'] ?? CalculadoraCarroService::REGLAS_POR_DEFECTO['toma_muestra']['sku'];

        return Producto::whereIn('sku', array_unique($skus))->get()->keyBy('sku')->all();
    }

    /** Mantenedor: cambia visible_tienda y deja registro de quien y cuando. */
    public function cambiarVisibilidad(Producto $producto, bool $visible, string $userEmail): Producto
    {
        return DB::transaction(function () use ($producto, $visible, $userEmail) {
            $anterior = (bool) $producto->visible_tienda;

            if ($anterior !== $visible) {
                $producto->update(['visible_tienda' => $visible]);

                VisibilidadLog::create([
                    'producto_id' => $producto->id,
                    'user_email' => $userEmail,
                    'anterior' => $anterior,
                    'nuevo' => $visible,
                ]);
            }

            return $producto->fresh();
        });
    }

    /** Mismo control que HomeImagenService::esAdministrador (D3 de la Spec 03). */
    public function esAdministrador(?string $userEmail): bool
    {
        if (! $userEmail) {
            return false;
        }

        return DB::table('users_metadata as um')
            ->join('perfiles as p', 'p.id', '=', 'um.perfiles_id')
            ->where('um.user_email', $userEmail)
            ->where('p.nombre', 'Administrador')
            ->exists();
    }
}
