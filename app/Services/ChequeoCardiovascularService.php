<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;
use App\Models\ChequeoCardiovascular;
use Carbon\Carbon;

class ChequeoCardiovascularService
{
   /**
     * Register services.
     */
    public function filterCalendar($perfilId, $fecha_calendar, $user_email)
    {

        $resChequeoCardiovascular = DB::table('chequeo_cardiovascular as cc')
        ->leftJoin('electro_cardiogranas as ec', 'cc.rut', '=', 'ec.rut_paciente');
        if($perfilId == 3) {
            $resChequeoCardiovascular->where('cc.user_email', $user_email);
        }
        $resChequeoCardiovascular->where(function ($query) use ($fecha_calendar) {
            $query->whereDate('cc.created_at', Carbon::parse($fecha_calendar)->format('Y-m-d'))
              ->orWhereDate('cc.fecha_atencion', Carbon::parse($fecha_calendar)->format('Y-m-d'));
        });
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

        // Devuelve la colección con los resultados
        return json_decode(json_encode($resChequeoCardiovascular->get()), true);
    }

    public function chequeoUserEmail($user_email) {

        //Filtra por el email y fecha creacion o actualizacion
        $resChequeoCardiovascular = DB::table('chequeo_cardiovascular as cc')
        ->leftJoin('electro_cardiogranas as ec', 'cc.rut', '=', 'ec.rut_paciente')
        ->where('user_email', $user_email)
        ->select(
        'cc.*',
        DB::raw("DATE_FORMAT(cc.fecha_atencion, '%d/%m/%Y') as fecha_atencion") ,
        DB::raw("DATE_FORMAT(cc.created_at, '%d/%m/%Y') as created_at") ,
        DB::raw("COALESCE(ec.estado_paciente, 'En Revision') as estado_paciente"),
        DB::raw("COALESCE(ec.frecuencia_cardiaca_paciente, '-') as frecuencia_cardiaca_paciente"),
        DB::raw("COALESCE(ec.derivacion_paciente, '-') as derivacion_paciente"),
        DB::raw("COALESCE(ec.observacion_paciente, '-') as observacion_paciente"))
        ->orderBy('cc.id', 'desc')
        ->get();
        return json_decode(json_encode($resChequeoCardiovascular), true);
    }

    public function SearchChequeo($perfilId,$textoValue,$fecha_calendar,$selectClub,$user_email) {


        $resChequeoCardiovascular = DB::table('chequeo_cardiovascular as cc')
            ->leftJoin('electro_cardiogranas as ec', 'cc.rut', '=', 'ec.rut_paciente');

        // Filtra por Rol
        if ($perfilId == 3) {
            $resChequeoCardiovascular->where('cc.user_email', $user_email);
        }

        // Filtra por Fecha
        if (!empty($fecha_calendar)) {
            $fechaFormateada = Carbon::parse($fecha_calendar)->format('Y-m-d');

            $resChequeoCardiovascular->where(function ($query) use ($fechaFormateada) {
                $query->whereDate('cc.created_at', $fechaFormateada)
                    ->orWhereDate('cc.fecha_atencion', $fechaFormateada);
            });
        }

        // Filtra por Texto
        if (!empty($textoValue)) {
            $resChequeoCardiovascular->where(function ($query) use ($textoValue) {
                $query->where('cc.rut', 'like', '%' . $textoValue . '%')
                    ->orWhere('cc.nombre', 'like', '%' . $textoValue . '%');
            });
        }

        // Filtra por Club (Email)
        if (!empty($selectClub)) {
            $resChequeoCardiovascular->where('cc.user_email', $selectClub);
        }


        $resChequeoCardiovascular->select(
        'cc.*',
        DB::raw("DATE_FORMAT(cc.fecha_atencion, '%d/%m/%Y') as fecha_atencion") ,
        DB::raw("DATE_FORMAT(cc.created_at, '%d/%m/%Y') as created_at") ,
        DB::raw("COALESCE(ec.estado_paciente, 'En Revisión') as estado_paciente"),
        DB::raw("COALESCE(ec.frecuencia_cardiaca_paciente, '-') as frecuencia_cardiaca_paciente"),
        DB::raw("COALESCE(ec.derivacion_paciente, '-') as derivacion_paciente"),
        DB::raw("COALESCE(ec.observacion_paciente, '-') as observacion_paciente")
        )
        ->limit(300)
        ->orderBy('cc.id', 'desc')
        ->get();

        // Devuelve la colección con los resultados
        return json_decode(json_encode($resChequeoCardiovascular->get()), true);

    }

}
