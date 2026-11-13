---
name: ergosanitas-testing
description: Crea y ejecuta tests PHPUnit 11 del back-end de Ergosanitas sin tocar la MySQL remota (patrón SQLite forzado de CartFlow y ChatComercial, OpenAI::fake, filtro del CI). Úsala al escribir tests, depurar un fallo o validar un cambio antes de cerrarlo. Dentro del flujo de specs la cargan solas spec-ergo-backend, spec-impl-ergo-backend y spec-verify-ergo-backend cuando el Kit de ejecución activa sus señales (S-tests).
---

# Ergosanitas Testing

## 1. El riesgo número uno: la base de datos

- `phpunit.xml` tiene **comentadas** las líneas de SQLite en memoria, y `.env` apunta a la **MySQL remota** con datos clínicos reales. Un test que use la BD sin más **escribe en esa base**.
- No descomentes `phpunit.xml` como solución general. Copia el patrón existente: `tests/Feature/CartFlow/CartFlowTestCase.php` y `tests/Feature/ChatComercial/ChatComercialTestCase.php` fuerzan `DB_CONNECTION=sqlite` y `DB_DATABASE=:memory:` **antes** de arrancar la app, verifican con `assertSame('sqlite', config('database.default'))` y migran solo sus tablas.
- Si no puedes confirmar que el test corre sobre SQLite en memoria, no lo ejecutes.
- En SQLite **no existen** los procedimientos almacenados ni las tablas sin migración (`users_metadata`, `params`, `electro_cardiogranas`, `pago_mensual`). Lo que dependa de ellos se mockea o se crea mínimo en el test (como `CartFlowTestCase::crearAdministrador()`).

## 2. Herramientas reales

- PHPUnit 11 (`vendor/bin/phpunit`, `php artisan test`). **No hay Pest ni PHPStan**: no propongas `vendor/bin/pest` ni `phpstan analyse`.
- Pint **solo** con `vendor/bin/pint --dirty` (o rutas explícitas). Sin argumentos reformatea medio repo.
- OpenAI: `OpenAI::fake([...])`, ver `tests/Feature/ChatComercial/ChatComercialTest.php`. Nunca llames a la API real en un test.
- Unit sin Laravel: `tests/Unit/CartFlow/CalculadoraCarroServiceTest.php` crea modelos con `Model::unguarded()` porque `fill()` con `$guarded` consulta la BD.

## 3. Comandos

```bash
php artisan test --filter="CartFlow|ChatComercial"   # lo que corre el CI; siempre antes de cerrar
php artisan test --filter=NombreDelTest
vendor/bin/phpunit tests/Feature/CartFlow/AdminCartFlowTest.php
```

## 4. Qué cubrir

- **Endpoint**: éxito con el sobre exacto que espera el cliente (ver skill `ergosanitas-api`), validación inválida (422), recurso inexistente, perfil sin permiso (403 donde existe, por ejemplo `home-imagenes`), filtrado por perfil 3 (solo lo suyo) y 6 (solo derivados).
- **Negocio**: límites, nulos y vacíos, RUT con y sin dígito `K`, fechas (la app usa `APP_TIMEZONE`, por defecto `UTC`; no asumas hora de Chile), transiciones de estado e idempotencia (reintentar una subida de certificado vuelve a facturar).
- **Privacidad**: que no se filtren datos al prompt, al log ni a la respuesta (patrón de `ChatComercialTest`: enmascarado de RUT, no consulta tablas clínicas).
- **Procedimientos almacenados**: solo en una MySQL local con datos sintéticos, nunca en remoto.

## 5. Datos

Solo sintéticos: RUT inventados con formato válido, emails `@example.com`. Nunca datos reales de pacientes, ni siquiera "para reproducir un bug".

## 6. Informe

Comandos ejecutados, número de tests (aprobados, fallidos, omitidos), conexión usada y limitaciones. No modifiques un test para ocultar un defecto; separa los fallos previos de los nuevos. Nunca inventes resultados.

---

## Uso en el flujo de specs

Esta skill forma parte del **Kit de ejecución** de las specs del back (`.claude/skills/spec-ergo-backend/references/enrutado.md`).

- **Señales que la activan:** S-tests.
- **Quién la carga:** ergosanitas-developer (escribe los tests).

| Fase | Qué aporta |
|---|---|
| Diseño | Qué se prueba en SQLite y con qué `TestCase` (con `engineering:testing-strategy`). |
| Implementación | Pasos que escriben tests (vía `ergosanitas-developer`) y Fase 4b. |
| Verificación | Fase 2b y criterios de tipo «test» (Fase 4 del verificador). |

Las reglas transversales (§4 de `enrutado.md`: graphify solo AST, BD solo metadatos, sin HTTP, tests solo en SQLite, sin commits) aplican también aquí. El mapa completo de relaciones está en §8 de ese archivo.
