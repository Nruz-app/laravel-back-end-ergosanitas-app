<?php
namespace App\Services;

use App\Models\ChequeoCardiovascular;

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
}
