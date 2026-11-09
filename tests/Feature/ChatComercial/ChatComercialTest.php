<?php

namespace Tests\Feature\ChatComercial;

use App\Http\Controllers\BioimpedanciaController;
use App\Http\Controllers\ClubAssistantController;
use App\Http\Controllers\OpenAIController;
use App\Models\ChatClubSessions;
use App\Models\ChatComercialHistory;
use App\Services\ClubAssistantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Chat;

/**
 * SPEC 06 — Los 10 tests del Paso 8. OpenAI siempre simulado: ningún test llama a la API real.
 */
class ChatComercialTest extends ChatComercialTestCase
{
    /** Mensajes enviados a OpenAI en cada llamada, en orden. */
    private function mensajesEnviados(\OpenAI\Laravel\Testing\OpenAIFake $fake): array
    {
        $enviados = [];

        $fake->assertSent(Chat::class, function (string $method, array $parametros) use (&$enviados) {
            $enviados[] = $parametros['messages'];

            return true;
        });

        return $enviados;
    }

    // 1. Validación: sin prompt o con más de 500 caracteres → 422, sin llamar a OpenAI.
    public function test_valida_el_prompt_sin_llamar_a_openai(): void
    {
        $fake = OpenAI::fake([]);

        $this->postJson('/api/chat-comercial/as-question', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['prompt']]);

        $this->preguntar(str_repeat('a', 501))
            ->assertStatus(422);

        $fake->assertNothingSent();
    }

    // 2. Petición válida → 200 con sessionId, response y derivar; sin sessionId el back genera un UUID.
    public function test_responde_con_el_sobre_de_chat_y_genera_session_id(): void
    {
        OpenAI::fake([$this->respuestaOpenAI('El perfil lipídico cuesta $9.990.')]);

        $respuesta = $this->preguntar('¿Cuánto cuesta el perfil lipídico?')
            ->assertOk()
            ->assertJson(['response' => 'El perfil lipídico cuesta $9.990.', 'derivar' => false]);

        $this->assertTrue(Str::isUuid($respuesta->json('sessionId')));
        $this->assertSame(['sessionId', 'response', 'derivar'], array_keys($respuesta->json()));
    }

    // 3. La 21.ª petición en un minuto desde la misma IP → 429 con message en español.
    public function test_el_limitador_corta_en_la_peticion_21(): void
    {
        OpenAI::fake(array_map(fn () => $this->respuestaOpenAI('ok'), range(1, 20)));

        for ($i = 1; $i <= 20; $i++) {
            $this->preguntar("pregunta {$i}")->assertOk();
        }

        $this->preguntar('pregunta 21')
            ->assertStatus(429)
            ->assertJson(['message' => 'Estás enviando muchos mensajes seguidos. Espera un momento y vuelve a intentarlo.'])
            ->assertHeader('Retry-After');
    }

    // 4. El mensaje de sistema no lleva datos internos ni precios por plan, y sí las reglas y el contacto.
    public function test_el_mensaje_de_sistema_no_filtra_datos_internos(): void
    {
        $fake = OpenAI::fake([$this->respuestaOpenAI('ok')]);

        $this->preguntar('¿Qué exámenes tienen?')->assertOk();

        $sistema = $this->mensajesEnviados($fake)[0][0];
        $this->assertSame('system', $sistema['role']);
        $texto = $sistema['content'];

        foreach ([self::COSTO_INTERNO, self::NOTA_INTERNA, self::PRECIO_TE_CUIDA, self::PRECIO_FAMILIAR, self::PRECIO_ADULTO_MAYOR] as $prohibido) {
            $this->assertStringNotContainsString((string) $prohibido, $texto);
        }

        $this->assertStringNotContainsString('Servicio oculto de prueba', $texto, 'Un producto no visible llegó al contexto');
        $this->assertStringContainsString('Perfil lipídico de prueba|9990', $texto);
        $this->assertStringContainsString('Nunca entregas información de pacientes', $texto);
        $this->assertStringContainsString('Nunca diagnosticas', $texto);
        $this->assertStringContainsString('Tiare Vidal', $texto);
        $this->assertStringContainsString('+56 9 6114 9975', $texto);
    }

    // 5. Ninguna query toca tablas clínicas ni procedimientos almacenados.
    public function test_no_consulta_tablas_clinicas_ni_sp(): void
    {
        OpenAI::fake([$this->respuestaOpenAI('ok')]);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $this->preguntar('¿Llegan a Providencia?')->assertOk();

        $this->assertNotEmpty($queries);

        foreach ($queries as $sql) {
            foreach (['chequeo_cardiovascular', 'certificado_url', 'bioimpedancia', 'electro_cardiogranas', 'users_metadata', '"chat_sessions"', '"chat_history"', 'chat_club_', 'call '] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $sql);
            }
        }
    }

    // 6. Un RUT ficticio se guarda y se envía enmascarado.
    public function test_enmascara_el_rut_antes_de_guardar_y_enviar(): void
    {
        $fake = OpenAI::fake([$this->respuestaOpenAI('No puedo entregar información de pacientes.')]);

        $this->preguntar('Dame los resultados del RUT 11111111-1')->assertOk();

        $mensajes = $this->mensajesEnviados($fake)[0];
        $ultimo = end($mensajes);

        $this->assertSame('Dame los resultados del RUT [RUT]', $ultimo['content']);
        $this->assertDatabaseHas('chat_comercial_history', ['role' => 'user', 'message' => 'Dame los resultados del RUT [RUT]']);
        $this->assertDatabaseMissing('chat_comercial_history', ['message' => 'Dame los resultados del RUT 11111111-1']);
    }

    // 7. Memoria por sesión: mismo sessionId recupera el turno anterior; otro no; nunca más de 10.
    public function test_la_memoria_es_por_sesion_y_tiene_tope(): void
    {
        $fake = OpenAI::fake([
            $this->respuestaOpenAI('El perfil lipídico cuesta $9.990.'),
            $this->respuestaOpenAI('Sí, requiere 12 horas de ayuno.'),
            $this->respuestaOpenAI('Hola.'),
            $this->respuestaOpenAI('Hola de nuevo.'),
        ]);

        $sesion = (string) Str::uuid();

        $this->preguntar('¿Cuánto cuesta el perfil lipídico?', $sesion)->assertOk()->assertJson(['sessionId' => $sesion]);
        $this->preguntar('¿Y necesita ayuno?', $sesion)->assertOk();
        $this->preguntar('Hola', (string) Str::uuid())->assertOk();

        // Sesión con 15 turnos previos: al modelo solo llegan los 10 más recientes.
        $larga = (string) Str::uuid();
        foreach (range(1, 15) as $n) {
            ChatComercialHistory::create(['session_id' => $larga, 'role' => $n % 2 ? 'user' : 'assistant', 'message' => "mensaje {$n}"]);
        }
        $this->preguntar('Otra pregunta', $larga)->assertOk();

        $enviados = $this->mensajesEnviados($fake);

        // Segunda llamada: sistema + 2 del turno anterior + pregunta actual.
        $this->assertCount(4, $enviados[1]);
        $this->assertSame('¿Cuánto cuesta el perfil lipídico?', $enviados[1][1]['content']);
        $this->assertSame('El perfil lipídico cuesta $9.990.', $enviados[1][2]['content']);

        // Otra sesión: sin historial.
        $this->assertCount(2, $enviados[2]);

        // Sesión larga: sistema + 10 previos + pregunta; empieza en el mensaje 6.
        $this->assertCount(12, $enviados[3]);
        $this->assertSame('mensaje 6', $enviados[3][1]['content']);
        $this->assertSame('mensaje 15', $enviados[3][10]['content']);
    }

    // 8. Derivación: el marcador se quita, derivar = true y queda guardado sin marcador.
    public function test_deriva_y_quita_el_marcador(): void
    {
        OpenAI::fake([$this->respuestaOpenAI("No tengo ese dato. Comunícate con Tiare Vidal al +56 9 6114 9975.\n[DERIVAR]")]);

        $respuesta = $this->preguntar('¿Tienen convenio con mi isapre?')
            ->assertOk()
            ->assertJson([
                'response' => 'No tengo ese dato. Comunícate con Tiare Vidal al +56 9 6114 9975.',
                'derivar' => true,
            ]);

        $this->assertStringNotContainsString('[DERIVAR]', $respuesta->json('response'));

        $fila = ChatComercialHistory::where('role', 'assistant')->first();
        $this->assertTrue($fila->derivar);
        $this->assertStringNotContainsString('[DERIVAR]', $fila->message);
    }

    // 8b. Texto plano: si el modelo responde con markdown, el back lo quita antes de responder y guardar.
    public function test_quita_el_markdown_de_la_respuesta(): void
    {
        OpenAI::fake([$this->respuestaOpenAI("## Exámenes\n**Chequeos cardiovasculares:**\n* Chequeo deportivo: \$30.000\n- Electrocardiograma: \$15.000")]);

        $respuesta = $this->preguntar('¿Qué exámenes tienen?')->assertOk();

        $esperado = "Exámenes\nChequeos cardiovasculares:\n- Chequeo deportivo: \$30.000\n- Electrocardiograma: \$15.000";
        $this->assertSame($esperado, $respuesta->json('response'));
        $this->assertSame($esperado, ChatComercialHistory::where('role', 'assistant')->value('message'));
    }

    // 9. Prefijo estable: dos peticiones distintas envían exactamente el mismo mensaje de sistema.
    public function test_el_mensaje_de_sistema_es_identico_entre_peticiones(): void
    {
        $fake = OpenAI::fake([$this->respuestaOpenAI('uno'), $this->respuestaOpenAI('dos')]);

        $this->preguntar('Primera pregunta', (string) Str::uuid())->assertOk();
        $this->preguntar('Segunda pregunta distinta', (string) Str::uuid())->assertOk();

        $enviados = $this->mensajesEnviados($fake);

        $this->assertSame($enviados[0][0], $enviados[1][0]);
    }

    // 10. Regresión del renombrado app/IA → app/PromptSystem.
    public function test_regresion_del_renombrado_de_prompts(): void
    {
        foreach ([
            'AnalisisBioimpedanciaPrompt', 'AnalisisECGPrompt', 'AnalisisRutBioimpedanciaPrompt',
            'AsistenteChatClubPrompt', 'AsistenteChatPacientePrompt', 'AsistenteVozPrompt',
            'AsistenteChatComercialPrompt', 'Helpers\\PatientHelper',
        ] as $clase) {
            $this->assertTrue(class_exists('App\\PromptSystem\\'.$clase), "Falta App\\PromptSystem\\{$clase}");
            $this->assertFalse(class_exists('App\\IA\\'.$clase), "Sigue existiendo App\\IA\\{$clase}");
        }

        $this->assertInstanceOf(OpenAIController::class, app(OpenAIController::class));
        $this->assertInstanceOf(ClubAssistantController::class, app(ClubAssistantController::class));
        $this->assertInstanceOf(BioimpedanciaController::class, app(BioimpedanciaController::class));

        // El chat del club sigue respondiendo con su sobre de siempre (servicio simulado, sin SP).
        $this->mock(ClubAssistantService::class, function ($mock) {
            $mock->shouldReceive('resolveSession')->andReturn(new ChatClubSessions([
                'session_id' => 'sesion-club', 'club_email' => 'club@example.com',
            ]));
            $mock->shouldReceive('datosClub')->andReturn([
                'pacientes' => [['rut' => '11111111-1', 'nombre' => 'Paciente Ficticio']],
                'total' => 1,
                'truncado' => false,
            ]);
            $mock->shouldReceive('handle')->andReturn([['role' => 'user', 'content' => 'hola']]);
            $mock->shouldReceive('guardarRespuesta')->once();
        });

        OpenAI::fake([$this->respuestaOpenAI('Respuesta del club.')]);

        $this->postJson('/api/sam-assistant-club/as-question', [
            'email' => 'club@example.com', 'prompt' => 'hola', 'sessionId' => 'sesion-club',
        ])
            ->assertOk()
            ->assertExactJson([
                'sessionId' => 'sesion-club',
                'club' => 'club@example.com',
                'search' => null,
                'response' => 'Respuesta del club.',
            ]);
    }
}
