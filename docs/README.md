# Documentación de la API de Ergosanitas

Esta carpeta contiene el contrato de la API y los diagramas del sistema. La documentación en prosa —instalación, stack, catálogo de endpoints, trampas conocidas— está en el [README de la raíz](../README.md); las convenciones para trabajar en el código, en [`CLAUDE.md`](../CLAUDE.md).

## Qué hay aquí

| Archivo | Qué es |
|---|---|
| [`openapi.yaml`](openapi.yaml) | Contrato OpenAPI 3.0.3 con las 81 operaciones: request, respuestas por código y ejemplos |
| [`index.html`](index.html) | Visor Swagger UI del contrato, listo para abrir en el navegador |
| [`arquitectura.md`](arquitectura.md) | Componentes, las tres capas y sus excepciones, inyección de dependencias y despliegue |
| [`flujos.md`](flujos.md) | 12 diagramas de secuencia, uno por flujo de negocio end-to-end |
| [`diagrama-clases.md`](diagrama-clases.md) | Clases UML por dominio: controladores, servicios, modelos y prompts |
| [`modelo-datos.md`](modelo-datos.md) | ERD de las 18 tablas, máquina de estados del chequeo, autorización por perfil y catálogo de procedimientos almacenados |

## Por dónde empezar

- **¿Qué hace este proyecto y cómo se conecta todo?** → [`arquitectura.md`](arquitectura.md)
- **¿Qué pasa cuando alguien sube un certificado?** → [`flujos.md`](flujos.md)
- **¿Dónde vive la lógica de X?** → [`diagrama-clases.md`](diagrama-clases.md)
- **¿Cómo se relacionan las tablas y qué significa `status`?** → [`modelo-datos.md`](modelo-datos.md)
- **¿Qué recibe y devuelve un endpoint concreto?** → [`openapi.yaml`](openapi.yaml)

## Ver la documentación

Los cuatro `.md` usan diagramas **Mermaid**, que se renderizan solos en GitHub y en la vista previa de VS Code. No hace falta instalar nada.

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
