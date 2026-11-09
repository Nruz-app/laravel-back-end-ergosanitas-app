# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Descripción

API REST en Laravel 11 (PHP 8.2) para **Ergosanitas**: gestión de chequeos cardiovasculares deportivos, electrocardiogramas, bioimpedancia, certificados médicos, incidencias deportivas, agendamiento, pagos WebPay y asistentes clínicos con OpenAI. No tiene frontend propio (Vite/Blade están sin uso real salvo la plantilla de correo); todo se expone bajo `/api`.

El código y los comentarios están en español. Los métodos de controlador van en `PascalCase` (`FindByEmail`, `ChequeoPDFRut`), a diferencia de la convención Laravel por defecto.

`README.md` documenta el stack, la instalación y el catálogo de endpoints. `docs/openapi.yaml` es el contrato OpenAPI 3.0.3 con las 119 operaciones detalladas (request, respuestas por código y ejemplos); `docs/index.html` lo muestra con Swagger UI. Este archivo cubre lo que no se deduce leyendo un solo archivo.

La vista gráfica vive en cuatro documentos Mermaid: `docs/arquitectura.md` (componentes, capas, providers, despliegue), `docs/flujos.md` (15 diagramas de secuencia end-to-end), `docs/diagrama-clases.md` (clases UML por dominio) y `docs/modelo-datos.md` (ERD, máquina de estados, autorización por perfil, catálogo de SP). `docs/README.md` los indexa. Ademas, `docs/diagramas/` tiene tres artefactos **explorables** en HTML generados con archify (arquitectura, juego de cartas y ciclo de vida del chequeo): el `.html` es generado, la fuente es su `.json` hermano.

`docs/postman/` es la excepción generada: `Ergosanitas-API.postman_collection.json` (119 requests, 19 carpetas) y sus tres entornos salen de `openapi.yaml` con `php docs/postman/generar-coleccion.php`. No los edites a mano; `php docs/postman/verificar-cobertura.php` contrasta la colección con `route:list`.

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

- `{success, message, data}` / `{success: false, message, error}` — `Auth/UserController`, `Auth/GoogleAuthControlle`, `BioimpedanciaController`, `FichaClinicaController`, `FileUploadController`, `JuegoCartasController`, `HomeImagenController` (con 201 en el alta, y 403/404/422 propios cuyo `error` es `"forbidden"`, `"not_found"` o el array de errores de validación), y en `CertificadoUrlController` y `OpenAIController` solo algunos métodos.
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
- `JuegoCartaClub`: `SP_juego_cartas_club` (una columna `resultado_json` con el array de cartas del club; los umbrales de nivel salen de la tabla `juego_niveles`, así que `JuegoCartasService` recalcula en cada llamada, sin snapshot)
- `ChequeoClubPrompt`: `SP_chequeos_club_prompt` (datos de los chequeos de un club para el asistente por club)
- `Bioimpedancia`: `SP_bioimpedacia_rut`

Estos SP **no están versionados en el repo** ni en las migraciones. Al cambiar su firma hay que actualizarlos directamente en la base de datos.

### Autenticación y perfiles

Coexisten tres mecanismos: `Auth::attempt()` con sesión (login principal en `UserController::AuthRegister`), Sanctum (solo la ruta `/user` de ejemplo) y JWT (`php-open-source-saver/jwt-auth`, configurado como guard `api` en `config/auth.php`). **Las rutas de negocio en `routes/api.php` no llevan middleware de autenticación.** El `user_email` y el `perfiles_id` llegan como parámetros del request, así que el filtrado por perfil descrito arriba es una regla de presentación, no un control de acceso.

Las escrituras de `home-imagenes` (`POST`, los dos `PUT` y `DELETE`) son el único sitio que **rechaza** por perfil: exigen un `user_email` (body o query) cuyo perfil se llame `Administrador` (`HomeImagenService::esAdministrador()`, join `users_metadata` → `perfiles` por `perfiles.nombre`, no por id) y responden 403 si no (decisión D3 de la Spec 03). **Tampoco es autenticación**: el email viaja en el request y quien conozca uno de administrador puede falsificarlo. Queda como deuda hasta que exista autenticación real (Sanctum), que es una spec aparte.

Los usuarios viven en dos tablas: `users` (Laravel, password hasheada) y `users_metadata` (datos de negocio: perfil, logo, rut, `ergo_pass`, y una copia en claro de la contraseña). `UserMetadataService::userSave()` y `UserUpdatePassowrd()` escriben en ambas y deben mantenerse sincronizadas.

`GoogleAuthControlle` usa `google/apiclient` directamente (no Socialite), con `env('GOOGLE_CLIENT_ID')` / `env('GOOGLE_CLIENT_SECRET')` leídos en el propio controlador.

### Configuración de negocio en la tabla `params`

Valores de negocio (no de infraestructura) viven en la tabla `params` y se leen con `Params::where('descripcion', 'CLAVE')->firstOrFail()->valor`. Hoy se usa `VALOR-ECG` en `CertificadoService`, `CertificadoUrlController` y `ElectroCardiogramaController`. Si un valor tarifario parece "hardcodeado y ausente", búscalo ahí antes que en `.env`. Al ser `firstOrFail()`, una fila faltante en `params` tumba el endpoint con un 500.

Ese flujo tiene un efecto cruzado fácil de pasar por alto: al subir un certificado/ECG, `CertificadoService::subirCertificado()` borra el certificado previo del par `(rut, id_chequeo)`, marca el chequeo con `status = 'ECG FOTO'` **y** llama a `EstadisticasService::PagoMensual($periodo, $user_email, $valor_ecg, "ADD")`. Es decir, la carga del documento es lo que factura el mes del club; al tocar la subida de certificados considera siempre el lado de facturación (y que reintentar la subida vuelve a facturar).

### Integración OpenAI

Usa `openai-php/laravel` (facade `OpenAI::chat()`). Los prompts de sistema viven como clases estáticas en `app/IA/` (`AnalisisECGPrompt::system()`, `AsistenteChatPacientePrompt::system($patient, $data)`, `AnalisisBioimpedanciaPrompt`, `AnalisisRutBioimpedanciaPrompt`, `AsistenteVozPrompt`, `AsistenteChatClubPrompt`) — mantén los prompts ahí, no incrustados en controladores.

Cada prompt debe vivir en **un solo archivo**: dos archivos que declaren la misma clase en `app/IA/` colisionan en el classmap que genera `composer install --optimize-autoloader` (lo que hace el `dockerfile` de producción), y cuál gana queda indeterminado. Para versionar un prompt, usa git, no copias del archivo.

Dónde ocurre cada llamada (es inconsistente; sigue el patrón del dominio que toques):

- Chat clínico: `OpenAIService` invocado desde `OpenAIController::AsQuestionUseCase`.
- Asistente por club: `ClubAssistantService` (sesión, datos del SP, historial) + `OpenAI::chat()` en `ClubAssistantController`.
- ECG y asistente de voz: `OpenAI::chat()` directo en `OpenAIController`.
- Bioimpedancia: `OpenAI::chat()` en `BioimpedanciaController::FormUpload` y en `BioimpedanciaService`.

Modelos en uso: `gpt-4o-mini` (extracción de paciente, chat clínico y chat por club) y `gpt-4.1-mini` (ECG, voz, bioimpedancia).

El chat clínico (`POST api/sam-assistant/as-question`) funciona así: `OpenAIService::resolveSession()` crea/recupera una `ChatSessions` por `sessionId` y extrae el identificador del paciente del prompt (regex de RUT y, si falla, una llamada a `gpt-4o-mini` en `PatientHelper::extractPatient()`). Sin paciente resuelto responde `status: needs_identifier`. Con paciente, `handle()` recupera los 20 mensajes más recientes de `chat_history` (`desc` + `reverse`, igual que el asistente por club), `EstadisticasService::ChequeoPrompt()` (→ `SP_chequeos_prompt`) aporta el chequeo y `BioimpedanciaService::SP_bioempdacia_rut()` la **última bioimpedancia**, buscada por el RUT exacto del chequeo encontrado (no por el texto de búsqueda). `AsistenteChatPacientePrompt::system($patient, $data, $bio)` los manda en mensajes de sistema separados, y `recordatorio($bio)` se inserta justo antes de la pregunta actual. `POST api/sam-assistant/reset-patient` suelta el paciente de la sesión.

Un detalle del historial que no se ve en el nombre del método: `chat_history` se agrupa por `patient_identifier` y **no guarda `session_id`**, así que dos sesiones sobre el mismo paciente comparten la conversación. Por eso existe `recordatorio()`: las respuestas viejas del historial (por ejemplo, de cuando el chat no recibía la bioimpedancia) pesaban más que los datos del inicio y el modelo las repetía.

El asistente por club (`POST api/sam-assistant-club/as-question`, body `{email, prompt, sessionId, search?}`) es un dominio aparte a propósito: tablas propias `chat_club_sessions` / `chat_club_history` (esta sí por `session_id`), datos de `SP_chequeos_club_prompt` filtrados por el email del club, y `POST api/sam-assistant-club/reset-search` para soltar el filtro de búsqueda. No lo fusiones con el chat clínico: uno resuelve un paciente por RUT y el otro una institución por email. La spec está en `specs/01-asistente-chat-club.md`.

### Pagos WebPay (Transbank)

`WebPayController` habla con Transbank por HTTP crudo (`Http::withHeaders()`), **sin el SDK de Transbank**: `WEBPAY_URL`, `WEBPAY_ID`, `WEBPAY_SECRET`, `WEBPAY_RETURN` leídos con `env()` en el propio controlador. `WebPayRequest` crea la transacción (`buy_order` = fecha `Ymd` + nombre del servicio, `session_id` = RUT) y `WebPayResponse` la confirma con un `PUT` sobre `token_ws`, que se lee de `$_GET` directamente, no del `Request`.

Es el único lugar del código que persiste errores: el `catch` graba en la tabla `logs_api` vía el modelo `LogsApi` **y no devuelve respuesta**, así que un fallo aquí sale como cuerpo vacío. Si tocas este controlador, ese es el bug de fondo a considerar.

### Carro de servicios `servicio-cart-flow` (Spec 04)

Flujo **paralelo** al de `servicios` / `agenda-horas` / `transbank/*`, que siguen vivos sin cambios (los usa la app móvil y es el rollback). Todo vive bajo `api/cart-flow/*`, tablas `cf_*`, `app/Models/CartFlow/`, `app/Services/CartFlow/` y `app/Http/Controllers/CartFlow/`, con un solo provider (`CartFlowServiceProvider`). La spec es `specs/04-servicio-cart-flow.md`; la lógica de negocio sale de `ERGO_SANITAS_SERVICIOS_Y_CARRO.md` (Parte B) del dueño.

- **El total se calcula solo en el servidor**: `CalculadoraCarroService` es una función pura (no consulta la base). Si cambias una regla de precio, corre `tests/Unit/CartFlow/CalculadoraCarroServiceTest.php`: tiene los 10 ejemplos resueltos de la especificación.
- Los montos de negocio (toma de muestra, ticket mínimo, redondeo, anticipación de agenda…) están en `cf_reglas` (JSON por clave) con valores por defecto en `CalculadoraCarroService::REGLAS_POR_DEFECTO`. No en `params`.
- Catálogo: `php artisan cart-flow:importar-catalogo [--dir=]` hace upsert por SKU y **no pisa `visible_tienda`** de productos existentes. La copia versionada `database/data/cart-flow/*.csv` va **sin** `costo_domilab`, `nota_interna` ni `costo_componente`; para cargar costos se apunta `--dir` a una carpeta privada. `Producto::$hidden` impide que salgan por la API.
- `php artisan db:seed --class=CartFlowSeeder` siembra reglas, bloques de agenda (lunes a sábado, 07:30–10:00, 10:00–13:00 y 15:00–18:00, cupo 6) e importa el catálogo. Es idempotente.
- Nada se borra: quitar paciente o línea del carro es soft delete; un pedido se anula (`estado = anulado`).
- Webpay del carro (`WebPayCartFlowService`) reutiliza `WEBPAY_URL|ID|SECRET` vía `config('services.cart_flow')` y suma dos variables **opcionales**: `WEBPAY_CART_FLOW_RETURN` (si falta, `route('CartFlowWebPayRetorno')`, el mismo host que atiende) y `CART_FLOW_FRONT_URL` (si falta, `http://localhost:5173` con `APP_ENV=local`, si no `https://ergosanitas.com`). Probado de punta a punta contra la integración `webpay3gint` con la tarjeta de prueba VISA (2026-11-05). Cobra `cf_pedidos.total`, `buy_order` único por intento, y solo marca `pagado` con `AUTHORIZED` + `response_code = 0` + monto y `buy_order` iguales.
- La orden médica va al disco privado `local` (`storage/app/private/cart-flow/ordenes`), nunca a `public/`.
- **Tests**: `php artisan test --filter=CartFlow`. `tests/Feature/CartFlow/CartFlowTestCase.php` fuerza SQLite en memoria antes de arrancar la app y migra solo las migraciones `cf_*`, así que no tocan el MySQL remoto aunque `phpunit.xml` lo tenga configurado. El test unitario no arranca Laravel: crea los `Producto` con `Model::unguarded()` porque `fill()` con `$guarded` consulta las columnas en la base. `CartFlowTestCase::crearAdministrador()` crea `perfiles` y `users_metadata` mínimas para probar lo que exige Administrador.
- **Mantenedor del Administrador (Spec 05, `specs/05-mantenedor-carrito-compra.md`)**: `AdminCartFlowController` + `AdminPedidoService`, rutas `cart-flow/admin/*`, todas con `user_email` Administrador. El estado del pedido cambia **solo** por acciones nombradas (`confirmar_pago`, `confirmar`, `atender`, `anular`); `accionesPermitidas()` es la única fuente de qué se puede hacer, y cada cambio escribe en `cf_pedido_historial`. La orden médica se descarga por `URL::temporarySignedRoute` (5 min, middleware `signed`), nunca con `user_email` en la URL.

### Generación de documentos

- **PDF con mPDF**: `ChequeoCardiovascularPDFService` y `BioimpedanciaService` construyen HTML como string y lo renderizan. Los assets (`logo.png`, `firmarDoctor.jpg`, `firmarErgo.jpg`, `watermark.png`, `firma_cardiologo.jpeg`) se referencian con `public_path()`.
- **Word con PHPWord**: `ChequeoCardiovascularWordService`.
- **Excel con maatwebsite/excel**: `App\Imports\ChequeoImport` para la carga masiva.
- **Correo**: `App\Mail\EmailMailable` + `Mail::to()` (el `phpmailer` del `composer.json` no se usa). `EmailController::EmailReservaHora` manda copia fija a `ergosanitas@gmail.com`.

Los archivos subidos van a `public/` (`public/Certificado`, `public/Logo`, `public/Electrocardiograma`, `public/Bioimpedancia`, `public/HomeImagenes`) mediante `$file->move()`, **no** al disco de storage de Laravel. Las URLs públicas se arman con `env('API_PATH_CER')` / `env('API_PATH_LOGO')`. **`public/HomeImagenes` es la excepción**: su URL sale de `config('services.home_imagenes.url')` (← `API_PATH_HOME` en `config/services.php`), no de `env()`, y la tabla `home_imagenes` guarda solo el nombre del archivo; el modelo `HomeImagen` arma `src` al serializar. Las 24 imágenes iniciales están versionadas en `resources/home-imagenes-semilla/` (`public/HomeImagenes/` está en `.gitignore`).

Consecuencia en producción: `dockerHub/docker-compose.yml` monta volúmenes para `storage/` y `bootstrap/cache`, **pero no para `public/`**. Los archivos subidos viven en la capa escribible del contenedor, así que un redeploy de `:latest` los pierde. Tenlo presente antes de proponer que algo nuevo se guarde en `public/`. Ojo: eso vale **solo si se despliega con ese Docker**, y hoy no es así. **Producción corre en el hosting compartido**, en `https://ergosanitas.com/BackEnd/public/` (lo confirman `API_PATH_LOGO`, `API_PATH_CER` y el dueño del proyecto, el 2026-11-03), donde `public/` es una carpeta normal y lo subido persiste mientras el despliegue no la borre. Además, el nginx de `dockerHub/` no monta `/var/www/public`, así que ese Docker ni siquiera sirve archivos estáticos.

**Puesta en marcha de `public/HomeImagenes` en el hosting (paso 8 de la Spec 03, se hace una sola vez):**

1. Crear `BackEnd/public/HomeImagenes/` en el hosting.
2. Subir ahí los 24 archivos de `resources/home-imagenes-semilla/` (los mismos nombres que siembra la migración `2026_11_03_120000_create_home_imagenes_table`).
3. Definir `API_PATH_HOME=https://ergosanitas.com/BackEnd/public/HomeImagenes` en el `.env` del hosting. Sin ella cada `src` sale relativo y el Home comercial queda sin imágenes.
4. Confirmar que el método de despliegue del back **no borra** `public/HomeImagenes/`: si es un `mirror` por FTP, sin `--delete` o excluyendo esa carpeta. Ahí viven las imágenes que sube el Administrador y no están en git (`.gitignore`).
5. Comprobar: `.../HomeImagenes/promo-mes-corazon-01.png` responde 200 y `GET api/home-imagenes` trae `src` con el dominio de producción.

La tabla `home_imagenes` ya existe en la base remota (se migró con `--path` durante la implementación).

### Rutas

Todo en `routes/api.php`, plano y sin agrupar, con prefijo automático `/api`. Ojo con el orden: rutas específicas como `chequeo-cardiovascular/pdf/{id}` deben ir antes de `chequeo-cardiovascular/{id_paciente}`, `juego-cartas/niveles` / `juego-cartas/detalle/{rut}` antes de `juego-cartas/{user_email}` (si no, `niveles` se resuelve como un email), y `home-imagenes/admin` antes de las rutas `home-imagenes/{id}`, que además restringen `{id}` con `whereNumber`. Existe `GET /api/execute-link-simbolik`, que ejecuta `Artisan::call('storage:link')` desde HTTP.

## Agentes, skills y specs (`.claude/`, `specs/`)

- Agentes en `.claude/agents/`: `ergosanitas-developer` (implementa y corrige), `ergosanitas-laravel` (dudas de framework/PHP), `ergosanitas-mysql` (queries, SP, MySQL 5.7) y `ergo-docs` (mantiene `docs/`; documenta, nunca toca código). Cada uno carga su skill homónima de `.claude/skills/`.
- Al cerrar un cambio que toque `routes/`, controladores, servicios, modelos o el esquema, pasa por `ergo-docs` para poner al día el contrato, los diagramas y la colección Postman.
- Las features nuevas se hacen por spec: `/spec` redacta `specs/NN-nombre.md` y `/spec-impl` o `/spec-impl-ergo` la implementan por pasos. Hoy existen `01-asistente-chat-club`, `02-juego-cartas-niveles`, `03-mantenedor-imagenes-home`, `04-servicio-cart-flow` y `05-mantenedor-carrito-compra`; léelas antes de tocar esos dominios.

## Trampas conocidas

- **No uses `php artisan config:cache`.** Varios controladores y servicios leen `env()` fuera de los archivos de configuración: `API_PATH_CER` (`CertificadoService`, `CertificadoUrlController`), `API_PATH_LOGO` (`Auth/UserController`), `WEBPAY_URL|ID|SECRET|RETURN` (`WebPayController`) y `GOOGLE_CLIENT_ID|SECRET` (`GoogleAuthControlle`). Con la config cacheada esas llamadas devuelven `null` y se rompen certificados, logos, pagos y login de Google. Por eso `dockerHub/entrypoint.sh` hace `config:clear` en cada arranque.
- **`.env.example` está incompleto**: define `API_PATH_CER` pero no `API_PATH_LOGO`, que el código sí usa.
- **Tablas sin migración**: `users_metadata`, `params`, `electro_cardiogranas` y `pago_mensual` existen solo en la base de datos, igual que los procedimientos almacenados. `php artisan migrate` sobre una base vacía deja el esquema incompleto.
- El nombre real de la tabla de ECG es **`electro_cardiogranas`** (con "n"): es un typo consolidado en producción, respétalo en las queries.
- Hay **rutas duplicadas** en `routes/api.php`: `GET /user` e `incidencia-deportivos/count-liga/{user_email}`, cada una declarada dos veces (búscalas con `grep -n`; los números de línea se mueven). Laravel se queda con la última declaración; al editar una de ellas asegúrate de tocar la que realmente se resuelve, o elimina la duplicada.
- `api.php` en la raíz del proyecto es una copia obsoleta y sin uso de `routes/api.php`. El archivo real es `routes/api.php`.
- `app/Http/Controllers/bootstrap/` (con `app.php`, `providers.php` y `cache/`) es otra copia versionada y muerta del `bootstrap/` real; nunca se carga. Los archivos que importan son `bootstrap/app.php` y `bootstrap/providers.php`. `src/` está vacío; `base_datos/references/sp/` solo contiene `SP_chequeos_club_prompt.sql` y `SP_juego_cartas_club.sql`, los únicos procedimientos almacenados versionados en el repo (fuera del repo, `Projectos/MySql/SP/` guarda más fuentes `.sql`, sin garantía de que estén al día con la BD).
- El controlador de Google OAuth se llama `GoogleAuthControlle` (sin la "r" final), tanto el archivo como la clase; y `UserController` expone `UserUpdatePassowrd`. Son typos consolidados: no los "corrijas" sin actualizar `routes/api.php` y los clientes.
- `App\Models\FichaClinica` tiene el `$table` comentado a propósito: solo es un envoltorio de `SP_ficha_clinica`, no mapea una tabla.
- No dupliques archivos en `app/IA/` para conservar versiones anteriores de un prompt: declararían la misma clase y colisionarían en el classmap optimizado (ver "Integración OpenAI").

## Despliegue

`.github/workflows/github-action.build.yml` tiene dos jobs:

1. `test`: `composer validate`, `composer install`, `.env` ficticio desde `.env.example` y `php artisan test --filter="CartFlow|ChatComercial"`. Solo esos tests, porque fuerzan SQLite en memoria; el resto tocaría la BD de `.env`. No usa secrets.
2. `build` (`needs: test`): versionado con `PaulHatch/semantic-version@v5` y un único `docker/build-push-action` con los tags `:<version>` y `:latest` de `nruz176/laravel-back-end-ergosanitas-app`. En **push a `main`** hace login y publica en Docker Hub. En un **pull request** a `main` solo construye la imagen, sin publicarla. No hay migraciones en el pipeline.

3. `deploy` (`needs: test`): sube el back por FTP al hosting compartido, en `/public_html/BackEnd/`, con `lftp mirror -R` **sin `--delete`**, así que nunca borra nada del servidor. Excluye **todo `public/`** (datos de pacientes, imágenes, firmas e `index.php`: cambios ahí se suben a mano), `storage/`, los `.htaccess` y `.env*`. No genera `APP_KEY` ni corre migraciones. Corre en dos casos: a mano desde Actions (por defecto `dry_run`, que solo lista lo que subiría) o en push a `main` si la variable del repo `DEPLOY_FTP_ENABLED` vale `true`. Usa los secrets `FTP_USERNAME` / `FTP_PASSWORD` y las variables opcionales `FTP_HOST` y `FTP_REMOTE_DIR`.

El bump sale del **mensaje de commit** (Conventional Commits, con regex que se aplica solo al asunto del commit): `feat:` / `feat(x):` sube minor; `tipo!:` o `BREAKING CHANGE` en el asunto sube major; cualquier otro, patch.

`.dockerignore` deja fuera de la imagen `.env*`, `.git`, `.github`, `.claude`, `docs/`, `specs/`, `tests/`, `vendor/`, lo subido en `public/` y las firmas `*_OLD.*`. Las firmas vigentes y `public/Logo` sí entran porque los PDF los leen con `public_path()`.

`dockerHub/docker-compose.yml` con `dockerHub/entrypoint.sh` (limpia cachés, hace `storage:link` y arranca php-fpm) es el despliegue en contenedor, pero **la producción real corre en el hosting compartido** (`ergosanitas.com/BackEnd`), no en ese Docker: ver «Generación de documentos» para lo que eso implica en `public/`.
