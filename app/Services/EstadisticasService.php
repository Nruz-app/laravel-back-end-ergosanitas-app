<?php
namespace App\Services;

use App\Models\ChequeoCardiovascular;
use App\Models\PagoMensual;
use Illuminate\Support\Facades\Log;

class EstadisticasService {

    public function EstadisticaPresion($user_email) {

        $results = ChequeoCardiovascular::sp_estadistica_presion($user_email);

        // Convertir el resultado a un objeto (si es necesario)
        $resultadoJson = json_decode($results[0]->resultado_json);

        return $resultadoJson;

    }
    public function EstadisticaIMC($user_email) {

        $results = ChequeoCardiovascular::SP_estadistica_IMC($user_email);

        // Convertir el resultado a un objeto (si es necesario)
        $resultadoJson = json_decode($results[0]->resultado_json);

        return $resultadoJson;

    }
    public function SP_estadistica_hemoglucotest($user_email) {

        $results = ChequeoCardiovascular::SP_estadistica_hemoglucotest($user_email);

        // Convertir el resultado a un objeto (si es necesario)
        $resultadoJson = json_decode($results[0]->resultado_json);

        return $resultadoJson;

    }
    public function SP_estadistica_saturacion($user_email) {

        $results = ChequeoCardiovascular::SP_estadistica_saturacion($user_email);

        // Convertir el resultado a un objeto (si es necesario)
        $resultadoJson = json_decode($results[0]->resultado_json);

        return $resultadoJson;

    }


    public function PagoMensual($periodo,$user_email, $valor_ecg,$modo) {

        $results = ChequeoCardiovascular::PagoMensual($periodo,$user_email, $valor_ecg,$modo);
        $resultadoJson = json_decode($results[0]->resultado);
        return $resultadoJson;
    }


    public function EstadisticaPagoMensual() {

        $results = ChequeoCardiovascular::SP_estadistica_monto();

        $resultadoJson = json_decode($results[0]->resultado);

        return $resultadoJson;
    }
    public function AgendaMensual($periodo) {

        $results = PagoMensual::AgendaMensual($periodo);

        $resultadoJson = json_decode($results[0]->resultado);

        return $resultadoJson;
    }
    public function EstadisticaPagoMDC() {

        $results = PagoMensual::SP_estadistica_monto_mdc();

        $resultadoJson = json_decode($results[0]->resultado);

        return $resultadoJson;
    }


    public function UpdatePagoMensual($periodo) {

        $results = PagoMensual::SP_update_pago_mensual($periodo);

        $resultadoJson = json_decode($results[0]->resultado);

        return $resultadoJson;
    }
    public function ChequeoPrompt($search) {
        $results = PagoMensual::SP_chequeos_prompt($search);
        $resultadoJson = json_decode($results[0]->resultado_json);
        return $resultadoJson;
    }
}
