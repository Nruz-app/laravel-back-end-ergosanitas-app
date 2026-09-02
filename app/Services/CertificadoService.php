<?php
namespace App\Services;
use App\Models\CertificadoURl;
use App\Models\ChequeoCardiovascular;
use App\Services\EstadisticasService;
use App\Models\Params;

class CertificadoService {

    protected $estadisticasService;

    public function __construct(EstadisticasService $estadisticasService) {
        $this->estadisticasService = $estadisticasService;
    }

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

     public function subirCertificado($file,string $rut_paciente,
        int $id_paciente,?string $derivado_medico = "NO") {

        CertificadoURl::where('rut_paciente', $rut_paciente)
            ->where('id_chequeo', $id_paciente)
            ->delete();

        $extension = $file->getClientOriginalExtension();

        $destinationPath = public_path('Certificado');

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $fileName = $rut_paciente . '-' . $id_paciente . '.' . $extension;

        $file->move($destinationPath, $fileName);

        $url_pdf = env('API_PATH_CER') . '/' . $fileName;

        $chequeoCardiovascular = ChequeoCardiovascular::findOrFail($id_paciente);

        $nombre = ucwords(
            strtolower($chequeoCardiovascular->nombre ?? '')
        );

        $name_pdf = str_replace(' ', '-', $nombre);

        $save = new CertificadoURl();

        $save->rut_paciente = $rut_paciente;
        $save->id_chequeo = $id_paciente;
        $save->url_pdf = $url_pdf;
        $save->name_pdf = $name_pdf;
        $save->titulo = $nombre;
        $save->derivado_medico = $derivado_medico;

        $save->save();

        $chequeoCardiovascular->status = 'ECG FOTO';
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

        return [
            'success' => true,
            'file' => $fileName
        ];
    }

}
