<?php

namespace App\Http\Controllers;

use App\Services\JuegoCartasService;
use Illuminate\Http\Request;

class JuegoCartasController extends Controller
{
    protected $juegoCartasService;

    public function __construct(JuegoCartasService $juegoCartasService)
    {
        $this->juegoCartasService = $juegoCartasService;
    }

    /**
     * Todas las cartas de un club, ordenadas por puntaje descendente.
     */
    public function CartasClub(Request $request, string $user_email)
    {
        try {
            $search = $request->query('search');

            $cartas = $this->juegoCartasService->CartasClub($search, $user_email);

            return response()->json([
                'success' => true,
                'message' => 'Cartas obtenidas correctamente',
                'data' => [
                    'club' => $user_email,
                    'search' => $search,
                    'total' => count($cartas),
                    'cartas' => $cartas,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo las cartas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Una carta con el desglose de atributos. 200 con data null si el rut no existe.
     */
    public function CartaDetalle(string $rut_paciente)
    {
        try {
            $carta = $this->juegoCartasService->CartaDetalle($rut_paciente);

            return response()->json([
                'success' => true,
                'message' => $carta === null
                    ? 'Paciente sin chequeos registrados'
                    : 'Carta obtenida correctamente',
                'data' => $carta,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo la carta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Configuración de la carta: bandas de los dos ejes y catálogo de atributos.
     */
    public function Niveles()
    {
        try {
            $niveles = $this->juegoCartasService->Niveles();

            return response()->json([
                'success' => true,
                'message' => 'Niveles obtenidos correctamente',
                'data' => $niveles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo los niveles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
