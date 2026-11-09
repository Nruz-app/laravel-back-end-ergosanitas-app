---
name: spec-ergo-back
description: Diseña o revisa una spec del backend Ergosanitas (Laravel 11 / MySQL 5.7). Delega el flujo de diseño en /spec (contexto, preguntas, redacción, guardado en specs/NN-slug.md) y le añade las convenciones del repo — dominio vecino y su sobre de respuesta, provider en bootstrap/providers.php, filtrado de perfiles 3 y 6, triángulo del SP, fase ergo-docs y análisis de consumidores si cambia un endpoint existente. Con una spec existente como argumento entra en modo revisión. Nunca escribe código ni aprueba la spec.
disable-model-invocation: true
argument-hint: <descripción de la feature | NN-slug de una spec existente>
---

# /spec-ergo-back — diseñador y revisor de specs con convenciones Ergosanitas

Homólogo de `/spec`, especializado en **este** repositorio. No reimplementa su flujo: lo invoca
tal cual y le añade el contexto, las guardas y la verificación del back. Tiene dos modos:

```
/spec-ergo-back <descripción>        =  /spec  +  contexto y guardas Ergosanitas (back)   → spec nueva
/spec-ergo-back <spec existente>     =  modo revisión (Fase R)                           → spec corregida
```

**No escribe código** (nada en `app/`, `routes/`, `database/`…), **no implementa** y **nunca
cambia el estado a `Aprobado`**: eso lo hace el humano. Implementar es `/spec-impl-ergo-back`.

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

---

## Si te ejecuta `ergo-father` desde `D:\ErgoSanitas\Projectos`

El super agente `ergo-father` sigue esta skill leyendo este archivo (no puede invocarla con
`Skill`: tiene `disable-model-invocation`). En ese caso el directorio actual **no es este repo**,
así que el «Contexto de sesión» de arriba no se evaluó. Entonces:

- Ejecuta a mano: `git -C "D:/ErgoSanitas/Projectos/Back-end/ergosanitas-app" status --short`,
  `git -C … branch --show-current`, `date +%F`, y lista `Back-end/ergosanitas-app/specs/` y
  `bootstrap/providers.php`.
- Lee `Back-end/ergosanitas-app/.agents/skills/spec/SKILL.md` y su `template.md` y sigue sus
  cuatro fases tal cual, con rutas absolutas.
- Si corres como **subagente** no puedes preguntar al usuario: aplica solo las correcciones
  **factuales** y devuelve las decisiones como bloque de preguntas (3–5, con opciones y
  recomendación). No las inventes.
- Todo lo demás (estado `Borrador`, nada de código, nada de commits) se aplica **igual**.

---

## Fase 0 — Elegir el modo

- `$ARGUMENTS` identifica una spec existente (`specs/NN-slug.md`, `NN`, o el slug) → **modo
  revisión**: Fase R.
- `$ARGUMENTS` es una descripción (o viene vacío) → **modo diseño**: Fase 1.

---

## Fase 1 — Delegar en /spec (modo diseño)

**Invoca la skill `spec` pasándole el argumento recibido tal cual:**

```
Skill(skill: "spec", args: "$ARGUMENTS")
```

Y **sigue sus cuatro fases** con estas precisiones del back:

### 1.1 Contexto antes de la primera pregunta

1. `CLAUDE.md` (arquitectura, los siete sobres, perfiles, SP, trampas).
2. La skill `ergosanitas-dev` (`Skill(skill: "ergosanitas-dev")`, o lee
   `.claude/skills/ergosanitas-dev/SKILL.md`): recetarios de dominio nuevo, perfiles, SP,
   documentos, WebPay, OpenAI.
3. `docs/openapi.yaml`: las operaciones del dominio que se toca.
4. El **dominio vecino** más parecido (controlador / service / model / provider existentes) y
   **qué sobre de respuesta usa** hoy (mira el método concreto, no el controlador entero).
5. Las dos specs más recientes de `specs/` (idioma, encabezados, cabecera).
6. Si hay SQL de por medio, la skill `ergosanitas-mysql` (esquema real, MySQL 5.7.22: sin CTEs
   ni funciones de ventana).

### 1.2 Lo que la spec del back debe declarar siempre

- **Rutas**: método, ruta bajo `/api`, middleware (`throttle`, `signed`…) y **orden** respecto a
  las rutas con parámetro.
- **Sobre de respuesta** de éxito y de cada error, con ejemplo JSON. Endpoint nuevo: el del
  dominio vecino, justificado. Endpoint existente: **no se cambia** sin el análisis de abajo.
- **Service + provider**: el `XServiceProvider` con `singleton()` y su alta en
  `bootstrap/providers.php`.
- **Filtrado por perfil 3 (club) y 6 (médico)** si toca queries de chequeos — y si cambia la
  regla, los tres sitios (`filterCalendar()`, `SearchChequeo()`, `ChequeoEmailAll()`).
- **Triángulo del SP** si crea o cambia uno: BD (la aplica el humano) + `.sql` versionado +
  wrapper estático del modelo.
- **Migraciones aditivas** y reversibles; nada de `DELETE`/`TRUNCATE`/`DROP` sobre datos
  productivos (Ley 21.719): se rectifica, bloquea o anonimiza.
- **Facturación**: si toca certificados/ECG, decir que `subirCertificado()` factura el mes.
- **Fase de documentación con `ergo-docs`** en el plan (último paso): `openapi.yaml`, diagramas
  afectados de `docs/`, Postman regenerado.
- **Criterios comprobables** con los comandos del repo: `php -l`, `vendor/bin/pint --dirty`,
  `php artisan route:list --path=…`, `php artisan test --filter=…`, validación del contrato.

### 1.3 Si cambia un endpoint existente

Antes de cerrar la Fase 2 exige el **análisis de consumidores** con la skill raíz
`ergo-contrato-api` (`D:/ErgoSanitas/Projectos/.claude/skills/ergo-contrato-api/SKILL.md`):
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

---

## Fase R — Modo revisión (argumento = spec existente)

1. Lee el contexto de 1.1 y la spec entera; anota su estado (si es `Implementado`/`Obsoleto`,
   avisa antes de editar).
2. **Hallazgos contra el código**: cada afirmación sobre el código actual (archivo, método,
   **línea**) se comprueba con Read/Grep. ✅ cierta · ✏️ línea movida (corrige) · ❌ falsa.
3. **Contrato**: rutas contra `routes/api.php` (y `php artisan route:list` si puedes correrlo),
   sobres contra el controlador, operaciones contra `docs/openapi.yaml`.
4. **Plan**: los archivos citados existen o se declaran nuevos; hay provider registrado si hay
   service; la fase `ergo-docs` está.
5. **Guardas de 1.2/1.3**: sobre declarado, perfiles 3/6, triángulo del SP, migraciones aditivas,
   consumidores analizados si cambia un endpoint existente.
6. **Criterios**: booleanos y comprobables con los comandos del repo.
7. **Aplica las correcciones factuales** en la spec con una nota de revisión fechada.
8. **Decisiones de producto**: bloque de 3–5 preguntas con opciones y recomendación; con las
   respuestas, edita la spec.
9. Estado final: **`Borrador`** (o `En revisión` si el usuario lo pide). Nunca `Aprobado`.

---

## Informe final

```
Spec:            specs/NN-slug.md   (nueva | revisada)
Estado:          Borrador
Sobre:           <sobre declarado por endpoint>
Consumidores:    <FE / APP afectados, o «endpoint nuevo»>
Cambios:         <qué se escribió o corrigió, y por qué>
Discrepancias:   <afirmaciones falsas, líneas movidas, archivos inexistentes>
Preguntas:       <las abiertas, con recomendación>
Siguiente paso:  el humano relee y cambia a «Aprobado» → /spec-impl-ergo-back NN-slug
```

---

## Nota sobre dónde viven las piezas

- Flujo genérico: `.agents/skills/spec/SKILL.md` + `template.md`.
- Implementador: `.claude/skills/spec-impl-ergo-back/SKILL.md`.
- Homólogo del front: `Front-end/ergosanitas-app/.claude/skills/spec-ergo-front/SKILL.md`.
- Consumidores de un endpoint: skill raíz `ergo-contrato-api`.
