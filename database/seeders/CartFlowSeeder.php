<?php

namespace Database\Seeders;

use App\Models\CartFlow\BloqueAgenda;
use App\Models\CartFlow\Regla;
use App\Services\CartFlow\CalculadoraCarroService;
use App\Services\CartFlow\ImportadorCatalogoService;
use Illuminate\Database\Seeder;

/**
 * SPEC 04 — Siembra del carro: reglas de negocio, bloques de agenda por defecto y catalogo.
 *
 * Es idempotente: no pisa reglas que Gerencia ya cambio (firstOrCreate) y solo crea bloques si
 * la tabla esta vacia. El catalogo pasa por el importador, que respeta visible_tienda.
 *
 *   php artisan db:seed --class=CartFlowSeeder
 */
class CartFlowSeeder extends Seeder
{
    public function run(ImportadorCatalogoService $importador): void
    {
        $descripciones = [
            'toma_muestra' => 'SKU y precio de la toma de muestra que se agrega sola (una por paciente con examenes sueltos)',
            'ticket_minimo' => 'Monto minimo de una visita a domicilio (CLP)',
            'redondeo_multiplo' => 'El total se redondea hacia arriba a este multiplo (CLP)',
            'horas_ayuno_defecto' => 'Horas de ayuno cuando un producto pide ayuno y no informa horas',
            'laboratorio_solo_manana' => 'Si hay laboratorio, toda la visita va en bloque de manana',
            'agenda_anticipacion_dias' => 'Dias minimos entre hoy y la primera fecha que ofrece la agenda',
            'agenda_dias_max' => 'Cuantos dias hacia adelante muestra la agenda',
            'carrito_dias_vigencia' => 'Dias que dura un carro abierto antes de expirar',
        ];

        foreach (CalculadoraCarroService::REGLAS_POR_DEFECTO as $clave => $valor) {
            Regla::firstOrCreate(['clave' => $clave], ['valor' => $valor, 'descripcion' => $descripciones[$clave] ?? null]);
        }

        if (BloqueAgenda::count() === 0) {
            // Lunes a sabado. Laboratorio y ayuno solo usan la franja de manana.
            $bloques = [
                ['07:30:00', '10:00:00', 'manana', 6],
                ['10:00:00', '13:00:00', 'manana', 6],
                ['15:00:00', '18:00:00', 'tarde', 6],
            ];

            foreach (range(1, 6) as $dia) {
                foreach ($bloques as [$inicio, $fin, $franja, $cupo]) {
                    BloqueAgenda::create([
                        'zona_id' => null,
                        'dia_semana' => $dia,
                        'hora_inicio' => $inicio,
                        'hora_fin' => $fin,
                        'franja' => $franja,
                        'cupo' => $cupo,
                        'activo' => true,
                    ]);
                }
            }
        }

        $importador->importar(database_path('data/cart-flow'));
    }
}
