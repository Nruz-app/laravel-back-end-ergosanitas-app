<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Params;
use App\Models\CertificadoURl;
use App\Services\EstadisticasService;
use App\Services\CertificadoService;
use App\Models\ChequeoCardiovascular;

class CertificadoUrlController extends Controller {

    protected $estadisticasService;
    protected $certificadoService;

    public function __construct(
        EstadisticasService $estadisticasService,
        CertificadoService $certificadoService
    ){
        $this->estadisticasService = $estadisticasService;
        $this->certificadoService = $certificadoService;
    }
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

            $param = Params::where(
                'descripcion',
                'VALOR-ECG'
            )->firstOrFail();

            $valor_ecg = (float) $param->valor;

            $periodo = date('Y-m-d');

            $this->estadisticasService->PagoMensual(
                $periodo,
                $chequeoCardiovascular->user_email,
                $valor_ecg,
                "ADD"
            );

            return response()->json([
                'success' => true,
                'message' => 'Archivo subido con éxito.',
            ]);

            //return response()->json(['message' => 'Archivo subido correctamente', 'file' => $fileName]);
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
    //ValidaCertificado
    public function ValidaCertificado(Request $request)
    {
        $rut_paciente = $request->rut_paciente;

        try {

            $certificado = CertificadoURL::query()
                ->join('chequeo_cardiovascular as cc', function ($join) {
                    $join->on('certificado_url.id_chequeo', '=', 'cc.id')
                        ->on('certificado_url.rut_paciente', '=', 'cc.rut');
                })
                ->whereIn('cc.status', ['REVISION MEDICA'])
                ->when($rut_paciente, function ($query) use ($rut_paciente) {
                    $query->where('cc.rut', $rut_paciente);
                })
                ->orderByDesc('cc.id')
                ->select(
                    'certificado_url.url_pdf',
                    'certificado_url.name_pdf',
                    'certificado_url.titulo'
                )
                ->first();

            if ($certificado) {
                return response()->json([
                    'status' => 200,
                    'url_pdf' => $certificado->url_pdf,
                    'name_pdf' => $certificado->name_pdf,
                    'titulo' => $certificado->titulo
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
    public function CargaMasivaEcg(Request $request)
    {
        try {

            $request->validate([
                'files'   => 'required|array',
                'files.*' => 'required|file'
            ]);

            $procesados = [];

            foreach ($request->file('files') as $file) {

                try {
                    //18222333-1.pdf
                    $nombreOriginal = $file->getClientOriginalName();

                    $rut_paciente = pathinfo($nombreOriginal,PATHINFO_FILENAME);

                    $chequeo = ChequeoCardiovascular::where('rut',$rut_paciente)
                        ->latest('id')
                        ->first();

                    if (!$chequeo) {

                        $procesados[] = [
                            'archivo'      => $nombreOriginal,
                            'rut'          => '-',
                            'id_chequeo'   => '-',
                            'nombre'       => '-',
                            'status'       => '-',
                            'resultado'    => 'ERROR',
                            'mensaje'      => 'No existe chequeo para el rut'
                        ];

                        continue;
                    }

                    //SUBIR ARCHIVO

                    $this->certificadoService->subirCertificado($file, $rut_paciente,
                        $chequeo->id,$request->derivado_medico);

                    $procesados[] = [
                        'archivo'      => $nombreOriginal,
                        'rut'          => $chequeo->rut,
                        'id_chequeo'   => $chequeo->id,
                        'nombre'       => $chequeo->nombre,
                        'status'       => 'ECG FOTO',
                        'resultado'    => 'OK',
                        'mensaje'      => 'Archivo cargado correctamente'
                    ];

                }
                catch (\Exception $e) {

                    $procesados[] = [
                        'archivo'      => $nombreOriginal ?? '-',
                        'rut'          => $rut_paciente ?? '-',
                        'id_chequeo'   => '-',
                        'nombre'       => '-',
                        'status'       => '-',
                        'resultado'    => 'ERROR',
                        'mensaje'      => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'total' => count($procesados),
                'procesados' => $procesados
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
