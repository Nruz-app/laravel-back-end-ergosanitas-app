<?php

namespace App\Services;

use App\Models\JuegoAtributo;
use App\Models\JuegoCartaClub;
use App\Models\JuegoNivel;

class JuegoCartasService
{
    /**
     * Cartas de un club, ordenadas de mejor a peor.
     */
    public function CartasClub(?string $search, string $club): array
    {
        $cartas = $this->llamarSP($search, $club);

        $this->ordenar($cartas);

        return $cartas;
    }

    /**
     * Una sola carta por rut, sin filtro de club.
     * Devuelve null si el rut no tiene ningún chequeo registrado.
     */
    public function CartaDetalle(string $rut): ?array
    {
        $cartas = $this->llamarSP($rut, null);

        // El SP filtra con LIKE, así que un rut parcial puede traer más de una carta:
        // se devuelve la coincidencia exacta si existe, y si no la primera.
        foreach ($cartas as $carta) {
            if (isset($carta['rut']) && $carta['rut'] === $rut) {
                return $carta;
            }
        }

        return $cartas[0] ?? null;
    }

    /**
     * Configuración de la carta: bandas de los dos ejes y catálogo de atributos.
     * El front no debe hardcodear ni colores ni umbrales ni iconos.
     */
    public function Niveles(): array
    {
        $niveles = JuegoNivel::where('activo', 1)
            ->orderBy('tipo')
            ->orderBy('orden')
            ->get()
            ->groupBy('tipo');

        return [
            'clinico' => $niveles->get('clinico', collect())->values(),
            'completitud' => $niveles->get('completitud', collect())->values(),
            'atributos' => JuegoAtributo::where('activo', 1)->orderBy('orden')->get(),
        ];
    }

    /**
     * Llama al procedimiento y decodifica su única columna resultado_json.
     * El SP siempre devuelve un array (COALESCE(..., JSON_ARRAY())), pero un
     * resultset vacío haría reventar el acceso directo a $results[0].
     */
    private function llamarSP(?string $search, ?string $club): array
    {
        $results = JuegoCartaClub::SP_juego_cartas_club($search, $club);

        if (empty($results) || empty($results[0]->resultado_json)) {
            return [];
        }

        $cartas = json_decode($results[0]->resultado_json, true);

        return is_array($cartas) ? $cartas : [];
    }

    /**
     * En MySQL 5.7 JSON_ARRAYAGG no respeta ORDER BY: el orden de los elementos del
     * array no está garantizado, así que este es el único punto determinista.
     * Las cartas sin evaluar (puntaje null) van siempre al final.
     */
    private function ordenar(array &$cartas): void
    {
        usort($cartas, function ($a, $b) {
            $pa = $a['puntaje'] ?? null;
            $pb = $b['puntaje'] ?? null;

            if ($pa === null || $pb === null) {
                return ($pa === null ? 1 : 0) <=> ($pb === null ? 1 : 0)
                    ?: strcasecmp($a['nombre'] ?? '', $b['nombre'] ?? '');
            }

            return $pb <=> $pa
                ?: ($b['atributos_medidos'] ?? 0) <=> ($a['atributos_medidos'] ?? 0)
                ?: strcasecmp($a['nombre'] ?? '', $b['nombre'] ?? '');
        });
    }
}
