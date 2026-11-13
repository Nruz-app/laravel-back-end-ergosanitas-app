---
name: ergosanitas-api
description: Diseña, modifica y diagnostica endpoints REST de la API de Ergosanitas (todo bajo /api) sin romper a sus clientes React y React Native. Úsala al tocar routes/api.php, un controlador, la validación, el sobre JSON de una respuesta o el contrato que consume el front o la app móvil. Dentro del flujo de specs la cargan solas spec-ergo-backend, spec-impl-ergo-backend y spec-verify-ergo-backend cuando el Kit de ejecución activa sus señales (S-ruta, y S-consumido junto con ergo-contrato-api).
---

# Ergosanitas API

Antes de cambiar un endpoint, **mira el código real**: este repo no sigue las convenciones Laravel por defecto. El `CLAUDE.md` del repo manda; esta skill resume lo que afecta al contrato.

## 1. Lo que existe (y lo que no)

- Todo vive en `routes/api.php`, plano y sin grupos, con prefijo `/api`. Contrasta con `php artisan route:list --json`.
- **No hay** `app/Http/Middleware`, Form Requests, API Resources ni policies. La validación es `$request->validate()` / `Validator::make()` dentro del controlador; el sobre se arma a mano en cada `try/catch` porque `bootstrap/app.php` no registra manejador de excepciones.
- **Las rutas de negocio no tienen autenticación.** `user_email` y `perfiles_id` llegan en el request. El filtrado por perfil (3 = club, solo lo suyo; 6 = médico, solo derivados) es una regla de presentación, no un control de acceso. No lo presentes como autorización.
- Los únicos middleware de ruta: `auth:sanctum` (`/user`), `signed` (orden médica del carro) y `throttle:chat-comercial`.
- Métodos de controlador en `PascalCase` (`FindByEmail`). Typos consolidados (`GoogleAuthControlle`, `UserUpdatePassowrd`) están cableados en los clientes: no los corrijas.

## 2. El sobre de respuesta

Conviven **siete formatos** (tabla en `README.md` § Formatos de respuesta y en `CLAUDE.md`). Reglas:

1. Antes de editar, anota qué devuelve **hoy** ese método, en el camino feliz **y** en el `catch` (muchos difieren).
2. Un endpoint nuevo copia el sobre del dominio al que pertenece; si el dominio es nuevo, usa `{success, message, data}` / `{success: false, message, error}`.
3. Cambiar el sobre, un nombre de campo, un tipo o el código HTTP de un endpoint existente **rompe clientes**. Si es inevitable, documenta el cambio y qué debe actualizarse en cada cliente.
4. No expongas `getMessage()`, SQL, rutas del servidor ni trazas. Hay código existente que lo hace: no lo copies.

## 3. Consumidores (siempre antes de cambiar un contrato)

- Front: `../../Front-end/ergosanitas-app/src/common/api/api.adapter.ts` y los servicios que lo usan.
- App móvil: `../../APP/V2/ergosanitasApp/src/config/api/api.adapter.ts`.
- Para el análisis completo de consumidores y la clasificación de riesgo usa la skill raíz `ergo-contrato-api` (`../../.claude/skills/ergo-contrato-api/SKILL.md`).
- No inventes endpoints ni campos: confírmalos en el código del cliente.

## 4. Rutas

- Lo específico va antes que lo genérico: `chequeo-cardiovascular/pdf/{id}` antes de `chequeo-cardiovascular/{id_paciente}`, `juego-cartas/niveles` antes de `juego-cartas/{user_email}`, `home-imagenes/admin` antes de `home-imagenes/{id}` (con `whereNumber`).
- Hay rutas duplicadas (`GET /user`, `incidencia-deportivos/count-liga/{user_email}`): Laravel resuelve la **última**. Búscalas con `grep -n`.
- Un dominio nuevo necesita controlador + servicio + provider registrado en `bootstrap/providers.php` (ver skill `ergosanitas-dev`).

## 5. Validación y datos

- Valida todo en el servidor aunque el front ya valide: tipos, rangos, formatos y límites de archivos.
- El RUT es la clave de negocio (`12345678-9`); varias tablas se unen por `(rut, id_chequeo)` sin FK.
- Devuelve solo las columnas necesarias; no serialices modelos completos de usuario (`users_metadata` guarda una copia de la contraseña en claro).
- No registres en logs ni en mensajes de error tokens, credenciales, RUT ni datos clínicos.
- Lo que cambia el estado de un chequeo o factura (subida de certificado → `PagoMensual`) tiene efectos cruzados: revisa la sección correspondiente del `CLAUDE.md`.

## 6. Cierre de un cambio de contrato

1. `docs/openapi.yaml` actualizado en el mismo cambio; luego `php docs/postman/generar-coleccion.php` y `php docs/postman/verificar-cobertura.php`. Pasa por el agente `ergo-docs`.
2. Tests: éxito, validación inválida, recurso inexistente y, si aplica, filtrado por perfil 3/6 (ver skill `ergosanitas-testing`). Nunca con pacientes reales.
3. Resume qué clientes deben actualizarse y con qué JSON de ejemplo.

---

## Uso en el flujo de specs

Esta skill forma parte del **Kit de ejecución** de las specs del back (`.claude/skills/spec-ergo-backend/references/enrutado.md`).

- **Señales que la activan:** S-ruta (y S-consumido junto con `ergo-contrato-api`).
- **Quién la carga:** ergo-docs (y ergosanitas-developer en pasos de ruta).

| Fase | Qué aporta |
|---|---|
| Diseño | Sobre de respuesta, orden de rutas y contrato de la spec. |
| Implementación | Carga extra del developer en pasos de ruta y de `ergo-docs` en la Fase 5. |
| Verificación | Revisor `ergo-docs` (Fase 2b): contrato, orden de rutas, consumidores. |

Las reglas transversales (§4 de `enrutado.md`: graphify solo AST, BD solo metadatos, sin HTTP, tests solo en SQLite, sin commits) aplican también aquí. El mapa completo de relaciones está en §8 de ese archivo.
