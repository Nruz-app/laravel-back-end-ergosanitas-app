# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Descripción

API REST en Laravel 11 (PHP 8.2) para **Ergosanitas**: gestión de chequeos cardiovasculares deportivos, electrocardiogramas, bioimpedancia, certificados médicos, incidencias deportivas, agendamiento, pagos WebPay y asistentes clínicos con OpenAI. No tiene frontend propio (Vite/Blade están sin uso real salvo la plantilla de correo); todo se expone bajo `/api`.

El código y los comentarios están en español. Los métodos de controlador van en `PascalCase` (`FindByEmail`, `ChequeoPDFRut`), a diferencia de la convención Laravel por defecto.

`README.md` documenta el stack, la instalación y el catálogo de endpoints. `docs/openapi.yaml` es el contrato OpenAPI 3.0.3 con las 81 operaciones detalladas (request, respuestas por código y ejemplos); `docs/index.html` lo muestra con Swagger UI. Este archivo cubre lo que no se deduce leyendo un solo archivo.

La vista gráfica vive en cuatro documentos Mermaid: `docs/arquitectura.md` (componentes, capas, providers, despliegue), `docs/flujos.md` (12 diagramas de secuencia end-to-end), `docs/diagrama-clases.md` (clases UML por dominio) y `docs/modelo-datos.md` (ERD, máquina de estados, autorización por perfil, catálogo de SP). `docs/README.md` los indexa.

El contrato y los diagramas **se mantienen a mano**: no hay generación automática ni anotaciones L5-Swagger. Al agregar o modificar una ruta en `routes/api.php`, actualiza `docs/openapi.yaml` en el mismo commit y contrasta con `php artisan route:list --json`. Si cambia un flujo, una clase o el esquema, actualiza también el `.md` de diagramas correspondiente.

## Comandos

```bash
# Dependencias
composer install

# Servidor de desarrollo
php artisan serve

# Tests (PHPUnit 11, suites "Unit" y "Feature")
php artisan test
php artisan test --testsuite=Feature
php artisan test --filter=NombreDelTest
vendor/bin/phpunit tests/Feature/ExampleTest.php

# Formateo (Laravel Pint) — SOLO sobre lo que tocaste
vendor/bin/pint --dirty
vendor/bin/pint app/Services/BioimpedanciaService.php

# Migraciones
php artisan migrate
php artisan migrate:status

# Limpiar cachés tras tocar config/, rutas o providers
php artisan config:clear && php artisan route:clear && php artisan cache:clear

# Entorno completo con Docker (nginx :6162, MySQL :3337, phpMyAdmin :8383, Redis :7379)
docker compose up -d --build
```

No hay `pint.json`, así que Pint aplica el preset `laravel`, que el código existente **no** cumple. `vendor/bin/pint` sin argumentos reformatea medio repositorio y entierra el cambio real; usa siempre `--dirty` o rutas explícitas.

Los tests son solo los stubs de Laravel. `phpunit.xml` tiene comentadas las líneas de SQLite en memoria, así que cualquier test que toque la BD usará la conexión de `.env` (MySQL remoto). Descomenta `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` antes de escribir tests con base de datos, teniendo en cuenta que ahí no existirán los procedimientos almacenados.

## Arquitectura

### Flujo Controller → Service → Model

Cada dominio sigue el mismo patrón de tres capas, cableado a mano:

1. **Controlador** (`app/Http/Controllers/`): valida el `Request`, arma la respuesta JSON y captura excepciones. Recibe los servicios por constructor.
2. **Service** (`app/Services/`): toda la lógica de negocio, queries con el Query Builder y generación de documentos.
3. **Model** (`app/Models/`): Eloquent + métodos estáticos que envuelven procedimientos almacenados MySQL.

`bootstrap/app.php` no registra middleware ni manejadores de excepción (ambos closures están vacíos) y **no existe `app/Http/Middleware`**. Por eso cada acción envuelve su cuerpo en `try/catch` y arma el sobre a mano: no hay handler global que lo haga por ti.

**Conviven siete formatos de respuesta incompatibles.** No hay uno canónico, y varios controladores mezclan más de uno según el método y según si es el camino feliz o el `catch`. **Mira qué devuelve hoy el método que estás tocando**, no lo deduzcas del controlador:

- `{success, message, data}` / `{success: false, message, error}` — `Auth/UserController`, `Auth/GoogleAuthControlle`, `BioimpedanciaController`, `FichaClinicaController`, `FileUploadController`, y en `CertificadoUrlController` y `OpenAIController` solo algunos métodos.
- `{response: {status, mensaje}}` anidado — `EmailController`, `ElectroCardiogramaController::Save`, `ChequeoCardiovascularController::Store`; en `AgendaHorasController`, `EstadisticasController` y `ChequeoCardiovascularController::Update`, **solo en el `catch`**.
- `{status, mensaje}` plano, con `status` unas veces texto (`'OK'`) y otras entero (`200`) — buena parte de `ChequeoCardiovascularController` y `CertificadoUrlController`, `EstadisticasController::deletePagoMensual`, `CargaMasivaController`.
- `{status: 'success', message, data}` — `IncidenciasController`, que además responde **201 también en los GET**.
- Payload crudo sin sobre — `ChequeoCardiovascularController::Index`/`FindByEmail`/`ChequeoRut`/`EstadoGeneral`, `ServiciosController`, `AgendaHorasController::getAgenda`, `ElectroCardiogramaController::FindByRut`, todos los éxitos de `EstadisticasController`.
- Sobre de chat `{sessionId, patient|club, search?, response, status?}` — `OpenAIController` y `ClubAssistantController`.
- No JSON: PDF binario, `streamDownload` de Word, `redirect()->away` en `WebPayResponse`, texto plano en los `health`.

Si el cliente ya consume un endpoint, cambiarle el sobre lo rompe. La tabla completa está en `README.md` § Formatos de respuesta.

**Cada servicio necesita su propio ServiceProvider registrado manualmente en `bootstrap/providers.php`** — no hay auto-discovery para ellos. Al crear un servicio nuevo hay que: crear `app/Services/XService.php`, crear `app/Providers/XServiceProvider.php` con un `singleton()`, y añadirlo a `bootstrap/providers.php`. Los nombres de provider son inconsistentes (`CertificadoProvider` vs `BioimpedanciaServiceProvider`); sigue el existente del dominio que toques.

`CertificadoProvider` es el único que inyecta una dependencia entre servicios (`CertificadoService` recibe `EstadisticasService`).

Si un servicio no está registrado, Laravel igualmente lo resuelve por autowiring cuando puede construir sus dependencias — pero deja de ser singleton. Registra siempre el provider. **`ChequeoCardiovascularWordService` es la excepción que ya existe en el código**: no tiene provider ni aparece en `bootstrap/providers.php`, así que se instancia de nuevo en cada request. Es el ejemplo de qué pasa si te saltas ese paso, no el patrón a copiar.

### El RUT como clave de negocio

El identificador que cruza todos los dominios no es un id numérico sino el **RUT del paciente** (`chequeo_cardiovascular.rut`, `certificado_url.rut_paciente`, `bioimpedancia`, `electro_cardiogranas`, `agenda_horas`, `SP_ficha_clinica`). El formato esperado es `12345678-9`; `App\Imports\ChequeoImport` lo normaliza con `preg_replace` y lo valida contra `/^\d{7,8}-[0-9kK]$/` (sin verificar el dígito verificador). Las filas del Excel con RUT inválido se descartan en silencio: `ChequeoImport` acumula el conteo en `getCantInser()` y el último error en `getErrorMsg()`.

Varias tablas se relacionan por el par `(rut_paciente, id_chequeo)` en vez de por foreign key — por ejemplo el join de `certificado_url` en `ChequeoCardiovascularService`.

### Estado del chequeo y perfiles

`chequeo_cardiovascular.status` avanza por valores de texto libre: `ingresado` → `Testiado` → `ECG FOTO` → `REVISION MEDICA`. **Seis puntos del código PHP lo escriben**, así que al cambiar la regla hay que revisarlos todos:

| Dónde | Valor | Condición |
|---|---|---|
| `ChequeoCardiovascularController::Store()` | `Testiado` | `perfilId == 2`; fija además `fecha_atencion` |
| `ChequeoCardiovascularController::Update()` | `Testiado` | `perfilId == 2` |
| `ChequeoCardiovascularController::Update()` | **el que venga en el request** | `perfilId == 1` |
| `CertificadoService::subirCertificado()` | `ECG FOTO` | siempre |
| `CertificadoUrlController::FileUploadCer()` | `ECG FOTO` | duplica a mano la lógica del servicio |
| `ElectroCardiogramaController::Save()` | `REVISION MEDICA` | siempre |

`ChequeoImport` no escribe `status` (queda el default de la base). Ninguna transición valida el estado previo. El resto de los cambios ocurre en los `UPDATE` del cliente o en procedimientos almacenados. `ChequeoCardiovascularService` los ordena con `orderByRaw("FIELD(cc.status, 'ECG FOTO', 'REVISION MEDICA', 'Testiado', 'ingresado')")`.

La autorización es por `perfiles_id`, obtenido con `UserMetadataService::getPerfilIdByEmail()`. **Dos perfiles tienen tratamiento especial y hay que respetar ambos** al escribir queries nuevas sobre chequeos:

- **Perfil 3 (club deportivo)**: `->where('cc.user_email', $user_email)` — solo ve sus propios registros, e ignora el filtro `selectClub`.
- **Perfil 6 (médico)**: solo ve derivados — join contra `certificado_url` por `(rut, id_chequeo)` con `->where('cu.derivado_medico', 'SI')`, y en la búsqueda además `->where('cc.status', 'ECG FOTO')`.
- Cualquier otro perfil ve todo.

Ese patrón está duplicado en `ChequeoCardiovascularService::filterCalendar()`, `SearchChequeo()` y `ChequeoEmailAll()`; si cambias la regla hay que cambiarla en los tres. **Ojo: hoy las tres copias no son idénticas** — `filterCalendar()` no aplica el perfil 6, y solo `SearchChequeo()` añade el filtro `status = 'ECG FOTO'`. Antes de unificarlas, decide si la divergencia es intencional.

### Procedimientos almacenados

Gran parte de las estadísticas vive en MySQL, no en PHP. Los modelos exponen wrappers estáticos sobre `DB::select('CALL SP_xxx(?)')`:

- `ChequeoCardiovascular`: `SP_estadistica_IMC`, `SP_estado_general`, `sp_estadistica_presion`, `SP_estadistica_hemoglucotest`, `SP_estadistica_monto`, `SP_pago_mensual`
- `IncidentesDeportivos`: `SP_estadistica_liga`, `SP_estadistica_categoria`, `SP_estadistica_lesiones`, `SP_estadistica_parte_cuerpo`, `SP_estadistica_lesiones_fechas`
- `PagoMensual`: `SP_agenda_mensual`, `SP_estadistica_monto_mdc`, `SP_update_pago_mensual`, `SP_chequeos_prompt`
- `FichaClinica`: `SP_ficha_clinica` (devuelve una columna `resultado_json` que el servicio decodifica)
- `Bioimpedancia`: `SP_bioimpedacia_rut`

Estos SP **no están versionados en el repo** ni en las migraciones. Al cambiar su firma hay que actualizarlos directamente en la base de datos.

### Autenticación y perfiles

Coexisten tres mecanismos: `Auth::attempt()` con sesión (login principal en `UserController::AuthRegister`), Sanctum (solo la ruta `/user` de ejemplo) y JWT (`php-open-source-saver/jwt-auth`, configurado como guard `api` en `config/auth.php`). **Las rutas de negocio en `routes/api.php` no llevan middleware de autenticación.** El `user_email` y el `perfiles_id` llegan como parámetros del request, así que el filtrado por perfil descrito arriba es una regla de presentación, no un control de acceso.

Los usuarios viven en dos tablas: `users` (Laravel, password hasheada) y `users_metadata` (datos de negocio: perfil, logo, rut, `ergo_pass`, y una copia en claro de la contraseña). `UserMetadataService::userSave()` y `UserUpdatePassowrd()` escriben en ambas y deben mantenerse sincronizadas.

`GoogleAuthControlle` usa `google/apiclient` directamente (no Socialite), con `env('GOOGLE_CLIENT_ID')` / `env('GOOGLE_CLIENT_SECRET')` leídos en el propio controlador.

### Configuración de negocio en la tabla `params`

Valores de negocio (no de infraestructura) viven en la tabla `params` y se leen con `Params::where('descripcion', 'CLAVE')->firstOrFail()->valor`. Hoy se usa `VALOR-ECG` en `CertificadoService`, `CertificadoUrlController` y `ElectroCardiogramaController`. Si un valor tarifario parece "hardcodeado y ausente", búscalo ahí antes que en `.env`. Al ser `firstOrFail()`, una fila faltante en `params` tumba el endpoint con un 500.

Ese flujo tiene un efecto cruzado fácil de pasar por alto: al subir un certificado/ECG, `CertificadoService::subirCertificado()` borra el certificado previo del par `(rut, id_chequeo)`, marca el chequeo con `status = 'ECG FOTO'` **y** llama a `EstadisticasService::PagoMensual($periodo, $user_email, $valor_ecg, "ADD")`. Es decir, la carga del documento es lo que factura el mes del club; al tocar la subida de certificados considera siempre el lado de facturación (y que reintentar la subida vuelve a facturar).

### Integración OpenAI

Usa `openai-php/laravel` (facade `OpenAI::chat()`). Los prompts de sistema viven como clases estáticas en `app/IA/` (`AnalisisECGPrompt::system()`, `AsistenteChatPacientePrompt::system($patient, $data)`, `AnalisisBioimpedanciaPrompt`, `AnalisisRutBioimpedanciaPrompt`, `AsistenteVozPrompt`) — mantén los prompts ahí, no incrustados en controladores.

Cada prompt debe vivir en **un solo archivo**: dos archivos que declaren la misma clase en `app/IA/` colisionan en el classmap que genera `composer install --optimize-autoloader` (lo que hace el `dockerfile` de producción), y cuál gana queda indeterminado. Para versionar un prompt, usa git, no copias del archivo.

Dónde ocurre cada llamada (es inconsistente; sigue el patrón del dominio que toques):

- Chat clínico: `OpenAIService` invocado desde `OpenAIController::AsQuestionUseCase`.
- ECG y asistente de voz: `OpenAI::chat()` directo en `OpenAIController`.
- Bioimpedancia: `OpenAI::chat()` en `BioimpedanciaController::FormUpload` y en `BioimpedanciaService`.

Modelos en uso: `gpt-4o-mini` (extracción de paciente, chat) y `gpt-4.1-mini` (ECG, voz, bioimpedancia).

El chat clínico (`POST api/sam-assistant/as-question`) funciona así: `OpenAIService::resolveSession()` crea/recupera una `ChatSessions` por `sessionId` y extrae el identificador del paciente del prompt (regex de RUT y, si falla, una llamada a `gpt-4o-mini` en `PatientHelper::extractPatient()`). Sin paciente resuelto responde `status: needs_identifier`. Con paciente, `handle()` recupera 20 mensajes de `chat_history` y `EstadisticasService::ChequeoPrompt()` (→ `SP_chequeos_prompt`) aporta los datos clínicos. `POST api/sam-assistant/reset-patient` suelta el paciente de la sesión.

Dos detalles del historial que no se ven en el nombre del método: la consulta es `orderBy('created_at', 'asc')->limit(20)`, así que trae los **20 mensajes más antiguos**, no los más recientes (el asistente por club sí usa `desc` + `reverse`); y `chat_history` se agrupa por `patient_identifier` y **no guarda `session_id`**, así que dos sesiones sobre el mismo paciente comparten la conversación.

### Pagos WebPay (Transbank)

`WebPayController` habla con Transbank por HTTP crudo (`Http::withHeaders()`), **sin el SDK de Transbank**: `WEBPAY_URL`, `WEBPAY_ID`, `WEBPAY_SECRET`, `WEBPAY_RETURN` leídos con `env()` en el propio controlador. `WebPayRequest` crea la transacción (`buy_order` = fecha `Ymd` + nombre del servicio, `session_id` = RUT) y `WebPayResponse` la confirma con un `PUT` sobre `token_ws`, que se lee de `$_GET` directamente, no del `Request`.

Es el único lugar del código que persiste errores: el `catch` graba en la tabla `logs_api` vía el modelo `LogsApi` **y no devuelve respuesta**, así que un fallo aquí sale como cuerpo vacío. Si tocas este controlador, ese es el bug de fondo a considerar.

### Generación de documentos

- **PDF con mPDF**: `ChequeoCardiovascularPDFService` y `BioimpedanciaService` construyen HTML como string y lo renderizan. Los assets (`logo.png`, `firmarDoctor.jpg`, `firmarErgo.jpg`, `watermark.png`, `firma_cardiologo.jpeg`) se referencian con `public_path()`.
- **Word con PHPWord**: `ChequeoCardiovascularWordService`.
- **Excel con maatwebsite/excel**: `App\Imports\ChequeoImport` para la carga masiva.
- **Correo**: `App\Mail\EmailMailable` + `Mail::to()` (el `phpmailer` del `composer.json` no se usa). `EmailController::EmailReservaHora` manda copia fija a `ergosanitas@gmail.com`.

Los archivos subidos van a `public/` (`public/Certificado`, `public/Logo`, `public/Electrocardiograma`, `public/Bioimpedancia`) mediante `$file->move()`, **no** al disco de storage de Laravel. Las URLs públicas se arman con `env('API_PATH_CER')` / `env('API_PATH_LOGO')`.

Consecuencia en producción: `dockerHub/docker-compose.yml` monta volúmenes para `storage/` y `bootstrap/cache`, **pero no para `public/`**. Los archivos subidos viven en la capa escribible del contenedor, así que un redeploy de `:latest` los pierde. Tenlo presente antes de proponer que algo nuevo se guarde en `public/`.

### Rutas

Todo en `routes/api.php`, plano y sin agrupar, con prefijo automático `/api`. Ojo con el orden: rutas específicas como `chequeo-cardiovascular/pdf/{id}` deben ir antes de `chequeo-cardiovascular/{id_paciente}`. Existe `GET /api/execute-link-simbolik`, que ejecuta `Artisan::call('storage:link')` desde HTTP.

## Trampas conocidas

- **No uses `php artisan config:cache`.** Varios controladores y servicios leen `env()` fuera de los archivos de configuración: `API_PATH_CER` (`CertificadoService`, `CertificadoUrlController`), `API_PATH_LOGO` (`Auth/UserController`), `WEBPAY_URL|ID|SECRET|RETURN` (`WebPayController`) y `GOOGLE_CLIENT_ID|SECRET` (`GoogleAuthControlle`). Con la config cacheada esas llamadas devuelven `null` y se rompen certificados, logos, pagos y login de Google. Por eso `dockerHub/entrypoint.sh` hace `config:clear` en cada arranque.
- **`.env.example` está incompleto**: define `API_PATH_CER` pero no `API_PATH_LOGO`, que el código sí usa.
- **Tablas sin migración**: `users_metadata`, `params`, `electro_cardiogranas` y `pago_mensual` existen solo en la base de datos, igual que los procedimientos almacenados. `php artisan migrate` sobre una base vacía deja el esquema incompleto.
- El nombre real de la tabla de ECG es **`electro_cardiogranas`** (con "n"): es un typo consolidado en producción, respétalo en las queries.
- Hay **rutas duplicadas** en `routes/api.php`: `/user` (líneas 7 y 44) e `incidencia-deportivos/count-liga` (líneas 267 y 270). Laravel se queda con la última declaración; al editar una de ellas asegúrate de tocar la que realmente se resuelve, o elimina la duplicada.
- `api.php` en la raíz del proyecto es una copia obsoleta y sin uso de `routes/api.php`. El archivo real es `routes/api.php`.
- `app/Http/Controllers/bootstrap/` (con `app.php`, `providers.php` y `cache/`) es otra copia versionada y muerta del `bootstrap/` real; nunca se carga. Los archivos que importan son `bootstrap/app.php` y `bootstrap/providers.php`. `src/` está vacío; `base_datos/` solo contiene `references/sp/SP_chequeos_club_prompt.sql`, el único procedimiento almacenado versionado en el repo.
- El controlador de Google OAuth se llama `GoogleAuthControlle` (sin la "r" final), tanto el archivo como la clase; y `UserController` expone `UserUpdatePassowrd`. Son typos consolidados: no los "corrijas" sin actualizar `routes/api.php` y los clientes.
- `App\Models\FichaClinica` tiene el `$table` comentado a propósito: solo es un envoltorio de `SP_ficha_clinica`, no mapea una tabla.
- No dupliques archivos en `app/IA/` para conservar versiones anteriores de un prompt: declararían la misma clase y colisionarían en el classmap optimizado (ver "Integración OpenAI").

## Despliegue

Push a `main` dispara `.github/workflows/github-action.build.yml`: versionado semántico automático con `PaulHatch/semantic-version` y build+push de la imagen `nruz176/laravel-back-end-ergosanitas-app` (tags `:<version>` y `:latest`) a Docker Hub. No hay lint, tests ni migraciones en el pipeline: el push a `main` publica directo.

El bump lo decide el **texto del mensaje de commit**: `major` sube major, `feat` sube minor, cualquier otra cosa sube patch. Son coincidencias de subcadena, así que un mensaje como "refactor: quita el feature flag" bumpea minor sin querer.

En el servidor se usa `dockerHub/docker-compose.yml` con `dockerHub/entrypoint.sh` (limpia cachés, hace `storage:link` y arranca php-fpm).
