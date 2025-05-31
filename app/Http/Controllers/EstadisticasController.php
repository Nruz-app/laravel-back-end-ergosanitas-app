<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ChequeoCardiovascular;
use App\Services\EstadisticasService;
class EstadisticasController extends Controller
{
    //
    protected $estadisticasService;

    public function __construct(
        EstadisticasService $estadisticasService ){
        $this->estadisticasService = $estadisticasService;
    }

    public function EstadisticaIMC(Request $request) {

        try {
            $user_email           = $request->user_email;
            $responseService = $this->estadisticasService->EstadisticaIMC($user_email);
            return response()->json($responseService,200);
        }
        catch (\Exception $e) {
            // Retorna una respuesta con el error
            $array = array('response' => array(
                'status' => 'Error en ejecucion',
                'mensaje' => $e->getMessage()));

            return response()->json($array,500);
        }

    }
    public function EstadisticaPresion(Request $request) {
        try {
            $user_email = $request->user_email;
            $responseService = $this->estadisticasService->EstadisticaPresion($user_email);
            return response()->json($responseService,200);
        }
        catch (\Exception $e) {
            // Retorna una respuesta con el error
            $array = array('response' => array(
                'status' => 'Error en ejecucion',
                'mensaje' => $e->getMessage()));

            return response()->json($array,500);
        }
    }

    public function EstadisticaHemoglucotest(Request $request) {
        try {

            $user_email = $request->user_email;
            $responseService = $this->estadisticasService->SP_estadistica_hemoglucotest($user_email);
            return response()->json($responseService,200);
        }
        catch (\Exception $e) {

            // Retorna una respuesta con el error
            $array = array('response' => array(
                'status' => 'Error en ejecucion',
                'mensaje' => $e->getMessage()));

            return response()->json($array,500);

        }
    }

    public function EstadisticaSaturacion(Request $request) {
        try {

            $user_email = $request->user_email;
            $responseService = $this->estadisticasService->SP_estadistica_saturacion($user_email);
            return response()->json($responseService,200);
        }
        catch (\Exception $e) {

            // Retorna una respuesta con el error
            $array = array('response' => array(
                'status' => 'Error en ejecucion',
                'mensaje' => $e->getMessage()));

            return response()->json($array,500);

        }
    }
}
