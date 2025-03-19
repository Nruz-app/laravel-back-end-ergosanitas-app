<?php
namespace App\Services;
use Carbon\Carbon;
use App\Models\ChequeoCardiovascular;
use App\Models\ElectroCardiograma;

class ChequeoCardiovascularPDFService {

    public function getPercentil($imc, $edad, $sexo)
    {
        $edadNum = (int) $edad;
        $imcNum  = (int) $imc; // Conversión a entero


        if($edadNum >= 18) {

            if ($imcNum < 18.5) return 'Bajo peso';
            elseif($imcNum < 25) return 'Peso normal';
            elseif($imcNum < 30) return 'Sobrepeso';
            else return 'Obesidad';
        }
        else {

            $base = $sexo === 'Masculino'
                ? 16 + ($edadNum * 0.23)
                : 15.5 + ($edadNum * 0.21);

            $desviacion = $sexo === 'Masculino'
                ? 1.8 + ($edadNum * 0.08)
                : 1.6 + ($edadNum * 0.07);

            $diferencia = $imcNum - $base;
            $percentil = 50 + ($diferencia / $desviacion) * 34;

            // Ajustar límites
            $percentil = max(min($percentil, 99.9), 0.1);

            if ($percentil < 5) return  "Bajo peso";
            elseif ($percentil < 85) return "Peso saludable";
            elseif ($percentil < 95) return "Sobrepeso";
            else return "Obesidad";

        }
    }


    public function chequeoPDf(int $id_paciente)
    {
        $logoPath = public_path('logo.png');//logoTrans.png
        $firmaDoc = public_path('firmarDoctor.jpg');
        $firmaErgo = public_path('firmarErgo.jpg');

        $chequeoCardiovascular = ChequeoCardiovascular::where(['id' => $id_paciente])
            ->orderBy('id', 'desc')
            ->first();

        $electroCardiograma = ElectroCardiograma::where(['id_chequeo' => $id_paciente])
            ->first();

        $percentil = $this->getPercentil(
                $chequeoCardiovascular->imc_paciente,
                $chequeoCardiovascular->edad,
                $chequeoCardiovascular->sexo_paciente);


        $IMFText = '';
        if($electroCardiograma && $electroCardiograma->imc_paciente){
            $IMFText = $electroCardiograma->imc_paciente;
        }

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
        //$html .= "<strong style='font-size: 13px;'>Identificación del Paciente</strong>";

        $html .= "<strong style='font-size: 13px;'>Identificación del Paciente</strong>";


        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<td style='font-size: 11px;width: 40%;'><strong>Nombre : </strong>". ucwords(strtolower($chequeoCardiovascular->nombre))."</td>";
        $html .= "<td style='font-size: 11px;width: 30%;'><strong>R.U.T : </strong>". $chequeoCardiovascular->rut."</td>";
        $html .= "<td style='font-size: 11px;width: 30%;'><strong>Fecha de Nacimiento : </strong>".Carbon::parse($chequeoCardiovascular->fechaNacimiento)->format('d-m-Y')."</td>";
        $html .= "</tr>";
        $html .= "</table>";

        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<td style='font-size: 11px; width: 25%;'><strong>Edad</strong> : ".$chequeoCardiovascular->edad." Años</strong></td>";
        $html .= "<td style='font-size: 11px; width: 22%;'><strong>Estatura (cm)</strong> : ".$chequeoCardiovascular->estatura."</p></td>";
        $html .= "<td style='font-size: 11px; width: 24.6%;'><strong>Peso  (kg) : </strong>".$chequeoCardiovascular->peso."</p></td>";
        $html .= "<td style='font-size: 11px;'><strong>Fecha de Atención : </strong>".Carbon::parse($chequeoCardiovascular->fecha_atencion)->format('d-m-Y')."</p></td>";
        $html .= "</tr>";
        $html .= "</table>";


        $html .= "<div style='font-size: 13px;'><strong>Antecedentes Clínicos : </strong></div>";
        $html .= "<div style='font-size: 11px;'>";
        $html .= "<ul >";
        $html .= "<li >Pulso: <span style='border-bottom: 1px solid black;'>".(isset($electroCardiograma->frecuencia_cardiaca_paciente) ? $electroCardiograma->frecuencia_cardiaca_paciente : '0')."</span> por minuto</li>";
        $html .= "<li >Presión Arterial (mm Hg): <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->presion_sistolica."/".$chequeoCardiovascular->presionArterial."</span></li>";
        $html .= "<li >Saturación de O2 (%): <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->saturacionOxigeno."%</span></li>";
        $html .= "<li >Temperatura (°C): <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->temperatura."</span></li>";
        $html .= "<li >Hemoglucotest (mg/dL): <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->hemoglucotest."</span></li>";
        $html .= "<li >Índice Masa Corporal (IMC): <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->imc_paciente." (".$percentil.") </span></li>";
        $html .= "<li >Presencia de Enf. Crónicas y medicamentos: <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->enfermedadesCronicas."</span></li>";
        $html .= "<li >Sistema Osteoarticular:  <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->sistemaOsteoarticular."</span></li>";
        $html .= "<li >Sistema cardiovascular:  <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->sistemaCardiovascular."</span></li>";
        $html .= "<li >Presencia de enfermedades anteriores que afecten la actividad física :  <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->enfermedadesAnteriores."</span></li>";
        $html .= "<li >Recuperación lograda en los casos anteriores y grado de incidencia posterior : <span style='border-bottom: 1px solid black;'>".$chequeoCardiovascular->gradoIncidenciaPosterio."</span></li>";

        $html .= "</ul>";
        $html .= "</div>";

        if (isset($electroCardiograma->estado_paciente) && $electroCardiograma->estado_paciente == 'Alterado')
        $html .= " <strong style='font-size: 10px;'>El Electrocardiograma realizado el ".Carbon::parse($chequeoCardiovascular->fecha_atencion)->format('d-m-Y').".</strong>";
        else {
            $html .= " <p style='font-size: 10px;'>El Electrocardiograma realizado el ".Carbon::parse($chequeoCardiovascular->fecha_atencion)->format('d-m-Y')." se encuentra dentro de los límites normales acorde a la edad.</p>";
        }
        $html .= "<table>";

        $html .= "<tr><td style='font-size: 11px; vertical-align: top;'>";

        //$html .= "<ul >";
        //$html .= "<li ><strong>Frecuencia Cardiaca </strong> : <span style='border-bottom: 1px solid black;'>".(isset($electroCardiograma->frecuencia_cardiaca_paciente) ? $electroCardiograma->frecuencia_cardiaca_paciente : '0')."</span></li>";
        //$html .= "</ul>";

        $html .= "<span >- Frecuencia Cardiaca   <span style='border-bottom: 1px solid black;'>".(isset($electroCardiograma->frecuencia_cardiaca_paciente) ? $electroCardiograma->frecuencia_cardiaca_paciente : '0')."</span>LPM</span><br />";

        $html .= "<span >".(isset($electroCardiograma->observacion_paciente) ? nl2br(htmlspecialchars($electroCardiograma->observacion_paciente)) : '')."</span>";
        $html .= "</td>";



        if(isset($electroCardiograma->rut_paciente)) {
            $html .= "<td>";
            $html .= "<img src='".$firmaErgo."' alt='logo' style='width:180px; height:150px; margin-left: 100px' />";
            $html .= "</td></tr>";
            $html .= "</table>";
        }


        if (isset($electroCardiograma->estado_paciente) && $electroCardiograma->estado_paciente == 'Alterado')
            $html .= " <span style='font-weight: bold; font-size: 11px;'>Se deriva a ".$chequeoCardiovascular->nombre." a unidad ".$electroCardiograma->derivacion_paciente.".</span>";
        else {
            $html .= "<span style='font-weight: bold; font-size: 11px;'>Certifico que hasta la presente fecha " . $chequeoCardiovascular->nombre . " se encuentra apto para la realización de actividades físicas y/o deportivas.</span>";
            $html .= "<br /><br><span style='font-weight: bold; font-size: 11px;'>Se extiende el presente certificado para centro deportivo.</span>";
        }

        if(isset($electroCardiograma->rut_paciente)) {

            $html .= "<table>";
            $html .= "<tr><td ><br />";
            $html .= "<img src='".$firmaDoc."' alt='Firma Medica' style='width:180px; height:130px; margin-left: 200px;' />";
            $html .= "</td></tr>";
            $html .= "</table>";
        }


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

        return $html;
    }

}
