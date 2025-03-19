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

    public function chequeoUserEmail($user_email) {

        //Filtra por el email y fecha creacion o actualizacion
        return ChequeoCardiovascular::where('user_email', $user_email)
            ->get();
    }

}
