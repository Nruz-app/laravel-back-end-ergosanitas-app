<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\PagoMensual;
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
    public function PagoMensual(Request $request) {
        try {

            $user_email = $request->user_email;
            $valor_ecg = $request->valor_ecg;
            $periodo = $request->periodo;
            $modo  = $request->modo;

            $responseService = $this->estadisticasService->PagoMensual($periodo, $user_email, $valor_ecg,$modo);
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
    public function EstadisticaPagoMensual() {
        try {
             $responseService = $this->estadisticasService->EstadisticaPagoMensual();
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
    public function deletePagoMensual(Request $request)
    {
        try {
            $club    = $request->user_email;
            $periodo = $request->periodo;

            $deleted = PagoMensual::where('club', $club)
                ->where('periodo', $periodo)
                ->delete();

            return response()->json([
                'status' => 'ok',
                'deleted' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error en ejecucion',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }
    public function AgendaMensual(Request $request) {
        try {
             $periodo = $request->periodo;
             $responseService = $this->estadisticasService->AgendaMensual($periodo);
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
    public function EstadisticaPagoMDC() {
        try {
             $responseService = $this->estadisticasService->EstadisticaPagoMDC();
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

    public function UpdatePagoMensual(Request $request) {
        try {
             $periodo = $request->periodo;
             $responseService = $this->estadisticasService->UpdatePagoMensual($periodo);
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
