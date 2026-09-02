<?php

namespace App\Services;

use App\IA\Helpers\PatientHelper;
use App\Models\ChatHistory;
use App\Models\ChatSessions;

class OpenAIService
{
    public function resolveSession(string $sessionId,string $prompt): ChatSessions {

        $session = ChatSessions::firstOrCreate(['session_id' => $sessionId]);

        if ($session->patient_identifier) {return $session;}

        $patient = PatientHelper::extractPatient($prompt);

        if (!$patient) {
            return $session;
        }

        $session->update(['patient_identifier' => $patient]);

        return $session->fresh();
    }

    public function handle(ChatSessions $session,string $prompt): array {

        $patient = $session->patient_identifier;

        if (!$patient) {
            return [
                'patient' => null,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'session' => $session
            ];
        }
        if ($patient) {
            ChatHistory::create([
                'patient_identifier' => $patient,
                'role' => 'user',
                'message' => $prompt
            ]);
        }

        $messages = ChatHistory::where('patient_identifier',$patient)
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'role' => $item->role,
                    'content' => $item->message
                ];
            })
            ->toArray();

        return [
            'patient' => $patient,
            'messages' => $messages,
            'session' => $session
        ];
    }
}
