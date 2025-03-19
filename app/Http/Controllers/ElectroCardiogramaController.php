<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ElectroCardiograma;

use App\Models\ChequeoCardiovascular;

class ElectroCardiogramaController extends Controller
{
    //
    public function FindByRut(Request $request) {

        $rut_paciente = $request->rut_paciente;

        try {
            $electroCardiograma = ElectroCardiograma::where(['rut_paciente' => $rut_paciente])
                ->get();

            return response()->json($electroCardiograma,200);
        }
        catch (\Exception $e) {

            $array = array(
                'rut_paciente'                 => $rut_paciente,
                'estado_paciente'              =>  'N/A',
                'frecuencia_cardiaca_paciente' => 0,
                'derivacion_paciente'          => '',
                'observacion_paciente'         => '',
                'imc_paciente'         => '',
            );

            return response()->json($array,200);

        }

    }




    public function Save(Request $request) {

        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array('response' => array(
                'status' => 'Bad Request',
                'mensaje' => 'Error en momento de ejecucion!!!'));

            return response()->json($array,400);

        }

        try {

            ElectroCardiograma::where(['id_chequeo' => $request->id_paciente])->delete();

            $electroCardiograma = new ElectroCardiograma;

            $electroCardiograma->rut_paciente    = $request->rut_paciente;
            $electroCardiograma->id_chequeo      = $request->id_paciente;
            $electroCardiograma->estado_paciente = $request->estado_paciente;
            $electroCardiograma->frecuencia_cardiaca_paciente = $request->frecuencia_cardiaca_paciente;
            $electroCardiograma->derivacion_paciente  = $request->derivacion_paciente;
            $electroCardiograma->observacion_paciente = $request->observacion_paciente;
            $electroCardiograma->imc_paciente = $request->imc_paciente;

            $electroCardiograma->save();

            $chequeoCardiovascular = ChequeoCardiovascular::where(['id' => $request->id_paciente])->firstOrFail();
            $chequeoCardiovascular->status         = 'REVISION MEDICA';
            $chequeoCardiovascular->save();


            $array = array('response' => array(
                'status' => 'OK',
                'mensaje' => 'Reserva con Exito'));

            return response()->json($array,201);
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
