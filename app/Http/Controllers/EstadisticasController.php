<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ChequeoCardiovascular;

class EstadisticasController extends Controller
{
    //
    public function Estadistica_IMC(Request $request) {


        try {
            $user_email           = $request->user_email;

            $results = ChequeoCardiovascular::SP_estadistica_IMC($user_email);

            // Convertir el resultado a un objeto (si es necesario)
            $resultadoJson = json_decode($results[0]->resultado_json);

            return $resultadoJson;
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
