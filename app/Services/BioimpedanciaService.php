<?php

namespace App\Services;

use App\Models\Bioimpedancia;
use App\IA\AnalisisRutBioimpedanciaPrompt;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use App\Models\ChequeoCardiovascular;

class BioimpedanciaService
{
    /**
     * Limpia números flotantes (evita 33.10000000000001)
     */
    private function cleanNumber($value)
    {
        if ($value === null) return null;
        if (!is_numeric($value)) return $value;

        $value = round((float)$value, 2);

        return ($value == (int)$value) ? (int)$value : $value;
    }

    private function formatDate($date)
    {
        if (!$date) return null;

        try {
            $date = str_replace('/', '-', $date);
            return date('Y-m-d', strtotime($date));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function listAll()
    {
        return Bioimpedancia::query()
            ->select([
                // Identificación
                'id',
                'rut',
                'club',
                'nombre',
                'sexo',
                'edad',

                // Medidas base
                'estatura_cm',
                'peso_kg',

                // Fecha y hora
                'fecha_prueba',
                'hora_prueba',
                'created_at',
                'updated_at',

                // Composición corporal
                'puntaje_corporal',
                'imc',
                'grasa_corporal_pct',
                'masa_grasa_kg',
                'masa_muscular_kg',
                'masa_musculo_esqueletico_kg',
                'masa_libre_grasa_kg',
                'proteinas_kg',
                'minerales_kg',
                'agua_corporal_total_kg',

                // Metabolismo
                'tasa_metabolica_basal_kcal',
                'edad_corporal',

                // Grasas
                'grasa_visceral',
                'grasa_subcutanea_pct',

                // Indicadores
                'smi',
                'whr',

                // Objetivos / control
                'peso_objetivo_kg',
                'control_peso_kg',
                'control_grasa_kg',
                'control_musculo_kg',

                // Tipo corporal
                'tipo_corporal',

                // Composición muscular segmentaria
                'musculo_brazo_derecho_kg',
                'musculo_brazo_izquierdo_kg',
                'musculo_pierna_derecha_kg',
                'musculo_pierna_izquierda_kg',
                'musculo_tronco_kg',

                // Grasa segmentaria
                'grasa_brazo_derecho_kg',
                'grasa_brazo_izquierdo_kg',
                'grasa_pierna_derecha_kg',
                'grasa_pierna_izquierda_kg',
                'grasa_tronco_kg',

                // Análisis adicional
                'asimetrias_relevantes',
                'calidad_extraccion',

                // Equipo
                'marca',
                'equipo',

                // Data original
                'archivo',
                'raw_json',
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    public function firstByRutChequeoBio(string $rut)
    {
        $bio = Bioimpedancia::where('rut', $rut)
            ->select('rut', 'nombre', 'club')
            ->latest()
            ->first();

        if ($bio) {
            return (object) [
                'rut' => $bio->rut,
                'nombre' => $bio->nombre,
                'user_email' => $bio->club,
            ];
        }

        return ChequeoCardiovascular::where('rut', $rut)
            ->select('rut', 'nombre', 'user_email')
            ->latest()
            ->first();
    }

    public function firstByRut(string $rut)
    {
        $data = Bioimpedancia::query()
            ->select(['id', 'rut', 'nombre', 'archivo'])
            ->where('rut', $rut)
            ->orderByDesc('created_at')
            ->first();

        if ($data && $data->archivo) {
            $data->archivo = url('Bioimpedancia/' . $data->archivo);
        }

        return $data;
    }
    public function CreateBio(string $rut,string $nombre,string $club): Bioimpedancia {

        $bio = new Bioimpedancia();

        $bio->rut = $rut;
        $bio->nombre = $nombre;
        $bio->club = $club;

        $bio->save();

        return $bio;
    }

    public function saveFromAI(
        array $parsed,
        string $rut,
        string $nombre,
        string $club,
        string $fileName
    ): Bioimpedancia {

        $mapped = $this->mapBioimpedancia($parsed, $rut, $nombre);

        return Bioimpedancia::updateOrCreate(
            [
                'rut' => $mapped['rut'],
            ],
            [
                // Identificación
                'nombre' => $mapped['nombre'],
                'club' => $club,

                // Datos del evaluado
                'sexo' => $mapped['sexo'],
                'edad' => $mapped['edad'],
                'estatura_cm' => $mapped['estatura_cm'],
                'peso_kg' => $mapped['peso_kg'],

                // Evaluación
                'fecha_prueba' => $mapped['fecha_prueba'],
                'hora_prueba' => $mapped['hora_prueba'],
                'puntaje_corporal' => $mapped['puntaje_corporal'],

                // Composición corporal
                'imc' => $mapped['imc'],
                'grasa_corporal_pct' => $mapped['grasa_corporal_pct'],
                'masa_grasa_kg' => $mapped['masa_grasa_kg'],
                'masa_muscular_kg' => $mapped['masa_muscular_kg'],
                'masa_musculo_esqueletico_kg' => $mapped['masa_musculo_esqueletico_kg'],
                'masa_libre_grasa_kg' => $mapped['masa_libre_grasa_kg'],
                'proteinas_kg' => $mapped['proteinas_kg'],
                'minerales_kg' => $mapped['minerales_kg'],
                'agua_corporal_total_kg' => $mapped['agua_corporal_total_kg'],

                // Metabolismo y grasa
                'tasa_metabolica_basal_kcal' => $mapped['tasa_metabolica_basal_kcal'],
                'grasa_visceral' => $mapped['grasa_visceral'],
                'grasa_subcutanea_pct' => $mapped['grasa_subcutanea_pct'],
                'edad_corporal' => $mapped['edad_corporal'],
                'smi' => $mapped['smi'],
                'whr' => $mapped['whr'],

                // Objetivos
                'peso_objetivo_kg' => $mapped['peso_objetivo_kg'],
                'control_peso_kg' => $mapped['control_peso_kg'],
                'control_grasa_kg' => $mapped['control_grasa_kg'],
                'control_musculo_kg' => $mapped['control_musculo_kg'],

                // Clasificación
                'tipo_corporal' => $mapped['tipo_corporal'],

                // Músculo segmentario
                'musculo_brazo_derecho_kg' => $mapped['musculo_brazo_derecho_kg'],
                'musculo_brazo_izquierdo_kg' => $mapped['musculo_brazo_izquierdo_kg'],
                'musculo_pierna_derecha_kg' => $mapped['musculo_pierna_derecha_kg'],
                'musculo_pierna_izquierda_kg' => $mapped['musculo_pierna_izquierda_kg'],
                'musculo_tronco_kg' => $mapped['musculo_tronco_kg'],

                // Grasa segmentaria
                'grasa_brazo_derecho_kg' => $mapped['grasa_brazo_derecho_kg'],
                'grasa_brazo_izquierdo_kg' => $mapped['grasa_brazo_izquierdo_kg'],
                'grasa_pierna_derecha_kg' => $mapped['grasa_pierna_derecha_kg'],
                'grasa_pierna_izquierda_kg' => $mapped['grasa_pierna_izquierda_kg'],
                'grasa_tronco_kg' => $mapped['grasa_tronco_kg'],

                // Análisis
                'asimetrias_relevantes' => $mapped['asimetrias_relevantes'],

                // Metadata del equipo
                'marca' => $mapped['marca'],
                'equipo' => $mapped['equipo'],
                'calidad_extraccion' => $mapped['calidad_extraccion'],

                // Archivo original
                'archivo' => $fileName,

                // Respuesta completa de IA
                'raw_json' => json_encode(
                    $parsed,
                    JSON_UNESCAPED_UNICODE
                ),
            ]
        );
    }

    private function mapBioimpedancia(
        array $ai,
        string $rut,
        string $nombre
    ): array {

        return [

            // Identificación
            'rut' => $rut,
            'nombre' => $nombre,

            // Datos del evaluado
            'sexo' => $ai['sexo'] ?? null,

            'edad' => $this->cleanNumber(
                $ai['edad'] ?? null
            ),

            'estatura_cm' => $this->cleanNumber(
                $ai['estatura_cm'] ?? null
            ),

            'peso_kg' => $this->cleanNumber(
                $ai['peso_kg'] ?? null
            ),

            'fecha_prueba' => $this->formatDate(
                $ai['fecha_prueba'] ?? null
            ),

            'hora_prueba' => $ai['hora_prueba'] ?? null,

            // Evaluación
            'puntaje_corporal' => $this->cleanNumber(
                $ai['puntaje_corporal'] ?? null
            ),

            // Composición corporal
            'imc' => $this->cleanNumber(
                $ai['imc'] ?? null
            ),

            'grasa_corporal_pct' => $this->cleanNumber(
                $ai['grasa_corporal_pct'] ?? null
            ),

            'masa_grasa_kg' => $this->cleanNumber(
                $ai['masa_grasa_kg'] ?? null
            ),

            'masa_muscular_kg' => $this->cleanNumber(
                $ai['masa_muscular_kg'] ?? null
            ),

            'masa_musculo_esqueletico_kg' => $this->cleanNumber(
                $ai['masa_musculo_esqueletico_kg'] ?? null
            ),

            'masa_libre_grasa_kg' => $this->cleanNumber(
                $ai['masa_libre_grasa_kg'] ?? null
            ),

            'proteinas_kg' => $this->cleanNumber(
                $ai['proteinas_kg'] ?? null
            ),

            'minerales_kg' => $this->cleanNumber(
                $ai['minerales_kg'] ?? null
            ),

            'agua_corporal_total_kg' => $this->cleanNumber(
                $ai['agua_corporal_total_kg'] ?? null
            ),

            // Metabolismo y grasa
            'tasa_metabolica_basal_kcal' => $this->cleanNumber(
                $ai['tasa_metabolica_basal_kcal'] ?? null
            ),

            'grasa_visceral' => $this->cleanNumber(
                $ai['grasa_visceral'] ?? null
            ),

            'grasa_subcutanea_pct' => $this->cleanNumber(
                $ai['grasa_subcutanea_pct'] ?? null
            ),

            'edad_corporal' => $this->cleanNumber(
                $ai['edad_corporal'] ?? null
            ),

            'smi' => $this->cleanNumber(
                $ai['smi'] ?? null
            ),

            'whr' => $this->cleanNumber(
                $ai['whr'] ?? null
            ),

            // Objetivos
            'peso_objetivo_kg' => $this->cleanNumber(
                $ai['peso_objetivo_kg'] ?? null
            ),

            'control_peso_kg' => $this->cleanNumber(
                $ai['control_peso_kg'] ?? null
            ),

            'control_grasa_kg' => $this->cleanNumber(
                $ai['control_grasa_kg'] ?? null
            ),

            'control_musculo_kg' => $this->cleanNumber(
                $ai['control_musculo_kg'] ?? null
            ),

            // Clasificación
            'tipo_corporal' => $ai['tipo_corporal'] ?? null,

            // Músculo segmentario
            'musculo_brazo_derecho_kg' => $this->cleanNumber(
                $ai['musculo_brazo_derecho_kg'] ?? null
            ),

            'musculo_brazo_izquierdo_kg' => $this->cleanNumber(
                $ai['musculo_brazo_izquierdo_kg'] ?? null
            ),

            'musculo_pierna_derecha_kg' => $this->cleanNumber(
                $ai['musculo_pierna_derecha_kg'] ?? null
            ),

            'musculo_pierna_izquierda_kg' => $this->cleanNumber(
                $ai['musculo_pierna_izquierda_kg'] ?? null
            ),

            'musculo_tronco_kg' => $this->cleanNumber(
                $ai['musculo_tronco_kg'] ?? null
            ),

            // Grasa segmentaria
            'grasa_brazo_derecho_kg' => $this->cleanNumber(
                $ai['grasa_brazo_derecho_kg'] ?? null
            ),

            'grasa_brazo_izquierdo_kg' => $this->cleanNumber(
                $ai['grasa_brazo_izquierdo_kg'] ?? null
            ),

            'grasa_pierna_derecha_kg' => $this->cleanNumber(
                $ai['grasa_pierna_derecha_kg'] ?? null
            ),

            'grasa_pierna_izquierda_kg' => $this->cleanNumber(
                $ai['grasa_pierna_izquierda_kg'] ?? null
            ),

            'grasa_tronco_kg' => $this->cleanNumber(
                $ai['grasa_tronco_kg'] ?? null
            ),

            // Análisis
            'asimetrias_relevantes' =>
                $ai['asimetrias_relevantes'] ?? null,

            // Metadata
            'marca' => $ai['marca'] ?? null,

            'equipo' => $ai['equipo'] ?? null,

            'calidad_extraccion' =>
                $ai['calidad_extraccion'] ?? null,
        ];
    }

    public function SP_bioempdacia_rut($rut_paciente)
    {
        $results = Bioimpedancia::SP_bioempdacia_rut($rut_paciente);

        // El SP no devuelve una fila vacía: devuelve cero filas.
        if (empty($results)) {
            return null;
        }

        return $results[0]->resultado_json;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de presentación
    |--------------------------------------------------------------------------
    */

    /**
     * Escapa texto para HTML. Un "<" o "&" sin escapar rompe el render de mPDF.
     */
    private function esc($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        if (is_array($valor)) {
            $valor = implode(', ', $valor);
        }

        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Banda de título de sección.
     */
    private function seccion(string $titulo): string
    {
        return "<div class='seccion'>" . $this->esc($titulo) . "</div>";
    }

    /**
     * Párrafo con subtítulo dentro de una caja. Devuelve '' si no hay texto.
     */
    private function parrafo(string $titulo, $texto): string
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return '';
        }

        return "<div class='sub'>" . $this->esc($titulo) . "</div>"
            . "<div class='txt'>" . $this->esc($texto) . "</div>";
    }

    /*
    |--------------------------------------------------------------------------
    | Preparación de datos para la IA
    |--------------------------------------------------------------------------
    */

    /**
     * Adapta el JSON del SP a lo que espera AnalisisRutBioimpedanciaPrompt:
     *
     * 1. El SP entrega los segmentarios anidados (segmentario_musculo.brazo_derecho_kg)
     *    y el prompt los espera planos (musculo_brazo_derecho_kg). Sin esto la IA
     *    los considera ausentes y omite todo el análisis segmentario.
     *
     * 2. Descarta raw_json, que duplica exactamente los mismos campos ya
     *    desagregados y solo infla el prompt.
     *
     * 3. Descarta identificadores del paciente: no aportan al análisis de
     *    composición corporal y se reponen en el PDF desde la base de datos.
     */
    private function prepararPayloadIA(array $ficha): array
    {
        $data = $ficha;

        foreach (($data['segmentario_musculo'] ?? []) as $segmento => $valor) {
            $data['musculo_' . $segmento] = $valor;
        }

        foreach (($data['segmentario_grasa'] ?? []) as $segmento => $valor) {
            $data['grasa_' . $segmento] = $valor;
        }

        $descartar = [
            'segmentario_musculo',
            'segmentario_grasa',
            'raw_json',
            'id',
            'rut',
            'nombre',
            'club',
            'archivo',
            'created_at',
            'updated_at',
        ];

        foreach ($descartar as $campo) {
            unset($data[$campo]);
        }

        // Los campos null se descartan: el prompt indica ignorarlos.
        return array_filter($data, function ($valor) {
            return $valor !== null && $valor !== '';
        });
    }

    /**
     * Llama a OpenAI y devuelve el análisis decodificado, o [] si falla.
     */
    private function analizarConIA(array $payload): array
    {
        $response = OpenAI::chat()->create([
            'model'           => 'gpt-4.1-mini',
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                [
                    'role'    => 'system',
                    'content' => AnalisisRutBioimpedanciaPrompt::system(),
                ],
                [
                    'role'    => 'user',
                    'content' => "Analiza la siguiente bioimpedancia:\n\n"
                        . json_encode($payload, JSON_UNESCAPED_UNICODE),
                ],
            ],
            'temperature' => 0.1,
            'max_tokens'  => 4000,
        ]);

        $content = $response->choices[0]->message->content ?? '';

        $analisis = json_decode($content, true);

        if (!is_array($analisis)) {

            Log::warning('Bioimpedancia: la IA no devolvió JSON válido', [
                'respuesta' => mb_substr((string) $content, 0, 500),
            ]);

            return [];
        }

        return $analisis;
    }

    /*
    |--------------------------------------------------------------------------
    | Informe PDF
    |--------------------------------------------------------------------------
    |
    | Estructura del informe (AnalisisRutBioimpedanciaPrompt):
    |
    |   1. Datos del/de la evaluado/a
    |   2. Resumen de resultados principales
    |   3. Hallazgos relevantes
    |   4. Orientación, derivación sugerida y recomendación general
    |      + advertencia obligatoria + firma
    |
    */

    public function BioPDFRut(string $rut_paciente)
    {
        $logoPath  = public_path('logo.png');
        $firmaErgo = public_path('firmarErgo.jpg');

        /*
        |--------------------------------------------------------------------------
        | 1. Datos desde el SP
        |--------------------------------------------------------------------------
        */

        $resultadoJson = $this->SP_bioempdacia_rut($rut_paciente);

        if (!$resultadoJson) {
            throw new \Exception("No existe bioimpedancia registrada para el RUT {$rut_paciente}");
        }

        $ficha = json_decode($resultadoJson, true);

        if (!is_array($ficha)) {
            throw new \Exception("El procedimiento SP_bioimpedacia_rut devolvió un JSON inválido");
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Análisis con IA
        |--------------------------------------------------------------------------
        */

        $analisis = $this->analizarConIA($this->prepararPayloadIA($ficha));

        $encabezado  = $analisis['encabezado'] ?? [];
        $resumen     = $analisis['resumen_resultados'] ?? [];
        $hallazgos   = $analisis['hallazgos_relevantes'] ?? [];
        $orientacion = $analisis['orientacion_general'] ?? [];

        // El prompt puede declarar que los datos no alcanzan para un informe.
        $disponible = !array_key_exists('informe_disponible', $analisis)
            || !empty($analisis['informe_disponible']);

        /*
        |--------------------------------------------------------------------------
        | 3. CSS
        |--------------------------------------------------------------------------
        */

        $stylesheet = "<style>
            body { font-family: Arial, sans-serif; font-size: 10.5px; color: #333; }

            .cab-inst { font-size: 10px; color: #555; text-align: right; }

            .titulo {
                text-align: center;
                font-size: 16px;
                font-weight: bold;
                margin: 12px 0 3px 0;
                color: #00695c;
            }

            .subtitulo { text-align: center; font-size: 9.5px; color: #666; margin-bottom: 12px; }

            .seccion {
                background: #00695c;
                color: #fff;
                font-weight: bold;
                font-size: 11px;
                padding: 6px 8px;
                margin-top: 13px;
                margin-bottom: 6px;
            }

            .caja { border: 1px solid #d9d9d9; padding: 10px; }

            .sub { font-weight: bold; color: #00695c; margin: 7px 0 2px 0; font-size: 10.5px; }
            .sub:first-child { margin-top: 0; }

            .txt { margin-bottom: 5px; }

            table { width: 100%; border-collapse: collapse; }

            .tabla td, .tabla th { border: 1px solid #ddd; padding: 5px 6px; vertical-align: top; }
            .tabla th { background: #f0f4f3; font-size: 10px; text-align: left; }

            .tabla .par { width: 30%; font-weight: bold; background: #fafafa; }
            .tabla .res { width: 20%; }

            .datos td { border: 1px solid #ddd; padding: 5px 6px; }
            .datos .k { background: #fafafa; font-weight: bold; width: 15%; }

            .aviso {
                border: 1px solid #ef6c00;
                background: #fff8f0;
                padding: 10px;
                color: #a04b00;
            }

            .advertencia {
                border: 1px solid #e0e0e0;
                background: #fafafa;
                padding: 8px;
                font-size: 9px;
                color: #555;
                margin-top: 12px;
            }

            .footer { margin-top: 16px; text-align: center; font-size: 8.5px; color: #666; }
        </style>";

        /*
        |--------------------------------------------------------------------------
        | 4. Cabecera
        |--------------------------------------------------------------------------
        */

        $html = "<html><head><meta charset='utf-8'>" . $stylesheet . "</head><body>";

        $html .= "<table><tr>"
            . "<td style='border:none;'><img src='" . $logoPath . "' style='width:170px;'></td>"
            . "<td style='border:none;' class='cab-inst'>"
            . $this->esc($encabezado['institucion'] ?? 'ERGO SANITAS SPA') . "<br>"
            . "Servicios de Salud a Domicilio"
            . "</td>"
            . "</tr></table>";

        $html .= "<div class='titulo'>"
            . $this->esc($encabezado['titulo'] ?? 'INFORME DE BIOIMPEDANCIA CORPORAL BodyPro Go')
            . "</div>";

        $subtitulo = 'Equipo: ' . ($ficha['equipo'] ?? ($encabezado['equipo'] ?? 'BodyPro Go'));

        if (!empty($ficha['fecha_prueba'])) {
            $subtitulo .= '  |  Fecha de medición: ' . $ficha['fecha_prueba'];
        }

        $html .= "<div class='subtitulo'>" . $this->esc($subtitulo) . "</div>";

        /*
        |--------------------------------------------------------------------------
        | 5. Sección 1 - Datos del/de la evaluado/a
        |--------------------------------------------------------------------------
        |
        | Se toman desde la base de datos, no desde la IA.
        |
        */

        $html .= $this->seccion('1. DATOS DEL/DE LA EVALUADO/A');

        $html .= "<table class='datos'>"
            . "<tr>"
            . "<td class='k'>Nombre</td><td>" . $this->esc($ficha['nombre'] ?? '') . "</td>"
            . "<td class='k'>RUT</td><td>" . $this->esc($ficha['rut'] ?? $rut_paciente) . "</td>"
            . "</tr>"
            . "<tr>"
            . "<td class='k'>Sexo</td><td>" . $this->esc($ficha['sexo'] ?? '') . "</td>"
            . "<td class='k'>Edad</td><td>" . $this->esc($ficha['edad'] ?? '') . "</td>"
            . "</tr>"
            . "<tr>"
            . "<td class='k'>Estatura</td><td>" . $this->esc($ficha['estatura_cm'] ?? '') . " cm</td>"
            . "<td class='k'>Peso corporal</td><td>" . $this->esc($ficha['peso_kg'] ?? '') . " kg</td>"
            . "</tr>"
            . "</table>";

        /*
        |--------------------------------------------------------------------------
        | Datos insuficientes: se corta el informe aquí.
        |--------------------------------------------------------------------------
        */

        if (!$disponible) {

            $motivo = trim((string) ($analisis['motivo_no_disponible'] ?? ''));

            if ($motivo === '') {
                $motivo = 'Los datos registrados no permiten emitir un informe interpretativo. '
                    . 'Se sugiere repetir la medición.';
            }

            $html .= $this->seccion('INFORME NO DISPONIBLE');
            $html .= "<div class='aviso'>" . $this->esc($motivo) . "</div>";

            $html .= $this->pieInforme($analisis, $firmaErgo);

            return $html . "</body></html>";
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Sección 2 - Resumen de resultados principales
        |--------------------------------------------------------------------------
        */

        if (!empty($resumen) && is_array($resumen)) {

            $filas = '';

            foreach ($resumen as $item) {

                if (!is_array($item)) {
                    continue;
                }

                $parametro = trim((string) ($item['parametro'] ?? ''));

                // "resultado" es el campo del prompt; "valor" queda por compatibilidad.
                $resultado = trim((string) ($item['resultado'] ?? ($item['valor'] ?? '')));

                if ($parametro === '' && $resultado === '') {
                    continue;
                }

                $orientacion_breve = trim((string) ($item['orientacion'] ?? ($item['interpretacion'] ?? '')));

                $filas .= "<tr>"
                    . "<td class='par'>" . $this->esc($parametro) . "</td>"
                    . "<td class='res'>" . ($resultado !== '' ? $this->esc($resultado) : '—') . "</td>"
                    . "<td>" . $this->esc($orientacion_breve) . "</td>"
                    . "</tr>";
            }

            if ($filas !== '') {

                $html .= $this->seccion('2. RESUMEN DE RESULTADOS PRINCIPALES');

                $html .= "<table class='tabla'>"
                    . "<tr><th>Parámetro</th><th>Resultado</th><th>Orientación breve</th></tr>"
                    . $filas
                    . "</table>";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Sección 3 - Hallazgos relevantes
        |--------------------------------------------------------------------------
        */

        if (!empty($hallazgos) && is_array($hallazgos)) {

            $filas = '';

            foreach ($hallazgos as $item) {

                // Acepta tanto {titulo, detalle} como un texto simple.
                if (is_string($item)) {
                    $titulo  = '';
                    $detalle = trim($item);
                } elseif (is_array($item)) {
                    $titulo  = trim((string) ($item['titulo'] ?? ''));
                    $detalle = trim((string) ($item['detalle'] ?? ''));
                } else {
                    continue;
                }

                if ($titulo === '' && $detalle === '') {
                    continue;
                }

                $filas .= "<tr><td>";

                if ($titulo !== '') {
                    $filas .= "<strong>" . $this->esc($titulo) . "</strong><br>";
                }

                $filas .= $this->esc($detalle) . "</td></tr>";
            }

            if ($filas !== '') {
                $html .= $this->seccion('3. HALLAZGOS RELEVANTES');
                $html .= "<table class='tabla'>" . $filas . "</table>";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Sección 4 - Orientación, derivación y recomendación general
        |--------------------------------------------------------------------------
        */

        if (!empty($orientacion) && is_array($orientacion)) {

            $areas = [
                'area_clinica'     => 'Área clínica',
                'area_nutricional' => 'Área nutricional',
                'area_deportiva'   => 'Área deportiva',
            ];

            $filasArea = '';

            foreach ($areas as $clave => $etiqueta) {

                $area = $orientacion[$clave] ?? null;

                if (!is_array($area)) {
                    continue;
                }

                // "sugerida" es el campo del prompt; "requerida" queda por compatibilidad.
                $sugerida = !empty($area['sugerida']) || !empty($area['requerida']);

                if (!$sugerida) {
                    continue;
                }

                $filasArea .= "<tr>"
                    . "<td class='par'>" . $this->esc($etiqueta) . "</td>"
                    . "<td>" . $this->esc($area['motivo'] ?? '') . "</td>"
                    . "</tr>";
            }

            $cuerpo = $this->parrafo('Síntesis', $orientacion['sintesis'] ?? '')
                . $this->parrafo('Hábitos saludables', $orientacion['habitos_saludables'] ?? '')
                . $this->parrafo('Actividad física', $orientacion['actividad_fisica'] ?? '')
                . $this->parrafo('Control profesional', $orientacion['control_profesional'] ?? '')
                . $this->parrafo('Reevaluación', $orientacion['reevaluacion'] ?? '');

            if ($filasArea !== '' || $cuerpo !== '') {

                $html .= $this->seccion('4. ORIENTACIÓN, DERIVACIÓN SUGERIDA Y RECOMENDACIÓN GENERAL');

                if ($filasArea !== '') {
                    $html .= "<table class='tabla'>"
                        . "<tr><th>Derivación sugerida</th><th>Motivo</th></tr>"
                        . $filasArea
                        . "</table>";
                }

                if ($cuerpo !== '') {
                    $html .= "<div class='caja' style='margin-top:6px;'>" . $cuerpo . "</div>";
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Advertencia, firma y pie
        |--------------------------------------------------------------------------
        */

        $html .= $this->pieInforme($analisis, $firmaErgo);

        $html .= "</body></html>";

        return $html;
    }

    /**
     * Advertencia obligatoria, firma y pie institucional.
     */
    private function pieInforme(array $analisis, string $firmaErgo): string
    {
        $advertencia = trim((string) ($analisis['advertencia'] ?? ''));

        if ($advertencia === '') {
            $advertencia = 'Este informe es orientativo y no reemplaza una evaluación médica, '
                . 'nutricional ni diagnóstico clínico. Los resultados deben ser interpretados '
                . 'por profesionales competentes según el contexto individual del paciente.';
        }

        $html = "<div class='advertencia'>" . $this->esc($advertencia) . "</div>";

        $html .= "<div style='text-align:center;margin-top:20px;'>"
            . "<img src='" . $firmaErgo . "' style='width:160px;'><br>"
            . "_________________________<br>"
            . $this->esc($analisis['firma'] ?? 'Equipo Ergo SaniTas SpA')
            . "</div>";

        $html .= "<div class='footer'>"
            . "San Bernardo - Región Metropolitana - Chile<br>"
            . "+56 9 6114 9975 - contacto@ergosanitas.com - www.ergosanitas.com"
            . "</div>";

        return $html;
    }

}
