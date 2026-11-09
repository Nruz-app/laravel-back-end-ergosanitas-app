<?php

namespace App\Http\Controllers;

use App\PromptSystem\AsistenteChatComercialPrompt;
use App\Services\ChatComercialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * SPEC 06 — Chat comercial «Ergosanito» del Home (POST api/chat-comercial/as-question).
 *
 * Endpoint público para visitantes anónimos, protegido por el limitador «chat-comercial».
 * Responde con el sobre de chat {sessionId, response, derivar}. A diferencia de los otros chats,
 * el 500 no expone file ni line: el detalle va solo al log del servidor.
 */
class ChatComercialController extends Controller
{
    public function __construct(protected ChatComercialService $chatComercialService) {}

    public function AsQuestionUseCase(Request $request)
    {
        try {

            $request->validate([
                'prompt' => 'required|string|max:500',
                'sessionId' => 'nullable|string|max:64',
            ]);

            //PREGUNTA ENMASCARADA: NI SE GUARDA NI SE ENVÍA EL RUT, CORREO O TELÉFONO DEL VISITANTE
            $prompt = $this->chatComercialService->enmascarar(trim($request->input('prompt')));

            //RESOLVER SESIÓN (SI EL sessionId NO ES UN UUID, SE GENERA UNO NUEVO)
            $session = $this->chatComercialService->resolverSesion($request->input('sessionId'));

            //CONTEXTO DEL CATÁLOGO PÚBLICO (EN CACHÉ) Y CONTACTO DE DERIVACIÓN
            $contexto = $this->chatComercialService->contexto();
            $contacto = config('services.chat_comercial.contacto', []);

            //MENSAJES: SISTEMA ESTÁTICO PRIMERO (PROMPT CACHING), LUEGO HISTORIAL Y PREGUNTA
            $messages = array_merge(
                [['role' => 'system', 'content' => AsistenteChatComercialPrompt::system($contexto, $contacto)]],
                $this->chatComercialService->historial($session),
                [['role' => 'user', 'content' => $prompt]],
            );

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => 400,
            ]);

            $text = $response
                ->choices[0]
                ->message
                ->content ?? '';

            //QUITAR [DERIVAR] Y DECIDIR SI EL FRONT MUESTRA EL BOTÓN DE WHATSAPP
            $separado = $this->chatComercialService->separarDerivacion($text, $contacto);

            //TEXTO PLANO: SI EL MODELO USÓ MARKDOWN IGUAL, SE QUITA (EL FRONT LO MUESTRA TAL CUAL)
            $separado['texto'] = $this->chatComercialService->limpiarMarkdown($separado['texto']);

            //GUARDAR TURNO (openai-php/client v0.10.1 no expone los tokens cacheados: quedan en null)
            $this->chatComercialService->guardarTurno(
                $session,
                $prompt,
                $separado['texto'],
                $separado['derivar'],
                $contexto['hash'],
                $response->usage?->promptTokens,
                null,
                $response->usage?->completionTokens,
            );

            return response()->json([
                'sessionId' => $session->session_id,
                'response' => $separado['texto'],
                'derivar' => $separado['derivar'],
            ]);

        } catch (ValidationException $e) {

            //EL try/catch DE ABAJO ATRAPARÍA ESTA EXCEPCIÓN Y LA DEVOLVERÍA COMO 500
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error('ChatComercial: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'No pude responder en este momento. Inténtalo de nuevo en unos minutos.',
            ], 500);
        }
    }
}
