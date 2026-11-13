---
name: spec-ergo-backend
description: Diseña o revisa una spec del backend Ergosanitas (Laravel 11 / MySQL 5.7). Detecta qué toca el requerimiento y arma automáticamente su «Kit de ejecución» (skills, agentes y MCP según references/enrutado.md — graphify, Context7, Laravel Boost, mysql-server, engram), sigue el flujo de /spec (contexto, preguntas, redacción, guardado en specs/NN-slug.md) y le añade las convenciones del repo — dominio vecino y su sobre de respuesta, provider en bootstrap/providers.php, filtrado de perfiles 3 y 6, triángulo del SP, fase ergo-docs y análisis de consumidores si cambia un endpoint existente. Con una spec existente como argumento entra en modo revisión. Nunca escribe código ni aprueba la spec.
disable-model-invocation: true
argument-hint: <descripción de la feature | NN-slug de una spec existente>
---

# /spec-ergo-backend — diseñador y revisor de specs con convenciones Ergosanitas

Homólogo de `/spec`, especializado en **este** repositorio. No reimplementa su flujo: lo sigue
tal cual y le añade el **enrutado automático de recursos**, el contexto, las guardas y la
verificación del back. Tiene dos modos:

```
/spec-ergo-backend <descripción>        =  Kit + /spec + guardas Ergosanitas (back)   → spec nueva
/spec-ergo-backend <spec existente>     =  modo revisión (Fase R)                     → spec corregida
```

Es el primer eslabón del flujo del back:

```
/spec-ergo-backend         → diseña la spec y su Kit de ejecución   (Borrador)   ← este
   (una persona aprueba)                                             (Aprobado)
/spec-impl-ergo-backend    → la implementa con el Kit                (Implementado)
/spec-verify-ergo-backend  → comprueba cada criterio con el Kit      (evidencia)
```

**No escribe código** (nada en `app/`, `routes/`, `database/`…), **no implementa** y **nunca
cambia el estado a `Aprobado`**: eso lo hace el humano. Solo escribe en `specs/`.

Responde siempre en el idioma del prompt inicial; la spec se escribe en español, como las
existentes.

---

## Contexto de sesión

Estado actual del repositorio:
!`git status --short`

Rama actual:
!`git branch --show-current`

Fecha de hoy (úsala en la cabecera, nunca la inventes):
!`date +%F`

Specs disponibles:
!`ls specs/ 2>/dev/null || echo "La carpeta specs/ no existe"`

Providers registrados a mano:
!`cat bootstrap/providers.php 2>/dev/null || echo "bootstrap/providers.php no encontrado"`

Grafo de graphify:
!`test -f graphify-out/graph.json && echo "grafo OK" || echo "FALTA grafo → graphify update ."`

---

## Si te ejecuta `ergo-father` o el agente `spec-ergo-backend`

Esta skill tiene `disable-model-invocation`: un agente no la invoca con `Skill`, la **lee** y la
sigue. Si el directorio actual no es este repo, el «Contexto de sesión» no se evaluó. Entonces:

- Ejecuta a mano: `git -C "D:/ErgoSanitas/Projectos/Back-end/ergosanitas-app" status --short`,
  `git -C … branch --show-current`, `date +%F`, y lista `Back-end/ergosanitas-app/specs/`,
  `bootstrap/providers.php` y `graphify-out/graph.json`. Usa rutas absolutas.
- Si corres como **subagente** no puedes preguntar al usuario ni lanzar agentes: aplica solo las
  correcciones **factuales**, emula a los agentes del kit (§6 de `enrutado.md`) y devuelve las
  decisiones como bloque de preguntas (3–5, con opciones y recomendación). No las inventes.
- Todo lo demás (estado `Borrador`, nada de código, nada de commits) se aplica **igual**.

---

## Fase 0 — Elegir el modo

- `$ARGUMENTS` identifica una spec existente (`specs/NN-slug.md`, `NN`, o el slug) → **modo
  revisión**: Fase E y luego Fase R.
- `$ARGUMENTS` es una descripción (o viene vacío) → **modo diseño**: Fase E y luego Fase 1.

---

## Fase E — Enrutado: armar el Kit de ejecución (automática, siempre)

Antes de la primera pregunta al usuario:

1. **Lee `.claude/skills/spec-ergo-backend/references/enrutado.md` entero.** Es la fuente única
   de señales, matriz, reglas transversales y formato del kit.
2. **Memoria y mapa:**
   - `mem_search` (engram) con el dominio y las palabras clave del requerimiento: decisiones
     previas, specs relacionadas, deuda conocida. Si engram no responde, sigue y dilo.
   - `graphify query "<feature o dominio>"` y `graphify explain "<dominio vecino>"` para ubicar
     el controlador / service / model más parecido. Nunca el modo semántico.
3. **Detecta las señales** (§2 de `enrutado.md`) con la descripción (o la spec, en revisión) y lo
   que devolvió graphify. Anota la evidencia de cada una.
4. **Carga los recursos de la columna D** de la matriz:
   - MCP: **una** llamada a `ToolSearch` con todos los del kit.
   - Skills invocables con `Skill` (`ergosanitas-dev`, `ergosanitas-api`, `ergosanitas-mysql`,
     `ergosanitas-laravel`, `ergosanitas-security`, `ergosanitas-testing`,
     `ergosanitas-architecture`, `ergo-docs`, `prompt-engineering-patterns`,
     `engineering:architecture`, `engineering:testing-strategy`…).
   - `ergo-contrato-api` (raíz) se **lee**: `D:/ErgoSanitas/Projectos/.claude/skills/ergo-contrato-api/SKILL.md`.
5. **Consultas de diseño** que fijan decisiones de la spec:
   - Context7 (`resolve-library-id` con la versión de `composer.lock` + `query-docs`) y Boost
     `search-docs` sobre **los patrones concretos** que la spec va a exigir (limitador,
     `temporarySignedRoute`, `SoftDeletes`, `OpenAI::fake()`, mPDF…).
   - `mysql-server` / Boost `database-schema` **solo metadatos** de las tablas que la spec toca o
     de las que depende (columnas, índices, tablas sin migración). Nunca filas de pacientes.
   - Si hay `S-sql` o `S-laravel` con dudas de fondo y estás en sesión principal, consulta en
     solo lectura al agente `ergosanitas-mysql` / `ergosanitas-laravel` con la plantilla de §6.
6. **Muestra el kit** (formato §7 de `enrutado.md`) y una línea `Cargado: … · No disponible: …`.
   No pidas confirmación para el kit: es automático. Sí sigue al flujo de preguntas.

Si `S-ambiguo` se activa, usa la técnica de `brainstorming` (alternativas y preguntas una a una)
**dentro** de la Fase 2 de `/spec`, sin generar su documento de diseño ni salir de este flujo.

---

## Fase 1 — Seguir el flujo de /spec (modo diseño)

`spec` tiene `disable-model-invocation`: **no** la invoques con `Skill`. **Lee**
`.agents/skills/spec/SKILL.md` y `.agents/skills/spec/template.md` y **sigue sus cuatro fases**,
con `$ARGUMENTS` como descripción, y con estas precisiones del back:

### 1.1 Contexto antes de la primera pregunta

Ya cargado en la Fase E. Además:

1. `CLAUDE.md` (arquitectura, los siete sobres, perfiles, SP, trampas).
2. `docs/openapi.yaml`: las operaciones del dominio que se toca.
3. El **dominio vecino** (de graphify) y **qué sobre de respuesta usa** hoy (mira el método
   concreto, no el controlador entero).
4. Las dos specs más recientes de `specs/` (idioma, encabezados, cabecera).
5. MySQL 5.7.22 si hay SQL: sin CTEs ni funciones de ventana.

### 1.2 Lo que la spec del back debe declarar siempre

- **Rutas**: método, ruta bajo `/api`, middleware (`throttle`, `signed`…) y **orden** respecto a
  las rutas con parámetro.
- **Sobre de respuesta** de éxito y de cada error, con ejemplo JSON. Endpoint nuevo: el del
  dominio vecino, justificado. Endpoint existente: **no se cambia** sin el análisis de 1.3.
- **Service + provider**: el `XServiceProvider` con `singleton()` y su alta en
  `bootstrap/providers.php`.
- **Filtrado por perfil 3 (club) y 6 (médico)** si toca queries de chequeos — y si cambia la
  regla, los tres sitios (`filterCalendar()`, `SearchChequeo()`, `ChequeoEmailAll()`).
- **Triángulo del SP** si crea o cambia uno: BD (la aplica el humano) + `.sql` versionado +
  wrapper estático del modelo.
- **Migraciones aditivas** y reversibles; nada de `DELETE`/`TRUNCATE`/`DROP` sobre datos
  productivos (Ley 21.719): se rectifica, bloquea o anonimiza.
- **Facturación**: si toca certificados/ECG, decir que `subirCertificado()` factura el mes.
- **Seguridad** (`S-seguridad`): qué datos clínicos expone, quién puede llamar, límites, y la
  deuda que hereda (sin autenticación real), con lo que diga `ergosanitas-security`.
- **Tests** (`S-tests`): qué se prueba en SQLite y con qué `TestCase`, según `ergosanitas-testing`.
- **Decisiones** (`S-arquitectura`): alternativas, elegida y consecuencias, en formato ADR breve,
  citando la fuente de Context7/Boost cuando una decisión se apoye en ella.
- **Fase de documentación con `ergo-docs`** en el plan (último paso): `openapi.yaml`, diagramas
  afectados de `docs/`, `docs/diagramas/` con `archify` si aplica, Postman regenerado.
- **Criterios comprobables** con los comandos del repo: `php -l`, `vendor/bin/pint --dirty`,
  `php artisan route:list --path=…`, `php artisan test --filter=…`, validación del contrato,
  consultas de esquema en solo lectura. Redáctalos para que `/spec-verify-ergo-backend` pueda
  marcarlos **sin HTTP**.
- **Plan por pasos con su agente**: cada paso del plan dice qué agente del kit lo ejecuta
  (`ergosanitas-developer`, `ergosanitas-mysql`, `ergosanitas-laravel`, `ergo-docs`).
- **Sección `## Kit de ejecución`** con el formato de §7 de `enrutado.md`.

### 1.3 Si cambia un endpoint existente (`S-consumido`)

Antes de cerrar la Fase 2 de `/spec` exige el **análisis de consumidores** con `ergo-contrato-api`:
quién lo llama en el front (`Front-end/ergosanitas-app/src/`) y en la app móvil
(`APP/V2/ergosanitasApp/src/`), y la clasificación de riesgo. La spec lo registra y, si afecta al
front, propone la **spec hermana** del front (`/spec-ergo-front`).

### 1.4 Guardado

- Ruta: `specs/NN-slug.md` (carpeta **plana**, numeración global: el mayor existente + 1).
- Cabecera en español: `**Estado:** Borrador`, `**Depende de:**`, `**Fecha:**` (la del contexto),
  `**Objetivo:**` (una frase).
- Estados: `Borrador` → `En revisión` → `Aprobado` → `Implementado` (y `Obsoleto`). **Se guarda en
  `Borrador`**; `Aprobado` lo pone el humano.
- No toques `specs/.spec-config.yml` si ya existe.

### 1.5 Memoria

Al cerrar, guarda en engram (`mem_search` del tema primero; `mem_update` si ya existe,
`mem_save` si es nuevo) las **decisiones de producto confirmadas** por el usuario y el porqué.
Nada de datos de pacientes ni de `.env`. Si engram falla, dilo y sigue.

---

## Fase R — Modo revisión (argumento = spec existente)

1. Con el kit de la Fase E cargado, lee el contexto de 1.1 y la spec entera; anota su estado (si
   es `Implementado`/`Obsoleto`, avisa antes de editar).
2. **Hallazgos contra el código**: cada afirmación sobre el código actual (archivo, método,
   **línea**) se comprueba con graphify y Read/Grep. ✅ cierta · ✏️ línea movida (corrige) · ❌ falsa.
3. **Contrato**: rutas contra `routes/api.php` (y `php artisan route:list` si puedes correrlo),
   sobres contra el controlador, operaciones contra `docs/openapi.yaml`.
4. **Esquema**: tablas y columnas citadas contra `mysql-server` / Boost (solo metadatos).
5. **Plan**: los archivos citados existen o se declaran nuevos; hay provider registrado si hay
   service; cada paso tiene agente; la fase `ergo-docs` está.
6. **Guardas de 1.2/1.3**: sobre declarado, perfiles 3/6, triángulo del SP, migraciones aditivas,
   seguridad, tests, consumidores analizados si cambia un endpoint existente.
7. **Kit**: recalcula el kit y compáralo con la sección `## Kit de ejecución` de la spec. Si no
   existe, añádela; si quedó desfasado, corrígelo y di qué cambió.
8. **Criterios**: booleanos y comprobables sin HTTP con los comandos del repo.
9. **Aplica las correcciones factuales** en la spec con una nota de revisión fechada.
10. **Decisiones de producto**: bloque de 3–5 preguntas con opciones y recomendación; con las
    respuestas, edita la spec.
11. Estado final: **`Borrador`** (o `En revisión` si el usuario lo pide). Nunca `Aprobado`.

---

## Informe final

```
Spec:            specs/NN-slug.md   (nueva | revisada)
Estado:          Borrador
Kit:             <señales activadas> → skills · agentes · MCP   (No disponible: …)
Sobre:           <sobre declarado por endpoint>
Consumidores:    <FE / APP afectados, o «endpoint nuevo»>
Cambios:         <qué se escribió o corrigió, y por qué>
Discrepancias:   <afirmaciones falsas, líneas movidas, archivos inexistentes>
Preguntas:       <las abiertas, con recomendación>
Siguiente paso:  el humano relee y cambia a «Aprobado» → /spec-impl-ergo-backend NN-slug
```

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
