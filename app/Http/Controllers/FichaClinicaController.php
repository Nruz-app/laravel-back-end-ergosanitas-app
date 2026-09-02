<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FichaClinicaService;


class FichaClinicaController extends Controller
{
    //
    protected $fichaClinicaService;

    public function __construct(FichaClinicaService $fichaClinicaService)
    {
        $this->fichaClinicaService = $fichaClinicaService;
    }


    public function FichaClinica(string $rut_paciente)
    {
        try {
            $fichaClinica = $this->fichaClinicaService->FichaClinica($rut_paciente);

            return response()->json([
                'success' => true,
                'message' => 'Datos guardados correctamente',
                'data' => $fichaClinica
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error procesando IA',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
