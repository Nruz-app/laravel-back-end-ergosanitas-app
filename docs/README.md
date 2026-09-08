# Documentación de la API de Ergosanitas

Esta carpeta contiene el contrato de la API y los diagramas del sistema. La documentación en prosa —instalación, stack, catálogo de endpoints, trampas conocidas— está en el [README de la raíz](../README.md); las convenciones para trabajar en el código, en [`CLAUDE.md`](../CLAUDE.md).

## Qué hay aquí

| Archivo | Qué es |
|---|---|
| [`openapi.yaml`](openapi.yaml) | Contrato OpenAPI 3.0.3 con las 84 operaciones: request, respuestas por código y ejemplos |
| [`index.html`](index.html) | Visor Swagger UI del contrato, listo para abrir en el navegador |
| [`arquitectura.md`](arquitectura.md) | Componentes, las tres capas y sus excepciones, inyección de dependencias y despliegue |
| [`flujos.md`](flujos.md) | 13 diagramas de secuencia, uno por flujo de negocio end-to-end |
| [`diagrama-clases.md`](diagrama-clases.md) | Clases UML por dominio: controladores, servicios, modelos y prompts |
| [`modelo-datos.md`](modelo-datos.md) | ERD de las 20 tablas, máquina de estados del chequeo, autorización por perfil y catálogo de procedimientos almacenados |
| [`diagramas/arquitectura.html`](diagramas/arquitectura.html) | **Explorable** — mapa de componentes: zoom, búsqueda, vistas guiadas y exportación |
| [`diagramas/juego-cartas.html`](diagramas/juego-cartas.html) | **Explorable** — secuencia del juego de cartas: las tres capas, el SP y el orden en PHP |
| [`diagramas/ciclo-chequeo.html`](diagramas/ciclo-chequeo.html) | **Explorable** — máquina de estados del chequeo y el efecto de facturación de `ECG FOTO` |

## Por dónde empezar

- **¿Qué hace este proyecto y cómo se conecta todo?** → [`arquitectura.md`](arquitectura.md)
- **¿Qué pasa cuando alguien sube un certificado?** → [`flujos.md`](flujos.md)
- **¿Dónde vive la lógica de X?** → [`diagrama-clases.md`](diagrama-clases.md)
- **¿Cómo se relacionan las tablas y qué significa `status`?** → [`modelo-datos.md`](modelo-datos.md)
- **¿Qué recibe y devuelve un endpoint concreto?** → [`openapi.yaml`](openapi.yaml)
- **¿Cómo se calcula el nivel de un alumno?** → [`flujos.md` §13](flujos.md) o el explorable [`diagramas/juego-cartas.html`](diagramas/juego-cartas.html)

## Ver la documentación

Los cuatro `.md` usan diagramas **Mermaid**, que se renderizan solos en GitHub y en la vista previa de VS Code. No hace falta instalar nada. **Son la documentación de referencia**: se leen en el diff y cambian con el código.

Los tres `.html` de [`diagramas/`](diagramas/) son otra cosa: artefactos **explorables** (zoom, búsqueda, temas claro/oscuro, vistas guiadas, exportación a PNG/SVG) para presentar o navegar. Se abren con doble clic, sin servidor. Cuestan más de mantener —~700 KB cada uno y no se leen en un diff—, así que solo cubren las tres vistas más estables.

Para el contrato OpenAPI con Swagger UI, sirve la carpeta por HTTP (el navegador no carga el YAML desde `file://`):

```bash
php -S localhost:8080 -t docs
# y abre http://localhost:8080
```

## Mantenerlo al día

Nada de esto se genera automáticamente: **no hay anotaciones L5-Swagger ni extracción de diagramas desde el código**. Al cambiar algo hay que actualizar la documentación en el mismo commit.

| Si tocas… | Actualiza |
|---|---|
| Una ruta en `routes/api.php` | `openapi.yaml` (contrasta con `php artisan route:list --json`) y el catálogo del README |
| Un flujo de negocio | El diagrama de secuencia correspondiente en `flujos.md` |
| Un servicio, controlador o provider | `diagrama-clases.md` y, si cambia el cableado, `arquitectura.md` |
| Un componente que salga en un explorable | El `.json` correspondiente en `diagramas/`, y vuelve a generar el HTML (ver abajo) |
| Una tabla, un `status` o un procedimiento almacenado | `modelo-datos.md` |

### Validar antes de commitear

```bash
# El YAML parsea
php -r 'require "vendor/autoload.php"; Symfony\Component\Yaml\Yaml::parseFile("docs/openapi.yaml"); echo "OK\n";'

# Validación estructural OpenAPI (requiere Node)
npx @redocly/cli lint

# Las rutas documentadas coinciden con las registradas
php artisan route:list --json
```

`redocly.yaml`, en la raíz, deja documentado qué reglas de estilo están desactivadas y por qué.

### Regenerar los explorables

Los `.html` de `diagramas/` son **generados**: no los edites a mano, el siguiente `deliver` los
pisa. La fuente de cada uno es su `.json` hermano:

| Artefacto | Fuente | Tipo |
|---|---|---|
| `diagramas/arquitectura.html` | `diagramas/arquitectura.architecture.json` | `architecture` |
| `diagramas/juego-cartas.html` | `diagramas/juego-cartas.sequence.json` | `sequence` |
| `diagramas/ciclo-chequeo.html` | `diagramas/ciclo-chequeo.lifecycle.json` | `lifecycle` |

Tras cambiar un `.json`, desde `.claude/skills/archify` y con rutas absolutas al repo:

```bash
cd .claude/skills/archify
node bin/archify.mjs validate <tipo> <ruta>/<nombre>.json --quality showcase --json
node bin/archify.mjs deliver  <tipo> <ruta>/<nombre>.json <ruta>/<nombre>.html --quality showcase --json
node bin/archify.mjs visual-check <ruta>/<nombre>.html --json
```

Un pase válido son **9 checks en verde con 0 errores y 0 warnings**. `visual-check` abre el HTML
en Chrome y deja capturas PNG más un recibo `.json` junto al artefacto; requiere Chrome y Node ≥ 18.

Tres restricciones que cuestan varios intentos si no se saben de antemano:

- **El `viewBox` no puede pasar de ~1085 px de ancho.** El visor reserva 930 px de panel a 1440×900;
  por encima de eso el texto de contexto (7 px) se proyecta por debajo del mínimo de 6 px y falla
  `composition/desktop-readability`.
- **La proporción manda sobre el alto.** Un lienzo demasiado alto desborda los 900 px del viewport
  aunque cada check de composición pase. Es más barato quitar pasos que encoger tipografía.
- En `sequence` no existen los auto-mensajes (`from` = `to`) y las etiquetas de participante deben
  caber en su caja: el nombre exacto de la clase va en el `sublabel`, no en el `label`. En
  `lifecycle`, la banda de eventos solo tiene columnas 0..2 y cada una se alinea bajo la columna
  N+2 del carril principal.
