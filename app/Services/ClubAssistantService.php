<?php

namespace App\Services;

use App\Models\ChatClubHistory;
use App\Models\ChatClubSessions;
use App\Models\ChequeoClubPrompt;

class ClubAssistantService
{
    // Tope de pacientes que se envían al modelo por cada consulta
    const MAX_PACIENTES = 120;

    /**
     * Recupera o crea la sesión de chat del club por su session_id.
     * Semántica de $search (la decide el controlador antes de llamar aquí):
     * - null: el campo no vino en el request, se conserva el search_actual actual de la sesión.
     * - '' (cadena vacía): reset explícito, se guarda search_actual = null (chat sobre todo el club).
     * - con contenido: se guarda ese valor como search_actual.
     */
    public function resolveSession(string $sessionId, string $clubEmail, ?string $search): ChatClubSessions
    {
        // club_email es NOT NULL sin default y la base corre en STRICT_TRANS_TABLES:
        // hay que darlo en la creación o el primer insert de la sesión falla.
        $session = ChatClubSessions::firstOrCreate(
            ['session_id' => $sessionId],
            ['club_email' => $clubEmail]
        );

        $datos = ['club_email' => $clubEmail];

        if ($search !== null) {
            $datos['search_actual'] = $search === '' ? null : $search;
        }

        $session->update($datos);

        return $session->fresh();
    }

    public function datosClub(?string $search, string $clubEmail): array
    {
        $results = ChequeoClubPrompt::SP_chequeos_club_prompt($search, $clubEmail);

        if (empty($results) || empty($results[0]->resultado_json)) {
            return [
                'pacientes' => [],
                'total' => 0,
                'truncado' => false,
            ];
        }

        $pacientes = json_decode($results[0]->resultado_json, true);

        if (! is_array($pacientes)) {
            return [
                'pacientes' => [],
                'total' => 0,
                'truncado' => false,
            ];
        }

        $pacientes = array_map([$this, 'normalizarPresion'], $pacientes);

        $total = count($pacientes);
        $truncado = $total > self::MAX_PACIENTES;

        if ($truncado) {
            $pacientes = array_slice($pacientes, 0, self::MAX_PACIENTES);
        }

        return [
            'pacientes' => $pacientes,
            'total' => $total,
            'truncado' => $truncado,
        ];
    }

    /**
     * El SP arma la presión como CONCAT(presionArterial, '/', presion_sistolica),
     * y la columna presionArterial guarda la diastólica: el JSON llega como
     * "70/130", es decir diastólica/sistólica, al revés de la convención médica.
     * Aquí se invierten los dos componentes para entregar "130/70" (sistólica/diastólica)
     * y evitar que el modelo interprete una presión normal como una crisis hipertensiva.
     */
    private function normalizarPresion(array $paciente): array
    {
        $presion = $paciente['medicos']['presionArterial'] ?? null;

        if (! is_string($presion) || substr_count($presion, '/') !== 1) {
            return $paciente;
        }

        [$diastolica, $sistolica] = explode('/', $presion);

        $paciente['medicos']['presionArterial'] = trim($sistolica).'/'.trim($diastolica);

        return $paciente;
    }

    public function handle(ChatClubSessions $session, string $prompt): array
    {
        ChatClubHistory::create([
            'session_id' => $session->session_id,
            'club_email' => $session->club_email,
            'role' => 'user',
            'message' => $prompt,
        ]);

        // Los 20 mensajes más recientes; el id desempata cuando dos filas
        // comparten el mismo created_at (la precisión es de un segundo).
        $messages = ChatClubHistory::where('session_id', $session->session_id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($item) {
                return [
                    'role' => $item->role,
                    'content' => $item->message,
                ];
            })
            ->toArray();

        return $messages;
    }

    public function guardarRespuesta(ChatClubSessions $session, string $texto, array $data): void
    {
        ChatClubHistory::create([
            'session_id' => $session->session_id,
            'club_email' => $session->club_email,
            'role' => 'assistant',
            'message' => $texto,
            'context_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
