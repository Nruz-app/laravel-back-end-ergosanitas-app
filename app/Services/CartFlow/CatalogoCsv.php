<?php

namespace App\Services\CartFlow;

/**
 * SPEC 04 — Lector de los CSV del mantenedor (UTF-8 con BOM, separador ";", booleanos SI/NO).
 *
 * No toca la base de datos: convierte cada fila en un array de atributos tipados. Lo usan el
 * comando cart-flow:importar-catalogo y los tests de la calculadora.
 */
class CatalogoCsv
{
    /** Columnas booleanas de productos.csv. */
    private const BOOLEANOS = [
        'publicable', 'visible_tienda', 'es_laboratorio', 'incluye_toma_muestra', 'requiere_orden_medica',
        'requiere_ayuno', 'solo_ruta_manana', 'solo_domicilio', 'solo_operativo', 'aplica_plan',
        'proteger_luz', 'muestra_orina', 'muestra_deposicion',
    ];

    /** Columnas enteras de productos.csv (vacio = null). */
    private const ENTEROS = [
        'costo_domilab', 'precio', 'precio_te_cuida', 'precio_familiar', 'precio_adulto_mayor',
        'edad_min_beneficio', 'horas_ayuno', 'edad_min', 'edad_max',
    ];

    /** Columnas de texto que pasan tal cual (vacio = null). */
    private const TEXTOS = [
        'sku', 'tipo', 'categoria', 'nombre', 'codigo_fonasa', 'agrega_automatico', 'motivo_no_publicable',
        'sexo', 'descripcion_publica', 'preparacion', 'nota_interna', 'observacion',
    ];

    /**
     * Productos indexados por SKU. Solo trae las columnas presentes en el archivo: si el CSV
     * versionado no tiene costo_domilab, la clave no aparece y la importacion no pisa el costo.
     *
     * @return array<string, array{atributos: array, componentes: string[]}>
     */
    public function productos(string $dir): array
    {
        $productos = [];

        foreach ($this->filas($dir.DIRECTORY_SEPARATOR.'productos.csv') as $fila) {
            $atributos = [];

            foreach (self::TEXTOS as $col) {
                if (array_key_exists($col, $fila)) {
                    $atributos[$col] = $this->texto($fila[$col]);
                }
            }

            foreach (self::ENTEROS as $col) {
                if (array_key_exists($col, $fila)) {
                    $atributos[$col] = $this->entero($fila[$col]);
                }
            }

            foreach (self::BOOLEANOS as $col) {
                if (array_key_exists($col, $fila)) {
                    $atributos[$col] = $this->booleano($fila[$col]);
                }
            }

            if (isset($atributos['sexo'])) {
                $atributos['sexo'] = in_array(strtoupper($atributos['sexo']), ['F', 'M'], true)
                    ? strtoupper($atributos['sexo'])
                    : null;
            }

            $componentes = array_values(array_filter(array_map('trim', explode('|', (string) ($fila['componentes'] ?? '')))));

            if (! empty($atributos['sku'])) {
                $productos[$atributos['sku']] = ['atributos' => $atributos, 'componentes' => $componentes];
            }
        }

        return $productos;
    }

    /** @return array<int, array{comuna:string, zona:?int, cargo_servicio:?int, cargo_laboratorio:?int, km_ida_vuelta:?int, a_cotizar:bool, comuna_lejana_chequeo_cv:bool}> */
    public function comunas(string $dir): array
    {
        $comunas = [];

        foreach ($this->filas($dir.DIRECTORY_SEPARATOR.'comunas_zonas.csv') as $fila) {
            $comunas[] = [
                'comuna' => trim($fila['comuna']),
                'zona' => $this->entero($fila['zona'] ?? ''),
                'cargo_servicio' => $this->entero($fila['cargo_domicilio_servicio'] ?? ''),
                'cargo_laboratorio' => $this->entero($fila['cargo_domicilio_laboratorio'] ?? ''),
                'km_ida_vuelta' => $this->entero($fila['km_ida_vuelta'] ?? ''),
                'a_cotizar' => $this->booleano($fila['a_cotizar'] ?? ''),
                'comuna_lejana_chequeo_cv' => $this->booleano($fila['comuna_lejana_chequeo_cv'] ?? ''),
            ];
        }

        return $comunas;
    }

    /** @return array<int, array{sku_pack:string, sku_componente:string, orden:int}> */
    public function componentes(string $dir): array
    {
        $componentes = [];

        foreach ($this->filas($dir.DIRECTORY_SEPARATOR.'componentes_packs.csv') as $fila) {
            $componentes[] = [
                'sku_pack' => trim($fila['sku_pack']),
                'sku_componente' => trim($fila['sku_componente']),
                'orden' => (int) $fila['orden'],
            ];
        }

        return $componentes;
    }

    public function tramos(string $dir): array
    {
        $tramos = [];

        foreach ($this->filas($dir.DIRECTORY_SEPARATOR.'operativos_tramos.csv') as $fila) {
            $tramos[] = [
                'sku_operativo' => trim($fila['sku_operativo']),
                'pacientes_min' => (int) $fila['pacientes_min'],
                'pacientes_max' => $this->entero($fila['pacientes_max'] ?? ''),
                'precio_rm' => $this->entero($fila['precio_rm'] ?? ''),
                'precio_rm_comuna_lejana' => $this->entero($fila['precio_rm_comuna_lejana'] ?? ''),
                'precio_v_region_ohiggins' => $this->entero($fila['precio_v_region_ohiggins'] ?? ''),
                'precio_otras_regiones' => $this->entero($fila['precio_otras_regiones'] ?? ''),
                'nota' => $this->texto($fila['nota'] ?? ''),
            ];
        }

        return $tramos;
    }

    /** Filas del CSV como arrays asociativos por cabecera. */
    private function filas(string $archivo): \Generator
    {
        if (! is_file($archivo)) {
            throw new \RuntimeException("No existe el archivo {$archivo}");
        }

        $handle = fopen($archivo, 'r');

        try {
            $cabecera = fgetcsv($handle, 0, ';');

            if (! $cabecera) {
                return;
            }

            $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecera[0]);
            $cabecera = array_map('trim', $cabecera);

            while (($fila = fgetcsv($handle, 0, ';')) !== false) {
                if ($fila === [null] || $fila === []) {
                    continue;
                }

                yield array_combine($cabecera, array_pad(array_slice($fila, 0, count($cabecera)), count($cabecera), ''));
            }
        } finally {
            fclose($handle);
        }
    }

    private function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function entero(?string $valor): ?int
    {
        $valor = trim((string) $valor);

        return $valor === '' || ! is_numeric($valor) ? null : (int) round((float) $valor);
    }

    private function booleano(?string $valor): bool
    {
        return in_array(strtoupper(trim((string) $valor)), ['SI', 'SÍ', 'S', '1', 'TRUE'], true);
    }
}
