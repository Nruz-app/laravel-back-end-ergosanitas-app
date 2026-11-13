<?php

namespace App\Http\Controllers;

use App\Models\CertificadoDescarga;
use App\Services\CertificadoDescargaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * SPEC 07 — Consulta única y descarga firmada de certificados por RUT (página pública
 * /certificados del front). Sobre del dominio vecino bioimpedancia: {success, message, data}.
 * Nunca devuelve el mensaje de una excepción al cliente ni lo escribe en el log (puede traer el
 * RUT): se registran solo la clase y el código.
 */
class CertificadoDescargaController extends Controller
{
    private const MENSAJE_RUT_INVALIDO = 'El RUT no tiene un formato válido.';

    public function __construct(private CertificadoDescargaService $certificados) {}

    public function Consulta(Request $request): JsonResponse
    {
        $entrada = $request->input('rut');
        $rut = CertificadoDescargaService::normalizarRut(is_string($entrada) ? $entrada : null);

        if (! CertificadoDescargaService::rutValido($rut)) {
            return response()->json([
                'success' => false,
                'message' => self::MENSAJE_RUT_INVALIDO,
                'errors' => ['rut' => [self::MENSAJE_RUT_INVALIDO]],
            ], 422);
        }

        try {
            $data = $this->certificados->consultar($rut);
            $hayDocumentos = count($data['documentos']) > 0;

            $this->certificados->registrar(
                $rut,
                CertificadoDescarga::TIPO_CONSULTA,
                $request->ip(),
                $hayDocumentos ? 'encontrado' : 'sin_resultados'
            );

            return response()->json([
                'success' => true,
                'message' => $hayDocumentos ? 'Documentos encontrados' : 'No hay documentos disponibles para este RUT',
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Certificados: error en la consulta', CertificadoDescargaService::contextoError($e));

            return response()->json([
                'success' => false,
                'message' => 'No fue posible consultar los documentos. Intenta más tarde.',
                'data' => null,
            ], 500);
        }
    }

    public function DescargaEcg(Request $request, int $id): Response
    {
        return $this->descargar(
            $request,
            CertificadoDescarga::TIPO_CERTIFICADO_ECG,
            fn () => $this->certificados->rutDeChequeo($id),
            fn () => $this->certificados->pdfCertificadoEcg($id),
        );
    }

    public function DescargaBio(Request $request, int $id): Response
    {
        return $this->descargar(
            $request,
            CertificadoDescarga::TIPO_CERTIFICADO_BIO,
            fn () => $this->certificados->rutDeBioimpedancia($id),
            fn () => $this->certificados->pdfCertificadoBio($id),
        );
    }

    /**
     * La firma ya la validó el middleware «signed» (403 si falta o venció).
     */
    private function descargar(Request $request, string $tipo, callable $rut, callable $pdf): Response
    {
        $rutPaciente = CertificadoDescargaService::RUT_DESCONOCIDO;

        try {
            $rutPaciente = $rut() ?? CertificadoDescargaService::RUT_DESCONOCIDO;
            $documento = $pdf();

            if ($documento === null) {
                $this->certificados->registrar($rutPaciente, $tipo, $request->ip(), 'no_encontrado');

                return response()->json([
                    'success' => false,
                    'message' => 'Documento no disponible',
                    'data' => null,
                ], 404);
            }

            $this->certificados->registrar($rutPaciente, $tipo, $request->ip(), 'ok');

            return response($documento['contenido'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$documento['archivo'].'"',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (\Throwable $e) {
            Log::error('Certificados: error al generar el PDF', ['tipo' => $tipo] + CertificadoDescargaService::contextoError($e));

            $this->certificados->registrar($rutPaciente, $tipo, $request->ip(), 'error');

            return response()->json([
                'success' => false,
                'message' => 'No fue posible generar el documento. Intenta más tarde.',
                'data' => null,
            ], 500);
        }
    }
}
