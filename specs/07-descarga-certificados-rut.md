# SPEC 07 — Descarga de certificados por RUT: consulta única, URLs firmadas, análisis de bioimpedancia persistido y registro de descargas

> **Estado:** Aprobado
> **Depende de:** ninguna spec. Reutiliza el patrón de URL firmada y temporal de la SPEC 05 (`routes/api.php` L393–413, `AdminPedidoService.php` L328) y el patrón de limitador nombrado de la SPEC 06 (`ChatComercialServiceProvider.php` L30).
> **Fecha:** 2026-11-11
> **Consumidores:** spec hermana del front `Front-end/ergosanitas-app/specs/certificados/01-rediseno-descarga-certificados.md` (página pública `/certificados`). La app móvil **no** consume ninguno de los endpoints de este dominio (verificado con grep en `APP/V2/ergosanitasApp/src`: sin coincidencias para `certificado`, `bioimpedancia`, `pdfRut`).
> **Objetivo:** Que la página pública de certificados obtenga con **una sola llamada** los documentos del último chequeo en `REVISION MEDICA` y de la última bioimpedancia de un RUT, descargue los PDF generados por **URL firmada y temporal**, no vuelva a llamar a OpenAI en cada descarga de bioimpedancia y deje **registro de cada consulta y descarga**.

---

## 1 — Por qué existe esta spec

La pantalla pública «Descargar certificados» del front (`src/Certificados/`) hace hoy **cuatro
llamadas HTTP** por búsqueda (dos de ellas se descartan) y descarga cuatro documentos por caminos
distintos. En el back eso se traduce en:

- Endpoints sin `throttle` que responden por RUT a cualquiera: enumeración de pacientes.
- Un endpoint que **sin RUT devuelve el último certificado de cualquier paciente**.
- PDF de bioimpedancia que **llama a OpenAI en cada clic** (lento, con costo, resultado no
  determinista y envío repetido de datos clínicos a un tercero, Especificación legal §9.3).
- El PDF del certificado ECG sale del chequeo **más reciente en cualquier estado**, aunque no
  haya pasado la revisión médica.
- Ninguna traza de quién descargó qué (Especificación legal §7.4).

La Especificación legal §7.3 pide «enlaces temporales y autenticados para descarga de documentos»
y «proteger APIs contra enumeración de pacientes y abuso de endpoints». El usuario decidió
mantener el acceso **público por RUT**, pero con limitador, URL firmada temporal para los PDF
generados y registro de descargas.

### 1.1 Lo que se verificó en el código (2026-11-11)

| Hallazgo | Consecuencia |
| --- | --- |
| `routes/api.php` L118, L224, L230, L233, L328, L331: `chequeo-cardiovascular/pdfRut/{rut}`, `certificado/valida-certificado`, `certificado/validar/{rut}`, `certificado/{rut}`, `bioimpedancia/first-rut`, `bioimpedancia/pdfRut/{rut}` — **ninguna** con `throttle`, `signed` ni `auth` | El endpoint nuevo lleva limitador nombrado; las descargas, `signed`. |
| `CertificadoUrlController::ValidaCertificado` L221: `->when($rut_paciente, …)` — con body vacío no filtra por RUT y devuelve el último certificado en `REVISION MEDICA` de **cualquier** paciente | El endpoint nuevo **exige** RUT válido (422). El viejo queda fuera de alcance (ver §8) pero se deja anotado como riesgo. |
| `ValidaCertificado` L220 filtra `cc.status = 'REVISION MEDICA'`, pero `ChequeoCardiovascularController::ChequeoPDFRut` L571–573 toma el chequeo **más reciente sin filtrar estado** | Se corrige `ChequeoPDFRut` para usar el último chequeo en `REVISION MEDICA` (decisión del usuario). |
| `ChequeoCardiovascularController::ChequeoPDF(int $id)` L594–612 genera el certificado ECG con mPDF a partir de `ChequeoCardiovascularPDFService::chequeoPDf` | La descarga firmada del certificado ECG reutiliza `ChequeoPDF($id)` tal cual. |
| `BioimpedanciaController::BioPDFRut` L214–239 → `BioimpedanciaService::BioPDFRut` L608: `SP_bioempdacia_rut` (L619, wrapper L439) → `analizarConIA` (L637 → L557, `gpt-4.1-mini`) → HTML → mPDF | Se persiste el análisis de IA la primera vez; las siguientes descargas lo leen de la base. Beneficia también a `bioimpedancia/pdfRut`. |
| `BioimpedanciaService::firstByRut` L144–157: última bioimpedancia por `created_at`, `archivo` → `url('Bioimpedancia/'.archivo)` | La URL del archivo subido se mantiene pública (decisión del usuario). |
| `CertificadoUrlController::FileUploadCer` L110–120: el ECG subido vive en `public/Certificado/{rut}-{id_chequeo}.{ext}` y su URL es `env('API_PATH_CER').'/'.$fileName` | Los archivos subidos (`ecg`, `bio`) se quedan en `public/` con su URL actual; solo los PDF generados van por URL firmada. |
| `ValidarRut` L59 y `ValidaCertificado` L249 devuelven `$e->getMessage()` al cliente; `BioPDFRut` L236 también | El endpoint nuevo y las descargas firmadas **nunca** devuelven el mensaje de la excepción: `Log::error` y mensaje genérico. |
| Migraciones `2024_09_24_201401_create_chequeo_cardiovascular_table.php`, `2024_10_07_205117_create_certificado_url_table.php`, `2026_06_26_141515_create_bioimpedancia_table.php`: **sin índices** sobre `chequeo_cardiovascular.rut`, `certificado_url.rut_paciente` ni `bioimpedancia.rut` | Paso 1 (solo lectura con `ergosanitas-mysql`): confirmar en la BD real con `SHOW INDEX`. Solo si faltan, migración aditiva de índices. **No verificado aún contra la BD**: el MCP `mysql-server` no estaba disponible en la sesión que redactó la spec. |
| Patrón firmado existente: `URL::temporarySignedRoute('CartFlowOrdenDescarga', $expira, ['id' => …])` devuelve `['url' => …, 'expira' => $expira->toIso8601String()]` (`AdminPedidoService.php` L328–329); la ruta lleva `->middleware('signed')` (`routes/api.php` L411–414) | Mismo patrón y mismo formato de `expira`. |
| Patrón de limitador: `RateLimiter::for('chat-comercial', …)` en `ChatComercialServiceProvider.php` L30, con respuesta 429 propia | Mismo patrón en un provider nuevo del dominio. |
| Consumidores de los endpoints viejos en el front: `GET certificado/validar/{rut}` → `src/Login/services/useRegister.ts` L56; `GET certificado/{rut}` → `src/chequeo-administrador/services/useCertificadoService.ts` L22 y `src/chequeo-cardiovascular/services/useCertificadoService.ts` L22; `POST certificado/valida-certificado`, `POST bioimpedancia/first-rut`, `GET …/pdfRut/{rut}` → solo `src/Chequeo/services/useChequeoService.ts` L17–25, L165–192, llamados únicamente desde `src/Certificados/components/DownloadFile.tsx` | Los endpoints viejos **no cambian de forma ni de sobre**. El único cambio de comportamiento sobre uno viejo es el filtro de estado de `chequeo-cardiovascular/pdfRut`, cuyo único consumidor es la propia página de certificados (riesgo bajo, skill `ergo-contrato-api`). |

---

## 2 — Alcance

### Entra

- **A. Consulta única** `POST /api/certificados/consulta` con `{ rut }`: devuelve nombre y lista
  de documentos disponibles del último chequeo en `REVISION MEDICA` y de la última bioimpedancia.
- **B. Descargas firmadas** de los dos PDF generados (certificado ECG y certificado de
  bioimpedancia), con `URL::temporarySignedRoute` de **10 minutos** y middleware `signed`.
- **C. Corrección de `ChequeoPDFRut`**: último chequeo en `REVISION MEDICA`.
- **D. Análisis de IA de bioimpedancia persistido** (tabla aditiva): la primera generación lo
  guarda; las siguientes no llaman a OpenAI.
- **E. Registro de consultas y descargas** en la tabla aditiva `certificado_descargas`.
- **F. Limitador nombrado** `certificados` para la consulta y las descargas.
- **G. Índices por RUT**, solo si `ergosanitas-mysql` confirma que faltan en la BD real.
- **H. Documentación** con `ergo-docs`.

### No entra

Ver §8.

---

## 3 — Modelo de datos y contrato

### 3.1 Tabla nueva `certificado_descargas` (migración aditiva y reversible)

| Columna | Tipo | Nota |
| --- | --- | --- |
| `id` | `bigIncrements` | |
| `rut` | `string(12)` | RUT normalizado consultado (sin puntos, `k` minúscula). Índice. |
| `tipo` | `string(20)` | `consulta` · `certificado_ecg` · `certificado_bio` |
| `ip` | `string(45)` | `$request->ip()` (IPv4/IPv6). |
| `resultado` | `string(20)` | `encontrado` · `sin_resultados` · `ok` · `no_encontrado` · `error` |
| `created_at` | `timestamp` | Es la «fecha» del registro. Sin `updated_at`. |

- Solo **inserta**; no hay endpoint, comando ni botón que la lea, edite o borre (Ley 21.719).
- No guarda nombre, datos clínicos ni la URL firmada.
- `down()` hace `dropIfExists('certificado_descargas')`: es una tabla nueva sin datos productivos
  previos; si ya tiene datos en producción, el rollback **no** se ejecuta allí.
- Las descargas de los archivos subidos (`ecg`, `bio`) las sirve el servidor web desde `public/`
  y **no** pasan por Laravel: no quedan registradas (riesgo §7).

### 3.2 Tabla nueva `bioimpedancia_analisis_ia` (migración aditiva y reversible)

| Columna | Tipo | Nota |
| --- | --- | --- |
| `id` | `bigIncrements` | |
| `bioimpedancia_id` | `unsignedBigInteger` | id de `bioimpedancia`. Índice. Sin FK con `cascade` (nada se borra en cascada). |
| `rut` | `string(12)` | Índice. |
| `payload_hash` | `char(64)` | `sha256` del JSON que se envía a la IA (`prepararPayloadIA($ficha)`). |
| `modelo` | `string(40)` | `gpt-4.1-mini` hoy. |
| `analisis` | `json` | Respuesta ya parseada de `analizarConIA`. |
| `created_at`, `updated_at` | `timestamps` | |

- Índice único `(bioimpedancia_id, payload_hash)`.
- Regla: en `BioimpedanciaService::BioPDFRut`, tras armar el payload, se busca
  `(bioimpedancia_id, payload_hash)`. Si existe, se usa `analisis` y **no** se llama a OpenAI. Si
  no existe, se llama, se guarda y se usa. Como `saveFromAI` hace `updateOrCreate` por `rut`, si la
  bioimpedancia se recarga el hash cambia y se genera un análisis nuevo; **el anterior no se
  borra** (queda como histórico).
- Si OpenAI falla, no se guarda nada y la descarga responde error genérico.
- MySQL 5.7.22 soporta `json`; en los tests (SQLite) Laravel lo trata como texto.

### 3.3 Índices por RUT (condicional, paso 1)

Solo si `SHOW INDEX` en la BD real confirma que faltan: migración aditiva que crea
`chequeo_cardiovascular (rut, status)`, `certificado_url (rut_paciente, id_chequeo)` y
`bioimpedancia (rut)`, cada uno protegido con `Schema::hasIndex()` (Laravel 11) para no fallar
si alguno ya existe. `down()` elimina **solo** los índices que esta migración creó. Crear un índice
no modifica datos; el humano decide cuándo aplicarla en producción (bloqueo de tabla en 5.7).

### 3.4 Contrato — `POST /api/certificados/consulta`

- **Middleware:** `throttle:certificados`. Sin `auth` (decisión del usuario: acceso público por
  RUT).
- **Body:** `{ "rut": "11111111-1" }`. Se normaliza (quitar puntos y espacios, `K` → `k`) y se
  valida con `^\d{7,8}-[\dk]$`.
- **Sobre:** el del dominio vecino `bioimpedancia` (`success` / `message` / `data`,
  `BioimpedanciaController::FirstRut` L50–54).

Éxito con documentos (200):

```json
{
  "success": true,
  "message": "Documentos encontrados",
  "data": {
    "rut": "11111111-1",
    "nombre": "Paciente Ficticio",
    "documentos": [
      { "tipo": "certificado_ecg", "fecha": "2026-05-04", "url": "https://<api>/api/certificados/descarga/ecg/123?expires=1762870000&signature=…", "expira": "2026-11-11T15:10:00-03:00" },
      { "tipo": "ecg",             "fecha": "2026-05-04", "url": "https://<api>/Certificado/11111111-1-123.pdf", "expira": null },
      { "tipo": "certificado_bio", "fecha": "2026-06-30", "url": "https://<api>/api/certificados/descarga/bio/45?expires=1762870000&signature=…", "expira": "2026-11-11T15:10:00-03:00" },
      { "tipo": "bio",             "fecha": "2026-06-30", "url": "https://<api>/Bioimpedancia/archivo-ficticio.pdf", "expira": null }
    ]
  }
}
```

Sin documentos (200, **no** 404 — el front lo trata como estado vacío):

```json
{ "success": true, "message": "No hay documentos disponibles para este RUT", "data": { "rut": "11111111-1", "nombre": null, "documentos": [] } }
```

RUT ausente o inválido (422):

```json
{ "success": false, "message": "El RUT no tiene un formato válido.", "errors": { "rut": ["El RUT no tiene un formato válido."] } }
```

Demasiadas peticiones (429, con cabecera `Retry-After`):

```json
{ "success": false, "message": "Demasiadas consultas seguidas. Espera un momento y vuelve a intentarlo.", "data": null }
```

Error interno (500, sin `getMessage()`):

```json
{ "success": false, "message": "No fue posible consultar los documentos. Intenta más tarde.", "data": null }
```

Reglas de armado de `documentos` (orden fijo: `certificado_ecg`, `ecg`, `certificado_bio`, `bio`):

| `tipo` | Aparece si… | `fecha` | `url` | `expira` |
| --- | --- | --- | --- | --- |
| `certificado_ecg` | existe un `chequeo_cardiovascular` con ese `rut` y `status = 'REVISION MEDICA'` (se toma el de mayor `id`) | `certificado_url.created_at` de ese chequeo si existe; si no, `chequeo_cardiovascular.created_at` (`YYYY-MM-DD`) | firmada, ruta `CertificadosDescargaEcg`, `{id}` = id del chequeo | ISO 8601, ahora + 10 min |
| `ecg` | hay fila en `certificado_url` con `(rut_paciente, id_chequeo)` del chequeo anterior | `certificado_url.created_at` | `certificado_url.url_pdf` tal cual (pública, sin cambios) | `null` |
| `certificado_bio` | existe una `bioimpedancia` con ese `rut` (la de mayor `created_at`) | `bioimpedancia.created_at` | firmada, ruta `CertificadosDescargaBio`, `{id}` = id de la bioimpedancia | ISO 8601, ahora + 10 min |
| `bio` | esa bioimpedancia tiene `archivo` no nulo | `bioimpedancia.created_at` | `url('Bioimpedancia/'.archivo)` (igual que `firstByRut` L153) | `null` |

`nombre`: `chequeo_cardiovascular.nombre` del chequeo elegido; si no hay, `bioimpedancia.nombre`;
si no hay ninguno, `null`. Se devuelve con `ucwords(strtolower(…))`.

Cada consulta inserta una fila en `certificado_descargas` con `tipo = consulta` y `resultado`
`encontrado` o `sin_resultados` (el 422 y el 429 no se registran).

### 3.5 Contrato — descargas firmadas

| Método y ruta | Middleware | Nombre | Respuesta |
| --- | --- | --- | --- |
| `GET /api/certificados/descarga/ecg/{id}` | `signed`, `throttle:certificados` | `CertificadosDescargaEcg` | PDF (`ChequeoPDF($id)`), solo si el chequeo `{id}` está en `REVISION MEDICA` |
| `GET /api/certificados/descarga/bio/{id}` | `signed`, `throttle:certificados` | `CertificadosDescargaBio` | PDF de `BioimpedanciaService::BioPDFRut` para el `rut` de la bioimpedancia `{id}` |

- `{id}` con `->whereNumber('id')`. Las rutas literales `certificados/consulta` y
  `certificados/descarga/...` no chocan con `certificado/{rut_paciente}` (L233: prefijo distinto,
  singular), pero se declaran **juntas, antes** del bloque `certificado/*` con un comentario
  `// SPEC 07`.
- Firma inválida o vencida → **403** (lo responde el middleware `signed` de Laravel).
- `{id}` inexistente, o chequeo que no está en `REVISION MEDICA` → 404
  `{ "success": false, "message": "Documento no disponible", "data": null }`.
- Error interno → 500 con mensaje genérico; `Log::error` con el detalle.
- Cada descarga inserta en `certificado_descargas` (`tipo` = `certificado_ecg` | `certificado_bio`,
  `resultado` = `ok` | `no_encontrado` | `error`).
- Respuesta PDF con `Content-Type: application/pdf`,
  `Content-Disposition: attachment; filename="Certificado-<Nombre>.pdf"` y
  `Cache-Control: private, no-store` (documento clínico: sin caché compartida).

### 3.6 Limitador `certificados`

`RateLimiter::for('certificados', …)`: **10 por minuto por IP** para `certificados/consulta` y
**30 por minuto por IP** para las descargas (`Limit::perMinute(…)->by($request->ip())`, dos
límites distinguidos por el nombre de la ruta), con la respuesta 429 de §3.4. Los valores
quedan como constantes del provider para ajustarlos sin tocar la lógica.

### 3.7 Service y provider

- `app/Services/CertificadoDescargaService.php`: `consultar(string $rut): array`,
  `registrar(string $rut, string $tipo, string $ip, string $resultado): void`,
  `urlFirmada(string $ruta, int $id): array` (patrón `AdminPedidoService.php` L328).
- `app/Providers/CertificadoDescargaServiceProvider.php`: `singleton()` del service + el
  `RateLimiter::for('certificados', …)` en `boot()`. Alta en `bootstrap/providers.php`.
- `app/Http/Controllers/CertificadoDescargaController.php`: `Consulta`, `DescargaEcg`,
  `DescargaBio`. La descarga ECG delega en `ChequeoCardiovascularController::ChequeoPDF` (o en el
  service de PDF que usa) sin duplicar la lógica de mPDF.
- `app/Models/CertificadoDescarga.php` y `app/Models/BioimpedanciaAnalisisIa.php`.
- **Perfiles 3 y 6:** no aplican (ruta pública por RUT; no toca `filterCalendar()`,
  `SearchChequeo()` ni `ChequeoEmailAll()`).
- **Facturación:** solo lee; **no** pasa por `subirCertificado()` ni factura el mes del club.
- **SP:** no cambia ninguna firma. `SP_bioimpedacia_rut` se sigue llamando igual (wrapper
  `Bioimpedancia::SP_bioempdacia_rut`).

---

## 4 — Plan de implementación

Rama `spec-07-descarga-certificados-rut`. Pausa y confirmación del usuario tras cada paso.

1. **Esquema real (solo lectura, `ergosanitas-mysql`).** `SHOW INDEX` de `chequeo_cardiovascular`,
   `certificado_url` y `bioimpedancia`; confirmar el formato real de `rut` (con/sin puntos, `k`/`K`)
   con un `SELECT` de metadatos/patrón, **sin traer filas de pacientes**. Decide si el paso 3 crea
   índices y si la normalización de §3.4 cubre los datos reales.
2. **Migraciones de tablas** (`ergosanitas-mysql`): `certificado_descargas` y
   `bioimpedancia_analisis_ia` (§3.1, §3.2). Aditivas y reversibles. Modelos.
3. **Migración de índices** (`ergosanitas-mysql`), solo si el paso 1 lo confirma (§3.3).
4. **Análisis de IA persistido** (`ergosanitas-developer`): `BioimpedanciaService::BioPDFRut` busca
   por `(bioimpedancia_id, payload_hash)` antes de `analizarConIA` y guarda tras la primera llamada.
5. **Corrección de `ChequeoPDFRut`** (`ergosanitas-developer`): filtro `status = 'REVISION MEDICA'`
   (`ChequeoCardiovascularController.php` L571).
6. **Service, provider, limitador y controlador** (`ergosanitas-developer`): §3.4–§3.7, rutas en
   `routes/api.php` con comentario `// SPEC 07`.
7. **Tests en SQLite** (`ergosanitas-developer`, skill `ergosanitas-testing`):
   `tests/Feature/Certificados/` con los casos de §5. OpenAI con `OpenAI::fake()`; el SP con un
   mock parcial de `BioimpedanciaService::SP_bioempdacia_rut` (en SQLite no hay SP). Datos
   ficticios (`11111111-1`). Base `CertificadosTestCase` copiada del patrón `CartFlowTestCase`
   (fuerza SQLite en memoria y migra solo las tablas que necesita), **sin tocar `phpunit.xml`**.
   Las tablas sin migración que el test necesite se crean dentro del test case con un esquema
   mínimo ficticio. Añadir `Certificados` al filtro del job `test` del CI
   (`--filter="CartFlow|ChatComercial|Certificados"`) se propone en este paso, con confirmación
   del usuario.
8. **Documentación** (`ergo-docs`): `docs/openapi.yaml` (3 rutas nuevas, sobres de §3.4/§3.5,
   cambio de comportamiento de `chequeo-cardiovascular/pdfRut`), diagramas de `docs/` afectados,
   Postman regenerado, catálogo de endpoints de `README.md`/`CLAUDE.md`.

---

## 5 — Criterios de aceptación

- [x] `php -l` sin errores en todos los archivos PHP nuevos o modificados.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda, base `af9cfa7` + cambios sin commit): `php -l` sobre los 13 archivos (5 nuevos de `app/`, 2 migraciones, 2 de tests, `ChequeoCardiovascularController.php`, `BioimpedanciaService.php`, `bootstrap/providers.php`, `routes/api.php`) → «No syntax errors detected» en los 13.
- [ ] `vendor/bin/pint --dirty` sin cambios pendientes.
  - ❌ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): `vendor/bin/pint --dirty --test` → `FAIL 13 files, 3 style issues` en `ChequeoCardiovascularController.php`, `BioimpedanciaService.php` y `routes/api.php`. Es formato preexistente que el preset `laravel` no acepta, y §5.1 lo dejó sin aplicar a propósito. `pint --test` solo sobre los 9 archivos nuevos → `PASS`.
- [x] `php artisan route:list --path=certificados` muestra `POST api/certificados/consulta` con
      `throttle:certificados` y las dos `GET api/certificados/descarga/{ecg|bio}/{id}` con `signed`
      y `throttle:certificados`.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): `route:list --path=certificados -v` → 3 rutas: `POST api/certificados/consulta` (`api`, `ThrottleRequests:certificados`) y `GET|HEAD api/certificados/descarga/bio/{id}` y `…/ecg/{id}` (`api`, `ThrottleRequests:certificados`, `ValidateSignature`).
- [x] `php artisan route:list --path=certificado` muestra intactas `certificado/validar/{rut_paciente}`,
      `certificado/{rut_paciente}`, `certificado/valida-certificado`, `certificado/path-url`,
      `certificado/save-url`; y `bioimpedancia/first-rut`, `bioimpedancia/pdfRut/{rut_paciente}`
      siguen registradas.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): `route:list --path=certificado` → las 5 rutas `certificado/*` con sus acciones originales de `CertificadoUrlController`, más las 3 nuevas. `--path=bioimpedancia` → `POST api/bioimpedancia/first-rut` y `GET api/bioimpedancia/pdfRut/{rut_paciente}`.
- [x] Test: `POST certificados/consulta` con RUT con chequeo en `REVISION MEDICA` y bioimpedancia
      con archivo devuelve 200 y los 4 `tipo` en el orden de §3.4; `url` de `certificado_*` contiene
      `signature=` y `expira` no es nulo; `ecg`/`bio` tienen `expira: null`.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): test `CertificadosTest::test_consulta_devuelve_los_cuatro_documentos_en_orden` en SQLite → pasa (`--filter=Certificados`: 12 passed, 122 assertions).
- [x] Test: un RUT cuyo único chequeo está en `ingresado` **no** produce `certificado_ecg` ni `ecg`.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): test `CertificadosTest::test_chequeo_ingresado_no_aparece` → pasa.
- [x] Test: RUT sin registros → 200 con `documentos: []`.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): test `CertificadosTest::test_rut_sin_registros_devuelve_lista_vacia` → pasa (además registra `sin_resultados`).
- [x] Test: body sin `rut` o con `rut` mal formado → 422 con `success: false` y `errors.rut`.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): test `CertificadosTest::test_rut_ausente_o_invalido_devuelve_422` → pasa (y no inserta nada en `certificado_descargas`).
- [x] Test: la petición n.º 11 en el mismo minuto desde la misma IP a `certificados/consulta` → 429
      con `success: false`.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): test `CertificadosTest::test_limitador_devuelve_429` → pasa (10 respuestas 200 y la 11.ª 429 con `success: false` y cabecera `Retry-After`).
- [x] Test: `GET certificados/descarga/ecg/{id}` sin `signature` → 403; con firma vencida
      (`travel` > 10 min) → 403; con firma válida → 200 `application/pdf` (o, si mPDF no corre en
      el entorno de test, el controlador recibe el id correcto, verificado con mock).
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): test `CertificadosTest::test_descarga_ecg_exige_firma_vigente` → pasa. Sin firma da 403; firmada da 200 `application/pdf` con `Content-Disposition` de adjunto (mPDF real sobre un HTML mínimo, con `chequeoPDf` simulado); con `travel(11)` da 403; un chequeo `ingresado` da 404.
- [x] Test: dos descargas firmadas de `certificados/descarga/bio/{id}` con `OpenAI::fake()` →
      OpenAI recibe **una sola** petición y existe una fila en `bioimpedancia_analisis_ia`.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): test `CertificadosTest::test_segunda_descarga_bio_no_llama_a_openai` → pasa (1 petición a `Chat`, 1 fila en `bioimpedancia_analisis_ia` y 2 registros `certificado_bio`/`ok`). El SP está simulado con un mock parcial.
- [x] Test: cada consulta y cada descarga inserta una fila en `certificado_descargas` con `tipo`,
      `ip` y `resultado` correctos.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): `CertificadosTest` comprueba `tipo`, `resultado` e `ip` (`127.0.0.1`) en la consulta (L46) y en la descarga `certificado_bio`/`no_encontrado` (L82). También cubre `certificado_ecg` con ok, no_encontrado y error, y `certificado_bio` con ok, no_encontrado y error (tests `test_descarga_bio_inexistente_y_error` y `test_descarga_bio_no_vigente_devuelve_404`), todos en verde. Todas las ramas pasan `$request->ip()` (`CertificadoDescargaController.php`).
- [x] Test: un error forzado en el service devuelve 500 con el mensaje genérico; el cuerpo **no**
      contiene el texto de la excepción.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): test `CertificadosTest::test_error_interno_no_expone_la_excepcion` → pasa (la descarga ECG y la consulta responden 500 con `success: false` y sin `DETALLE-INTERNO-SECRETO`).
- [x] `grep -n "getMessage" app/Http/Controllers/CertificadoDescargaController.php` no devuelve
      líneas que lleguen a `response()`.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): 2 coincidencias, L53 y L112, ambas dentro de `Log::error(...)`; ninguna llega a un `response()`.
- [x] `ChequeoPDFRut` usa `status = 'REVISION MEDICA'` (lectura del código + test).
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): `ChequeoCardiovascularController.php:574` → `->where('status', 'REVISION MEDICA')`. El test `CertificadosTest::test_pdf_rut_ignora_chequeos_sin_revision_medica` pasa (404 con un chequeo `ingresado`).
- [x] `php artisan test --filter=Certificados` en verde.
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): `CertificadosTestCase` fuerza `DB_CONNECTION=sqlite` y `:memory:` (L25, con asserts en L38–39). `php artisan test --filter=Certificados` → `12 passed (122 assertions)`.
- [x] Migraciones nuevas sin `DELETE`, `TRUNCATE` ni `DROP` sobre tablas existentes
      (`grep -nE "delete|truncate|drop" database/migrations/<nuevas>`: solo `dropIfExists`/`dropIndex`
      de lo que la propia migración crea, en `down()`).
  - ✅ Evidencia (2026-11-11, spec-verify-backend, 2.ª ronda): `grep -niE "delete|truncate|drop"` → solo `Schema::dropIfExists` de la tabla propia en `down()` (`120000:40` y `120100:43`). Ambos `down()` lanzan `RuntimeException` antes si `app()->isProduction()` (`120000:36-37` y `120100:39-40`).
- [ ] `docs/openapi.yaml` documenta las 3 rutas nuevas con sus sobres y valida (fase `ergo-docs`).
      ⏸ Documentadas y el YAML parsea (Symfony Yaml, 123 operaciones; Postman regenerado con cobertura 123/123). Falta `npx @redocly/cli lint`: no se pudo ejecutar en la sesión de implementación.
  - ⏸ No verificable por completo (2026-11-11, spec-verify-backend, 2.ª ronda): `openapi.yaml` documenta las 3 rutas, y las descargas tienen 403, 404 y 429 con `content` y la cabecera `Retry-After`. `verificar-cobertura.php` → «Rutas registradas: 123 | requests en la coleccion: 123 · Cobertura completa». Solo falta `npx @redocly/cli lint` (permiso denegado).

---

### 5.1 Resultado del cierre (2026-11-11)

- Rama `feat/descarga-certificados` (sin commit).
- `php -l`: sin errores en los 13 archivos PHP nuevos o tocados.
- `vendor/bin/pint` aplicado **solo a los archivos nuevos**. `pint --dirty` reformatearía enteros `ChequeoCardiovascularController.php`, `BioimpedanciaService.php` y `routes/api.php`, que el código existente no cumple: se dejó sin aplicar ahí para no enterrar el cambio.
- `php artisan route:list --path=certificados`: 3 rutas, consulta con `ThrottleRequests:certificados`, descargas con `ThrottleRequests:certificados` + `ValidateSignature`.
- `php artisan test --filter="CartFlow|ChatComercial|Certificados"`: 55 tests en verde (9 de esta spec).
- Singleton: `app(CertificadoDescargaService::class) === app(...)` → `true`.
- **Paso 3 (índices) no ejecutado**: el MCP `mysql-server` no estaba disponible para confirmar en la BD real que faltan. No se creó migración de índices.
- Las migraciones `2026_11_11_120000` y `2026_11_11_120100` **no se aplicaron** en ninguna base: las aplica una persona con `--path`.
- Desvío menor: si el insert en `certificado_descargas` falla, se registra en el log y la descarga continúa (no se le niega el certificado al paciente por un fallo de auditoría).

- **Ajustes tras la verificación (2026-11-11):**
  - La bioimpedancia vigente se elige como `SP_bioimpedacia_rut` (`fecha_prueba DESC, id DESC`), no por `created_at`; `fecha` de `certificado_bio` y `bio` es `fecha_prueba`. `pdfCertificadoBio($id)` obtiene la ficha con `BioimpedanciaService::fichaVigente()` y responde 404 si `ficha[id]` no es `{id}` (la medición ya no es la vigente). El fallback de `analisisPersistido` usa el mismo orden.
  - `rut` de las dos tablas nuevas pasa a `string(20)` (sin truncar). `down()` lanza excepción en producción para no perder trazabilidad.
  - Los logs de error registran clase y código de la excepción (`CertificadoDescargaService::contextoError`), nunca su mensaje.
  - Tests: 12 en `Certificados` (orden del SP, bio no vigente → 404, bio inexistente/error, `ip` en el registro). `CartFlow|ChatComercial|Certificados`: 58 en verde.

## 6 — Decisiones tomadas y descartadas

| Decisión | Motivo |
| --- | --- |
| Acceso **público por RUT** + `throttle` + URL firmada temporal + registro | Decisión del usuario. Autenticación del paciente queda para otra spec. |
| Solo el **último** chequeo en `REVISION MEDICA` y la **última** bioimpedancia | Decisión del usuario. No se listan históricos. |
| Archivos subidos (`ecg`, `bio`) siguen en `public/` con su URL actual | Decisión del usuario; mover a `storage/` exige migrar archivos y volumen en producción. |
| Análisis de IA persistido en tabla aparte con `payload_hash` | Evita llamar a OpenAI en cada descarga y se invalida solo si cambian los datos, sin sobrescribir históricos. Descartado: columna en `bioimpedancia` (se pisaría con `updateOrCreate`). Descartado: caché en `Cache::` (volátil, se pierde en cada redeploy). |
| Sin documentos → 200 con lista vacía | El front lo trata como estado vacío; un 4xx haría lanzar a axios. |
| Endpoint nuevo en vez de cambiar los viejos | Los viejos tienen consumidores (`useRegister.ts` L56, `useCertificadoService.ts` L22 ×2). |
| Sobre `success/message/data` | El del dominio vecino `bioimpedancia`. |
| Provider propio `CertificadoDescargaServiceProvider` en vez de ampliar `CertificadoProvider` | `CertificadoProvider` arma `CertificadoService` (que factura vía `EstadisticasService`); el dominio nuevo solo lee y además registra el limitador, como `ChatComercialServiceProvider`. |
| URL firmada de **10 minutos** | Decisión del usuario (~10 min); suficiente para clicar tras la consulta. |

---

## 7 — Riesgos identificados

| Riesgo | Mitigación |
| --- | --- |
| Los endpoints viejos siguen públicos y sin `throttle` (incluido `valida-certificado` sin RUT) | Fuera de alcance por decisión; queda listado en §8 como siguiente spec (deprecación). |
| Los archivos `ecg`/`bio` siguen en URL pública y predecible y **no** quedan registrados al descargarse | Decisión consciente del usuario; anotado para la spec de `storage/` privado. |
| Formato de `rut` en la BD distinto del normalizado (puntos, `K`) | Paso 1 lo confirma antes de codificar la normalización. |
| `ALTER TABLE … ADD INDEX` en MySQL 5.7 bloquea la tabla | La migración de índices la aplica el humano en ventana de bajo uso. |
| La URL firmada depende de `APP_URL`/`APP_KEY`; detrás de proxy el host puede no coincidir y dar 403 | Verificar `APP_URL` y `TrustProxies` en el despliegue (mismo caso que SPEC 05). |
| La página web usa el nuevo endpoint y deja sin consumidor a `valida-certificado`, `first-rut` y los dos `pdfRut` | Se mantienen; deprecación en otra spec. |
| Envío de datos de bioimpedancia a OpenAI (Especificación legal §9.3) | Se reduce a una vez por versión de los datos; quitar la IA queda fuera. |
| Sin SP en un entorno con solo `migrate` → `certificado_bio` da 500 | Mensaje genérico + `Log::error`; documentado en `ergo-docs`. |

---

## 8 — Lo que **no** entra en esta spec

- Autenticación del paciente (login, segundo factor, código por correo).
- Listar chequeos o bioimpedancias históricos.
- Mover archivos de `public/` a `storage/` privado.
- Modificar, deprecar o borrar `certificado/validar`, `certificado/{rut}`,
  `certificado/valida-certificado`, `bioimpedancia/first-rut` ni `bioimpedancia/pdfRut`.
- Corregir el `->when()` de `ValidaCertificado` (L221) ni los `getMessage()` de los endpoints viejos.
- El `->delete()` de `FileUploadCer` (L100–102).
- Quitar la IA del certificado de bioimpedancia o cambiar su prompt.
- Endpoint o pantalla para consultar `certificado_descargas`.
- Cambios en la app móvil.

---

## Estado de verificación

> Última verificación: 2026-11-11 (2.ª ronda, después de los «Ajustes tras la verificación» de §5.1) · `spec-verify-ergo-backend` · commit base `af9cfa7` + cambios sin commit · rama `feat/descarga-certificados`
> Entorno: tests en SQLite en memoria · **sin acceso al esquema** (los MCP `mysql-server` y `laravel-boost` no estaban disponibles) · sin peticiones HTTP

| Resultado | Criterios |
|---|---|
| ✅ Verificados | 16 |
| ❌ Fallan | 1 — n.º 2 (`pint --dirty`: formato preexistente en `ChequeoCardiovascularController.php`, `BioimpedanciaService.php` y `routes/api.php`; §5.1 lo dejó sin aplicar a propósito) |
| ⏸ No verificables | 1 — n.º 18 (`redocly lint` no ejecutado) |

**Mapa (graphify y git):** archivos nuevos: `CertificadoDescargaController`, `CertificadoDescargaService`, `CertificadoDescargaServiceProvider`, los modelos `CertificadoDescarga` y `BioimpedanciaAnalisisIa`, las migraciones `2026_11_11_120000` y `120100` y `tests/Feature/Certificados/`. Modificados: `ChequeoCardiovascularController.php`, `BioimpedanciaService.php`, `bootstrap/providers.php`, `routes/api.php`, `docs/*`, `README.md` y `CLAUDE.md`. La rama real es `feat/descarga-certificados`, no la `spec-07-…` que dice §4.

**Impactos:**
- El provider está registrado (`bootstrap/providers.php:8`, con su `singleton(`).
- No hay `env()` nuevo ni borrados.
- No queda `getMessage` en el controlador ni en el servicio.
- La cobertura Postman es de 123/123.
- `chequeo-cardiovascular/pdfRut` responde ahora 404 si el último chequeo no está en `REVISION MEDICA`. Es una decisión de la spec, y su único consumidor es la página de certificados.

**Revisión de calidad (agentes):** los hallazgos de la 1.ª ronda vinieron del padre (`ergosanitas-developer`, `ergosanitas-laravel`, `ergosanitas-mysql` y `ergo-docs`, en solo lectura). En esta ronda se revisó leyendo cada línea si quedaron resueltos.

| Hallazgo (1.ª ronda) | Severidad | Estado ahora (evidencia) |
|---|---|---|
| La bioimpedancia se elegía por `created_at`, distinto del SP, y la URL del id X podía entregar el PDF de otra bioimpedancia | mayor | **Resuelto.** La consulta ordena por `fecha_prueba` y luego `id` (`CertificadoDescargaService.php:94-100`), y `fecha` es `fecha_prueba` (L109, L117). `pdfCertificadoBio` usa `fichaVigente()` (L184) y responde 404 si no es la vigente. Lo confirman los tests `test_bioimpedancia_vigente_sigue_el_orden_del_sp` y `test_descarga_bio_no_vigente_devuelve_404`. |
| CI sin los tests de Certificados | mayor | **Abierto.** `.github/workflows/github-action.build.yml:68` sigue con `--filter="CartFlow|ChatComercial"`. La spec lo deja «con confirmación» del usuario. |
| Docs: decían 120 operaciones; faltaban el flujo, las clases y la variante del sobre A | mayor | **Resuelto.** `CLAUDE.md:11,15`, `README.md:964,1001`, `docs/README.md:9,11` y `docs/postman/README.md:3` ya dicen 123. El flujo está en `docs/flujos.md` y las clases en `docs/diagrama-clases.md`. La variante del sobre A está en `README.md:599`. |
| `BioimpedanciaService.php:570-572` hacía el fallback por `created_at` | menor | **Resuelto.** Ahora ordena por `fecha_prueba` y luego `id`. |
| Migraciones: `rut` era `string(12)` con truncado y `down()` podía borrar la auditoría | menor | **Resuelto.** `rut` es `string(20)` y ya no hay `mb_substr` en el servicio. `down()` lanza `RuntimeException` en producción. |
| `Log::error` con `getMessage()` | menor | **Resuelto.** El log usa `contextoError()` (`CertificadoDescargaService.php:225`; controlador L54 y L113). |
| `docs/openapi.yaml`: 403 y 429 sin `content` ni `Retry-After` | menor | **Resuelto.** Las descargas tienen 403, 404 y 429 con `content` y `Retry-After`. |
| `CLAUDE.md:90,230` desactualizado | menor | **Resuelto.** L90 lista los providers con dependencias y L230 incluye la 07. |
| `modelo-datos.md`: tablas nuevas fuera del ERD | menor | **Resuelto en parte.** Se documentan en la nota de L388. No se comprobó que estén dentro del `erDiagram`. |
| `pdfRut` responde 404 si el chequeo no está en revisión médica | menor | Aceptado: es una decisión de la spec. |
| La columna `json` exige MySQL ≥ 5.7.8 | menor | **Abierto.** La versión de producción sigue sin confirmar. |
| Índices por RUT (§3.3) | ⏸ | **Abierto.** Sin MCP no se pudo hacer `SHOW INDEX`, y el paso 3 sigue pendiente. |

**Buenas prácticas (Context7 / Boost):** no se ejecutó, porque los MCP no estaban disponibles. Los patrones `temporarySignedRoute` con `signed` y `RateLimiter::for` siguen las decisiones de las SPEC 05 y 06.

**Cobertura de tests:** 12 tests en SQLite (`12 passed, 122 assertions`) cubren los criterios 5 a 13 y 15, además del orden del SP y de la bioimpedancia no vigente.

**Recomendación:** no hay bloqueantes. Queda un ❌ formal de Pint sobre formato preexistente, ya documentado en §5.1, y un hallazgo mayor abierto (el CI), que necesita la confirmación del usuario. Se puede pasar a `Implementado` si se acepta el ❌ de Pint como decisión documentada. Decide una persona. El `Estado:` no se tocó y sigue en `Aprobado`.
