# SPEC 08 — Banner de ofertas en «Descargar certificados» (sin impacto en back-end)

> **Estado:** Implementado
> **Depende de:** spec hermana del front `Front-end/ergosanitas-app/specs/certificados/02-banner-ofertas-busqueda-rut.md` (Borrador). Reutiliza el endpoint público de la SPEC 03 (`specs/03-mantenedor-imagenes-home.md`, Implementado) tal como está.
> **Fecha:** 2026-11-12
> **Objetivo:** dejar constancia de que el banner de ofertas que se muestra mientras se busca un RUT en la página pública de certificados no requiere cambios en este repo.

---

## 1 — Por qué no hay cambios

El banner del front solo muestra, rotando, las imágenes **activas** de la sección `promocion` del
Home. Ese dato ya lo entrega `GET api/home-imagenes` (SPEC 03), que es público, no recibe
parámetros y devuelve solo las filas con `activo = true`, ordenadas por sección, `orden` e `id`.
El usuario decidió que la rotación **no** tiene fechas de vigencia, así que no hace falta ninguna
columna, filtro ni endpoint nuevo: el front filtra `seccion === 'promocion'` sobre la respuesta
actual.

## 2 — Evidencia

| Comprobación | Resultado |
|---|---|
| `grep -n "home-imagenes" routes/api.php` | L373–374: `Route::get('home-imagenes', [HomeImagenController::class, 'Index'])`, sin middleware (público). La literal `home-imagenes/admin` (L376) va antes de las rutas con `{id}` |
| `HomeImagenController::Index` (L32–35) → `listar(true)` (L174–187) | Sobre `{ success: true, message: 'Imagenes obtenidas correctamente', data: [...] }`; error 500 con `{ success: false, message, error }` (`errorServidor`, L254–261) |
| `HomeImagenService::listar` (L24–33) | `where('activo', true)` con `$soloActivas`; `orderByRaw("FIELD(seccion, 'promocion', 'alianza', 'galeria')")`, `orderBy('orden')`, `orderBy('id')` |
| `app/Models/HomeImagen.php` | `SECCIONES = ['promocion', 'alianza', 'galeria']` (L20); `$appends = ['src']` (L40); `$hidden = ['archivo', 'created_at', 'updated_at']` (L42); `src` = `config('services.home_imagenes.url').'/'.rawurlencode(archivo)` (L44–49) |
| `config/services.php` L40–42 | `home_imagenes.url` = `rtrim(env('API_PATH_HOME'), '/')` (no se lee ni se copia el valor) |
| Migración `2026_11_03_120000_create_home_imagenes_table.php` | Columnas `id`, `seccion` (enum), `archivo`, `alt`, `caption` (150, nullable), `activo`, `orden`, `timestamps`, e índice `(seccion, activo, orden)`. **Sin fechas de vigencia**: no hacen falta (decisión del usuario) |
| Esquema real (`mysql-server`, solo `information_schema.COLUMNS`, 2026-11-12) | `home_imagenes` existe en la base con las mismas 9 columnas que la migración; ninguna de vigencia |
| `docs/openapi.yaml` L5104 | `/home-imagenes` `get` ya documentado («Imágenes activas del Home (público)») |
| Consumidores actuales (`grep -rln "home-imagenes"` en el front) | Servicios `src/home-ergo/services/UseHomeImagenesService.ts` y `src/chequeo-administrador/services/useImagenesHomeService.ts`, con sus interfaces `src/home-ergo/interface/home.interface.ts` y `src/chequeo-administrador/interface/imagen-home.interface.ts`. La spec hermana añade un **tercer consumidor** en `src/Certificados/`, sin cambiar el contrato |
| App móvil (`grep -rn "home-imagenes" APP/V2/ergosanitasApp/src`) | Sin coincidencias |
| Dominios revisados | `HomeImagenController`, `HomeImagenService`, `HomeImagen`, `routes/api.php` (SPEC 03), `docs/openapi.yaml`; dominio de certificados (SPEC 07) no se toca |

### 2.1 Nota para cambios futuros del endpoint

Desde la spec hermana, `GET api/home-imagenes` tiene **tres** consumidores en el front (Home
comercial, mantenedor del Administrador y página de certificados). Cualquier cambio futuro de su
sobre, de sus campos (`src`, `alt`, `caption`, `seccion`) o de su filtro de activas debe pasar por
el análisis de consumidores de `ergo-contrato-api` incluyendo `src/Certificados/`.

## 3 — Seguridad

`S-seguridad` se activa solo porque el endpoint es **público** y ahora se llama desde una página
pública donde se escribe un RUT. No cambia nada:

- `GET api/home-imagenes` no recibe parámetros: el RUT que se busca **no** viaja a este endpoint.
- La respuesta solo trae `id`, `seccion`, `src`, `alt`, `caption`, `activo` y `orden` de imágenes
  comerciales (`$hidden` oculta `archivo` y las fechas). Ningún dato clínico ni de pacientes.
- Hereda la deuda conocida (sin autenticación real ni `throttle` en esta ruta); ver §5.

## 4 — Criterios de aceptación

Se comparan contra **`origin/main`**, no contra `main` local (hoy 31 commits por detrás, sin la
SPEC 03) ni contra el diff completo de la rama (que arrastra cambios sin commitear de la SPEC 07).

- [x] `git diff --stat origin/main -- app/Http/Controllers/HomeImagenController.php app/Services/HomeImagenService.php app/Models/HomeImagen.php database/migrations/2026_11_03_120000_create_home_imagenes_table.php` sale vacío.
  - ✅ Evidencia (2026-11-12, spec-verify-backend, `af9cfa7` + árbol de trabajo, contra `origin/main` `cf407c3`): salida vacía (0 líneas). `git diff <commit>` compara también con el árbol de trabajo, así que cubre los cambios sin commitear; `git status --short` de esos archivos y de `config/services.php` también sale vacío.
- [x] `git diff origin/main -- routes/api.php config/services.php docs/openapi.yaml | grep -n "^[-+].*\(home-imagenes\|home_imagenes\|HomeImagen\)"` no devuelve líneas.
  - ✅ Evidencia (2026-11-12, spec-verify-backend, `af9cfa7` + árbol de trabajo, contra `origin/main` `cf407c3`): el `grep` no devuelve líneas (código de salida 1).
- [x] `php artisan route:list --path=home-imagenes -v` muestra `GET|HEAD api/home-imagenes` → `HomeImagenController@Index` con un único middleware, `api`.
  - ✅ Evidencia (2026-11-12, spec-verify-backend, `af9cfa7`): `route:list --path=home-imagenes --json` → `GET|HEAD api/home-imagenes` · `App\Http\Controllers\HomeImagenController@Index` · middleware `["api"]`. La salida con `-v` lo muestra igual (`⇂ api`).

## 5 — Lo que **no** entra en esta spec

- Fechas de vigencia (`vigente_desde` / `vigente_hasta`) en `home_imagenes`: descartadas por el usuario. Si algún día se piden, van en su propia spec (migración aditiva, filtro en `listar`, mantenedor del front).
- Cambios en el mantenedor de imágenes (`home-imagenes/admin`, `Store`, `Update`, `UpdateActivo`, `Destroy`).
- Limitador (`throttle`) para `GET home-imagenes`: el endpoint ya es público y lo carga la portada en cada visita; la página de certificados añade una llamada por visita, no por búsqueda.

## Kit de ejecución

> Generado por `/spec-ergo-backend` (seguido por `ergo-father`) el 2026-11-12. Fuente: `.claude/skills/spec-ergo-backend/references/enrutado.md`.

| Señal | Evidencia en la spec | Skills | Agente | MCP / comandos |
|---|---|---|---|---|
| `S-base` | Toda spec | `ergosanitas-architecture` (impacto: endpoint con un consumidor más) · `ergosanitas-dev` | — | graphify (`explain "HomeImagenController"`) · engram `mem_search` · `mysql-server` (solo `information_schema` de `home_imagenes`) |
| `S-seguridad` | §3: endpoint público consumido desde la página pública de certificados | `ergosanitas-security` | — | — |

Señales evaluadas y **no** activadas: `S-ruta` y `S-consumido` (no cambia ninguna ruta ni sobre; el
consumidor nuevo se registra en §2.1), `S-sql` (sin migración ni SP; el esquema solo se consultó
como evidencia), `S-capas`, `S-laravel`, `S-tests`, `S-flujo`, `S-facturacion` (la página de
certificados no sube ni factura nada).

No hay pasos de implementación ni agentes que lanzar. La verificación se limita a los tres
criterios de §4, sin HTTP; en V, `ergosanitas-security` revisa §3.

Librerías a contrastar en Context7: ninguna.

## Notas de revisión

**2026-11-12 — `/spec-ergo-backend` (modo revisión).** Todas las afirmaciones de §2 se
comprobaron contra el código, `route:list` y el esquema (solo metadatos).

- ✏️ `HomeImagenService::listar` empieza en L24, no en L25.
- ✏️ Consumidores del front: se añaden las dos interfaces que también citan `home-imagenes`.
- ➕ Índice `(seccion, activo, orden)` de la migración y confirmación del esquema real.
- ❌→✏️ El criterio 1 no era verificable: el diff de la rama contra `main` incluye la SPEC 07 sin
  commitear y `main` local no tiene la SPEC 03 (todo `HomeImagen*` salía como «añadido»). Se
  reescribe contra `origin/main` y limitado a los archivos del dominio.
- ✏️ El criterio de la ruta pide `-v`: sin él, `route:list` no muestra middleware.
- ✏️ Kit: `S-base` incluye `ergosanitas-dev` (matriz §3) y `S-seguridad` se activa por «endpoint
  público» (§2 de `enrutado.md`), con su sección §3.
- ⚠️ La spec hermana del front dice que esta spec está «**Aprobado** en disco el 2026-11-12», pero
  aquí sigue en `Borrador`. Hay que aprobar esta o corregir la cabecera de la hermana.

**2026-11-12 — Aprobado** por el dueño del proyecto tras la revisión.

## Estado de verificación

> Última verificación: 2026-11-12 · `/spec-verify-ergo-backend` · commit `af9cfa7` (con cambios sin commitear) · rama `spec-08-banner-ofertas-certificados-sin-impacto`
> Entorno: sin tests (la spec no los pide) · esquema en solo lectura de la BD remota de `.env` · sin peticiones HTTP

| Resultado | Criterios |
|---|---|
| ✅ Verificados | 3 |
| ❌ Fallan | 0 |
| ⏸ No verificables | 0 |

**Kit de ejecución:** recalculado contra la spec y contra el diff real. Salen `S-base` y `S-seguridad`, igual que en la sección «Kit de ejecución». **Sin diferencias**: el back no tiene archivos implementados para esta spec.

**Mapa (graphify):** el dominio es `HomeImagenController` → `HomeImagenService::listar` → `HomeImagen`, más `routes/api.php` L373–374 y `config/services.php` L40–42. Ninguno cambia respecto de `origin/main`. git coincide: no hay commits ni cambios en el árbol de trabajo asociados a la SPEC 08 fuera de `specs/`.

**Impactos:**
- Reglas duras comprobadas: no hay servicio ni provider nuevos; el sobre `{success, message, data}` no cambia; no toca perfiles 3/6, borrados ni `env()` nuevos.
- Contrato: `docs/openapi.yaml` L5104 sigue documentando `/home-imagenes`, y `verificar-cobertura.php` cubre las 123 de 123 rutas.
- Consumidores: el endpoint no cambia (`S-consumido` no aplica). La app móvil no lo usa.

**Revisión de calidad (agentes):** no se lanzaron revisores, porque no hay archivos implementados que revisar y el kit no tiene agentes. Apliqué directamente la skill `ergosanitas-security` sobre §3:
- `Index()` no recibe `Request`, así que el RUT que se busca no llega al endpoint;
- `HomeImagen::$hidden` (L42) oculta `archivo` y las fechas;
- la respuesta no trae datos clínicos;
- la deuda conocida (sin `throttle` ni autenticación en lecturas públicas) ya está declarada en §5.

| Archivo:línea | Severidad | Regla | Agente / skill |
|---|---|---|---|
| — | — | Sin hallazgos | `ergosanitas-security` |

**Buenas prácticas (Context7 / Boost):** no aplica. La spec no usa ninguna librería nueva ni cambia código.

**Cobertura de tests:** la spec no pide tests y los tres criterios son estáticos. Los tests de la SPEC 03 no se volvieron a correr: no fuerzan SQLite y el dominio no cambió.

**Recomendación:** `Implementado`, sin cambios en el código del back. El dueño del proyecto cambió el estado el 2026-11-12 tras esta verificación.
