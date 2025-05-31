<?php

namespace App\Http\Controllers;
use App\Services\IncidenciaServicee;
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

    public function IncidenciaCreate(Request $request){

        $nombres     = $request->nombres;
        $edad        = $request->edad;
        $deporte     = $request->deporte;
        $tipoLesion  = $request->tipo_lesion;
        $ubicacion   = $request->ubicacion;
        $parteCuerpo = $request->parte_cuerpo;
        $descripcion = $request->descripcion;
        $primerosAuxilios = $request->primeros_auxilios;
        $gravedad    = $request->gravedad;
        $estado      = $request->estado;
        $user_email      = $request->user_email;


        $perfilId = $this->incidentesService->IncidenciaCreate(
            $nombres,$edad,$deporte,$tipoLesion,
            $ubicacion,$parteCuerpo,$descripcion,
            $primerosAuxilios,$gravedad,$estado,$user_email
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


}
