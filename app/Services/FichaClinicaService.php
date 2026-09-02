<?php

namespace App\Services;
use App\Models\FichaClinica;

class FichaClinicaService
{
    public function FichaClinica($rut_paciente) {

        $results = FichaClinica::SP_ficha_clinica($rut_paciente);

        // Convertir el resultado a un objeto (si es necesario)
        $resultadoJson = json_decode($results[0]->resultado_json);

        return $resultadoJson;
    }
}
