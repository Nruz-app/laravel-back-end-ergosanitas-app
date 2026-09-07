---
name: ergo-docs
description: Recetario operativo para leer, revisar y actualizar la documentación de Ergosanitas — el contrato OpenAPI docs/openapi.yaml, los cuatro diagramas Mermaid de docs/ y los diagramas explorables de docs/diagramas/ generados con archify. Úsalo al añadir o cambiar una ruta, al modificar un flujo de negocio, un servicio, una clase, una tabla o un procedimiento almacenado, y para el checklist de validación de la documentación antes de commitear.
argument-hint: 'qué cambió en el código y qué documentación hay que poner al día'
allowed-tools: Read, Write, Edit, Glob, Grep, Bash, AskUserQuestion
---

# Ergosanitas — mantenimiento de la documentación

`docs/` tiene seis archivos y una carpeta de diagramas generados. **Nada se genera solo desde el código**: no hay anotaciones L5-Swagger ni extracción de diagramas desde el código. Todo se mantiene a mano, en el mismo commit que el cambio que lo provoca.

| Archivo | Qué es | Tamaño aproximado |
|---|---|---|
| `openapi.yaml` | Contrato OpenAPI 3.0.3, 81 operaciones | ~5.800 líneas |
| `index.html` | Visor Swagger UI del contrato | pequeño, casi nunca se toca |
| `arquitectura.md` | Componentes, capas, providers, despliegue | ~315 líneas |
| `flujos.md` | 12 diagramas de secuencia end-to-end | ~571 líneas |
| `diagrama-clases.md` | Clases UML por dominio | ~600 líneas |
| `modelo-datos.md` | ERD, estados, autorización, catálogo de SP | ~461 líneas |
| `diagramas/` | Diagramas explorables en HTML generados con archify desde un `.json` | ~700 KB por artefacto |

`CLAUDE.md` (raíz) es la referencia de arquitectura y `docs/README.md` el índice de la carpeta. Esta skill es el **cómo hacerlo**.

---

## Paso 0 — Determinar el delta (siempre)

No abras el contrato hasta saber qué cambió de verdad.

```bash
git status --short                  # qué hay sin commitear
git diff --stat                     # qué archivos y cuánto
git log --oneline -10               # de dónde viene esto
php artisan route:list --json       # LA fuente de verdad de las rutas
```

Contrasta las rutas registradas con las documentadas:

```bash
# rutas registradas bajo api/ (ordenadas, sin duplicar)
php artisan route:list --json | php -r '$r=json_decode(stream_get_contents(STDIN),true); $u=[]; foreach($r as $x){ if(strpos($x["uri"],"api/")===0) $u[]=strtoupper($x["method"])." /".substr($x["uri"],4); } $u=array_unique($u); sort($u); echo implode("\n",$u),"\n";'

# operaciones documentadas
grep -cE "^    (get|post|put|patch|delete):" docs/openapi.yaml
```

**Filtra siempre por el prefijo `api/`.** `route:list` devuelve además cuatro rutas de Laravel que el contrato no documenta a propósito: `/`, `/sanctum/csrf-cookie`, `/storage/{path}` y `/up`. Contarlas te hace ver una divergencia que no existe.

A día de hoy la comparación cuadra: **81 rutas únicas bajo `api/` = 81 operaciones documentadas**. Si te sale otro número, hay algo que documentar o que sobra.

Salida de este paso: **la lista concreta de documentos a tocar**, según la tabla de impacto del agente `ergo-docs`. Enúmerala antes de editar.

Si el delta no cambia comportamiento observable (un refactor interno, un rename privado, formateo), **no toques `docs/`**. Una edición cosmética en un archivo de 5.800 líneas es ruido puro en el diff.

---

## Receta A — Ruta nueva o modificada → `openapi.yaml`

### 1. Ubica la operación

Los `paths:` empiezan en la línea ~983 y están **agrupados por bloques de comentario** en mayúsculas:

```yaml
  # ====================================================================
  # UTILIDADES
  # ====================================================================
```

Mete la ruta nueva en el bloque de su dominio, junto a sus hermanas. No la pegues al final del archivo.

### 2. Elige el `tag`

Los 15 tags ya declarados (líneas ~229-275). **No inventes uno nuevo** salvo que el dominio sea realmente nuevo; si lo creas, decláralo también en la sección `tags:` con su `description`.

`Autenticación y usuarios` · `Servicios` · `Agenda de horas` · `Chequeo cardiovascular` · `Electrocardiograma` · `Certificados` · `Bioimpedancia` · `Ficha clínica` · `Incidencias deportivas` · `Estadísticas y pagos` · `Asistentes IA` · `Carga masiva` · `Pagos WebPay` · `Correo` · `Utilidades`

### 3. Escribe la operación en el estilo del archivo

```yaml
  /mi-dominio/accion/{rut_paciente}:
    post:
      tags: [Chequeo cardiovascular]
      summary: Frase corta, sin punto final
      operationId: camelCaseUnicoEnTodoElArchivo
      description: |
        Qué hace, en español. Aquí van las **rarezas reales**: si devuelve 200 con error
        dentro, si el sobre difiere entre éxito y `catch`, si la ruta está duplicada, si
        toca facturación.
      parameters:
        - $ref: '#/components/parameters/RutPaciente'
      requestBody:
        required: true
        content:
          application/json:
            schema: { ... }
      responses:
        '200':
          description: Qué significa este 200 concreto.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SobreExitoA'
        '500':
          description: Excepción no controlada.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorThrowable'
```

**Reutiliza lo que ya existe en `components:`** en vez de repetir estructuras:

- Parámetros: `RutPaciente`, `IdPaciente`, `UserEmailPath`, `PerfilPath`.
- Sobres: `SobreExitoA`, `SobreErrorA`, `SobreRespuestaB`, `SobrePlanoB`, `ErrorThrowable`, `ErrorValidacion`.
- Entidades: `ChequeoCardiovascular`, `ChequeoConEstado`, `ElectroCardiograma`, `CertificadoUrl`, `Bioimpedancia`, `IncidenteDeportivo`, `IncidenciaEnvoltorio`, `AgendaHora`, `Servicio`, `UsuarioListado`, `UsuarioAutenticado`, `RespuestaLogin`, `ResultadoSP`.

Si tu endpoint devuelve una forma nueva y reutilizable, añádela a `components/schemas` con el mismo estilo; si es de un solo uso, va inline.

### 4. El sobre que documentas es el que devuelve el código

Abre el método del controlador y míralo. Conviven **siete formatos** y varios controladores mezclan más de uno según el método y según si es el camino feliz o el `catch`. No lo deduzcas del controlador vecino ni del "ideal".

**Nunca inventes un 4xx.** Esta API devuelve 500 (o incluso 200 con error dentro) donde otra devolvería 400/404. `redocly.yaml` desactiva `operation-4xx-response` justo por eso, con el motivo escrito.

### 5. Actualiza los contadores y el catálogo

Si el número de operaciones cambió:

```bash
# recuenta de verdad
grep -cE "^    (get|post|put|patch|delete):" docs/openapi.yaml
```

Y actualiza:

- `info.x-generated-from` en `openapi.yaml` (`"routes/api.php — 81 endpoints"`).
- La tabla "Qué hay aquí" de `docs/README.md`, que repite la cifra.
- El **catálogo de endpoints** del `README.md` de la raíz.
- `CLAUDE.md` solo si cambió una regla de arquitectura (un sobre, una trampa), no por añadir una ruta.

---

## Receta B — Flujo de negocio → `flujos.md`

El archivo tiene 12 secciones numeradas, cada una con un `sequenceDiagram` de Mermaid:

1. Login y perfiles · 2. Carga masiva desde Excel · 3. Ciclo de vida del chequeo · 4. Certificado y facturación · 5. ECG del cardiólogo · 6. Generación de documentos · 7. Bioimpedancia con IA · 8. Informe de bioimpedancia · 9. Asistente clínico SAM · 10. Asistente por club · 11. Pago WebPay · 12. Agenda, correo y estadísticas

- **Cambio en un flujo existente:** edita solo los mensajes del diagrama que cambiaron. No reescribas el diagrama entero.
- **Flujo nuevo:** añádelo como **§13** al final. **No renumeres** los anteriores: `docs/README.md`, `arquitectura.md` y los enlaces del repo apuntan a los números actuales. Actualiza la cifra "12 diagramas" de `docs/README.md` y del `README.md` raíz.
- Copia el estilo del vecino: mismos nombres de participantes (`Cliente`, el controlador, el servicio, `MySQL`, `OpenAI`…), `Note over` para las advertencias, y español.
- **Si el flujo toca facturación** (`CertificadoService::subirCertificado()` → `EstadisticasService::PagoMensual(..., "ADD")`), eso va explícito en el diagrama. Es el efecto cruzado que más se pasa por alto.

---

## Receta C — Clase, servicio o provider → `diagrama-clases.md` (+ `arquitectura.md`)

`diagrama-clases.md` tiene ocho secciones por dominio:

Chequeo cardiovascular · Certificados y electrocardiogramas · Asistentes de IA · Bioimpedancia · Incidencias y ficha clínica · Usuarios y perfiles · Agenda, servicios y pagos · Convenciones y anomalías

| Qué cambió en el código | Qué tocas |
|---|---|
| Método nuevo en un controlador o servicio | El `classDiagram` de su dominio |
| Clase nueva (controller/service/model) | El `classDiagram` de su dominio + relaciones |
| Prompt nuevo en `app/IA/` | § Asistentes de IA **y** la subsección "Qué modelo de OpenAI usa cada prompt" |
| Provider nuevo o dependencia entre servicios | `arquitectura.md` § Mapa de dominios **y** § Inyección de dependencias |
| Una anomalía nueva (servicio sin provider, typo consolidado) | § Convenciones y anomalías |

Recuerda al documentar: **un servicio nuevo exige provider registrado a mano** en `bootstrap/providers.php`. Si el código añadió el servicio pero no el provider, el diagrama debe reflejar la realidad (no es singleton) **y** hay que reportarlo — como ya pasa con `ChequeoCardiovascularWordService`.

Los métodos de controlador van en `PascalCase` en el código y así se escriben en el diagrama.

---

## Receta D — Tabla, `status` o SP → `modelo-datos.md`

Secciones: ERD del dominio clínico · ERD de negocio y conversaciones · Las tres claves de cruce · Máquina de estados del chequeo (+ quién escribe el `status`, + el campo derivado `estado_paciente`) · Autorización por perfil · Procedimientos almacenados.

- **Tabla o columna nueva:** al ERD que corresponda. Recuerda que **no hay foreign keys**: las relaciones se dibujan por `rut` y por el par `(rut_paciente, id_chequeo)`, y casi todas las columnas clínicas son `varchar`.
- **Tabla sin migración:** márcala como tal. `users_metadata`, `params`, `electro_cardiogranas` y `pago_mensual` existen solo en la BD; `php artisan migrate` sobre una base vacía no reproduce producción.
- **Nombres reales:** la tabla de ECG es `electro_cardiogranas` (con "n"). Es un typo consolidado en producción; en el ERD se escribe igual.
- **Nuevo punto que escribe `status`:** la tabla "Quién escribe el `status`" enumera hoy **seis** sitios en PHP. Si aparece un séptimo, entra ahí y también en la tabla equivalente de `CLAUDE.md`.
- **SP nuevo o con firma distinta:** entra al catálogo de SP **solo si confirmaste la firma contra la base de datos**:

```sql
SHOW CREATE PROCEDURE SP_nombre;
SHOW PROCEDURE STATUS WHERE Db = DATABASE();
```

Si no pudiste consultar la BD, documenta lo que se ve desde PHP (el `DB::select('CALL SP_xxx(?)')` del modelo) y **di explícitamente que la firma no está verificada**. No inventes parámetros a partir del nombre.

- **Regla de autorización por perfil:** si cambia, revisa que la sección refleje que el patrón está duplicado en `filterCalendar()`, `SearchChequeo()` y `ChequeoEmailAll()`, y que hoy **las tres copias divergen** (`filterCalendar()` no aplica el perfil 6; solo `SearchChequeo()` filtra `status = 'ECG FOTO'`).

---

## Receta E — Diagrama explorable con archify

Los cuatro `.md` de `docs/` usan **Mermaid** y son la documentación de referencia: se renderizan solos en GitHub y en VS Code, sin instalar nada, y el diff de un cambio es legible. **Esa sigue siendo la opción por defecto.**

`docs/diagramas/` es otra cosa: HTML autocontenido generado con la skill `archify`, con zoom, búsqueda, temas claro/oscuro, vistas guiadas y exportación. Cuesta más de mantener (700 KB por artefacto) y no se lee en un diff.

| Usa Mermaid en un `.md` cuando… | Usa archify cuando… |
|---|---|
| Es documentación de referencia que se consulta en GitHub | Hace falta un artefacto explorable o de presentación |
| El diagrama cambia a menudo con el código | La vista es estable (arquitectura general) |
| Quieres que el cambio se revise en el diff | El valor está en navegarlo, no en leer su fuente |

**El HTML es generado, no fuente.** La fuente es el `.architecture.json`. Nunca edites el `.html` a mano: el siguiente `deliver` lo pisa.

### Cómo regenerar un diagrama existente

Los comandos se ejecutan **desde el directorio de la skill archify**, con rutas absolutas al `docs/diagramas/` del proyecto:

```bash
cd .claude/skills/archify

# 1. validar (repetir tras cada edición del JSON)
node bin/archify.mjs validate architecture <ruta>/arquitectura.architecture.json --quality showcase --json

# 2. entregar (acepta y congela; exit != 0 nunca es éxito)
node bin/archify.mjs deliver architecture <ruta>/arquitectura.architecture.json <ruta>/arquitectura.html --quality showcase --json

# 3. evidencia de navegador sobre el HTML ya entregado
node bin/archify.mjs visual-check <ruta>/arquitectura.html --json
```

Un pase válido en `showcase` son **9 checks OK con 0 errores y 0 warnings** de composición. `visual-check` deja capturas PNG y un `.json` de recibo junto al HTML; ábrelas para el juicio visual, que ningún comando aprueba por ti.

Aprendido al construir el diagrama de arquitectura, para no repetirlo:

- **El alto lo mandan las tarjetas, no el diagrama.** El primer intento se pasaba 322 px del viewport de 1440×900 con cuatro tarjetas de tres ítems. Tres tarjetas de dos ítems cortos caben. Si un dato ya está en un nodo, no lo repitas en una tarjeta.
- **Una cadena de seis columnas no cabe legible**: el texto de contexto baja de 6 px y falla `composition/desktop-readability`. Cinco columnas sí; sube el nodo de entrada a la fila de arriba para ganar una.
- **Ninguna arista puede atravesar un nodo ajeno.** Si un dato exige saltarse una capa (los seis controladores que hablan directo con los modelos), va en una tarjeta, no en una flecha que cruce el nodo intermedio.
- Empieza con rutas y etiquetas automáticas. Añade `labelDy` **solo** cuando el validador lo diagnostique, y usa el valor que él sugiere.

---

## Receta F — Revisión de coherencia (sin cambio de código)

Auditoría a demanda: ¿sigue el contrato describiendo la API real?

1. Saca las rutas registradas y las operaciones documentadas (comandos del Paso 0).
2. Compara en tres direcciones:
   - **Rutas sin documentar** → falta la operación en el contrato.
   - **Operaciones huérfanas** → documentan una ruta que ya no existe, o que quedó pisada por una duplicada.
   - **Método o path distinto** entre una y otra.
3. Revisa la coherencia de los conteos: `x-generated-from`, `docs/README.md`, catálogo del `README.md` raíz, "12 diagramas" en `flujos.md`.
4. **Reporta las divergencias; no arregles el código.** Si el contrato está bien y el código está mal, eso es trabajo de `ergosanitas-developer`.

---

## Paso final — Validar antes de dar por terminado

```bash
# 1. el YAML parsea
php -r 'require "vendor/autoload.php"; Symfony\Component\Yaml\Yaml::parseFile("docs/openapi.yaml"); echo "OK\n";'

# 2. validación estructural OpenAPI (usa redocly.yaml de la raíz; requiere Node)
npx @redocly/cli lint

# 3. las rutas documentadas coinciden con las registradas
php artisan route:list --json

# 4. el conteo de operaciones que declaras es el real
grep -cE "^    (get|post|put|patch|delete):" docs/openapi.yaml

# 5. el diff es mínimo y localizado
git diff --stat docs/
```

Si no hay Node, el paso 2 no corre: **dilo**, no lo des por pasado.

Los `.md` con Mermaid se renderizan en GitHub y en la vista previa de VS Code; para revisarlos, ábrelos ahí. Para el contrato con Swagger UI:

```bash
php -S localhost:8080 -t docs   # y abre http://localhost:8080
```

### Checklist de cierre documental

- [ ] El YAML parsea y `redocly lint` está en verde (o consta por qué no corrió)
- [ ] Rutas documentadas == rutas de `route:list` (sin huérfanas ni faltantes)
- [ ] Conteo de operaciones actualizado en `x-generated-from`, `docs/README.md` y el catálogo del `README.md` raíz
- [ ] Diagramas Mermaid afectados actualizados (`flujos.md`, `diagrama-clases.md`, `arquitectura.md`, `modelo-datos.md`)
- [ ] Sobre de respuesta documentado == el que devuelve el código, incluido el del `catch`
- [ ] Sin 4xx inventados; las rarezas reales (200 con error, 201 en GET, cuerpo vacío de WebPay) quedan documentadas
- [ ] Typos consolidados intactos: `GoogleAuthControlle`, `UserUpdatePassowrd`, `electro_cardiogranas`
- [ ] Firmas de SP confirmadas contra la BD, o marcadas como no verificadas
- [ ] Si tocaste un diagrama de `docs/diagramas/`: editado el `.json`, reentregado con `deliver` (9 checks, 0 errores) y `visual-check` en verde
- [ ] Diff mínimo: nada reordenado ni reformateado que no cambiara
- [ ] Nada tocado fuera de `docs/` (+ `README.md` / `CLAUDE.md` si el cambio lo exigía); nada commiteado

### Commit

La documentación va **en el mismo commit** que el cambio de código que la provoca. Cuidado con el mensaje: el CI decide el bump de versión por coincidencia de subcadena (`major` → major, `feat` → minor, cualquier otra cosa → patch), así que un `docs: ...` normal bumpea patch, pero un `docs: documenta el nuevo feature de X` bumpea minor sin querer.
