<?php

namespace App\Http\Controllers;

use App\Models\Servicios;
use Illuminate\Http\Request;

class ServiciosController extends Controller {

    public function index() {

        $resServicios = Servicios::where(['activo' => 's'])->firstOrFail();
        $resServicios = $resServicios->orderBy("id","desc")->get();
        return response()->json($resServicios,200);
    }

    public function show(string $nombre)
    {
        $resJugadores = Servicios::where(['nombre' => $nombre])->firstOrFail();

        $resArray = array(
            "id" => $resJugadores->id,
            "precio" => $resJugadores->precio
        );

        return response()->json($resJugadores,200);
    }

    public function LikeServices(Request $request){

        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array(
                'status' => 'Bad Request',
                'mensaje' => 'Http NO trae Datos para Procesar');
        
            return response()->json($array,400);

        }

        try {
            $textoValue = $request->textoValue;
            //$resServicios = Servicios::where('nombre', 'like', '%' . $textoValue . '%')
            $resServicios = Servicios::
            whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($textoValue) . '%'])    
            ->orderBy('id', 'desc')
            ->get();

            return response()->json($resServicios,200);
        }
        catch (\Exception $e) {
            
            $array = array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage());
        
            return response()->json($array,500);
        
        }
    }
    
}
