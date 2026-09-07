---
name: spec-impl-ergo
description: Implementa una spec aprobada del backend Ergosanitas (Laravel 11 / MySQL 5.7). Valida que el estado signifique "Aprobado" (en cualquier idioma), crea la rama git con el nombre de la spec, cambia a ella y ejecuta el plan paso a paso delegando en los agentes ergosanitas-developer / ergosanitas-laravel / ergosanitas-mysql, con pausas para revisar el diff. Cierra con una fase obligatoria de documentación delegada en ergo-docs (contrato OpenAPI y diagramas de docs/) y el checklist de cierre del repo.
disable-model-invocation: true
argument-hint: <NN-nombre-spec>
allowed-tools: Read, Glob, Grep, Edit, Write, AskUserQuestion, Agent, Skill, Bash(git status:*), Bash(git branch:*), Bash(git checkout:*), Bash(git log:*), Bash(git diff:*), Bash(git stash:*), Bash(cat:*), Bash(ls:*), Bash(php:*), Bash(vendor/bin/pint:*), Bash(vendor/bin/phpunit:*), Bash(composer:*), Bash(npx:*)
---

# /spec-impl-ergo — Implementador de specs aprobadas para Ergosanitas

Homólogo de `/spec-impl`, especializado en **este** repositorio: mismas reglas de bloqueo, pero la implementación se ejecuta con los agentes y skills de Ergosanitas, añade una **quinta fase obligatoria de documentación** y termina con el checklist de cierre del proyecto.

## Contexto de sesión

Estado actual del repositorio:
!`git status --short`

Rama actual:
!`git branch --show-current`

Specs disponibles en esta carpeta:
!`ls specs/ 2>/dev/null || echo "La carpeta specs/ no existe"`

Configuración de creación de rama:
!`cat specs/.spec-config.yml 2>/dev/null || echo "AutoCreateBranch: true (por defecto, sin archivo de configuración)"`

Providers registrados a mano (un servicio nuevo debe aparecer aquí):
!`cat bootstrap/providers.php 2>/dev/null || echo "bootstrap/providers.php no encontrado — ¿estás en la raíz del proyecto?"`

---

## Instrucciones

Sigue estas cinco fases en orden estricto. **No avances a la siguiente fase si la anterior no se completó correctamente.**

Responde siempre en el idioma del prompt inicial. El **código, los comentarios y los mensajes** que escribas van en español, sea cual sea ese idioma: es la convención del repo.

---

### Fase 1 — Identificar la spec

El argumento recibido es: `$ARGUMENTS`

Si `$ARGUMENTS` viene vacío:

- Lista los archivos disponibles en `specs/` (ya los tienes arriba).
- Pide al usuario el nombre exacto de la spec.
- Detente y espera respuesta. No continúes.

Si `$ARGUMENTS` trae valor:

- Busca el archivo en `specs/`. El usuario puede haber escrito el nombre completo (`03-alertas-ecg`), solo el número (`03`) o solo el slug (`alertas-ecg`). Localiza el archivo correcto en cualquiera de esos casos.
- Si no encuentras el archivo, muestra las specs disponibles y pide corregir el nombre.
- Si lo encuentras, continúa a la Fase 2.

---

### Fase 2 — Validar el estado de la spec

Lee el archivo de la spec localizado en la Fase 1 con la herramienta Read o con `cat`.

En el contenido, busca la línea que contiene el estado. La etiqueta habitual es `**Estado:**` (español) o `**Status:**` (inglés), pero puede estar en cualquier idioma. Identifícala por posición (línea de estado cerca del encabezado) y por la máquina de estados que la rodea, no por la etiqueta exacta.

**Regla absoluta:** solo puedes continuar si el estado **significa "Aprobado"** — sin importar el idioma.

Trata como estado **Aprobado** (y continúa) cualquiera de estos y sus equivalentes en otros idiomas:

- Español: `Aprobado`
- Inglés: `Approved`
- Portugués: `Aprovado`
- Francés: `Approuvé`
- Alemán: `Genehmigt`
- Italiano: `Approvato`
- …o la palabra de cualquier otro idioma que claramente signifique "aprobado"

Cualquier otra cosa (Borrador / Draft, En revisión / In review, Implementado / Implemented, Obsoleto / Obsolete, o un valor irreconocible) significa **detenerse** y mostrar el mensaje de error de abajo.

| Categoría de estado                                 | Ejemplos (cualquier idioma)                       | Acción                                                                 |
| --------------------------------------------------- | ------------------------------------------------- | ---------------------------------------------------------------------- |
| Aprobado                                            | `Aprobado`, `Approved`, `Aprovado`, `Approuvé`, … | Continuar a la Fase 3.                                                 |
| Borrador                                            | `Borrador`, `Draft`, …                            | Detenerse. Mostrar el mensaje de error de abajo.                       |
| En revisión                                         | `En revisión`, `In review`, …                     | Detenerse. Mostrar el mensaje de error de abajo.                       |
| Implementado                                        | `Implementado`, `Implemented`, …                  | Detenerse. Mostrar el mensaje de error de abajo.                       |
| Obsoleto                                            | `Obsoleto`, `Obsolete`, …                         | Detenerse. Mostrar el mensaje de error de abajo.                       |
| Línea de estado no encontrada / valor irreconocible | —                                                 | Detenerse. El archivo no sigue el formato esperado. Díselo al usuario. |

Si dudas de si un valor significa "aprobado", **no asumas**. Detente y pide al usuario que aclare o que actualice la spec al término canónico.

**Mensaje de error estándar cuando el estado no significa Aprobado:**

```
❌ No puedo implementar esta spec.

Estado actual: [ESTADO ENCONTRADO]
Solo trabajo con specs cuyo estado signifique "Aprobado" (p. ej. `Aprobado`, `Approved`,
o el equivalente en otro idioma).

Para continuar tienes dos opciones:
  1. Si la spec está lista para implementarse, ábrela y cambia el estado
     a "Aprobado" (o el término equivalente que use tu equipo) manualmente.
     Ese cambio lo hace la persona, no el agente.
  2. Si la spec todavía necesita trabajo, usa /spec [nombre] para retomarla.
```

No ofrezcas alternativas, no sugieras "igual puedo empezar si quieres". El bloqueo es intencional.

---

### Fase 3 — Crear la rama git y cambiar a ella

Una vez confirmado que el estado significa `Aprobado`:

0. **Revisa primero el working tree.** Mira la salida de `git status --short` del contexto de sesión. Si **no está vacío**, detente, muestra los cambios pendientes y pregunta:

   ```
   ⚠️ Hay cambios sin commitear en el working tree.
   Cambiar de rama los arrastraría. ¿Qué quieres hacer?
     1. Commitearlos o stashearlos tú, y volver a ejecutar el comando  (recomendado)
     2. Continuar igualmente — los cambios viajan a la rama nueva
   ```

   Espera la respuesta. **No hagas stash ni commit por tu cuenta** salvo que el usuario lo pida explícitamente. Si el working tree está limpio, pasa directo al paso 1 sin mencionarlo.

1. Deriva el nombre de la rama del nombre completo del archivo de la spec, sin extensión. Formato: `spec-NN-slug`. Ejemplos:

   - `03-alertas-ecg.md` → rama `spec-03-alertas-ecg`
   - `04-bioimpedancia-pdf.md` → rama `spec-04-bioimpedancia-pdf`

2. Lee el flag `AutoCreateBranch` de la **configuración de creación de rama** mostrada en el contexto de sesión.

   - Si el archivo de configuración no existe, el valor falta o es irreconocible → trátalo como `true` (el valor por defecto).
   - Solo un `false` explícito (en cualquier capitalización) desactiva la creación automática de rama.

   **Si `AutoCreateBranch` es `true` (por defecto):** procede sin preguntar.

   - Si la rama **no existe**: créala con `git checkout -b spec-NN-slug`.
   - Si **ya existe**: significa que se retoma trabajo previo. Cambia a ella, lee `git log --oneline` de la rama y dile al usuario qué pasos del plan parecen hechos y desde qué paso propones retomar. Espera confirmación del punto de retome antes de implementar nada.
   - En ambos casos: cambia a la rama con `git checkout spec-NN-slug` y confirma que el cambio fue exitoso antes de continuar.

   **Si `AutoCreateBranch` es `false`:** pregunta antes de tocar git. Muestra:

   ```
   AutoCreateBranch está en false.
   ¿Crear y cambiar a la rama spec-NN-slug? [s/N]
   ```

   - Si responde **sí**: crea/cambia a la rama igual que en el caso `true`.
   - Si responde **no** o deja vacío: **no crees ninguna rama.** Dile que implementarás en la rama actual (la del contexto de sesión) y pide confirmación explícita para continuar ahí. No improvises — espera la respuesta.

   > **Ojo con la rama `main`:** un push a `main` dispara `.github/workflows/github-action.build.yml`, que publica la imagen a Docker Hub **sin lint, tests ni migraciones**. Implementar sobre `main` es implementar sobre el canal de publicación. Si el usuario elige quedarse en `main`, adviértelo una vez y sigue.

3. Confirma visualmente al usuario que la spec está lista y qué rama está activa:

   ```
   ✅ Listo para implementar.

   Spec:   specs/NN-slug.md
   Rama:   spec-NN-slug  (activa)   (← o la rama actual, si no se creó rama nueva)
   Estado: Aprobado   (← devuelve el valor real encontrado en la spec)
   ```

4. **Todavía no empieces a implementar.** Primero muestra al usuario el resumen de la spec para que lo tenga fresco. Extrae y muestra:
   - El **objetivo** (la línea tras la etiqueta `**Objetivo:**` / `**Objective:**` / equivalente).
   - El **alcance** (la sección `## Alcance` / `## Scope` / equivalente).
   - El **plan de implementación** (la sección con los pasos numerados — `## Plan de implementación` / `## Implementation plan` / equivalente).
   - Los **criterios de aceptación** (el checklist — `## Criterios de aceptación` / `## Acceptance criteria` / equivalente).

   Empareja los encabezados por significado, no por texto exacto — la spec puede estar escrita en cualquier idioma.

5. **Carga el contexto del repositorio antes de tocar código.** En este orden:
   - Lee `CLAUDE.md` en la raíz. Es la fuente de verdad sobre arquitectura y trampas; no lo contradigas sin verificarlo en el código.
   - Invoca la skill `ergosanitas-dev` (`Skill` con `skill: "ergosanitas-dev"`) para los recetarios paso a paso y las plantillas exactas.
   - Localiza el **dominio vecino** más parecido a lo que toca la spec (el controlador / service / model existente más cercano) y anótalo: su estilo es el que hay que copiar, incluido **cuál de los dos sobres de respuesta** usa.

   Si el plan de la spec choca con algo de `CLAUDE.md` — por ejemplo, propone cambiar el sobre de respuesta de un endpoint ya consumido, corregir un typo consolidado, cachear la configuración o guardar archivos nuevos en `public/` — **dilo ahora**, antes del Paso 1, y pregunta si se implementa igual o se corrige la spec. No lo resuelvas por tu cuenta a mitad de la implementación.

---

### Fase 4 — Implementar paso a paso

Tras mostrar el resumen de la spec y cargar el contexto, dile al usuario:

```
Voy a implementar la spec siguiendo el plan de implementación al pie de la letra,
usando los agentes de Ergosanitas según el tipo de cada paso.
Haré una pausa después de cada paso para que revises el diff.

¿Empezamos con el Paso 1?
```

Espera confirmación explícita ("sí", "dale", "adelante" o equivalente). No empieces sin ella.

Una vez confirmado, sigue estas reglas durante toda la implementación:

**Nunca commitees automáticamente.** Ni por paso, ni al final. Tú escribes el código y muestras el diff; commitear es decisión y comando del usuario. Commitea solo si lo pide explícitamente.

**Una regla por encima de todas:** implementa lo que dice la spec. Si algo de la spec te parece subóptimo, menciónalo como observación pero implementa lo acordado. Los cambios a la spec van en la spec, no en el código por sorpresa.

#### A quién delegar cada paso

Antes de ejecutar un paso, clasifícalo y delega con la herramienta `Agent`. Pasa siempre en el prompt: el número y el texto literal del paso, la sección relevante de la spec, el dominio vecino identificado en la Fase 3 y la instrucción de **no commitear**.

| Tipo de paso                                                                                                                                                  | Agente                  |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------- |
| Crear o modificar controlador, service, model, provider, prompt de `app/IA/`, ruta o flujo de negocio                                                          | `ergosanitas-developer` |
| Duda de framework o lenguaje: contenedor, providers, ciclo de vida, auth (sesión / Sanctum / JWT), validación, colas, correo, PHPUnit, refactor idiomático seguro | `ergosanitas-laravel`   |
| SQL: queries, joins, `EXPLAIN`, índices, migraciones, procedimientos almacenados, esquema real, tipos y charset                                                | `ergosanitas-mysql`     |
| Documentación: contrato OpenAPI (`docs/openapi.yaml`) o los diagramas Mermaid de `docs/` (`arquitectura.md`, `flujos.md`, `diagrama-clases.md`, `modelo-datos.md`) | `ergo-docs`             |
| Paso trivial y acotado (una constante, un texto, un `use`)                                                                                                     | Hazlo tú directamente   |

Reglas de delegación:

- Un paso puede necesitar **dos** agentes en secuencia (el caso típico: `ergosanitas-mysql` define o ajusta el SP / la query, `ergosanitas-developer` la cablea en el service). Lánzalos en orden, no en paralelo, cuando el segundo dependa del resultado del primero.
- **Nunca lances más de un agente sobre los mismos archivos a la vez**: se pisan.
- El resultado del agente **no lo ve el usuario**. Resume tú lo que hizo y muestra los archivos tocados.
- No te fíes del reporte del agente sin mirar el diff. Si dice que verificó algo que no pudo verificar (un SP que vive solo en la BD, un endpoint que necesita datos reales), corrígelo al reportar.

#### Ritmo de trabajo

- Implementa un paso del plan.
- Muestra un resumen de qué archivos tocaste y qué hiciste.
- Di: `Paso N completado. ¿Puedes revisar el diff y decirme si continúo con el Paso N+1?`
- Espera confirmación antes de continuar.

#### Guardas propias de este repo (aplican en cada paso)

- **Servicio nuevo → provider nuevo registrado a mano** en `bootstrap/providers.php`. Sin eso deja de ser singleton.
- **Sobre de respuesta:** usa el del controlador que tocas. Conviven `{success, message, data}` y `{response: {status, mensaje}}`, ninguno es canónico, y cambiarle el sobre a un endpoint existente rompe al cliente.
- **Cada acción en `try/catch`**: no hay middleware ni handler global de excepciones.
- **Filtrado por perfil 3 (club) y 6 (médico)** en toda query nueva sobre chequeos. Si la spec cambia la regla, cámbiala en los **tres** sitios: `filterCalendar()`, `SearchChequeo()` y `ChequeoEmailAll()`.
- **Certificados y ECG tocan facturación:** `CertificadoService::subirCertificado()` llama a `EstadisticasService::PagoMensual(...)`. Si el paso toca ese flujo, dilo explícitamente al reportar.
- **Nunca `php artisan config:cache`**: hay `env()` fuera de `config/` (`API_PATH_CER`, `API_PATH_LOGO`, `WEBPAY_*`, `GOOGLE_CLIENT_*`).
- **MySQL 5.7**: sin CTEs ni funciones de ventana. Los procedimientos almacenados no están versionados en el repo.
- **Typos consolidados intactos**: `GoogleAuthControlle`, `UserUpdatePassowrd`, tabla `electro_cardiogranas`.
- **`vendor/bin/pint --dirty`** o rutas explícitas. `pint` a secas reformatea medio repo y entierra el cambio real.

**Si durante la implementación encuentras una ambigüedad** que la spec no resuelve:

- Detente.
- Describe la ambigüedad exactamente.
- Presenta dos o tres opciones concretas.
- Espera la decisión del usuario.
- No improvises.

**Si el usuario pide algo fuera del alcance de la spec:**

- Recuérdale que está fuera del alcance de esta spec.
- Sugiere anotarlo para la siguiente.
- No lo implementes en esta rama.

---

### Fase 5 — Documentación (obligatoria)

Terminado el último paso del plan, **antes** de la verificación de cierre, actualiza la documentación. En este repo nada de `docs/` se genera solo: ni el contrato OpenAPI ni los diagramas. Si la spec se implementa y la documentación no se toca, `docs/` queda mintiendo desde el mismo commit.

Delega en el agente `ergo-docs` con la herramienta `Agent`. Pásale en el prompt:

- La ruta del archivo de la spec y su objetivo.
- La **lista completa de archivos tocados** en la Fase 4 (sácala de `git status --short` / `git diff --stat`).
- Los endpoints nuevos o modificados, con el sobre de respuesta real que devuelven.
- La instrucción explícita de **no commitear** y de **no tocar código** (solo `docs/`, y `README.md` / `CLAUDE.md` si el cambio lo exige).

Qué debe cubrir, según lo que tocó la spec:

| Si la Fase 4 tocó… | `ergo-docs` actualiza |
| ------------------ | --------------------- |
| `routes/api.php` | `docs/openapi.yaml` (operación + conteo en `x-generated-from`) y el catálogo de endpoints del `README.md` raíz |
| Un flujo end-to-end | El diagrama de secuencia de `docs/flujos.md` |
| Controlador, service, model o prompt de `app/IA/` | `docs/diagrama-clases.md` |
| Un provider o el cableado de dependencias | `docs/arquitectura.md` |
| Tabla, columna, `status` o procedimiento almacenado | `docs/modelo-datos.md` |

Reglas de esta fase:

- **Es obligatoria, no opcional.** Si la spec no cambió nada documentable (refactor interno, sin superficie observable nueva), `ergo-docs` debe decirlo explícitamente y no editar nada. Esa respuesta es un resultado válido; saltarse la fase no lo es.
- Como con el resto de agentes: **el usuario no ve el resultado**. Resume tú qué documentos cambiaron y muestra `git diff --stat docs/`.
- No te fíes del reporte sin mirar el diff. Si `ergo-docs` dice que validó el contrato con `redocly lint` pero no hay Node en el entorno, corrígelo al reportar.
- Si `ergo-docs` encuentra una incoherencia en el **código** (una ruta duplicada, un sobre que no cuadra), no la arregla: te la reporta. Decide con el usuario si entra en esta spec o queda anotada para la siguiente.

Muestra al usuario el cierre de la fase y espera confirmación antes de la verificación:

```
Documentación actualizada. Archivos de docs/ tocados: [lista]
¿Revisas el diff de docs/ antes de que ejecute la verificación de cierre?
```

---

#### Verificación de cierre

Ejecuta la verificación de cierre del repo sobre **lo que tocaste** (nunca `config:cache`):

```bash
php -l app/Services/XService.php                  # 1. sintaxis de cada archivo tocado
vendor/bin/pint --dirty                           # 2. formato SOLO de lo tocado
php artisan config:clear && php artisan route:clear && php artisan cache:clear
php artisan route:list --path=<prefijo>           # 4. la ruta resuelve y no quedó pisada por una duplicada
php artisan tinker --execute="var_dump(app(App\Services\XService::class) === app(App\Services\XService::class));"
php artisan test                                  # 6. hoy solo stubs; deben seguir en verde
```

Y sobre la documentación de la Fase 5:

```bash
# 7. el contrato parsea y valida (redocly.yaml de la raíz; el lint requiere Node)
php -r 'require "vendor/autoload.php"; Symfony\Component\Yaml\Yaml::parseFile("docs/openapi.yaml"); echo "OK\n";'
npx @redocly/cli lint
```

Luego repasa el checklist y muestra el cierre:

```
✅ Todos los pasos del plan están implementados.

Checklist de cierre:
  [ ] Métodos de controlador en PascalCase; textos y comentarios en español
  [ ] Cuerpo en try/catch con el sobre correcto del dominio
  [ ] Provider creado y registrado en bootstrap/providers.php
  [ ] Ruta declarada, en el orden correcto, sin duplicar
  [ ] Filtrado de perfiles 3 y 6 aplicado (y en los tres sitios si cambió la regla)
  [ ] Sin config:cache; cachés limpiadas
  [ ] pint --dirty pasado
  [ ] Typos consolidados intactos (GoogleAuthControlle, UserUpdatePassowrd, electro_cardiogranas)
  [ ] docs/openapi.yaml actualizado (o consta por qué no hacía falta)
  [ ] Diagramas de docs/ afectados actualizados (flujos, clases, arquitectura, modelo de datos)
  [ ] El contrato parsea y redocly lint está en verde (o consta que no había Node)

Siguiente paso: verificar los criterios de aceptación de la spec uno por uno.
Si todos pasan, actualiza el estado de la spec a "Implementado" (o el equivalente
en el idioma de tu repo) y haz el commit final antes de mergear esta rama.

Recuerda: el mensaje del commit decide el bump de versión del CI por coincidencia
de subcadena (`major` → major, `feat` → minor, cualquier otra cosa → patch), y un
push a main publica la imagen a Docker Hub sin lint, tests ni migraciones.
```

Reporta con honestidad **qué verificaste de verdad y qué no**: los procedimientos almacenados viven solo en la BD, la facturación y varios endpoints necesitan datos reales, y los tests actuales son stubs de Laravel. Si algo quedó sin comprobar, dilo.

---

## Resumen del comportamiento esperado

```
/spec-impl-ergo 03-alertas-ecg

  Fase 1  →  Encuentra specs/03-alertas-ecg.md
  Fase 2  →  Lee el estado → "Aprobado" (o "Approved", etc.) → ✅ continúa
  Fase 3  →  git checkout -b spec-03-alertas-ecg → git checkout spec-03-alertas-ecg
             Muestra objetivo, alcance, plan y criterios
             Lee CLAUDE.md, invoca la skill ergosanitas-dev, identifica el dominio vecino
  Fase 4  →  Implementa paso a paso delegando en ergosanitas-developer /
             ergosanitas-laravel / ergosanitas-mysql, con pausas para revisar el diff
  Fase 5  →  Delega en ergo-docs: actualiza docs/openapi.yaml y los diagramas
             Mermaid afectados (flujos, clases, arquitectura, modelo de datos)
  Cierre  →  php -l, pint --dirty, *:clear, route:list, tinker, test,
             validación del contrato y el checklist de cierre del repo

/spec-impl-ergo 04-bioimpedancia-pdf  (estado: Borrador / Draft)

  Fase 1  →  Encuentra specs/04-bioimpedancia-pdf.md
  Fase 2  →  Lee el estado → "Borrador" → ❌ se detiene
             Muestra el mensaje de error estándar
             No crea rama, no toca código
```

**La creación de rama la controla el flag `AutoCreateBranch`** de `specs/.spec-config.yml`. Por defecto es `true` (crea la rama automáticamente, como arriba). Ponlo en `false` para que la Fase 3 pregunte `[s/N]` antes de crearla.

## Diferencias respecto a `/spec-impl`

Mismas reglas de bloqueo y de no-commit, y las mismas cuatro fases de `/spec-impl` más una quinta propia. Lo que añade este comando:

1. **Contexto obligatorio del repo** antes del Paso 1 (Fase 3, punto 5): `CLAUDE.md`, skill `ergosanitas-dev`, dominio vecino y sobre de respuesta.
2. **Delegación por tipo de paso** a los agentes `ergosanitas-developer`, `ergosanitas-laravel`, `ergosanitas-mysql` y `ergo-docs`.
3. **Guardas del repo** aplicadas en cada paso (providers a mano, perfiles 3 y 6, facturación de certificados, MySQL 5.7, typos consolidados, nada de `config:cache`).
4. **Fase 5 de documentación, obligatoria**: `ergo-docs` pone al día el contrato OpenAPI y los diagramas de `docs/` antes del cierre. Nada de esa carpeta se genera solo, así que si no se hace en este commit, `docs/` queda desactualizada.
5. **Verificación de cierre y checklist** propios de Ergosanitas —código y documentación—, más el aviso sobre el bump de versión del CI y el despliegue directo desde `main`.
