<?php

namespace App\Http\Controllers;

use App\Models\ChequeoCardiovascular;
use Illuminate\Http\Request;

use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\UserMetadataService;
use App\Services\ChequeoCardiovascularService;
use App\Services\ChequeoCardiovascularPDFService;
use App\Services\ElectroCardiogramaService;
use App\Services\CertificadoService;

class ChequeoCardiovascularController extends Controller
{
    protected $userMetadataService;
    protected $chequeoCardiovascularService;
    protected $chequeoCardiovascularPDFService;
    protected $electroCardiogramaService;
    protected $certificadoService;


    // Inyectar servicios a través del constructor
    public function __construct(
        UserMetadataService $userMetadataService,
        ChequeoCardiovascularService $chequeoCardiovascularService,
        ChequeoCardiovascularPDFService $chequeoCardiovascularPDFService,
        ElectroCardiogramaService $electroCardiogramaService,
        CertificadoService $certificadoService )
    {
        $this->userMetadataService             = $userMetadataService;
        $this->chequeoCardiovascularService    = $chequeoCardiovascularService;
        $this->chequeoCardiovascularPDFService = $chequeoCardiovascularPDFService;
        $this->electroCardiogramaService       = $electroCardiogramaService;
        $this->certificadoService              = $certificadoService;
    }
    //
    public function HealthCheck(){
        return "Bienvenido a ergosanitas.com";
    }

    public function Index(){

        $resChequeoCardiovascular = ChequeoCardiovascular::orderBy("id","desc")->get();
        $resArray = $resChequeoCardiovascular->toArray();
        return response()->json($resArray,200);
    }

    public function FindByEmail(Request $request){

        try {

            $user_email = $request->user_email;

            $perfilId = $this->userMetadataService->getPerfilIdByEmail($user_email);

            $resChequeoCardiovascular = DB::table('chequeo_cardiovascular as cc')
                ->leftJoin('electro_cardiogranas as ec', 'cc.rut', '=', 'ec.rut_paciente');
            if($perfilId == 3) {
                $resChequeoCardiovascular->where('cc.user_email', $user_email);
            }
            // Seleccionar columnas y aplicar orden
            $resChequeoCardiovascular->select(
                'cc.*',
                DB::raw("DATE_FORMAT(cc.fecha_atencion, '%d/%m/%Y') as fecha_atencion"),
                DB::raw("DATE_FORMAT(cc.created_at, '%d/%m/%Y') as created_at") ,
                DB::raw("
                    CASE
                        WHEN cc.status = 'REVISION MEDICA'
                            AND MAX(ec.estado_paciente) IS NOT NULL
                        THEN CONCAT('Diag. Card. - ', MAX(ec.estado_paciente))

                        WHEN cc.status = 'REVISION MEDICA'
                        THEN 'En Rev. Cardio'

                        ELSE cc.status
                    END as estado_paciente
                "),
                DB::raw("COALESCE(ec.frecuencia_cardiaca_paciente, '-') as frecuencia_cardiaca_paciente"),
                DB::raw("COALESCE(ec.derivacion_paciente, '-') as derivacion_paciente"),
                DB::raw("COALESCE(ec.observacion_paciente, '-') as observacion_paciente")
            )
            ->orderBy('cc.id', 'desc')
            ->get();


            $resArray = json_decode(json_encode($resChequeoCardiovascular->get()), true);

            return response()->json($resArray,200);
         }
         catch (\Exception $e) {

            $array = array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage());

            return response()->json($array,500);

        }
    }

    public function LikeChequeo(Request $request){

        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array(
                'status' => 'Bad Request',
                'mensaje' => 'Http NO trae Datos para Procesar');

            return response()->json($array,400);

        }

        try {

            $textoValue = $request->textoValue;


            $resChequeo = ChequeoCardiovascular::where('rut', 'like', '%' . $textoValue . '%')
                ->orWhere('nombre', 'like', '%' . $textoValue . '%')
                ->get();


            $resArray = $resChequeo->toArray();
            return response()->json($resArray,200);
        }
        catch (\Exception $e) {

            $respuesta = $this->Index();

            if ($respuesta->original) {

                return response()->json($respuesta->original,200);

            }
            // Retorna una respuesta con el error
            $array = array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage());

            return response()->json($array,500);

        }
    }

    public function LikeChequeoUser(Request $request){

        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array(
                'status' => 'Bad Request',
                'mensaje' => 'Http NO trae Datos para Procesar');

            return response()->json($array,400);

        }

        try {

            $textoValue = $request->textoValue;
            $user_email = $request->user_email;

            $perfilId = $this->userMetadataService->getPerfilIdByEmail($user_email);

            $resChequeoCardiovascular = DB::table('chequeo_cardiovascular as cc')
                ->leftJoin('electro_cardiogranas as ec', 'cc.rut', '=', 'ec.rut_paciente');
            if($perfilId ==3) {
                $resChequeoCardiovascular->where('cc.user_email', $user_email);
            }

            $resChequeoCardiovascular->where(function ($query) use ($textoValue) {
                $query->where('cc.rut', 'like', '%' . $textoValue . '%')
                      ->orWhere('cc.nombre', 'like', '%' . $textoValue . '%');
            });

            // Seleccionar columnas y aplicar orden
            $resChequeoCardiovascular->select(
                'cc.*',
                DB::raw("DATE_FORMAT(cc.fecha_atencion, '%d/%m/%Y') as fecha_atencion") ,
                DB::raw("DATE_FORMAT(cc.created_at, '%d/%m/%Y') as created_at") ,
                DB::raw("
                    CASE
                        WHEN cc.status = 'REVISION MEDICA'
                            AND MAX(ec.estado_paciente) IS NOT NULL
                        THEN CONCAT('Diag. Card. - ', MAX(ec.estado_paciente))

                        WHEN cc.status = 'REVISION MEDICA'
                        THEN 'En Rev. Cardio'

                        ELSE cc.status
                    END as estado_paciente
                "),
                DB::raw("COALESCE(ec.frecuencia_cardiaca_paciente, '-') as frecuencia_cardiaca_paciente"),
                DB::raw("COALESCE(ec.derivacion_paciente, '-') as derivacion_paciente"),
                DB::raw("COALESCE(ec.observacion_paciente, '-') as observacion_paciente")
            )
            ->orderBy('cc.id', 'desc')
            ->get();

            $resArray = json_decode(json_encode($resChequeoCardiovascular->get()), true);
            return response()->json($resArray,200);
        }
        catch (\Exception $e) {

            $array = array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage());

            return response()->json($array,500);

        }
    }

    public function ChequeoRut (int $id_paciente) {

        try {

            $chequeoCardiovascular = ChequeoCardiovascular::where(['id' => $id_paciente])
                ->orderBy('id', 'desc')
                ->first();
            $resArray = $chequeoCardiovascular->toArray();

            return response()->json($resArray,200);

        }
        catch (\Exception $e) {

            // Retorna una respuesta con el error
            $array = array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage());

            return response()->json($array,500);

        }
    }

    public function deleteById (string $id) {

        try {

            ChequeoCardiovascular::where('id', (int) $id)->delete();

            $array = array(
                'status' => 'OK',
                'mensaje' => 'Delete con exitoaaa'.$id);

            return response()->json($array,200);

        }
        catch (\Exception $e) {

            // Retorna una respuesta con el error
            $array = array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage());

            return response()->json($array,500);

        }
    }

    public function Store(Request $request)
    {
        // Validar request vacío
        if (empty($request->all())) {

            return response()->json([
                'response' => [
                    'status'  => 'Bad Request',
                    'mensaje' => 'Ingrese Valores'
                ]
            ], 400);
        }

        // Validaciones básicas
        $request->validate([
            'nombre'     => 'required|string',
            'rut'        => 'required|string',
            'user_email' => 'required|email'
        ]);

        try {

            $perfilId = $this->userMetadataService
                ->getPerfilIdByEmail($request->user_email);

            $save = new ChequeoCardiovascular;

            $save->nombre                = ucwords(strtolower(trim((string) $request->nombre)));
            $save->rut                   = trim((string) $request->rut);
            $save->edad                  = $request->edad;
            $save->estatura              = str_replace(',', '.', (string) $request->estatura);
            $save->peso                  = $request->peso;
            $save->hemoglucotest         = $request->hemoglucotest;
            $save->pulso                 = $request->pulso;
            $save->presionArterial       = $request->presionArterial;
            $save->saturacionOxigeno     = $request->saturacionOxigeno;
            $save->temperatura           = $request->temperatura;
            $save->presion_sistolica     = $request->presion_sistolica;
            // Valores con default
            $save->enfermedadesCronicas = filled($request->enfermedadesCronicas)
                ? trim((string) $request->enfermedadesCronicas)
                : 'No Presenta';

            $save->medicamentosDiarios = filled($request->medicamentosDiarios)
                ? trim((string) $request->medicamentosDiarios)
                : 'No Presenta';

            $save->sistemaOsteoarticular = filled($request->sistemaOsteoarticular)
                ? trim((string) $request->sistemaOsteoarticular)
                : 'Sin Alteraciones';

            $save->sistemaCardiovascular = filled($request->sistemaCardiovascular)
                ? trim((string) $request->sistemaCardiovascular)
                : 'Sin Alteraciones';

            $save->enfermedadesAnteriores = filled($request->enfermedadesAnteriores)
                ? trim((string) $request->enfermedadesAnteriores)
                : 'Sin Alteraciones';

            $save->Recuperacion = filled($request->Recuperacion)
                ? trim((string) $request->Recuperacion)
                : 'Sin Alteraciones';

            $save->gradoIncidenciaPosterio = filled($request->gradoIncidenciaPosterio)
                ? trim((string) $request->gradoIncidenciaPosterio)
                : 'Sin Alteraciones';

            $save->fechaNacimiento       = $request->fechaNacimiento;
            $save->user_email            = $request->user_email;
            $save->user_email_update     = $request->user_email_perfil;
            $save->sexo_paciente         = $request->sexo_paciente;
            $save->imc_paciente          = $request->imc_paciente;
            $save->division_paciente     = $request->division_paciente;
            $save->medio_pago_paciente   = $request->medio_pago_paciente;
            $save->email_paciente        = $request->email_paciente;
            $save->created_at            = now();

            // Perfil testiado
            if ($perfilId == 2) {

                $save->fecha_atencion = Carbon::now()->format('Y-m-d H:i:s');
                $save->status         = 'Testiado';
            }

            $save->save();

            return response()->json([
                'response' => [
                    'status'  => 'OK',
                    'mensaje' => 'Reserva con Exito'
                ]
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'response' => [
                    'status'  => 'Error en ejecucion',
                    'mensaje' => $e->getMessage()
                ]
            ], 500);
        }
    }
    public function Update(Request $request, int $id_paciente, string $user_email)
    {
        // Validar request vacío
        if (empty($request->all())) {

            return response()->json([
                'status'  => 'Bad Request',
                'mensaje' => 'Http NO trae Datos para Procesar'
            ], 400);
        }

        try {

            $perfilId = $this->userMetadataService
                ->getPerfilIdByEmail($user_email);

            $chequeoCardiovascular = ChequeoCardiovascular::where('id', $id_paciente)
                ->firstOrFail();

            // Datos básicos
            $chequeoCardiovascular->nombre = filled($request->nombre)
                ? ucwords(strtolower(trim((string) $request->nombre)))
                : $chequeoCardiovascular->nombre;

            $chequeoCardiovascular->edad                = $request->edad;
            $chequeoCardiovascular->estatura            = str_replace(',', '.', (string) $request->estatura);
            $chequeoCardiovascular->peso                = $request->peso;
            $chequeoCardiovascular->pulso               = $request->pulso;
            $chequeoCardiovascular->presionArterial     = $request->presionArterial;
            $chequeoCardiovascular->saturacionOxigeno   = $request->saturacionOxigeno;
            $chequeoCardiovascular->temperatura         = $request->temperatura;
            $chequeoCardiovascular->presion_sistolica   = $request->presion_sistolica;
            $chequeoCardiovascular->fechaNacimiento     = $request->fechaNacimiento;
            $chequeoCardiovascular->hemoglucotest       = $request->hemoglucotest;
            $chequeoCardiovascular->user_email_update   = $request->user_email_perfil;
            $chequeoCardiovascular->sexo_paciente       = $request->sexo_paciente;
            $chequeoCardiovascular->imc_paciente        = $request->imc_paciente;
            $chequeoCardiovascular->division_paciente   = $request->division_paciente;
            $chequeoCardiovascular->medio_pago_paciente = $request->medio_pago_paciente;

            // Campos con valores default
            $chequeoCardiovascular->enfermedadesCronicas = filled($request->enfermedadesCronicas)
                ? trim((string) $request->enfermedadesCronicas)
                : 'No Presenta';

            $chequeoCardiovascular->medicamentosDiarios = filled($request->medicamentosDiarios)
                ? trim((string) $request->medicamentosDiarios)
                : 'No Presenta';

            $chequeoCardiovascular->sistemaOsteoarticular = filled($request->sistemaOsteoarticular)
                ? trim((string) $request->sistemaOsteoarticular)
                : 'Sin Alteraciones';

            $chequeoCardiovascular->sistemaCardiovascular = filled($request->sistemaCardiovascular)
                ? trim((string) $request->sistemaCardiovascular)
                : 'Sin Alteraciones';

            $chequeoCardiovascular->enfermedadesAnteriores = filled($request->enfermedadesAnteriores)
                ? trim((string) $request->enfermedadesAnteriores)
                : 'Sin Alteraciones';

            $chequeoCardiovascular->Recuperacion = filled($request->Recuperacion)
                ? trim((string) $request->Recuperacion)
                : 'Sin Alteraciones';

            $chequeoCardiovascular->gradoIncidenciaPosterio = filled($request->gradoIncidenciaPosterio)
                ? trim((string) $request->gradoIncidenciaPosterio)
                : 'Sin Alteraciones';

            // Perfil testiado
            if ($perfilId == 2) {

                $chequeoCardiovascular->fecha_atencion = Carbon::now()
                    ->format('Y-m-d H:i:s');

                $chequeoCardiovascular->status = 'Testiado';
            }

            // Perfil administrador
            if ($perfilId == 1) {

                $chequeoCardiovascular->rut        = $request->rut;
                $chequeoCardiovascular->user_email = $request->user_email;
                $chequeoCardiovascular->status     = $request->status;

                if (filled($request->fecha_atencion)) {

                    $chequeoCardiovascular->fecha_atencion = Carbon::parse(
                        $request->fecha_atencion
                    )->format('Y-m-d H:i:s');
                }
            }
            else {
                 $chequeoCardiovascular->fecha_atencion = $chequeoCardiovascular->created_at;
            }

            $chequeoCardiovascular->save();

            // Actualizar Certificado y ECG
            if ($perfilId == 1) {

                $this->certificadoService->UpdateRutCertificado(
                    $id_paciente,
                    $request->rut
                );

                $this->electroCardiogramaService->UpdateRutECG(
                    $id_paciente,
                    $request->rut
                );
            }

            return response()->json([
                'status'  => 'OK',
                'mensaje' => 'Modificado con exito'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'response' => [
                    'status'  => 'Error en ejecucion',
                    'mensaje' => $e->getMessage()
                ]
            ], 500);
        }
    }

    public function FilterCalendar(Request $request){

        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array(
                'status' => 'Bad Request',
                'mensaje' => 'Http NO trae Datos para Procesar');

            return response()->json($array,400);

        }

        try {

            $fecha_calendar = $request->fecha_calendar;
            $user_email     = $request->user_email;


            $perfilId = $this->userMetadataService->getPerfilIdByEmail($user_email);


            $responseChequeo = $this->chequeoCardiovascularService
                ->filterCalendar($perfilId, $fecha_calendar, $user_email);


            return response()->json($responseChequeo);

        }
        catch (\Exception $e) {

            $array = array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage());

            return response()->json($array,500);

        }
    }

    public function EstadoGeneral(Request $request) {


        try {
            $user_email           = $request->user_email;

            $results = ChequeoCardiovascular::SP_estado_general($user_email);

            // Convertir el resultado a un objeto (si es necesario)
            $resultadoJson = json_decode($results[0]->resultado_json);

            return $resultadoJson;
        }
        catch (\Exception $e) {

            // Retorna una respuesta con el error
            $array = array('response' => array(
                'status' => 'Error en ejecucion',
                'mensaje' => $e->getMessage()));

            return response()->json($array,500);

        }

    }

    public function ChequeoPDFRut(string $rut_paciente)
    {
        $chequeoCardiovascular = ChequeoCardiovascular::where(['rut' => $rut_paciente])
            ->orderBy('id', 'desc')
            ->first();

        // Verifica que se haya encontrado un registro antes de llamar a la función
        if ($chequeoCardiovascular) {
           return $this->ChequeoPDF((int) $chequeoCardiovascular->id);
        } else {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }
    }
    public function ChequeoUserEmail(Request $request){

        try {
            $user_email = $request->user_email;
            $result_chequeo = $this->chequeoCardiovascularService->chequeoUserEmail($user_email);
            return response()->json($result_chequeo, 200);
        }
        catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function ChequeoPDF(int $id_paciente)
    {

        $mpdf = new Mpdf();

        $chequeoCardiovascular = ChequeoCardiovascular::where(['id' => $id_paciente])
            ->orderBy('id', 'desc')
            ->first();

        $html = $this->chequeoCardiovascularPDFService->chequeoPDf($id_paciente);

        $mpdf->WriteHTML($html);
        // Generar el archivo PDF
        $pdfOutput = $mpdf->Output('', 'S'); // S: Retorna el contenido como string

        return response($pdfOutput)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="Certificado '.ucwords(strtolower($chequeoCardiovascular->nombre)).'.pdf"');
    }

    public function DeleteRut(string $rut)
    {
        ChequeoCardiovascular::where(['rut' => $rut])->delete();

        $array = array(
            'status' => 'OK',
            'mensaje' => 'Delete con exito');

        return response()->json($array,200);
    }

    public function SearchChequeo(Request $request)
    {
        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array(
                'status' => 'Bad Request',
                'mensaje' => 'Http NO trae Datos para Procesar');

            return response()->json($array,400);

        }
        try {

            $textoValue     = $request->textoValue;
            $fechaCalendar  = $request->fechaCalendar;
            $selectClub     = $request->selectClub;
            $user_email     = $request->user_email;
            $limit          = $request->get('limit', 20);
            $page           = $request->get('page', 1);

            $perfilId = $this->userMetadataService->getPerfilIdByEmail($user_email);

            $responseChequeo = $this->chequeoCardiovascularService
                    ->SearchChequeo($perfilId,$textoValue,$fechaCalendar,$selectClub,
                    $user_email,$limit,$page);

            return response()->json($responseChequeo->original);

        }
        catch (\Exception $e) {

            $array = array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage());

            return response()->json($array,500);

        }

    }

    public function ChequeoEmailAll(Request $request)
    {
        try {
            $json = json_decode(file_get_contents('php://input'), true);

            if (!is_array($json)) {
                return response()->json([
                    'status' => 400,
                    'mensaje' => 'La solicitud no contiene datos válidos para procesar.'
                ], 400);
            }

            $user_email = $request->user_email;
            $perfilId = $this->userMetadataService->getPerfilIdByEmail($user_email);
            $responseChequeo = $this->chequeoCardiovascularService
                ->ChequeoEmailAll($user_email, $perfilId);

            return response()->json([
                'status' => 200,
                'mensaje' => 'Chequeo obtenido correctamente.',
                'data' => $responseChequeo
            ], 200);

        }
        catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'mensaje' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }




}
