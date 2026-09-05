<?php

namespace App\IA\Helpers;

use OpenAI\Laravel\Facades\OpenAI;

class PatientHelper
{
    /**
     * Identificador del paciente al que se ancla la sesión de chat.
     *
     * Es una puerta de un solo sentido: una vez que OpenAIService guarda el
     * patient_identifier, la sesión ya no vuelve a extraerlo (salvo reset-patient).
     * Por eso el prompt prefiere NULL antes que un falso positivo: quedarse sin
     * paciente solo cuesta una repregunta, mientras que anclarse al identificador
     * equivocado contamina toda la conversación.
     */
    public static function extractPatient(string $prompt): ?string
    {
        if (preg_match('/\d{7,8}-[\dkK]/', $prompt, $matches)) {
            return $matches[0];
        }

        $extract = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => self::promptSistema(),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0,
        ]);

        $name = trim($extract->choices[0]->message->content ?? '');

        // El modelo a veces envuelve la respuesta en comillas o la cierra con punto.
        $name = trim($name, " \t\n\r\0\x0B\"'.");

        if (! $name || strtoupper($name) === 'NULL') {
            return null;
        }

        return $name;
    }

    private static function promptSistema(): string
    {
        return <<<'PROMPT'
Tu única tarea es detectar si el mensaje nombra al PACIENTE (deportista) sobre el que se
quiere consultar, dentro de un sistema de chequeos cardiovasculares deportivos en Chile.

Responde EXCLUSIVAMENTE con una de estas dos cosas:
- El nombre del paciente, tal como aparece escrito en el mensaje.
- La palabra NULL.

Sin comillas, sin puntuación final, sin explicaciones, sin prefijos como "Nombre:".

Devuelve NULL cuando:
- El mensaje no contiene ningún nombre de persona (saludos, preguntas generales,
  "hola", "¿qué puedes hacer?", "muéstrame los chequeos de hoy").
- El único nombre presente NO es el del paciente: nombres de médicos, del club o
  institución, del usuario que escribe, de una ciudad, de un mes o de un día.
- Hay más de un paciente posible y no puedes determinar cuál es.
- Solo hay un nombre de pila muy genérico sin contexto que indique que es el paciente.

Reglas de extracción:
- Copia el nombre literalmente del mensaje. No lo corrijas, no lo completes, no lo
  traduzcas y no cambies su capitalización más allá de lo evidente.
- Los nombres chilenos suelen llevar dos apellidos: inclúyelos si están escritos.
- Quita las palabras que no son parte del nombre ("el paciente", "el jugador", "don",
  "doña", "señor", "niño").
- No inventes un apellido que no esté en el mensaje.

Ejemplos:
"¿cuál es la presión de Juan Pérez Soto?" => Juan Pérez Soto
"dame los datos del paciente Matías González" => Matías González
"hola, ¿en qué me puedes ayudar?" => NULL
"muéstrame los chequeos alterados de esta semana" => NULL
"el doctor Ramírez revisó el electro" => NULL
"chequeos del club Deportivo Colina" => NULL
PROMPT;
    }
}
