---
name: spec-verify-ergo-backend
description: Verifica una o varias specs ya implementadas del back-end Ergosanitas (Laravel 11 / MySQL 5.7) y deja cada criterio de aceptación con su evidencia. Arma automáticamente el «Kit de ejecución» de la spec (skills, agentes y MCP según spec-ergo-backend/references/enrutado.md) para elegir revisores y consultas. Mapea el código con graphify, analiza dependencias e impactos, revisa la calidad del código con los agentes del proyecto (ergosanitas-developer, ergosanitas-mysql, ergosanitas-laravel, ergo-docs y las skills de seguridad y testing), contrasta buenas prácticas con Context7 y Laravel Boost, recorre los criterios uno por uno (tests en SQLite, artisan, grep y esquema en solo lectura con los MCP laravel-boost y mysql-server) y actualiza los checks de la spec. Nunca implementa ni corrige código, nunca hace peticiones HTTP contra la BD de .env y no cambia el Estado de la spec.
disable-model-invocation: true
argument-hint: <NN-slug | todas>
---

# /spec-verify-ergo-backend — verificador de specs implementadas con evidencia

Tercer eslabón del flujo spec-driven del back:

```
/spec-ergo-backend         → diseña la spec y su Kit de ejecución   (Borrador)
   (una persona aprueba)                                             (Aprobado)
/spec-impl-ergo-backend    → la implementa con el Kit                (Implementado)
/spec-verify-ergo-backend  → comprueba cada criterio con el Kit      (evidencia)   ← este
```

La fuente única del enrutado (señales, matriz de skills/agentes/MCP, reglas transversales,
alternativas cuando falta un MCP) es `.claude/skills/spec-ergo-backend/references/enrutado.md`.
Las tablas de esta skill aplican esa matriz a la verificación; si discrepan, manda `enrutado.md`
(y por encima de todo, `CLAUDE.md`).

`/spec-impl-ergo-backend` marca `Implementado` cuando el código está completo, pero **no deja evidencia por criterio**. Al 2026-11-10 las specs 03 a 06 están en `Implementado` con 66 criterios y ninguno marcado; solo la 02 los tiene (16/16). Este comando cierra esa brecha.

Es el homólogo de `/spec-verify-ergo-front` (`../../Front-end/ergosanitas-app/.claude/skills/spec-verify-ergo-front/`), con una diferencia: **el back no tiene un entorno local seguro**. Por eso aquí no hay Playwright ni peticiones HTTP: la evidencia sale de tests en SQLite, de comandos estáticos y del esquema leído en solo lectura.

Responde y escribe siempre en español.

---

## Reglas que no se negocian

1. **Nada de HTTP contra la API.** `.env` apunta a la **MySQL de producción**: `php artisan serve` en local escribe en ella, y las rutas de chat llaman a OpenAI (coste real, sesiones e historial en producción). Ningún `curl`, Postman ni `Http::` contra `/api`. Los criterios que solo se demuestran así quedan `⏸`, salvo que el prompt que te lanzó traiga una autorización explícita **para ese criterio**.
2. **Tests solo sobre SQLite en memoria.** Se corren `php artisan test --filter="CartFlow|ChatComercial"` y los tests cuyo `TestCase` fuerce `DB_CONNECTION=sqlite` (`CartFlowTestCase`, `ChatComercialTestCase`). Cualquier otro test, antes de correrlo, se comprueba con `grep -n "sqlite" <TestCase>`; si no lo fuerza, **no se ejecuta** y el criterio queda `⏸`. Los tests no se modifican ni se crean: si falta cobertura, se reporta.
3. **BD en solo lectura y sin datos de pacientes.** `mysql-server` y Laravel Boost se usan para esquema y metadatos: tablas, columnas, índices, `information_schema`, `SHOW CREATE PROCEDURE`, `COUNT(*)` agregados. **Nunca** filas de pacientes (RUT, nombres, resultados clínicos) ni `CALL` a un SP. Nada de `INSERT`, `UPDATE`, `DELETE` ni DDL, aunque el MCP lo rechace igual (Ley 21.719; ver `CLAUDE.md` § Servidores MCP de desarrollo).
4. **Tinker solo con expresiones puras** que no toquen la BD ni la red, por ejemplo `var_dump(app(X::class) === app(X::class));`. Sin `DB::`, modelos, `Http::` ni `OpenAI::`.
5. **Solo se reporta.** Un criterio que falla queda `❌` con su evidencia. **No tocas** `app/`, `routes/`, `bootstrap/`, `config/`, `database/`, `tests/`, `docs/`, `.env`, `composer.*` ni `.claude/`, y **no cambias el `Estado:`** de la spec. Si hay ❌, recomiendas `En revisión` en el informe; decide una persona.
6. **Un ✅ exige evidencia reproducible:** el comando con su salida real, el test que pasó (nombre exacto) o la consulta de esquema con su resultado. Sin eso no es ✅. Un `⏸` bien explicado vale más que un ✅ supuesto.
7. **Ningún secreto en la evidencia.** No copies valores de `.env`, contraseñas, tokens ni el host de la BD: escribe «BD remota de `.env`».

---

## Contexto de sesión

Estado del repositorio:
!`git status --short`

Rama y último commit:
!`git branch --show-current && git log -1 --format="%h %s"`

Specs y estados:
!`grep -H -m1 -i "estado:" specs/[0-9]*.md 2>/dev/null || echo "No existe specs/"`

Destino de la BD en `.env` (sin secretos):
!`grep -E "^DB_HOST=" .env 2>/dev/null | sed -E 's/=(127\.0\.0\.1|localhost)$/ → local/; s/=.+$/ → remota (producción)/' || echo "sin .env"`

---

## Si te ejecuta `ergo-father` o el agente `spec-verify-ergo-backend`

Esta skill tiene `disable-model-invocation`, así que un agente no la invoca con `Skill`: la **lee** y la sigue. En ese caso el «Contexto de sesión» de arriba no se evaluó: ejecuta tú esos comandos (con `git -C "D:/ErgoSanitas/Projectos/Back-end/ergosanitas-app"` y rutas absolutas si el directorio actual no es este repo).

Un subagente **no puede lanzar otros agentes** ni preguntar con `AskUserQuestion`. En la Fase 2b emula a los revisores leyendo sus definiciones y skills (o integra los hallazgos que le pasó `ergo-father`), y las ambigüedades las devuelve como preguntas en el informe.

---

## Fase 0 — Alcance y preparación

### 0.1 — Resolver el argumento

| Argumento | Qué se verifica |
|---|---|
| `NN-slug`, `NN` o ruta (`specs/05-mantenedor-carrito-compra.md`) | Esa spec |
| `todas` | Todas las de `specs/` en orden numérico |

En el back los números son únicos (01 a 06), así que `05` basta. Solo se verifican specs en `Implementado`, o en `Aprobado` si ya hay código en la rama (por ejemplo, la 01). `Borrador`, `En revisión` y `Obsoleto` se saltan, y se dice por qué.

### 0.2 — Comprobar las herramientas

Verifica y **reporta lo que falte**. No inventes resultados para suplir una herramienta ausente:

```bash
test -f graphify-out/graph.json && echo "grafo OK" || echo "FALTA grafo → graphify update ."
graphify --version
claude mcp list          # graphify, context7, laravel-boost, mysql-server
```

| Falta | Qué haces |
|---|---|
| `graph.json` | `graphify update .` (AST, sin LLM ni coste) y dilo. Nunca el modo semántico (`/graphify .`), que manda código a un LLM externo. |
| MCP `graphify` | Usa la CLI (`graphify query|path|explain|affected`): da lo mismo. |
| `context7` | La Fase 3 queda «no ejecutada» en el informe. No se reemplaza con memoria. |
| `laravel-boost` | Esquema con `mysql-server`; docs solo con Context7. |
| `mysql-server` | Esquema con Boost `database-schema`. Si faltan los dos, los criterios de esquema quedan `⏸ (sin acceso al esquema)`. |

Las herramientas MCP pueden estar diferidas: cárgalas con **una sola** llamada a `ToolSearch`, por ejemplo `select:mcp__graphify__query_graph,mcp__graphify__get_node,mcp__graphify__get_neighbors,mcp__graphify__shortest_path,mcp__laravel-boost__database-schema,mcp__laravel-boost__search-docs,mcp__mysql-server__mysql_query,mcp__context7__resolve-library-id,mcp__context7__query-docs,mcp__engram__mem_search` (la lista y las alternativas viven en §5 de `enrutado.md`).

### 0.3 — Leer la spec y su contexto

Por cada spec, léela entera (contexto, alcance, contrato, plan, **criterios**, decisiones y riesgos) y lee `CLAUDE.md`, que **manda** sobre las skills si discrepan. Anota: dominio, rutas y sobre, tablas, SP, perfiles afectados, providers y la lista de criterios de «5 — Criterios de aceptación».

### 0.4 — Kit de ejecución (automático)

1. Lee `.claude/skills/spec-ergo-backend/references/enrutado.md`.
2. Lee la sección `## Kit de ejecución` de la spec. Si no existe (specs anteriores al enrutado), arma el kit desde cero.
3. **Recalcula** las señales contra la spec **y contra el diff real** (Fase 1). Las diferencias (señal que el código activa y la spec no declaró, o al revés) son **hallazgos** del informe; esta skill **no edita** esa sección de la spec.
4. Carga los recursos de la columna **V**: los MCP con la llamada única de 0.2, y las skills invocables con `Skill` (`ergosanitas-architecture` siempre para impactos, más `ergosanitas-dev`, `ergosanitas-mysql`, `ergosanitas-laravel`, `ergosanitas-api`, `ergosanitas-security`, `ergosanitas-testing` según señales). `ergo-contrato-api` (raíz) se **lee** si hay `S-consumido`.
5. `mem_search` (engram) del dominio: verificaciones y decisiones previas.
6. El kit decide los revisores de la Fase 2b y las librerías de la Fase 3. Muéstralo (formato §7) con `Cargado: … · No disponible: …`.

---

## Fase 1 — Mapa del código con graphify

Objetivo: la **lista real de archivos** que implementan la spec.

```bash
graphify query "controladores, servicios, modelos y rutas de <feature de la spec>"
graphify explain "<Servicio o controlador que nombra la spec>"
```

(Equivalente MCP: `query_graph`, `get_node`, `get_neighbors`.)

Crúzalo con git, que dice qué tocó la implementación:

```bash
git log --oneline --all -- specs/<NN-slug>.md
git log --all --name-only --format="--- %h %s" --grep="<NN>\|<slug>"
git branch -a --list "*spec-<NN>*"
```

Si el grafo y git no coinciden (un archivo que la spec nombra y no existe, o uno tocado que la spec no menciona), anótalo como hallazgo. graphify es AST: no ve SQL dentro de strings ni procedimientos almacenados, así que esos se buscan con `grep` y en la Fase 5.

---

## Fase 2 — Dependencias e impactos

1. **Impacto inverso:** `graphify affected "<Servicio>"` y `graphify path "<Controlador>" "<Modelo>"`, leídos con el procedimiento de la skill `ergosanitas-architecture` (capas, providers, SP, contratos con los clientes).
2. **Reglas duras del `CLAUDE.md`**, verificadas con su comando y citando la salida:

   | Regla | Comprobación |
   |---|---|
   | Servicio nuevo con provider registrado | `grep -n "<X>ServiceProvider" bootstrap/providers.php` y `singleton(` en el provider |
   | Sobre del dominio respetado | leer el `return response()->json(...)` del controlador frente al contrato §3 de la spec |
   | Perfiles 3/6 en queries de chequeos | `grep -n "perfil" <servicio>`, frente a `filterCalendar`/`SearchChequeo`/`ChequeoEmailAll` |
   | Orden de rutas (específica antes de genérica) | `php artisan route:list --path=<prefijo> --json` y `grep -n` en `routes/api.php` |
   | Sin `env()` nuevo fuera de `config/` | `git diff <base>..<merge> -- app routes \| grep -n "env("` |
   | Nada borra datos (Ley 21.719) | `grep -rnE "->delete\(|forceDelete|truncate|DROP|DELETE FROM" <archivos de la spec>` (soft delete o «anular» está bien) |
   | Contrato al día | `php docs/postman/verificar-cobertura.php` y `grep -n "<ruta>" docs/openapi.yaml` |
   | Sin `config:cache` ni nuevos `env()` que lo rompan | cuando la spec añada configuración |

3. **Consumidores externos:** si la spec cambió un endpoint existente, `grep -rn "<ruta>"` en `../../Front-end/ergosanitas-app/src` y `../../APP/V2/ergosanitasApp/src`. Solo lectura, y solo se reporta.
4. **Revisión de calidad por los agentes del proyecto** → Fase 2b.

---

## Fase 2b — Revisión de calidad con los agentes del back

Los agentes de `.claude/agents/` son los que saben cómo se escribe buen código **en este repo**. Esta fase los usa como revisores de lo que implementó la spec. Es obligatoria: no se salta porque los criterios pasen.

### 2b.1 — Elegir los revisores

Con el kit de 0.4 y la lista de archivos de la Fase 1 (cada señal con agente aporta su revisor; la tabla de abajo es esa matriz aplicada a la revisión):

| La spec toca | Revisor | Qué mira, además de su definición |
|---|---|---|
| Controladores, servicios, providers, rutas, `app/PromptSystem/` (**casi siempre**) | `ergosanitas-developer` + skill `ergosanitas-dev` | Controller → Service → Model; servicio con `XServiceProvider` + `singleton()` registrado en `bootstrap/providers.php`; sobre de respuesta del dominio (siete sobres, no se cambia uno consumido); `try/catch` por acción; perfiles 3/6 en los tres sitios; sin `env()` nuevo fuera de `config/`; prompts en `app/PromptSystem/`, un archivo por clase; métodos en `PascalCase`; typos consolidados respetados. |
| SQL, migraciones, SP, índices, wrappers de SP | `ergosanitas-mysql` | MySQL 5.7.22 (sin CTE ni funciones de ventana); migraciones aditivas y reversibles; triángulo del SP (BD + `.sql` + wrapper); índices para los filtros nuevos; joins por `(rut, id_chequeo)`; nada de `DELETE`/`TRUNCATE`/`DROP` sobre datos productivos. |
| Uso del framework: contenedor, validación, rate limiting, caché, auth, colas | `ergosanitas-laravel` | API de Laravel 11 en uso, refactors permitidos, nada de `config:cache` implícito. |
| `routes/api.php`, el contrato o `docs/` | `ergo-docs` + skill `ergosanitas-api` | `docs/openapi.yaml` al día con la ruta, diagramas afectados, Postman regenerado; orden de rutas; consumidores en front y app. |
| **Siempre** (sin agente propio) | skill `ergosanitas-security` | Datos clínicos y Ley 21.719, uploads, secretos, autorización por perfil. |
| Hay tests o la spec los pide | skill `ergosanitas-testing` | Tests en SQLite forzado, `OpenAI::fake()`, filtro del CI, cobertura de los criterios. |

Un revisor por fila que aplique; no se lanzan dos veces con el mismo enfoque. Las dos skills sin agente (`ergosanitas-security`, `ergosanitas-testing`) las aplicas tú, o se las pasas como carga extra al prompt de `ergosanitas-developer`.

### 2b.2 — Ejecutar la revisión

| Modo | Qué haces |
|---|---|
| **Sesión principal** (`/spec-verify-ergo-backend` o el agente lanzado con `--agent`, con la herramienta `Agent`) | Lanza a los revisores **en paralelo**, en solo lectura, con `subagent_type` igual al nombre del agente (en este repo están registrados) y el prompt de abajo. |
| **Subagente sin `Agent`** | Emula a cada revisor: lee `.claude/agents/<revisor>.md` y su skill, aplica su checklist con graphify y `grep` sobre los archivos de la spec, y en el informe di que la revisión fue emulada. |
| **El prompt que te lanzó trae hallazgos de revisores** (lo hace `ergo-father`) | Intégralos tal cual en 2b.3 y no repitas esa revisión; solo emula la de los revisores que falten. |

Prompt para cada revisor:

> Estoy verificando la spec `specs/<NN-slug>.md`, ya implementada. Archivos: `<lista de la Fase 1>`. **Revisa sin modificar nada**: ni código, ni `docs/`, ni specs; sin peticiones HTTP, sin `php artisan serve`, sin tests fuera de SQLite, sin consultar filas de pacientes y sin leer valores de `.env`. Carga tu skill y el `CLAUDE.md`; puedes usar graphify (solo AST), Context7 / Boost `search-docs` y, para esquema, `mysql-server` / Boost `database-schema` solo con metadatos. Señales del kit que te tocan: `<señales y su evidencia>`. Dime si esos archivos rompen alguna regla de tu dominio o del `CLAUDE.md`. Devuélveme una tabla `archivo:línea · severidad (bloqueante|mayor|menor) · regla violada (con su fuente) · sugerencia`. Si no hay hallazgos, dilo y lista las reglas que comprobaste.

### 2b.3 — Consolidar

- Une las tablas, quita duplicados y conserva qué agente o skill vio cada hallazgo.
- **Severidad**: `bloqueante` = rompe una regla dura del `CLAUDE.md` (sobre consumido cambiado, provider sin registrar, borrado de datos, `env()` que rompe con caché, SQL incompatible con 5.7, dato de paciente expuesto); `mayor` = se aparta del patrón del repo de forma que costará mantener; `menor` = detalle.
- Los hallazgos son **observaciones**: no marcan ni desmarcan criterios. Si hay al menos un `bloqueante`, la recomendación final es `En revisión`.
- Verifica cada hallazgo con `Read` en la línea citada antes de aceptarlo: un revisor puede equivocarse. Los que no se sostienen se descartan y se dice cuántos.

---

## Fase 3 — Buenas prácticas (Context7 y Laravel Boost)

Por cada librería del kit (las de las señales `S-laravel`, `S-openai`, `S-docs-gen`) y por cada una que use el código de la spec (lo dicen los `use` de la Fase 1: Laravel **11**, `openai-php/laravel` 0.10, `maatwebsite/excel` 3.1, `mpdf/mpdf` 8.2, `phpoffice/phpword`, Sanctum 4…):

1. `mcp__context7__resolve-library-id` con el nombre y **la versión de `composer.lock`**.
2. `mcp__context7__query-docs` sobre **el patrón concreto** que usa el código. Ejemplos: «`RateLimiter::for` con varios límites por IP», «`URL::temporarySignedRoute` y middleware `signed`», «`SoftDeletes` y unicidad», «`OpenAI::fake()` en tests».
3. `mcp__laravel-boost__search-docs` como segunda fuente para Laravel; es específica de la versión instalada.

Los hallazgos son **observaciones**: van al informe y a «Estado de verificación» y **nunca marcan ni desmarcan un check**. Cita la fuente. Respeta las decisiones de la spec (§6) y del `CLAUDE.md`: una práctica «mejor» que contradice una decisión deliberada (los siete sobres, el registro manual de providers, Query Builder en vez de Eloquent) se anota como «coherente con la decisión X», no como defecto.

---

## Fase 4 — Clasificar y verificar los criterios

Recorre **uno por uno** los `- [ ]` (y los `- [x]` previos, para revalidarlos) de «5 — Criterios de aceptación». Clasifica cada uno:

| Tipo | Cómo se verifica | Ejemplos |
|---|---|---|
| **estático** | Se ejecuta ahora y se cita la salida real. | `grep`, `git diff -M`, `php artisan route:list --path=…`, «no existe `app/IA/`», `vendor/bin/pint --test <archivos de la spec>` (nunca Pint sin rutas), `php docs/postman/verificar-cobertura.php` |
| **test** | Un test **en SQLite** que cubre exactamente ese comportamiento. Búscalo con `grep -n "function test_\|#\[Test\]\|it(" tests/Feature/<Dominio>/` y con el nombre del test. | «sin Administrador responde 403», «`anular` sin motivo → 422», «el limitador corta en la 21» |
| **esquema** | Fase 5, en solo lectura. | «la tabla `cache` existe», «`cf_pedidos` tiene índice por estado», «`SP_x` recibe 2 parámetros» |
| **HTTP / externo** | No se verifica: `⏸` con el motivo. | Postman con respuestas de OpenAI, producción, despliegue FTP, «el humano confirma», comportamiento solo observable con peticiones reales |

- Un criterio redactado como prueba Postman puede quedar ✅ **si un test en SQLite** prueba lo mismo (misma ruta, mismo código, mismo efecto). Cita el test y di que la evidencia es el test, no Postman. Si el test cubre solo una parte, queda `⏸` con lo que falta.
- El filtro de tests se corre **una vez por spec** (`php artisan test --filter=<Dominio>`) y la salida se reutiliza. Si falla algo, separa los fallos previos de lo que pide la spec.
- Un criterio que se refería a la rama de implementación (`git diff main...spec-NN-…`) se verifica contra el commit de merge si la rama ya no existe (`git log --merges --grep="spec-NN"` o el commit `feat:` correspondiente). Dilo en la evidencia.

---

## Fase 5 — Esquema y BD con los MCP (solo lectura)

Para los criterios de tipo **esquema**:

- **Laravel Boost**: `mcp__laravel-boost__database-schema` (tablas, columnas, índices, FK) y `database-connections`.
- **MySQL**: `mcp__mysql-server__mysql_query` solo con metadatos:

  ```sql
  SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('cache','cache_locks');
  SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cf_pedidos';
  SHOW INDEX FROM cf_pedidos;
  SELECT PARAMETER_NAME, DATA_TYPE FROM information_schema.PARAMETERS WHERE SPECIFIC_NAME = 'SP_x';
  SHOW CREATE PROCEDURE SP_x;
  ```

- `php artisan migrate:status` (lectura) contra las migraciones que nombra la spec.
- Un conteo agregado (`SELECT COUNT(*) …`) solo si el criterio lo exige, y **nunca** con `SELECT` de columnas de pacientes.
- Esto se verifica **contra la BD de `.env` (producción)**. Dilo en la evidencia: «esquema de la BD remota de `.env`». Cuando la spec diga «en la base local», queda `⏸` si no hay una base local disponible.

---

## Fase 6 — Escribir la evidencia en la spec

Edita **solo dos sitios** de la spec: la sección «5 — Criterios de aceptación» y la sección final «Estado de verificación». No reescribas el resto, no reordenes criterios, no borres las notas `> **Ajuste…**` y **no toques `Estado:`**.

### 6.1 — Cada criterio

Conserva el texto original y cambia solo la casilla. Debajo va una sublínea de evidencia:

```markdown
- [x] `php artisan route:list --path=chat-comercial` muestra exactamente una ruta `POST`, con middleware `throttle:chat-comercial`.
  - ✅ Evidencia (2026-11-10, spec-verify-backend, `1d6567c`): `route:list --path=chat-comercial --json` → 1 ruta `POST api/chat-comercial/as-question`, middleware `["api","throttle:chat-comercial"]`.
- [x] Sin `user_email` de Administrador, todo `cart-flow/admin/*` responde 403.
  - ✅ Evidencia (2026-11-10, spec-verify-backend): test `AdminCartFlowTest::<nombre exacto del test>` en SQLite → pasa (`--filter=CartFlow`: <N> passed).
- [ ] El precio coincide con `GET cart-flow/catalogo`.
  - ⏸ No verificable (2026-11-10, spec-verify-backend): exige una petición HTTP con respuesta de OpenAI contra la BD de `.env` (producción); ningún test lo cubre.
- [ ] Ninguna ruta nueva borra filas.
  - ❌ Evidencia (2026-11-10, spec-verify-backend): `app/Services/X.php:88` → `->delete()` sobre `cf_lineas`.
```

- `[x]` solo con ✅. `❌` y `⏸` dejan la casilla vacía.
- Si una verificación anterior ya dejó una sublínea, **reemplázala**, no apiles otra.
- Si un criterio ya estaba `[x]` sin sublínea (como en la 02), revalídalo y añade la sublínea. Si ya no se cumple, desmárcalo con ❌.

### 6.2 — Sección «Estado de verificación»

Añádela al final de la spec, o reemplázala si ya existe:

```markdown
## Estado de verificación

> Última verificación: 2026-11-10 · `/spec-verify-ergo-backend` · commit `<hash>` · rama `<rama>`
> Entorno: tests en SQLite en memoria · esquema en solo lectura de la BD remota de `.env` · sin peticiones HTTP

| Resultado | Criterios |
|---|---|
| ✅ Verificados | N |
| ❌ Fallan | N — <números> |
| ⏸ No verificables | N — <motivo agrupado> |

**Kit de ejecución:** <señales activadas; diferencias con la sección «Kit de ejecución» de la spec>
**Mapa (graphify):** <archivos de la spec, y discrepancias con git>
**Impactos:** <graphify affected, reglas duras comprobadas, consumidores>
**Revisión de calidad (agentes):** <revisores y modo (lanzados | emulados | del padre)>

| Archivo:línea | Severidad | Regla | Agente / skill |
|---|---|---|---|
| … | bloqueante / mayor / menor | … | … |

**Buenas prácticas (Context7 / Boost):** <observaciones con su fuente; «coherente con decisión X» cuando aplique>
**Cobertura de tests:** <criterios que sí tienen test y los que piden uno que no existe>
**Recomendación:** mantener `Implementado` | pasar a `En revisión` por <criterios ❌ y/o hallazgos bloqueantes> — decide una persona.
```

---

## Informe final

Por cada spec, y al final un total:

```
🔎 Spec verificada: specs/<NN-slug>.md   (Estado: <sin cambios>)
   Commit:        <hash>        Entorno: SQLite (tests) · esquema remoto solo lectura · sin HTTP
   Herramientas:  graphify ✅/❌ · context7 ✅/❌ · laravel-boost ✅/❌ · mysql-server ✅/❌ · engram ✅/❌
   Kit:           <señales activadas> · diferencias con el kit de la spec: <ninguna | lista>
   Criterios:     ✅ N · ❌ N · ⏸ N   de M
   Fallan:        <número — resumen de una línea, cada uno>
   Impactos:      <lo relevante de la Fase 2>
   Calidad:       <revisores · modo> — bloqueantes N · mayores N · menores N (detalle en la spec)
   Buenas práct.: <observaciones, o «sin observaciones»>
   Pendiente:     <criterios ⏸ que una persona puede cerrar a mano (Postman local con datos ficticios, producción)>
   Recomendación: <mantener Implementado | En revisión>
```

No hagas commit ni push salvo que el usuario lo pida. Si lo pide: prefijo `docs:` (bump patch en la CI) y solo los archivos de `specs/`.

---

## Nota sobre dónde viven las piezas

| Pieza | Dónde |
|---|---|
| Enrutado: señales, matriz, reglas transversales, MCP, formato del kit | `.claude/skills/spec-ergo-backend/references/enrutado.md` (§1–§7) |
| Mapa de relaciones (diagrama, recurso → quién lo usa, quién invoca a quién) | el mismo archivo, §8 |
| Diseñador | `.claude/skills/spec-ergo-backend/SKILL.md` + agente `.claude/agents/spec-ergo-backend.md` |
| Implementador | `.claude/skills/spec-impl-ergo-backend/SKILL.md` + agente `.claude/agents/spec-impl-ergo-backend.md` |
| Verificador | `.claude/skills/spec-verify-ergo-backend/SKILL.md` + agente `.claude/agents/spec-verify-ergo-backend.md` |
| Flujo genérico (se lee, no se invoca) | `.agents/skills/spec/` (+ `template.md`) y `.agents/skills/spec-impl/` |
| Agentes del dominio | `.claude/agents/{ergosanitas-developer,ergosanitas-mysql,ergosanitas-laravel,ergo-docs}.md` |
| Consumidores de un endpoint | skill raíz `D:/ErgoSanitas/Projectos/.claude/skills/ergo-contrato-api/` |
| Orquestador | `D:/ErgoSanitas/Projectos/.claude/agents/ergo-father.md` + skill `ergo-father` |
| Homólogos del front | `Front-end/ergosanitas-app/.claude/skills/{spec-ergo-front,spec-impl-ergo-front,spec-verify-ergo-front}/` |
