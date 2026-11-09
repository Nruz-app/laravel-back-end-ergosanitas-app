<?php

namespace App\Services;

use App\Models\CartFlow\OperativoTramo;
use App\Models\CartFlow\Producto;
use App\Models\ChatComercialHistory;
use App\Models\ChatComercialSessions;
use App\Services\CartFlow\CalculadoraCarroService;
use App\Services\CartFlow\CatalogoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SPEC 06 — Chat comercial «Ergosanito» del Home.
 *
 * Arma el contexto SOLO desde el catálogo público (cf_productos visibles, comunas, tramos de
 * operativo y reglas), enmascara los datos personales que escriba el visitante, resuelve la
 * sesión y su historial, y separa el marcador [DERIVAR] de la respuesta del modelo.
 *
 * Nunca consulta tablas clínicas ni procedimientos almacenados. Las llamadas a OpenAI van en
 * ChatComercialController, como en el chat del club.
 */
class ChatComercialService
{
    /** Mensajes previos de la sesión que se reenvían al modelo. */
    public const HISTORIAL_MAX = 10;

    /** Tope del texto del catálogo; si se supera, se recorta la preparación. */
    public const MAX_CARACTERES_CONTEXTO = 40000;

    /** El catálogo cambia poco: 10 minutos de caché (tabla cache). */
    public const CACHE_SEGUNDOS = 600;

    public const CLAVE_CACHE = 'chat-comercial:contexto';

    /** Marcador con el que el modelo cierra una respuesta que deriva a contacto. */
    public const MARCADOR_DERIVAR = '[DERIVAR]';

    private const LARGO_PREPARACION = 160;

    private const LARGO_PREPARACION_TRUNCADO = 80;

    private const LARGO_DESCRIPCION = 120;

    public function __construct(private CatalogoService $catalogo) {}

    /**
     * Contexto del catálogo en formato compacto, en caché.
     *
     * @return array{texto: string, hash: string, truncado: bool}
     */
    public function contexto(): array
    {
        return Cache::remember(self::CLAVE_CACHE, self::CACHE_SEGUNDOS, function () {
            $productos = $this->productos();

            $texto = $this->serializar($productos, self::LARGO_PREPARACION);
            $truncado = false;

            if (mb_strlen($texto) > self::MAX_CARACTERES_CONTEXTO) {
                $texto = $this->serializar($productos, self::LARGO_PREPARACION_TRUNCADO);
                $truncado = true;
            }

            return [
                'texto' => $texto,
                'hash' => hash('sha256', $texto),
                'truncado' => $truncado,
            ];
        });
    }

    /**
     * Reemplaza correo, teléfono y RUT por marcadores antes de guardar y de enviar a OpenAI.
     * El orden importa: el teléfono va antes que el RUT porque un celular sin espacios
     * (961149975) también calza con el patrón de RUT sin guion.
     */
    public function enmascarar(string $texto): string
    {
        $texto = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', '[EMAIL]', $texto);
        $texto = preg_replace('/(?<![\d+])(?:\+?56\s?)?9\s?\d{4}\s?\d{4}(?!\d)/u', '[TELEFONO]', $texto);
        $texto = preg_replace('/(?<![\w.])\d{1,2}\.?\d{3}\.?\d{3}-?[\dkK](?![\w])/u', '[RUT]', $texto);

        return $texto;
    }

    /**
     * Recupera la sesión por su UUID o crea una nueva. Si el front no manda un UUID válido,
     * el back genera uno y el controlador lo devuelve para que el front lo guarde.
     */
    public function resolverSesion(?string $sessionId): ChatComercialSessions
    {
        if (! is_string($sessionId) || ! Str::isUuid($sessionId)) {
            $sessionId = (string) Str::uuid();
        }

        return ChatComercialSessions::firstOrCreate(['session_id' => $sessionId]);
    }

    /**
     * Los HISTORIAL_MAX mensajes más recientes de la sesión, en orden cronológico.
     * El id desempata cuando dos filas comparten created_at (precisión de un segundo).
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function historial(ChatComercialSessions $session): array
    {
        return ChatComercialHistory::where('session_id', $session->session_id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(self::HISTORIAL_MAX)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatComercialHistory $fila) => [
                'role' => $fila->role,
                'content' => $fila->message,
            ])
            ->all();
    }

    /**
     * Quita el marcador [DERIVAR] de la respuesta y dice si hay que derivar.
     * Respaldo: si el modelo escribió el teléfono del contacto pero olvidó el marcador,
     * también se deriva.
     *
     * @param  array{nombre?: string, telefono?: string}  $contacto
     * @return array{texto: string, derivar: bool}
     */
    public function separarDerivacion(string $texto, array $contacto): array
    {
        $patron = '/\[\s*derivar\s*\]/iu';
        $derivar = preg_match($patron, $texto) === 1;

        $texto = trim(preg_replace($patron, '', $texto));

        if (! $derivar) {
            $digitosTelefono = substr(preg_replace('/\D/', '', (string) ($contacto['telefono'] ?? '')), -8);
            $digitosTexto = preg_replace('/\D/', '', $texto);

            $derivar = $digitosTelefono !== '' && str_contains($digitosTexto, $digitosTelefono);
        }

        return ['texto' => $texto, 'derivar' => $derivar];
    }

    /**
     * Respaldo del estilo «texto plano» del prompt: el front muestra la respuesta tal cual
     * (BurbujaBot, pre-wrap), así que se quitan negritas, cursivas con guion bajo, títulos y
     * backticks si el modelo los usó igual. Las viñetas «* » pasan a «- ».
     */
    public function limpiarMarkdown(string $texto): string
    {
        $texto = str_replace(['**', '__', '`'], '', $texto);
        $texto = preg_replace('/^[ \t]*#{1,6}[ \t]*/mu', '', $texto);
        $texto = preg_replace('/^([ \t]*)\*[ \t]+/mu', '$1- ', $texto);

        return trim($texto);
    }

    /**
     * Guarda la pregunta (ya enmascarada) y la respuesta (sin marcador) y actualiza la sesión.
     */
    public function guardarTurno(
        ChatComercialSessions $session,
        string $pregunta,
        string $respuesta,
        bool $derivar,
        string $contextoHash,
        ?int $tokensPrompt = null,
        ?int $tokensCacheados = null,
        ?int $tokensRespuesta = null,
    ): void {
        DB::transaction(function () use ($session, $pregunta, $respuesta, $derivar, $contextoHash, $tokensPrompt, $tokensCacheados, $tokensRespuesta) {
            ChatComercialHistory::create([
                'session_id' => $session->session_id,
                'role' => 'user',
                'message' => $pregunta,
                'contexto_hash' => $contextoHash,
            ]);

            ChatComercialHistory::create([
                'session_id' => $session->session_id,
                'role' => 'assistant',
                'message' => $respuesta,
                'derivar' => $derivar,
                'contexto_hash' => $contextoHash,
                'tokens_prompt' => $tokensPrompt,
                'tokens_cacheados' => $tokensCacheados,
                'tokens_respuesta' => $tokensRespuesta,
            ]);

            $session->update([
                'mensajes' => $session->mensajes + 1,
                'ultimo_mensaje_at' => now(),
            ]);
        });
    }

    /**
     * Productos visibles con lista blanca de columnas. Nunca costo_domilab, nota_interna,
     * publicable, motivo_no_publicable, observacion, agrega_automatico ni precios por plan.
     */
    private function productos()
    {
        return Producto::visibles()
            ->where('tipo', '!=', 'toma_muestra')
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get([
                'sku', 'nombre', 'categoria', 'tipo', 'precio', 'solo_operativo',
                'requiere_orden_medica', 'requiere_ayuno', 'horas_ayuno',
                'solo_ruta_manana', 'solo_domicilio', 'preparacion', 'descripcion_publica',
            ]);
    }

    /**
     * Texto compacto: una línea por producto, comuna y tramo, separada por "|".
     * Nada variable (fecha, sesión) entra aquí, para que el prefijo sea idéntico entre
     * peticiones y OpenAI lo reutilice (prompt caching).
     */
    private function serializar($productos, int $largoPreparacion): string
    {
        $lineas = ['# EXAMENES Y SERVICIOS', 'sku|nombre|precio|cotizar|orden|ayuno_h|manana|domicilio|prep'];

        foreach ($productos->groupBy('categoria') as $categoria => $grupo) {
            $lineas[] = '## '.$this->limpiar((string) $categoria);

            foreach ($grupo as $p) {
                $prep = $this->limpiar((string) $p->preparacion);

                $prep = $prep !== ''
                    ? mb_substr($prep, 0, $largoPreparacion)
                    : mb_substr($this->limpiar((string) $p->descripcion_publica), 0, self::LARGO_DESCRIPCION);

                $lineas[] = implode('|', [
                    $p->sku,
                    $this->limpiar((string) $p->nombre),
                    $p->precio ?? '',
                    $p->solo_cotizacion ? 1 : 0,
                    $p->requiere_orden_medica ? 1 : 0,
                    $p->requiere_ayuno ? ($p->horas_ayuno ?? '') : '',
                    $p->solo_ruta_manana ? 1 : 0,
                    $p->solo_domicilio ? 1 : 0,
                    $prep,
                ]);
            }
        }

        $lineas[] = '# COMUNAS';
        $lineas[] = 'comuna|zona|a_cotizar|cargo_servicio|cargo_laboratorio';

        foreach ($this->catalogo->comunas() as $c) {
            $lineas[] = implode('|', [
                $this->limpiar((string) $c['comuna']),
                $this->limpiar((string) ($c['zona'] ?? '')),
                $c['a_cotizar'] ? 1 : 0,
                $c['cargo_servicio'] ?? '',
                $c['cargo_laboratorio'] ?? '',
            ]);
        }

        $lineas[] = '# OPERATIVOS POR REGION';
        $lineas[] = 'sku_operativo|pacientes_min|pacientes_max|precio_rm|precio_rm_comuna_lejana|precio_v_region_ohiggins|precio_otras_regiones|nota';

        $tramos = OperativoTramo::orderBy('sku_operativo')->orderBy('pacientes_min')->get([
            'sku_operativo', 'pacientes_min', 'pacientes_max', 'precio_rm',
            'precio_rm_comuna_lejana', 'precio_v_region_ohiggins', 'precio_otras_regiones', 'nota',
        ]);

        foreach ($tramos as $t) {
            $lineas[] = implode('|', [
                $t->sku_operativo,
                $t->pacientes_min,
                $t->pacientes_max ?? '',
                $t->precio_rm ?? '',
                $t->precio_rm_comuna_lejana ?? '',
                $t->precio_v_region_ohiggins ?? '',
                $t->precio_otras_regiones ?? '',
                $this->limpiar((string) $t->nota),
            ]);
        }

        $reglas = array_replace(CalculadoraCarroService::REGLAS_POR_DEFECTO, $this->catalogo->reglas());

        $lineas[] = '# REGLAS';
        $lineas[] = 'ticket_minimo|'.($reglas['ticket_minimo'] ?? '');
        $lineas[] = 'horas_ayuno_defecto|'.($reglas['horas_ayuno_defecto'] ?? '');

        return implode("\n", $lineas);
    }

    /** Quita saltos de línea y "|" de los textos libres para no romper el formato de líneas. */
    private function limpiar(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace('|', '/', $texto)));
    }
}
