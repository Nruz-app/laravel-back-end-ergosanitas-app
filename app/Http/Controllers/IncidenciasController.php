<?php

namespace App\Http\Controllers;
use App\Services\IncidentesService;
use Illuminate\Http\Request;

class IncidenciasController extends Controller
{
    //
    protected $incidentesService;

    public function __construct(
        IncidentesService $incidentesService ){
        $this->incidentesService = $incidentesService;
    }

    public function CountClub(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->CountClub($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }

    public function CountGravedad(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->CountGravedad($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }

    public function CountLiga(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->CountLiga($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }

    public function IncidenciaCreate(Request $request){

        $nombres        = $request->nombres;
        $edad           = $request->edad;
        $deporte        = $request->deporte;
        $tipoLesion     = $request->tipo_lesion;
        $ubicacion      = $request->ubicacion;
        $parteCuerpo    = $request->parte_cuerpo;
        $descripcion    = $request->descripcion;
        $primerosAuxilios = $request->primeros_auxilios;
        $gravedad       = $request->gravedad;
        $estado         = $request->estado;
        $user_email     = $request->user_email;
        $liga           = $request->liga;
        $club_deportivo = $request->club_deportivo;
        $categoria      = $request->categoria;
        $rut_paciente   = $request->rut_paciente;


        $perfilId = $this->incidentesService->IncidenciaCreate(
            $nombres,$edad,$deporte,$tipoLesion,
            $ubicacion,$parteCuerpo,$descripcion,
            $primerosAuxilios,$gravedad,$estado,$user_email,
            $liga,$club_deportivo,$categoria,$rut_paciente
        );

        if($perfilId){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia creada correctamente',
                'data' => $perfilId
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear la incidencia'
            ], 500);
        }

    }

    public function FindByUserEmail(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->FindByUser($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }

    public function LesionFrecuente(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->LesionFrecuente($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }

    public function LigaCasos(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->LigaCasos($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }

    public function sp_estadistica_liga(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->sp_estadistica_liga($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }

    public function sp_estadistica_categoria(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->sp_estadistica_categoria($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }

    public function sp_estadistica_lesiones(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->sp_estadistica_lesiones($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }

     public function sp_estadistica_parte_cuerpo(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->sp_estadistica_parte_cuerpo($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }

     public function sp_estadistica_lesiones_fechas(string $user_email){

        $incidentesDeportivosModel = $this->incidentesService->sp_estadistica_lesiones_fechas($user_email);

        if($incidentesDeportivosModel){
            return response()->json([
                'status' => 'success',
                'message' => 'Incidencia lista correctamente',
                'data' => $incidentesDeportivosModel
            ], 201);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al listar las incidencias'
            ], 500);
        }

    }


}
