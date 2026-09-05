# SPEC 01 — Asistente de chat por club (SP_chequeos_club_prompt)

> **Estado:** Aprobado
> **Depende de:** —
> **Fecha:** 2026-09-04
> **Objetivo:** Exponer un endpoint POST de chat que, dado el email de un club, responda preguntas sobre los chequeos en `REVISION MEDICA` de ese club usando `SP_chequeos_club_prompt`, con filtro opcional por rut o nombre y memoria de conversación por sesión.

---

## 1 — Por qué existe esta spec

Hoy el único asistente conversacional (`POST api/sam-assistant/as-question`) está construido **alrededor de un paciente**: exige identificar un RUT o nombre antes de responder, y sin él contesta `needs_identifier`. Un club no quiere eso: quiere preguntar por *sus* pacientes, a veces por uno concreto y a veces por todos.

El SP `SP_chequeos_club_prompt(p_search, p_club)` ya resuelve esa consulta —filtra por `cc.user_email = p_club` y por `status = 'REVISION MEDICA'`, y devuelve un `resultado_json` con `personales` / `medicos` / `revision` por paciente— pero ningún código PHP lo invoca todavía.

Esta spec construye el flujo paralelo completo (controlador → service → modelos → provider → prompt → rutas) **sin tocar el asistente de paciente existente**, que ya está en producción y cuyo contrato consume el cliente.

---

## 2 — Alcance

**Dentro:**

- `POST api/sam-assistant-club/as-question`: chat del club con memoria por `sessionId`.
- `POST api/sam-assistant-club/reset-search`: suelta el filtro de paciente pegado a la sesión.
- Wrapper PHP de `SP_chequeos_club_prompt` en un modelo nuevo.
- Tablas nuevas `chat_club_sessions` y `chat_club_history` con sus migraciones.
- Prompt de sistema nuevo en `app/IA/AsistenteChatClubPrompt.php`.
- Service nuevo con su provider registrado a mano en `bootstrap/providers.php`.
- Tope de pacientes enviados al modelo, con aviso de truncado dentro del prompt.
- Verificación manual con Postman (bodies de ejemplo documentados en esta spec).

**Fuera de alcance (para specs futuras):**

- **Autenticación o middleware** del endpoint: la ruta queda pública como el resto de `routes/api.php`.
- **Tests automatizados**: `phpunit.xml` apunta al MySQL remoto y en SQLite no existen los procedimientos almacenados; testear esto exige mockear el modelo y OpenAI.
- **Modificar el asistente de paciente**: `AsQuestionUseCase`, `OpenAIService`, `chat_sessions` y `chat_history` quedan intactos. Nada de refactorizar para "compartir código".
- **Colección Postman versionada** en el repo.
- **Crear o modificar el SP**: se asume que `SP_chequeos_club_prompt` ya existe en la base y que el `.sql` de `base_datos/references/sp/` es su copia versionada.
- Resumen agregado / estadísticas del club cuando no hay `search` (hoy se resuelve truncando).

---

## 3 — Modelo de datos

### 3.1 Tablas nuevas

Dos migraciones nuevas en `database/migrations/`. No reutilizan `chat_sessions` / `chat_history`: esas están indexadas por `patient_identifier` y meterles emails de club ensucia la semántica del índice.

```php
// create_chat_club_sessions_table
Schema::create('chat_club_sessions', function (Blueprint $table) {
    $table->id();
    $table->string('session_id')->unique();   // hilo de conversación
    $table->string('club_email')->index();    // = chequeo_cardiovascular.user_email
    $table->string('search_actual')->nullable(); // rut o nombre pegado a la sesión
    $table->timestamps();
});

// create_chat_club_history_table
Schema::create('chat_club_history', function (Blueprint $table) {
    $table->id();
    $table->string('session_id')->index();
    $table->string('club_email')->index();
    $table->string('role');                   // user | assistant
    $table->longText('message');
    $table->longText('context_json')->nullable(); // JSON clínico usado en esa respuesta
    $table->timestamps();

    $table->index(['session_id', 'created_at']);
});
```

### 3.2 Contrato del endpoint principal

`POST api/sam-assistant-club/as-question`

```json
{
  "email": "club@ejemplo.cl",
  "prompt": "¿Cuántos pacientes tienen la presión alta?",
  "search": "12345678-9",
  "sessionId": "postman-001"
}
```

| Campo       | Regla                       | Nota                                                                    |
| ----------- | --------------------------- | ----------------------------------------------------------------------- |
| `email`     | `required\|email`           | Es el `user_email` del club; se pasa tal cual como `p_club`.            |
| `prompt`    | `required\|string\|max:500` | Misma regla que el asistente de paciente.                              |
| `search`    | `nullable\|string\|max:255` | Rut o nombre. Ausente = se hereda el de la sesión. `""` = todo el club. |
| `sessionId` | `required\|string`          | Hilo de conversación.                                                  |

Respuesta OK (200) — mismo sobre que `AsQuestionUseCase`, adaptado al club:

```json
{
  "sessionId": "postman-001",
  "club": "club@ejemplo.cl",
  "search": "12345678-9",
  "response": "texto del modelo"
}
```

Respuesta cuando el SP devuelve `[]` (200):

```json
{
  "sessionId": "postman-001",
  "club": "club@ejemplo.cl",
  "search": "12345678-9",
  "response": "No encontré chequeos en revisión médica para ese club con ese filtro.",
  "status": "sin_datos"
}
```

Respuesta de error (`catch`, 500) — copia literal del `catch` existente: `{ "error": ..., "line": ..., "file": ... }`.

### 3.3 Contrato del reset

`POST api/sam-assistant-club/reset-search` con body `{ "sessionId": "postman-001" }` (`required|string`).

- Sesión encontrada → `{ "ok": true, "message": "Filtro de paciente reiniciado" }` (200). Pone `search_actual = null`. **No borra el historial.**
- Sesión no encontrada → `{ "ok": false, "message": "Sesión no encontrada" }` (404).

### 3.4 Forma de `resultado_json`

El SP devuelve **una fila** con la columna `resultado_json`: un array JSON (posiblemente vacío, por el `COALESCE(..., JSON_ARRAY())`) donde cada elemento es:

```json
{
  "personales": { "rut": "...", "nombre": "...", "edad": 0, "sexo_paciente": "...", "fechaNacimiento": "..." },
  "medicos": { "estatura": 0, "peso": 0, "hemoglucotest": 0, "presionArterial": "120/80", "saturacionOxigeno": 0, "imc": 0, "gradoIncidenciaPosterio": "...", "recuperacion": "...", "pulso": 0, "medicamentosDiarios": "..." },
  "revision": { "frecuencia_cardiaca_paciente": 0, "observacion_paciente": "...", "estado_paciente": "...", "derivacion_paciente": "..." }
}
```

---

## 4 — Plan de implementación

Cada paso deja el sistema funcional. El orden importa: la base primero, el cableado después.

**Paso 1 — Verificar la base antes de escribir PHP.**
Contra la BD real: `SELECT VERSION();` y `SHOW PROCEDURE STATUS WHERE Name = 'SP_chequeos_club_prompt';`, más una llamada de humo `CALL SP_chequeos_club_prompt(NULL, '<email de club con datos>');`.
El SP usa `JSON_ARRAYAGG`, disponible **desde MySQL 5.7.22**. Si `VERSION()` es anterior a 5.7.22 o el SP no está creado, **detener e informar**: esa premisa cambia el trabajo. Anotar en el reporte el email de club usado para la prueba.

> **Verificado el 2026-09-04:** servidor `5.7.44-48` sobre la base `ergosan1_bdd`; `SELECT JSON_ARRAYAGG(1)` devuelve `[1]`; el SP existe y su cuerpo es idéntico al `.sql` de referencia; la llamada de humo devuelve 116 elementos con la forma esperada. **Datos de prueba:** club `brisas@ergosanitas.com` (116 registros), RUT `25527383-3`. Otros clubes con datos: `Colegio.altair@ergosanitas.com` (109), `cobresal.buin@ergosanitas.com` (86), `ue.colina@ergosanitas.com` (78), `brisas.femenino@ergosanitas.com` (65).

**Paso 2 — Migraciones.**
Crear `chat_club_sessions` y `chat_club_history` como en §3.1 y ejecutar `php artisan migrate`. Comprobar con `php artisan migrate:status` y `SHOW CREATE TABLE`.

**Paso 3 — Modelos.**
- `app/Models/ChatClubSessions.php`: `$table = 'chat_club_sessions'`, `$fillable = ['session_id', 'club_email', 'search_actual']`.
- `app/Models/ChatClubHistory.php`: `$table = 'chat_club_history'`, `$fillable = ['session_id', 'club_email', 'role', 'message', 'context_json']`.
- `app/Models/ChequeoClubPrompt.php`: sin `$table` (solo envuelve el SP, igual que `FichaClinica`), con el wrapper estático:

```php
public static function SP_chequeos_club_prompt($search, $club) {
    return DB::select('CALL SP_chequeos_club_prompt(?, ?)', [$search, $club]);
}
```

**Paso 4 — Service.**
`app/Services/ClubAssistantService.php`, con la lógica de negocio completa y **sin** llamadas a OpenAI (esas van en el controlador, como en `AsQuestionUseCase`):

- `const MAX_PACIENTES = 40;`
- `resolveSession(string $sessionId, string $clubEmail, ?string $search): ChatClubSessions` — `firstOrCreate` por `session_id`; si `$search` viene informado (incluido `''`), lo persiste en `search_actual` (`''` → `null`); si viene ausente/`null`, conserva el de la sesión. Mantiene `club_email` actualizado.
- `datosClub(?string $search, string $clubEmail): array` — llama al wrapper, `json_decode` de `resultado_json`, y devuelve `['pacientes' => array, 'total' => int, 'truncado' => bool]`, cortando con `array_slice(..., 0, self::MAX_PACIENTES)` cuando `total > MAX_PACIENTES`.
- `handle(ChatClubSessions $session, string $prompt): array` — guarda el mensaje `user` en `chat_club_history` y devuelve los **últimos 20** mensajes de esa `session_id` (`orderBy('created_at')`, `limit(20)`) mapeados a `['role' => ..., 'content' => ...]`.
- `guardarRespuesta(ChatClubSessions $session, string $texto, array $data): void` — inserta la fila `assistant` con `context_json` = `json_encode($data, JSON_UNESCAPED_UNICODE)`.

**Paso 5 — Provider.**
`app/Providers/ClubAssistantServiceProvider.php` con un `singleton(ClubAssistantService::class, ...)`, siguiendo `BioimpedanciaServiceProvider`, **y su línea añadida en `bootstrap/providers.php`** en orden alfabético (entre `ChequeoCardiovascularProvider` y `ElectroCardiogramaProvider`). Sin ese registro deja de ser singleton.

**Paso 6 — Prompt.**
`app/IA/AsistenteChatClubPrompt.php`, clase `AsistenteChatClubPrompt` con
`public static function system(string $club, ?string $search, array $pacientes, int $total, bool $truncado): array`.
Devuelve el array de mensajes `system` con: las reglas del asistente clínico (usar únicamente la información entregada, no inventar, decir cuándo un dato no existe, responder en español, explicar términos médicos), la indicación de que los datos corresponden **solo a los chequeos en `REVISION MEDICA` del club indicado**, el `search` activo (o "todos los pacientes del club"), el conteo real (`$total`), y —cuando `$truncado` es `true`— la instrucción explícita de avisar que la lista fue recortada a `MAX_PACIENTES` y sugerir filtrar por rut o nombre. Los datos van en un tercer mensaje con `json_encode($pacientes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)`.
Un solo archivo por prompt: dos archivos declarando la misma clase colisionan en el classmap optimizado.

**Paso 7 — Controlador.**
`app/Http/Controllers/ClubAssistantController.php`, con `ClubAssistantService` inyectado por constructor y todo el cuerpo en `try/catch` (no hay handler global):

- `AsQuestionClubUseCase(Request $request)`: valida §3.2 → `resolveSession()` → `datosClub()` → si `pacientes` está vacío devuelve el sobre `sin_datos` de §3.2 **sin llamar a OpenAI** → `handle()` → `array_merge(AsistenteChatClubPrompt::system(...), $chat['messages'])` → `OpenAI::chat()->create(['model' => 'gpt-4o-mini', 'messages' => $messages, 'temperature' => 0.2])` → `guardarRespuesta()` → sobre OK.
- `ResetSearch(Request $request)`: §3.3.

Métodos en `PascalCase`, textos y comentarios en español.

**Paso 8 — Rutas.**
En `routes/api.php`, junto al bloque `//CHATGPT-API`:

```php
Route::post('sam-assistant-club/as-question',[ClubAssistantController::class,'AsQuestionClubUseCase'])
    ->name('AsQuestionClubUseCase');

Route::post('sam-assistant-club/reset-search',[ClubAssistantController::class,'ResetSearch'])
    ->name('ResetSearch');
```

Más el `use App\Http\Controllers\ClubAssistantController;` arriba. No hay colisión con `sam-assistant/...`: son prefijos distintos.

**Paso 9 — Verificación.**
`php -l` de cada archivo tocado, `vendor/bin/pint --dirty`, `php artisan config:clear && php artisan route:clear && php artisan cache:clear` (**nunca `config:cache`**), `php artisan route:list --path=sam-assistant-club`, comprobación de singleton con `tinker`, `php artisan test`, y las cuatro pruebas Postman de §5.

---

## 5 — Criterios de aceptación

- [ ] `php artisan route:list --path=sam-assistant-club` muestra exactamente las dos rutas nuevas, sin duplicados.
- [ ] `php artisan migrate:status` muestra las dos migraciones nuevas aplicadas, y `chat_club_sessions` / `chat_club_history` existen en la BD.
- [ ] `bootstrap/providers.php` contiene `App\Providers\ClubAssistantServiceProvider::class`, y `app(ClubAssistantService::class) === app(ClubAssistantService::class)` devuelve `true`.
- [ ] **Postman A (club + prompt, sin search):** POST `sam-assistant-club/as-question` con `email` de un club con chequeos en `REVISION MEDICA` y sin `search` → 200, `search: null`, y el texto responde sobre varios pacientes del club.
- [ ] **Postman B (con search por rut):** el mismo body más `"search": "<rut real del club>"` → 200, `search` con ese valor, y la respuesta habla solo de ese paciente.
- [ ] **Postman C (memoria):** un segundo POST con el mismo `sessionId` y **sin** `search` mantiene el paciente de B (`search` en la respuesta sigue siendo ese rut) y el modelo responde con contexto del mensaje anterior.
- [ ] **Postman D (reset):** POST `sam-assistant-club/reset-search` con ese `sessionId` → `{ "ok": true, ... }`; el siguiente `as-question` sin `search` vuelve a responder sobre todo el club.
- [ ] Email de club sin chequeos en `REVISION MEDICA` → 200 con `status: "sin_datos"` y **sin** llamada a OpenAI.
- [ ] Body sin `email`, sin `prompt` o sin `sessionId` → 422 de validación.
- [ ] `chat_club_history` acumula una fila `user` y una `assistant` por intercambio, con `context_json` poblado en la fila `assistant`.
- [ ] Un club con más de 40 pacientes en `REVISION MEDICA` recibe respuesta y el modelo indica que la lista fue recortada.
- [ ] `POST api/sam-assistant/as-question` sigue funcionando igual que antes; no hay diff en `OpenAIController.php`, `OpenAIService.php` ni en las migraciones de `chat_sessions` / `chat_history`.
- [ ] `vendor/bin/pint --dirty` sin cambios pendientes y `php artisan test` en verde.

---

## 6 — Decisiones tomadas y descartadas

| Decisión                                                                    | Por qué                                                                                                                                              |
| --------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Tablas nuevas** `chat_club_sessions` / `chat_club_history`                 | Descartado reutilizar `chat_sessions` / `chat_history`: están indexadas por `patient_identifier` y guardar ahí emails de club rompe esa semántica.    |
| **Historial por `session_id`**, no por `club_email`                          | `sessionId` es obligatorio; dos personas del mismo club en dos chats simultáneos no deben compartir hilo.                                            |
| **`search` explícito y pegajoso** (sticky en la sesión)                      | Descartado extraer el paciente del prompt con `PatientHelper`: añade una llamada extra a `gpt-4o-mini` y vuelve ambiguo el caso "pregúntame por todos". |
| **Tope de 40 pacientes** con aviso de truncado                               | Sin `search` el SP devuelve todo el club: mandar el JSON completo puede exceder el contexto y disparar el coste. Descartado el resumen agregado, que es otra spec. |
| **Solo se valida el formato del email**                                      | Descartado comprobar `users_metadata` o exigir `perfiles_id = 3`: el repo no tiene middleware, el filtrado por perfil es presentación y exigir club bloquearía a admins y médicos. |
| **Sobre igual al del asistente de paciente**                                 | Descartados los sobres `{success, message, data}` y `{response:{status, mensaje}}`: el cliente del chat ya sabe consumir `{sessionId, ..., response}`. |
| **`status: "sin_datos"` en vez de `needs_identifier`**                       | Ajuste semántico respecto del endpoint hermano: aquí el club **siempre** está identificado, lo que falta son datos. Si prefieres reusar `needs_identifier` por compatibilidad de cliente, cámbialo antes de aprobar. |
| **Controlador nuevo**, no un método más en `OpenAIController`               | `OpenAIController` ya mezcla ECG, voz y chat de paciente; el flujo del club es paralelo y no debe arrastrar sus dependencias.                        |
| **`gpt-4o-mini`, `temperature` 0.2**                                         | Mismo modelo y temperatura que el chat que este endpoint imita.                                                                                     |
| **El SP no se crea ni se modifica**                                          | Se asume ya creado en la BD; el `.sql` de `base_datos/references/sp/` es la copia versionada. **Verificado en el Paso 1**: existe en `ergosan1_bdd` y su cuerpo coincide con el `.sql`. |
| **La base es MySQL 5.7.44, no 8.x**                                          | Corrección posterior a la aprobación: la spec asumía MySQL 8 porque daba por hecho que `JSON_ARRAYAGG` era exclusivo de 8.0. Existe desde 5.7.22 y el SP funciona. No cambia ninguna decisión de diseño; sí mantiene vigente el límite de 5.7 (sin CTEs ni funciones de ventana) para cualquier SQL futuro. |
| **Reset dentro del alcance**                                                 | Sin él, un `search` pegajoso no tiene salida limpia salvo mandar `search: ""` a mano.                                                                |

---

## 7 — Riesgos identificados

- ~~**`JSON_ARRAYAGG` no existe en MySQL 5.7.**~~ **Descartado en el Paso 1:** la función existe desde 5.7.22 y el servidor real es 5.7.44-48. El riesgo era una premisa equivocada de esta spec, no un problema del entorno. Se mantiene el límite duro de 5.7 para el resto de SQL: **sin CTEs ni funciones de ventana**.
- **El SP no está versionado en las migraciones.** Vive solo en la base. Un entorno nuevo levantado con `php artisan migrate` no lo tendrá, y el endpoint devolverá error 500 desde el `catch`. Además, en la base verificada el SP tiene `Created`/`Modified` = 2026-09-04: **confirmar que está desplegado en el servidor de producción** antes de dar el endpoint por disponible ahí.
- **El truncado a 40 se activa casi siempre.** Los cinco clubes con más actividad tienen entre 65 y 116 pacientes en `REVISION MEDICA`, así que las preguntas globales ("¿cuántos tienen…?") se responden sobre un subconjunto. El prompt debe avisarlo de forma inequívoca.
- **Coste y latencia por request.** Sin `search`, cada mensaje manda hasta 40 fichas clínicas completas al modelo. El tope acota el daño, no lo elimina.
- **El `catch` filtra `file` y `line`** al cliente, igual que el endpoint existente. Se replica a propósito por consistencia; endurecerlo es otra spec (junto con la autenticación).
- **Datos clínicos sin control de acceso.** Cualquiera que conozca el email de un club puede leer sus chequeos: la ruta es pública, como el resto de la API. Está fuera de alcance, pero es el riesgo más grande de este endpoint.
- **`electro_cardiogranas` con `INNER JOIN`.** Un chequeo en `REVISION MEDICA` sin fila de ECG no aparece en el resultado. Si el club "ve pacientes que faltan", ese es el motivo, y no es un bug del endpoint.
- **Nota heredada:** la ruta `sam-assistant/reset-patient` apunta a `'ResetPatient'` mientras el método real se llama `resetPatient`; funciona porque PHP no distingue mayúsculas en nombres de método. En las rutas nuevas el nombre coincide exactamente con el método.
