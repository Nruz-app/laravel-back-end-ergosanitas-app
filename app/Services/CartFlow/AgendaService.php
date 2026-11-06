<?php

namespace App\Services\CartFlow;

use App\Models\CartFlow\BloqueAgenda;
use App\Models\CartFlow\Pedido;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SPEC 04 — Bloques disponibles por fecha y zona.
 *
 * Cupo = visitas (pedidos) por bloque y fecha; cuentan todos los pedidos no anulados.
 * Si el carro exige manana (ayuno, laboratorio o solo_ruta_manana) solo se ofrece la franja "manana".
 */
class AgendaService
{
    /**
     * @return array<int, array{fecha:string, dia_semana:int, bloques: array}>
     */
    public function disponibles(?int $zonaId, bool $soloManana, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $bloques = $this->bloques($zonaId, $soloManana);

        if ($bloques->isEmpty()) {
            return [];
        }

        $ocupados = Pedido::query()
            ->where('estado', '!=', 'anulado')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('bloque_id', $bloques->pluck('id'))
            ->selectRaw('fecha, bloque_id, COUNT(*) as usados')
            ->groupBy('fecha', 'bloque_id')
            ->get()
            ->mapWithKeys(fn ($fila) => [Carbon::parse($fila->fecha)->toDateString().'#'.$fila->bloque_id => (int) $fila->usados]);

        $dias = [];

        for ($dia = Carbon::parse($desde)->startOfDay(); $dia->lte($hasta); $dia->addDay()) {
            $delDia = $bloques->where('dia_semana', $dia->isoWeekday());

            if ($delDia->isEmpty()) {
                continue;
            }

            $dias[] = [
                'fecha' => $dia->toDateString(),
                'dia_semana' => $dia->isoWeekday(),
                'bloques' => $delDia->map(function (BloqueAgenda $b) use ($dia, $ocupados) {
                    $usados = $ocupados->get($dia->toDateString().'#'.$b->id, 0);

                    return [
                        'id' => $b->id,
                        'bloque' => $b->etiqueta(),
                        'franja' => $b->franja,
                        'cupo' => $b->cupo,
                        'libres' => max(0, $b->cupo - $usados),
                    ];
                })->values()->all(),
            ];
        }

        return $dias;
    }

    /** Bloques activos que aplican a la zona (los de zona null aplican a todas). */
    public function bloques(?int $zonaId, bool $soloManana): Collection
    {
        return BloqueAgenda::query()
            ->where('activo', true)
            ->where(fn ($q) => $q->whereNull('zona_id')->when($zonaId, fn ($q2) => $q2->orWhere('zona_id', $zonaId)))
            ->when($soloManana, fn ($q) => $q->where('franja', 'manana'))
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();
    }

    /** Visitas ya tomadas en un bloque y fecha (pedidos no anulados). */
    public function usados(int $bloqueId, string $fecha): int
    {
        return Pedido::where('bloque_id', $bloqueId)
            ->whereDate('fecha', $fecha)
            ->where('estado', '!=', 'anulado')
            ->count();
    }

    /** Rango [desde, hasta] permitido segun las reglas de anticipacion. */
    public function rango(array $reglas, ?string $desde = null, ?string $hasta = null): array
    {
        $minimo = Carbon::today()->addDays((int) $reglas['agenda_anticipacion_dias']);
        $maximo = Carbon::today()->addDays((int) $reglas['agenda_dias_max']);

        $inicio = $desde ? Carbon::parse($desde)->startOfDay() : $minimo->copy();
        $fin = $hasta ? Carbon::parse($hasta)->startOfDay() : $maximo->copy();

        if ($inicio->lt($minimo)) {
            $inicio = $minimo->copy();
        }

        if ($fin->gt($maximo)) {
            $fin = $maximo->copy();
        }

        return [$inicio, $fin, $minimo, $maximo];
    }
}
