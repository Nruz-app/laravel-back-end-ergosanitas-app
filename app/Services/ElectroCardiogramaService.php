<?php
namespace App\Services;
use App\Models\ElectroCardiograma;

class ElectroCardiogramaService {

    public function UpdateRutECG($id_chequeo,$rut_paciente) {

        try {
            $ecg = ElectroCardiograma::where('id_chequeo', $id_chequeo)->firstOrFail();
            $ecg->rut_paciente = $rut_paciente;
            $ecg->save();
            return $ecg;
        } catch (\Exception $e) {
            return null;
        }
    }

}
