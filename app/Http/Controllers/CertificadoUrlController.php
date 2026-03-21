<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\CertificadoURl;

use App\Models\ChequeoCardiovascular;

class CertificadoUrlController extends Controller {

    public function ValidarRut(string $rut_paciente) {

        try {
            $existe = CertificadoURl::where('rut_paciente', $rut_paciente)->exists();

            if ($existe) {
                return response()->json([
                    'status' => 200,
                    'mensaje' => 'El certificado existe.']);
            } else {
                return response()->json([
                    'status' => 404,
                    'mensaje' => 'No se encontró un Certificado Registrado para el RUT: ' . $rut_paciente]);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => 'Error en la consulta: ' . $e->getMessage()]);
        }

    }

    public function showCertificado(string $rut_paciente)
    {
        try {

            $certificado = CertificadoURl::where('rut_paciente', $rut_paciente)
                ->latest('id')   // ORDER BY id DESC
                ->first();      // LIMIT 1

            if (!$certificado) {
                return response()->json([
                    'status' => 404,
                    'mensaje' => 'No se encontró certificado para el rut ' . $rut_paciente
                ], 404);
            }

            return response()->json($certificado, 200);

        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'mensaje' => $e->getMessage()
            ], 500);

        }
    }

    public function FileUploadCer(Request $request) {

        $rut_paciente    = $request->rut_paciente;
        $id_paciente     = $request->id_paciente;
        $derivado_medico = $request->derivado_medico;
        $nombre         = ucwords(strtolower($request->nombre_paciente));

        // Verificar si el archivo existe
        if ($request->hasFile('file') && $request->file('file')->isValid()) {

            CertificadoURl::where('rut_paciente', $rut_paciente)
                ->where('id_chequeo', $id_paciente)
                ->delete();
            // Obtener el archivo
            $file = $request->file('file');

            // Obtener la extensión del archivo
            $extension = $file->getClientOriginalExtension();

            // Definir la ruta de destino (Carpeta "Certificado")
            $destinationPath = public_path('Certificado'); // o puedes usar 'storage_path('app/public/Certificado')'


            // Crear el nombre completo con el rut del paciente y la extensión
            $fileName = $rut_paciente . '-'.$id_paciente. '.' . $extension;

            // Mover el archivo al destino
            $file->move($destinationPath, $fileName);


            $url_pdf=env('API_PATH_CER').'/'.$fileName;
            $name_pdf = str_replace(' ', '-', $nombre);
            $titulo = $nombre;


            $save                   = new CertificadoURl;
            $save->rut_paciente     = $rut_paciente;
            $save->id_chequeo       = $id_paciente;
            $save->url_pdf          = $url_pdf;
            $save->name_pdf         = $name_pdf;
            $save->titulo           = $titulo;
            $save->derivado_medico  = $derivado_medico;

            $save->save();

            $chequeoCardiovascular = ChequeoCardiovascular::where(['id' => $id_paciente])->firstOrFail();
            $chequeoCardiovascular->status         = 'ECG FOTO';
            $chequeoCardiovascular->save();


            return response()->json([
                'success' => true,
                'message' => 'Archivo subido con éxito.',
            ]);

        return response()->json(['message' => 'Archivo subido correctamente', 'file' => $fileName]);
        }

    }

    public function PathUrlCertificado(Request $request)
    {
        $rut_paciente = $request->rut_paciente;
        $id_paciente  = $request->id_paciente;

        try {

            $query = CertificadoURl::query();

            if ($rut_paciente) {
                $query->where('rut_paciente', $rut_paciente);
            }

            if ($id_paciente) {
                $query->where('id_chequeo', $id_paciente);
            }

            $certificadoURl = $query
                ->orderBy('id', 'desc')
                ->limit(1)
                ->first();

            if ($certificadoURl) {
                return response()->json([
                    'status' => 200,
                    'url_pdf' => $certificadoURl->url_pdf,
                    'name_pdf' => $certificadoURl->name_pdf,
                    'titulo' => $certificadoURl->titulo
                ]);
            }

            return response()->json([
                'status' => 404,
                'mensaje' => 'No se encontró un certificado con los filtros enviados'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'Error en la consulta: ' . $e->getMessage()
            ]);
        }
    }
}
