<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\CertificadoURl;

use App\Models\ChequeoCardiovascular;

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

        $rut_paciente   = $request->rut_paciente;
        $nombre         = ucwords(strtolower($request->nombre_paciente));

        // Verificar si el archivo existe
        if ($request->hasFile('file') && $request->file('file')->isValid()) {

            // Obtener el archivo
            $file = $request->file('file');

            // Obtener la extensión del archivo
            $extension = $file->getClientOriginalExtension();

            // Definir la ruta de destino (Carpeta "Certificado")
            $destinationPath = public_path('Certificado'); // o puedes usar 'storage_path('app/public/Certificado')'


            // Crear el nombre completo con el rut del paciente y la extensión
            $fileName = $rut_paciente . '.' . $extension;

            // Mover el archivo al destino
            $file->move($destinationPath, $fileName);


            $url_pdf=env('API_PATH_CER').'/'.$fileName;
            $name_pdf = str_replace(' ', '-', $nombre);
            $titulo = $nombre;


            $save                   = new CertificadoURl;
            $save->rut_paciente     = $rut_paciente;
            $save->url_pdf          = $url_pdf;
            $save->name_pdf         = $name_pdf;
            $save->titulo           = $titulo;

            $save->save();

            $chequeoCardiovascular = ChequeoCardiovascular::where(['rut' => $rut_paciente])->firstOrFail();
            $chequeoCardiovascular->status         = 'ECG FOTO';
            $chequeoCardiovascular->save();


            return response()->json([
                'success' => true,
                'message' => 'Archivo subido con éxito.',
            ]);

        return response()->json(['message' => 'Archivo subido correctamente', 'file' => $fileName]);
        }

    }

}
