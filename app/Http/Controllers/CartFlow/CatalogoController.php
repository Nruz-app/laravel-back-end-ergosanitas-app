<?php

namespace App\Http\Controllers\CartFlow;

use App\Http\Controllers\Controller;
use App\Models\CartFlow\Producto;
use App\Services\CartFlow\CartFlowException;
use App\Services\CartFlow\CatalogoService;
use Illuminate\Http\Request;

/**
 * SPEC 04 — Comunas, catalogo publico y el interruptor visible_tienda del mantenedor.
 */
class CatalogoController extends Controller
{
    use RespuestasCartFlow;

    public function __construct(protected CatalogoService $catalogo) {}

    public function Comunas()
    {
        return $this->responder('Error listando las comunas', fn () => $this->ok($this->catalogo->comunas()));
    }

    public function Index(Request $request)
    {
        return $this->responder('Error listando el catálogo', function () use ($request) {
            $filtros = $this->validar($request->query(), [
                'categoria' => ['nullable', 'string', 'max:120'],
                'tipo' => ['nullable', 'string', 'in:'.implode(',', Producto::TIPOS)],
                'q' => ['nullable', 'string', 'max:100'],
            ]);

            return $this->ok($this->catalogo->listar($filtros['categoria'] ?? null, $filtros['tipo'] ?? null, $filtros['q'] ?? null));
        });
    }

    public function Show(string $sku)
    {
        return $this->responder('Error buscando el producto', function () use ($sku) {
            $producto = $this->catalogo->detalle($sku);

            if (! $producto) {
                throw CartFlowException::noEncontrado('Producto no encontrado');
            }

            return $this->ok($producto);
        });
    }

    /**
     * Mantenedor: muestra u oculta un producto. Exige user_email de perfil Administrador.
     * Activar un producto con publicable = NO pide confirmar = true (advertencia con el motivo, §7.2).
     * Es PUT y no PATCH porque el ApiAdapter del front no tiene patch.
     */
    public function UpdateVisibilidad(Request $request, string $sku)
    {
        return $this->responder('Error cambiando la visibilidad del producto', function () use ($request, $sku) {
            $datos = $this->validar($request->all(), [
                'user_email' => ['required', 'email'],
                'visible_tienda' => ['required', 'boolean'],
                'confirmar' => ['nullable', 'boolean'],
            ]);

            if (! $this->catalogo->esAdministrador($datos['user_email'])) {
                throw new CartFlowException('Solo un Administrador puede cambiar el catálogo.', 403, 'forbidden');
            }

            $producto = Producto::where('sku', $sku)->first();

            if (! $producto) {
                throw CartFlowException::noEncontrado('Producto no encontrado');
            }

            $visible = (bool) $datos['visible_tienda'];

            if ($visible && ! $producto->publicable && empty($datos['confirmar'])) {
                throw CartFlowException::conflicto(
                    'Este producto no es publicable: '.($producto->motivo_no_publicable ?: 'sin motivo informado').'. Envía confirmar = true para mostrarlo igual.',
                    'requiere_confirmacion'
                );
            }

            $producto = $this->catalogo->cambiarVisibilidad($producto, $visible, $datos['user_email']);

            return $this->ok($producto, $visible ? 'Producto visible en la tienda' : 'Producto oculto de la tienda');
        });
    }
}
