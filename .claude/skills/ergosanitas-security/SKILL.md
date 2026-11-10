---
name: ergosanitas-security
description: Revisa seguridad y privacidad del back-end de Ergosanitas (autenticación, autorización por perfil, secretos, subida de archivos, dependencias y datos clínicos bajo la Ley 21.719). Úsala para auditorías, para revisar un cambio sensible y antes de tocar auth, permisos, uploads o datos de pacientes.
---

# Ergosanitas Security

El sistema trata **datos clínicos** (RUT, chequeos, ECG, bioimpedancia, certificados). Seguridad por defecto, mínimo privilegio y revisión pasiva primero.

## 1. Punto de partida: deuda conocida

No la "descubras" como hallazgo nuevo; úsala para priorizar y para no empeorarla:

- **Rutas de negocio sin autenticación**: `user_email` y `perfiles_id` viajan en el request. El filtro de perfiles 3/6 no es control de acceso. Cualquier endpoint nuevo hereda esto mientras no exista una spec de autenticación (Sanctum).
- **`home-imagenes`** rechaza por perfil `Administrador`, pero el email es falsificable (decisión D3, Spec 03).
- **`users_metadata`** guarda una copia de la contraseña en claro, sincronizada desde `UserMetadataService`.
- **`GET /api/execute-link-simbolik`** ejecuta `Artisan::call('storage:link')` por HTTP.
- **`env()` fuera de `config/`** (`API_PATH_*`, `WEBPAY_*`, `GOOGLE_CLIENT_*`): por eso no se usa `config:cache`.
- **Uploads en `public/`** con `$file->move()` (certificados, ECG, bioimpedancia, logos): accesibles por URL directa. La orden médica del carro es la excepción buena (disco privado + `temporarySignedRoute`).
- Errores que devuelven `getMessage()`, `file` y `line` al cliente en varios `catch`.
- **`WebPayController`** lee `token_ws` de `$_GET` y en el `catch` devuelve cuerpo vacío.
- Tres mecanismos de auth conviviendo (sesión, Sanctum, JWT).

## 2. Alcance de una revisión

- Autorización por recurso: ¿se puede leer el chequeo de otro club cambiando un RUT o un email? (IDOR)
- Validación, mass assignment (`$fillable`/`$guarded`), SQL crudo con concatenación (`DB::select`, `whereRaw`, `orderByRaw`).
- Subida de archivos: extensión y MIME, tamaño, nombre generado, destino.
- Exposición en respuestas, logs (`storage/logs`, tabla `logs_api`) y excepciones.
- CORS (`config/cors.php` si existe), cookies, HTTPS y rate limiting (hoy solo `throttle:chat-comercial`).
- Integraciones: OpenAI (qué datos clínicos salen en cada prompt de `app/PromptSystem/`), Transbank, Google OAuth.
- Secretos en código, git, `.env.example` y workflows.
- Dependencias: `composer audit` (solo lectura). Hoy reporta avisos; repórtalos sin actualizar paquetes en una tarea no relacionada.

## 3. Datos clínicos (Ley 21.719)

- No copies datos de pacientes a prompts, specs, docs, tests, capturas ni commits. Usa datos sintéticos.
- El MCP `mysql-server` lee producción con autorización de Gerencia: para auditar usa esquema y metadatos; filas de pacientes solo si la tarea lo exige, y no las reproduzcas en el informe.
- Si encuentras una exposición, describe el tipo de dato y dónde está, sin el valor.
- Las correcciones **no borran** datos: se rectifica, bloquea, restringe o anonimiza.
- No afirmes que el sistema "cumple" la ley ni que está "certificado".

## 4. Reglas de ejecución

- Solo análisis pasivo y lectura de código. Nada de escaneos activos, fuerza bruta, enumeración de usuarios ni explotación contra producción o el hosting.
- No cambies auth, permisos, CORS, credenciales ni configuración de producción sin autorización de Gerencia.
- No muestres secretos encontrados: indica el archivo o variable y recomienda rotarlos.

## 5. Informe

Por hallazgo: severidad (Crítico, Alto, Medio, Bajo), archivo:línea, evidencia mínima no sensible, escenario de explotación, impacto (prioriza la exposición de datos clínicos), corrección mínima y la prueba de regresión que la cubriría. Separa la deuda conocida (§1) de lo nuevo.
