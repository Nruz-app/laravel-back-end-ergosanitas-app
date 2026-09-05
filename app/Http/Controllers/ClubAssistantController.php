<?php

namespace App\Http\Controllers;

use App\IA\AsistenteChatClubPrompt;
use App\Models\ChatClubSessions;
use App\Services\ClubAssistantService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenAI\Laravel\Facades\OpenAI;

class ClubAssistantController extends Controller
{
    protected $clubAssistantService;

    public function __construct(ClubAssistantService $clubAssistantService)
    {
        $this->clubAssistantService = $clubAssistantService;
    }

    public function AsQuestionClubUseCase(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email',
                'prompt' => 'required|string|max:500',
                'search' => 'nullable|string|max:255',
                'sessionId' => 'required|string',
            ]);

            $email = $request->input('email');

            $prompt = trim($request->input('prompt'));

            $sessionId = trim($request->input('sessionId'));

            // null = el campo no vino (se conserva el search de la sesión)
            // ''   = vino vacío: reset explícito, el chat pasa a hablar de todo el club
            $search = $request->has('search')
                ? trim((string) $request->input('search'))
                : null;

            //RESOLVER SESIÓN
            $session = $this->clubAssistantService->resolveSession($sessionId, $email, $search);

            //EL SEARCH EFECTIVO ES EL DE LA SESIÓN TRAS RESOLVER
            $searchEfectivo = $session->search_actual;

            //DATOS DEL CLUB
            $datos = $this->clubAssistantService->datosClub($searchEfectivo, $email);

            if (empty($datos['pacientes'])) {

                return response()->json([
                    'sessionId' => $sessionId,
                    'club' => $email,
                    'search' => $searchEfectivo,
                    'response' => 'No encontré chequeos en revisión médica para ese club con ese filtro.',
                    'status' => 'sin_datos',
                ], 200);
            }

            //HISTORIAL CHAT
            $chat = $this->clubAssistantService->handle($session, $prompt);

            //MENSAJES GPT
            $messages = array_merge(
                AsistenteChatClubPrompt::system(
                    $email,
                    $searchEfectivo,
                    $datos['pacientes'],
                    $datos['total'],
                    $datos['truncado']
                ),
                $chat
            );

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.2,
            ]);

            $text = $response
                ->choices[0]
                ->message
                ->content ?? '';

            //GUARDAR RESPUESTA
            $this->clubAssistantService->guardarRespuesta($session, $text, $datos['pacientes']);

            return response()->json([
                'sessionId' => $sessionId,
                'club' => $email,
                'search' => $searchEfectivo,
                'response' => $text,
            ]);

        } catch (ValidationException $e) {

            //EL try/catch DE ABAJO ATRAPARÍA ESTA EXCEPCIÓN Y LA DEVOLVERÍA COMO 500
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function ResetSearch(Request $request)
    {
        try {

            $request->validate([
                'sessionId' => 'required|string',
            ]);

            $session = ChatClubSessions::where('session_id', $request->sessionId)->first();

            if (! $session) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Sesión no encontrada',
                ], 404);
            }

            //RESET SOLO DEL FILTRO DE PACIENTE
            $session->search_actual = null;
            $session->save();

            return response()->json([
                'ok' => true,
                'message' => 'Filtro de paciente reiniciado',
            ]);

        } catch (ValidationException $e) {

            //EL try/catch DE ABAJO ATRAPARÍA ESTA EXCEPCIÓN Y LA DEVOLVERÍA COMO 500
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}
