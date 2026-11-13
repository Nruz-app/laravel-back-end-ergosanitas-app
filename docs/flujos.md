# Flujos end-to-end

Un diagrama de secuencia por flujo de negocio, con el endpoint que lo dispara y las advertencias que no se ven leyendo un solo archivo. Todo está contrastado contra el código, no contra la documentación en prosa.

| # | Flujo | Endpoint principal |
|---|---|---|
| 1 | [Login y perfiles](#1-login-y-perfiles) | `POST auth-register` · `POST auth-login` |
| 2 | [Carga masiva desde Excel](#2-carga-masiva-desde-excel) | `POST carga-masiva/excel` |
| 3 | [Ciclo de vida del chequeo](#3-ciclo-de-vida-del-chequeo) | `POST` / `PUT chequeo-cardiovascular` |
| 4 | [Certificado y facturación](#4-certificado-y-facturación) | `POST certificado/save-url` |
| 5 | [ECG del cardiólogo](#5-ecg-del-cardiólogo) | `POST electro-cardiograma/save` |
| 6 | [Generación de documentos](#6-generación-de-documentos) | `GET chequeo-cardiovascular/pdf/{id}` |
| 7 | [Bioimpedancia con IA](#7-bioimpedancia-con-ia) | `POST bioimpedancia/form-upload` |
| 8 | [Informe de bioimpedancia](#8-informe-de-bioimpedancia) | `GET bioimpedancia/pdfRut/{rut}` |
| 9 | [Asistente clínico SAM](#9-asistente-clínico-sam) | `POST sam-assistant/as-question` |
| 10 | [Asistente por club](#10-asistente-por-club) | `POST sam-assistant-club/as-question` |
| 11 | [Pago WebPay](#11-pago-webpay) | `POST transbank/web-pay-request` |
| 12 | [Agenda, correo y estadísticas](#12-agenda-correo-y-estadísticas) | `POST agenda-horas` · `POST email/reserva-hora` |
| 13 | [Juego de cartas por niveles](#13-juego-de-cartas-por-niveles) | `GET juego-cartas/{user_email}` |
| 14 | [Carro de servicios](#14-carro-de-servicios) | `POST cart-flow/carritos/{uuid}/checkout` |
| 15 | [Mantenedor del carro (Administrador)](#15-mantenedor-del-carro-administrador) | `PUT cart-flow/admin/pedidos/{numero}/estado` |
| 16 | [Chat comercial «Ergosanito»](#16-chat-comercial-ergosanito) | `POST chat-comercial/as-question` |
| 17 | [Descarga de certificados por RUT](#17-descarga-de-certificados-por-rut) | `POST certificados/consulta` · `GET certificados/descarga/ecg/{id}` · `GET certificados/descarga/bio/{id}` |

---

## 1. Login y perfiles

Coexisten tres mecanismos de autenticación (sesión con `Auth::attempt`, Sanctum solo en `/user`, y JWT configurado como guard `api`), pero **ninguna ruta de negocio lleva middleware**. El login sirve para obtener el perfil, no para proteger endpoints.

```mermaid
sequenceDiagram
    autonumber
    actor U as Cliente
    participant UC as Auth/UserController
    participant UMS as UserMetadataService
    participant DB as MySQL

    rect rgb(240, 246, 252)
    note over U,DB: Login propio
    U->>UC: POST auth-register {email, password}
    UC->>UC: Auth::attempt(credenciales)
    UC->>DB: SELECT users WHERE email
    UC->>DB: SELECT users_metadata WHERE user_email
    DB-->>UC: perfiles_id, user_logo, rut_paciente
    UC-->>U: 200 {success, message, user}
    end

    rect rgb(245, 243, 255)
    note over U,DB: Login con Google
    U->>UC: POST auth-login {token}
    note right of UC: GoogleAuthControlle usa google/apiclient<br/>directamente, no Socialite
    UC->>UC: Google\Client->verifyIdToken()
    UC->>DB: SELECT users_metadata WHERE user_email
    UC-->>U: 200 {success, message, user}
    end
```

Los usuarios viven en **dos tablas** que hay que mantener sincronizadas: `users` (password hasheada por Laravel) y `users_metadata` (perfil, logo, RUT, `ergo_pass` y **una copia en claro de la contraseña**). `UserMetadataService::userSave()` y `UserUpdatePassowrd()` escriben en ambas.

> `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET` se leen con `env()` dentro del propio controlador: si alguien ejecuta `config:cache`, el login de Google deja de funcionar.

---

## 2. Carga masiva desde Excel

```mermaid
sequenceDiagram
    autonumber
    actor U as Club / administrador
    participant CMC as CargaMasivaController
    participant IMP as ChequeoImport
    participant DB as chequeo_cardiovascular

    U->>CMC: POST carga-masiva/excel (xlsx + user_email)
    CMC->>CMC: validate mimes xlsx,xls,csv
    note right of CMC: user_email NO se valida
    CMC->>IMP: Excel::import(new ChequeoImport(user_email))

    loop por cada fila del Excel
        IMP->>IMP: formatearRut()
        IMP->>IMP: validarRut() contra /^\d{7,8}-[0-9kK]$/
        alt RUT invalido
            IMP-->>IMP: return null, fila descartada en silencio
        else RUT valido
            IMP->>IMP: formatearFecha(), calculateAge(), formatearSexo()
            IMP->>DB: INSERT nombre, rut, fechaNacimiento,<br/>sexo_paciente, edad, division_paciente, user_email
        end
    end

    IMP-->>CMC: getCantInser(), getErrorMsg()
    CMC-->>U: 200 {status, message, cantidad}
```

Columnas esperadas del Excel (fila de cabecera, en snake_case): `rut`, `nombre_completo`, `fecha_nacimiento`, `sexo`, `division`.

**Advertencias:**

- Las filas con RUT inválido se **descartan en silencio**: no incrementan el contador ni el mensaje de error. El `message` de la respuesta es `'OK'` mientras no haya habido una excepción, aunque se hayan perdido N filas.
- El dígito verificador **no se valida**, solo el formato.
- `formatearSexo()` cae en `"Masculino"` ante cualquier valor que no sea `M` o `F`.
- El importador **no escribe `status`**: queda el default de la base (`ingresado`).
- `CargaMasivaController` inyecta `ChequeoCardiovascularService` en su constructor y **nunca lo usa**.

---

## 3. Ciclo de vida del chequeo

El `perfiles_id` se obtiene siempre con `UserMetadataService::getPerfilIdByEmail()` y decide qué se escribe y qué se ve.

```mermaid
sequenceDiagram
    autonumber
    actor U as Cliente
    participant CCC as ChequeoCardiovascularController
    participant UMS as UserMetadataService
    participant CCS as ChequeoCardiovascularService
    participant CS as CertificadoService
    participant ECS as ElectroCardiogramaService
    participant DB as MySQL

    rect rgb(240, 253, 244)
    note over U,DB: Crear
    U->>CCC: POST chequeo-cardiovascular
    CCC->>UMS: getPerfilIdByEmail(user_email)
    UMS-->>CCC: perfiles_id
    alt perfil 2 (tester en terreno)
        CCC->>DB: INSERT con status = Testiado y fecha_atencion = now()
    else otro perfil
        CCC->>DB: INSERT, status queda en el default ingresado
    end
    end

    rect rgb(255, 251, 235)
    note over U,DB: Actualizar
    U->>CCC: PUT chequeo-cardiovascular/{id}/{user_email}
    CCC->>UMS: getPerfilIdByEmail(user_email)
    alt perfil 1 (administrador)
        CCC->>DB: UPDATE con status libre desde el request
        CCC->>CS: UpdateRutCertificado(id_chequeo, rut)
        CCC->>ECS: UpdateRutECG(id_chequeo, rut)
        note right of CCC: propaga el RUT para no dejar<br/>certificados y ECG huerfanos
    else perfil 2
        CCC->>DB: UPDATE con status = Testiado
    end
    end

    rect rgb(239, 246, 255)
    note over U,DB: Listar y buscar
    U->>CCC: POST chequeo-cardiovascular/search-chequeo
    CCC->>UMS: getPerfilIdByEmail(user_email)
    CCC->>CCS: SearchChequeo(perfilId, texto, fecha, club, email, limit, page)
    CCS->>DB: SELECT con filtro segun perfil
    CCS-->>CCC: paginado
    CCC-->>U: 200
    end
```

El filtrado por perfil (3 = club, 6 = médico, resto = todo) está detallado en [modelo-datos.md](modelo-datos.md#autorización-por-perfil), y la máquina de estados en [la misma página](modelo-datos.md#máquina-de-estados-del-chequeo).

---

## 4. Certificado y facturación

**Es el efecto cruzado más fácil de pasar por alto del proyecto**: subir un documento no solo guarda un archivo, también cambia el estado del chequeo y **factura el mes del club**.

```mermaid
sequenceDiagram
    autonumber
    actor M as Personal clinico
    participant CUC as CertificadoUrlController
    participant CS as CertificadoService
    participant ES as EstadisticasService
    participant FS as public/Certificado
    participant DB as MySQL

    M->>CUC: POST carga-masiva-ecg (files[] + derivado_medico)

    loop por cada archivo
        CUC->>CUC: RUT desde el nombre del archivo
        CUC->>DB: SELECT chequeo_cardiovascular WHERE rut ORDER BY id DESC LIMIT 1
        CUC->>CS: subirCertificado(file, rut, id_chequeo, derivado_medico)

        CS->>DB: DELETE certificado_url WHERE rut_paciente AND id_chequeo
        CS->>FS: move() como {rut}-{id_chequeo}.{ext}
        CS->>CS: url_pdf = env('API_PATH_CER') + fileName
        CS->>DB: SELECT chequeo_cardiovascular findOrFail(id)
        CS->>DB: INSERT certificado_url
        CS->>DB: UPDATE chequeo_cardiovascular SET status = 'ECG FOTO'
        CS->>DB: SELECT params WHERE descripcion = 'VALOR-ECG' (firstOrFail)
        CS->>ES: PagoMensual(periodo, user_email, valor_ecg, "ADD")
        ES->>DB: CALL SP_pago_mensual(?, ?, ?, ?)
        CS-->>CUC: {success, file}
    end

    CUC-->>M: 200 {success, total, procesados}
```

**Cuatro advertencias que el diagrama hace visibles:**

1. **No hay idempotencia: cada subida vuelve a facturar.** Reintentar una carga fallida, o corregir un documento, suma otro cargo al mes del club. `carga-masiva-ecg` factura **una vez por archivo**: un lote de 200 PDF genera 200 cargos.
2. **No hay transacción.** Si falta la fila `VALOR-ECG` en `params`, el `firstOrFail()` lanza un 500 **después** de haber movido el archivo, insertado el certificado y cambiado el `status`. El chequeo queda en un estado inconsistente.
3. **`CertificadoUrlController::FileUploadCer` duplica a mano** toda esta lógica en vez de llamar a `CertificadoService::subirCertificado()`. Si cambias la regla de facturación hay que tocar los dos sitios.
4. `url_pdf` se arma con `env('API_PATH_CER')`: otra razón por la que `config:cache` rompe producción.

El reverso manual es `POST estadisticas/delete-pago-mensual`, que hace un `DELETE` físico sobre `pago_mensual` por `(club, periodo)`, sin confirmación y sin control de acceso.

---

## 5. ECG del cardiólogo

Segundo camino de facturación, con la misma estructura pero distinto estado final.

```mermaid
sequenceDiagram
    autonumber
    actor C as Cardiologo
    participant ECC as ElectroCardiogramaController
    participant ES as EstadisticasService
    participant DB as MySQL

    C->>ECC: POST electro-cardiograma/save
    ECC->>DB: DELETE electro_cardiogranas WHERE id_chequeo
    ECC->>DB: INSERT electro_cardiogranas<br/>estado_paciente Normal o Alterado,<br/>frecuencia, derivacion, observacion
    ECC->>DB: UPDATE chequeo_cardiovascular SET status = 'REVISION MEDICA'
    ECC->>DB: SELECT params WHERE descripcion = 'VALOR-ECG' (firstOrFail)
    ECC->>ES: PagoMensual(periodo, user_email, valor_ecg, "ADD")
    ES->>DB: CALL SP_pago_mensual(?, ?, ?, ?)
    ECC-->>C: 200 {response: {status, mensaje}}
```

> ⚠️ Un chequeo que pasa **primero por certificado y luego por ECG factura dos veces**: son dos llamadas independientes a `SP_pago_mensual(..., "ADD")` y ninguna comprueba si ya existe el cargo.

Consultar el ECG (`POST electro-cardiograma/find-by-rut`) tiene su propia trampa: el `catch` devuelve **HTTP 200 con datos vacíos**, así que un error de base se ve igual que un paciente sin ECG.

---

## 6. Generación de documentos

```mermaid
sequenceDiagram
    autonumber
    actor U as Cliente
    participant CCC as ChequeoCardiovascularController
    participant PDF as ChequeoCardiovascularPDFService
    participant DB as MySQL
    participant MP as mPDF

    alt por RUT
        U->>CCC: GET chequeo-cardiovascular/pdfRut/{rut}
        CCC->>DB: SELECT chequeo_cardiovascular WHERE rut ORDER BY id DESC
        CCC->>CCC: delega en ChequeoPDF(id)
    else por id
        U->>CCC: GET chequeo-cardiovascular/pdf/{id}
    end

    CCC->>PDF: chequeoPDf(id_paciente)
    PDF->>DB: SELECT chequeo_cardiovascular WHERE id
    PDF->>DB: SELECT electro_cardiogranas WHERE id_chequeo
    PDF->>DB: SELECT certificado_url WHERE id_chequeo
    PDF->>PDF: getPercentil(imc, edad, sexo)

    alt certificado_url.derivado_medico = SI
        PDF->>PDF: firma_cardiologo.jpeg
    else
        PDF->>PDF: firmarDoctor.jpg
    end

    alt ECG con mas de 3 meses
        PDF->>PDF: estampa watermark.png y omite la firma medica
    end

    PDF-->>CCC: HTML como string
    CCC->>MP: WriteHTML() y Output()
    MP-->>U: application/pdf
```

- Los assets (`logo.png`, `firmarDoctor.jpg`, `firmarErgo.jpg`, `watermark.png`, `firma_cardiologo.jpeg`) se referencian con `public_path()`.
- Si `chequeo_cardiovascular.fileName` está lleno, el PDF embebe `public/Electrocardiograma/{fileName}` como imagen final.
- La presión se imprime como `presion_sistolica/presionArterial` — en esta tabla **`presionArterial` guarda la diastólica**.
- La variante Word (`GET chequeo-cardiovascular-word/{id}`) usa `ChequeoCardiovascularWordService` con PHPWord y **reutiliza `getPercentil()`** del servicio de PDF.

---

## 7. Bioimpedancia con IA

Extracción de datos desde la foto o el PDF del informe de la balanza.

```mermaid
sequenceDiagram
    autonumber
    actor U as Cliente
    participant BIC as BioimpedanciaController
    participant BIS as BioimpedanciaService
    participant P as AnalisisBioimpedanciaPrompt
    participant AI as OpenAI gpt-4.1-mini
    participant DB as bioimpedancia

    U->>BIC: POST bioimpedancia/form-upload (imagen o PDF + rut)
    BIC->>BIS: firstByRutChequeoBio(rut)
    BIS->>DB: ultimo registro de bioimpedancia
    alt no existe
        BIS->>DB: fallback a chequeo_cardiovascular por rut
    end
    BIS-->>BIC: {rut, nombre, club}

    BIC->>P: system()
    BIC->>AI: chat con la imagen en base64
    AI-->>BIC: JSON con los campos del informe

    alt JSON valido
        BIC->>BIS: saveFromAI(parsed, rut, nombre, club, fileName)
        BIS->>BIS: mapBioimpedancia() y cleanNumber()
        BIS->>DB: updateOrCreate por rut
    else no se pudo parsear
        BIC->>DB: INSERT guardando raw_json
    end

    BIC-->>U: 200 {success, message, data}
```

Las claves del JSON que produce el prompt son exactamente las columnas de la tabla `bioimpedancia`; `mapBioimpedancia()` es el punto donde se traduce.

---

## 8. Informe de bioimpedancia

El otro sentido: interpretar datos ya guardados y redactar el informe en PDF.

```mermaid
sequenceDiagram
    autonumber
    actor U as Cliente
    participant BIC as BioimpedanciaController
    participant BIS as BioimpedanciaService
    participant DB as MySQL
    participant P as AnalisisRutBioimpedanciaPrompt
    participant AI as OpenAI gpt-4.1-mini
    participant MP as mPDF

    U->>BIC: GET bioimpedancia/pdfRut/{rut}
    BIC->>BIS: BioPDFRut(rut_paciente)
    BIS->>DB: CALL SP_bioimpedacia_rut(?)
    DB-->>BIS: resultado_json
    BIS->>BIS: prepararPayloadIA(ficha)
    note right of BIS: aplana segmentario_musculo y<br/>segmentario_grasa, descarta nulos
    BIS->>P: system()
    BIS->>AI: analizarConIA(payload)
    AI-->>BIS: analisis redactado
    BIS->>BIS: seccion(), parrafo(), pieInforme()
    BIS-->>BIC: HTML del informe
    BIC->>MP: WriteHTML() y Output()
    MP-->>U: application/pdf
```

> El método del modelo se llama `SP_bioempdacia_rut()` y el procedimiento `SP_bioimpedacia_rut`. **Son dos typos distintos, ambos consolidados**: respétalos.

---

## 9. Asistente clínico SAM

Chat por paciente. La sesión guarda el paciente activo; el historial se guarda por paciente.

```mermaid
sequenceDiagram
    autonumber
    actor U as Cliente
    participant OAC as OpenAIController
    participant OAS as OpenAIService
    participant PH as PatientHelper
    participant ES as EstadisticasService
    participant AI as OpenAI
    participant DB as MySQL

    U->>OAC: POST sam-assistant/as-question {prompt, sessionId}
    OAC->>OAS: resolveSession(sessionId, prompt)
    OAS->>DB: ChatSessions::firstOrCreate(session_id)

    alt la sesion ya tiene paciente
        OAS-->>OAC: sesion tal cual
    else sin paciente
        OAS->>PH: extractPatient(prompt)
        PH->>PH: regex de RUT /\d{7,8}-[\dkK]/
        alt sin coincidencia
            PH->>AI: gpt-4o-mini temperature 0
            AI-->>PH: nombre literal o NULL
        end
        PH-->>OAS: identificador o null
        OAS->>DB: UPDATE chat_sessions SET patient_identifier
    end

    alt sin paciente resuelto
        OAC-->>U: 200 {status: needs_identifier}
    else con paciente
        OAC->>OAS: handle(session, prompt)
        OAS->>DB: INSERT chat_history role user
        OAS->>DB: SELECT chat_history ORDER BY created_at ASC LIMIT 20
        OAC->>ES: ChequeoPrompt(search)
        ES->>DB: CALL SP_chequeos_prompt(?)
        OAC->>AI: gpt-4o-mini temperature 0.2 con 4 mensajes system + historial
        AI-->>OAC: respuesta
        OAC->>DB: INSERT chat_history role assistant con context_json
        OAC-->>U: 200 {sessionId, patient, response}
    end
```

**Tres particularidades:**

- El paciente de la sesión es una **puerta de un solo sentido**: una vez fijado, `resolveSession()` ya no vuelve a intentar extraerlo. Para cambiarlo hay que llamar a `POST sam-assistant/reset-patient`, que limpia solo `patient_identifier` y conserva el historial.
- El historial se agrupa por `patient_identifier` y **no guarda `session_id`**: dos sesiones distintas que hablen del mismo paciente comparten la conversación.
- ⚠️ `handle()` usa `orderBy('created_at', 'asc')->limit(20)`, así que recupera los **20 mensajes más antiguos**, no los más recientes. A partir del mensaje 21 el contexto conversacional queda congelado en el inicio. El asistente por club no tiene este problema.

---

## 10. Asistente por club

Flujo paralelo al anterior, construido sin tocarlo. En vez de girar en torno a un paciente, responde sobre **el conjunto de pacientes en `REVISION MEDICA` de un club**.

```mermaid
sequenceDiagram
    autonumber
    actor U as Club
    participant CAC as ClubAssistantController
    participant CAS as ClubAssistantService
    participant P as AsistenteChatClubPrompt
    participant AI as OpenAI gpt-4o-mini
    participant DB as MySQL

    U->>CAC: POST sam-assistant-club/as-question {email, prompt, search?, sessionId}
    CAC->>CAS: resolveSession(sessionId, email, search)
    CAS->>DB: ChatClubSessions::firstOrCreate(session_id, club_email)
    note right of CAS: club_email es NOT NULL sin default<br/>y la base corre en STRICT_TRANS_TABLES

    CAC->>CAS: datosClub(search efectivo, clubEmail)
    CAS->>DB: CALL SP_chequeos_club_prompt(?, ?)
    DB-->>CAS: resultado_json
    CAS->>CAS: normalizarPresion() invierte diastolica/sistolica
    CAS->>CAS: trunca en MAX_PACIENTES = 120

    alt sin pacientes
        CAC-->>U: 200 {status: sin_datos}
    else con pacientes
        CAC->>CAS: handle(session, prompt)
        CAS->>DB: INSERT chat_club_history role user
        CAS->>DB: SELECT los 20 mas recientes (desc + reverse)
        CAC->>P: system(email, search, pacientes, total, truncado)
        CAC->>AI: chat temperature 0.2
        AI-->>CAC: respuesta
        CAC->>CAS: guardarRespuesta(session, texto, pacientes)
        CAS->>DB: INSERT chat_club_history role assistant con context_json
        CAC-->>U: 200 {sessionId, club, search, response}
    end
```

La semántica del campo `search` es el detalle importante para el cliente, porque **se persiste en la sesión**:

| Cómo se envía | Efecto |
|---|---|
| El campo no viene en el JSON | Se conserva el `search_actual` que ya tenía la sesión |
| `"search": ""` | Reset explícito: el chat pasa a hablar de todo el club |
| `"search": "Fuentes"` | Se guarda ese filtro y el chat queda acotado a ese paciente |

`POST sam-assistant-club/reset-search` equivale a enviar `"search": ""`.

Notas adicionales: el prompt recibe el total real y una marca de truncado para que el asistente avise cuando ve solo una parte; `SP_chequeos_club_prompt` filtra `status = 'REVISION MEDICA'`, así que **el chat de club no ve chequeos en otros estados**; y, como `ChatComercialController` (§16), **captura `ValidationException` por separado** y responde un 422 correcto.

---

## 11. Pago WebPay

Integración por HTTP crudo, **sin el SDK de Transbank**.

```mermaid
sequenceDiagram
    autonumber
    actor U as Paciente
    participant WPC as WebPayController
    participant TBK as Transbank
    participant DB as MySQL
    participant LOG as logs_api

    rect rgb(240, 253, 244)
    U->>WPC: POST transbank/web-pay-request {servicios_name, rut, monto}
    WPC->>WPC: buy_order = Ymd + servicios_name, session_id = rut
    WPC->>TBK: POST con Tbk-Api-Key-Id y Tbk-Api-Key-Secret
    alt status 200
        TBK-->>WPC: {token, url}
        WPC-->>U: JSON crudo de Transbank
    else error
        WPC->>LOG: INSERT rutaWeb, mensaje
        note right of WPC: el catch NO devuelve respuesta:<br/>cuerpo vacio con HTTP 200
    end
    end

    U->>TBK: paga en el formulario de Transbank
    TBK->>WPC: redirect a WEBPAY_RETURN

    rect rgb(255, 251, 235)
    WPC->>WPC: lee $_GET['token_ws'] directamente, no del Request
    WPC->>TBK: PUT {WEBPAY_URL}/{token_ws}
    TBK-->>WPC: detalle de la transaccion
    WPC->>DB: INSERT web_pay_info con rut_paciente = session_id
    WPC->>DB: SELECT agenda_horas WHERE rut_paciente firstOrFail
    WPC->>DB: UPDATE agenda_horas SET pagado_paciente = 'PAGADO'
    WPC-->>U: redirect a https://ergosanitas.com
    end
```

**Tres problemas conocidos de este controlador:**

1. Es el único lugar del código que persiste errores (`logs_api`), pero el `catch` **no devuelve respuesta**: un fallo de Transbank sale como cuerpo vacío.
2. **No se valida `response_code` ni `status`** de Transbank antes de marcar la reserva como `PAGADO`.
3. El `firstOrFail()` sobre `agenda_horas` toma la **primera** reserva del RUT, no la última: con dos reservas del mismo paciente marca la equivocada.

`WEBPAY_URL`, `WEBPAY_ID`, `WEBPAY_SECRET` y `WEBPAY_RETURN` se leen con `env()` dentro del controlador.

---

## 12. Agenda, correo y estadísticas

```mermaid
sequenceDiagram
    autonumber
    actor U as Paciente
    participant AHC as AgendaHorasController
    participant EMC as EmailController
    participant M as EmailMailable
    participant SMTP as SMTP
    participant DB as MySQL

    U->>AHC: POST agenda-horas {datos del paciente, servicios_name, fecha}
    AHC->>AHC: json_decode(php://input) solo para verificar que es array
    AHC->>DB: SELECT servicios WHERE nombre firstOrFail
    AHC->>DB: INSERT agenda_horas con servicios_id
    AHC-->>U: 201 {data, status: OK, mensaje}

    U->>EMC: POST email/reserva-hora {rut_paciente}
    EMC->>DB: SELECT agenda_horas WHERE rut_paciente ORDER BY id DESC
    EMC->>M: new EmailMailable(subject, html)
    EMC->>SMTP: Mail::to(email_paciente)->send()
    EMC->>SMTP: copia fija a ergosanitas@gmail.com
    EMC-->>U: 201 {response: {status, mensaje}}
```

El envío es **síncrono, sin cola**: `EmailMailable` importa `ShouldQueue` pero no lo implementa. `GET agenda-horas` devuelve la lista completa, sin filtro por club ni paginación.

### Estadísticas

Casi todo el cálculo estadístico vive en MySQL, no en PHP. El patrón es siempre el mismo:

```mermaid
sequenceDiagram
    autonumber
    actor U as Cliente
    participant C as EstadisticasController<br/>o IncidenciasController
    participant S as EstadisticasService<br/>o IncidentesService
    participant M as Modelo
    participant DB as MySQL

    U->>C: GET estadisticas/... o incidencia-deportivos/...
    C->>S: metodo del servicio
    S->>M: wrapper estatico
    M->>DB: DB::select('CALL SP_xxx(?)')
    DB-->>M: columna resultado_json
    S->>S: json_decode()
    S-->>C: array
    C-->>U: 200
```

La lista completa de procedimientos y a qué endpoint corresponde cada uno está en [modelo-datos.md](modelo-datos.md#procedimientos-almacenados).

> Los 19 procedimientos que invoca la API están listados en [modelo-datos.md](modelo-datos.md#procedimientos-almacenados). La base declara 23: los cuatro restantes (`SP_cargar_desde_chequeo`, `SP_filtros_dashboard`, `SP_procesar_mes` y `tmp_call_sp`) no los llama ningún endpoint.

---

## 13. Juego de cartas por niveles

Evaluación gamificada por club. Cada paciente se convierte en una **carta** con cuatro atributos comparables 0–100, puntaje, estrellas, badge clínico y barra de completitud. **Nada se persiste**: cada llamada recalcula al vuelo.

Los umbrales y colores no están en el SP sino en la tabla `juego_niveles`, así que se retunean con un `UPDATE` sin tocar el procedimiento ni el código PHP.

```mermaid
sequenceDiagram
    autonumber
    actor U as Colegio / Club
    participant C as JuegoCartasController
    participant S as JuegoCartasService
    participant M as JuegoCartaClub
    participant DB as MySQL
    participant SP as SP_juego_cartas_club

    U->>C: GET juego-cartas/{user_email}?search=
    C->>S: CartasClub(search, club)
    S->>M: SP_juego_cartas_club(search, club)
    M->>DB: CALL SP_juego_cartas_club(?, ?)
    DB->>SP: ejecuta

    Note over SP: Capa 1 — una carta por RUT<br/>GROUP_CONCAT + SUBSTRING_INDEX<br/>(sin window functions en 5.7)
    SP->>DB: chequeo_cardiovascular (ultimo por rut)
    SP->>DB: LEFT JOIN electro_cardiogranas (acotado con MAX(id))
    SP->>DB: LEFT JOIN bioimpedancia (acotado con MAX(id))
    SP->>DB: EXISTS certificado_url (bloque de completitud)

    Note over SP: Capa 2 — cuatro atributos 0-100<br/>ROUND(100 * pts / max_presentes)<br/>NULL si no hay ningun sub-indicador
    Note over SP: Capa 3 — puntaje = AVG(medidos) + bonus<br/>bonus 0-10 por CALIDAD de bioimpedancia
    Note over SP: Capa 4 — banda -1 si no hay atributos<br/>estrellas = CEIL(puntaje/20)

    SP->>DB: JOIN juego_niveles (tipo=clinico) por banda
    SP->>DB: JOIN juego_niveles (tipo=completitud) por bloques
    SP-->>DB: JSON_ARRAYAGG -> resultado_json
    DB-->>M: 1 fila, 1 columna
    M-->>S: array

    S->>S: json_decode defensivo
    Note over S: JSON_ARRAYAGG NO respeta ORDER BY en 5.7:<br/>el usort de PHP es el unico orden determinista
    S->>S: usort: puntaje DESC, atributos_medidos DESC<br/>sin_evaluar al final
    S-->>C: array de cartas
    C-->>U: 200 {success, message, data:{club, search, total, cartas}}
```

**El efecto que no se ve en el diagrama:** un alumno cargado por Excel (`ChequeoImport` solo puebla nombre, rut, fechaNacimiento, sexo_paciente, edad, division_paciente y user_email) **no tiene ningún signo vital**. Sus cuatro atributos quedan en `null`, el puntaje es `null` y la banda es el centinela `-1` → badge `SIN EVALUAR`. Sin esa regla caería en `BAJO`, que ante el colegio se leería como "este alumno está mal de salud" cuando en realidad significa "nunca lo midieron". Hoy son 201 de 1.554 pacientes.

Los otros dos endpoints reutilizan el mismo procedimiento y el mismo service:

- `GET juego-cartas/detalle/{rut_paciente}` → `SP_juego_cartas_club(rut, NULL)`, **sin filtro de club**. Un RUT inexistente devuelve 200 con `data: null`.
- `GET juego-cartas/niveles` → no toca el SP: lee `juego_niveles` y `juego_atributos` con Eloquent.

> **El orden de declaración de las rutas es obligatorio.** `juego-cartas/niveles` y `juego-cartas/detalle/{rut}` van **antes** de `juego-cartas/{user_email}` en `routes/api.php`, o `niveles` se resolvería como un club llamado `"niveles"`.

---

## 14. Carro de servicios

Spec 04 (`servicio-cart-flow`). Flujo paralelo al 11 y al 12, que no cambian. El cliente **nunca** manda montos: cada respuesta del carro trae el desglose que calcula `CalculadoraCarroService`.

```mermaid
sequenceDiagram
    autonumber
    actor U as Cliente (front /servicios)
    participant CC as CarritoController
    participant CS as CarritoService
    participant CALC as CalculadoraCarroService
    participant AG as AgendaPedidoController
    participant CK as CheckoutService
    participant WP as WebPayCartFlowService
    participant TBK as Transbank
    participant DB as MySQL (cf_*)

    U->>CC: POST cart-flow/carritos {comuna_id}
    CC->>DB: cf_carritos (uuid, expira_en +7d) + primer paciente
    CC-->>U: 201 carro
    U->>CC: POST cart-flow/carritos/{uuid}/lineas {paciente_id, sku}
    CC->>CS: agregarLinea (rechaza ocultos y de cotizacion)
    CS->>CALC: calcular(comuna, plan, pacientes, automaticos)
    Note over CALC: toma de muestra 1 por paciente,<br/>TRUTROL, planes solo en servicios,<br/>domicilio 1 vez (mixta = el mayor),<br/>redondeo al $1.000, ticket minimo
    CC-->>U: 201 carro + desglose + preparacion + bloqueos
    U->>AG: GET cart-flow/agenda/bloques?carrito={uuid}
    AG-->>U: dias y bloques (solo manana si hay ayuno o laboratorio)
    U->>CC: POST cart-flow/carritos/{uuid}/checkout (multipart)
    CC->>CK: checkout
    CK->>CALC: recalcula a la fecha de la cita
    CK->>DB: BEGIN · lockForUpdate(bloque) · cupo libre?
    CK->>DB: cf_pedidos + pacientes + lineas (snapshot) · carrito convertido · COMMIT
    CC-->>U: 201 pedido + token_consulta
    alt medio_pago = webpay
        U->>AG: POST cart-flow/pedidos/{numero}/webpay {t}
        AG->>WP: iniciar (monto = cf_pedidos.total, buy_order unico)
        WP->>TBK: POST transactions
        AG-->>U: {token, url} → form POST token_ws
        TBK->>AG: GET|POST cart-flow/webpay/retorno?token_ws
        AG->>WP: retorno → PUT transactions/{token}
        Note over WP: pagado solo si AUTHORIZED,<br/>response_code 0, monto y buy_order iguales
        AG-->>U: 302 /servicios/resultado?pago=aprobado|rechazado&pedido&t
    else transferencia o efectivo
        Note over CK: pedido pendiente_pago + cf_pagos iniciado:<br/>Gerencia lo confirma
    end
```

- **Cupo**: `cupo` del bloque menos los pedidos no anulados de esa fecha y bloque. El `lockForUpdate` sobre la fila del bloque serializa el último cupo; el segundo recibe 409 `bloque_lleno`.
- **Producto oculto con carro abierto**: al leer el carro la línea se quita (soft delete) y sale el aviso `producto_no_disponible`; el checkout responde 409 para que el cliente revise el total.
- **Webpay rechazado o cancelado**: el pedido sigue `pendiente_pago` y admite otro intento, con un `buy_order` nuevo.

---

## 15. Mantenedor del carro (Administrador)

Spec 05. El ítem «Carrito Compra» del front (`src/carrito-compra/`) lista, controla y cierra lo que entró por el flujo 14.
Todas las llamadas llevan el `user_email` de la sesión, y el back exige que sea de perfil `Administrador`.

```mermaid
sequenceDiagram
    autonumber
    actor A as Administrador (front /carrito-compra)
    participant AC as AdminCartFlowController
    participant CS as CatalogoService
    participant AS as AdminPedidoService
    participant DB as MySQL (cf_*)
    participant FS as Disco privado local

    A->>AC: GET cart-flow/admin/resumen · pedidos?filtros&page
    AC->>CS: esAdministrador(user_email)
    CS->>DB: users_metadata JOIN perfiles (nombre = Administrador)
    alt no es Administrador
        AC-->>A: 403 forbidden
    end
    AC->>AS: resumen() · listar(filtros)
    AS->>DB: COUNT/SUM por estado · paginate(20)
    AC-->>A: KPIs + pagina de pedidos
    A->>AC: GET cart-flow/admin/pedidos/{numero}
    AC-->>A: detalle + pagos + ordenes + historial + acciones permitidas
    A->>AC: PUT .../estado {accion: confirmar_pago}
    AC->>AS: cambiarEstado()
    AS->>AS: accionesPermitidas() — si no esta, 409
    AS->>DB: BEGIN · lockForUpdate(pedido) · pago aprobado · estado_pago y estado = pagado
    AS->>DB: cf_pedido_historial (x2) · COMMIT
    AC-->>A: detalle actualizado
    A->>AC: POST cart-flow/admin/ordenes/{id}/enlace
    AC->>AS: enlaceOrden() → URL::temporarySignedRoute (5 min)
    AC-->>A: {url, expira}
    A->>AC: GET .../ordenes/{id}/descarga?expires&signature
    Note over AC: middleware signed: firma invalida o vencida → 403
    AC->>FS: Storage::disk(local)->download()
    AC-->>A: archivo
```

- **Máquina de estados**: ver `modelo-datos.md` § «Máquina de estados del pedido del carro». `anular` exige motivo y
  libera el cupo, porque `AgendaService::usados()` no cuenta los anulados.
- **Webpay no se confirma a mano**: `confirmar_pago` solo existe para transferencia y efectivo. `atender` sobre un pedido
  en efectivo pendiente registra también el pago.
- **Catálogo**: `PUT cart-flow/admin/productos/{sku}/visibilidad` deja registro en `cf_visibilidad_log`. Si el producto
  no es publicable, responde 409 `requiere_confirmacion` y el front reenvía con `confirmar: true`.

---

## 16. Chat comercial «Ergosanito»

Tercer chat del sistema (Spec 06), **público y para visitantes anónimos** del Home. Responde solo sobre el catálogo público del carro: exámenes, precios de lista, comunas, operativos y preparación. Fue la **primera ruta con limitador** del repo; la segunda es la descarga de certificados (§17).

```mermaid
sequenceDiagram
    autonumber
    actor V as Visitante del Home
    participant TH as throttle:chat-comercial
    participant CCC as ChatComercialController
    participant CCS as ChatComercialService
    participant CACHE as Cache (tabla cache)
    participant P as AsistenteChatComercialPrompt
    participant AI as OpenAI gpt-4o-mini
    participant DB as MySQL

    V->>TH: POST chat-comercial/as-question {prompt, sessionId?}
    alt mas de 20 por minuto o 200 por dia desde la misma IP
        TH-->>V: 429 {message} + Retry-After
    end
    TH->>CCC: AsQuestionUseCase
    CCC->>CCC: validate prompt max 500 y sessionId max 64 (si falla, 422)
    CCC->>CCS: enmascarar(trim(prompt))
    Note over CCS: correo, telefono y RUT pasan a<br/>[EMAIL] [TELEFONO] [RUT] antes de guardar y de enviar
    CCC->>CCS: resolverSesion(sessionId)
    CCS->>DB: ChatComercialSessions::firstOrCreate (UUID nuevo si no es valido)
    CCC->>CCS: contexto()
    CCS->>CACHE: remember(chat-comercial:contexto, 600 s)
    alt la cache expiro
        CCS->>DB: cf_productos visibles, comunas, cf_operativo_tramos, reglas (lista blanca)
        CCS->>CACHE: {texto, hash sha256, truncado}
    end
    CCC->>P: system(contexto, config services.chat_comercial.contacto)
    CCC->>CCS: historial(session)
    CCS->>DB: SELECT los 10 mas recientes de chat_comercial_history (desc + reverse)
    CCC->>AI: [system estatico] + historial + pregunta enmascarada, temperature 0.3, max_tokens 400
    AI-->>CCC: respuesta (puede cerrar con [DERIVAR])
    CCC->>CCS: separarDerivacion(texto, contacto)
    CCC->>CCS: guardarTurno(...)
    CCS->>DB: BEGIN · INSERT user + INSERT assistant (derivar, contexto_hash, tokens) · UPDATE sesion · COMMIT
    CCC-->>V: 200 {sessionId, response, derivar}
    Note over CCC: cualquier excepcion: Log::error y 500 {message} generico,<br/>sin error, file ni line
```

- **No consulta datos clínicos.** El contexto sale solo de `cf_productos` (visibles, sin `toma_muestra`), comunas, `cf_operativo_tramos` y las reglas `ticket_minimo` / `horas_ayuno_defecto`, con lista blanca de columnas: nunca costos, notas internas ni precios por plan. Ninguna consulta a tablas clínicas, `chat_*` de los otros chats ni `SP_*`.
- **El mensaje de sistema es idéntico entre peticiones** mientras no expire la caché (nada variable dentro: ni fecha ni `sessionId`), para aprovechar el *prompt caching* automático de OpenAI.
- **Memoria por `sessionId`, no por IP.** Sin `sessionId` (o con uno que no es UUID) la conversación parte de cero y el back devuelve el UUID nuevo. La IP solo vive en la clave efímera del limitador.
- **Derivación**: si el modelo cierra con `[DERIVAR]` (o escribe el teléfono del contacto aunque olvide el marcador), `derivar` sale `true` y el front muestra el botón de WhatsApp. El contacto vive en `config('services.chat_comercial.contacto')`, no en `.env`.
- `tokens_cacheados` queda siempre `null`: `openai-php/client` v0.10.1 no lo expone.

---

## 17. Descarga de certificados por RUT

Página pública `/certificados` del front (Spec 07). Una sola consulta por RUT devuelve los documentos del último chequeo en `REVISION MEDICA` y de la bioimpedancia vigente; los PDF generados se descargan solo con URL firmada de 10 minutos.

```mermaid
sequenceDiagram
    autonumber
    actor U as Visitante de /certificados
    participant TH as throttle:certificados
    participant SG as signed
    participant CDC as CertificadoDescargaController
    participant CDS as CertificadoDescargaService
    participant BS as BioimpedanciaService
    participant AI as OpenAI gpt-4.1-mini
    participant DB as MySQL

    U->>TH: POST certificados/consulta {rut}
    alt mas de 10 por minuto desde la misma IP
        TH-->>U: 429 {success:false, message, data:null} + Retry-After
    end
    TH->>CDC: Consulta
    CDC->>CDC: normalizarRut + regex (si falla, 422 {success:false, message, errors})
    CDC->>CDS: consultar(rut)
    CDS->>DB: ultimo chequeo_cardiovascular en REVISION MEDICA + certificado_url (rut, id_chequeo)
    CDS->>DB: bioimpedancia vigente (fecha_prueba DESC, id DESC, igual que el SP)
    CDS->>CDS: URL::temporarySignedRoute para certificado_ecg y certificado_bio (10 min)
    CDS->>DB: INSERT certificado_descargas (tipo consulta)
    CDC-->>U: 200 {success, message, data:{rut, nombre, documentos[]}}

    U->>TH: GET certificados/descarga/bio/{id}?expires&signature
    TH->>SG: firma valida y vigente?
    alt firma ausente, manipulada o vencida
        SG-->>U: 403 {message: "Invalid signature."}
    end
    SG->>CDC: DescargaBio(id)
    CDC->>CDS: pdfCertificadoBio(id)
    CDS->>BS: fichaVigente(rut)
    BS->>DB: CALL SP_bioimpedacia_rut(rut)
    alt la ficha vigente no es {id}
        CDC-->>U: 404 {success:false, message:"Documento no disponible"}
    end
    CDS->>BS: BioPDFRut(rut, ficha)
    BS->>DB: bioimpedancia_analisis_ia por (bioimpedancia_id, sha256 del payload)
    alt no hay analisis guardado
        BS->>AI: AnalisisRutBioimpedanciaPrompt + payload
        AI-->>BS: JSON del analisis
        BS->>DB: INSERT bioimpedancia_analisis_ia
    end
    CDS->>CDS: mPDF
    CDS->>DB: INSERT certificado_descargas (certificado_bio, ok)
    CDC-->>U: 200 application/pdf (Cache-Control private, no-store)
```

- **Solo lee: no factura** ni cambia el estado del chequeo. El certificado ECG (`descarga/ecg/{id}`) sigue el mismo camino y renderiza con `ChequeoCardiovascularPDFService::chequeoPDf`, solo si el chequeo `{id}` está en `REVISION MEDICA`.
- **Los archivos subidos** (`ecg` desde `certificado_url.url_pdf`, `bio` desde `public/Bioimpedancia`) conservan su URL pública: los sirve el servidor web y **no** quedan en `certificado_descargas`.
- **OpenAI se llama una vez por versión de los datos**: si la bioimpedancia se recarga, cambia el hash y se agrega un análisis nuevo sin borrar el anterior.
- Los errores van al log con la **clase y el código** de la excepción, nunca su mensaje (puede traer el RUT). Al cliente, 500 genérico.

---

**Ver también:** [Arquitectura](arquitectura.md) · [Diagrama de clases](diagrama-clases.md) · [Modelo de datos](modelo-datos.md)
