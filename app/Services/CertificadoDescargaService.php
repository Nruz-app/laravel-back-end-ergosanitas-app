<?php

namespace App\Services;

use App\Models\Bioimpedancia;
use App\Models\CertificadoDescarga;
use App\Models\ChequeoCardiovascular;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

/**
 * SPEC 07 — Consulta única y descarga firmada de los certificados de un RUT.
 *
 * Arma, en una sola respuesta, los documentos del último chequeo en REVISION MEDICA y de la
 * última bioimpedancia. Los PDF generados (certificado ECG y certificado de bioimpedancia) se
 * entregan con URL firmada y temporal; los archivos subidos (ECG y bioimpedancia) conservan su
 * URL pública actual. Solo lee: no factura ni cambia el estado de ningún chequeo.
 */
class CertificadoDescargaService
{
    /** Vigencia de las URL firmadas de descarga. */
    public const MINUTOS_URL = 10;

    /** Estado del chequeo que habilita el certificado. */
    public const ESTADO_CERTIFICABLE = 'REVISION MEDICA';

    /** RUT que se registra cuando la descarga no permite identificar al paciente. */
    public const RUT_DESCONOCIDO = 'desconocido';

    public function __construct(
        private ChequeoCardiovascularPDFService $pdfChequeo,
        private BioimpedanciaService $bioimpedancia,
    ) {}

    /**
     * Quita puntos y espacios y deja la «k» en minúscula: 11.111.111-K → 11111111-k.
     */
    public static function normalizarRut(?string $rut): string
    {
        return strtolower(str_replace(['.', ' '], '', trim((string) $rut)));
    }

    public static function rutValido(string $rut): bool
    {
        return (bool) preg_match('/^\d{7,8}-[\dk]$/', $rut);
    }

    /**
     * @return array{rut: string, nombre: ?string, documentos: array<int, array{tipo: string, fecha: ?string, url: string, expira: ?string}>}
     */
    public function consultar(string $rut): array
    {
        $documentos = [];
        $nombre = null;

        $chequeo = ChequeoCardiovascular::query()
            ->where('rut', $rut)
            ->where('status', self::ESTADO_CERTIFICABLE)
            ->orderByDesc('id')
            ->first(['id', 'nombre', 'created_at']);

        if ($chequeo) {
            $nombre = $chequeo->nombre;

            $certificado = DB::table('certificado_url')
                ->where('rut_paciente', $rut)
                ->where('id_chequeo', $chequeo->id)
                ->orderByDesc('id')
                ->first(['url_pdf', 'created_at']);

            $firmada = $this->urlFirmada('CertificadosDescargaEcg', (int) $chequeo->id);

            $documentos[] = [
                'tipo' => 'certificado_ecg',
                'fecha' => $this->fecha($certificado->created_at ?? $chequeo->created_at),
                'url' => $firmada['url'],
                'expira' => $firmada['expira'],
            ];

            if ($certificado && $certificado->url_pdf) {
                $documentos[] = [
                    'tipo' => 'ecg',
                    'fecha' => $this->fecha($certificado->created_at),
                    'url' => $certificado->url_pdf,
                    'expira' => null,
                ];
            }
        }

        // Mismo orden que SP_bioimpedacia_rut (fecha_prueba DESC, id DESC): la URL firmada debe
        // apuntar a la misma medición que después renderiza el PDF.
        $bio = Bioimpedancia::query()
            ->where('rut', $rut)
            ->orderByDesc('fecha_prueba')
            ->orderByDesc('id')
            ->first(['id', 'nombre', 'archivo', 'fecha_prueba']);

        if ($bio) {
            $nombre = $nombre ?? $bio->nombre;

            $firmada = $this->urlFirmada('CertificadosDescargaBio', (int) $bio->id);

            $documentos[] = [
                'tipo' => 'certificado_bio',
                'fecha' => $this->fecha($bio->fecha_prueba),
                'url' => $firmada['url'],
                'expira' => $firmada['expira'],
            ];

            if ($bio->archivo) {
                $documentos[] = [
                    'tipo' => 'bio',
                    'fecha' => $this->fecha($bio->fecha_prueba),
                    'url' => url('Bioimpedancia/'.$bio->archivo),
                    'expira' => null,
                ];
            }
        }

        return [
            'rut' => $rut,
            'nombre' => $nombre ? $this->nombrePropio($nombre) : null,
            'documentos' => $documentos,
        ];
    }

    /**
     * RUT del chequeo, en cualquier estado (solo para el registro de la descarga).
     */
    public function rutDeChequeo(int $id): ?string
    {
        return ChequeoCardiovascular::where('id', $id)->value('rut');
    }

    public function rutDeBioimpedancia(int $id): ?string
    {
        return Bioimpedancia::where('id', $id)->value('rut');
    }

    /**
     * PDF del certificado del chequeo, o null si el chequeo no existe o no está en REVISION MEDICA.
     *
     * @return array{contenido: string, archivo: string}|null
     */
    public function pdfCertificadoEcg(int $id): ?array
    {
        $chequeo = ChequeoCardiovascular::query()
            ->where('id', $id)
            ->where('status', self::ESTADO_CERTIFICABLE)
            ->first(['id', 'nombre']);

        if (! $chequeo) {
            return null;
        }

        $html = $this->pdfChequeo->chequeoPDf($id);

        return [
            'contenido' => $this->renderizarPdf($html),
            'archivo' => $this->nombreArchivo($chequeo->nombre),
        ];
    }

    /**
     * PDF del certificado de bioimpedancia, o null si la bioimpedancia no existe o ya no es la
     * vigente del RUT (el SP renderiza siempre la última por fecha_prueba: si llegó una medición
     * nueva después de la consulta, la URL firmada vieja no debe entregar otra). El análisis de
     * IA sale de bioimpedancia_analisis_ia si ya se generó (BioimpedanciaService::BioPDFRut).
     *
     * @return array{contenido: string, archivo: string}|null
     */
    public function pdfCertificadoBio(int $id): ?array
    {
        $bio = Bioimpedancia::where('id', $id)->first(['id', 'rut', 'nombre']);

        if (! $bio) {
            return null;
        }

        $ficha = $this->bioimpedancia->fichaVigente($bio->rut);

        if ($ficha === null || (int) ($ficha['id'] ?? 0) !== $id) {
            return null;
        }

        $html = $this->bioimpedancia->BioPDFRut($bio->rut, $ficha);

        return [
            'contenido' => $this->renderizarPdf($html),
            'archivo' => $this->nombreArchivo($bio->nombre),
        ];
    }

    /**
     * Registra una consulta o descarga. Si el registro falla, se informa en el log y la
     * descarga sigue: el paciente no debe quedarse sin su certificado por un fallo de auditoría.
     */
    public function registrar(string $rut, string $tipo, ?string $ip, string $resultado): void
    {
        try {
            CertificadoDescarga::create([
                'rut' => $rut,
                'tipo' => $tipo,
                'ip' => $ip ?? '0.0.0.0',
                'resultado' => $resultado,
            ]);
        } catch (\Throwable $e) {
            Log::error('Certificados: no se pudo registrar la descarga', [
                'tipo' => $tipo,
                'resultado' => $resultado,
            ] + self::contextoError($e));
        }
    }

    /**
     * Contexto de log de una excepción sin su mensaje: el mensaje de un error SQL puede traer
     * el RUT u otros datos del paciente.
     *
     * @return array{excepcion: string, codigo: int|string}
     */
    public static function contextoError(\Throwable $e): array
    {
        return [
            'excepcion' => get_class($e),
            'codigo' => $e->getCode(),
        ];
    }

    /**
     * Mismo patrón que la orden médica del carro (AdminPedidoService).
     *
     * @return array{url: string, expira: string}
     */
    public function urlFirmada(string $ruta, int $id): array
    {
        $expira = now()->addMinutes(self::MINUTOS_URL);

        return [
            'url' => URL::temporarySignedRoute($ruta, $expira, ['id' => $id]),
            'expira' => $expira->toIso8601String(),
        ];
    }

    protected function renderizarPdf(string $html): string
    {
        $mpdf = new Mpdf;
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function fecha($valor): ?string
    {
        if (! $valor) {
            return null;
        }

        try {
            return Carbon::parse($valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nombrePropio(string $nombre): string
    {
        return mb_convert_case(mb_strtolower(trim($nombre)), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * «Certificado-Paciente-Ficticio.pdf»: ASCII y sin caracteres que rompan Content-Disposition.
     */
    private function nombreArchivo(?string $nombre): string
    {
        $base = preg_replace('/[^A-Za-z0-9-]/', '', str_replace(' ', '-', Str::ascii($this->nombrePropio((string) $nombre))));

        return 'Certificado'.($base !== '' ? '-'.$base : '').'.pdf';
    }
}
