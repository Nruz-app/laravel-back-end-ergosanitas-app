<?php

namespace App\IA\Helpers;

use OpenAI\Laravel\Facades\OpenAI;

class PatientHelper
{
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
                    'content' => 'Extrae SOLO el nombre del paciente. Si no hay nombre responde NULL.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0
        ]);

        $name = trim($extract->choices[0]->message->content ?? '');

        if (!$name || strtoupper($name) === 'NULL') {
            return null;
        }

        return $name;
    }
}
