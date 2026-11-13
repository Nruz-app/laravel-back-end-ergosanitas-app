<?php

namespace Tests\Feature\Certificados;

use App\Services\BioimpedanciaService;
use App\Services\CertificadoDescargaService;
use App\Services\ChequeoCardiovascularPDFService;
use Illuminate\Support\Facades\URL;
use Mockery;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;

/**
 * SPEC 07 — Criterios de aceptación de la consulta única y las descargas firmadas.
 * Datos ficticios; OpenAI siempre simulado; mPDF real sobre HTML mínimo.
 */
class CertificadosTest extends CertificadosTestCase
{
    // Consulta completa: los 4 tipos en orden, firmados solo los certificados generados.
    public function test_consulta_devuelve_los_cuatro_documentos_en_orden(): void
    {
        $idChequeo = $this->crearChequeo();
        $this->crearCertificadoUrl($idChequeo);
        $this->crearBioimpedancia();

        $respuesta = $this->consultar('11.111.111-1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rut', self::RUT)
            ->assertJsonPath('data.nombre', 'Paciente Ficticio');

        $documentos = $respuesta->json('data.documentos');

        $this->assertSame(['certificado_ecg', 'ecg', 'certificado_bio', 'bio'], array_column($documentos, 'tipo'));
        $this->assertSame('2026-05-04', $documentos[0]['fecha']);
        $this->assertStringContainsString('signature=', $documentos[0]['url']);
        $this->assertNotNull($documentos[0]['expira']);
        $this->assertStringContainsString('signature=', $documentos[2]['url']);
        $this->assertNotNull($documentos[2]['expira']);
        $this->assertNull($documentos[1]['expira']);
        $this->assertNull($documentos[3]['expira']);
        $this->assertStringEndsWith('Bioimpedancia/archivo-ficticio.pdf', $documentos[3]['url']);

        $this->assertDatabaseHas('certificado_descargas', [
            'rut' => self::RUT, 'tipo' => 'consulta', 'resultado' => 'encontrado', 'ip' => '127.0.0.1',
        ]);
    }

    // La bioimpedancia vigente sigue el orden del SP (fecha_prueba DESC, id DESC), no created_at.
    public function test_bioimpedancia_vigente_sigue_el_orden_del_sp(): void
    {
        $vigente = $this->crearBioimpedancia(self::RUT, 'vigente.pdf', '2026-07-10', '2026-07-10 09:00:00');
        $this->crearBioimpedancia(self::RUT, 'antigua.pdf', '2026-06-01', '2026-07-20 09:00:00');

        $documentos = $this->consultar(self::RUT)->assertOk()->json('data.documentos');

        $this->assertSame(['certificado_bio', 'bio'], array_column($documentos, 'tipo'));
        $this->assertStringContainsString('/certificados/descarga/bio/'.$vigente.'?', $documentos[0]['url']);
        $this->assertSame('2026-07-10', $documentos[0]['fecha']);
        $this->assertStringEndsWith('Bioimpedancia/vigente.pdf', $documentos[1]['url']);
    }

    // Una URL firmada de una bioimpedancia que ya no es la vigente → 404, sin llamar a OpenAI.
    public function test_descarga_bio_no_vigente_devuelve_404(): void
    {
        $antigua = $this->crearBioimpedancia(self::RUT, null, '2026-06-01');
        $vigente = $this->crearBioimpedancia(self::RUT, null, '2026-07-10');

        $servicio = Mockery::mock(BioimpedanciaService::class)->makePartial();
        $servicio->shouldReceive('SP_bioempdacia_rut')->with(self::RUT)->andReturn(json_encode(['id' => $vigente, 'rut' => self::RUT]));
        $this->instance(BioimpedanciaService::class, $servicio);

        $fake = OpenAI::fake([]);

        $this->get(URL::temporarySignedRoute('CertificadosDescargaBio', now()->addMinutes(10), ['id' => $antigua]))
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $fake->assertNothingSent();
        $this->assertDatabaseHas('certificado_descargas', [
            'rut' => self::RUT, 'tipo' => 'certificado_bio', 'resultado' => 'no_encontrado', 'ip' => '127.0.0.1',
        ]);
    }

    // Bioimpedancia inexistente → 404; fallo del SP → 500 genérico. Ambos quedan registrados.
    public function test_descarga_bio_inexistente_y_error(): void
    {
        // El mock va antes de la primera petición: el servicio de descargas es singleton.
        $servicio = Mockery::mock(BioimpedanciaService::class)->makePartial();
        $servicio->shouldReceive('SP_bioempdacia_rut')->andThrow(new \RuntimeException('DETALLE-INTERNO-SECRETO'));
        $this->instance(BioimpedanciaService::class, $servicio);

        $this->get(URL::temporarySignedRoute('CertificadosDescargaBio', now()->addMinutes(10), ['id' => 999]))
            ->assertStatus(404);

        $this->assertDatabaseHas('certificado_descargas', [
            'rut' => CertificadoDescargaService::RUT_DESCONOCIDO, 'tipo' => 'certificado_bio', 'resultado' => 'no_encontrado',
        ]);

        $idBio = $this->crearBioimpedancia();

        $respuesta = $this->get(URL::temporarySignedRoute('CertificadosDescargaBio', now()->addMinutes(10), ['id' => $idBio]))
            ->assertStatus(500)
            ->assertJsonPath('success', false);

        $this->assertStringNotContainsString('DETALLE-INTERNO-SECRETO', $respuesta->getContent());
        $this->assertDatabaseHas('certificado_descargas', [
            'rut' => self::RUT, 'tipo' => 'certificado_bio', 'resultado' => 'error',
        ]);
    }

    // Un chequeo que no está en REVISION MEDICA no produce certificado ni ECG.
    public function test_chequeo_ingresado_no_aparece(): void
    {
        $idChequeo = $this->crearChequeo('ingresado');
        $this->crearCertificadoUrl($idChequeo);

        $this->consultar(self::RUT)
            ->assertOk()
            ->assertJsonPath('data.documentos', []);
    }

    // RUT sin registros → 200 con lista vacía, y queda registrado como sin_resultados.
    public function test_rut_sin_registros_devuelve_lista_vacia(): void
    {
        $this->consultar(self::RUT)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nombre', null)
            ->assertJsonPath('data.documentos', []);

        $this->assertDatabaseHas('certificado_descargas', [
            'rut' => self::RUT, 'tipo' => 'consulta', 'resultado' => 'sin_resultados',
        ]);
    }

    // Sin RUT o con formato inválido → 422 con el sobre success/message/errors.
    public function test_rut_ausente_o_invalido_devuelve_422(): void
    {
        $this->consultar(null)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['rut']]);

        $this->consultar('no-es-un-rut')->assertStatus(422);

        $this->assertDatabaseCount('certificado_descargas', 0);
    }

    // La consulta n.º 11 en el mismo minuto desde la misma IP → 429.
    public function test_limitador_devuelve_429(): void
    {
        foreach (range(1, 10) as $intento) {
            $this->consultar(self::RUT)->assertOk();
        }

        $this->consultar(self::RUT)
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertHeader('Retry-After');
    }

    // Descarga ECG: sin firma o con firma vencida → 403; firmada → PDF; ingresado → 404.
    public function test_descarga_ecg_exige_firma_vigente(): void
    {
        $this->instance(ChequeoCardiovascularPDFService::class, Mockery::mock(ChequeoCardiovascularPDFService::class, function ($mock) {
            $mock->shouldReceive('chequeoPDf')->andReturn('<html><body><p>Certificado ficticio</p></body></html>');
        }));

        $idChequeo = $this->crearChequeo();
        $idIngresado = $this->crearChequeo('ingresado', '22222222-2');

        $this->get('/api/certificados/descarga/ecg/'.$idChequeo)->assertStatus(403);

        $firmada = URL::temporarySignedRoute('CertificadosDescargaEcg', now()->addMinutes(10), ['id' => $idChequeo]);

        $this->get($firmada)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="Certificado-Paciente-Ficticio.pdf"');

        $this->travel(11)->minutes();
        $this->get($firmada)->assertStatus(403);
        $this->travelBack();

        $this->get(URL::temporarySignedRoute('CertificadosDescargaEcg', now()->addMinutes(10), ['id' => $idIngresado]))
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('certificado_descargas', ['tipo' => 'certificado_ecg', 'resultado' => 'ok', 'rut' => self::RUT]);
        $this->assertDatabaseHas('certificado_descargas', ['tipo' => 'certificado_ecg', 'resultado' => 'no_encontrado']);
    }

    // Dos descargas del certificado de bioimpedancia → OpenAI recibe una sola petición.
    public function test_segunda_descarga_bio_no_llama_a_openai(): void
    {
        $idBio = $this->crearBioimpedancia();

        $ficha = json_encode([
            'id' => $idBio,
            'rut' => self::RUT,
            'nombre' => 'Paciente Ficticio',
            'sexo' => 'M',
            'edad' => 20,
            'peso_kg' => 70,
            'estatura_cm' => 175,
        ]);

        $servicio = Mockery::mock(BioimpedanciaService::class)->makePartial();
        $servicio->shouldReceive('SP_bioempdacia_rut')->with(self::RUT)->andReturn($ficha);
        $this->instance(BioimpedanciaService::class, $servicio);

        $fake = OpenAI::fake([
            CreateResponse::fake(['choices' => [['message' => ['content' => json_encode([
                'informe_disponible' => true,
                'encabezado' => ['titulo' => 'INFORME FICTICIO'],
            ])]]]]),
        ]);

        $url = URL::temporarySignedRoute('CertificadosDescargaBio', now()->addMinutes(10), ['id' => $idBio]);

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $llamadas = 0;
        $fake->assertSent(Chat::class, function () use (&$llamadas) {
            $llamadas++;

            return true;
        });

        $this->assertSame(1, $llamadas);
        $this->assertDatabaseCount('bioimpedancia_analisis_ia', 1);
        $this->assertSame(2, \DB::table('certificado_descargas')->where('tipo', 'certificado_bio')->where('resultado', 'ok')->count());
    }

    // chequeo-cardiovascular/pdfRut ya no usa un chequeo que no pasó la revisión médica.
    public function test_pdf_rut_ignora_chequeos_sin_revision_medica(): void
    {
        $this->crearChequeo('ingresado');

        $this->get('/api/chequeo-cardiovascular/pdfRut/'.self::RUT)
            ->assertStatus(404)
            ->assertJsonPath('error', 'Paciente no encontrado');
    }

    // Un error interno responde 500 genérico, sin el texto de la excepción.
    public function test_error_interno_no_expone_la_excepcion(): void
    {
        $this->instance(ChequeoCardiovascularPDFService::class, Mockery::mock(ChequeoCardiovascularPDFService::class, function ($mock) {
            $mock->shouldReceive('chequeoPDf')->andThrow(new \RuntimeException('DETALLE-INTERNO-SECRETO'));
        }));

        $idChequeo = $this->crearChequeo();

        $descarga = $this->get(URL::temporarySignedRoute('CertificadosDescargaEcg', now()->addMinutes(10), ['id' => $idChequeo]))
            ->assertStatus(500)
            ->assertJsonPath('success', false);

        $this->assertStringNotContainsString('DETALLE-INTERNO-SECRETO', $descarga->getContent());
        $this->assertDatabaseHas('certificado_descargas', ['tipo' => 'certificado_ecg', 'resultado' => 'error']);

        $servicio = Mockery::mock(CertificadoDescargaService::class)->makePartial();
        $servicio->shouldReceive('consultar')->andThrow(new \RuntimeException('DETALLE-INTERNO-SECRETO'));
        $this->instance(CertificadoDescargaService::class, $servicio);

        $consulta = $this->consultar(self::RUT)->assertStatus(500)->assertJsonPath('success', false);

        $this->assertStringNotContainsString('DETALLE-INTERNO-SECRETO', $consulta->getContent());
    }
}
