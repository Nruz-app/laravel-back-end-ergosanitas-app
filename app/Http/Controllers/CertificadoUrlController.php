<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\CertificadoURl;

class CertificadoUrlController extends Controller {

    public function show(string $rut_paciente) {

        try {
 
            $certificadoURl = CertificadoURl::where(['rut_paciente' => $rut_paciente])
            ->orderBy('id', 'desc')
            ->first();


            return response()->json($certificadoURl,200);

        }
        catch (\Exception $e) {
            
            $array = array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage());
        
            return response()->json($array,500);
        
        }
    }


    public function FileUpload(Request $request) {


        $validated = $request->validate([
           // 'file' => 'required|file|mimes:pdf|max:5048'
           'file' => 'required|file|mimes:jpg,png|max:5048'
        ],
        [
            'file.requierd' => 'No Existe Archivo',
            'file.mines' => 'El Archivo debe ser PDF'   
        ]);
    

        $rut_paciente   = $request->rut_paciente;
        $nombre         = ucwords(strtolower($request->nombre_paciente));
        
        copy($_FILES['file']['tmp_name'],'Certificado/'.$rut_paciente.'.png');

        $url_pdf=env('API_PATH_CER').'/'.$rut_paciente.'.png';
        $name_pdf = 'Certificado-'.str_replace(' ', '-', $nombre);
        $titulo = 'Certificado '.$nombre;

        $save                   = new CertificadoURl;
        $save->rut_paciente     = $rut_paciente;
        $save->url_pdf          = $url_pdf;
        $save->name_pdf         = $name_pdf;
        $save->titulo           = $titulo;

        $save->save();

        return response()->json([
            'success' => true,
            'message' => 'Archivo subido con éxito.',
        ]);
        
    }
    
}
