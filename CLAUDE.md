# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Descripción

API REST en Laravel 11 (PHP 8.2) para **Ergosanitas**: gestión de chequeos cardiovasculares deportivos, electrocardiogramas, bioimpedancia, certificados médicos, incidencias deportivas, agendamiento, pagos WebPay y asistentes clínicos con OpenAI. No tiene frontend propio (Vite/Blade están sin uso real salvo la plantilla de correo); todo se expone bajo `/api`.

El código y los comentarios están en español. Los métodos de controlador van en `PascalCase` (`FindByEmail`, `ChequeoPDFRut`), a diferencia de la convención Laravel por defecto.

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

# Formateo (Laravel Pint)
vendor/bin/pint
vendor/bin/pint --test

# Migraciones
php artisan migrate
php artisan migrate:status

# Limpiar cachés tras tocar config/, rutas o providers
php artisan config:clear && php artisan route:clear && php artisan cache:clear

# Entorno completo con Docker (nginx :6162, MySQL :3337, phpMyAdmin :8383, Redis :7379)
docker compose up -d --build
```

Los tests son solo los stubs de Laravel. `phpunit.xml` tiene comentadas las líneas de SQLite en memoria, así que cualquier test que toque la BD usará la conexión de `.env` (MySQL remoto). Descomenta `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` antes de escribir tests con base de datos, teniendo en cuenta que ahí no existirán los procedimientos almacenados.

## Arquitectura

### Flujo Controller → Service → Model

Cada dominio sigue el mismo patrón de tres capas, cableado a mano:

1. **Controlador** (`app/Http/Controllers/`): valida el `Request`, arma la respuesta JSON y captura excepciones devolviendo `{success, message, error}`. Recibe los servicios por constructor.
2. **Service** (`app/Services/`): toda la lógica de negocio, queries con el Query Builder y generación de documentos.
3. **Model** (`app/Models/`): Eloquent + métodos estáticos que envuelven procedimientos almacenados MySQL.

**Cada servicio necesita su propio ServiceProvider registrado manualmente en `bootstrap/providers.php`** — no hay auto-discovery para ellos. Al crear un servicio nuevo hay que: crear `app/Services/XService.php`, crear `app/Providers/XServiceProvider.php` con un `singleton()`, y añadirlo a `bootstrap/providers.php`. Los nombres de provider son inconsistentes (`CertificadoProvider` vs `BioimpedanciaServiceProvider`); sigue el existente del dominio que toques.

`CertificadoProvider` es el único que inyecta una dependencia entre servicios (`CertificadoService` recibe `EstadisticasService`).

Si un servicio no está registrado, Laravel igualmente lo resuelve por autowiring cuando su constructor no tiene dependencias — pero deja de ser singleton. Registra siempre el provider.

### Procedimientos almacenados

Gran parte de las estadísticas vive en MySQL, no en PHP. Los modelos exponen wrappers estáticos sobre `DB::select('CALL SP_xxx(?)')`:

- `ChequeoCardiovascular`: `SP_estadistica_IMC`, `SP_estado_general`, `sp_estadistica_presion`, `SP_estadistica_hemoglucotest`, `SP_estadistica_monto`, `SP_pago_mensual`
- `IncidentesDeportivos`: `SP_estadistica_liga`, `SP_estadistica_categoria`, `SP_estadistica_lesiones`, `SP_estadistica_parte_cuerpo`, `SP_estadistica_lesiones_fechas`
- `PagoMensual`: `SP_agenda_mensual`, `SP_estadistica_monto_mdc`, `SP_update_pago_mensual`, `SP_chequeos_prompt`
- `FichaClinica`: `SP_ficha_clinica` (devuelve una columna `resultado_json` que el servicio decodifica)
- `Bioimpedancia`: `SP_bioimpedacia_rut`

Estos SP **no están versionados en el repo** ni en las migraciones. Al cambiar su firma hay que actualizarlos directamente en la base de datos.

### Autenticación y perfiles

Coexisten tres mecanismos: `Auth::attempt()` con sesión (login principal en `UserController::AuthRegister`), Sanctum (solo la ruta `/user` de ejemplo) y JWT (`php-open-source-saver/jwt-auth`, configurado como guard `api` en `config/auth.php`). **Las rutas de negocio en `routes/api.php` no llevan middleware de autenticación.**

Los usuarios viven en dos tablas: `users` (Laravel, password hasheada) y `users_metadata` (datos de negocio: perfil, logo, rut, `ergo_pass`, y una copia en claro de la contraseña). `UserMetadataService::userSave()` y `UserUpdatePassowrd()` escriben en ambas y deben mantenerse sincronizadas.

La autorización es por `perfiles_id`, obtenido con `UserMetadataService::getPerfilIdByEmail()`. El patrón recurrente es `if ($perfilId == 3) { ->where('cc.user_email', $user_email) }`: el perfil 3 (club deportivo) solo ve sus propios registros; los demás ven todo.

### Integración OpenAI

Usa `openai-php/laravel` (facade `OpenAI::chat()`). Los prompts de sistema viven como clases estáticas en `app/IA/` (`AnalisisECGPrompt::system()`, `AsistenteChatPacientePrompt::system($patient, $data)`, `AnalisisBioimpedanciaPrompt`, `AnalisisRutBioimpedanciaPrompt`, `AsistenteVozPrompt`) — mantén los prompts ahí, no incrustados en controladores. Hay varias copias con sufijos `_OLD` y con `()` en el nombre de archivo; ignóralas y no las tomes como referencia.

El chat clínico (`POST api/sam-assistant/as-question`) funciona así: `OpenAIService::resolveSession()` crea/recupera una `ChatSessions` por `sessionId` y extrae el identificador del paciente del prompt (regex de RUT y, si falla, una llamada a `gpt-4o-mini` en `PatientHelper::extractPatient()`). Sin paciente resuelto responde `status: needs_identifier`. Con paciente, `handle()` recupera los últimos 20 mensajes de `chat_history` y `EstadisticasService::ChequeoPrompt()` (→ `SP_chequeos_prompt`) aporta los datos clínicos.

### Generación de documentos

- **PDF con mPDF**: `ChequeoCardiovascularPDFService` y `BioimpedanciaService` construyen HTML como string y lo renderizan. Los assets (`logo.png`, `firmarDoctor.jpg`, `firmarErgo.jpg`, `watermark.png`, `firma_cardiologo.jpeg`) se referencian con `public_path()`.
- **Word con PHPWord**: `ChequeoCardiovascularWordService`.
- **Excel con maatwebsite/excel**: `App\Imports\ChequeoImport` para la carga masiva.

Los archivos subidos van a `public/` (`public/Certificado`, `public/Logo`, `public/Electrocardiograma`, `public/Bioimpedancia`) mediante `$file->move()`, **no** al disco de storage de Laravel. Las URLs públicas se arman con `env('API_PATH_CER')` / `env('API_PATH_LOGO')`.

### Rutas

Todo en `routes/api.php`, plano y sin agrupar, con prefijo automático `/api`. Ojo con el orden: rutas específicas como `chequeo-cardiovascular/pdf/{id}` deben ir antes de `chequeo-cardiovascular/{id_paciente}`. Existe `GET /api/execute-link-simbolik`, que ejecuta `Artisan::call('storage:link')` desde HTTP.

## Trampas conocidas

- **No uses `php artisan config:cache`.** `CertificadoService` y otros leen `env('API_PATH_CER')` / `env('API_PATH_LOGO')` fuera de los archivos de configuración; con la config cacheada esas llamadas devuelven `null` y las URLs de certificados y logos se rompen.
- **`users_metadata` y `electro_cardiogranas` no tienen migración.** Existen solo en la base de datos, igual que los procedimientos almacenados. `php artisan migrate` sobre una base vacía deja el esquema incompleto.
- El nombre real de la tabla de ECG es **`electro_cardiogranas`** (con "n"): es un typo consolidado en producción, respétalo en las queries.
- `api.php` en la raíz del proyecto es una copia obsoleta y sin uso de `routes/api.php`. El archivo real es `routes/api.php`.
- En `app/IA/` hay copias antiguas con sufijo `_OLD` y con paréntesis en el nombre (`AnalisisBioimpedanciaPrompt().php`). No son código activo.

## Despliegue

Push a `main` dispara `.github/workflows/github-action.build.yml`: versionado semántico automático (patrones `major` / `feat` en los mensajes de commit) y build+push de la imagen `nruz176/laravel-back-end-ergosanitas-app` a Docker Hub. En el servidor se usa `dockerHub/docker-compose.yml` con `dockerHub/entrypoint.sh` (limpia cachés, hace `storage:link` y arranca php-fpm). No hay paso de migraciones automático.
