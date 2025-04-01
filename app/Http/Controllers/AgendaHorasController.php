<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AgendaHoras;
use App\Models\Servicios;

class AgendaHorasController extends Controller {


    public function Health(){
        return "Bienvenido a ergosanitas.com";
    }

    public function getAgenda(){

        $resAgendaHoras = AgendaHoras::orderBy("id","desc")->get();
        $resArray = array();

        foreach($resAgendaHoras as $agendaHoras) {

            $resArray[] = array(
                "id"                     => $agendaHoras->id,
                "nombre_paciente"        => $agendaHoras->nombre_paciente,
                "rut_paciente"           => $agendaHoras->rut_paciente,
                "edad_paciente"          => $agendaHoras->edad_paciente,
                "direccion_paciente"     => $agendaHoras->direccion_paciente,
                "email_paciente"         => $agendaHoras->email_paciente,
                "celular_paciente"       => $agendaHoras->celular_paciente,
                "sexo_paciente"          => $agendaHoras->sexo_paciente,
                "servicios_id"           => $agendaHoras->servicios_id,
                "comuna_paciente"        => $agendaHoras->comuna_paciente,
                "pagado_paciente"        => $agendaHoras->pagado_paciente,
                "fecha_reserva_paciente" => $agendaHoras->fecha_reserva_paciente,
            );
        }
        return response()->json($resArray,200);
    }


    public function Store(Request $request) {

        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array('response' => array(
                'status' => 'Bad Request',
                'mensaje' => 'Error en momento de ejecucion!!!'));

            return response()->json($array,400);

        }

        try {

            $nombreServicio           = $request->servicios_name;
            $resServicios = Servicios::where(['nombre' => $nombreServicio])->firstOrFail();

            $agendaHoras = new AgendaHoras;

            $agendaHoras->nombre_paciente        = $request->nombre_paciente;
            $agendaHoras->rut_paciente           = $request->rut_paciente;
            $agendaHoras->edad_paciente          = $request->edad_paciente;
            $agendaHoras->direccion_paciente     = $request->direccion_paciente;
            $agendaHoras->email_paciente         = $request->email_paciente;
            $agendaHoras->celular_paciente       = $request->celular_paciente;
            $agendaHoras->sexo_paciente          = $request->sexo_paciente;
            $agendaHoras->servicios_id           = $resServicios->id;
            $agendaHoras->comuna_paciente        = $request->comuna_paciente;
            $agendaHoras->pagado_paciente        = $request->pagado_paciente;
            $agendaHoras->fecha_reserva_paciente = $request->fecha_reserva_paciente;

            $agendaHoras->save();

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
