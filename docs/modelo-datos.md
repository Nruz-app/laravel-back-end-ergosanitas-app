# Modelo de datos, estados y autorización

El esquema real de la base **va por delante de las migraciones del repo**. Las columnas de esta página se reconstruyeron contrastando tres fuentes, en este orden de fiabilidad: los schemas de [`openapi.yaml`](openapi.yaml), las migraciones de `database/migrations/`, y las queries del código para las columnas que solo existen en producción.

- [Diagrama entidad-relación: dominio clínico](#diagrama-entidad-relación-dominio-clínico)
- [Diagrama entidad-relación: negocio y conversaciones](#diagrama-entidad-relación-negocio-y-conversaciones)
- [Las tres claves de cruce](#las-tres-claves-de-cruce)
- [Máquina de estados del chequeo](#máquina-de-estados-del-chequeo)
- [Autorización por perfil](#autorización-por-perfil)
- [Procedimientos almacenados](#procedimientos-almacenados)

---

## Diagrama entidad-relación: dominio clínico

Las relaciones se dibujan **punteadas** porque en este esquema **casi ninguna tiene foreign key**: los cruces son por convención, no por integridad referencial declarada.

```mermaid
erDiagram
    CHEQUEO_CARDIOVASCULAR ||..o{ ELECTRO_CARDIOGRANAS : "por (rut, id_chequeo)"
    CHEQUEO_CARDIOVASCULAR ||..o{ CERTIFICADO_URL : "por (rut, id_chequeo)"
    CHEQUEO_CARDIOVASCULAR ||..o{ BIOIMPEDANCIA : "solo por rut"

    CHEQUEO_CARDIOVASCULAR {
        int id PK
        varchar rut "clave de negocio"
        varchar nombre
        varchar fechaNacimiento
        varchar edad
        varchar sexo_paciente "sin migracion"
        varchar division_paciente "sin migracion"
        varchar estatura
        varchar peso
        varchar imc "de la migracion"
        varchar imc_paciente "sin migracion"
        varchar presionArterial "guarda la DIASTOLICA"
        varchar presion_sistolica "sin migracion"
        varchar hemoglucotest
        varchar pulso
        varchar saturacionOxigeno
        varchar temperatura
        varchar enfermedadesCronicas
        varchar medicamentosDiarios
        varchar sistemaOsteoarticular
        varchar sistemaCardiovascular
        varchar enfermedadesAnteriores
        varchar Recuperacion
        varchar gradoIncidenciaPosterio
        varchar status "sin migracion"
        varchar user_email "club dueno, sin migracion"
        varchar user_email_update "sin migracion"
        datetime fecha_atencion "sin migracion"
        varchar medio_pago_paciente "sin migracion"
        varchar email_paciente "sin migracion"
        varchar fileStatus
        varchar fileName
        datetime created_at
        datetime updated_at
    }

    ELECTRO_CARDIOGRANAS {
        int id PK
        varchar rut_paciente FK
        int id_chequeo FK
        varchar estado_paciente "Normal o Alterado"
        varchar frecuencia_cardiaca_paciente
        varchar derivacion_paciente
        varchar observacion_paciente
        varchar imc_paciente
        datetime created_at
        datetime updated_at
    }

    CERTIFICADO_URL {
        int id PK
        varchar rut_paciente FK
        int id_chequeo FK "sin migracion"
        varchar url_pdf
        varchar name_pdf
        varchar titulo
        varchar derivado_medico "SI o NO, sin migracion"
        datetime created_at
        datetime updated_at
    }

    BIOIMPEDANCIA {
        int id PK
        varchar rut FK
        varchar nombre
        varchar sexo
        int edad
        decimal estatura_cm
        decimal peso_kg
        decimal imc
        decimal grasa_corporal_pct
        decimal masa_muscular_kg
        decimal agua_corporal_pct
        int tasa_metabolica_basal_kcal
        int grasa_visceral
        int edad_corporal
        decimal smi
        decimal peso_objetivo_kg
        decimal control_peso_kg
        varchar archivo
        text observaciones
        json raw_json
        datetime created_at
        datetime updated_at
    }
```

**Cuatro trampas de este esquema:**

- **`electro_cardiogranas` lleva una «n»**. Es un typo consolidado en producción; respétalo en las queries.
- **`imc` e `imc_paciente` coexisten.** El PDF y `ChequeoEmailAll` leen `imc_paciente`; `SP_chequeos_club_prompt` lee `cc.imc`.
- **`presionArterial` guarda la diastólica**, y la sistólica va en `presion_sistolica`. El SP del club las concatena al revés, y por eso `ClubAssistantService::normalizarPresion()` las invierte antes de mandarlas al modelo.
- Casi todo es `VARCHAR`: los números viajan como texto.

---

## Diagrama entidad-relación: negocio y conversaciones

```mermaid
erDiagram
    USERS ||..|| USERS_METADATA : "users_id"
    PERFILES ||..o{ USERS_METADATA : "perfiles_id"
    USERS_METADATA ||..o{ PAGO_MENSUAL : "user_email = club"
    USERS_METADATA ||..o{ INCIDENTES_DEPORTIVOS : "user_email"
    USERS_METADATA ||..o{ CHAT_CLUB_SESSIONS : "user_email = club_email"
    SERVICIOS ||--o{ AGENDA_HORAS : "servicios_id (unica FK real)"
    AGENDA_HORAS ||..o{ WEB_PAY_INFO : "por rut_paciente"
    CHAT_CLUB_SESSIONS ||..o{ CHAT_CLUB_HISTORY : "session_id"
    CHAT_SESSIONS ||..o{ CHAT_HISTORY : "patient_identifier, NO session_id"

    USERS {
        int id PK
        varchar name
        varchar email UK
        datetime email_verified_at
        varchar password "bcrypt"
        varchar remember_token
    }

    USERS_METADATA {
        int id PK
        int users_id FK
        int perfiles_id FK
        varchar user_name
        varchar user_email
        varchar status "S"
        varchar password "EN CLARO"
        varchar rut_paciente
        varchar ergo_pass
        varchar user_logo
    }

    PERFILES {
        int id PK
        varchar nombre
        varchar status
    }

    PARAMS {
        varchar descripcion PK "hoy solo VALOR-ECG"
        varchar valor
    }

    PAGO_MENSUAL {
        varchar club
        varchar periodo
        int cantidad_ecg
        decimal monto
    }

    SERVICIOS {
        int id PK
        varchar nombre
        varchar precio
        text descripcion
        varchar activo
    }

    AGENDA_HORAS {
        int id PK
        varchar nombre_paciente
        varchar rut_paciente
        varchar edad_paciente
        varchar direccion_paciente
        varchar email_paciente
        varchar celular_paciente
        varchar sexo_paciente
        int servicios_id FK
        varchar comuna_paciente
        varchar pagado_paciente "PAGADO"
        varchar fecha_reserva_paciente
    }

    WEB_PAY_INFO {
        int id PK
        varchar buy_order "Ymd + servicio"
        varchar session_id "contiene el RUT"
        varchar rut_paciente
        varchar tokenWs
        varchar amount
        varchar status
        varchar response_code
        varchar authorization_code
        varchar card_detail
        varchar payment_type_code
        varchar installments_number
        varchar vci
        varchar accounting_date
        varchar transaction_date
    }

    INCIDENTES_DEPORTIVOS {
        int id PK
        varchar nombres
        varchar edad
        varchar deporte
        varchar tipo_lesion
        varchar ubicacion
        varchar parte_cuerpo
        text descripcion
        text primeros_auxilios
        varchar gravedad
        varchar estado
        varchar user_email
        varchar liga
        varchar club_deportivo
    }

    CHAT_SESSIONS {
        int id PK
        varchar session_id UK
        varchar patient_identifier
    }

    CHAT_HISTORY {
        int id PK
        varchar patient_identifier
        varchar role
        longtext message
        longtext context_json
    }

    CHAT_CLUB_SESSIONS {
        int id PK
        varchar session_id UK
        varchar club_email "NOT NULL sin default"
        varchar search_actual
    }

    CHAT_CLUB_HISTORY {
        int id PK
        varchar session_id
        varchar club_email
        varchar role
        longtext message
        longtext context_json
    }

    LOGS_API {
        int id PK
        varchar rutaWeb
        varchar mensaje "250 caracteres"
    }
```

**Perfiles conocidos:** 1 administrador · 2 tester · 3 club deportivo · 5 paciente · 6 médico.

**Tablas sin migración en el repo** — existen solo en la base de datos, igual que los procedimientos almacenados. `php artisan migrate` sobre una base vacía deja el esquema incompleto:

| Tabla | Por qué importa |
|---|---|
| `users_metadata` | Sin ella no hay perfiles, y todo el filtrado se cae |
| `params` | Sin la fila `VALOR-ECG`, los tres endpoints de facturación responden 500 |
| `electro_cardiogranas` | Sin ella no hay lectura de ECG ni estado derivado |
| `pago_mensual` | Sin ella no hay facturación mensual por club |

> `chat_history` **no guarda `session_id`**: el historial del asistente clínico se agrupa por `patient_identifier`, así que dos sesiones distintas que hablen del mismo paciente comparten la conversación. El chat de club sí agrupa por sesión.

---

## Las tres claves de cruce

```mermaid
flowchart TB
    subgraph eje1["1 · RUT del paciente"]
        R["formato 12345678-9<br/>validado con /^\d{7,8}-[0-9kK]$/<br/>sin verificar el digito verificador"]
        R --> R1["chequeo_cardiovascular.rut"]
        R --> R2["electro_cardiogranas.rut_paciente"]
        R --> R3["certificado_url.rut_paciente"]
        R --> R4["bioimpedancia.rut"]
        R --> R5["agenda_horas.rut_paciente"]
        R --> R6["web_pay_info.rut_paciente"]
        R --> R7["parametro de SP_ficha_clinica"]
    end

    subgraph eje2["2 · Par (rut_paciente, id_chequeo)"]
        P["clave fuerte hacia los documentos"]
        P --> P1["electro_cardiogranas"]
        P --> P2["certificado_url"]
    end

    subgraph eje3["3 · user_email — tenancy por club"]
        E["identifica al club dueno"]
        E --> E1["chequeo_cardiovascular.user_email"]
        E --> E2["incidentes_deportivos.user_email"]
        E --> E3["pago_mensual.club"]
        E --> E4["chat_club_sessions.club_email"]
        E --> E5["users_metadata.user_email"]
    end
```

Cambiar el RUT de un chequeo sin propagarlo deja los certificados y los ECG huérfanos. Por eso, al editar desde el perfil 1, `ChequeoCardiovascularController::Update()` dispara `CertificadoService::UpdateRutCertificado()` y `ElectroCardiogramaService::UpdateRutECG()`.

> Algunos joins antiguos (`filterCalendar`, `chequeoUserEmail`, `FindByEmail`, `LikeChequeoUser`) unen ECG y chequeo **solo por RUT**, no por el par completo. Eso duplica filas cuando un paciente tiene varios chequeos.

---

## Máquina de estados del chequeo

`chequeo_cardiovascular.status` es texto libre y avanza por cuatro valores.

```mermaid
stateDiagram-v2
    [*] --> ingresado : default de la columna<br/>(alta normal y carga masiva)
    ingresado --> Testiado : Store o Update con perfil 2 (tester)<br/>tambien fija fecha_atencion
    Testiado --> ECG_FOTO : subida de certificado
    ECG_FOTO --> REVISION_MEDICA : el cardiologo guarda el informe
    REVISION_MEDICA --> [*]

    ingresado --> ECG_FOTO : la subida no exige estado previo
    Testiado --> REVISION_MEDICA : el guardado de ECG tampoco

    note right of ECG_FOTO
        Escrito en DOS sitios:
        CertificadoService::subirCertificado()
        CertificadoUrlController::FileUploadCer()
        Ambos facturan el mes del club.
    end note

    note right of REVISION_MEDICA
        ElectroCardiogramaController::Save()
        Factura otra vez.
    end note
```

### Quién escribe el `status`

Son **seis** puntos en el código PHP, no uno:

| Dónde | Valor | Condición |
|---|---|---|
| `ChequeoCardiovascularController::Store()` | `Testiado` | `perfilId == 2` |
| `ChequeoCardiovascularController::Update()` | `Testiado` | `perfilId == 2` |
| `ChequeoCardiovascularController::Update()` | **libre**, desde el request | `perfilId == 1` |
| `CertificadoService::subirCertificado()` | `ECG FOTO` | siempre |
| `CertificadoUrlController::FileUploadCer()` | `ECG FOTO` | siempre |
| `ElectroCardiogramaController::Save()` | `REVISION MEDICA` | siempre |

`ChequeoImport` no escribe `status`: queda el default de la base. El resto de transiciones puede venir de `UPDATE` del cliente o de procedimientos almacenados.

### El campo derivado `estado_paciente`

En los listados, el `status` se transforma antes de salir:

```mermaid
flowchart LR
    S["status del chequeo"] --> Q{"status = REVISION MEDICA?"}
    Q -->|no| T["se devuelve el status tal cual"]
    Q -->|si| E{"tiene ECG cargado?"}
    E -->|no| RC["En Rev. Cardio"]
    E -->|si| D["Diag. Card. - Normal<br/>o Diag. Card. - Alterado<br/>segun electro_cardiogranas.estado_paciente"]
```

Para el perfil 3 la búsqueda se ordena con `orderByRaw("FIELD(cc.status, 'ECG FOTO', 'REVISION MEDICA', 'Testiado', 'ingresado')")`.

Otros puntos que **leen** el status: `CertificadoUrlController::ValidaCertificado()` exige `REVISION MEDICA`, y `SP_chequeos_club_prompt` filtra `status = 'REVISION MEDICA'` — por eso el asistente por club solo ve pacientes en revisión médica.

---

## Autorización por perfil

El `perfilId` se obtiene con `UserMetadataService::getPerfilIdByEmail()` a partir del `user_email` **que llega en el request**. No es un control de acceso: es una regla de presentación.

```mermaid
flowchart TB
    IN["user_email del request"] --> GP["UserMetadataService::getPerfilIdByEmail()"]
    GP --> Q{"perfiles_id"}

    Q -->|3 · club deportivo| P3["where cc.user_email = user_email<br/>solo ve sus propios registros<br/>IGNORA el filtro selectClub"]
    Q -->|6 · medico| P6["join certificado_url por (rut, id_chequeo)<br/>where cu.derivado_medico = 'SI'"]
    Q -->|resto| PX["ve todo<br/>si viene selectClub, filtra por ese club"]

    P6 --> P6A["SearchChequeo ANADE where cc.status = 'ECG FOTO'"]
    P6 --> P6B["ChequeoEmailAll NO filtra por status"]

    P6A:::warn
    P6B:::warn
    classDef warn fill:#fff4e5,stroke:#d97706,stroke-width:2px,color:#5d4037
```

El patrón está implementado en tres métodos de `ChequeoCardiovascularService`, y **las tres versiones no son idénticas**:

| Método | Perfil 3 | Perfil 6 | Join con ECG |
|---|---|---|---|
| `filterCalendar()` | sí | no lo aplica | por RUT |
| `SearchChequeo()` | sí, más `orderByRaw(FIELD(...))` | `derivado_medico='SI'` **y** `status='ECG FOTO'` | subquery del último ECG por `id_chequeo` |
| `ChequeoEmailAll()` | sí | `derivado_medico='SI'`, **sin** filtro de status | subquery `MAX(id) GROUP BY id_chequeo` |

Si cambias la regla, hay que revisar los tres —y decidir a propósito si la divergencia actual es intencional—.

**Otras dos anomalías de estas queries**, útiles si algo se comporta raro:

- `filterCalendar()`, `FindByEmail()` y `LikeChequeoUser()` ejecutan `->get()` **dos veces** sobre el mismo builder; el primer resultado se descarta.
- Varios `SELECT` mezclan `MAX(ec....)` con columnas no agregadas y **sin `GROUP BY`**. Funciona en la base actual, pero rompe si se activa `only_full_group_by`.
- `SearchChequeo()` sin filtros devuelve `'total' => 300` **hardcodeado**, no el total real.

---

## Procedimientos almacenados

Gran parte de las estadísticas vive en MySQL, no en PHP. Los modelos exponen wrappers estáticos sobre `DB::select('CALL SP_xxx(?)')`, y casi todos devuelven una única columna `resultado_json` que el servicio decodifica. La API invoca **19**.

```mermaid
flowchart LR
    C["Controlador"] --> S["Servicio"] --> M["Modelo<br/>wrapper estatico"] --> SP[["CALL SP_xxx(?)"]] --> R["columna resultado_json"]
    R --> JD["json_decode() en el servicio"] --> C
```

| Procedimiento | Modelo | Servicio | Endpoint |
|---|---|---|---|
| `SP_estadistica_IMC` | `ChequeoCardiovascular` | `EstadisticasService::EstadisticaIMC` | `GET estadisticas/estadistica-imc/{email}` |
| `sp_estadistica_presion` | `ChequeoCardiovascular` | `EstadisticasService::EstadisticaPresion` | `GET estadisticas/estadistica-presion/{email}` |
| `SP_estadistica_hemoglucotest` | `ChequeoCardiovascular` | `EstadisticasService::SP_estadistica_hemoglucotest` | `GET estadisticas/estadistica-hemoglucotest/{email}` |
| `SP_estadistica_saturacion` | `ChequeoCardiovascular` | `EstadisticasService::SP_estadistica_saturacion` | `GET estadisticas/estadistica-saturacion/{email}` |
| `SP_estado_general` | `ChequeoCardiovascular` | — (llamada directa) | `GET chequeo-cardiovascular/estado-general/{email}` |
| `SP_estadistica_monto` | `ChequeoCardiovascular` | `EstadisticasService::EstadisticaPagoMensual` | `GET estadisticas/estadistica-pago-mensual` |
| `SP_pago_mensual` | `ChequeoCardiovascular` | `EstadisticasService::PagoMensual` | `POST estadisticas/pago-mensual` **y los 3 flujos que facturan** |
| `SP_agenda_mensual` | `PagoMensual` | `EstadisticasService::AgendaMensual` | `POST estadisticas/agenda-mensual` |
| `SP_estadistica_monto_mdc` | `PagoMensual` | `EstadisticasService::EstadisticaPagoMDC` | `GET estadisticas/estadistica-pago-mdc` |
| `SP_update_pago_mensual` | `PagoMensual` | `EstadisticasService::UpdatePagoMensual` | `POST estadisticas/update-pago-mensual` |
| `SP_chequeos_prompt` | `PagoMensual` | `EstadisticasService::ChequeoPrompt` | `POST sam-assistant/as-question` |
| `SP_chequeos_club_prompt` | `ChequeoClubPrompt` | `ClubAssistantService::datosClub` | `POST sam-assistant-club/as-question` |
| `SP_ficha_clinica` | `FichaClinica` | `FichaClinicaService::FichaClinica` | `GET ficha-clinica/{rut}` |
| `SP_bioimpedacia_rut` | `Bioimpedancia` | `BioimpedanciaService::BioPDFRut` | `GET bioimpedancia/pdfRut/{rut}` |
| `SP_estadistica_liga` | `IncidentesDeportivos` | `IncidentesService::sp_estadistica_liga` | `GET incidencia-deportivos/sp_estadistica_liga/{email}` |
| `SP_estadistica_categoria` | `IncidentesDeportivos` | `IncidentesService::sp_estadistica_categoria` | `GET incidencia-deportivos/sp_estadistica_categoria/{email}` |
| `SP_estadistica_lesiones` | `IncidentesDeportivos` | `IncidentesService::sp_estadistica_lesiones` | `GET incidencia-deportivos/sp_estadistica_lesiones/{email}` |
| `SP_estadistica_parte_cuerpo` | `IncidentesDeportivos` | `IncidentesService::sp_estadistica_parte_cuerpo` | `GET incidencia-deportivos/sp_estadistica_parte_cuerpo/{email}` |
| `SP_estadistica_lesiones_fechas` | `IncidentesDeportivos` | `IncidentesService::sp_estadistica_lesiones_fechas` | `GET incidencia-deportivos/sp_estadistica_lesiones_fechas/{email}` |

**Solo `SP_chequeos_club_prompt` está versionado** en el repo (`base_datos/references/sp/SP_chequeos_club_prompt.sql`). El resto son cajas negras que viven únicamente en la base: al cambiar su firma hay que actualizarlos ahí directamente, y no queda rastro en git.

La base declara **23 procedimientos**, pero la API solo invoca los 19 de la tabla. Los otros cuatro (`SP_cargar_desde_chequeo`, `SP_filtros_dashboard`, `SP_procesar_mes` y `tmp_call_sp`) no los llama ningún endpoint: antes de borrar alguno, comprueba que no lo use un proceso externo.

---

**Ver también:** [Arquitectura](arquitectura.md) · [Flujos end-to-end](flujos.md) · [Diagrama de clases](diagrama-clases.md)
