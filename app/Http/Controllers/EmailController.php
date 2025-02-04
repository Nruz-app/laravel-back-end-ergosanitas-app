<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Mail\EmailMailable;
use Illuminate\Support\Facades\Mail;
use App\Models\AgendaHoras;

class EmailController extends Controller {


    public function EmailReservaHora(Request $request) {

        try {

            $rut_paciente           = $request->rut_paciente;

            $agendaHora = AgendaHoras::where('rut_paciente', $rut_paciente)
                ->orderBy('id', 'desc')
                ->first();

            $subject = 'Reserva de Hora Ergosanitas.com';
            $html = '';
            $html .= '<table><tr>';
            $html .= '<td>Nombre :</td>';
            $html .= '<td>'.$agendaHora->nombre_paciente.'</td>';
            $html .= '<td>Fecha :</td>';
            $html .= '<td>'.$agendaHora->fecha_reserva_paciente.'</td>';
            $html .= '</tr></table>';


            $correo = new EmailMailable($subject,$html);
            Mail::to($agendaHora->email_paciente)->send($correo);
            //Copia a ergosanitas
            Mail::to('ergosanitas@gmail.com')->send($correo);

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
