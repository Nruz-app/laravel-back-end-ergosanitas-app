<?php

namespace App\Services;

use App\Models\IncidentesDeportivos;

class IncidentesService {

    public function IncidenciaCreate($nombres,$edad,$deporte,$tipoLesion,$ubicacion,$parteCuerpo,$descripcion,$primerosAuxilios,$gravedad,$estado,$user_email) {

        try {
            $incidentesDeportivosModel               = new IncidentesDeportivos;
            $incidentesDeportivosModel->nombres      = $nombres;
            $incidentesDeportivosModel->edad         = $edad;
            $incidentesDeportivosModel->deporte      = $deporte;
            $incidentesDeportivosModel->tipo_lesion  =  $tipoLesion;
            $incidentesDeportivosModel->ubicacion    =  $ubicacion;
            $incidentesDeportivosModel->parte_cuerpo =  $parteCuerpo;
            $incidentesDeportivosModel->descripcion  =  $descripcion;
            $incidentesDeportivosModel->primeros_auxilios =  $primerosAuxilios;
            $incidentesDeportivosModel->gravedad     =  $gravedad;
            $incidentesDeportivosModel->estado       =  $estado;
            $incidentesDeportivosModel->user_email   =  $user_email;
            $incidentesDeportivosModel->save();
            return $incidentesDeportivosModel->id;
        }
        catch (\Exception $e) {
            return null;
        }
    }
    public function FindByUser($user_email) {

        try {
            $incidentesDeportivosModel = IncidentesDeportivos::where('user_email', $user_email)
                ->orderBy('id', 'desc')
                ->get();
            return $incidentesDeportivosModel;
        } catch (\Exception $e) {
            return null;
        }

    }

}
