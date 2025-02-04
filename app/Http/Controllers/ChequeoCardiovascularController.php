<?php

namespace App\Http\Controllers;

use App\Models\ChequeoCardiovascular;
use App\Models\ElectroCardiograma;

use Illuminate\Http\Request;

use Mpdf\Mpdf;  
use Mpdf\MpdfException;
use Carbon\Carbon;


class ChequeoCardiovascularController extends Controller
{
    //
    public function Health(){
        return "Bienvenido a ergosanitas.com";
    }

    public function Index(){

        $resChequeoCardiovascular = ChequeoCardiovascular::orderBy("id","desc")->get();
        $resArray = array();

        foreach($resChequeoCardiovascular as $chequeoCardiovascular) {

            $resArray[] = array(
                "id"                      => $chequeoCardiovascular->id,
                "nombre"                  => $chequeoCardiovascular->nombre,
                "rut"                     => $chequeoCardiovascular->rut,
                "fechaNacimiento"         => $chequeoCardiovascular->fechaNacimiento,
                "edad"                    => $chequeoCardiovascular->edad,
                "estatura"                => $chequeoCardiovascular->estatura?$chequeoCardiovascular->estatura:'',
                "peso"                    => $chequeoCardiovascular->peso?$chequeoCardiovascular->peso:'',
                "hemoglucotest"           => $chequeoCardiovascular->hemoglucotest?$chequeoCardiovascular->hemoglucotest:'',
                "pulso"                   => $chequeoCardiovascular->pulso?$chequeoCardiovascular->pulso:'',
                "presionArterial"         => $chequeoCardiovascular->presionArterial?$chequeoCardiovascular->presionArterial:'',
                "saturacionOxigeno"       => $chequeoCardiovascular->saturacionOxigeno?$chequeoCardiovascular->saturacionOxigeno:'',
                "temperatura"             => $chequeoCardiovascular->temperatura?$chequeoCardiovascular->temperatura:'',
                "imc"                     => $chequeoCardiovascular->imc?$chequeoCardiovascular->imc:'',
                "enfermedadesCronicas"    => $chequeoCardiovascular->enfermedadesCronicas?$chequeoCardiovascular->enfermedadesCronicas:'',
                "medicamentosDiarios"     => $chequeoCardiovascular->medicamentosDiarios?$chequeoCardiovascular->medicamentosDiarios:'',
                "sistemaOsteoarticular"   => $chequeoCardiovascular->sistemaOsteoarticular?$chequeoCardiovascular->sistemaOsteoarticular:'',
                "sistemaCardiovascular"   => $chequeoCardiovascular->sistemaCardiovascular?$chequeoCardiovascular->sistemaCardiovascular:'',
                "enfermedadesAnteriores"  => $chequeoCardiovascular->enfermedadesAnteriores?$chequeoCardiovascular->enfermedadesAnteriores:'',
                "enfermedadesAnteriores"  => $chequeoCardiovascular->enfermedadesAnteriores?$chequeoCardiovascular->enfermedadesAnteriores:'',
                "Recuperacion"            => $chequeoCardiovascular->Recuperacion?$chequeoCardiovascular->Recuperacion:'',
                "gradoIncidenciaPosterio" => $chequeoCardiovascular->gradoIncidenciaPosterio?$chequeoCardiovascular->gradoIncidenciaPosterio:'',
                "user_email"              => $chequeoCardiovascular->user_email?$chequeoCardiovascular->user_email:''
            );
        }
        return response()->json($resArray,200);
    }

    public function FindByEmail(Request $request){

        try {

            $user_email = $request->user_email;
            
            //$resChequeoCardiovascular = ChequeoCardiovascular::orderBy("id","desc")->get();
            
            $resChequeoCardiovascular = ChequeoCardiovascular::where(['user_email' => $user_email])
                ->get();

            $resArray = array();
        
            foreach($resChequeoCardiovascular as $chequeoCardiovascular) {

                $resArray[] = array(
                    "id"                      => $chequeoCardiovascular->id,
                    "nombre"                  => $chequeoCardiovascular->nombre,
                    "rut"                     => $chequeoCardiovascular->rut,
                    "fechaNacimiento"         => $chequeoCardiovascular->fechaNacimiento,
                    "edad"                    => $chequeoCardiovascular->edad,
                    "estatura"                => $chequeoCardiovascular->estatura?$chequeoCardiovascular->estatura:'',
                    "peso"                    => $chequeoCardiovascular->peso?$chequeoCardiovascular->peso:'',
                    "hemoglucotest"           => $chequeoCardiovascular->hemoglucotest?$chequeoCardiovascular->hemoglucotest:'',
                    "pulso"                   => $chequeoCardiovascular->pulso?$chequeoCardiovascular->pulso:'',
                    "presionArterial"         => $chequeoCardiovascular->presionArterial?$chequeoCardiovascular->presionArterial:'',
                    "saturacionOxigeno"       => $chequeoCardiovascular->saturacionOxigeno?$chequeoCardiovascular->saturacionOxigeno:'',
                    "temperatura"             => $chequeoCardiovascular->temperatura?$chequeoCardiovascular->temperatura:'',
                    "imc"                     => $chequeoCardiovascular->imc?$chequeoCardiovascular->imc:'',
                    "enfermedadesCronicas"    => $chequeoCardiovascular->enfermedadesCronicas?$chequeoCardiovascular->enfermedadesCronicas:'',
                    "medicamentosDiarios"     => $chequeoCardiovascular->medicamentosDiarios?$chequeoCardiovascular->medicamentosDiarios:'',
                    "sistemaOsteoarticular"   => $chequeoCardiovascular->sistemaOsteoarticular?$chequeoCardiovascular->sistemaOsteoarticular:'',
                    "sistemaCardiovascular"   => $chequeoCardiovascular->sistemaCardiovascular?$chequeoCardiovascular->sistemaCardiovascular:'',
                    "enfermedadesAnteriores"  => $chequeoCardiovascular->enfermedadesAnteriores?$chequeoCardiovascular->enfermedadesAnteriores:'',
                    "enfermedadesAnteriores"  => $chequeoCardiovascular->enfermedadesAnteriores?$chequeoCardiovascular->enfermedadesAnteriores:'',
                    "Recuperacion"            => $chequeoCardiovascular->Recuperacion?$chequeoCardiovascular->Recuperacion:'',
                    "gradoIncidenciaPosterio" => $chequeoCardiovascular->gradoIncidenciaPosterio?$chequeoCardiovascular->gradoIncidenciaPosterio:'',
                    "user_email"              => $chequeoCardiovascular->user_email?$chequeoCardiovascular->user_email:''
                );
            }
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

           
            foreach($resChequeo as $chequeoCardiovascular) {

                $resArray[] = array(
                    "id"                      => $chequeoCardiovascular->id,
                    "nombre"                  => $chequeoCardiovascular->nombre,
                    "rut"                     => $chequeoCardiovascular->rut,
                    "fechaNacimiento"         => $chequeoCardiovascular->fechaNacimiento,
                    "edad"                    => $chequeoCardiovascular->edad,
                    "estatura"                => $chequeoCardiovascular->estatura?$chequeoCardiovascular->estatura:'',
                    "peso"                    => $chequeoCardiovascular->peso?$chequeoCardiovascular->peso:'',
                    "hemoglucotest"           => $chequeoCardiovascular->hemoglucotest?$chequeoCardiovascular->hemoglucotest:'',
                    "pulso"                   => $chequeoCardiovascular->pulso?$chequeoCardiovascular->pulso:'',
                    "presionArterial"         => $chequeoCardiovascular->presionArterial?$chequeoCardiovascular->presionArterial:'',
                    "saturacionOxigeno"       => $chequeoCardiovascular->saturacionOxigeno?$chequeoCardiovascular->saturacionOxigeno:'',
                    "temperatura"             => $chequeoCardiovascular->temperatura?$chequeoCardiovascular->temperatura:'',
                    "imc"                     => $chequeoCardiovascular->imc?$chequeoCardiovascular->imc:'',
                    "enfermedadesCronicas"    => $chequeoCardiovascular->enfermedadesCronicas?$chequeoCardiovascular->enfermedadesCronicas:'',
                    "medicamentosDiarios"     => $chequeoCardiovascular->medicamentosDiarios?$chequeoCardiovascular->medicamentosDiarios:'',
                    "sistemaOsteoarticular"   => $chequeoCardiovascular->sistemaOsteoarticular?$chequeoCardiovascular->sistemaOsteoarticular:'',
                    "sistemaCardiovascular"   => $chequeoCardiovascular->sistemaCardiovascular?$chequeoCardiovascular->sistemaCardiovascular:'',
                    "enfermedadesAnteriores"  => $chequeoCardiovascular->enfermedadesAnteriores?$chequeoCardiovascular->enfermedadesAnteriores:'',
                    "enfermedadesAnteriores"  => $chequeoCardiovascular->enfermedadesAnteriores?$chequeoCardiovascular->enfermedadesAnteriores:'',
                    "Recuperacion"            => $chequeoCardiovascular->Recuperacion?$chequeoCardiovascular->Recuperacion:'',
                    "gradoIncidenciaPosterio" => $chequeoCardiovascular->gradoIncidenciaPosterio?$chequeoCardiovascular->gradoIncidenciaPosterio:'',
                    "user_email"              => $chequeoCardiovascular->user_email?$chequeoCardiovascular->user_email:'' 
                );
            }
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

            $resChequeo = ChequeoCardiovascular::where('user_email', $user_email)
            ->where('rut', 'like', '%' . $textoValue . '%')
            ->orWhere('nombre', 'like', '%' . $textoValue . '%')
            ->where('user_email', $user_email)
            ->get();
                

            foreach($resChequeo as $chequeoCardiovascular) {

                $resArray[] = array(
                    "id"                      => $chequeoCardiovascular->id,
                    "nombre"                  => $chequeoCardiovascular->nombre,
                    "rut"                     => $chequeoCardiovascular->rut,
                    "fechaNacimiento"         => $chequeoCardiovascular->fechaNacimiento,
                    "edad"                    => $chequeoCardiovascular->edad,
                    "estatura"                => $chequeoCardiovascular->estatura?$chequeoCardiovascular->estatura:'',
                    "peso"                    => $chequeoCardiovascular->peso?$chequeoCardiovascular->peso:'',
                    "hemoglucotest"           => $chequeoCardiovascular->hemoglucotest?$chequeoCardiovascular->hemoglucotest:'',
                    "pulso"                   => $chequeoCardiovascular->pulso?$chequeoCardiovascular->pulso:'',
                    "presionArterial"         => $chequeoCardiovascular->presionArterial?$chequeoCardiovascular->presionArterial:'',
                    "saturacionOxigeno"       => $chequeoCardiovascular->saturacionOxigeno?$chequeoCardiovascular->saturacionOxigeno:'',
                    "temperatura"             => $chequeoCardiovascular->temperatura?$chequeoCardiovascular->temperatura:'',
                    "imc"                     => $chequeoCardiovascular->imc?$chequeoCardiovascular->imc:'',
                    "enfermedadesCronicas"    => $chequeoCardiovascular->enfermedadesCronicas?$chequeoCardiovascular->enfermedadesCronicas:'',
                    "medicamentosDiarios"     => $chequeoCardiovascular->medicamentosDiarios?$chequeoCardiovascular->medicamentosDiarios:'',
                    "sistemaOsteoarticular"   => $chequeoCardiovascular->sistemaOsteoarticular?$chequeoCardiovascular->sistemaOsteoarticular:'',
                    "sistemaCardiovascular"   => $chequeoCardiovascular->sistemaCardiovascular?$chequeoCardiovascular->sistemaCardiovascular:'',
                    "enfermedadesAnteriores"  => $chequeoCardiovascular->enfermedadesAnteriores?$chequeoCardiovascular->enfermedadesAnteriores:'',
                    "enfermedadesAnteriores"  => $chequeoCardiovascular->enfermedadesAnteriores?$chequeoCardiovascular->enfermedadesAnteriores:'',
                    "Recuperacion"            => $chequeoCardiovascular->Recuperacion?$chequeoCardiovascular->Recuperacion:'',
                    "gradoIncidenciaPosterio" => $chequeoCardiovascular->gradoIncidenciaPosterio?$chequeoCardiovascular->gradoIncidenciaPosterio:'',
                    "user_email"              => $chequeoCardiovascular->user_email?$chequeoCardiovascular->user_email:'' 
                );
            }
            return response()->json($resArray,200);
        }
        catch (\Exception $e) {
            
            $array = array(
                'status' => 'Error en ejecucion',
                'mensaje' =>  $e->getMessage());
        
            return response()->json($array,500);
        
        }
    }

    public function ChequeoRut (string $rut) {

        try {

            $chequeoCardiovascular = ChequeoCardiovascular::where(['rut' => $rut])
                ->orderBy('id', 'desc')
                ->first();


            
                $resArray = array(
                    "id"                      => $chequeoCardiovascular->id,
                    "nombre"                  => $chequeoCardiovascular->nombre,
                    "rut"                     => $chequeoCardiovascular->rut,
                    "fechaNacimiento"         => $chequeoCardiovascular->fechaNacimiento,
                    "edad"                    => $chequeoCardiovascular->edad,
                    "estatura"                => $chequeoCardiovascular->estatura?$chequeoCardiovascular->estatura:'',
                    "peso"                    => $chequeoCardiovascular->peso?$chequeoCardiovascular->peso:'',
                    "hemoglucotest"           => $chequeoCardiovascular->hemoglucotest?$chequeoCardiovascular->hemoglucotest:'',
                    "pulso"                   => $chequeoCardiovascular->pulso?$chequeoCardiovascular->pulso:'',
                    "presionArterial"         => $chequeoCardiovascular->presionArterial?$chequeoCardiovascular->presionArterial:'',
                    "saturacionOxigeno"       => $chequeoCardiovascular->saturacionOxigeno?$chequeoCardiovascular->saturacionOxigeno:'',
                    "temperatura"             => $chequeoCardiovascular->temperatura?$chequeoCardiovascular->temperatura:'',
                    "imc"                     => $chequeoCardiovascular->imc?$chequeoCardiovascular->imc:'',
                    "enfermedadesCronicas"    => $chequeoCardiovascular->enfermedadesCronicas?$chequeoCardiovascular->enfermedadesCronicas:'',
                    "medicamentosDiarios"     => $chequeoCardiovascular->medicamentosDiarios?$chequeoCardiovascular->medicamentosDiarios:'',
                    "sistemaOsteoarticular"   => $chequeoCardiovascular->sistemaOsteoarticular?$chequeoCardiovascular->sistemaOsteoarticular:'',
                    "sistemaCardiovascular"   => $chequeoCardiovascular->sistemaCardiovascular?$chequeoCardiovascular->sistemaCardiovascular:'',
                    "enfermedadesAnteriores"  => $chequeoCardiovascular->enfermedadesAnteriores?$chequeoCardiovascular->enfermedadesAnteriores:'',
                    "enfermedadesAnteriores"  => $chequeoCardiovascular->enfermedadesAnteriores?$chequeoCardiovascular->enfermedadesAnteriores:'',
                    "Recuperacion"            => $chequeoCardiovascular->Recuperacion?$chequeoCardiovascular->Recuperacion:'',
                    "gradoIncidenciaPosterio" => $chequeoCardiovascular->gradoIncidenciaPosterio?$chequeoCardiovascular->gradoIncidenciaPosterio:'',
                    "user_email"              => $chequeoCardiovascular->user_email?$chequeoCardiovascular->user_email:''  
                );


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

    public function Store(Request $request) {

        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array('response' => array(
                'status' => 'Bad Request',
                'mensaje' =>  'Ingrese Valores'));
        
            return response()->json($array,400);

        }

        $save = new ChequeoCardiovascular;
        $save->nombre                  = $request->nombre;
        $save->rut                     = $request->rut;
        $save->edad                    = $request->edad;
        $save->estatura                = $request->estatura;
        $save->peso                    = $request->peso;
        $save->hemoglucotest           = $request->hemoglucotest;
        $save->pulso                   = $request->pulso;
        $save->presionArterial         = $request->presionArterial;
        $save->saturacionOxigeno       = $request->saturacionOxigeno;
        $save->temperatura             = $request->temperatura;
        $save->imc                     = $request->imc;
        $save->enfermedadesCronicas    = $request->enfermedadesCronicas;
        $save->medicamentosDiarios     = $request->medicamentosDiarios;
        $save->sistemaOsteoarticular   = $request->sistemaOsteoarticular;
        $save->sistemaCardiovascular   = $request->sistemaCardiovascular;
        $save->enfermedadesAnteriores  = $request->enfermedadesAnteriores;
        $save->Recuperacion            = $request->Recuperacion;
        $save->gradoIncidenciaPosterio = $request->gradoIncidenciaPosterio;
        $save->fechaNacimiento         = $request->fechaNacimiento;
        $save->user_email              = $request->user_email;
        
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

    public function Update(Request $request, string $rut) {

        $json = json_decode(file_get_contents('php://input'),true);

        if(!is_array($json)) {

            $array = array(
                'status' => 'Bad Request',
                'mensaje' => 'Http NO trae Datos para Procesar');
        
            return response()->json($array,400);

        }

        try {
            $chequeoCardiovascular = ChequeoCardiovascular::where(['rut' => $rut])->firstOrFail();
            
            $chequeoCardiovascular->nombre                  = $json['nombre'];
            $chequeoCardiovascular->edad                    = $json['edad'];
            $chequeoCardiovascular->estatura                = $json['estatura'];
            $chequeoCardiovascular->peso                    = $json['peso'];
            $chequeoCardiovascular->pulso                   = $json['pulso'];
            $chequeoCardiovascular->presionArterial         = $json['presionArterial'];
            $chequeoCardiovascular->saturacionOxigeno       = $json['saturacionOxigeno'];
            $chequeoCardiovascular->temperatura             = $json['temperatura'];
            $chequeoCardiovascular->imc                     = $json['imc'];
            $chequeoCardiovascular->enfermedadesCronicas    = $json['enfermedadesCronicas'];
            $chequeoCardiovascular->medicamentosDiarios     = $json['medicamentosDiarios'];
            $chequeoCardiovascular->sistemaOsteoarticular   = $json['sistemaOsteoarticular'];
            $chequeoCardiovascular->sistemaCardiovascular   = $json['sistemaCardiovascular'];
            $chequeoCardiovascular->enfermedadesAnteriores  = $json['enfermedadesAnteriores'];
            $chequeoCardiovascular->Recuperacion            = $json['Recuperacion'];
            $chequeoCardiovascular->gradoIncidenciaPosterio = $json['gradoIncidenciaPosterio'];
            $chequeoCardiovascular->fechaNacimiento         = $json['fechaNacimiento'];
            $chequeoCardiovascular->hemoglucotest           = $json['hemoglucotest'];
            $chequeoCardiovascular->user_email_update       = $json['user_email'];
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

    public function ChequeoPDF(string $rut)
    {
        $logoPath = public_path('logo.png');//logoTrans.png
        $firmaDoc = public_path('firmarDoctor.jpg');
        $firmaErgo = public_path('firmarErgo.jpg');
        
        $chequeoCardiovascular = ChequeoCardiovascular::where(['rut' => $rut])
            ->orderBy('id', 'desc')
            ->first();   

        $electroCardiograma = ElectroCardiograma::where(['rut_paciente' => $rut])
            ->first();

            $IMFText = '';
            if($electroCardiograma && $electroCardiograma->imc_paciente){
                $IMFText = $electroCardiograma->imc_paciente;
            }
                
    
            /*
            $IMFText = 'Normal';

            $estaturaSinPuntos = str_replace('.', '', $chequeoCardiovascular->estatura);
            $estaturaNumerica = (int)$estaturaSinPuntos; // Si necesitas un entero

            $pesoSinPuntos = str_replace('.', '', $chequeoCardiovascular->peso);
            $pesoNumerica = (int)$pesoSinPuntos; // Si necesitas un entero



            if ($estaturaNumerica > 0) {
                //$IMC = ($chequeoCardiovascular->peso / $chequeoCardiovascular->estatura * $chequeoCardiovascular->estatura);
                $IMC = $pesoNumerica / ($estaturaNumerica / 100 ) ** 2;
            } else {
                $IMC = 0; 
            }

            if ($IMC < 18.5) {
                $IMFText = 'Bajo Peso';
            }
            else if ($IMC >= 18.5 && $IMC <= 24.5) {
                $IMFText = 'Normal';
            }
            else if ($IMC >= 24.5) {
                $IMFText = 'Sobre Peso';
            }*/



    
        //$html .= "<img src='".$logoPath."' alt='logo' style='width:150px; height:auto;'/>";
        
        $stylesheet="";
        $stylesheet .= "<style>";
        $stylesheet .= " body { font-family: Arial, sans-serif;margin: 0;padding: 20px;line-height: 1.6;}";
        $stylesheet .= " .container { max-width: 800px;margin: 0 auto; #ccc;}";
        $stylesheet .= " h1 { text-align: center;font-size: 24px;}";
        $stylesheet .= " .patient-info { margin-bottom: 20px;}";
        $stylesheet .= " .patient-info p {margin: 5px 0;}";
        $stylesheet .= " .section-title { font-weight: bold;}";
        $stylesheet .= " .signature { margin-top: 30px;text-align: center;}";
        $stylesheet .= " .footer { text-align: center;margin-top: 20px;font-size: 12px;}";
        $stylesheet .= "</style>";

        $html = "";
        $html .= "<html>";
        $html .= "<head>";
        $html .= $stylesheet;
        $html .= "</head>";
        $html .= "<body>";



        $html .= "<div class='container'>";

        $html .= "<table style='width:100%;'>"; 
        $html .= "<tr>";
        $html .= "<td style='width: 50px;'><img src='" . $logoPath . "' alt='logo' style='width:200px; height:100px;'/></td>";
        $html .= "<td style='text-align: right; vertical-align: middle;font-size: 10px;'><p>Servicios de Salud a Domicilio</p></td>";
        $html .= "</tr>";
        $html .= "</table>";

        $html .= "<h3 style='text-align: center;font-size: 15px;'>Chequeo Preventivo Cardiovascular</h3>";
        
        $html .= "<div class='patient-info'>";
        $html .= "<strong style='font-size: 13px;'>Identificación del Paciente</strong>";


        $html .= "<table>"; 
        $html .= "<tr>";
        $html .= "<td style='font-size: 11px;width: 40%;'><strong>Nombre : </strong>". $chequeoCardiovascular->nombre."</td>";
        $html .= "<td style='font-size: 11px;width: 30%;'><strong>R.U.T : </strong>". $chequeoCardiovascular->rut."</td>";
        $html .= "<td style='font-size: 11px;width: 30%;'><strong>Fecha de Nacimiento : </strong>".Carbon::parse($chequeoCardiovascular->fechaNacimiento)->format('d-m-Y')."</td>";
        $html .= "</tr>";
        $html .= "</table>";
        
        $html .= "<table>"; 
        $html .= "<tr>";
        $html .= "<td style='font-size: 11px; width: 25%;'><strong>Edad</strong> : ".$chequeoCardiovascular->edad." Años</strong></td>";
        $html .= "<td style='font-size: 11px; width: 22%;'><strong>Estatura (cm)</strong> : ".$chequeoCardiovascular->estatura."</p></td>";
        $html .= "<td style='font-size: 11px; width: 24.6%;'><strong>Peso  (kg) : </strong>".$chequeoCardiovascular->peso."</p></td>";
        $html .= "<td style='font-size: 11px;'><strong>Fecha de Atención : </strong>".Carbon::parse($chequeoCardiovascular->updated_at)->format('d-m-Y')."</p></td>";
        $html .= "</tr>";
        $html .= "</table>";
        
        
        $html .= "<div style='font-size: 13px;'><strong>Antecedentes Clínicos : </strong></div>";
        $html .= "<div style='font-size: 11px;'>";
        $html .= "<ul >";
        $html .= "<li >Pulso: <span style='border-bottom: 1px solid black;'>".(isset($electroCardiograma->frecuencia_cardiaca_paciente) ? $electroCardiograma->frecuencia_cardiaca_paciente : '0')."</span> por minuto</li>";
        $html .= "<li >Presión Arterial (mm Hg): <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->presionArterial."</span></li>";
        $html .= "<li >Saturación de O2 (%): <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->saturacionOxigeno."%</span></li>";
        $html .= "<li >Temperatura (°C): <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->temperatura."</span></li>";
        $html .= "<li >Hemoglucotest (mg/dL): <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->hemoglucotest."</span></li>";
        $html .= "<li >Índice Masa Corporal (IMC): <span style='border-bottom: 1px solid black;'>".$IMFText."</span></li>";
        $html .= "<li >Presencia de Enf. Crónicas y medicamentos: <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->enfermedadesCronicas."</span></li>";
        $html .= "<li >Sistema Osteoarticular:  <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->sistemaOsteoarticular."</span></li>";
        $html .= "<li >Sistema cardiovascular:  <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->sistemaCardiovascular."</span></li>";
        $html .= "<li >Presencia de enfermedades anteriores que afecten la actividad física :  <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->enfermedadesAnteriores."</span></li>";
        $html .= "<li >Recuperación lograda en los casos anteriores y grado de incidencia posterior : <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->gradoIncidenciaPosterio."</span></li>";
        
        $html .= "</ul>";
        $html .= "</div>";
        
        if (isset($electroCardiograma->estado_paciente) && $electroCardiograma->estado_paciente == 'Alterado')
        $html .= " <strong style='font-size: 10px;'>El Electrocardiograma realizado el ".Carbon::parse($chequeoCardiovascular->updated_at)->format('d-m-Y').".</strong>";
        else {
            $html .= " <p style='font-size: 10px;'>El Electrocardiograma realizado el ".Carbon::parse($chequeoCardiovascular->updated_at)->format('d-m-Y')." se encuentra dentro de los límites normales acorde a la edad.</p>";
        }
        $html .= "<table>";

        $html .= "<tr><td style='font-size: 11px; vertical-align: top;'>";

        //$html .= "<ul >";
        //$html .= "<li ><strong>Frecuencia Cardiaca </strong> : <span style='border-bottom: 1px solid black;'>".(isset($electroCardiograma->frecuencia_cardiaca_paciente) ? $electroCardiograma->frecuencia_cardiaca_paciente : '0')."</span></li>";
        //$html .= "</ul>";    
        
        $html .= "<span >- Frecuencia Cardiaca   <span style='border-bottom: 1px solid black;'>".(isset($electroCardiograma->frecuencia_cardiaca_paciente) ? $electroCardiograma->frecuencia_cardiaca_paciente : '0')."</span>LPM</span><br />";
        
        $html .= "<span >".(isset($electroCardiograma->observacion_paciente) ? nl2br(htmlspecialchars($electroCardiograma->observacion_paciente)) : '')."</span>";
        $html .= "</td>";

        $html .= "<td>";
        
        $html .= "<img src='".$firmaErgo."' alt='logo' style='width:180px; height:150px; margin-left: 100px' />";
        
        $html .= "</td></tr>";

        $html .= "</table>";  
        
        if (isset($electroCardiograma->estado_paciente) && $electroCardiograma->estado_paciente == 'Alterado')
        $html .= " <span style='font-weight: bold; font-size: 11px;'>Se deriva a ".$chequeoCardiovascular->nombre." a unidad ".$electroCardiograma->derivacion_paciente.".</span>";
        else {
            $html .= "<span style='font-weight: bold; font-size: 11px;'>Certifico que hasta la presente fecha " . $chequeoCardiovascular->nombre . " se encuentra apto para la realización de actividades físicas y/o deportivas de mayor rendimiento.</span>";
            $html .= "<br /><br><span style='font-weight: bold; font-size: 11px;'>Se extiende el presente certificado para centro deportivo.</span>";
        }
        
        $html .= "<table>";

        $html .= "<tr><td ><br />";
        
        $html .= "<img src='".$firmaDoc."' alt='Firma Medica' style='width:180px; height:130px; margin-left: 200px;' />";
        
        $html .= "</td></tr>";

        $html .= "</table>";
        
        
        $html .= "<p style='font-size: 8px; text-align: center;'><em>*Se recomienda realizar un chequeo preventivo cada 6 meses en personas con actividad deportiva mayor a 3 veces por semana.</em></p>";
        $html .= "<div class='footer'>";
        $html .= "<p style='font-size: 8px;'>San Bernardo, Región Metropolitana, Chile";
        $html .= " - 569 6114 9975";
        $html .= " - Contacto@ergosanitas.com";
        $html .= " - www.ergosanitas.com</p>";
        $html .= "</div>";
        $html .= "</div>";

         //IMG ELECTRO
        //$logoElec = asset('storage/app/public/Electrocardiograma/1727279326_1717770586636.png');    
        
        
       if(filled($chequeoCardiovascular->fileName)) {

            $rutaPath = "Electrocardiograma/".$chequeoCardiovascular->fileName;  
            $logoElec = public_path($rutaPath);
            
            $html .= "<div style='width: 100%; height: 100%;'>";
$html .= "<img src='" . $logoElec . "' alt='Electrocardiograma' style='width: 200%; height: 100%; object-fit: cover;' />";
$html .= "</div>";
            
        }
        
        
        $html .= "</body>";
        $html .= "</html>";



    
        $mpdf = new Mpdf();
        
        $mpdf->WriteHTML($html);
        // Generar el archivo PDF
        $pdfOutput = $mpdf->Output('', 'S'); // S: Retorna el contenido como string

        return response($pdfOutput)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="Certificado '.$chequeoCardiovascular->nombre.'.pdf"');
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
