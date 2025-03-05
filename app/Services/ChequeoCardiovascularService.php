<?php

namespace App\Services;

use App\Models\ChequeoCardiovascular;
use Carbon\Carbon;

class ChequeoCardiovascularService
{
   /**
     * Register services.
     */
    public function filterCalendar($perfilId, $fecha_calendar, $user_email)
    {

        $resChequeo = ChequeoCardiovascular::
             whereDate('created_at', Carbon::parse($fecha_calendar)->format('Y-m-d'))
            ->orWhereDate('updated_at', Carbon::parse($fecha_calendar)->format('Y-m-d'));

        // Aplica el filtro solo si el perfil no es 3
        if ($perfilId == 3) {

           //Filtra por el email y fecha creacion o actualizacion
           $resChequeo = ChequeoCardiovascular::where('user_email', $user_email)
           ->where(function($query) use ($fecha_calendar) {
               $query->whereDate('created_at', Carbon::parse($fecha_calendar)->format('Y-m-d'))
               ->orWhereDate('updated_at', Carbon::parse($fecha_calendar)->format('Y-m-d'));
            });
        }

        // Devuelve la colección con los resultados
        return $resChequeo->get();
    }
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

}
