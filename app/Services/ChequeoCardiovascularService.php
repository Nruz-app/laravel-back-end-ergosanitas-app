<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;
use App\Models\ChequeoCardiovascular;
use Carbon\Carbon;

use Illuminate\Support\Facades\Log;


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

        // Devuelve la colección con los resultados
        return json_decode(json_encode($resChequeoCardiovascular->get()), true);
    }

    public function SearchChequeo($perfilId, $textoValue, $fecha_calendar, $selectClub, $user_email, $limit, $page)
    {
        /* 🔥 LOG SQL (opcional, puedes comentarlo después)
        DB::listen(function ($query) {
            Log::info("🧠 SQL", [
                'time_ms' => $query->time,
                'sql' => $query->sql
            ]);
        });
        */
        $start = microtime(true);

        // 🔥 Subquery optimizada
        $subEC = DB::table('electro_cardiogranas')
            ->select(
                'rut_paciente',
                DB::raw('MAX(estado_paciente) as estado_paciente'),
                DB::raw('MAX(frecuencia_cardiaca_paciente) as frecuencia_cardiaca_paciente'),
                DB::raw('MAX(derivacion_paciente) as derivacion_paciente'),
                DB::raw('MAX(observacion_paciente) as observacion_paciente')
            )
            ->groupBy('rut_paciente');

        $query = DB::table('chequeo_cardiovascular as cc')
            ->leftJoinSub($subEC, 'ec', function ($join) {
                $join->on('cc.rut', '=', 'ec.rut_paciente');
            });

        // 🔹 Filtro por rol
        if ($perfilId == 3) {
            $query->where('cc.user_email', $user_email);
        }

        // 🔹 SOLO rol 6
        if ($perfilId == 6) {
            $query->leftJoin('certificado_url as cu', function ($join) {
                $join->on('cc.rut', '=', 'cu.rut_paciente')
                    ->on('cc.id', '=', 'cu.id_chequeo');
            });

            $query->where('cu.derivado_medico', 'SI');
        }

        // 🔹 Filtro por fecha
        if (!empty($fecha_calendar)) {
            $fechaFormateada = Carbon::parse($fecha_calendar)->format('Y-m-d');

            $query->where(function ($q) use ($fechaFormateada) {
                $q->whereBetween('cc.created_at', [
                    $fechaFormateada . ' 00:00:00',
                    $fechaFormateada . ' 23:59:59'
                ])
                ->orWhereBetween('cc.fecha_atencion', [
                    $fechaFormateada . ' 00:00:00',
                    $fechaFormateada . ' 23:59:59'
                ]);
            });
        }

        // 🔹 Filtro por texto
        if (!empty($textoValue)) {
            $query->where(function ($q) use ($textoValue) {
                $q->where('cc.rut', 'LIKE', "%{$textoValue}%")
                ->orWhere('cc.nombre', 'LIKE', "%{$textoValue}%");
            });
        }

        // 🔹 Filtro por club
        if (!empty($selectClub)) {
            $query->where('cc.user_email', $selectClub);
        }

        // 🔹 SELECT
        $query->select(
            'cc.id',
            'cc.rut',
            'cc.nombre',
            'cc.user_email',
            'cc.status',
            'cc.edad',
            'cc.created_at',
            'cc.fecha_atencion',

            DB::raw("DATE_FORMAT(cc.fecha_atencion, '%d/%m/%Y') as fecha_atencion_formatted"),
            DB::raw("DATE_FORMAT(cc.created_at, '%d/%m/%Y') as created_at_formatted"),

            DB::raw("
                CASE
                    WHEN cc.status = 'REVISION MEDICA' AND ec.estado_paciente IS NOT NULL
                        THEN CONCAT('Diag. Card. - ', ec.estado_paciente)
                    WHEN cc.status = 'REVISION MEDICA'
                        THEN 'En Rev. Cardio'
                    ELSE cc.status
                END as estado_paciente
            "),

            DB::raw("COALESCE(ec.frecuencia_cardiaca_paciente, '-') as frecuencia_cardiaca_paciente"),
            DB::raw("COALESCE(ec.derivacion_paciente, '-') as derivacion_paciente"),
            DB::raw("COALESCE(ec.observacion_paciente, '-') as observacion_paciente")
        )
        ->orderBy('cc.id', 'desc');

        // 🔹 Detectar filtros
        $hasFilters = !empty($textoValue) || !empty($fecha_calendar) || !empty($selectClub);

        // 🔥 Sin filtros → limitar a 300
        if (!$hasFilters) {
            $query->limit(300);
        }

        // 🚀 PAGINACIÓN OPTIMIZADA (sin COUNT)
        $paginacion = $query->simplePaginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginacion->items(),
            'current_page' => $paginacion->currentPage(),
            'per_page' => $paginacion->perPage(),
            'next_page_url' => $paginacion->nextPageUrl(),
            'prev_page_url' => $paginacion->previousPageUrl(),
            'total' => $hasFilters ? null : 300 // ya no usamos COUNT
        ]);
    }
    public function ChequeoEmailAll($user_email, $perfilId) {

    $query = DB::table('chequeo_cardiovascular as cc')
        ->leftJoin(DB::raw("
            (SELECT
                id_chequeo,
                MAX(estado_paciente) as estado_paciente,
                MAX(frecuencia_cardiaca_paciente) as frecuencia_cardiaca_paciente
             FROM electro_cardiogranas
             GROUP BY id_chequeo
            ) ec
        "), 'cc.id', '=', 'ec.id_chequeo')
        ->select([
            'cc.id',
            'cc.nombre',
            'cc.rut',
            'cc.edad',
            'cc.estatura',
            'cc.peso',
            'cc.hemoglucotest',
            'cc.presionArterial',
            'cc.presion_sistolica',
            'cc.saturacionOxigeno',
            'cc.temperatura',
            'cc.imc_paciente',
            'cc.sexo_paciente',
            'cc.user_email',
            'cc.status',
            DB::raw("DATE_FORMAT(COALESCE(cc.fecha_atencion, cc.created_at), '%d-%m-%Y') AS fecha_atencion"),
            DB::raw("
                CASE
                    WHEN cc.status = 'REVISION MEDICA'
                        AND ec.estado_paciente IS NOT NULL
                    THEN CONCAT('Diag. Card. - ', ec.estado_paciente)

                    WHEN cc.status = 'REVISION MEDICA'
                    THEN 'En Rev. Cardio'

                    ELSE cc.status
                END as estado_paciente
            "),
            DB::raw("COALESCE(ec.frecuencia_cardiaca_paciente, '-') AS frecuencia_cardiaca_paciente")
        ])
        ->orderByDesc('cc.id');

    if ($perfilId == 3) {
        $query->where('cc.user_email', $user_email);
    }

    if ($perfilId == 6) {
        $query->leftJoin('certificado_url as cu', function ($join) {
            $join->on('cc.rut', '=', 'cu.rut_paciente')
                 ->on('cc.id', '=', 'cu.id_chequeo');
        });

        $query->where('cu.derivado_medico', 'SI');
    }

    return $query->get();
}





}
