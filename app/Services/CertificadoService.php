<?php
namespace App\Services;
use App\Models\CertificadoURl;

class CertificadoService {

    public function UpdateRutCertificado($id_chequeo,$rut_paciente) {

        try {
            $ecg = CertificadoURl::where('id_chequeo', $id_chequeo)->firstOrFail();
            $ecg->rut_paciente = $rut_paciente;
            $ecg->save();
            return $ecg;
        } catch (\Exception $e) {
            return null;
        }
    }

}
