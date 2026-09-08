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
- [Formatos de respuesta](#formatos-de-respuesta)
- [Ciclo de vida del chequeo y facturación](#ciclo-de-vida-del-chequeo-y-facturación)
- [Procedimientos almacenados](#procedimientos-almacenados)
- [Autenticación y perfiles](#autenticación-y-perfiles)
- [Integración con OpenAI](#integración-con-openai)
- [Generación de documentos](#generación-de-documentos)
- [Trampas conocidas](#trampas-conocidas)
- [Diagramas](#diagramas)
- [Contrato OpenAPI (Swagger)](#contrato-openapi-swagger)
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

> **La regla tiene una excepción real en el código: `ChequeoCardiovascularWordService` no tiene provider** y no está en `bootstrap/providers.php`. Laravel lo resuelve igualmente por autowiring —su constructor solo pide `ChequeoCardiovascularPDFService`—, pero **deja de ser singleton**: se construye una instancia nueva en cada request. Es el ejemplo de qué pasa si te saltas el paso 2; no lo tomes como el patrón a seguir.

### Convenciones de código

- El código, los comentarios y los mensajes de error están en **español**.
- Los métodos de controlador usan `PascalCase` (`FindByEmail`, `ChequeoPDFRut`, `EstadisticaIMC`), a diferencia de la convención Laravel por defecto.
- No hay un formato de respuesta único: conviven siete formas incompatibles, a veces dentro del mismo controlador. Ver [Formatos de respuesta](#formatos-de-respuesta).

### Sin middleware ni manejador de excepciones

`bootstrap/app.php` deja vacíos los closures de middleware y de excepciones, y **no existe `app/Http/Middleware/`**. No hay un handler global que convierta las excepciones en respuestas JSON: por eso cada acción envuelve su cuerpo en `try/catch` y arma el sobre a mano. Al escribir un endpoint nuevo hay que hacer lo mismo, o la excepción saldrá como el error HTML de Laravel.

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
| `pago_mensual` | `PagoMensual` | Facturación mensual por club (`club`, `periodo`, monto) |
| `params` | `Params` | Parámetros de negocio clave/valor (hoy solo `VALOR-ECG`) |
| `chat_sessions` | `ChatSessions` | Sesiones del asistente clínico (paciente activo por `session_id`) |
| `chat_history` | `ChatHistory` | Historial de mensajes por paciente |
| `chat_club_sessions` | `ChatClubSessions` | Sesiones del asistente por club (filtro activo por `session_id`) |
| `chat_club_history` | `ChatClubHistory` | Historial de mensajes por sesión de club |
| `logs_api` | `LogsApi` | Errores de WebPay (es el único dominio que persiste errores) |

Tres advertencias sobre este esquema:

- **`users_metadata`, `params`, `electro_cardiogranas` y `pago_mensual` no tienen migración** en `database/migrations/`. Existen solo en la base de datos, igual que casi todos los procedimientos almacenados. Una base recién migrada quedará incompleta.
- El nombre de la tabla de electrocardiogramas es `electro_cardiogranas` (con "n"). Es un typo consolidado en producción; respétalo en las queries.
- `App\Models\FichaClinica` y `App\Models\ChequeoClubPrompt` tienen el `$table` comentado **a propósito**: no mapean ninguna tabla, son solo envoltorios de `SP_ficha_clinica` y `SP_chequeos_club_prompt`.

### El RUT como clave de negocio

El identificador que cruza todos los dominios no es un id numérico, sino el **RUT del paciente**: `chequeo_cardiovascular.rut`, `certificado_url.rut_paciente`, `bioimpedancia.rut`, `electro_cardiogranas.rut_paciente`, `agenda_horas.rut_paciente` y el parámetro de `SP_ficha_clinica`.

- Formato esperado: `12345678-9` — 7 u 8 dígitos, guion y dígito verificador (`0-9`, `k` o `K`), sin puntos.
- `App\Imports\ChequeoImport` lo normaliza con `preg_replace` y lo valida contra `/^\d{7,8}-[0-9kK]$/`, **sin verificar el dígito verificador**. Las filas del Excel con RUT inválido se descartan en silencio: el importador acumula el conteo en `getCantInser()` y el último error en `getErrorMsg()`.
- `BioimpedanciaController` normaliza aparte: quita los puntos y pasa la `K` a minúscula antes de guardar.
- Varias tablas se relacionan por el par `(rut_paciente, id_chequeo)` **sin foreign key**. Cambiar el RUT de un chequeo sin propagarlo deja los certificados y los ECG huérfanos; por eso el perfil 1 dispara `CertificadoService::UpdateRutCertificado()` y `ElectroCardiogramaService::UpdateRutECG()` al editar.

### Configuración de negocio en la tabla `params`

Los valores de negocio —no los de infraestructura— viven en la tabla `params` y se leen así:

```php
$valor = Params::where('descripcion', 'VALOR-ECG')->firstOrFail()->valor;
```

Hoy solo se usa `VALOR-ECG`, en `CertificadoService`, `CertificadoUrlController` y `ElectroCardiogramaController`. Si un valor tarifario parece hardcodeado y ausente, búscalo aquí antes que en `.env`. Al ser `firstOrFail()`, **una fila faltante en `params` tumba el endpoint con un 500**.

---

## Formatos de respuesta

No hay un sobre canónico. Cada controlador usa el suyo y **cambiarlo rompe a los clientes que ya lo consumen**. Al tocar un endpoint, respeta el formato que ya devuelve.

Son **siete formas distintas**, no dos. La mayoría de los controladores mezcla varias según el método y según si la respuesta es de éxito o del `catch`:

| Formato | Forma | Dónde aparece |
|---|---|---|
| **A** — `success` | `{"success": true, "message": "...", "data": {...}}` | `Auth/UserController` (usa `user` en vez de `data` en el login), `Auth/GoogleAuthControlle`, `BioimpedanciaController`, `FichaClinicaController`, `FileUploadController`, `CertificadoUrlController::FileUploadCer` y `::CargaMasivaEcg`, `OpenAIController::AsistenteVoz` y `::AnalisisEcg` |
| **B** — `response` anidado | `{"response": {"status": "OK", "mensaje": "..."}}` | `EmailController`, `ElectroCardiogramaController::Save`, `ChequeoCardiovascularController::Store`; en `AgendaHorasController`, `EstadisticasController`, `GoogleAuthControlle` y `ChequeoCardiovascularController::Update` **solo en el `catch`** |
| **C** — `status` plano | `{"status": ..., "mensaje": "..."}` — `status` a veces es texto (`'OK'`, `'Bad Request'`) y a veces un entero (`200`, `404`, `500`) | `ChequeoCardiovascularController` (`deleteById`, `FilterCalendar`, `LikeChequeo*`, `SearchChequeo`, `Update` en el camino feliz), `CertificadoUrlController` (`ValidarRut`, `PathUrlCertificado`, `ValidaCertificado`, `showCertificado`), `EstadisticasController::deletePagoMensual`, `CargaMasivaController` |
| **D** — `status: 'success'` | `{"status": "success", "message": "...", "data": [...]}` | `IncidenciasController`, los 12 métodos. **Devuelve 201 también en los GET** |
| **E** — payload crudo | el array, la colección o el modelo serializado, sin sobre | `ChequeoCardiovascularController` (`Index`, `FindByEmail`, `ChequeoRut`, `ChequeoUserEmail`, `EstadoGeneral`), `ServiciosController`, `AgendaHorasController::getAgenda`, `Auth/UserController::ListUserEmail`, `ElectroCardiogramaController::FindByRut`, todos los éxitos de `EstadisticasController`, `WebPayController::WebPayRequest` |
| **F** — sobre de chat | `{"sessionId", "patient"\|"club", "search"?, "response", "status"?}` | `OpenAIController::AsQuestionUseCase` y `::resetPatient`, `ClubAssistantController` |
| **G** — no JSON | binario o redirección | los PDF (`application/pdf`), el Word (`streamDownload`), `WebPayResponse` (`redirect()->away`), los `health` (texto plano) |

`OpenAIController` y `GoogleAuthControlle` mezclan varios formatos dentro del mismo archivo. **Al tocar un endpoint, mira qué devuelve hoy** — no asumas el sobre por el controlador.

### Códigos HTTP que no siguen la convención

Conviene conocerlos antes de integrar un cliente:

- Los tres endpoints de consulta de certificados (`certificado/validar/{rut}`, `certificado/path-url`, `certificado/valida-certificado`) responden **siempre HTTP 200**; el resultado real va en un campo `status` dentro del cuerpo.
- `IncidenciasController` devuelve **201 Created también en las lecturas** (GET). Y como evalúa el resultado del servicio con un `if` simple, un conteo de `0` o una lista vacía se traducen en **500 "Error al listar las incidencias"** aunque no haya fallado nada.
- `POST /api/electro-cardiograma/find-by-rut` no propaga el error: si la consulta falla, el `catch` devuelve 200 con un objeto de placeholders (`estado_paciente: "N/A"`, `frecuencia_cardiaca_paciente: 0`).
- `WebPayController` **no devuelve respuesta cuando falla**: graba en `logs_api` y termina con cuerpo vacío.
- Los errores de cliente suelen salir como **500** con el mensaje crudo de la excepción (`$e->getMessage()`), y los controladores de IA añaden `file` y `line`. No expongas esas respuestas al usuario final sin filtrarlas.

---

## Ciclo de vida del chequeo y facturación

### Estados

`chequeo_cardiovascular.status` es texto libre y avanza así:

```
ingresado  ->  Testiado  ->  ECG FOTO  ->  REVISION MEDICA
```

Son **seis los puntos del código PHP que escriben el `status`**:

| Estado | Quién lo escribe | Condición |
|---|---|---|
| `ingresado` | Default de la columna | Alta desde un perfil distinto de 2, y toda la carga masiva (`ChequeoImport` no escribe `status`) |
| `Testiado` | `ChequeoCardiovascularController::Store()` | `perfilId == 2` (tester en terreno); fija además `fecha_atencion` |
| `Testiado` | `ChequeoCardiovascularController::Update()` | `perfilId == 2` |
| *cualquier valor* | `ChequeoCardiovascularController::Update()` | `perfilId == 1`: el administrador escribe el `status` que venga en el request |
| `ECG FOTO` | `CertificadoService::subirCertificado()` | Al subir el certificado |
| `ECG FOTO` | `CertificadoUrlController::FileUploadCer()` | Duplica a mano la lógica del servicio |
| `REVISION MEDICA` | `ElectroCardiogramaController::Save()` | Al guardar la lectura del cardiólogo |

Ninguna transición valida el estado previo: subir un certificado sobre un chequeo en `ingresado` lo salta directo a `ECG FOTO`. El resto de cambios puede venir de `UPDATE` del cliente o de procedimientos almacenados. Ver la [máquina de estados](docs/modelo-datos.md#máquina-de-estados-del-chequeo).

En los listados el `status` se transforma en un campo derivado `estado_paciente`:

- `REVISION MEDICA` con ECG cargado → `Diag. Card. - Normal` / `Diag. Card. - Alterado`
- `REVISION MEDICA` sin ECG → `En Rev. Cardio`
- cualquier otro caso → el `status` tal cual

Para el perfil 3 la búsqueda se ordena con `FIELD(cc.status, 'ECG FOTO', 'REVISION MEDICA', 'Testiado', 'ingresado')`.

### Subir un documento factura el mes del club

Es el efecto cruzado más fácil de pasar por alto del proyecto. `CertificadoService::subirCertificado()` y `ElectroCardiogramaController::Save()` hacen, además de guardar:

1. Borran el registro previo del par `(rut, id_chequeo)` — solo en el caso del certificado.
2. Marcan el chequeo (`ECG FOTO` o `REVISION MEDICA`).
3. Leen `params.VALOR-ECG`.
4. Llaman a `EstadisticasService::PagoMensual($periodo, $club, $valor_ecg, "ADD")` → `SP_pago_mensual`.

Tres consecuencias prácticas:

- **No hay idempotencia: cada subida vuelve a facturar.** Reintentar una carga fallida, o corregir la lectura de un ECG, suma otro cargo al mes del club.
- `POST /api/carga-masiva-ecg` aplica lo mismo **por archivo**: un lote de 200 PDF genera 200 cargos.
- Si falta la fila `VALOR-ECG` en `params`, el `firstOrFail()` responde 500 **después** de haber movido el archivo, guardado la fila y cambiado el estado del chequeo.

Los endpoints que escriben facturación directamente son `POST /api/estadisticas/pago-mensual` y `POST /api/estadisticas/update-pago-mensual`; `POST /api/estadisticas/delete-pago-mensual` la **borra físicamente**, sin confirmación y sin control de acceso.

---

## Procedimientos almacenados

Una parte importante de las estadísticas se resuelve en MySQL, no en PHP. Los modelos exponen métodos estáticos que envuelven `DB::select('CALL SP_xxx(?)')`:

| Modelo | Procedimientos |
|---|---|
| `ChequeoCardiovascular` | `SP_estadistica_IMC`, `SP_estado_general`, `sp_estadistica_presion`, `SP_estadistica_hemoglucotest`, `SP_estadistica_monto`, `SP_pago_mensual` |
| `IncidentesDeportivos` | `SP_estadistica_liga`, `SP_estadistica_categoria`, `SP_estadistica_lesiones`, `SP_estadistica_parte_cuerpo`, `SP_estadistica_lesiones_fechas` |
| `PagoMensual` | `SP_agenda_mensual`, `SP_estadistica_monto_mdc`, `SP_update_pago_mensual`, `SP_chequeos_prompt` |
| `FichaClinica` | `SP_ficha_clinica` (devuelve una columna `resultado_json` que el servicio decodifica) |
| `ChequeoClubPrompt` | `SP_chequeos_club_prompt` |
| `Bioimpedancia` | `SP_bioimpedacia_rut` |

Todos devuelven una única columna —`resultado_json` o `resultado`— que el servicio decodifica antes de responder. Como la forma del JSON la define el procedimiento y no el código PHP, **el contrato de los endpoints de estadística depende del SP instalado en la base**.

**Salvo una excepción, estos procedimientos no están versionados en el repositorio ni en las migraciones.** Viven únicamente en la base de datos: al cambiar su firma o su lógica hay que actualizarlos directamente en MySQL, y una base recién migrada no los tendrá.

La excepción es `base_datos/references/sp/SP_chequeos_club_prompt.sql`, que sí está en el repo como referencia. Es el camino a seguir para los SP nuevos.

### Una cosa que conviene verificar contra la base

- `SP_chequeos_club_prompt` arma la presión como `CONCAT(presionArterial, '/', presion_sistolica)`, y la columna `presionArterial` guarda la **diastólica**: el JSON llega como `"70/130"`, al revés de la convención médica. `ClubAssistantService::normalizarPresion()` invierte los componentes antes de enviarlos al modelo para que no lea una presión normal como una crisis hipertensiva. Si escribes otro consumidor de ese SP, aplica la misma corrección.

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

La autorización se resuelve consultando `perfiles_id` con `UserMetadataService::getPerfilIdByEmail()`, a partir del email que envía el propio cliente. El patrón recurrente en los servicios es:

```php
$perfilId = $this->userMetadataService->getPerfilIdByEmail($user_email);

if ($perfilId == 3) {
    $query->where('cc.user_email', $user_email);
}
```

| Perfil | Rol | Efecto |
|---|---|---|
| 1 | Administrador | Ve todo. En `PUT /api/chequeo-cardiovascular/{id}/{email}` es el único que puede cambiar `rut`, `user_email`, `status` y `fecha_atencion`, y el que propaga el cambio de RUT a `certificado_url` y `electro_cardiogranas`. |
| 2 | Tester en terreno | Al crear o actualizar un chequeo, fija `fecha_atencion = now()` y `status = 'Testiado'`. |
| 3 | Club deportivo | `WHERE cc.user_email = :user_email` — solo ve sus propios registros, e **ignora el filtro `selectClub`**. |
| 5 | Paciente | Perfil que asigna `POST /api/login/create-user` en el autoregistro. |
| 6 | Médico | Solo ve derivados: join con `certificado_url` por `(rut, id_chequeo)` y `cu.derivado_medico = 'SI'`; en la búsqueda, además, `cc.status = 'ECG FOTO'`. |
| otros | — | Ven todo. |

> Este patrón está **duplicado en tres métodos** de `ChequeoCardiovascularService`: `filterCalendar()`, `SearchChequeo()` y `ChequeoEmailAll()`. Si cambia la regla, hay que cambiarla en los tres — pero **hoy las tres copias no son idénticas**:
>
> | Método | Perfil 3 | Perfil 6 | Join con el ECG |
> |---|---|---|---|
> | `filterCalendar()` | sí | **no lo aplica** | por RUT |
> | `SearchChequeo()` | sí, más `orderByRaw(FIELD(...))` | `derivado_medico='SI'` **y** `status='ECG FOTO'` | subquery del último ECG por `id_chequeo` |
> | `ChequeoEmailAll()` | sí | `derivado_medico='SI'`, **sin** filtro de status | subquery `MAX(id) GROUP BY id_chequeo` |
>
> Antes de unificarlas, decide si la divergencia es intencional. Ver el [diagrama de autorización](docs/modelo-datos.md#autorización-por-perfil).

Como las rutas no llevan middleware y el `user_email` lo envía el cliente, este filtrado es una **regla de presentación, no un control de acceso**: cualquiera que conozca la URL puede pedir los datos de otro club cambiando el email del cuerpo.

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
| `AsistenteChatClubPrompt::system($email, $search, $pacientes, $total, $truncado)` | Asistente conversacional sobre los pacientes de un club |
| `AsistenteVozPrompt` | Asistente de digitación por voz |

Los modelos en uso son `gpt-4o-mini` (chat clínico, chat por club, extracción del paciente) y `gpt-4.1-mini` (ECG, voz, bioimpedancia).

Dónde se hace la llamada es **inconsistente**; sigue el patrón del dominio que toques:

| Dominio | Dónde vive la llamada |
|---|---|
| Chat clínico | `OpenAIService`, invocado desde `OpenAIController::AsQuestionUseCase` |
| Chat por club | `ClubAssistantController::AsQuestionClubUseCase` (el service solo prepara los datos) |
| ECG y asistente de voz | `OpenAI::chat()` directo en `OpenAIController` |
| Bioimpedancia | `OpenAI::chat()` en `BioimpedanciaController::FormUpload` y en `BioimpedanciaService` |

### Contratos de salida de los prompts

Tres prompts devuelven JSON cuyas claves **son contrato con el cliente y con la base de datos**. No las renombres ni cambies su orden sin coordinar:

- `AsistenteVozPrompt` — 26 campos, siempre presentes aunque vayan vacíos. Sus nombres coinciden con las columnas de `chequeo_cardiovascular` y `electro_cardiogranas`, de modo que la respuesta se puede reenviar casi tal cual a `POST /api/chequeo-cardiovascular`. Los campos no dictados van a `""`; la excepción es `frecuencia_cardiaca_paciente`, que va a `null`.
- `AnalisisECGPrompt` — 20 campos de texto. Aplica los Criterios Internacionales de ECG en deportistas (2017) y el patrón juvenil pediátrico, porque la población es mayoritariamente menor de 18 años: bradicardia sinusal, repolarización precoz o inversión de onda T en V1-V3 en menores de 16 se informan como **normales** para esta población. Es un apoyo al tamizaje, no un informe final, y su resultado **no se guarda**: la lectura oficial se registra con `POST /api/electro-cardiograma/save`.
- `AnalisisBioimpedanciaPrompt` — sus claves corresponden una a una con las columnas de la tabla `bioimpedancia` (ver `BioimpedanciaService::mapBioimpedancia()`).

Cada prompt debe vivir en **un único archivo**. Duplicar el archivo para conservar una versión anterior provoca que dos archivos declaren la misma clase, lo que genera una colisión en el classmap que produce `composer install --optimize-autoloader` durante el build de producción: cuál de los dos se carga queda indeterminado. Para versionar un prompt, usa git.

### Flujo del asistente clínico

`POST /api/sam-assistant/as-question`:

1. `OpenAIService::resolveSession($sessionId, $prompt)` crea o recupera una fila en `chat_sessions` por `session_id`.
2. Si la sesión aún no tiene paciente asociado, `PatientHelper::extractPatient()` intenta extraerlo: primero con una expresión regular de RUT chileno (`/\d{7,8}-[\dkK]/`) y, si no hay coincidencia, con una llamada a `gpt-4o-mini` que extrae el nombre.
3. Sin paciente resuelto, la respuesta es `{"status": "needs_identifier"}` pidiendo el RUT o el nombre.
4. Con paciente resuelto, `OpenAIService::handle()` persiste el mensaje en `chat_history` y recupera **20** mensajes de ese paciente como contexto conversacional.

   > ⚠️ La consulta es `orderBy('created_at', 'asc')->limit(20)`, así que devuelve los **20 mensajes más antiguos**, no los más recientes: a partir del mensaje 21 el contexto queda congelado en el inicio de la conversación. El asistente por club no tiene este problema (usa `desc` y luego invierte).
5. `EstadisticasService::ChequeoPrompt($search)` (→ `SP_chequeos_prompt`) aporta los datos clínicos, que se inyectan en el prompt de sistema.

`POST /api/sam-assistant/reset-patient` limpia únicamente `patient_identifier` de la sesión, conservando el historial.

> El historial se guarda **por paciente**, no por sesión: dos sesiones distintas que hablen del mismo paciente comparten la conversación.

### Flujo del asistente por club

`POST /api/sam-assistant-club/as-question` es un flujo paralelo al anterior, construido sin tocarlo. En vez de girar en torno a un paciente, responde sobre **el conjunto de pacientes en `REVISION MEDICA` de un club**.

1. `ClubAssistantService::resolveSession($sessionId, $email, $search)` crea o recupera la fila de `chat_club_sessions`. `club_email` es `NOT NULL` sin default y la base corre en `STRICT_TRANS_TABLES`, así que se pasa en la creación.
2. `datosClub()` ejecuta `SP_chequeos_club_prompt($search, $club)`, corrige el orden de la presión arterial y **trunca en 120 pacientes** (`ClubAssistantService::MAX_PACIENTES`). El prompt recibe el total real y una marca de truncado para que el asistente avise cuando está viendo solo una parte.
3. Sin pacientes que devolver, la respuesta es `{"status": "sin_datos"}` con HTTP 200.
4. `handle()` persiste el mensaje en `chat_club_history` y recupera los **20 mensajes más recientes de la sesión**, desempatando por `id` cuando comparten `created_at`.

La semántica del campo `search` es el detalle importante para el cliente, porque se **persiste en la sesión**:

| Cómo se envía | Efecto |
|---|---|
| El campo no viene en el JSON | Se conserva el `search_actual` que ya tenía la sesión |
| `"search": ""` | Reset explícito: el chat pasa a hablar de todo el club |
| `"search": "Fuentes"` | Se guarda ese filtro y el chat queda acotado a ese paciente |

`POST /api/sam-assistant-club/reset-search` equivale a enviar `"search": ""`. Como el asistente de paciente, no borra el historial.

Es además el **único controlador que captura `ValidationException` por separado** y responde un 422 correcto; en el resto, la excepción de validación cae en el `catch (\Exception)` genérico y sale como 500.

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

> **En producción estos archivos no sobreviven a un redeploy.** `dockerHub/docker-compose.yml` monta volúmenes para `storage/` y `bootstrap/cache`, **pero no para `public/`**: lo subido vive en la capa escribible del contenedor y se pierde al desplegar `:latest`. Tenlo presente antes de proponer que algo nuevo se guarde en `public/`.

Además, la validación de estas subidas es desigual: `POST /api/carga-masiva/excel` y `POST /api/GPT/analisis-ecg` validan tipo y tamaño, mientras que `POST /api/certificado/save-url` y `POST /api/auth-register/load-logo` **no validan nada**, y si no llega un archivo válido no retornan nada (respuesta 200 con cuerpo vacío).

---

## Trampas conocidas

Comportamientos del proyecto que no se deducen leyendo un solo archivo y que rompen cosas si se ignoran:

- **No ejecutes `php artisan config:cache`.** Varios controladores y servicios leen `env()` fuera de `config/`: `API_PATH_CER` (`CertificadoService`, `CertificadoUrlController`), `API_PATH_LOGO` (`Auth/UserController`), `WEBPAY_URL|ID|SECRET|RETURN` (`WebPayController`) y `GOOGLE_CLIENT_ID|SECRET` (`GoogleAuthControlle`). Con la config cacheada devuelven `null` y se rompen certificados, logos, pagos y login de Google. Por eso `dockerHub/entrypoint.sh` hace `config:clear` en cada arranque.
- **`.env.example` está incompleto**: define `API_PATH_CER` pero no `API_PATH_LOGO`, que el código sí usa.
- **El orden de `routes/api.php` importa.** Las rutas específicas (`chequeo-cardiovascular/pdf/{id}`) deben declararse antes que las genéricas (`chequeo-cardiovascular/{id_paciente}`).
- **Hay rutas duplicadas**: `/user` (líneas 7 y 44) e `incidencia-deportivos/count-liga` (líneas 267 y 270). Laravel se queda con la última declaración; al editar una, asegúrate de tocar la que realmente se resuelve.
- **Typos consolidados que no se corrigen sin actualizar rutas y clientes**: el controlador de Google se llama `GoogleAuthControlle` (sin la "r" final), `UserController` expone `UserUpdatePassowrd`, la tabla de ECG es `electro_cardiogranas` y la columna es `gradoIncidenciaPosterio`.
- **Archivos muertos versionados**: `api.php` en la raíz es una copia obsoleta de `routes/api.php`, y `app/Http/Controllers/bootstrap/` es una copia muerta del `bootstrap/` real que nunca se carga. Los archivos que importan son `routes/api.php`, `bootstrap/app.php` y `bootstrap/providers.php`.
- **`vendor/bin/pint` sin argumentos reformatea medio repositorio.** No hay `pint.json`, así que aplica el preset `laravel`, que el código existente no cumple. Usa siempre `--dirty` o rutas explícitas.
- **`PUT /api/chequeo-cardiovascular/{id}/{email}` borra datos clínicos si el formulario llega incompleto**: los campos vacíos vuelven a sus valores por defecto (`No Presenta` / `Sin Alteraciones`) aunque antes tuvieran contenido.
- **`POST /api/electro-cardiograma/save` borra todos los ECG previos** del chequeo antes de insertar: solo se conserva la última lectura, no hay historial.
- **`DELETE /api/chequeo-cardiovascular/{id}` no borra en cascada** los certificados ni los ECG asociados, y no revierte la facturación que ese chequeo ya generó.
- **Ningún flujo de escritura múltiple usa transacción.** Subir un certificado mueve el archivo, inserta la fila, cambia el `status` y factura en cuatro pasos independientes: si uno falla, los anteriores quedan aplicados.
- **Métodos públicos sin ruta**: `ChequeoCardiovascularController::DeleteRut()` (su ruta `DELETE chequeo-cardiovascular/{rut}` está comentada) y `FileUploadController::FileUploadStorage()`.
- **Dependencias declaradas que no se usan**: `CargaMasivaController` inyecta `ChequeoCardiovascularService` y nunca lo llama; `OpenAIController::AsQuestionUseCase()` recibe `OpenAIService` dos veces (por constructor y por parámetro) y usa el del método.

---

## Diagramas

La carpeta [`docs/`](docs/) contiene la vista gráfica del sistema, en diagramas Mermaid que se renderizan solos en GitHub y en VS Code:

| Documento | Qué muestra |
|---|---|
| [`docs/arquitectura.md`](docs/arquitectura.md) | Componentes, las tres capas y sus excepciones, inyección de dependencias y despliegue |
| [`docs/flujos.md`](docs/flujos.md) | 13 diagramas de secuencia, uno por flujo end-to-end (carga masiva, facturación, asistentes, WebPay…) |
| [`docs/diagrama-clases.md`](docs/diagrama-clases.md) | Clases UML por dominio: controladores, servicios, modelos y prompts, con sus firmas reales |
| [`docs/modelo-datos.md`](docs/modelo-datos.md) | ERD de las 18 tablas, máquina de estados del chequeo, autorización por perfil y catálogo de procedimientos almacenados |

[`docs/README.md`](docs/README.md) es el índice de la carpeta y explica cuándo hay que actualizar cada documento. Como el contrato OpenAPI, **los diagramas se mantienen a mano**: al cambiar un flujo, actualízalos en el mismo commit.

---

## Contrato OpenAPI (Swagger)

El contrato completo de la API vive en **[`docs/openapi.yaml`](docs/openapi.yaml)** (OpenAPI 3.0.3): las 84 operaciones de `routes/api.php`, con esquemas, parámetros, ejemplos de request y de respuesta para cada código de estado, y las particularidades reales del proyecto (los dos formatos de sobre incompatibles, los endpoints que facturan, los que devuelven 200 en caso de error, el filtrado por perfil).

Las tablas de la sección [Endpoints](#endpoints) son el índice rápido; el YAML es la referencia detallada.

### Ver la documentación en el navegador

`docs/index.html` trae un visor Swagger UI ya configurado. Sirve la carpeta por HTTP (el navegador no carga el YAML desde `file://`):

```bash
php -S localhost:8080 -t docs
# y abre http://localhost:8080
```

### Validar el contrato

```bash
# Parseo del YAML con el componente que ya trae el proyecto
php -r 'require "vendor/autoload.php"; Symfony\Component\Yaml\Yaml::parseFile("docs/openapi.yaml"); echo "OK\n";'

# Validación estructural OpenAPI (requiere Node)
npx @redocly/cli lint
```

`redocly.yaml`, en la raíz, apunta al contrato y deja documentado qué reglas de estilo están desactivadas y por qué (por ejemplo `operation-4xx-response`: esta API devuelve 500 donde otra devolvería un 4xx, así que exigir respuestas 4xx obligaría a documentar códigos que nunca se emiten). Hoy el contrato pasa el lint **sin errores ni advertencias**.

### Mantenerlo al día

`docs/openapi.yaml` se escribe a mano: **no hay generación automática desde el código**, ni anotaciones de L5-Swagger en los controladores. Al agregar o cambiar una ruta en `routes/api.php` hay que actualizar el YAML en el mismo commit. Para comprobar que no falta ninguna:

```bash
php artisan route:list --json
```

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

### Juego de cartas

Evaluación gamificada por club: cada paciente es una carta con cuatro atributos
comparables 0-100, puntaje, estrellas, badge clínico y barra de completitud. Todo lo
calcula `SP_juego_cartas_club` al vuelo; nada se persiste.

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `juego-cartas/niveles` | Bandas de `juego_niveles` y catálogo de `juego_atributos` |
| `GET` | `juego-cartas/detalle/{rut_paciente}` | Una carta por RUT, sin filtro de club |
| `GET` | `juego-cartas/{user_email}` | Todas las cartas del club, con `?search=` opcional |

**El orden de declaración es obligatorio**: las rutas literales van antes de la que
captura `{user_email}`, o `juego-cartas/niveles` se resolvería con `user_email = "niveles"`.

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
| `POST` | `sam-assistant-club/as-question` | Chat sobre los pacientes de un club |
| `POST` | `sam-assistant-club/reset-search` | Reiniciar el filtro de paciente de la sesión |
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
