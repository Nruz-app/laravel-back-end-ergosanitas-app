# Diagrama de clases

Clases reales del proyecto por dominio, con sus firmas tal como están en el código —typos consolidados incluidos—. Cada diagrama se mantiene por debajo de ~20 clases para que siga siendo legible.

- [Chequeo cardiovascular](#chequeo-cardiovascular)
- [Certificados y electrocardiogramas](#certificados-y-electrocardiogramas)
- [Asistentes de IA](#asistentes-de-ia)
- [Bioimpedancia](#bioimpedancia)
- [Descarga de certificados por RUT](#descarga-de-certificados-por-rut)
- [Incidencias y ficha clínica](#incidencias-y-ficha-clínica)
- [Usuarios y perfiles](#usuarios-y-perfiles)
- [Agenda, servicios y pagos](#agenda-servicios-y-pagos)
- [Juego de cartas](#juego-de-cartas)
- [Home comercial](#home-comercial)
- [Convenciones y anomalías](#convenciones-y-anomalías)

---

## Chequeo cardiovascular

El dominio central. `ChequeoCardiovascularController` es el que más servicios compone: cinco.

```mermaid
classDiagram
    class ChequeoCardiovascularController {
        +HealthCheck()
        +Index()
        +FindByEmail(Request)
        +LikeChequeo(Request)
        +LikeChequeoUser(Request)
        +ChequeoRut(int id_paciente)
        +deleteById(string id)
        +Store(Request)
        +Update(Request, int id_paciente, string user_email)
        +FilterCalendar(Request)
        +EstadoGeneral(Request)
        +ChequeoPDFRut(string rut_paciente)
        +ChequeoUserEmail(Request)
        +ChequeoPDF(int id_paciente)
        +DeleteRut(string rut)
        +SearchChequeo(Request)
        +ChequeoEmailAll(Request)
    }

    class ChequeoCardiovascularService {
        +filterCalendar(perfilId, fecha_calendar, user_email) array
        +SearchChequeo(perfilId, textoValue, fecha_calendar, selectClub, user_email, limit, page) JsonResponse
        +ChequeoEmailAll(user_email, perfilId) Collection
        +chequeoUserEmail(string user_email) array
    }

    class ChequeoCardiovascularPDFService {
        +getPercentil(imc, edad, sexo)
        +chequeoPDf(int id_paciente)
    }

    class ChequeoCardiovascularWordService {
        +chequeoWord(int id_paciente)
    }

    class ChequeoCardiovascularWordController {
        +ChequeoWord(int id_paciente)
    }

    class CargaMasivaController {
        +CargaMasivaExcel(Request)
    }

    class ChequeoImport {
        <<ToModel, WithHeadingRow>>
        +model(array row)
        +getCantInser() int
        +getErrorMsg() string
        -formatearRut(rut)
        -validarRut(rut)
        -calculateAge(fechaNacimiento)
        -formatearSexo(sexo)
        -formatearFecha(fecha)
    }

    class ChequeoCardiovascular {
        <<Model>>
        table chequeo_cardiovascular
        +SP_estadistica_IMC(param1)$
        +SP_estado_general(param1)$
        +sp_estadistica_presion(param1)$
        +SP_estadistica_hemoglucotest(param1)$
        +SP_estadistica_saturacion(param1)$
        +SP_estadistica_monto()$
        +PagoMensual(p1, p2, p3, p4)$
    }

    ChequeoCardiovascularController *-- ChequeoCardiovascularService
    ChequeoCardiovascularController *-- ChequeoCardiovascularPDFService
    ChequeoCardiovascularController *-- UserMetadataService
    ChequeoCardiovascularController *-- CertificadoService
    ChequeoCardiovascularController *-- ElectroCardiogramaService
    ChequeoCardiovascularWordController *-- ChequeoCardiovascularWordService
    ChequeoCardiovascularWordService *-- ChequeoCardiovascularPDFService
    CargaMasivaController ..> ChequeoImport : usa
    CargaMasivaController ..> ChequeoCardiovascularService : inyectado, nunca usado
    ChequeoCardiovascularController --> ChequeoCardiovascular
    ChequeoImport --> ChequeoCardiovascular
```

`ChequeoCardiovascularService` **no tiene constructor**: trabaja con el Query Builder directamente (`DB::table('chequeo_cardiovascular as cc')`) e importa `ChequeoCardiovascular` sin usarlo.

> `DeleteRut()` es un método público **sin ruta**: la línea `DELETE chequeo-cardiovascular/{rut}` está comentada en `routes/api.php`.

---

## Certificados y electrocardiogramas

La única composición servicio→servicio registrada en un provider vive aquí: `CertificadoService` recibe `EstadisticasService`, y por eso subir un certificado factura.

```mermaid
classDiagram
    class CertificadoUrlController {
        +ValidarRut(string rut_paciente)
        +showCertificado(string rut_paciente)
        +FileUploadCer(Request)
        +PathUrlCertificado(Request)
        +ValidaCertificado(Request)
        +CargaMasivaEcg(Request)
    }

    class CertificadoService {
        +UpdateRutCertificado(id_chequeo, rut_paciente)
        +subirCertificado(file, string rut_paciente, int id_paciente, derivado_medico) array
    }

    class ElectroCardiogramaController {
        +FindByRut(Request)
        +Save(Request)
    }

    class ElectroCardiogramaService {
        +UpdateRutECG(id_chequeo, rut_paciente)
    }

    class FileUploadController {
        +FileUploadRes(Request)
        +FileUploadStorage(Request)
    }

    class EstadisticasService {
        +PagoMensual(periodo, user_email, valor_ecg, modo)
        +EstadisticaPagoMensual()
        +ChequeoPrompt(search)
    }

    class CertificadoURl {
        <<Model>>
        table certificado_url
    }

    class ElectroCardiograma {
        <<Model>>
        table electro_cardiogranas
    }

    class Params {
        <<Model>>
        table params
    }

    CertificadoUrlController *-- CertificadoService
    CertificadoUrlController *-- EstadisticasService
    ElectroCardiogramaController *-- EstadisticasService
    CertificadoService *-- EstadisticasService
    CertificadoService --> CertificadoURl
    CertificadoService --> Params
    ElectroCardiogramaService --> ElectroCardiograma
    ElectroCardiogramaController --> ElectroCardiograma
    ElectroCardiogramaController --> Params
    FileUploadController --> ElectroCardiograma
```

> ⚠️ `CertificadoUrlController::FileUploadCer()` **no llama a `CertificadoService::subirCertificado()`**: reimplementa a mano los mismos siete pasos (borrar, mover, insertar, marcar `ECG FOTO`, leer `VALOR-ECG`, facturar). Al cambiar la regla hay que tocar los dos sitios.
>
> `FileUploadController::FileUploadStorage()` es otro método público **sin ruta**.

---

## Asistentes de IA

Los prompts son clases estáticas sin estado en `app/PromptSystem/`: no se inyectan y no tienen constructor, solo producen strings o arrays de mensajes.

```mermaid
classDiagram
    class OpenAIController {
        +resetPatient(Request)
        +AsQuestionUseCase(Request, OpenAIService)
        +AsistenteVoz(Request)
        +AnalisisEcg(Request)
    }

    class ClubAssistantController {
        +AsQuestionClubUseCase(Request)
        +ResetSearch(Request)
    }

    class ChatComercialController {
        +AsQuestionUseCase(Request)
    }

    class OpenAIService {
        +resolveSession(string sessionId, string prompt) ChatSessions
        +handle(ChatSessions session, string prompt) array
    }

    class ClubAssistantService {
        +MAX_PACIENTES = 120
        +resolveSession(sessionId, clubEmail, search) ChatClubSessions
        +datosClub(search, clubEmail) array
        +handle(ChatClubSessions session, string prompt) array
        +guardarRespuesta(session, texto, data) void
        -normalizarPresion(array paciente) array
    }

    class ChatComercialService {
        +HISTORIAL_MAX = 10
        +MAX_CARACTERES_CONTEXTO = 40000
        +CACHE_SEGUNDOS = 600
        +CLAVE_CACHE
        +MARCADOR_DERIVAR
        +contexto() array
        +enmascarar(string texto) string
        +resolverSesion(sessionId) ChatComercialSessions
        +historial(ChatComercialSessions session) array
        +separarDerivacion(string texto, array contacto) array
        +guardarTurno(session, pregunta, respuesta, derivar, contextoHash, tokens...) void
        -productos() Collection
        -serializar(productos, int largoPreparacion) string
        -limpiar(string texto) string
    }

    class PatientHelper {
        <<static>>
        +extractPatient(string prompt) string
        -promptSistema() string
    }

    class AsistenteChatPacientePrompt {
        <<static>>
        +system(string patient, array data) array
    }

    class AsistenteChatClubPrompt {
        <<static>>
        +system(club, search, pacientes, total, truncado) array
    }

    class AsistenteChatComercialPrompt {
        <<static>>
        +system(array contexto, array contacto) string
        -rolYReglas(array contacto) string
        -diccionarioDeDatos() string
    }

    class AnalisisECGPrompt {
        <<static>>
        +system() string
    }

    class AsistenteVozPrompt {
        <<static>>
        +system() string
    }

    class ChatSessions {
        <<Model>>
        table chat_sessions
        session_id, patient_identifier
    }
    class ChatHistory {
        <<Model>>
        table chat_history
        patient_identifier, role, message, context_json
    }
    class ChatClubSessions {
        <<Model>>
        table chat_club_sessions
        session_id, club_email, search_actual
    }
    class ChatClubHistory {
        <<Model>>
        table chat_club_history
        session_id, club_email, role, message, context_json
    }
    class ChequeoClubPrompt {
        <<Model sin tabla>>
        +SP_chequeos_club_prompt(search, club)$
    }
    class ChatComercialSessions {
        <<Model>>
        table chat_comercial_sessions
        session_id, mensajes, ultimo_mensaje_at
    }
    class ChatComercialHistory {
        <<Model>>
        table chat_comercial_history
        session_id, role, message, derivar, contexto_hash
        tokens_prompt, tokens_cacheados, tokens_respuesta
    }

    OpenAIController *-- OpenAIService
    OpenAIController *-- EstadisticasService
    OpenAIController ..> AsistenteChatPacientePrompt
    OpenAIController ..> AnalisisECGPrompt
    OpenAIController ..> AsistenteVozPrompt
    OpenAIService ..> PatientHelper
    OpenAIService --> ChatSessions
    OpenAIService --> ChatHistory
    ClubAssistantController *-- ClubAssistantService
    ClubAssistantController ..> AsistenteChatClubPrompt
    ClubAssistantService --> ChatClubSessions
    ClubAssistantService --> ChatClubHistory
    ClubAssistantService --> ChequeoClubPrompt
    ChatComercialController *-- ChatComercialService
    ChatComercialController ..> AsistenteChatComercialPrompt
    ChatComercialService --> ChatComercialSessions
    ChatComercialService --> ChatComercialHistory
    ChatComercialService ..> CatalogoService : comunas y reglas
```

### Qué modelo de OpenAI usa cada prompt

| Prompt | Modelo | Dónde se invoca |
|---|---|---|
| `PatientHelper` (extracción de paciente) | `gpt-4o-mini`, `temperature: 0` | `OpenAIService::resolveSession()` |
| `AsistenteChatPacientePrompt` | `gpt-4o-mini`, `temperature: 0.2` | `OpenAIController::AsQuestionUseCase()` |
| `AsistenteChatClubPrompt` | `gpt-4o-mini`, `temperature: 0.2` | `ClubAssistantController::AsQuestionClubUseCase()` |
| `AsistenteChatComercialPrompt` | `gpt-4o-mini`, `temperature: 0.3`, `max_tokens: 400` | `ChatComercialController::AsQuestionUseCase()` |
| `AnalisisECGPrompt` | `gpt-4.1-mini` (visión, 2000 tokens) | `OpenAIController::AnalisisEcg()` |
| `AsistenteVozPrompt` | `gpt-4.1-mini`, `temperature: 0.1` | `OpenAIController::AsistenteVoz()` |
| `AnalisisBioimpedanciaPrompt` | `gpt-4.1-mini` (visión) | `BioimpedanciaController::FormUpload()` |
| `AnalisisRutBioimpedanciaPrompt` | `gpt-4.1-mini` | `BioimpedanciaService::analizarConIA()` |

**Dónde ocurre cada llamada es inconsistente** —el chat clínico pasa por el servicio, el del club y el comercial llaman a `OpenAI::chat()` en el controlador con el servicio solo para sesión, datos e historial, el ECG y la voz llaman a `OpenAI::chat()` directo en el controlador, y bioimpedancia lo hace en ambos sitios—. Sigue el patrón del dominio que toques.

> ⚠️ Cada prompt debe vivir en **un solo archivo**. Dos archivos que declaren la misma clase en `app/PromptSystem/` colisionan en el classmap que genera `composer install --optimize-autoloader` (lo que hace el `dockerfile` de producción) y cuál gana queda indeterminado. Para versionar un prompt, usa git, no copias del archivo.
>
> ⚠️ `OpenAIController::AsQuestionUseCase()` recibe `OpenAIService` **dos veces**: por constructor y como parámetro del método. Usa el del método, así que la propiedad del constructor queda muerta.

El chat comercial «Ergosanito» (Spec 06) es el tercer chat y un dominio aparte, sin relación con los otros dos:

- **Solo lee catálogo público**: `ChatComercialService::contexto()` arma el texto desde `Producto` (visibles, lista blanca de columnas), `CatalogoService::comunas()` / `reglas()` y `OperativoTramo`, y lo guarda en caché 600 s con la clave `chat-comercial:contexto`. No toca tablas clínicas ni SP.
- **Memoria por `session_id`**, no por IP ni por paciente: `historial()` devuelve los 10 mensajes más recientes de `chat_comercial_history`.
- `enmascarar()` cambia correo, teléfono y RUT del visitante por `[EMAIL]`, `[TELEFONO]` y `[RUT]` antes de guardar y de enviar a OpenAI.
- `separarDerivacion()` quita el marcador `[DERIVAR]` y devuelve `derivar: true`; también si el texto trae el teléfono de `config('services.chat_comercial.contacto')`.
- `tokens_cacheados` se guarda siempre `null`: `openai-php/client` v0.10.1 no expone `prompt_tokens_details.cached_tokens`.
- Su `catch` genérico **no expone `file` ni `line`** (los otros dos chats sí): el detalle va a `Log::error`.

---

## Bioimpedancia

`BioimpedanciaService` es el archivo más grande del proyecto (~980 líneas) y concentra extracción con IA, mapeo, consulta al procedimiento almacenado y generación del informe.

```mermaid
classDiagram
    class BioimpedanciaController {
        +ListBio()
        +FirstRut(Request)
        +CreateBio(Request)
        +FormUpload(Request)
        +BioPDFRut(string rut_paciente)
    }

    class BioimpedanciaService {
        +listAll()
        +firstByRutChequeoBio(string rut)
        +firstByRut(string rut)
        +CreateBio(rut, nombre, club) Bioimpedancia
        +saveFromAI(parsed, rut, nombre, club, fileName) Bioimpedancia
        +SP_bioempdacia_rut(rut_paciente)
        +fichaVigente(string rut_paciente) array
        +BioPDFRut(string rut_paciente, array ficha)
        -cleanNumber(value)
        -formatDate(date)
        -mapBioimpedancia(ai, rut, nombre) array
        -esc(valor) string
        -seccion(titulo) string
        -parrafo(titulo, texto) string
        -prepararPayloadIA(array ficha) array
        -analisisPersistido(array ficha, rut) array
        -analizarConIA(array payload) array
        -pieInforme(analisis, firmaErgo) string
    }

    class AnalisisBioimpedanciaPrompt {
        <<static>>
        +system() string
    }

    class AnalisisRutBioimpedanciaPrompt {
        <<static>>
        +system() string
    }

    class Bioimpedancia {
        <<Model>>
        table bioimpedancia
        guarded vacio
        +SP_bioempdacia_rut(rut_paciente)$
    }

    BioimpedanciaController *-- BioimpedanciaService
    BioimpedanciaController ..> AnalisisBioimpedanciaPrompt
    BioimpedanciaService ..> AnalisisRutBioimpedanciaPrompt
    BioimpedanciaService --> Bioimpedancia
    BioimpedanciaService --> ChequeoCardiovascular : fallback por rut
    BioimpedanciaService --> BioimpedanciaAnalisisIa : analisis guardado (Spec 07)
```

`Bioimpedancia` es el único modelo con `$guarded = []`; el resto no declara `$fillable` salvo los cuatro modelos de chat.

---

## Descarga de certificados por RUT

Spec 07. Dominio de solo lectura para la página pública `/certificados`: no factura ni cambia estados. `CertificadoDescargaServiceProvider` lo registra como singleton y define el limitador `certificados`.

```mermaid
classDiagram
    class CertificadoDescargaController {
        +Consulta(Request) JsonResponse
        +DescargaEcg(Request, int id) Response
        +DescargaBio(Request, int id) Response
        -descargar(Request, tipo, rut, pdf) Response
    }

    class CertificadoDescargaService {
        +MINUTOS_URL = 10
        +ESTADO_CERTIFICABLE = REVISION MEDICA
        +normalizarRut(rut)$ string
        +rutValido(rut)$ bool
        +contextoError(Throwable)$ array
        +consultar(string rut) array
        +rutDeChequeo(int id) string
        +rutDeBioimpedancia(int id) string
        +pdfCertificadoEcg(int id) array
        +pdfCertificadoBio(int id) array
        +registrar(rut, tipo, ip, resultado)
        +urlFirmada(ruta, int id) array
        #renderizarPdf(html) string
    }

    class CertificadoDescargaServiceProvider {
        +register()
        +boot() RateLimiter certificados
    }

    class CertificadoDescarga {
        <<Model>>
        table certificado_descargas
        UPDATED_AT null
    }

    class BioimpedanciaAnalisisIa {
        <<Model>>
        table bioimpedancia_analisis_ia
        casts analisis array
    }

    CertificadoDescargaController *-- CertificadoDescargaService
    CertificadoDescargaServiceProvider ..> CertificadoDescargaService : singleton
    CertificadoDescargaService --> ChequeoCardiovascularPDFService : chequeoPDf
    CertificadoDescargaService --> BioimpedanciaService : fichaVigente y BioPDFRut
    CertificadoDescargaService --> CertificadoDescarga : registrar
    CertificadoDescargaService --> ChequeoCardiovascular
    CertificadoDescargaService --> Bioimpedancia
    BioimpedanciaService --> BioimpedanciaAnalisisIa : analisisPersistido
```

---

## Incidencias y ficha clínica

Dos dominios de solo lectura estadística, casi enteramente delegados a procedimientos almacenados.

```mermaid
classDiagram
    class IncidenciasController {
        +CountClub(string user_email)
        +CountGravedad(string user_email)
        +CountLiga(string user_email)
        +IncidenciaCreate(Request)
        +FindByUserEmail(string user_email)
        +LesionFrecuente(string user_email)
        +LigaCasos(string user_email)
        +sp_estadistica_liga(string user_email)
        +sp_estadistica_categoria(string user_email)
        +sp_estadistica_lesiones(string user_email)
        +sp_estadistica_parte_cuerpo(string user_email)
        +sp_estadistica_lesiones_fechas(string user_email)
    }

    class IncidentesService {
        +IncidenciaCreate(15 parametros posicionales)
        +FindByUser(user_email)
        +CountClub(user_email)
        +CountLiga(user_email)
        +CountGravedad(user_email)
        +LesionFrecuente(user_email)
        +LigaCasos(user_email)
        +sp_estadistica_liga(user_email)
        +sp_estadistica_categoria(user_email)
        +sp_estadistica_lesiones(user_email)
        +sp_estadistica_parte_cuerpo(user_email)
        +sp_estadistica_lesiones_fechas(user_email)
    }

    class IncidentesDeportivos {
        <<Model>>
        table incidentes_deportivos
        +sp_estadistica_liga(param1)$
        +sp_estadistica_categoria(param1)$
        +sp_estadistica_lesiones(param1)$
        +sp_estadistica_parte_cuerpo(param1)$
        +sp_estadistica_lesiones_fechas(param1)$
    }

    class FichaClinicaController {
        +FichaClinica(string rut_paciente)
    }

    class FichaClinicaService {
        +FichaClinica(rut_paciente)
    }

    class FichaClinica {
        <<Model sin tabla>>
        +SP_ficha_clinica(param1)$
    }

    IncidenciasController *-- IncidentesService
    IncidentesService --> IncidentesDeportivos
    FichaClinicaController *-- FichaClinicaService
    FichaClinicaService --> FichaClinica
```

`IncidentesService` filtra siempre por `club_deportivo`; la tabla `incidentes_deportivos` **no guarda RUT**, así que se relaciona con el resto del sistema solo por `user_email`.

> `IncidenciasController` es el único que devuelve **HTTP 201 también en las lecturas** (`GET`).

---

## Usuarios y perfiles

```mermaid
classDiagram
    class UserController {
        +AuthRegister(Request)
        +FileUpload(Request)
        +ListUserEmail(int perfil)
        +userSave(Request)
        +UserUpdatePassowrd(Request)
        +UserUpdateErgoPass(Request)
        +UserFirstErgoPass(Request)
        +CreateUser(Request)
    }

    class GoogleAuthControlle {
        +AuthLogin(Request)
    }

    class UserMetadataService {
        +getPerfilIdByEmail(string userEmail) int
        +userSave(name, email, password, perfiles_id, rut_paciente)
        +UserUpdatePassowrd(password_user, email_user)
        +UserUpdateErgoPass(ergo_pass, email_user)
        +userFirstErgoPass(email_user)
    }

    class User {
        <<Model Authenticatable>>
        table users
        fillable name, email, password
        hidden password, remember_token
    }

    class UsersMetadata {
        <<Model>>
        table users_metadata
        timestamps false
        +users() BelongsTo
        +perfiles() BelongsTo
    }

    class Perfiles {
        <<Model>>
        table perfiles
        timestamps false
    }

    UserController *-- UserMetadataService
    UserMetadataService --> User
    UserMetadataService --> UsersMetadata
    UsersMetadata --> User
    UsersMetadata --> Perfiles
    GoogleAuthControlle ..> UsersMetadata : sin servicio
```

`UsersMetadata` es el **único modelo con relaciones Eloquent declaradas** en todo el proyecto. `getPerfilIdByEmail()` es la fuente del `perfilId` que alimenta todo el filtrado por perfil.

> Los typos `GoogleAuthControlle` (sin la «r» final, tanto el archivo como la clase) y `UserUpdatePassowrd` están consolidados: no los «corrijas» sin actualizar `routes/api.php` y los clientes.

---

## Agenda, servicios y pagos

Cuatro controladores sin ninguna capa de servicio: van directo al modelo.

```mermaid
classDiagram
    class AgendaHorasController {
        +Health()
        +getAgenda()
        +StoreAgenda(Request)
    }
    class ServiciosController {
        +getService()
        +showService(string nombre)
        +LikeServices(Request)
    }
    class EmailController {
        +EmailReservaHora(Request)
    }
    class WebPayController {
        +WebPayRequest(Request)
        +WebPayResponse()
    }
    class EstadisticasController {
        +EstadisticaIMC(Request)
        +EstadisticaPresion(Request)
        +EstadisticaHemoglucotest(Request)
        +EstadisticaSaturacion(Request)
        +PagoMensual(Request)
        +EstadisticaPagoMensual()
        +deletePagoMensual(Request)
        +AgendaMensual(Request)
        +EstadisticaPagoMDC()
        +UpdatePagoMensual(Request)
    }

    class AgendaHoras {
        <<Model>>
        table agenda_horas
    }
    class Servicios {
        <<Model>>
        table servicios
    }
    class WebPayInfo {
        <<Model>>
        table web_pay_info
    }
    class LogsApi {
        <<Model>>
        table logs_api
    }
    class PagoMensual {
        <<Model>>
        table pago_mensual
        +AgendaMensual(param1)$
        +SP_estadistica_monto_mdc()$
        +SP_update_pago_mensual(periodo)$
        +SP_chequeos_prompt(search)$
    }
    class EmailMailable {
        <<Mailable>>
        +envelope() Envelope
        +content() Content
        +attachments() array
    }

    AgendaHorasController --> AgendaHoras
    AgendaHorasController --> Servicios
    ServiciosController --> Servicios
    EmailController --> AgendaHoras
    EmailController ..> EmailMailable
    WebPayController --> WebPayInfo
    WebPayController --> AgendaHoras
    WebPayController --> LogsApi
    EstadisticasController *-- EstadisticasService
    EstadisticasController --> PagoMensual : deletePagoMensual salta el servicio
    EstadisticasService --> PagoMensual
    EstadisticasService --> ChequeoCardiovascular
```

`LogsApi` solo lo escribe `WebPayController`: es el único dominio del proyecto que persiste errores.

---

## Juego de cartas

Vertical slice completo y reciente: es el ejemplo más limpio del patrón Controller → Service → Model del repo, porque se escribió con las convenciones ya documentadas.

```mermaid
classDiagram
    class JuegoCartasController {
        -JuegoCartasService juegoCartasService
        +__construct(JuegoCartasService)
        +CartasClub(Request, string user_email)
        +CartaDetalle(string rut_paciente)
        +Niveles()
    }

    class JuegoCartasService {
        +CartasClub(?string search, string club) array
        +CartaDetalle(string rut) ?array
        +Niveles() array
        -llamarSP(?string search, ?string club) array
        -ordenar(array cartas) void
    }

    class JuegoCartaClub {
        <<wrapper de SP>>
        +SP_juego_cartas_club(search, club)$
    }

    class JuegoNivel {
        +string table = "juego_niveles"
        +array fillable
    }

    class JuegoAtributo {
        +string table = "juego_atributos"
        +array fillable
    }

    class JuegoCartasServiceProvider {
        +register() void
        +boot() void
    }

    JuegoCartasController --> JuegoCartasService : constructor
    JuegoCartasService --> JuegoCartaClub : CALL SP
    JuegoCartasService --> JuegoNivel : Eloquent
    JuegoCartasService --> JuegoAtributo : Eloquent
    JuegoCartasServiceProvider ..> JuegoCartasService : singleton
```

Detalles que no se ven en el diagrama:

- `JuegoCartaClub` **no declara `$table`**: solo envuelve el procedimiento, igual que `FichaClinica`. `JuegoNivel` y `JuegoAtributo` sí son modelos Eloquent reales.
- `llamarSP()` usa la variante **defensiva** de `json_decode` (la de `ClubAssistantService`, no la de `FichaClinicaService`): comprueba `empty($results[0]->resultado_json)` y valida `is_array()` antes de seguir.
- `ordenar()` existe porque **en MySQL 5.7 `JSON_ARRAYAGG` no respeta `ORDER BY`**. Ordena por `puntaje` descendente, desempata por `atributos_medidos` descendente —para que una carta con los cuatro atributos gane a otra que empata con solo dos— y manda las `sin_evaluar` al final.
- `JuegoCartasServiceProvider` está registrado en `bootstrap/providers.php`, en orden alfabético entre `IncidenciaServiceProvider` y `OpenAIServiceProvider`.

---

## Home comercial

Mantenedor de las imágenes del Home comercial del front (Spec 03). Mismo vertical slice que el juego de cartas, con un modelo Eloquent real y sin procedimientos almacenados.

```mermaid
classDiagram
    class HomeImagenController {
        -HomeImagenService homeImagenService
        +__construct(HomeImagenService)
        +Index()
        +Admin()
        +Store(Request)
        +Update(Request, int id)
        +UpdateActivo(Request, int id)
        +Destroy(Request, int id)
        -listar(bool soloActivas) JsonResponse
        -exigirAdministrador(Request) ?JsonResponse
        -reglasMetadatos() array
    }

    class HomeImagenService {
        +listar(bool soloActivas) Collection
        +esAdministrador(?string userEmail) bool
        +crear(UploadedFile, array datos) HomeImagen
        +actualizar(HomeImagen, array datos) HomeImagen
        +cambiarActivo(HomeImagen, bool activo) HomeImagen
        +borrar(HomeImagen) void
        -nombreArchivo(UploadedFile, seccion, caption, alt) string
    }

    class HomeImagen {
        +string table = "home_imagenes"
        +array SECCIONES$
        +string CARPETA$
        +array appends = "src"
        +array hidden
        #src() Attribute
        +rutaArchivo() string
    }

    class HomeImagenServiceProvider {
        +register() void
        +boot() void
    }

    HomeImagenController --> HomeImagenService : constructor
    HomeImagenController --> HomeImagen : find(id)
    HomeImagenService --> HomeImagen : Eloquent
    HomeImagenServiceProvider ..> HomeImagenService : singleton
```

Detalles que no se ven en el diagrama:

- `SECCIONES` = `promocion`, `alianza`, `galeria` (también es el orden del listado) y `CARPETA` = `HomeImagenes`. `$hidden` oculta `archivo`, `created_at` y `updated_at`.
- `src` es un accessor: `config('services.home_imagenes.url')` + `/` + `rawurlencode(archivo)`. Es el único lugar del repo que arma una URL pública desde `config()` y no desde `env()`; `grep -rn "API_PATH_HOME" app/` sale vacío a propósito.
- `esAdministrador()` hace un join `users_metadata` → `perfiles` y compara `perfiles.nombre = 'Administrador'`, no `perfiles_id = 1` como el resto del código.
- El controlador valida con `Validator::make` y no con `$request->validate()`: el front no envía `Accept: application/json`, y sin ese header un fallo de `validate()` respondería con un redirect.
- El controlador busca la imagen con `HomeImagen::find($id)` antes de pasarla al servicio; es el único acceso al modelo que salta la capa de servicio.
- `HomeImagenServiceProvider` está registrado en `bootstrap/providers.php` entre `FichaClinicaServiceProvider` e `IncidenciaServiceProvider`.

---

## Carro de servicios (cart-flow)

Specs 04 y 05. Es el dominio más grande que entró después de los chequeos:
- 4 controladores en `app/Http/Controllers/CartFlow/`, que comparten el trait `RespuestasCartFlow`.
- 10 servicios en `app/Services/CartFlow/`, todos registrados en `CartFlowServiceProvider`.
- 19 modelos en `app/Models/CartFlow/`.

### Controladores y servicios

```mermaid
classDiagram
    class RespuestasCartFlow {
        <<trait>>
        #responder(string error, callable accion) JsonResponse
        #ok(mixed data, string mensaje, int status) JsonResponse
        #validar(array datos, array reglas) array
    }
    class CatalogoController {
        +Comunas()
        +Index(Request)
        +Show(string sku)
        +UpdateVisibilidad(Request, string sku)
    }
    class CarritoController {
        +Store(Request) · Show(uuid) · Update(Request, uuid)
        +PacienteStore · PacienteUpdate · PacienteQuitar
        +LineaStore · LineaUpdate · LineaQuitar
        +Checkout(Request, string uuid)
    }
    class AgendaPedidoController {
        +Bloques(Request)
        +PedidoShow(Request, string numero)
        +WebPay(Request, string numero)
        +WebPayRetorno(Request) RedirectResponse
        +CotizacionStore(Request)
    }
    class AdminCartFlowController {
        +Resumen · Pedidos · Pedido · PedidoEstado
        +OrdenEnlace · OrdenDescarga
        +Cotizaciones · CotizacionEstado · Productos
        -exigirAdministrador(Request) string
    }
    class CalculadoraCarroService {
        +REGLAS_POR_DEFECTO$
        +calcular(array datos, array reglas) array
        +precioUnitario(Producto, plan, ?edad) array
        +validarLinea(Producto, ?edad, ?sexo) array
        +edad(?fechaNac, fecha) ?int
    }
    class CatalogoService {
        +comunas() · listar() · detalle(sku)
        +reglas() array
        +automaticos(reglas) array
        +cambiarVisibilidad(Producto, bool, email) Producto
        +esAdministrador(?email) bool
    }
    class CarritoService {
        +crear · abierto(uuid) · actualizar
        +agregarPaciente · quitarPaciente (soft delete)
        +agregarLinea · actualizarLinea · quitarLinea
        +calcular(Carrito, ?fecha) array
        +serializar(Carrito) array
    }
    class AgendaService {
        +disponibles(zonaId, soloManana, desde, hasta) array
        +usados(bloqueId, fecha) int
        +rango(reglas) array
    }
    class CheckoutService {
        +checkout(Carrito, array datos, array ordenes) array
        -validarPaciente(...) array
    }
    class WebPayCartFlowService {
        +iniciar(Pedido) array
        +retorno(?tokenWs, ?tbkOrden) string
        +confirmar(Pago) bool
    }
    class PedidoService {
        +buscar(numero, token) Pedido
        +serializar(Pedido) array
        +cotizar(array) Cotizacion
    }
    class AdminPedidoService {
        +resumen() · listar(filtros) · detalle(Pedido)
        +accionesPermitidas(Pedido) array
        +cambiarEstado(Pedido, accion, email, motivo) Pedido
        +enlaceOrden(id) array
        +listarCotizaciones · cambiarEstadoCotizacion · listarProductos
    }
    class ImportadorCatalogoService {
        +importar(string dir) array
    }
    class CatalogoCsv {
        +productos(dir) · comunas(dir) · componentes(dir) · tramos(dir)
    }
    class CartFlowException {
        +int status
        +mixed error
    }

    CatalogoController ..|> RespuestasCartFlow
    CarritoController ..|> RespuestasCartFlow
    AgendaPedidoController ..|> RespuestasCartFlow
    AdminCartFlowController ..|> RespuestasCartFlow
    CatalogoController --> CatalogoService
    CarritoController --> CarritoService
    CarritoController --> CheckoutService
    CarritoController --> PedidoService
    AgendaPedidoController --> AgendaService
    AgendaPedidoController --> WebPayCartFlowService
    AgendaPedidoController --> PedidoService
    AdminCartFlowController --> AdminPedidoService
    AdminCartFlowController --> CatalogoService
    CarritoService --> CalculadoraCarroService
    CarritoService --> CatalogoService
    CheckoutService --> CarritoService
    CheckoutService --> AgendaService
    ImportadorCatalogoService --> CatalogoCsv
    CarritoService ..> CartFlowException : lanza
```

### Modelos

```mermaid
classDiagram
    class Producto {
        +sku
        +tipo
        +precio
        +visible_tienda
        +hidden costo_domilab nota_interna
        +solo_cotizacion()
    }
    class Comuna {
        +comuna
        +zona_id
        +esACotizar()
    }
    class Zona {
        +zona
        +cargo_servicio
        +cargo_laboratorio
    }
    class Carrito {
        +uuid
        +plan
        +estado
        +estaAbierto()
    }
    class CarritoPaciente {
        +SoftDeletes
    }
    class CarritoLinea {
        +SoftDeletes
    }
    class Pedido {
        +numero
        +token_consulta_hidden
        +total
        +estado
        +historial()
    }
    class PedidoPaciente {
        +preparacion json
    }
    class PedidoLinea {
        +precio_unitario copiado
    }
    class PedidoOrdenMedica {
        +path_hidden
    }
    class Pago {
        +buy_order
        +token_hidden
        +response_code
        +estado
    }
    class PedidoHistorial {
        +campo
        +anterior
        +nuevo
        +motivo
        +user_email
    }
    class BloqueAgenda {
        +dia_semana
        +franja
        +cupo
        +etiqueta()
    }

    Comuna --> Zona
    Carrito --> Comuna
    Carrito "1" --> "*" CarritoPaciente
    CarritoPaciente "1" --> "*" CarritoLinea
    CarritoLinea --> Producto
    Producto "1" --> "*" Producto : componentes
    Pedido "1" --> "*" PedidoPaciente
    PedidoPaciente "1" --> "*" PedidoLinea
    PedidoPaciente "1" --> "*" PedidoOrdenMedica
    Pedido "1" --> "*" Pago
    Pedido "1" --> "*" PedidoHistorial
    Pedido --> BloqueAgenda
```

Detalles que no se ven en el diagrama:

- `CalculadoraCarroService` no toca la base de datos: recibe los `Producto` ya cargados. Por eso su test unitario no
  arranca Laravel y crea los productos con `Model::unguarded()`.
- Los controladores no tienen `try/catch` propio: `RespuestasCartFlow::responder()` traduce `CartFlowException` a su
  código HTTP y cualquier otro error a 500 con log.
- `CartFlowServiceProvider::boot()` registra el comando `cart-flow:importar-catalogo`. `bootstrap/app.php` le pasa
  `routes/console.php` a `withRouting()`, y con eso Laravel deja de auto-descubrir `app/Console/Commands`.
- Hay dos `esAdministrador()` idénticos, en `HomeImagenService` y en `CatalogoService`. Es duplicación deliberada,
  para no acoplar dominios.

---

## Convenciones y anomalías

**Convenciones propias del repo** (respétalas al añadir código):

- Los métodos de controlador van en `PascalCase` (`FindByEmail`, `ChequeoPDFRut`), a diferencia de la convención Laravel por defecto. Hay excepciones ya existentes en minúscula: `resetPatient`, `deleteById`, `deletePagoMensual`, `chequeoUserEmail`.
- Los modelos exponen **wrappers estáticos** sobre `DB::select('CALL SP_xxx(?)')` en vez de repartir los `CALL` por los servicios.
- Los prompts viven en `app/PromptSystem/` como clases estáticas, nunca incrustados en controladores.

**Anomalías estructurales visibles en los diagramas:**

| Anomalía | Dónde |
|---|---|
| Servicio inyectado y nunca usado | `CargaMasivaController` → `ChequeoCardiovascularService` |
| Servicio inyectado dos veces | `OpenAIController::AsQuestionUseCase` → `OpenAIService` |
| Servicio sin provider (no es singleton) | `ChequeoCardiovascularWordService` |
| Lógica de servicio duplicada en el controlador | `CertificadoUrlController::FileUploadCer` |
| Métodos públicos sin ruta | `ChequeoCardiovascularController::DeleteRut`, `FileUploadController::FileUploadStorage` |
| Modelos sin tabla (envoltorios de SP) | `FichaClinica`, `ChequeoClubPrompt` |

---

**Ver también:** [Arquitectura](arquitectura.md) · [Flujos end-to-end](flujos.md) · [Modelo de datos](modelo-datos.md)
