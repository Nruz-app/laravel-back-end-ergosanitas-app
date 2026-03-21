<?php

namespace App\Services;

use App\Models\IncidentesDeportivos;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class IncidentesService {

    public function IncidenciaCreate($nombres,$edad,$deporte,$tipoLesion,$ubicacion,
    $parteCuerpo,$descripcion,$primerosAuxilios,$gravedad,$estado,$user_email,$liga,
    $club_deportivo,$categoria,$rut_paciente) {

        try {
            $incidentesDeportivosModel                 = new IncidentesDeportivos;
            $incidentesDeportivosModel->nombres        = $nombres;
            $incidentesDeportivosModel->edad           = $edad;
            $incidentesDeportivosModel->deporte        = $deporte;
            $incidentesDeportivosModel->tipo_lesion    =  $tipoLesion;
            $incidentesDeportivosModel->ubicacion      =  $ubicacion;
            $incidentesDeportivosModel->parte_cuerpo   =  $parteCuerpo;
            $incidentesDeportivosModel->descripcion    =  $descripcion;
            $incidentesDeportivosModel->primeros_auxilios =  $primerosAuxilios;
            $incidentesDeportivosModel->gravedad       =  $gravedad;
            $incidentesDeportivosModel->estado         =  $estado;
            $incidentesDeportivosModel->user_email     =  $user_email;
            $incidentesDeportivosModel->liga           =  $liga;
            $incidentesDeportivosModel->club_deportivo =  $club_deportivo;
            $incidentesDeportivosModel->categoria      =  $categoria;
            $incidentesDeportivosModel->rut_paciente   =  $rut_paciente;
            $incidentesDeportivosModel->save();
            return $incidentesDeportivosModel->id;
        }
        catch (\Exception $e) {
            Log::error('Error en IncidenciaCreate: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    public function FindByUser($user_email) {

        try {
            $incidentesDeportivosModel = IncidentesDeportivos::where('club_deportivo', $user_email)
                ->orderBy('id', 'desc')
                ->get();
            return $incidentesDeportivosModel;
        } catch (\Exception $e) {
            return null;
        }

    }

    public function CountClub($user_email) {

        try {
            $incidentesDeportivosModel = IncidentesDeportivos::where('club_deportivo', $user_email)
                ->orderBy('id', 'desc')
                ->count();
            return $incidentesDeportivosModel;
        } catch (\Exception $e) {
            return null;
        }

    }

    public function CountLiga($user_email) {
        try {
            $cantidadLigas = IncidentesDeportivos::where('club_deportivo', $user_email)
            ->distinct('liga')
            ->count('liga');

            return $cantidadLigas;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function CountGravedad($user_email) {

        try {
            $incidentesDeportivosModel = IncidentesDeportivos::where('club_deportivo', $user_email)
                ->where('gravedad', 'Leve (sin tiempo fuera)')
                ->orderBy('id', 'desc')
                ->count();
            return $incidentesDeportivosModel;
        } catch (\Exception $e) {
            return null;
        }

    }

    public function LesionFrecuente($user_email) {

        try {
            $tipoLesionMasComun = IncidentesDeportivos::select('tipo_lesion', DB::raw('COUNT(*) as cantidad'))
                ->where('club_deportivo', $user_email)
                ->groupBy('tipo_lesion')
                ->orderByDesc('cantidad')
                ->first(); // devuelve el más frecuente

            return $tipoLesionMasComun;
        } catch (\Exception $e) {
            return null;
        }

    }

    public function LigaCasos($user_email) {

        try {
            $tipoLesionMasComun = IncidentesDeportivos::select('liga', DB::raw('COUNT(*) as cantidad'))
                ->where('club_deportivo', $user_email)
                ->groupBy('liga')
                ->orderByDesc('cantidad')
                ->first(); // devuelve el más frecuente

            return $tipoLesionMasComun;
        } catch (\Exception $e) {
            return null;
        }

    }

    public function sp_estadistica_liga($user_email) {

        try {
            $results = IncidentesDeportivos::sp_estadistica_liga($user_email);
            // Convertir el resultado a un objeto (si es necesario)
            $resultadoJson = json_decode($results[0]->resultado_json);
            return $resultadoJson;

        } catch (\Exception $e) {
            return null;
        }

    }

    public function sp_estadistica_categoria($user_email) {
        try {
            $results = IncidentesDeportivos::SP_estadistica_categoria($user_email);
            // Convertir el resultado a un objeto (si es necesario)
            $resultadoJson = json_decode($results[0]->resultado_json);
            return $resultadoJson;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function sp_estadistica_lesiones($user_email) {
        try {
            $results = IncidentesDeportivos::sp_estadistica_lesiones($user_email);
            // Convertir el resultado a un objeto (si es necesario)
            $resultadoJson = json_decode($results[0]->resultado_json);
            return $resultadoJson;

        } catch (\Exception $e) {
            return null;
        }
    }

     public function sp_estadistica_parte_cuerpo($user_email) {
        try {
            $results = IncidentesDeportivos::sp_estadistica_parte_cuerpo($user_email);
            // Convertir el resultado a un objeto (si es necesario)
            $resultadoJson = json_decode($results[0]->resultado_json);
            return $resultadoJson;

        } catch (\Exception $e) {
            return null;
        }
    }

     public function sp_estadistica_lesiones_fechas($user_email) {
        try {
            $results = IncidentesDeportivos::sp_estadistica_lesiones_fechas($user_email);
            // Convertir el resultado a un objeto (si es necesario)
            $resultadoJson = json_decode($results[0]->resultado_json);
            return $resultadoJson;

        } catch (\Exception $e) {
            return null;
        }
    }
}
