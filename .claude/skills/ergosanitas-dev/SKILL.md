---
name: ergosanitas-dev
description: Recetario operativo para desarrollar, modificar, probar y corregir módulos del backend Laravel de Ergosanitas respetando sus convenciones. Úsalo al crear un endpoint o dominio nuevo (controller/service/provider/model/ruta), al tocar queries filtradas por perfil, certificados y facturación, prompts de OpenAI, procedimientos almacenados, generación de PDF/Word/Excel o pagos WebPay; y para el checklist de verificación antes de commitear.
argument-hint: 'qué módulo vas a crear, modificar o corregir'
allowed-tools: Read, Write, Edit, Glob, Grep, Bash, AskUserQuestion
---

# Ergosanitas — desarrollo de módulos

Laravel 11 / PHP 8.2. API REST bajo `/api`, sin frontend. Código y comentarios **en español**. Métodos de controlador en **`PascalCase`**.

`CLAUDE.md` (raíz) es la referencia de arquitectura. Esta skill es el **cómo hacerlo**: pasos, plantillas y verificación.

---

## Paso 0 — Orientarse (siempre)

```bash
cat CLAUDE.md                       # arquitectura y trampas
git log --oneline -10               # qué se tocó último
grep -n "<dominio>" routes/api.php  # rutas del área que vas a tocar
```

Identifica el **dominio vecino** más parecido y ábrelo completo (controlador + servicio + provider + modelo). En este repo el patrón correcto es el del archivo que estás editando, no un ideal global.

Decide antes de escribir:

| Pregunta | Cómo se responde |
|---|---|
| ¿Qué sobre de respuesta uso? | El que ya usa el controlador que toco. Si es nuevo: `{success, message, data}`. Ver `references/plantillas.md`. |
| ¿Necesito Service nuevo? | Sí si hay queries o lógica. Entonces también Provider + entrada en `bootstrap/providers.php`. |
| ¿La query cruza chequeos? | Aplica el filtrado por perfil 3 y 6. Obligatorio. |
| ¿Hay datos que vengan de un SP? | El SP no está en el repo. Confirma su firma en la BD antes de asumirla. |
| ¿Toca certificados/ECG? | Estás tocando facturación. Lee la Receta D. |

---

## Receta A — Endpoint nuevo en un dominio existente

1. **Servicio**: añade el método en `app/Services/XService.php`. Query Builder (`DB::table(...)`) o Eloquent, según lo que ya use el archivo.
2. **Controlador**: método `PascalCase`, cuerpo completo en `try/catch`, sobre igual al de sus hermanos del mismo archivo.
3. **Ruta**: en `routes/api.php`, con `->name('NombreDelMetodo')` como el resto.
   - **El orden importa**: rutas específicas (`chequeo-cardiovascular/pdf/{id}`) van **antes** que las paramétricas (`chequeo-cardiovascular/{id_paciente}`).
   - Verifica que no estés creando una duplicada — ya existen duplicados en `/user` e `incidencia-deportivos/count-liga`, y Laravel se queda con la última declaración.
4. **Verifica** (Paso final).

No edites `api.php` de la raíz: es una copia muerta. El archivo real es `routes/api.php`.

---

## Receta B — Dominio nuevo completo

Cuatro archivos + dos registros. Plantillas exactas en `references/plantillas.md`.

```
app/Models/X.php                     # Eloquent; + wrappers estáticos si hay SP
app/Services/XService.php            # lógica de negocio
app/Providers/XServiceProvider.php   # singleton()
app/Http/Controllers/XController.php # inyecta el servicio por constructor
```

Luego, **imprescindible**:

```bash
# 1. registrar el provider (sin esto deja de ser singleton)
cat bootstrap/providers.php
# 2. añadir la línea  App\Providers\XServiceProvider::class,  en orden alfabético
# 3. registrar las rutas en routes/api.php
php artisan config:clear && php artisan route:clear && php artisan cache:clear
php artisan route:list --path=<prefijo>
```

Nombre del provider: sigue al del dominio vecino. Conviven `CertificadoProvider` y `BioimpedanciaServiceProvider`; no unifiques.

Si el servicio depende de otro servicio, inyéctalo en el provider — el único precedente es `CertificadoProvider`, que pasa `EstadisticasService` a `CertificadoService`.

---

## Receta C — Queries sobre chequeos: filtrado por perfil

Toda query nueva sobre `chequeo_cardiovascular` respeta los dos perfiles especiales. El id de perfil sale de `UserMetadataService::getPerfilIdByEmail()` y en el servicio se llama `$perfilId`.

```php
// Perfil 3 (club deportivo): solo sus registros — y el filtro selectClub queda excluido
if ($perfilId == 3) {
    $query->where('cc.user_email', $user_email);
} elseif (!empty($selectClub)) {
    $query->where('cc.user_email', $selectClub);
}

// Perfil 6 (médico): solo derivados
if ($perfilId == 6) {
    $query->where('cc.status', 'ECG FOTO');   // solo en SearchChequeo()

    $query->leftJoin('certificado_url as cu', function ($join) {
        $join->on('cc.rut', '=', 'cu.rut_paciente')
             ->on('cc.id', '=', 'cu.id_chequeo');
    });

    $query->where('cu.derivado_medico', 'SI');
}
// cualquier otro perfil ve todo
```

`SearchChequeo()` usa `leftJoin` + `where('cc.status', 'ECG FOTO')`; `ChequeoEmailAll()` usa `join` y no filtra por status. Copia el que corresponda en lugar de unificarlos a ciegas.

Ordenamiento estándar de estados:

```php
->orderByRaw("FIELD(cc.status, 'ECG FOTO', 'REVISION MEDICA', 'Testiado', 'ingresado')")
```

**La regla está duplicada en tres sitios** — si la cambias, cámbiala en los tres:
`ChequeoCardiovascularService::filterCalendar()`, `::SearchChequeo()` y `::ChequeoEmailAll()`.

```bash
grep -n "perfiles_id\|derivado_medico" app/Services/ChequeoCardiovascularService.php
```

**El RUT es la clave de negocio**, no un id numérico. Formato `12345678-9`, validado con `/^\d{7,8}-[0-9kK]$/` (sin verificar dígito verificador). Las tablas se cruzan por el par `(rut_paciente, id_chequeo)`, no por foreign key.

El `status` avanza `ingresado` → `Testiado` → `ECG FOTO` → `REVISION MEDICA`. El único punto de PHP que **escribe** un status es `CertificadoService::subirCertificado()`; el resto ocurre en UPDATEs del cliente o en SPs.

---

## Receta D — Certificados / ECG (toca facturación)

`CertificadoService::subirCertificado()` hace tres cosas en una:

1. **borra** el certificado previo del par `(rut, id_chequeo)`,
2. pone `chequeo_cardiovascular.status = 'ECG FOTO'`,
3. llama a `EstadisticasService::PagoMensual($periodo, $user_email, $valor_ecg, "ADD")` → **factura el mes del club**.

Consecuencias a tener siempre presentes: reintentar la subida **vuelve a facturar**, y el valor sale de la tabla `params`, no de `.env`:

```php
$valor = Params::where('descripcion', 'VALOR-ECG')->firstOrFail()->valor;  // fila faltante = 500
```

Antes de modificar este flujo, avisa al usuario del efecto en facturación.

---

## Receta E — OpenAI

- Los prompts de sistema viven en `app/IA/` como clases con métodos estáticos. **Nunca incrustes un prompt en un controlador.**
- **Un prompt = un archivo.** Dos archivos que declaren la misma clase colisionan en el classmap que genera `composer install --optimize-autoloader` (lo que hace el dockerfile de producción) y cuál gana queda indeterminado. Para versionar un prompt usa git, no copias del archivo.
- Dónde va la llamada — es inconsistente; sigue el patrón del dominio que toques:
  - chat clínico → `OpenAIService`, invocado desde `OpenAIController::AsQuestionUseCase`
  - ECG y asistente de voz → `OpenAI::chat()` directo en `OpenAIController`
  - bioimpedancia → `OpenAI::chat()` en `BioimpedanciaController::FormUpload` y en `BioimpedanciaService`
- Modelos en uso: `gpt-4o-mini` (extracción de paciente, chat) y `gpt-4.1-mini` (ECG, voz, bioimpedancia).
- Chat clínico: `OpenAIService::resolveSession()` crea/recupera una `ChatSessions` por `sessionId` y resuelve el paciente por regex de RUT y, si falla, con `PatientHelper::extractPatient()`. Sin paciente → `status: needs_identifier`. Con paciente, `handle()` usa los últimos 20 mensajes de `chat_history` y `EstadisticasService::ChequeoPrompt()` (→ `SP_chequeos_prompt`).

---

## Receta F — Procedimientos almacenados

Los SP **no están en el repo ni en las migraciones**. El modelo solo los envuelve:

```php
public static function SP_ficha_clinica($param1) {
    return DB::select('CALL SP_ficha_clinica(?)', [$param1]);
}
```

Antes de usar uno, confirma su firma real contra la BD; no la deduzcas del nombre:

```bash
php artisan tinker --execute="print_r(DB::select('SHOW PROCEDURE STATUS WHERE Db = DATABASE()'));"
```

Si cambias la firma de un SP hay que actualizarlo **directamente en la base de datos** y decírselo al usuario: no queda rastro en el repo.

`FichaClinica` devuelve una columna `resultado_json` que el servicio decodifica con `json_decode`. Su `$table` está comentado a propósito: es solo un envoltorio de SP, no mapea una tabla.

Inventario de SP por modelo en `CLAUDE.md` § "Procedimientos almacenados".

---

## Receta G — Documentos y archivos

- **PDF**: mPDF, HTML construido como string (`ChequeoCardiovascularPDFService`, `BioimpedanciaService`). Assets referenciados con `public_path()`.
- **Word**: PHPWord (`ChequeoCardiovascularWordService`).
- **Excel**: `App\Imports\ChequeoImport`. Descarta filas con RUT inválido **en silencio**; el conteo va en `getCantInser()` y el último error en `getErrorMsg()`.
- **Correo**: `App\Mail\EmailMailable` + `Mail::to()`. El `phpmailer` de `composer.json` no se usa.
- **Subidas**: van a `public/` con `$file->move()`, **no** al disco de storage de Laravel. Las URLs se arman con `env('API_PATH_CER')` / `env('API_PATH_LOGO')`.
  - Producción no monta volumen para `public/`: un redeploy de `:latest` pierde los archivos. Si vas a proponer que algo nuevo se guarde ahí, dilo explícitamente.

---

## Receta H — WebPay

`WebPayController` habla con Transbank por HTTP crudo (`Http::withHeaders()`), sin SDK, con `WEBPAY_URL|ID|SECRET|RETURN` leídos con `env()` en el propio controlador. `WebPayResponse` lee `token_ws` de `$_GET`, no del `Request`.

Es el único lugar que persiste errores: el `catch` graba en `logs_api` vía `LogsApi` **y no devuelve respuesta**, así que un fallo sale como cuerpo vacío. Si tocas este controlador, ese es el bug de fondo a considerar.

---

## Paso final — Verificar antes de dar por terminado

```bash
# 1. sintaxis de lo que tocaste
php -l app/Services/XService.php

# 2. formato SOLO de lo tocado (nunca 'pint' a secas: reformatea medio repo)
vendor/bin/pint --dirty

# 3. cachés (obligatorio tras tocar config/, rutas o providers)
php artisan config:clear && php artisan route:clear && php artisan cache:clear

# 4. la ruta se resuelve y no quedó pisada por una duplicada
php artisan route:list --path=<prefijo>

# 5. el contenedor resuelve el servicio como singleton
php artisan tinker --execute="var_dump(app(App\Services\XService::class) === app(App\Services\XService::class));"

# 6. tests (hoy solo stubs de Laravel; deben seguir en verde)
php artisan test
```

**Nunca ejecutes `php artisan config:cache`** — hay `env()` fuera de `config/` (`API_PATH_CER`, `API_PATH_LOGO`, `WEBPAY_*`, `GOOGLE_CLIENT_*`) y con la config cacheada devuelven `null`: se rompen certificados, logos, pagos y login de Google. Por eso `dockerHub/entrypoint.sh` hace `config:clear` en cada arranque.

Para tests con BD, primero descomenta en `phpunit.xml` las líneas `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`; si no, apuntan al MySQL remoto de `.env`. En SQLite **no existen los procedimientos almacenados**, así que ahí solo se testea lo que no dependa de ellos.

### Checklist de cierre

- [ ] Métodos de controlador en `PascalCase`, textos y comentarios en español
- [ ] Cuerpo en `try/catch` con el sobre correcto (no hay handler global de excepciones)
- [ ] Provider creado **y** registrado en `bootstrap/providers.php`
- [ ] Ruta declarada, en el orden correcto, sin duplicar
- [ ] Filtrado de perfiles 3 y 6 aplicado (y en los tres sitios si cambió la regla)
- [ ] Sin `config:cache`; cachés limpiadas
- [ ] `pint --dirty` pasado
- [ ] Typos consolidados intactos: `GoogleAuthControlle`, `UserUpdatePassowrd`, tabla `electro_cardiogranas`
- [ ] Reportado qué se verificó de verdad y qué no (SP en BD, facturación, endpoints que requieren datos reales)

### Commit

El mensaje decide el bump de versión en el CI por coincidencia de subcadena: `major` → major, `feat` → minor, cualquier otra cosa → patch. Cuidado con mensajes como *"refactor: quita el **feat**ure flag"*, que bumpean minor sin querer.

Push a `main` publica la imagen a Docker Hub directo, **sin lint, tests ni migraciones** en el pipeline.
