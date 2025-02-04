<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIController extends Controller {

    public function AsQuestionUseCase(Request $request) {

        try {

            // Validar que el prompt esté presente en la solicitud
            $request->validate(['prompt' => 'required|string|max:500',]);

            $prompt = $request->input('prompt'); // Usa input() para obtener el valor del prompt

            $result = OpenAI::chat()->create([
                'model' => 'gpt-4', // Asegúrate de usar el modelo correcto
                'messages' => [ // Configurar el mensaje como un usuario
                    [
                        'role' => 'user', 
                        'content' => $prompt
                    ], 
                ],
                'max_tokens' => 100,
            ]);

            // Obtener la respuesta del chat
            $response = $result->choices[0]->message->content ?? '';

            return response()->json(['response' => $response], 200);

        }
        catch (\Exception $e) {
            // Maneja cualquier excepción y devuelve un mensaje de error
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }

}
