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
        //Subquery: trae SOLO el último electro por id_chequeo
        $subEC = DB::table('electro_cardiogranas as e1')
            ->select(
                'e1.id_chequeo',
                'e1.rut_paciente',
                'e1.estado_paciente',
                'e1.frecuencia_cardiaca_paciente',
                'e1.derivacion_paciente',
                'e1.observacion_paciente'
            )
            ->whereRaw('e1.id = (
                SELECT e2.id
                FROM electro_cardiogranas e2
                WHERE e2.id_chequeo = e1.id_chequeo
                ORDER BY e2.created_at DESC
                LIMIT 1
            )');

        $query = DB::table('chequeo_cardiovascular as cc')
            ->leftJoinSub($subEC, 'ec', function ($join) {
                $join->on('cc.id', '=', 'ec.id_chequeo');
            });

        // -----------------------------
        // FILTROS
        // -----------------------------

        if ($perfilId == 3) {
            $query->where('cc.user_email', $user_email);
        } elseif (!empty($selectClub)) {
            $query->where('cc.user_email', $selectClub);
        }
        if ($perfilId == 6) {
             $query->where('cc.status', 'ECG FOTO');
        }

        if ($perfilId == 6) {
            $query->leftJoin('certificado_url as cu', function ($join) {
                $join->on('cc.rut', '=', 'cu.rut_paciente')
                    ->on('cc.id', '=', 'cu.id_chequeo');
            });

            $query->where('cu.derivado_medico', 'SI');

        }

        if (!empty($fecha_calendar)) {
            $fechaFormateada = Carbon::parse($fecha_calendar)->format('Y-m-d');

            $query->where(function ($q) use ($fechaFormateada) {
                $q->whereDate('cc.created_at', $fechaFormateada)
                ->orWhereDate('cc.fecha_atencion', $fechaFormateada);
            });
        }

        if (!empty($textoValue)) {
            $query->where(function ($q) use ($textoValue) {
                $q->where('cc.rut', 'LIKE', "%{$textoValue}%")
                ->orWhere('cc.nombre', 'LIKE', "%{$textoValue}%");
            });
        }

        // -----------------------------
        // SELECT
        // -----------------------------
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
                    WHEN cc.status = 'REVISION MEDICA'
                        AND ec.estado_paciente = 'Alterado'
                    THEN 'Diag. Card. - Alterado'

                    WHEN cc.status = 'REVISION MEDICA'
                        AND ec.estado_paciente = 'Normal'
                    THEN 'Diag. Card. - Normal'

                    WHEN cc.status = 'REVISION MEDICA'
                    THEN 'En Rev. Cardio'

                    ELSE cc.status
                END as estado_paciente
            "),

            DB::raw("COALESCE(ec.frecuencia_cardiaca_paciente, '-') as frecuencia_cardiaca_paciente"),
            DB::raw("COALESCE(ec.derivacion_paciente, '-') as derivacion_paciente"),
            DB::raw("COALESCE(ec.observacion_paciente, '-') as observacion_paciente")
        );


        // -----------------------------
        // ORDER
        // -----------------------------
        if ($perfilId == 3) {
            $query->orderByRaw("FIELD(cc.status, 'ECG FOTO', 'REVISION MEDICA', 'Testiado', 'ingresado')")
                ->orderBy('cc.created_at', 'desc');
        } else {
            $query->orderBy('cc.created_at', 'desc');
        }

        // -----------------------------
        // PAGINACIÓN
        // -----------------------------
        $hasFilters = !empty($textoValue) || !empty($fecha_calendar) || !empty($selectClub);

        if (!$hasFilters) {
            $paginacion = $query->simplePaginate(min($limit, 300), ['*'], 'page', $page);

            return response()->json([
                'data' => $paginacion->items(),
                'current_page' => $paginacion->currentPage(),
                'per_page' => $paginacion->perPage(),
                'next_page_url' => $paginacion->nextPageUrl(),
                'prev_page_url' => $paginacion->previousPageUrl(),
                'total' => 300
            ]);
        }

        $paginacion = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginacion->items(),
            'current_page' => $paginacion->currentPage(),
            'per_page' => $paginacion->perPage(),
            'next_page_url' => $paginacion->nextPageUrl(),
            'prev_page_url' => $paginacion->previousPageUrl(),
            'total' => $paginacion->total()
        ]);
    }

    public function ChequeoEmailAll($user_email, $perfilId) {

        // Subquery: obtener el último ECG por id_chequeo
        $subQueryECG = DB::raw("
            (
                SELECT e1.id_chequeo,
                    e1.estado_paciente,
                    e1.frecuencia_cardiaca_paciente
                FROM electro_cardiogranas e1
                INNER JOIN (
                    SELECT id_chequeo, MAX(id) as max_id
                    FROM electro_cardiogranas
                    GROUP BY id_chequeo
                ) e2
                ON e1.id = e2.max_id
            ) as ec
        ");

        $query = DB::table('chequeo_cardiovascular as cc')
            ->leftJoin($subQueryECG, 'cc.id', '=', 'ec.id_chequeo')
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

                DB::raw("
                    DATE_FORMAT(
                        COALESCE(cc.fecha_atencion, cc.created_at),
                        '%d-%m-%Y'
                    ) AS fecha_atencion
                "),

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

                DB::raw("
                    COALESCE(ec.frecuencia_cardiaca_paciente, '-')
                    AS frecuencia_cardiaca_paciente
                ")
            ])
            ->orderByDesc('cc.id');

        // Perfil usuario → solo ve sus registros
        if ($perfilId == 3) {
            $query->where('cc.user_email', $user_email);
        }

        // Perfil médico → solo derivados
        if ($perfilId == 6) {
            $query->join('certificado_url as cu', function ($join) {
                $join->on('cc.rut', '=', 'cu.rut_paciente')
                    ->on('cc.id', '=', 'cu.id_chequeo');
            })
            ->where('cu.derivado_medico', 'SI');
        }

        return $query->get();
    }
    public function chequeoUserEmail(string $user_email): array
    {
        return DB::table('chequeo_cardiovascular as cc')
            ->leftJoin('electro_cardiogranas as ec', 'cc.rut', '=', 'ec.rut_paciente')
            ->where('cc.user_email', $user_email)
            ->select([
                'cc.*',

                DB::raw("DATE_FORMAT(cc.fecha_atencion, '%d/%m/%Y') as fecha_atencion_format"),
                DB::raw("DATE_FORMAT(cc.created_at, '%d/%m/%Y') as created_at_format"),

                DB::raw("IFNULL(ec.estado_paciente, 'En Revision') as estado_paciente"),
                DB::raw("IFNULL(ec.frecuencia_cardiaca_paciente, '-') as frecuencia_cardiaca_paciente"),
                DB::raw("IFNULL(ec.derivacion_paciente, '-') as derivacion_paciente"),
                DB::raw("IFNULL(ec.observacion_paciente, '-') as observacion_paciente"),
            ])
            ->orderByDesc('cc.id')
            ->get()
            ->toArray();
    }
}
