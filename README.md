# Ergosanitas — Backend API

API REST en **Laravel 11 / PHP 8.2** que da soporte a la plataforma de salud deportiva de [Ergosanitas](https://ergosanitas.com). Gestiona chequeos cardiovasculares, electrocardiogramas, bioimpedancia, fichas clínicas, certificados médicos, incidencias deportivas, agendamiento de horas, pagos con WebPay (Transbank) y asistentes clínicos basados en OpenAI.

El proyecto es exclusivamente backend: no hay frontend propio. Todos los recursos se exponen bajo el prefijo `/api`.

---

## Tabla de contenidos

- [Stack](#stack)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Variables de entorno](#variables-de-entorno)
- [Ejecución con Docker](#ejecución-con-docker)
- [Comandos habituales](#comandos-habituales)
- [Arquitectura](#arquitectura)
- [Modelo de datos](#modelo-de-datos)
- [Procedimientos almacenados](#procedimientos-almacenados)
- [Autenticación y perfiles](#autenticación-y-perfiles)
- [Integración con OpenAI](#integración-con-openai)
- [Generación de documentos](#generación-de-documentos)
- [Endpoints](#endpoints)
- [Despliegue](#despliegue)

---

## Stack

| Componente | Versión / Paquete |
|---|---|
| Framework | Laravel 11 |
| PHP | 8.2 |
| Base de datos | MySQL 5.7 |
| Autenticación | Laravel Sanctum 4, `php-open-source-saver/jwt-auth` 2.3, sesión nativa |
| IA | `openai-php/laravel` 0.10 |
| PDF | `mpdf/mpdf` 8.2 |
| Word | `phpoffice/phpword` 1.3 |
| Excel | `maatwebsite/excel` 3.1 |
| Correo | `phpmailer/phpmailer` 6.9 |
| Google OAuth | `google/apiclient` 2.17 |
| Cache / colas | Redis (opcional), driver `database` por defecto |
| Formateo | Laravel Pint |
| Tests | PHPUnit 11 |

---

## Requisitos

- PHP 8.2 con las extensiones `pdo_mysql`, `mbstring`, `zip`, `gd`, `bcmath`, `exif`, `intl`, `sockets`
- Composer 2
- MySQL 5.7 o superior
- Docker y Docker Compose (opcional, para el entorno completo)

---

## Instalación

```bash
git clone <repo>
cd ergosanitas-app

composer install

cp .env.example .env
php artisan key:generate

# Configura las credenciales de base de datos en .env y luego:
php artisan migrate   # ver advertencia en "Modelo de datos"

# Enlace simbólico storage/app/public -> public/storage
php artisan storage:link

php artisan serve
```

La API queda disponible en `http://localhost:8000/api`.

---

## Variables de entorno

Además de las variables estándar de Laravel, el proyecto usa:

| Variable | Descripción |
|---|---|
| `DB_CONNECTION` | `mysql` en todos los entornos (los procedimientos almacenados son específicos de MySQL) |
| `OPENAI_API_KEY` | Clave de la API de OpenAI |
| `OPENAI_ORGANIZATION` | Organización de OpenAI (opcional) |
| `OPENAI_REQUEST_TIMEOUT` | Timeout en segundos de las llamadas a OpenAI (por defecto 120) |
| `WEBPAY_URL` | Endpoint de Transbank WebPay |
| `WEBPAY_RETURN` | URL de retorno tras el pago |
| `WEBPAY_ID` | Código de comercio |
| `WEBPAY_SECRET` | API key de Transbank |
| `GOOGLE_CLIENT_ID` | Client ID de Google OAuth |
| `GOOGLE_CLIENT_SECRET` | Client secret de Google OAuth |
| `JWT_SECRET` | Secreto para firmar los JWT |
| `JWT_ALGO` | Algoritmo de firma JWT |
| `API_PATH_CER` | URL pública base de la carpeta de certificados (ej. `https://ergosanitas.com/BackEnd/public/Certificado`) |
| `API_PATH_LOGO` | URL pública base de la carpeta de logos de clubes |

> `API_PATH_CER` y `API_PATH_LOGO` se leen con `env()` directamente desde los servicios. Si se cachea la configuración con `php artisan config:cache`, estas llamadas devolverán `null`. Evita `config:cache` en este proyecto o migra esos valores a un archivo de configuración.

---

## Ejecución con Docker

El `docker-compose.yml` de la raíz levanta el entorno de desarrollo completo, construyendo la imagen desde el `dockerfile` local:

```bash
docker compose up -d --build
```

| Servicio | Puerto host | Descripción |
|---|---|---|
| `laravel_webserver` | `6162` | Nginx sirviendo `public/` |
| `laravel_app` | — | PHP-FPM 8.2 |
| `db_mysql` | `3337` | MySQL 5.7.22 |
| `php_myadmin` | `8383` | phpMyAdmin |
| `redis` | `7379` | Redis 7.2 |

La aplicación queda en `http://localhost:6162/api`.

El directorio `dockerHub/` contiene el compose de **producción**, que en lugar de construir la imagen consume `nruz176/laravel-back-end-ergosanitas-app:latest` desde Docker Hub y arranca mediante `dockerHub/entrypoint.sh` (limpia cachés, ejecuta `storage:link` y lanza php-fpm).

---

## Comandos habituales

```bash
# Servidor de desarrollo
php artisan serve

# Tests
php artisan test
php artisan test --testsuite=Feature
php artisan test --filter=NombreDelTest
vendor/bin/phpunit tests/Feature/ExampleTest.php

# Formateo de código
vendor/bin/pint            # aplica
vendor/bin/pint --test     # solo verifica

# Migraciones
php artisan migrate
php artisan migrate:status
php artisan migrate:rollback

# Limpiar cachés tras tocar config/, rutas o providers
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Listar rutas registradas
php artisan route:list
```

### Sobre los tests

La suite actual contiene únicamente los stubs generados por Laravel. En `phpunit.xml` las líneas de SQLite en memoria están comentadas, por lo que cualquier test que toque la base de datos usará la conexión definida en `.env`. Antes de escribir tests con base de datos conviene descomentarlas:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Ten presente que los procedimientos almacenados no existen en SQLite, así que todo lo que dependa de ellos debe mockearse.

---

## Arquitectura

### Flujo Controller → Service → Model

Cada dominio funcional sigue el mismo patrón de tres capas:

```
routes/api.php
      │
      ▼
app/Http/Controllers/XController.php     Valida el Request, arma la respuesta JSON,
      │                                   captura excepciones ({success, message, error})
      ▼
app/Services/XService.php                Lógica de negocio, Query Builder, documentos
      │
      ▼
app/Models/X.php                         Eloquent + wrappers de procedimientos almacenados
```

Los servicios se inyectan por constructor en los controladores.

### Registro de servicios

**Cada servicio requiere su propio ServiceProvider registrado manualmente en `bootstrap/providers.php`.** No hay auto-discovery para ellos. Para añadir un dominio nuevo:

1. Crear `app/Services/XService.php`
2. Crear `app/Providers/XServiceProvider.php` registrando un `singleton()`
3. Añadir la clase a `bootstrap/providers.php`

```php
// app/Providers/XServiceProvider.php
public function register(): void
{
    $this->app->singleton(XService::class, fn ($app) => new XService());
}
```

Los nombres de provider son históricamente inconsistentes (`CertificadoProvider` frente a `BioimpedanciaServiceProvider`); al modificar un dominio existente conviene seguir el nombre que ya tiene.

`CertificadoProvider` es el único que inyecta una dependencia entre servicios: `CertificadoService` recibe `EstadisticasService`.

### Convenciones de código

- El código, los comentarios y los mensajes de error están en **español**.
- Los métodos de controlador usan `PascalCase` (`FindByEmail`, `ChequeoPDFRut`, `EstadisticaIMC`), a diferencia de la convención Laravel por defecto.
- Las respuestas de error siguen la forma `{"success": false, "message": "...", "error": "..."}` con el código HTTP correspondiente.

### Directorios propios

| Ruta | Contenido |
|---|---|
| `app/IA/` | Clases estáticas con los prompts de sistema de OpenAI |
| `app/IA/Helpers/` | Utilidades de IA (`PatientHelper::extractPatient()`) |
| `app/Services/` | Capa de lógica de negocio |
| `app/Imports/` | Importadores de Excel (`ChequeoImport`) |
| `app/Mail/` | Mailables (`EmailMailable`) |
| `docker-compose/nginx/` | Configuración de Nginx para los contenedores |
| `dockerHub/` | Compose y entrypoint del despliegue en producción |
| `public/Certificado`, `public/Logo`, `public/Electrocardiograma`, `public/Bioimpedancia` | Archivos subidos por los usuarios |

---

## Modelo de datos

Tablas principales del dominio:

| Tabla | Modelo | Descripción |
|---|---|---|
| `users` | `User` | Usuarios de Laravel (password hasheada) |
| `users_metadata` | `UsersMetadata` | Datos de negocio del usuario: perfil, logo, RUT, `ergo_pass` |
| `perfiles` | `Perfiles` | Catálogo de perfiles / roles |
| `chequeo_cardiovascular` | `ChequeoCardiovascular` | Chequeos cardiovasculares (entidad central) |
| `electro_cardiogranas` | `ElectroCardiograma` | Informes de ECG asociados por RUT y por `id_chequeo` |
| `bioimpedancia` | `Bioimpedancia` | Mediciones de bioimpedancia |
| `certificado_url` | `CertificadoURl` | Certificados médicos en PDF y su URL pública |
| `incidentes_deportivos` | `IncidentesDeportivos` | Lesiones e incidencias deportivas |
| `agenda_horas` | `AgendaHoras` | Reservas de hora |
| `servicios` | `Servicios` | Catálogo de servicios ofrecidos |
| `web_pay_info` | `WebPayInfo` | Transacciones de WebPay |
| `chat_sessions` | `ChatSessions` | Sesiones del asistente clínico (paciente activo por `session_id`) |
| `chat_history` | `ChatHistory` | Historial de mensajes por paciente |
| `logs_api` | `LogsApi` | Registro de llamadas a la API |

Dos advertencias sobre este esquema:

- **`users_metadata` y `electro_cardiogranas` no tienen migración** en `database/migrations/`. Existen solo en la base de datos, igual que los procedimientos almacenados. Una base recién migrada quedará incompleta.
- El nombre de la tabla de electrocardiogramas es `electro_cardiogranas` (con "n"). Es un typo consolidado en producción; respétalo en las queries.

---

## Procedimientos almacenados

Una parte importante de las estadísticas se resuelve en MySQL, no en PHP. Los modelos exponen métodos estáticos que envuelven `DB::select('CALL SP_xxx(?)')`:

| Modelo | Procedimientos |
|---|---|
| `ChequeoCardiovascular` | `SP_estadistica_IMC`, `SP_estado_general`, `sp_estadistica_presion`, `SP_estadistica_hemoglucotest`, `SP_estadistica_monto`, `SP_pago_mensual` |
| `IncidentesDeportivos` | `SP_estadistica_liga`, `SP_estadistica_categoria`, `SP_estadistica_lesiones`, `SP_estadistica_parte_cuerpo`, `SP_estadistica_lesiones_fechas` |
| `PagoMensual` | `SP_agenda_mensual`, `SP_estadistica_monto_mdc`, `SP_update_pago_mensual`, `SP_chequeos_prompt` |
| `FichaClinica` | `SP_ficha_clinica` (devuelve una columna `resultado_json` que el servicio decodifica) |
| `Bioimpedancia` | `SP_bioimpedacia_rut` |

**Estos procedimientos no están versionados en el repositorio ni en las migraciones.** Viven únicamente en la base de datos. Al cambiar su firma o su lógica hay que actualizarlos directamente en MySQL, y una base recién migrada no los tendrá.

---

## Autenticación y perfiles

En el proyecto conviven tres mecanismos de autenticación:

1. **Sesión nativa** — `Auth::attempt()` en `UserController::AuthRegister`. Es el login principal en uso.
2. **Sanctum** — solo protege la ruta de ejemplo `GET /api/user`.
3. **JWT** — `php-open-source-saver/jwt-auth` está configurado como guard `api` en `config/auth.php`.

> **Las rutas de negocio en `routes/api.php` no llevan middleware de autenticación.** El control de acceso se hace dentro de los controladores a partir del `user_email` recibido en la petición.

### Doble tabla de usuarios

Los usuarios se reparten entre dos tablas que deben mantenerse sincronizadas:

- `users` — tabla estándar de Laravel, con la contraseña hasheada mediante `bcrypt`.
- `users_metadata` — datos de negocio: `perfiles_id`, `user_name`, `user_email`, `user_logo`, `rut_paciente`, `ergo_pass`, `status`.

`UserMetadataService::userSave()` y `UserMetadataService::UserUpdatePassowrd()` escriben en ambas tablas en la misma operación.

### Autorización por perfil

La autorización se resuelve consultando `perfiles_id` con `UserMetadataService::getPerfilIdByEmail()`. El patrón recurrente en los servicios es:

```php
$perfilId = $this->userMetadataService->getPerfilIdByEmail($user_email);

if ($perfilId == 3) {
    $query->where('cc.user_email', $user_email);
}
```

El **perfil 3 (club deportivo)** solo puede ver sus propios registros; el resto de perfiles ve la totalidad.

---

## Integración con OpenAI

Se usa `openai-php/laravel` a través de la facade `OpenAI::chat()`.

### Prompts

Los prompts de sistema viven como clases estáticas en `app/IA/` y **no deben incrustarse en los controladores**:

| Clase | Uso |
|---|---|
| `AnalisisECGPrompt::system()` | Interpretación de electrocardiogramas; responde JSON estructurado |
| `AnalisisBioimpedanciaPrompt` | Análisis de una medición de bioimpedancia |
| `AnalisisRutBioimpedanciaPrompt` | Análisis histórico de bioimpedancia por RUT |
| `AsistenteChatPacientePrompt::system($patient, $data)` | Asistente conversacional con contexto clínico del paciente |
| `AsistenteVozPrompt` | Asistente por voz |

Cada prompt debe vivir en **un único archivo**. Duplicar el archivo para conservar una versión anterior provoca que dos archivos declaren la misma clase, lo que genera una colisión en el classmap que produce `composer install --optimize-autoloader` durante el build de producción: cuál de los dos se carga queda indeterminado. Para versionar un prompt, usa git.

### Flujo del asistente clínico

`POST /api/sam-assistant/as-question`:

1. `OpenAIService::resolveSession($sessionId, $prompt)` crea o recupera una fila en `chat_sessions` por `session_id`.
2. Si la sesión aún no tiene paciente asociado, `PatientHelper::extractPatient()` intenta extraerlo: primero con una expresión regular de RUT chileno (`/\d{7,8}-[\dkK]/`) y, si no hay coincidencia, con una llamada a `gpt-4o-mini` que extrae el nombre.
3. Sin paciente resuelto, la respuesta es `{"status": "needs_identifier"}` pidiendo el RUT o el nombre.
4. Con paciente resuelto, `OpenAIService::handle()` persiste el mensaje en `chat_history` y recupera los últimos **20** mensajes de ese paciente como contexto conversacional.
5. `EstadisticasService::ChequeoPrompt($search)` (→ `SP_chequeos_prompt`) aporta los datos clínicos, que se inyectan en el prompt de sistema.

`POST /api/sam-assistant/reset-patient` limpia únicamente `patient_identifier` de la sesión, conservando el historial.

---

## Generación de documentos

| Formato | Librería | Implementación |
|---|---|---|
| PDF | mPDF | `ChequeoCardiovascularPDFService`, `BioimpedanciaService` |
| Word | PHPWord | `ChequeoCardiovascularWordService` |
| Excel (importación) | maatwebsite/excel | `App\Imports\ChequeoImport` |

Los PDF se construyen concatenando HTML como string y renderizándolo con mPDF. Los recursos gráficos se referencian con `public_path()`: `logo.png`, `logoChico.png`, `firmarDoctor.jpg`, `firmarErgo.jpg`, `firma_cardiologo.jpeg`, `watermark.png`.

### Almacenamiento de archivos

Los archivos subidos se mueven con `$file->move()` a subdirectorios de `public/`, **no** al disco de storage de Laravel:

| Directorio | Contenido |
|---|---|
| `public/Certificado` | Certificados médicos, nombrados `{rut}-{id_chequeo}.{ext}` |
| `public/Logo` | Logos de clubes deportivos |
| `public/Electrocardiograma` | Imágenes de ECG para análisis con IA |
| `public/Bioimpedancia` | Informes de bioimpedancia |

Las URL públicas se componen con `env('API_PATH_CER')` y `env('API_PATH_LOGO')`.

---

## Endpoints

Todas las rutas están definidas en `routes/api.php` y llevan el prefijo `/api`.

> El archivo es plano, sin agrupaciones. **El orden importa**: las rutas específicas como `chequeo-cardiovascular/pdf/{id}` deben declararse antes que las genéricas como `chequeo-cardiovascular/{id_paciente}`.

### Autenticación y usuarios

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `auth-login` | Login con Google |
| `POST` | `auth-register` | Login con email y contraseña |
| `POST` | `login/create-user` | Crear usuario |
| `POST` | `auth-register/load-logo` | Subir logo de club |
| `GET` | `auth-register/user_email/{perfil}` | Listar usuarios por perfil |
| `POST` | `user-save` | Guardar usuario y metadata |
| `PUT` | `user-update-password` | Actualizar contraseña |
| `PUT` | `user-update-ergo-pass` | Actualizar `ergo_pass` |
| `POST` | `user-first-ergo-pass` | Consultar `ergo_pass` inicial |
| `GET` | `user` | Usuario autenticado (Sanctum) |

### Chequeo cardiovascular

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `chequeo-cardiovascular/health` | Health check |
| `GET` | `chequeo-cardiovascular` | Listado completo |
| `POST` | `chequeo-cardiovascular/user` | Buscar por email (filtra según perfil) |
| `POST` | `chequeo-cardiovascular` | Crear chequeo |
| `PUT` | `chequeo-cardiovascular/{id_paciente}/{user_email}` | Actualizar chequeo |
| `DELETE` | `chequeo-cardiovascular/{id}` | Eliminar por ID |
| `GET` | `chequeo-cardiovascular/{id_paciente}` | Detalle por ID |
| `GET` | `chequeo-cardiovascular/pdf/{id_paciente}` | PDF por ID |
| `GET` | `chequeo-cardiovascular/pdfRut/{rut_paciente}` | PDF por RUT |
| `GET` | `chequeo-cardiovascular-word/{id_paciente}` | Documento Word |
| `POST` | `chequeo-cardiovascular/filter-calendar` | Filtrar por fecha |
| `POST` | `chequeo-cardiovascular/search-chequeo` | Búsqueda paginada |
| `GET` | `chequeo-cardiovascular/estado-general/{user_email}` | Estado general (`SP_estado_general`) |
| `POST` | `chequeo-cardiovascular/club-deportivo` | Chequeos del club |
| `POST` | `chequeo-cardiovascular/chequeo-all` | Todos los chequeos por email |
| `POST` | `chequeo-cardiovascular/like-chequeo` | Búsqueda parcial |
| `POST` | `chequeo-cardiovascular/like-chequeo/user` | Búsqueda parcial por usuario |

### Electrocardiograma

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `electro-cardiograma/find-by-rut` | Buscar informe por RUT |
| `POST` | `electro-cardiograma/save` | Guardar informe |

### Bioimpedancia

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `bioimpedancia/list-all` | Listado completo |
| `POST` | `bioimpedancia/first-rut` | Última medición por RUT |
| `POST` | `bioimpedancia/create-bio` | Crear medición |
| `POST` | `bioimpedancia/form-upload` | Subir formulario |
| `GET` | `bioimpedancia/pdfRut/{rut_paciente}` | PDF por RUT |

### Ficha clínica

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `ficha-clinica/{rut_paciente}` | Ficha clínica consolidada (`SP_ficha_clinica`) |

### Certificados

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `certificado/save-url` | Subir certificado |
| `POST` | `certificado/path-url` | Obtener ruta del certificado |
| `POST` | `certificado/valida-certificado` | Validar certificado |
| `POST` | `carga-masiva-ecg` | Carga masiva de ECG |
| `GET` | `certificado/validar/{rut_paciente}` | Validar por RUT |
| `GET` | `certificado/{rut_paciente}` | Mostrar certificado |

### Estadísticas y pagos

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `estadisticas/estadistica-imc/{user_email}` | IMC |
| `GET` | `estadisticas/estadistica-presion/{user_email}` | Presión arterial |
| `GET` | `estadisticas/estadistica-hemoglucotest/{user_email}` | Hemoglucotest |
| `GET` | `estadisticas/estadistica-saturacion/{user_email}` | Saturación |
| `GET` | `estadisticas/estadistica-pago-mensual` | Montos mensuales |
| `GET` | `estadisticas/estadistica-pago-mdc` | Montos MDC |
| `POST` | `estadisticas/pago-mensual` | Registrar pago mensual |
| `POST` | `estadisticas/update-pago-mensual` | Actualizar pago mensual |
| `POST` | `estadisticas/delete-pago-mensual` | Eliminar pago mensual |
| `POST` | `estadisticas/agenda-mensual` | Agenda mensual |

### Incidencias deportivas

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `incidencia-deportivos/create` | Registrar incidencia |
| `GET` | `incidencia-deportivos/find-by-user/{user_email}` | Incidencias del usuario |
| `GET` | `incidencia-deportivos/count-club/{user_email}` | Conteo por club |
| `GET` | `incidencia-deportivos/count-gravedad/{user_email}` | Conteo por gravedad |
| `GET` | `incidencia-deportivos/count-liga/{user_email}` | Conteo por liga |
| `GET` | `incidencia-deportivos/lesion-frecuente/{user_email}` | Lesión más frecuente |
| `GET` | `incidencia-deportivos/liga-casos/{user_email}` | Casos por liga |
| `GET` | `incidencia-deportivos/sp_estadistica_liga/{user_email}` | Estadística por liga |
| `GET` | `incidencia-deportivos/sp_estadistica_categoria/{user_email}` | Estadística por categoría |
| `GET` | `incidencia-deportivos/sp_estadistica_lesiones/{user_email}` | Estadística de lesiones |
| `GET` | `incidencia-deportivos/sp_estadistica_parte_cuerpo/{user_email}` | Estadística por parte del cuerpo |
| `GET` | `incidencia-deportivos/sp_estadistica_lesiones_fechas/{user_email}` | Lesiones por fecha |

### Asistentes de IA

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `sam-assistant/as-question` | Chat clínico con contexto de paciente |
| `POST` | `sam-assistant/reset-patient` | Reiniciar el paciente de la sesión |
| `POST` | `GPT/asistente-voz` | Asistente por voz |
| `POST` | `GPT/analisis-ecg` | Análisis de ECG a partir de imagen |

### Agenda, servicios, pagos y utilidades

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `agenda-horas/health` | Health check |
| `GET` | `agenda-horas` | Listar agenda |
| `POST` | `agenda-horas` | Reservar hora |
| `GET` | `servicios` | Listar servicios |
| `GET` | `servicios/{name}` | Detalle de servicio |
| `POST` | `servicios/like` | Búsqueda parcial de servicios |
| `POST` | `transbank/web-pay-request` | Iniciar pago WebPay |
| `GET` | `transbank/web-pay-response` | Callback de WebPay |
| `POST` | `email/reserva-hora` | Enviar correo de reserva |
| `POST` | `file-upload` | Subida genérica de archivos |
| `POST` | `carga-masiva/excel` | Carga masiva desde Excel |
| `GET` | `execute-link-simbolik` | Ejecuta `php artisan storage:link` vía HTTP |

---

## Despliegue

El push a la rama `main` dispara el workflow `.github/workflows/github-action.build.yml`:

1. **Versionado semántico automático** con `PaulHatch/semantic-version`. Los patrones en los mensajes de commit determinan el incremento: `major` sube la versión mayor y `feat` la menor.
2. **Build y push** de la imagen Docker `nruz176/laravel-back-end-ergosanitas-app`, etiquetada con la versión calculada y con `latest`.

En el servidor se despliega con `dockerHub/docker-compose.yml`, que consume la imagen publicada y monta `.env`, `storage` y `bootstrap/cache` como volúmenes. El arranque lo maneja `dockerHub/entrypoint.sh`:

```sh
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan storage:link
exec php-fpm -F
```

**El pipeline no ejecuta migraciones.** Los cambios de esquema y los procedimientos almacenados deben aplicarse manualmente en la base de datos.
