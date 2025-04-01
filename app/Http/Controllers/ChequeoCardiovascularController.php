<?php

namespace App\Http\Controllers;

use App\Models\ChequeoCardiovascular;
use App\Models\ElectroCardiograma;

use Illuminate\Http\Request;

use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\UserMetadataService;
use App\Services\ChequeoCardiovascularService;
use App\Services\ChequeoCardiovascularPDFService;

class ChequeoCardiovascularController extends Controller
{
    protected $userMetadataService;
    protected $chequeoCardiovascularService;
    protected $chequeoCardiovascularPDFService;


    // Inyectar servicios a través del constructor
    public function __construct(
        UserMetadataService $userMetadataService,
        ChequeoCardiovascularService $chequeoCardiovascularService,
        ChequeoCardiovascularPDFService $chequeoCardiovascularPDFService )
    {
        $this->userMetadataService             = $userMetadataService;
        $this->chequeoCardiovascularService    = $chequeoCardiovascularService;
        $this->chequeoCardiovascularPDFService = $chequeoCardiovascularPDFService;
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
                DB::raw("COALESCE(ec.estado_paciente, 'En Revisión') as estado_paciente"),
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
                DB::raw("COALESCE(ec.estado_paciente, 'En Revisión') as estado_paciente"),
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

    public function Store(Request $request) {

        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array('response' => array(
                'status' => 'Bad Request',
                'mensaje' =>  'Ingrese Valores'));

            return response()->json($array,400);

        }
        $perfilId = $this->userMetadataService->getPerfilIdByEmail($request->user_email);

        $save = new ChequeoCardiovascular;
        $save->nombre                  = ucwords(strtolower($request->nombre));
        $save->rut                     = $request->rut;
        $save->edad                    = $request->edad;
        $save->estatura                = str_replace(',','.',$request->estatura);
        $save->peso                    = $request->peso;
        $save->hemoglucotest           = $request->hemoglucotest;
        $save->pulso                   = $request->pulso;
        $save->presionArterial         = $request->presionArterial;
        $save->saturacionOxigeno       = $request->saturacionOxigeno;
        $save->temperatura             = $request->temperatura;
        $save->presion_sistolica       = $request->presion_sistolica;
        $save->enfermedadesCronicas    = $request->enfermedadesCronicas;
        $save->medicamentosDiarios     = $request->medicamentosDiarios;
        $save->sistemaOsteoarticular   = $request->sistemaOsteoarticular;
        $save->sistemaCardiovascular   = $request->sistemaCardiovascular;
        $save->enfermedadesAnteriores  = $request->enfermedadesAnteriores;
        $save->Recuperacion            = $request->Recuperacion;
        $save->gradoIncidenciaPosterio = $request->gradoIncidenciaPosterio;
        $save->fechaNacimiento         = $request->fechaNacimiento;
        $save->user_email              = $request->user_email;
        $save->user_email_update       = $request->user_email_perfil;
        $save->sexo_paciente           = $request->sexo_paciente;
        $save->imc_paciente            = $request->imc_paciente;
        $save->division_paciente       = $request->division_paciente;
        $save->medio_pago_paciente     = $request->medio_pago_paciente;

        if($perfilId == 2) {
            $save->fecha_atencion = Carbon::now()->format('Y-m-d H:i:s');
            $save->status         = 'Testiado';
        }

        try {

            $save->save();
            $array = array('response' => array(
                'status' => 'OK',
                'mensaje' => 'Reserva con Exito'));

            return response()->json($array,201);

        }
        catch (\Exception $e) {

            // Retorna una respuesta con el error
            $array = array('response' => array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage()));

            return response()->json($array,500);

        }

    }

    public function Update(Request $request, int $id_paciente,string $user_email) {

        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array(
                'status' => 'Bad Request',
                'mensaje' => 'Http NO trae Datos para Procesar');

            return response()->json($array,400);

        }

        try {

            $perfilId = $this->userMetadataService->getPerfilIdByEmail($user_email);


            $chequeoCardiovascular = ChequeoCardiovascular::where('id', $id_paciente)
            ->firstOrFail();

            $chequeoCardiovascular->nombre                  = ucwords(strtolower($json['nombre']));
            $chequeoCardiovascular->edad                    = $json['edad'];
            $chequeoCardiovascular->estatura                = str_replace(',','.',$json['estatura']);
            $chequeoCardiovascular->peso                    = $json['peso'];
            $chequeoCardiovascular->pulso                   = $json['pulso'];
            $chequeoCardiovascular->presionArterial         = $json['presionArterial'];
            $chequeoCardiovascular->saturacionOxigeno       = $json['saturacionOxigeno'];
            $chequeoCardiovascular->temperatura             = $json['temperatura'];
            $chequeoCardiovascular->presion_sistolica       = $json['presion_sistolica'];
            $chequeoCardiovascular->enfermedadesCronicas    = $json['enfermedadesCronicas'];
            $chequeoCardiovascular->medicamentosDiarios     = $json['medicamentosDiarios'];
            $chequeoCardiovascular->sistemaOsteoarticular   = $json['sistemaOsteoarticular'];
            $chequeoCardiovascular->sistemaCardiovascular   = $json['sistemaCardiovascular'];
            $chequeoCardiovascular->enfermedadesAnteriores  = $json['enfermedadesAnteriores'];
            $chequeoCardiovascular->Recuperacion            = $json['Recuperacion'];
            $chequeoCardiovascular->gradoIncidenciaPosterio = $json['gradoIncidenciaPosterio'];
            $chequeoCardiovascular->fechaNacimiento         = $json['fechaNacimiento'];
            $chequeoCardiovascular->hemoglucotest           = $json['hemoglucotest'];
            $chequeoCardiovascular->user_email_update       = $json['user_email_perfil'];
            $chequeoCardiovascular->sexo_paciente           = $json['sexo_paciente'];
            $chequeoCardiovascular->imc_paciente            = $json['imc_paciente'];
            $chequeoCardiovascular->division_paciente       = $json['division_paciente'];
            $chequeoCardiovascular->medio_pago_paciente     = $json['medio_pago_paciente'];

            if($perfilId == 2) {
                $chequeoCardiovascular->fecha_atencion = Carbon::now()->format('Y-m-d H:i:s');
                $chequeoCardiovascular->status         = 'Testiado';
            }

            if($perfilId == 1) {
                $chequeoCardiovascular->rut            = $json['rut'];
                $chequeoCardiovascular->user_email     = $json['user_email'];
                $chequeoCardiovascular->status         = $json['status'];
                if (isset($json['fecha_atencion']) && !empty($json['fecha_atencion'])) {
                    $chequeoCardiovascular->fecha_atencion = Carbon::parse($json['fecha_atencion'])->format('Y-m-d H:i:s');
                }

            }
            $chequeoCardiovascular->save();

            $array = array(
                'status' => 'OK',
                'mensaje' => 'Modificado con exito');

            return response()->json($array,200);
        }
        catch (\Exception $e) {

            // Retorna una respuesta con el error
            $array = array('response' => array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage()));

            return response()->json($array,500);

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

}
