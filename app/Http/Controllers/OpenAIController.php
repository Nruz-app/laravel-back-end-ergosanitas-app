<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use App\IA\AsistenteVozPrompt;
use App\IA\AnalisisECGPrompt;
use App\Services\EstadisticasService;
use App\Models\ChatHistory;
use App\Models\ChatSessions;
use App\Services\OpenAIService;
use App\IA\AsistenteChatPacientePrompt;

class OpenAIController extends Controller {


    //
    protected $estadisticasService;
    protected $openAIService;

    public function __construct(
        EstadisticasService $estadisticasService,
        OpenAIService $openAIService) {
        $this->estadisticasService = $estadisticasService;
        $this->openAIService = $openAIService;
    }

    public function resetPatient(Request $request)
    {
        $request->validate([
            'sessionId' => 'required|string'
        ]);

        $session = ChatSessions::where('session_id', $request->sessionId)->first();

        if (!$session) {
            return response()->json([
                'ok' => false,
                'message' => 'Session not found'
            ], 404);
        }

        //reset SOLO del paciente
        $session->patient_identifier = null;
        $session->save();

        return response()->json([
            'ok' => true,
            'message' => 'Patient context reset'
        ]);
    }

    public function AsQuestionUseCase(Request $request, OpenAIService $service)
    {
        try {

            $request->validate([
                'prompt' => 'required|string|max:500',
                'sessionId' => 'required|string'
            ]);

            $prompt = trim($request->input('prompt'));

            $sessionId = trim($request->input('sessionId'));

            //RESOLVER SESIÓN Y PACIENTE
            $session = $service->resolveSession($sessionId,$prompt);

            if (!$session->patient_identifier) {

                return response()->json([
                    'sessionId' => $sessionId,
                    'patient' => null,
                    'status' => 'needs_identifier',
                    'response' => "Bienvenido a Ergosanitas Para Ayudarte Necesito Que
                Me Indiques El RUT o El Nombre Del Paciente."
                ], 200);
            }

            $search = $session->patient_identifier;

            if (!$search) {

                $text = "Bienvenido a Ergosanitas Para Ayudarte Necesito Que
                Me Indiques El RUT o El Nombre Del Paciente.";

                return response()->json([
                    'sessionId' => $sessionId,
                    'patient' => null,
                    'response' => $text,
                    'status' => 'needs_identifier'
                ], 200);
            }


            //HISTORIAL CHAT

            $chat = $service->handle($session,$prompt);

            //DATOS PACIENTE

            $result = $this->estadisticasService->ChequeoPrompt($search);

            if (!$result || !isset($result[0])) {

                return response()->json([
                    'sessionId' => $sessionId,
                    'patient' => null,
                    'response' => "No encontré información del paciente. Por favor indícame el RUT o confirma el nombre.",
                    'status' => 'needs_identifier'
                ], 200);
            }

            //CORRECCIÓN
            $data = (array) $result[0];

            //MENSAJES GPT

            $messages = array_merge(
                AsistenteChatPacientePrompt::system(
                    $search,
                    $data
                ),
                $chat['messages']
            );

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.2
            ]);

            $text = $response
                ->choices[0]
                ->message
                ->content ?? '';

            //GUARDAR RESPUESTA
            ChatHistory::create([
                'patient_identifier' => $search,
                'role' => 'assistant',
                'message' => $text,
                'context_json' => json_encode(
                    $data,
                    JSON_UNESCAPED_UNICODE
                )
            ]);

            return response()->json([
                'sessionId' => $sessionId,
                'patient' => $search,
                'response' => $text
            ]);

        }
        catch (\Throwable $e) {

            $status = str_contains($e->getMessage(),'Debes ingresar')
                ? 400 : 500;

            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], $status);
        }
    }

    public function AsistenteVoz(Request $request)
    {
        try {

            $request->validate([
                'prompt' => ['required', 'string']
            ]);

            $texto = $request->input('prompt');

            $systemPrompt = AsistenteVozPrompt::system();

            $result = OpenAI::chat()->create([
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $texto,
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 1500,
            ]);

            $response =
                $result->choices[0]
                ->message
                ->content ?? '{}';

            $json = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {

                return response()->json([
                    'success' => false,
                    'error' => 'OpenAI no devolvió un JSON válido',
                    'raw_response' => $response
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $json
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function AnalisisEcg(Request $request)
    {
        try {

            $request->validate([
                'file' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png',
                    'max:5120'
                ]
            ]);

            $file = $request->file('file');

            $fileName = time() . '.' . $file->getClientOriginalExtension();

            $path = public_path('Electrocardiograma');

            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }

            $file->move($path, $fileName);

            $fullPath = $path . DIRECTORY_SEPARATOR . $fileName;

            $base64 = base64_encode(
                file_get_contents($fullPath)
            );

            $mimeType = mime_content_type($fullPath);

            $systemPrompt = AnalisisECGPrompt::system();

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Analiza este electrocardiograma.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64}"
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.1
            ]);

            $content =
                $response->choices[0]
                ->message
                ->content ?? '{}';

            return response()->json([
                'success' => true,
                'analisis' => json_decode($content, true),
                'raw' => $content
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
