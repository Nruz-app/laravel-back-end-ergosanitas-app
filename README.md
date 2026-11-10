# Ergosanitas — Backend API

API REST en **Laravel 11 / PHP 8.2** que da soporte a la plataforma de salud deportiva de [Ergosanitas](https://ergosanitas.com). Gestiona chequeos cardiovasculares, electrocardiogramas, bioimpedancia, fichas clínicas, certificados médicos, incidencias deportivas, agendamiento de horas, pagos con WebPay (Transbank) y asistentes clínicos basados en OpenAI.

El proyecto es exclusivamente backend: no hay frontend propio. Todos los recursos se exponen bajo el prefijo `/api`.

---

## Tabla de contenidos

- [Enlaces y páginas de referencia](#enlaces-y-páginas-de-referencia)
- [Stack](#stack)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Variables de entorno](#variables-de-entorno)
- [Ejecución con Docker](#ejecución-con-docker)
- [Comandos habituales](#comandos-habituales)
- [Arquitectura](#arquitectura)
- [Modelo de datos](#modelo-de-datos)
- [Formatos de respuesta](#formatos-de-respuesta)
- [Ciclo de vida del chequeo y facturación](#ciclo-de-vida-del-chequeo-y-facturación)
- [Procedimientos almacenados](#procedimientos-almacenados)
- [Autenticación y perfiles](#autenticación-y-perfiles)
- [Integración con OpenAI](#integración-con-openai)
- [Generación de documentos](#generación-de-documentos)
- [Carro de servicios (cart-flow)](#carro-de-servicios-cart-flow)
- [Trampas conocidas](#trampas-conocidas)
- [Diagramas](#diagramas)
- [Contrato OpenAPI (Swagger)](#contrato-openapi-swagger)
- [Colección Postman](#colección-postman)
- [Endpoints](#endpoints)
- [Despliegue](#despliegue)
- [Entorno de desarrollo con IA (Claude Code)](#entorno-de-desarrollo-con-ia-claude-code)
- [Problemas frecuentes](#problemas-frecuentes)
- [Cómo contribuir](#cómo-contribuir)
- [Máquina nueva desde cero (checklist)](#máquina-nueva-desde-cero-checklist)
- [Historial de lo instalado](#historial-de-lo-instalado)

---

## Enlaces y páginas de referencia

**Del proyecto**

| Qué | Enlace |
|---|---|
| Repositorio del back-end (este) | <https://github.com/Nruz-app/laravel-back-end-ergosanitas-app> |
| Repositorio del front-end (SPA React que consume esta API) | <https://github.com/Nruz-app/react-front-end-ergosanitas-app> (el clon local todavía apunta al nombre anterior, `Nruz-app/react-ergosanitas`) |
| App móvil React Native 0.76 (otro cliente de esta API) | Sin remoto: `../../APP/V2/ergosanitasApp` |
| Acciones de la CI (ejecuciones del pipeline) | <https://github.com/Nruz-app/laravel-back-end-ergosanitas-app/actions> |
| Imagen Docker publicada por la CI | <https://hub.docker.com/r/nruz176/laravel-back-end-ergosanitas-app> |
| Sitio comercial | <https://ergosanitas.com> |
| API en producción (hosting compartido) | `https://ergosanitas.com/BackEnd/public/api` |
| Contrato OpenAPI | [`docs/openapi.yaml`](docs/openapi.yaml) · visor [`docs/index.html`](docs/index.html) |
| Diagramas | [`docs/README.md`](docs/README.md) |
| Colección Postman | [`docs/postman/README.md`](docs/postman/README.md) |
| Specs del back-end | [`specs/`](specs/) |
| Guía para Claude Code | [`CLAUDE.md`](CLAUDE.md) |
| Especificación legal-técnica (Ley 21.719) | `../../specs/Especificacion_Legal_Tecnica_Ergo_SaniTas.md` (fuera de este repo) |
| Fuentes de los procedimientos almacenados | `../../MySql/SP/` (fuera de git) y [`base_datos/references/sp/`](base_datos/references/sp/) |
| Agente padre de todo Ergosanitas | `../../.claude/agents/ergo-father.md` y `../../CLAUDE.md` (carpeta contenedora, no es git) |

**Cómo encaja este repo con los demás**

```
                 ┌──────────────────────────────┐
  Navegador ───► │ Front-end (Vite + React + MUI)│ ──┐
                 └──────────────────────────────┘   │  HTTP JSON  /api/*
                 ┌──────────────────────────────┐   ├──────────────►  ESTE BACK (Laravel 11)  ──►  MySQL 5.7 (tablas + SP)
  Móvil ───────► │ App React Native 0.76         │ ──┘                     │
                 └──────────────────────────────┘                         ├──►  OpenAI (chat, ECG, voz, bioimpedancia)
                                                                           ├──►  Transbank Webpay (pagos)
                                                                           ├──►  Google OAuth
                                                                           └──►  SMTP (correo)
```

El back es la única fuente de datos. Cambiar el sobre de respuesta de un endpoint rompe a los dos clientes: el front centraliza axios en `src/common/api/api.adapter.ts` y la app móvil en `src/config/api/api.adapter.ts`.

**Documentación oficial del stack**

| Pieza | Documentación |
|---|---|
| Laravel 11 | <https://laravel.com/docs/11.x> |
| PHP 8.2 | <https://www.php.net/releases/8.2/es.php> · <https://www.php.net/manual/es/> |
| Composer | <https://getcomposer.org/doc/> |
| Laravel Sanctum | <https://laravel.com/docs/11.x/sanctum> |
| jwt-auth (`php-open-source-saver/jwt-auth`) | <https://laravel-jwt-auth.readthedocs.io/> · <https://github.com/PHP-Open-Source-Saver/jwt-auth> |
| `openai-php/laravel` | <https://github.com/openai-php/laravel> · cliente <https://github.com/openai-php/client> |
| API de OpenAI | <https://platform.openai.com/docs/api-reference/chat> · modelos <https://platform.openai.com/docs/models> |
| mPDF | <https://mpdf.github.io/> |
| PHPWord | <https://phpoffice.github.io/PHPWord/> |
| Laravel Excel (`maatwebsite/excel`) | <https://docs.laravel-excel.com/3.1/> |
| Google API PHP Client | <https://github.com/googleapis/google-api-php-client> · OAuth <https://developers.google.com/identity/protocols/oauth2> |
| Transbank Webpay Plus (API REST) | <https://www.transbankdevelopers.cl/referencia/webpay> · ambiente de integración y tarjetas de prueba <https://www.transbankdevelopers.cl/documentacion/como_empezar#ambientes> |
| Correo en Laravel | <https://laravel.com/docs/11.x/mail> |
| Laravel Pint | <https://laravel.com/docs/11.x/pint> |
| PHPUnit 11 | <https://docs.phpunit.de/en/11.5/> · tests en Laravel <https://laravel.com/docs/11.x/testing> |
| MySQL 5.7 | <https://dev.mysql.com/doc/refman/5.7/en/> · procedimientos <https://dev.mysql.com/doc/refman/5.7/en/create-procedure.html> |
| OpenAPI 3.0.3 | <https://spec.openapis.org/oas/v3.0.3> |
| Redocly CLI | <https://redocly.com/docs/cli/> |
| Swagger UI | <https://swagger.io/tools/swagger-ui/> |
| Postman / Newman | <https://learning.postman.com/docs/> · <https://github.com/postmanlabs/newman> |
| Docker / Compose | <https://docs.docker.com/> · <https://docs.docker.com/compose/> |
| GitHub Actions | <https://docs.github.com/actions> · [`PaulHatch/semantic-version`](https://github.com/PaulHatch/semantic-version) · [`docker/build-push-action`](https://github.com/docker/build-push-action) |
| lftp (despliegue FTP) | <https://lftp.yar.ru/lftp-man.html> |
| Conventional Commits | <https://www.conventionalcommits.org/es/v1.0.0/> |

Las herramientas de IA (Claude Code, MCP, graphify, Laravel Boost, skills) tienen sus enlaces en [Entorno de desarrollo con IA](#entorno-de-desarrollo-con-ia-claude-code).

---

## Stack

Versiones exactas de `composer.lock` (2026-11-10):

| Componente | Paquete | Restricción en `composer.json` | Versión instalada | Enlace |
|---|---|---|---|---|
| PHP | — | `^8.2` | 8.2.12 | <https://www.php.net/> |
| Framework | `laravel/framework` | `^11.9` | 11.57.1 | <https://github.com/laravel/framework> |
| Tokens API | `laravel/sanctum` | `^4.0` | 4.0.2 | <https://github.com/laravel/sanctum> |
| JWT | `php-open-source-saver/jwt-auth` | `^2.3` | 2.3.0 | <https://github.com/PHP-Open-Source-Saver/jwt-auth> |
| Consola REPL | `laravel/tinker` | `^2.9` | 2.10.0 | <https://github.com/laravel/tinker> |
| IA | `openai-php/laravel` (+ `openai-php/client`) | `^0.10.1` | 0.10.1 | <https://github.com/openai-php/laravel> |
| PDF | `mpdf/mpdf` | `^8.2` | 8.2.4 | <https://github.com/mpdf/mpdf> |
| Word | `phpoffice/phpword` | `^1.3` | 1.3.0 | <https://github.com/PHPOffice/PHPWord> |
| Excel | `maatwebsite/excel` | `^3.1` | 3.1.64 | <https://github.com/SpartnerNL/Laravel-Excel> |
| Correo | `phpmailer/phpmailer` (declarado, **sin uso**: se usa `Mail::to()`) | `^6.9` | 6.9.1 | <https://github.com/PHPMailer/PHPMailer> |
| Google OAuth | `google/apiclient` | `^2.17` | 2.17.0 | <https://github.com/googleapis/google-api-php-client> |
| HTTP (WebPay) | `guzzlehttp/guzzle` (vía `Http::`) | dependencia de Laravel | 7.16.1 | <https://docs.guzzlephp.org/> |
| Base de datos | MySQL | — | 5.7 (sin CTE ni funciones de ventana) | <https://dev.mysql.com/doc/refman/5.7/en/> |
| Cache / colas / sesión | driver `database` por defecto; Redis opcional | — | Redis 7.2 en Docker | <https://laravel.com/docs/11.x/cache> |
| **Solo desarrollo** | | | | |
| Tests | `phpunit/phpunit` | `^11.0.1` | 11.3.6 | <https://phpunit.de/> |
| Formateo | `laravel/pint` | `^1.13` | 1.18.1 | <https://github.com/laravel/pint> |
| MCP para Claude Code | `laravel/boost` | `^2.10` | 2.10.4 | <https://github.com/laravel/boost> |
| Mocks | `mockery/mockery` | `^1.6` | 1.6.12 | <https://github.com/mockery/mockery> |
| Datos falsos | `fakerphp/faker` | `^1.23` | 1.23.1 | <https://fakerphp.org/> |
| Errores en consola | `nunomaduro/collision` | `^8.0` | 8.4.0 | <https://github.com/nunomaduro/collision> |
| Docker de Laravel (sin uso) | `laravel/sail` | `^1.26` | 1.33.0 | <https://laravel.com/docs/11.x/sail> |
| Assets (sin uso real) | `vite` 5 + `laravel-vite-plugin` (npm) | — | — | Solo existe por la plantilla de correo |

Para ver una versión: `composer show <paquete>`. Para ver los paquetes desactualizados: `composer outdated --direct`.

---

## Requisitos

| Herramienta | Versión en la PC de desarrollo (2026-11-10) | Para qué | Descarga |
|---|---|---|---|
| PHP 8.2 (vía **XAMPP**, `C:\xampp\php\php.exe`) | 8.2.12 | Ejecutar Laravel | <https://www.apachefriends.org/es/download.html> · <https://windows.php.net/download/> |
| Composer 2 | 2.8.5 | Dependencias PHP | <https://getcomposer.org/download/> |
| MySQL | 5.7 (producción); la de XAMPP sirve en local | Base de datos y procedimientos almacenados | <https://dev.mysql.com/downloads/mysql/5.7.html> |
| Git | 2.46.0 | Control de versiones | <https://git-scm.com/download/win> |
| Node.js + npm | 22.23.4 / 10.9.9 (vía nvm-windows, `C:\nvm4w\nodejs`) | Redocly, Newman, MCP de MySQL, Context7 | <https://nodejs.org/> · <https://github.com/coreybutler/nvm-windows> |
| Docker Desktop (opcional) | 29.7.2 | Entorno completo en contenedores y MCP de GitHub | <https://www.docker.com/products/docker-desktop/> |
| uv (opcional, IA) | 0.7.2 | Instalar graphify | <https://docs.astral.sh/uv/> |
| Claude Code (opcional, IA) | 2.1.322 | Agentes, skills y MCP del repo | <https://docs.claude.com/en/docs/claude-code/setup> |

Extensiones de PHP necesarias: `pdo_mysql`, `mbstring`, `zip`, `gd`, `bcmath`, `exif`, `intl`, `sockets` (en XAMPP se activan quitando el `;` de la línea `extension=` correspondiente en `C:\xampp\php\php.ini`). Comprobación: `php -m`.

---

## Instalación

```bash
git clone https://github.com/Nruz-app/laravel-back-end-ergosanitas-app.git ergosanitas-app
cd ergosanitas-app

composer install                 # incluye require-dev (Laravel Boost, PHPUnit, Pint)
php artisan package:discover     # registra los providers de paquetes (Boost lo necesita)

cp .env.example .env
php artisan key:generate         # APP_KEY
php artisan jwt:secret           # JWT_SECRET, si el .env no lo trae

# Completa en .env la base de datos y el resto de variables (ver "Variables de entorno")
php artisan migrate              # OJO: deja el esquema incompleto, ver abajo

# Carro de servicios: reglas, bloques de agenda y catálogo (idempotente)
php artisan db:seed --class=CartFlowSeeder
# o solo el catálogo, desde la copia versionada o una carpeta privada con costos
php artisan cart-flow:importar-catalogo [--dir=<carpeta>]

# Enlace simbólico storage/app/public -> public/storage
php artisan storage:link

php artisan serve
```

La API queda disponible en `http://localhost:8000/api`.

**Lo que `migrate` no crea.** Las tablas `users_metadata`, `params`, `electro_cardiogranas` y `pago_mensual`, y todos los procedimientos almacenados, existen solo en la base de datos. En una base vacía hay que crearlos a mano:

- Procedimientos: los `.sql` de `../../MySql/SP/` (fuera de git) y de `base_datos/references/sp/` (`SP_chequeos_club_prompt`, `SP_juego_cartas_club`). Ninguna de las dos fuentes garantiza estar al día: la verdad es la BD de producción. Para copiar la definición vigente: `SHOW CREATE PROCEDURE SP_xxx;`.
- Tablas sin migración: `SHOW CREATE TABLE users_metadata;` (y las demás) sobre la base real.
- `params` necesita al menos la fila `VALOR-ECG`; sin ella la subida de certificados y ECG responde 500.

Nunca copies **datos** de pacientes de producción a local (Ley 21.719): solo el esquema, con datos ficticios.

### Base de datos local paso a paso

```bash
# 1. Crear la base (MySQL de XAMPP: C:\xampp\mysql\bin\mysql.exe)
mysql -u root -e "CREATE DATABASE ergosanitas_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Esquema SIN datos (tablas, procedimientos y triggers) desde la base de origen.
#    --no-data es obligatorio: no se traen filas de pacientes.
mysqldump -h <DB_HOST> -P <DB_PORT> -u <DB_USERNAME> -p --no-data --routines --triggers <DB_DATABASE> > esquema.sql
mysql -u root ergosanitas_local < esquema.sql

# 3. Alternativa sin acceso al origen: migraciones + SP desde los .sql
php artisan migrate
for f in ../../MySql/SP/*.sql ../../MySql/SP/*.SQL base_datos/references/sp/*.sql; do mysql -u root ergosanitas_local < "$f"; done
#    (y crear a mano users_metadata, params, electro_cardiogranas y pago_mensual)

# 4. Datos mínimos de negocio, todos ficticios
#    - perfiles (1 Administrador, 2, 3 club, 6 médico…) y un usuario por perfil en users + users_metadata
#    - params: la fila VALOR-ECG
php artisan db:seed --class=CartFlowSeeder
```

`esquema.sql` es un archivo de trabajo: no lo subas a git. Con la base local lista, apunta los `DB_*` del `.env` a `127.0.0.1` / `ergosanitas_local`.

---

## Variables de entorno

Se parte de `cp .env.example .env`. **Los valores no están en ningún documento ni en git**: pídelos o cópialos del `.env` de otra PC por un canal privado. Hay `.env` con credenciales reales en esta carpeta y en `../.env`; nunca los copies a documentación, artefactos ni commits.

### Variables estándar de Laravel (las de `.env.example`)

| Grupo | Variables | Notas para este proyecto |
|---|---|---|
| App | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_TIMEZONE`, `APP_URL`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`, `APP_MAINTENANCE_DRIVER` | `APP_KEY` sale de `php artisan key:generate`. `APP_ENV=local` cambia el valor por defecto de `CART_FLOW_FRONT_URL`. En producción, `APP_DEBUG=false`. |
| Hash | `BCRYPT_ROUNDS` | — |
| Logs | `LOG_CHANNEL`, `LOG_STACK`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_LEVEL` | Los logs quedan en `storage/logs/laravel.log`. |
| Base de datos | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | En `.env.example` vienen comentadas, salvo `DB_CONNECTION`. Tiene que ser `mysql`: los procedimientos almacenados no existen en SQLite. |
| Sesión | `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN` | `database` por defecto (tabla `sessions`). |
| Colas, broadcast y disco | `QUEUE_CONNECTION`, `BROADCAST_CONNECTION`, `FILESYSTEM_DISK` | Colas en `database`; hoy no se encola nada. |
| Cache | `CACHE_STORE`, `CACHE_PREFIX`, `MEMCACHED_HOST` | `CACHE_STORE=database` necesita las tablas `cache` y `cache_locks`. Las usan el chat comercial (contexto y limitador de tasa). |
| Redis | `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` | Opcional (Docker local, puerto 7379). |
| Correo | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | En local conviene `MAIL_MAILER=log`, para que los correos queden en el log y no salgan. |
| AWS | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT` | Sin uso. |
| Vite | `VITE_APP_NAME` | Sin uso real. |

### Variables propias del proyecto

| Variable | Descripción |
|---|---|
| `DB_CONNECTION` | `mysql` en todos los entornos (los procedimientos almacenados son específicos de MySQL) |
| `OPENAI_API_KEY` | Clave de la API de OpenAI |
| `OPENAI_ORGANIZATION` | Organización de OpenAI (opcional) |
| `OPENAI_REQUEST_TIMEOUT` | Timeout en segundos de las llamadas a OpenAI (por defecto 120) |
| `WEBPAY_URL` | Endpoint de Transbank WebPay |
| `WEBPAY_RETURN` | URL de retorno tras el pago |
| `WEBPAY_ID` | Código de comercio |
| `WEBPAY_SECRET` | API key de Transbank |
| `GOOGLE_CLIENT_ID` | Client ID de Google OAuth |
| `GOOGLE_CLIENT_SECRET` | Client secret de Google OAuth |
| `JWT_SECRET` | Secreto para firmar los JWT |
| `JWT_ALGO` | Algoritmo de firma JWT |
| `API_PATH_CER` | URL pública base de la carpeta de certificados (ej. `https://ergosanitas.com/BackEnd/public/Certificado`) |
| `API_PATH_LOGO` | URL pública base de la carpeta de logos de clubes. **No está en `.env.example`**, pero el código la usa: agrégala a mano. |
| `API_PATH_HOME` | URL pública base de `public/HomeImagenes`, sin barra final (ej. `http://127.0.0.1:8000/HomeImagenes`). Arma el `src` de las imágenes del Home |
| `WEBPAY_CART_FLOW_RETURN` | **Opcional.** Retorno de Webpay del carro. Vacía → `route('CartFlowWebPayRetorno')` del mismo host (`…/api/cart-flow/webpay/retorno`) |
| `CART_FLOW_FRONT_URL` | **Opcional.** A dónde vuelve el cliente tras pagar (`{url}/servicios/resultado`). Vacía → `http://localhost:5173` con `APP_ENV=local`, si no `https://ergosanitas.com` |

> `API_PATH_CER` y `API_PATH_LOGO` se leen con `env()` directamente desde los servicios. Si se cachea la configuración con `php artisan config:cache`, estas llamadas devolverán `null`. Evita `config:cache` en este proyecto o migra esos valores a un archivo de configuración.
>
> `API_PATH_HOME` es la excepción: se lee con `config('services.home_imagenes.url')` (definida en `config/services.php`, que le quita la `/` final), así que sobrevive a un `config:cache`. Si falta, `src` queda como `/nombre-del-archivo`.
>
> Las demás variables leídas con `env()` fuera de `config/` son `WEBPAY_URL|ID|SECRET|RETURN` (`WebPayController`) y `GOOGLE_CLIENT_ID|SECRET` (`GoogleAuthControlle`). Por eso: **nunca `php artisan config:cache`**.

### Dónde se consigue cada credencial

| Variable | Dónde se obtiene |
|---|---|
| `OPENAI_API_KEY` | <https://platform.openai.com/api-keys> (cuenta de la organización) |
| `WEBPAY_ID` / `WEBPAY_SECRET` | Integración: los códigos públicos de prueba de <https://www.transbankdevelopers.cl/documentacion/como_empezar#codigos-de-comercio>. Producción: el portal de Transbank del comercio. |
| `WEBPAY_URL` | Integración: `https://webpay3gint.transbank.cl`. Producción: `https://webpay3g.transbank.cl`. |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Google Cloud Console → *APIs y servicios → Credenciales* (<https://console.cloud.google.com/apis/credentials>) |
| `JWT_SECRET` | `php artisan jwt:secret` |
| `APP_KEY` | `php artisan key:generate` |
| `DB_*` | Local: tu MySQL (XAMPP o el contenedor `db_mysql`). Producción: el panel del hosting. |
| `MAIL_*` | La cuenta SMTP del dominio (panel del hosting) |

---

## Ejecución con Docker

El `docker-compose.yml` de la raíz levanta el entorno de desarrollo completo, construyendo la imagen desde el `dockerfile` local:

```bash
docker compose up -d --build
```

| Servicio | Puerto host | Descripción |
|---|---|---|
| `laravel_webserver` | `6162` | Nginx sirviendo `public/` |
| `laravel_app` | — | PHP-FPM 8.2 |
| `db_mysql` | `3337` | MySQL 5.7.22 |
| `php_myadmin` | `8383` | phpMyAdmin |
| `redis` | `7379` | Redis 7.2 |

La aplicación queda en `http://localhost:6162/api`.

La configuración de esos servicios vive en `docker-compose/` (`nginx/`, `php/`, `mysql/`), junto al `dockerfile` de la raíz. Es solo para desarrollo local. Dentro de Docker, el `.env` debe apuntar a `DB_HOST=db_mysql` y `DB_PORT=3306` (el contenedor crea la base `laravel_db`), y a `REDIS_HOST=redis`.

```bash
docker compose up -d --build            # levantar (y reconstruir la imagen)
docker compose ps                       # estado de los servicios
docker compose logs -f laravel_app      # logs de PHP-FPM
docker compose exec laravel_app bash    # shell dentro del contenedor
docker compose exec laravel_app php artisan migrate
docker compose exec laravel_app php artisan test --filter="CartFlow|ChatComercial"
docker compose down                     # parar (los volúmenes se conservan)
```

phpMyAdmin queda en <http://localhost:8383>, con servidor `db_mysql`.

El directorio `dockerHub/` contiene el compose pensado para **producción en contenedor** (hoy la producción real corre en el hosting compartido, ver [Despliegue](#despliegue)), que en lugar de construir la imagen consume `nruz176/laravel-back-end-ergosanitas-app:latest` desde Docker Hub y arranca mediante `dockerHub/entrypoint.sh` (limpia cachés, ejecuta `storage:link` y lanza php-fpm).

---

## Comandos habituales

```bash
# Servidor de desarrollo
php artisan serve

# Tests
php artisan test
php artisan test --testsuite=Feature
php artisan test --filter=NombreDelTest
vendor/bin/phpunit tests/Feature/ExampleTest.php

# Formateo de código (solo lo que tocaste: sin argumentos reformatea medio repo)
vendor/bin/pint --dirty
vendor/bin/pint --dirty --test     # solo verifica

# Migraciones
php artisan migrate
php artisan migrate:status
php artisan migrate:rollback

# Limpiar cachés tras tocar config/, rutas o providers
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Listar rutas registradas
php artisan route:list
php artisan route:list --path=cart-flow        # filtrar por prefijo
php artisan route:list --json                  # para contrastar con docs/openapi.yaml
```

Más comandos que se usan en este repo:

```bash
# Dependencias
composer install                               # todas (incluye dev)
composer install --no-dev --optimize-autoloader  # como en producción y en la imagen Docker
composer dump-autoload -o                      # regenerar el classmap (detecta clases duplicadas en app/PromptSystem)
composer validate                              # lo corre la CI
composer show laravel/framework                # versión instalada de un paquete

# Comandos propios
php artisan cart-flow:importar-catalogo        # upsert del catálogo desde database/data/cart-flow/*.csv
php artisan cart-flow:importar-catalogo --dir=<carpeta privada con costos>
php artisan db:seed --class=CartFlowSeeder     # reglas, bloques de agenda y catálogo (idempotente)

# Migraciones: siempre aditivas, primero en local o staging
php artisan make:migration create_x_table
php artisan migrate --path=database/migrations/<archivo>.php   # aplicar solo una
php artisan migrate --pretend                  # ver el SQL sin ejecutarlo

# Crear piezas nuevas (cada servicio necesita su provider en bootstrap/providers.php)
php artisan make:controller XController
php artisan make:model X
php artisan make:provider XServiceProvider
php artisan make:test X/XTest                  # Feature
php artisan make:test X/XTest --unit

# Enlace de storage y utilidades
php artisan storage:link
php artisan about                              # versión de Laravel, drivers y entorno
php artisan optimize:clear                     # limpia todas las cachés de una vez

# Tests con cobertura (requiere Xdebug o PCOV)
php artisan test --coverage --filter=CartFlow

# Contrato, Postman y diagramas (ver sus secciones)
npx @redocly/cli lint docs/openapi.yaml
php docs/postman/generar-coleccion.php
php docs/postman/verificar-cobertura.php

# Grafo del código tras un cambio
graphify update .
```

⚠️ No uses `php artisan config:cache` (rompe certificados, logos, WebPay y el login de Google) ni `vendor/bin/pint` sin argumentos. `php artisan tinker` y cualquier comando que escriba usan la base de `.env`: si apunta a producción, **no los corras**.

### Sobre los tests

Los tests reales son los del carro (`tests/Feature/CartFlow/`, `tests/Unit/CartFlow/`) y del chat comercial (`tests/Feature/ChatComercial/`): `php artisan test --filter="CartFlow|ChatComercial"`, lo mismo que corre el CI. El resto son los stubs de Laravel.

En `phpunit.xml` las líneas de SQLite en memoria están comentadas, así que un test que toque la base sin más usará la conexión de `.env` (la MySQL remota). Para un dominio nuevo, copia el patrón de `CartFlowTestCase` / `ChatComercialTestCase`: fuerzan SQLite en memoria antes de arrancar la app y migran solo sus tablas. Los procedimientos almacenados y las tablas sin migración no existen en SQLite, así que lo que dependa de ellos debe mockearse.

### Servidores MCP para Claude Code (opcional, solo desarrollo)

Cada desarrollador los registra en su máquina, en ámbito local (no hay `.mcp.json` en el repo). Los comandos completos, el uso permitido y los detalles de Windows están en [Servidores MCP](#servidores-mcp).

---

## Arquitectura

### Flujo Controller → Service → Model

Cada dominio funcional sigue el mismo patrón de tres capas:

```
routes/api.php
      │
      ▼
app/Http/Controllers/XController.php     Valida el Request, arma la respuesta JSON,
      │                                   captura excepciones ({success, message, error})
      ▼
app/Services/XService.php                Lógica de negocio, Query Builder, documentos
      │
      ▼
app/Models/X.php                         Eloquent + wrappers de procedimientos almacenados
```

Los servicios se inyectan por constructor en los controladores.

### Registro de servicios

**Cada servicio requiere su propio ServiceProvider registrado manualmente en `bootstrap/providers.php`.** No hay auto-discovery para ellos. Para añadir un dominio nuevo:

1. Crear `app/Services/XService.php`
2. Crear `app/Providers/XServiceProvider.php` registrando un `singleton()`
3. Añadir la clase a `bootstrap/providers.php`

```php
// app/Providers/XServiceProvider.php
public function register(): void
{
    $this->app->singleton(XService::class, fn ($app) => new XService());
}
```

Los nombres de provider son históricamente inconsistentes (`CertificadoProvider` frente a `BioimpedanciaServiceProvider`); al modificar un dominio existente conviene seguir el nombre que ya tiene.

`CertificadoProvider` es el único que inyecta una dependencia entre servicios: `CertificadoService` recibe `EstadisticasService`.

> **La regla tiene una excepción real en el código: `ChequeoCardiovascularWordService` no tiene provider** y no está en `bootstrap/providers.php`. Laravel lo resuelve igualmente por autowiring —su constructor solo pide `ChequeoCardiovascularPDFService`—, pero **deja de ser singleton**: se construye una instancia nueva en cada request. Es el ejemplo de qué pasa si te saltas el paso 2; no lo tomes como el patrón a seguir.

### Convenciones de código

- El código, los comentarios y los mensajes de error están en **español**.
- Los métodos de controlador usan `PascalCase` (`FindByEmail`, `ChequeoPDFRut`, `EstadisticaIMC`), a diferencia de la convención Laravel por defecto.
- No hay un formato de respuesta único: conviven siete formas incompatibles, a veces dentro del mismo controlador. Ver [Formatos de respuesta](#formatos-de-respuesta).

### Sin middleware ni manejador de excepciones

`bootstrap/app.php` deja vacíos los closures de middleware y de excepciones, y **no existe `app/Http/Middleware/`**. No hay un handler global que convierta las excepciones en respuestas JSON: por eso cada acción envuelve su cuerpo en `try/catch` y arma el sobre a mano. Al escribir un endpoint nuevo hay que hacer lo mismo, o la excepción saldrá como el error HTML de Laravel.

Los únicos middleware de ruta son de Laravel y se declaran en la propia ruta: `auth:sanctum` en `GET /user`, `signed` en la descarga de órdenes médicas del carro y `throttle:chat-comercial` en `POST chat-comercial/as-question`, el **único limitador** del repo (20 por minuto y 200 por día por IP, definido en `ChatComercialServiceProvider::boot()`). El limitador corta el costo; no autentica.

### Directorios propios

| Ruta | Contenido |
|---|---|
| `app/PromptSystem/` | Clases estáticas con los prompts de sistema de OpenAI |
| `app/PromptSystem/Helpers/` | Utilidades de IA (`PatientHelper::extractPatient()`) |
| `app/Services/` | Capa de lógica de negocio |
| `app/Imports/` | Importadores de Excel (`ChequeoImport`) |
| `app/Mail/` | Mailables (`EmailMailable`) |
| `docker-compose/nginx/` | Configuración de Nginx para los contenedores |
| `dockerHub/` | Compose y entrypoint del despliegue en producción |
| `public/Certificado`, `public/Logo`, `public/Electrocardiograma`, `public/Bioimpedancia`, `public/HomeImagenes` | Archivos subidos por los usuarios |
| `resources/home-imagenes-semilla/` | Las 24 imágenes iniciales del Home, versionadas; desde aquí se siembra `public/HomeImagenes` |

### Estructura del repositorio

```
ergosanitas-app/
├── app/
│   ├── Console/Commands/          CartFlowImportarCatalogo (cart-flow:importar-catalogo)
│   ├── Http/Controllers/          un controlador por dominio; Auth/ y CartFlow/ agrupados
│   │   └── bootstrap/             ⚠️ copia muerta del bootstrap real: nunca se carga
│   ├── Imports/                   ChequeoImport (carga masiva Excel)
│   ├── Mail/                      EmailMailable
│   ├── Models/                    Eloquent + wrappers estáticos de los SP; CartFlow/ aparte
│   ├── PromptSystem/              prompts de OpenAI (una clase por archivo) + Helpers/
│   ├── Providers/                 un ServiceProvider por servicio (registro manual)
│   └── Services/                  lógica de negocio; CartFlow/ aparte
├── bootstrap/
│   ├── app.php                    sin middleware ni handler de excepciones
│   └── providers.php              ⚠️ aquí se registra cada provider nuevo
├── config/                        incluye boost.php, jwt.php, openai.php y services.php (home_imagenes, cart_flow, chat_comercial)
├── database/
│   ├── migrations/                28 migraciones (faltan users_metadata, params, electro_cardiogranas, pago_mensual)
│   ├── seeders/                   DatabaseSeeder, CartFlowSeeder
│   └── data/cart-flow/            catálogo CSV versionado, sin costos
├── base_datos/references/sp/      SP_chequeos_club_prompt.sql, SP_juego_cartas_club.sql
├── references/html/               material de referencia del juego de cartas
├── resources/                     plantilla de correo y home-imagenes-semilla/
├── routes/
│   ├── api.php                    ⚠️ TODAS las rutas (prefijo /api), planas
│   └── console.php
├── public/                        index.php, logos, firmas y watermark de los PDF + carpetas de subidas
├── storage/                       logs, caché y orden médica privada del carro (app/private/cart-flow/ordenes)
├── tests/                         Feature/ y Unit/ (tests reales: CartFlow y ChatComercial)
├── docs/                          openapi.yaml, index.html (Swagger), 4 Mermaid, diagramas/ (archify), postman/
├── specs/                         01…06 (spec-driven)
├── docker-compose.yml             entorno local (+ docker-compose/{nginx,php,mysql})
├── dockerfile, .dockerignore      imagen publicada en Docker Hub
├── dockerHub/                     compose + entrypoint del despliegue en contenedor
├── .github/workflows/             github-action.build.yml (test, build, deploy)
├── redocly.yaml                   reglas de lint del contrato
├── api.php                        ⚠️ copia obsoleta de routes/api.php, sin uso
├── src/                           vacío
└── .claude/, .agents/, skills-lock.json, .graphifyignore, CLAUDE.md   entorno de IA (ver su sección)
```

---

## Modelo de datos

Tablas principales del dominio:

| Tabla | Modelo | Descripción |
|---|---|---|
| `users` | `User` | Usuarios de Laravel (password hasheada) |
| `users_metadata` | `UsersMetadata` | Datos de negocio del usuario: perfil, logo, RUT, `ergo_pass` |
| `perfiles` | `Perfiles` | Catálogo de perfiles / roles |
| `chequeo_cardiovascular` | `ChequeoCardiovascular` | Chequeos cardiovasculares (entidad central) |
| `electro_cardiogranas` | `ElectroCardiograma` | Informes de ECG asociados por RUT y por `id_chequeo` |
| `bioimpedancia` | `Bioimpedancia` | Mediciones de bioimpedancia |
| `certificado_url` | `CertificadoURl` | Certificados médicos en PDF y su URL pública |
| `incidentes_deportivos` | `IncidentesDeportivos` | Lesiones e incidencias deportivas |
| `agenda_horas` | `AgendaHoras` | Reservas de hora |
| `servicios` | `Servicios` | Catálogo de servicios ofrecidos |
| `web_pay_info` | `WebPayInfo` | Transacciones de WebPay |
| `pago_mensual` | `PagoMensual` | Facturación mensual por club (`club`, `periodo`, monto) |
| `params` | `Params` | Parámetros de negocio clave/valor (hoy solo `VALOR-ECG`) |
| `chat_sessions` | `ChatSessions` | Sesiones del asistente clínico (paciente activo por `session_id`) |
| `chat_history` | `ChatHistory` | Historial de mensajes por paciente |
| `chat_club_sessions` | `ChatClubSessions` | Sesiones del asistente por club (filtro activo por `session_id`) |
| `chat_club_history` | `ChatClubHistory` | Historial de mensajes por sesión de club |
| `chat_comercial_sessions` | `ChatComercialSessions` | Sesiones del chat comercial «Ergosanito» del Home: visitante anónimo por `session_id` (UUID del front), sin IP ni datos personales |
| `chat_comercial_history` | `ChatComercialHistory` | Historial del chat comercial por sesión: mensaje del visitante ya enmascarado, `derivar`, `contexto_hash` y tokens |
| `logs_api` | `LogsApi` | Errores de WebPay (es el único dominio que persiste errores) |
| `home_imagenes` | `HomeImagen` | Imágenes de promociones, alianzas y galería del Home comercial (solo el nombre del archivo; la URL se arma al responder). La migración siembra 24 filas |
| `cf_zonas`, `cf_comunas` | `CartFlow\Zona`, `CartFlow\Comuna` | Carro: 3 zonas de domicilio con sus cargos y 50 comunas (sin zona = fuera de RM, a cotizar) |
| `cf_productos`, `cf_producto_componentes` | `CartFlow\Producto`, `CartFlow\ProductoComponente` | Catálogo del carro: 245 productos por SKU y componentes de perfiles/packs. `costo_domilab` y `nota_interna` nunca salen por la API |
| `cf_reglas`, `cf_bloques_agenda`, `cf_operativo_tramos` | `CartFlow\Regla`, `CartFlow\BloqueAgenda`, `CartFlow\OperativoTramo` | Reglas de negocio en JSON, bloques horarios con cupo y precios de operativos por tramo |
| `cf_carritos`, `cf_carrito_pacientes`, `cf_carrito_lineas` | `CartFlow\Carrito`, `CarritoPaciente`, `CarritoLinea` | Carro anónimo por `uuid`; pacientes y líneas con **soft delete** |
| `cf_pedidos`, `cf_pedido_pacientes`, `cf_pedido_lineas`, `cf_pedido_ordenes_medicas` | `CartFlow\Pedido`, … | Pedido: copia inmutable del carro. Se anula, nunca se borra. Las órdenes médicas van al disco privado |
| `cf_pagos`, `cf_pedido_historial` | `CartFlow\Pago`, `CartFlow\PedidoHistorial` | Intentos de pago (Webpay, transferencia, efectivo) y cada cambio de estado del pedido con quién y por qué |
| `cf_cotizaciones`, `cf_visibilidad_log` | `CartFlow\Cotizacion`, `CartFlow\VisibilidadLog` | Solicitudes de cotización y registro de quién mostró u ocultó un producto |

Tres advertencias sobre este esquema:

- **`users_metadata`, `params`, `electro_cardiogranas` y `pago_mensual` no tienen migración** en `database/migrations/`. Existen solo en la base de datos, igual que casi todos los procedimientos almacenados. Una base recién migrada quedará incompleta.
- El nombre de la tabla de electrocardiogramas es `electro_cardiogranas` (con "n"). Es un typo consolidado en producción; respétalo en las queries.
- `App\Models\FichaClinica` y `App\Models\ChequeoClubPrompt` tienen el `$table` comentado **a propósito**: no mapean ninguna tabla, son solo envoltorios de `SP_ficha_clinica` y `SP_chequeos_club_prompt`.

### El RUT como clave de negocio

El identificador que cruza todos los dominios no es un id numérico, sino el **RUT del paciente**: `chequeo_cardiovascular.rut`, `certificado_url.rut_paciente`, `bioimpedancia.rut`, `electro_cardiogranas.rut_paciente`, `agenda_horas.rut_paciente` y el parámetro de `SP_ficha_clinica`.

- Formato esperado: `12345678-9` — 7 u 8 dígitos, guion y dígito verificador (`0-9`, `k` o `K`), sin puntos.
- `App\Imports\ChequeoImport` lo normaliza con `preg_replace` y lo valida contra `/^\d{7,8}-[0-9kK]$/`, **sin verificar el dígito verificador**. Las filas del Excel con RUT inválido se descartan en silencio: el importador acumula el conteo en `getCantInser()` y el último error en `getErrorMsg()`.
- `BioimpedanciaController` normaliza aparte: quita los puntos y pasa la `K` a minúscula antes de guardar.
- Varias tablas se relacionan por el par `(rut_paciente, id_chequeo)` **sin foreign key**. Cambiar el RUT de un chequeo sin propagarlo deja los certificados y los ECG huérfanos; por eso el perfil 1 dispara `CertificadoService::UpdateRutCertificado()` y `ElectroCardiogramaService::UpdateRutECG()` al editar.

### Configuración de negocio en la tabla `params`

Los valores de negocio —no los de infraestructura— viven en la tabla `params` y se leen así:

```php
$valor = Params::where('descripcion', 'VALOR-ECG')->firstOrFail()->valor;
```

Hoy solo se usa `VALOR-ECG`, en `CertificadoService`, `CertificadoUrlController` y `ElectroCardiogramaController`. Si un valor tarifario parece hardcodeado y ausente, búscalo aquí antes que en `.env`. Al ser `firstOrFail()`, **una fila faltante en `params` tumba el endpoint con un 500**.

---

## Formatos de respuesta

No hay un sobre canónico. Cada controlador usa el suyo y **cambiarlo rompe a los clientes que ya lo consumen**. Al tocar un endpoint, respeta el formato que ya devuelve.

Son **siete formas distintas**, no dos. La mayoría de los controladores mezcla varias según el método y según si la respuesta es de éxito o del `catch`:

| Formato | Forma | Dónde aparece |
|---|---|---|
| **A** — `success` | `{"success": true, "message": "...", "data": {...}}` | `Auth/UserController` (usa `user` en vez de `data` en el login), `Auth/GoogleAuthControlle`, `BioimpedanciaController`, `FichaClinicaController`, `FileUploadController`, `JuegoCartasController`, `HomeImagenController` (con 201 en el alta y 403/404/422 propios), `CertificadoUrlController::FileUploadCer` y `::CargaMasivaEcg`, `OpenAIController::AsistenteVoz` y `::AnalisisEcg` |
| **B** — `response` anidado | `{"response": {"status": "OK", "mensaje": "..."}}` | `EmailController`, `ElectroCardiogramaController::Save`, `ChequeoCardiovascularController::Store`; en `AgendaHorasController`, `EstadisticasController`, `GoogleAuthControlle` y `ChequeoCardiovascularController::Update` **solo en el `catch`** |
| **C** — `status` plano | `{"status": ..., "mensaje": "..."}` — `status` a veces es texto (`'OK'`, `'Bad Request'`) y a veces un entero (`200`, `404`, `500`) | `ChequeoCardiovascularController` (`deleteById`, `FilterCalendar`, `LikeChequeo*`, `SearchChequeo`, `Update` en el camino feliz), `CertificadoUrlController` (`ValidarRut`, `PathUrlCertificado`, `ValidaCertificado`, `showCertificado`), `EstadisticasController::deletePagoMensual`, `CargaMasivaController` |
| **D** — `status: 'success'` | `{"status": "success", "message": "...", "data": [...]}` | `IncidenciasController`, los 12 métodos. **Devuelve 201 también en los GET** |
| **E** — payload crudo | el array, la colección o el modelo serializado, sin sobre | `ChequeoCardiovascularController` (`Index`, `FindByEmail`, `ChequeoRut`, `ChequeoUserEmail`, `EstadoGeneral`), `ServiciosController`, `AgendaHorasController::getAgenda`, `Auth/UserController::ListUserEmail`, `ElectroCardiogramaController::FindByRut`, todos los éxitos de `EstadisticasController`, `WebPayController::WebPayRequest` |
| **F** — sobre de chat | `{"sessionId", "patient"\|"club", "search"?, "response", "status"?}`; el chat comercial usa `{"sessionId", "response", "derivar"}` (sin `patient`, `club` ni `search`) | `OpenAIController::AsQuestionUseCase` y `::resetPatient`, `ClubAssistantController`, `ChatComercialController` (sus errores: 422 `{message, errors}`, 429 `{message}` y 500 `{message}` genérico, sin `file` ni `line`) |
| **G** — no JSON | binario o redirección | los PDF (`application/pdf`), el Word (`streamDownload`), `WebPayResponse` (`redirect()->away`), los `health` (texto plano) |

`OpenAIController` y `GoogleAuthControlle` mezclan varios formatos dentro del mismo archivo. **Al tocar un endpoint, mira qué devuelve hoy** — no asumas el sobre por el controlador.

### Códigos HTTP que no siguen la convención

Conviene conocerlos antes de integrar un cliente:

- Los tres endpoints de consulta de certificados (`certificado/validar/{rut}`, `certificado/path-url`, `certificado/valida-certificado`) responden **siempre HTTP 200**; el resultado real va en un campo `status` dentro del cuerpo.
- `IncidenciasController` devuelve **201 Created también en las lecturas** (GET). Y como evalúa el resultado del servicio con un `if` simple, un conteo de `0` o una lista vacía se traducen en **500 "Error al listar las incidencias"** aunque no haya fallado nada.
- `POST /api/electro-cardiograma/find-by-rut` no propaga el error: si la consulta falla, el `catch` devuelve 200 con un objeto de placeholders (`estado_paciente: "N/A"`, `frecuencia_cardiaca_paciente: 0`).
- `WebPayController` **no devuelve respuesta cuando falla**: graba en `logs_api` y termina con cuerpo vacío.
- Los errores de cliente suelen salir como **500** con el mensaje crudo de la excepción (`$e->getMessage()`), y los controladores de IA añaden `file` y `line`. No expongas esas respuestas al usuario final sin filtrarlas.

---

## Ciclo de vida del chequeo y facturación

### Estados

`chequeo_cardiovascular.status` es texto libre y avanza así:

```
ingresado  ->  Testiado  ->  ECG FOTO  ->  REVISION MEDICA
```

Son **seis los puntos del código PHP que escriben el `status`**:

| Estado | Quién lo escribe | Condición |
|---|---|---|
| `ingresado` | Default de la columna | Alta desde un perfil distinto de 2, y toda la carga masiva (`ChequeoImport` no escribe `status`) |
| `Testiado` | `ChequeoCardiovascularController::Store()` | `perfilId == 2` (tester en terreno); fija además `fecha_atencion` |
| `Testiado` | `ChequeoCardiovascularController::Update()` | `perfilId == 2` |
| *cualquier valor* | `ChequeoCardiovascularController::Update()` | `perfilId == 1`: el administrador escribe el `status` que venga en el request |
| `ECG FOTO` | `CertificadoService::subirCertificado()` | Al subir el certificado |
| `ECG FOTO` | `CertificadoUrlController::FileUploadCer()` | Duplica a mano la lógica del servicio |
| `REVISION MEDICA` | `ElectroCardiogramaController::Save()` | Al guardar la lectura del cardiólogo |

Ninguna transición valida el estado previo: subir un certificado sobre un chequeo en `ingresado` lo salta directo a `ECG FOTO`. El resto de cambios puede venir de `UPDATE` del cliente o de procedimientos almacenados. Ver la [máquina de estados](docs/modelo-datos.md#máquina-de-estados-del-chequeo).

En los listados el `status` se transforma en un campo derivado `estado_paciente`:

- `REVISION MEDICA` con ECG cargado → `Diag. Card. - Normal` / `Diag. Card. - Alterado`
- `REVISION MEDICA` sin ECG → `En Rev. Cardio`
- cualquier otro caso → el `status` tal cual

Para el perfil 3 la búsqueda se ordena con `FIELD(cc.status, 'ECG FOTO', 'REVISION MEDICA', 'Testiado', 'ingresado')`.

### Subir un documento factura el mes del club

Es el efecto cruzado más fácil de pasar por alto del proyecto. `CertificadoService::subirCertificado()` y `ElectroCardiogramaController::Save()` hacen, además de guardar:

1. Borran el registro previo del par `(rut, id_chequeo)` — solo en el caso del certificado.
2. Marcan el chequeo (`ECG FOTO` o `REVISION MEDICA`).
3. Leen `params.VALOR-ECG`.
4. Llaman a `EstadisticasService::PagoMensual($periodo, $club, $valor_ecg, "ADD")` → `SP_pago_mensual`.

Tres consecuencias prácticas:

- **No hay idempotencia: cada subida vuelve a facturar.** Reintentar una carga fallida, o corregir la lectura de un ECG, suma otro cargo al mes del club.
- `POST /api/carga-masiva-ecg` aplica lo mismo **por archivo**: un lote de 200 PDF genera 200 cargos.
- Si falta la fila `VALOR-ECG` en `params`, el `firstOrFail()` responde 500 **después** de haber movido el archivo, guardado la fila y cambiado el estado del chequeo.

Los endpoints que escriben facturación directamente son `POST /api/estadisticas/pago-mensual` y `POST /api/estadisticas/update-pago-mensual`; `POST /api/estadisticas/delete-pago-mensual` la **borra físicamente**, sin confirmación y sin control de acceso.

---

## Procedimientos almacenados

Una parte importante de las estadísticas se resuelve en MySQL, no en PHP. Los modelos exponen métodos estáticos que envuelven `DB::select('CALL SP_xxx(?)')`:

| Modelo | Procedimientos |
|---|---|
| `ChequeoCardiovascular` | `SP_estadistica_IMC`, `SP_estado_general`, `sp_estadistica_presion`, `SP_estadistica_hemoglucotest`, `SP_estadistica_monto`, `SP_pago_mensual` |
| `IncidentesDeportivos` | `SP_estadistica_liga`, `SP_estadistica_categoria`, `SP_estadistica_lesiones`, `SP_estadistica_parte_cuerpo`, `SP_estadistica_lesiones_fechas` |
| `PagoMensual` | `SP_agenda_mensual`, `SP_estadistica_monto_mdc`, `SP_update_pago_mensual`, `SP_chequeos_prompt` |
| `FichaClinica` | `SP_ficha_clinica` (devuelve una columna `resultado_json` que el servicio decodifica) |
| `ChequeoClubPrompt` | `SP_chequeos_club_prompt` |
| `Bioimpedancia` | `SP_bioimpedacia_rut` |

Todos devuelven una única columna —`resultado_json` o `resultado`— que el servicio decodifica antes de responder. Como la forma del JSON la define el procedimiento y no el código PHP, **el contrato de los endpoints de estadística depende del SP instalado en la base**.

**Salvo una excepción, estos procedimientos no están versionados en el repositorio ni en las migraciones.** Viven únicamente en la base de datos: al cambiar su firma o su lógica hay que actualizarlos directamente en MySQL, y una base recién migrada no los tendrá.

La excepción es `base_datos/references/sp/SP_chequeos_club_prompt.sql`, que sí está en el repo como referencia. Es el camino a seguir para los SP nuevos.

### Una cosa que conviene verificar contra la base

- `SP_chequeos_club_prompt` arma la presión como `CONCAT(presionArterial, '/', presion_sistolica)`, y la columna `presionArterial` guarda la **diastólica**: el JSON llega como `"70/130"`, al revés de la convención médica. `ClubAssistantService::normalizarPresion()` invierte los componentes antes de enviarlos al modelo para que no lea una presión normal como una crisis hipertensiva. Si escribes otro consumidor de ese SP, aplica la misma corrección.

---

## Autenticación y perfiles

En el proyecto conviven tres mecanismos de autenticación:

1. **Sesión nativa** — `Auth::attempt()` en `UserController::AuthRegister`. Es el login principal en uso.
2. **Sanctum** — solo protege la ruta de ejemplo `GET /api/user`.
3. **JWT** — `php-open-source-saver/jwt-auth` está configurado como guard `api` en `config/auth.php`.

> **Las rutas de negocio en `routes/api.php` no llevan middleware de autenticación.** El control de acceso se hace dentro de los controladores a partir del `user_email` recibido en la petición.

### Doble tabla de usuarios

Los usuarios se reparten entre dos tablas que deben mantenerse sincronizadas:

- `users` — tabla estándar de Laravel, con la contraseña hasheada mediante `bcrypt`.
- `users_metadata` — datos de negocio: `perfiles_id`, `user_name`, `user_email`, `user_logo`, `rut_paciente`, `ergo_pass`, `status`.

`UserMetadataService::userSave()` y `UserMetadataService::UserUpdatePassowrd()` escriben en ambas tablas en la misma operación.

### Autorización por perfil

La autorización se resuelve consultando `perfiles_id` con `UserMetadataService::getPerfilIdByEmail()`, a partir del email que envía el propio cliente. El patrón recurrente en los servicios es:

```php
$perfilId = $this->userMetadataService->getPerfilIdByEmail($user_email);

if ($perfilId == 3) {
    $query->where('cc.user_email', $user_email);
}
```

| Perfil | Rol | Efecto |
|---|---|---|
| 1 | Administrador | Ve todo. En `PUT /api/chequeo-cardiovascular/{id}/{email}` es el único que puede cambiar `rut`, `user_email`, `status` y `fecha_atencion`, y el que propaga el cambio de RUT a `certificado_url` y `electro_cardiogranas`. |
| 2 | Tester en terreno | Al crear o actualizar un chequeo, fija `fecha_atencion = now()` y `status = 'Testiado'`. |
| 3 | Club deportivo | `WHERE cc.user_email = :user_email` — solo ve sus propios registros, e **ignora el filtro `selectClub`**. |
| 5 | Paciente | Perfil que asigna `POST /api/login/create-user` en el autoregistro. |
| 6 | Médico | Solo ve derivados: join con `certificado_url` por `(rut, id_chequeo)` y `cu.derivado_medico = 'SI'`; en la búsqueda, además, `cc.status = 'ECG FOTO'`. |
| otros | — | Ven todo. |

> Este patrón está **duplicado en tres métodos** de `ChequeoCardiovascularService`: `filterCalendar()`, `SearchChequeo()` y `ChequeoEmailAll()`. Si cambia la regla, hay que cambiarla en los tres — pero **hoy las tres copias no son idénticas**:
>
> | Método | Perfil 3 | Perfil 6 | Join con el ECG |
> |---|---|---|---|
> | `filterCalendar()` | sí | **no lo aplica** | por RUT |
> | `SearchChequeo()` | sí, más `orderByRaw(FIELD(...))` | `derivado_medico='SI'` **y** `status='ECG FOTO'` | subquery del último ECG por `id_chequeo` |
> | `ChequeoEmailAll()` | sí | `derivado_medico='SI'`, **sin** filtro de status | subquery `MAX(id) GROUP BY id_chequeo` |
>
> Antes de unificarlas, decide si la divergencia es intencional. Ver el [diagrama de autorización](docs/modelo-datos.md#autorización-por-perfil).

Como las rutas no llevan middleware y el `user_email` lo envía el cliente, este filtrado es una **regla de presentación, no un control de acceso**: cualquiera que conozca la URL puede pedir los datos de otro club cambiando el email del cuerpo.

---

## Integración con OpenAI

Se usa `openai-php/laravel` a través de la facade `OpenAI::chat()`.

### Prompts

Los prompts de sistema viven como clases estáticas en `app/PromptSystem/` y **no deben incrustarse en los controladores**:

| Clase | Uso |
|---|---|
| `AnalisisECGPrompt::system()` | Interpretación de electrocardiogramas; responde JSON estructurado |
| `AnalisisBioimpedanciaPrompt` | Análisis de una medición de bioimpedancia |
| `AnalisisRutBioimpedanciaPrompt` | Análisis histórico de bioimpedancia por RUT |
| `AsistenteChatPacientePrompt::system($patient, $data)` | Asistente conversacional con contexto clínico del paciente |
| `AsistenteChatClubPrompt::system($email, $search, $pacientes, $total, $truncado)` | Asistente conversacional sobre los pacientes de un club |
| `AsistenteChatComercialPrompt::system($contexto, $contacto)` | Chat comercial «Ergosanito» del Home: un solo mensaje de sistema estático (rol y reglas, diccionario de datos y catálogo público) |
| `AsistenteVozPrompt` | Asistente de digitación por voz |

Los modelos en uso son `gpt-4o-mini` (chat clínico, chat por club, chat comercial, extracción del paciente) y `gpt-4.1-mini` (ECG, voz, bioimpedancia).

Dónde se hace la llamada es **inconsistente**; sigue el patrón del dominio que toques:

| Dominio | Dónde vive la llamada |
|---|---|
| Chat clínico | `OpenAIService`, invocado desde `OpenAIController::AsQuestionUseCase` |
| Chat por club | `ClubAssistantController::AsQuestionClubUseCase` (el service solo prepara los datos) |
| Chat comercial | `ChatComercialController::AsQuestionUseCase` (el service arma el contexto, la sesión y el historial) |
| ECG y asistente de voz | `OpenAI::chat()` directo en `OpenAIController` |
| Bioimpedancia | `OpenAI::chat()` en `BioimpedanciaController::FormUpload` y en `BioimpedanciaService` |

### Contratos de salida de los prompts

Tres prompts devuelven JSON cuyas claves **son contrato con el cliente y con la base de datos**. No las renombres ni cambies su orden sin coordinar:

- `AsistenteVozPrompt` — 26 campos, siempre presentes aunque vayan vacíos. Sus nombres coinciden con las columnas de `chequeo_cardiovascular` y `electro_cardiogranas`, de modo que la respuesta se puede reenviar casi tal cual a `POST /api/chequeo-cardiovascular`. Los campos no dictados van a `""`; la excepción es `frecuencia_cardiaca_paciente`, que va a `null`.
- `AnalisisECGPrompt` — 20 campos de texto. Aplica los Criterios Internacionales de ECG en deportistas (2017) y el patrón juvenil pediátrico, porque la población es mayoritariamente menor de 18 años: bradicardia sinusal, repolarización precoz o inversión de onda T en V1-V3 en menores de 16 se informan como **normales** para esta población. Es un apoyo al tamizaje, no un informe final, y su resultado **no se guarda**: la lectura oficial se registra con `POST /api/electro-cardiograma/save`.
- `AnalisisBioimpedanciaPrompt` — sus claves corresponden una a una con las columnas de la tabla `bioimpedancia` (ver `BioimpedanciaService::mapBioimpedancia()`).

Cada prompt debe vivir en **un único archivo**. Duplicar el archivo para conservar una versión anterior provoca que dos archivos declaren la misma clase, lo que genera una colisión en el classmap que produce `composer install --optimize-autoloader` durante el build de producción: cuál de los dos se carga queda indeterminado. Para versionar un prompt, usa git.

### Flujo del asistente clínico

`POST /api/sam-assistant/as-question`:

1. `OpenAIService::resolveSession($sessionId, $prompt)` crea o recupera una fila en `chat_sessions` por `session_id`.
2. Si la sesión aún no tiene paciente asociado, `PatientHelper::extractPatient()` intenta extraerlo: primero con una expresión regular de RUT chileno (`/\d{7,8}-[\dkK]/`) y, si no hay coincidencia, con una llamada a `gpt-4o-mini` que extrae el nombre.
3. Sin paciente resuelto, la respuesta es `{"status": "needs_identifier"}` pidiendo el RUT o el nombre.
4. Con paciente resuelto, `OpenAIService::handle()` persiste el mensaje en `chat_history` y recupera **20** mensajes de ese paciente como contexto conversacional.

   > ⚠️ La consulta es `orderBy('created_at', 'asc')->limit(20)`, así que devuelve los **20 mensajes más antiguos**, no los más recientes: a partir del mensaje 21 el contexto queda congelado en el inicio de la conversación. El asistente por club no tiene este problema (usa `desc` y luego invierte).
5. `EstadisticasService::ChequeoPrompt($search)` (→ `SP_chequeos_prompt`) aporta los datos clínicos, que se inyectan en el prompt de sistema.

`POST /api/sam-assistant/reset-patient` limpia únicamente `patient_identifier` de la sesión, conservando el historial.

> El historial se guarda **por paciente**, no por sesión: dos sesiones distintas que hablen del mismo paciente comparten la conversación.

### Flujo del asistente por club

`POST /api/sam-assistant-club/as-question` es un flujo paralelo al anterior, construido sin tocarlo. En vez de girar en torno a un paciente, responde sobre **el conjunto de pacientes en `REVISION MEDICA` de un club**.

1. `ClubAssistantService::resolveSession($sessionId, $email, $search)` crea o recupera la fila de `chat_club_sessions`. `club_email` es `NOT NULL` sin default y la base corre en `STRICT_TRANS_TABLES`, así que se pasa en la creación.
2. `datosClub()` ejecuta `SP_chequeos_club_prompt($search, $club)`, corrige el orden de la presión arterial y **trunca en 120 pacientes** (`ClubAssistantService::MAX_PACIENTES`). El prompt recibe el total real y una marca de truncado para que el asistente avise cuando está viendo solo una parte.
3. Sin pacientes que devolver, la respuesta es `{"status": "sin_datos"}` con HTTP 200.
4. `handle()` persiste el mensaje en `chat_club_history` y recupera los **20 mensajes más recientes de la sesión**, desempatando por `id` cuando comparten `created_at`.

La semántica del campo `search` es el detalle importante para el cliente, porque se **persiste en la sesión**:

| Cómo se envía | Efecto |
|---|---|
| El campo no viene en el JSON | Se conserva el `search_actual` que ya tenía la sesión |
| `"search": ""` | Reset explícito: el chat pasa a hablar de todo el club |
| `"search": "Fuentes"` | Se guarda ese filtro y el chat queda acotado a ese paciente |

`POST /api/sam-assistant-club/reset-search` equivale a enviar `"search": ""`. Como el asistente de paciente, no borra el historial.

Es además, junto con `ChatComercialController`, uno de los controladores que **captura `ValidationException` por separado** y responde un 422 correcto; en el resto, la excepción de validación cae en el `catch (\Exception)` genérico y sale como 500.

### Flujo del chat comercial «Ergosanito»

`POST /api/chat-comercial/as-question` (Spec 06, `specs/06-chat-comercial-ergosanito.md`) es el tercer chat y un dominio aparte: atiende a **visitantes anónimos** del Home, es **pública** y es la única ruta con limitador (`throttle:chat-comercial`).

1. Valida `prompt` (`required|string|max:500`) y `sessionId` (`nullable|string|max:64`); `ChatComercialService::enmascarar()` cambia correo, teléfono y RUT del visitante por `[EMAIL]`, `[TELEFONO]` y `[RUT]` antes de guardar y de enviar a OpenAI.
2. `resolverSesion()` recupera la sesión de `chat_comercial_sessions` o crea una con un UUID nuevo si el `sessionId` falta o no es UUID.
3. `contexto()` arma **solo desde el catálogo público** (`cf_productos` visibles, comunas, tramos de operativo y dos reglas, con lista blanca de columnas) y lo guarda 600 s en caché (`chat-comercial:contexto`). No consulta tablas clínicas ni SP.
4. `AsistenteChatComercialPrompt::system()` arma un mensaje de sistema idéntico entre peticiones (para el *prompt caching* de OpenAI), seguido de los **10 mensajes más recientes** de la sesión y la pregunta. `gpt-4o-mini`, `temperature: 0.3`, `max_tokens: 400`.
5. `separarDerivacion()` quita el marcador `[DERIVAR]` y responde `{sessionId, response, derivar}`. La persona de contacto sale de `config('services.chat_comercial.contacto')`.

La memoria es por `sessionId`, no por IP; ninguna tabla guarda IP ni datos del visitante. El 500 es genérico y no expone `file` ni `line`.

---

## Generación de documentos

| Formato | Librería | Implementación |
|---|---|---|
| PDF | mPDF | `ChequeoCardiovascularPDFService`, `BioimpedanciaService` |
| Word | PHPWord | `ChequeoCardiovascularWordService` |
| Excel (importación) | maatwebsite/excel | `App\Imports\ChequeoImport` |

Los PDF se construyen concatenando HTML como string y renderizándolo con mPDF. Los recursos gráficos se referencian con `public_path()`: `logo.png`, `logoChico.png`, `firmarDoctor.jpg`, `firmarErgo.jpg`, `firma_cardiologo.jpeg`, `watermark.png`.

### Almacenamiento de archivos

Los archivos subidos se mueven con `$file->move()` a subdirectorios de `public/`, **no** al disco de storage de Laravel:

| Directorio | Contenido |
|---|---|
| `public/Certificado` | Certificados médicos, nombrados `{rut}-{id_chequeo}.{ext}` |
| `public/Logo` | Logos de clubes deportivos |
| `public/Electrocardiograma` | Imágenes de ECG para análisis con IA |
| `public/Bioimpedancia` | Informes de bioimpedancia |
| `public/HomeImagenes` | Imágenes del Home comercial, nombradas `{seccion}-{slug}-{uniqid}.{ext}` |

Las URL públicas se componen con `env('API_PATH_CER')` y `env('API_PATH_LOGO')`; las del Home, con `config('services.home_imagenes.url')`.

> **En producción estos archivos no sobreviven a un redeploy.** `dockerHub/docker-compose.yml` monta volúmenes para `storage/` y `bootstrap/cache`, **pero no para `public/`**: lo subido vive en la capa escribible del contenedor y se pierde al desplegar `:latest`. Tenlo presente antes de proponer que algo nuevo se guarde en `public/`.

Además, la validación de estas subidas es desigual: `POST /api/carga-masiva/excel` y `POST /api/GPT/analisis-ecg` validan tipo y tamaño, mientras que `POST /api/certificado/save-url` y `POST /api/auth-register/load-logo` **no validan nada**, y si no llega un archivo válido no retornan nada (respuesta 200 con cuerpo vacío).

---

## Carro de servicios (cart-flow)

La tienda pública `/servicios` del front vende un catálogo completo: laboratorio, enfermería, cardiovascular, vacunas
y eventos. El carro admite varios pacientes en la misma dirección, agenda por bloques con cupo y pago por Webpay,
transferencia o efectivo. Las specs son:
- [`specs/04-servicio-cart-flow.md`](specs/04-servicio-cart-flow.md): carro público.
- [`specs/05-mantenedor-carrito-compra.md`](specs/05-mantenedor-carrito-compra.md): mantenedor del Administrador.

Es un flujo **paralelo** al de `servicios` / `agenda-horas` / `transbank/*`, que siguen sin cambios.

| Pieza | Dónde |
| --- | --- |
| Rutas | `cart-flow/*` (público) y `cart-flow/admin/*` (Administrador) en `routes/api.php` |
| Controladores | `app/Http/Controllers/CartFlow/` (`CatalogoController`, `CarritoController`, `AgendaPedidoController`, `AdminCartFlowController`; sobre común en el trait `RespuestasCartFlow`) |
| Servicios | `app/Services/CartFlow/`, registrados en `CartFlowServiceProvider` |
| Modelos | `app/Models/CartFlow/` (tablas `cf_*`) |
| Catálogo versionado | `database/data/cart-flow/*.csv` (sin costos) |
| Tests | `tests/Unit/CartFlow/` (los 10 ejemplos de la especificación) y `tests/Feature/CartFlow/` |

**Reglas que no se ven leyendo un solo archivo:**

- El total lo calcula **solo** `CalculadoraCarroService`, una función pura. El cliente nunca envía montos y Webpay
  cobra `cf_pedidos.total`.
- Nada se borra:
  - quitar un paciente o una línea del carro es soft delete;
  - un pedido se anula con motivo;
  - cada cambio de estado va a `cf_pedido_historial`.
- El estado del pedido cambia **solo** por acciones nombradas (`confirmar_pago`, `confirmar`, `atender`, `anular`).
  `AdminPedidoService::accionesPermitidas()` decide cuáles valen.
- El Administrador se valida con `user_email` de perfil `Administrador`, igual que en la Spec 03. **No es
  autenticación.**
- La orden médica se guarda en `storage/app/private/cart-flow/ordenes` y se descarga por una URL firmada que dura
  5 minutos.

**Puesta en marcha** (una vez por entorno, con respaldo previo; nunca `migrate` sin `--path`):

```bash
php artisan migrate --path=database/migrations/2026_11_04_120000_create_cf_catalogo_tables.php \
  --path=database/migrations/2026_11_04_120100_create_cf_agenda_carrito_tables.php \
  --path=database/migrations/2026_11_04_120200_create_cf_pedido_pago_tables.php \
  --path=database/migrations/2026_11_05_120000_create_cf_pedido_historial_table.php
php artisan db:seed --class=CartFlowSeeder          # reglas, bloques de agenda y catálogo (idempotente)
php artisan cart-flow:importar-catalogo [--dir=...]  # reimportar: no pisa visible_tienda; --dir para cargar costos
```

**Probar Webpay en integración:** con las credenciales públicas de Transbank en `WEBPAY_URL|ID|SECRET`
(`webpay3gint`), usa la tarjeta VISA `4051 8856 0044 6623`, CVV 123, vencimiento 12/34, y en el banco simulado
RUT 11.111.111-1 y clave 123.

**Tests sin tocar el MySQL remoto:** `php artisan test --filter=CartFlow`. `CartFlowTestCase` fuerza SQLite en
memoria antes de arrancar la app.

---

## Trampas conocidas

Comportamientos del proyecto que no se deducen leyendo un solo archivo y que rompen cosas si se ignoran:

- **No ejecutes `php artisan config:cache`.** Varios controladores y servicios leen `env()` fuera de `config/`: `API_PATH_CER` (`CertificadoService`, `CertificadoUrlController`), `API_PATH_LOGO` (`Auth/UserController`), `WEBPAY_URL|ID|SECRET|RETURN` (`WebPayController`) y `GOOGLE_CLIENT_ID|SECRET` (`GoogleAuthControlle`). Con la config cacheada devuelven `null` y se rompen certificados, logos, pagos y login de Google. Por eso `dockerHub/entrypoint.sh` hace `config:clear` en cada arranque.
- **`.env.example` está incompleto**: define `API_PATH_CER` y `API_PATH_HOME` pero no `API_PATH_LOGO`, que el código sí usa.
- **Las escrituras de `home-imagenes` no tienen autenticación real.** Exigen un `user_email` de perfil Administrador, pero el email viaja en el request: quien conozca uno puede falsificarlo. Es deuda hasta que exista autenticación (Sanctum) en una spec aparte.
- **El orden de `routes/api.php` importa.** Las rutas específicas (`chequeo-cardiovascular/pdf/{id}`) deben declararse antes que las genéricas (`chequeo-cardiovascular/{id_paciente}`).
- **Hay rutas duplicadas**: `/user` (líneas 7 y 44) e `incidencia-deportivos/count-liga` (líneas 267 y 270). Laravel se queda con la última declaración; al editar una, asegúrate de tocar la que realmente se resuelve.
- **Typos consolidados que no se corrigen sin actualizar rutas y clientes**: el controlador de Google se llama `GoogleAuthControlle` (sin la "r" final), `UserController` expone `UserUpdatePassowrd`, la tabla de ECG es `electro_cardiogranas` y la columna es `gradoIncidenciaPosterio`.
- **Archivos muertos versionados**: `api.php` en la raíz es una copia obsoleta de `routes/api.php`, y `app/Http/Controllers/bootstrap/` es una copia muerta del `bootstrap/` real que nunca se carga. Los archivos que importan son `routes/api.php`, `bootstrap/app.php` y `bootstrap/providers.php`.
- **`vendor/bin/pint` sin argumentos reformatea medio repositorio.** No hay `pint.json`, así que aplica el preset `laravel`, que el código existente no cumple. Usa siempre `--dirty` o rutas explícitas.
- **`PUT /api/chequeo-cardiovascular/{id}/{email}` borra datos clínicos si el formulario llega incompleto**: los campos vacíos vuelven a sus valores por defecto (`No Presenta` / `Sin Alteraciones`) aunque antes tuvieran contenido.
- **`POST /api/electro-cardiograma/save` borra todos los ECG previos** del chequeo antes de insertar: solo se conserva la última lectura, no hay historial.
- **`DELETE /api/chequeo-cardiovascular/{id}` no borra en cascada** los certificados ni los ECG asociados, y no revierte la facturación que ese chequeo ya generó.
- **Ningún flujo de escritura múltiple usa transacción.** Subir un certificado mueve el archivo, inserta la fila, cambia el `status` y factura en cuatro pasos independientes: si uno falla, los anteriores quedan aplicados.
- **Métodos públicos sin ruta**: `ChequeoCardiovascularController::DeleteRut()` (su ruta `DELETE chequeo-cardiovascular/{rut}` está comentada) y `FileUploadController::FileUploadStorage()`.
- **Dependencias declaradas que no se usan**: `CargaMasivaController` inyecta `ChequeoCardiovascularService` y nunca lo llama; `OpenAIController::AsQuestionUseCase()` recibe `OpenAIService` dos veces (por constructor y por parámetro) y usa el del método.

---

## Diagramas

La carpeta [`docs/`](docs/) contiene la vista gráfica del sistema, en diagramas Mermaid que se renderizan solos en GitHub y en VS Code:

| Documento | Qué muestra |
|---|---|
| [`docs/arquitectura.md`](docs/arquitectura.md) | Componentes, las tres capas y sus excepciones, inyección de dependencias y despliegue |
| [`docs/flujos.md`](docs/flujos.md) | 16 diagramas de secuencia, uno por flujo end-to-end (carga masiva, facturación, asistentes, WebPay…) |
| [`docs/diagrama-clases.md`](docs/diagrama-clases.md) | Clases UML por dominio: controladores, servicios, modelos y prompts, con sus firmas reales |
| [`docs/modelo-datos.md`](docs/modelo-datos.md) | ERD de las 18 tablas, máquina de estados del chequeo, autorización por perfil y catálogo de procedimientos almacenados |

[`docs/README.md`](docs/README.md) es el índice de la carpeta y explica cuándo hay que actualizar cada documento. Como el contrato OpenAPI, **los diagramas se mantienen a mano**: al cambiar un flujo, actualízalos en el mismo commit.

---

## Contrato OpenAPI (Swagger)

El contrato completo de la API vive en **[`docs/openapi.yaml`](docs/openapi.yaml)** (OpenAPI 3.0.3): las 120 operaciones de `routes/api.php`, con esquemas, parámetros, ejemplos de request y de respuesta para cada código de estado, y las particularidades reales del proyecto (los dos formatos de sobre incompatibles, los endpoints que facturan, los que devuelven 200 en caso de error, el filtrado por perfil).

Las tablas de la sección [Endpoints](#endpoints) son el índice rápido; el YAML es la referencia detallada.

### Ver la documentación en el navegador

`docs/index.html` trae un visor Swagger UI ya configurado. Sirve la carpeta por HTTP (el navegador no carga el YAML desde `file://`):

```bash
php -S localhost:8080 -t docs
# y abre http://localhost:8080
```

### Validar el contrato

```bash
# Parseo del YAML con el componente que ya trae el proyecto
php -r 'require "vendor/autoload.php"; Symfony\Component\Yaml\Yaml::parseFile("docs/openapi.yaml"); echo "OK\n";'

# Validación estructural OpenAPI (requiere Node)
npx @redocly/cli lint
```

`redocly.yaml`, en la raíz, apunta al contrato y deja documentado qué reglas de estilo están desactivadas y por qué (por ejemplo `operation-4xx-response`: esta API devuelve 500 donde otra devolvería un 4xx, así que exigir respuestas 4xx obligaría a documentar códigos que nunca se emiten). Hoy el contrato pasa el lint **sin errores ni advertencias**. Hay 3 excepciones puntuales en `.redocly.lint-ignore.yaml`, cada una con su motivo: el ejemplo `data: null` de `juego-cartas/detalle` y los dos retornos de Webpay del carro, que solo responden 302.

### Mantenerlo al día

`docs/openapi.yaml` se escribe a mano: **no hay generación automática desde el código**, ni anotaciones de L5-Swagger en los controladores. Al agregar o cambiar una ruta en `routes/api.php` hay que actualizar el YAML en el mismo commit. Para comprobar que no falta ninguna:

```bash
php artisan route:list --json
```

---

## Colección Postman

**[`docs/postman/`](docs/postman/)** trae la colección Postman v2.1 con las **120 operaciones** repartidas en 19 carpetas, más tres entornos (local, Docker y producción). A diferencia del contrato y los diagramas, esto **sí es generado**: la fuente es `docs/openapi.yaml`.

```bash
# Regenerar la colección y los entornos tras tocar el contrato
php docs/postman/generar-coleccion.php

# Comprobar que cubre todas las rutas registradas
php docs/postman/verificar-cobertura.php

# Ejecutar una carpeta desde la línea de comandos (requiere Node)
npx newman run docs/postman/Ergosanitas-API.postman_collection.json \
  -e docs/postman/Ergosanitas-Local.postman_environment.json --folder "Utilidades"
```

En Postman: **Import → Files**, selecciona el `.json` de la colección y el entorno que uses. Las variables `baseUrl`, `user_email`, `rut_paciente`, `id_paciente`, `perfil` y `name` traen ejemplos ficticios del contrato; apúntalas a datos reales antes de probar.

Dos avisos antes de dispararle a un entorno con datos: los endpoints de certificados y ECG **facturan en cada ejecución** (`SP_pago_mensual(..., 'ADD')`, sin idempotencia) y la carpeta *Asistentes IA* **gasta tokens de OpenAI**. Las carpetas del carro **crean carros, pedidos y cotizaciones reales** en la base del entorno; además, `pedidos/{numero}/webpay` abre una transacción en Transbank y las acciones de `admin/pedidos/{numero}/estado` dejan historial. El detalle está en [`docs/postman/README.md`](docs/postman/README.md).

---

## Endpoints

Todas las rutas están definidas en `routes/api.php` y llevan el prefijo `/api`.

> El archivo es plano, sin agrupaciones. **El orden importa**: las rutas específicas como `chequeo-cardiovascular/pdf/{id}` deben declararse antes que las genéricas como `chequeo-cardiovascular/{id_paciente}`.

### Autenticación y usuarios

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `auth-login` | Login con Google |
| `POST` | `auth-register` | Login con email y contraseña |
| `POST` | `login/create-user` | Crear usuario |
| `POST` | `auth-register/load-logo` | Subir logo de club |
| `GET` | `auth-register/user_email/{perfil}` | Listar usuarios por perfil |
| `POST` | `user-save` | Guardar usuario y metadata |
| `PUT` | `user-update-password` | Actualizar contraseña |
| `PUT` | `user-update-ergo-pass` | Actualizar `ergo_pass` |
| `POST` | `user-first-ergo-pass` | Consultar `ergo_pass` inicial |
| `GET` | `user` | Usuario autenticado (Sanctum) |

### Chequeo cardiovascular

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `chequeo-cardiovascular/health` | Health check |
| `GET` | `chequeo-cardiovascular` | Listado completo |
| `POST` | `chequeo-cardiovascular/user` | Buscar por email (filtra según perfil) |
| `POST` | `chequeo-cardiovascular` | Crear chequeo |
| `PUT` | `chequeo-cardiovascular/{id_paciente}/{user_email}` | Actualizar chequeo |
| `DELETE` | `chequeo-cardiovascular/{id}` | Eliminar por ID |
| `GET` | `chequeo-cardiovascular/{id_paciente}` | Detalle por ID |
| `GET` | `chequeo-cardiovascular/pdf/{id_paciente}` | PDF por ID |
| `GET` | `chequeo-cardiovascular/pdfRut/{rut_paciente}` | PDF por RUT |
| `GET` | `chequeo-cardiovascular-word/{id_paciente}` | Documento Word |
| `POST` | `chequeo-cardiovascular/filter-calendar` | Filtrar por fecha |
| `POST` | `chequeo-cardiovascular/search-chequeo` | Búsqueda paginada |
| `GET` | `chequeo-cardiovascular/estado-general/{user_email}` | Estado general (`SP_estado_general`) |
| `POST` | `chequeo-cardiovascular/club-deportivo` | Chequeos del club |
| `POST` | `chequeo-cardiovascular/chequeo-all` | Todos los chequeos por email |
| `POST` | `chequeo-cardiovascular/like-chequeo` | Búsqueda parcial |
| `POST` | `chequeo-cardiovascular/like-chequeo/user` | Búsqueda parcial por usuario |

### Electrocardiograma

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `electro-cardiograma/find-by-rut` | Buscar informe por RUT |
| `POST` | `electro-cardiograma/save` | Guardar informe |

### Bioimpedancia

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `bioimpedancia/list-all` | Listado completo |
| `POST` | `bioimpedancia/first-rut` | Última medición por RUT |
| `POST` | `bioimpedancia/create-bio` | Crear medición |
| `POST` | `bioimpedancia/form-upload` | Subir formulario |
| `GET` | `bioimpedancia/pdfRut/{rut_paciente}` | PDF por RUT |

### Ficha clínica

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `ficha-clinica/{rut_paciente}` | Ficha clínica consolidada (`SP_ficha_clinica`) |

### Juego de cartas

Evaluación gamificada por club: cada paciente es una carta con cuatro atributos
comparables 0-100, puntaje, estrellas, badge clínico y barra de completitud. Todo lo
calcula `SP_juego_cartas_club` al vuelo; nada se persiste.

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `juego-cartas/niveles` | Bandas de `juego_niveles` y catálogo de `juego_atributos` |
| `GET` | `juego-cartas/detalle/{rut_paciente}` | Una carta por RUT, sin filtro de club |
| `GET` | `juego-cartas/{user_email}` | Todas las cartas del club, con `?search=` opcional |

**El orden de declaración es obligatorio**: las rutas literales van antes de la que
captura `{user_email}`, o `juego-cartas/niveles` se resolvería con `user_email = "niveles"`.

### Home comercial

Mantenedor de las imágenes de promociones, alianzas y galería del Home del front (Spec 03).
Sobre `{success, message, data}`. Las escrituras exigen un `user_email` de perfil
Administrador y responden **403** si no lo es; no es autenticación real (ver
[Trampas conocidas](#trampas-conocidas)).

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `home-imagenes` | Imágenes activas, por sección y orden. Público |
| `GET` | `home-imagenes/admin` | Todas, activas e inactivas (no exige `user_email`) |
| `POST` | `home-imagenes` | Sube una imagen (multipart: `file`, `seccion`, `alt`, `caption?`, `orden?`, `user_email`). JPG/PNG/WEBP de hasta 5 MB. Responde 201 |
| `PUT` | `home-imagenes/{id}` | Edita `seccion`, `alt`, `caption` y `orden` (JSON + `user_email`) |
| `PUT` | `home-imagenes/{id}/activo` | Muestra u oculta (`activo` booleano + `user_email`) |
| `DELETE` | `home-imagenes/{id}` | Borra la fila y el archivo (`?user_email=`) |

`home-imagenes/admin` se declara antes que las rutas con `{id}`, y `{id}` lleva `whereNumber`.

### Certificados

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `certificado/save-url` | Subir certificado |
| `POST` | `certificado/path-url` | Obtener ruta del certificado |
| `POST` | `certificado/valida-certificado` | Validar certificado |
| `POST` | `carga-masiva-ecg` | Carga masiva de ECG |
| `GET` | `certificado/validar/{rut_paciente}` | Validar por RUT |
| `GET` | `certificado/{rut_paciente}` | Mostrar certificado |

### Estadísticas y pagos

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `estadisticas/estadistica-imc/{user_email}` | IMC |
| `GET` | `estadisticas/estadistica-presion/{user_email}` | Presión arterial |
| `GET` | `estadisticas/estadistica-hemoglucotest/{user_email}` | Hemoglucotest |
| `GET` | `estadisticas/estadistica-saturacion/{user_email}` | Saturación |
| `GET` | `estadisticas/estadistica-pago-mensual` | Montos mensuales |
| `GET` | `estadisticas/estadistica-pago-mdc` | Montos MDC |
| `POST` | `estadisticas/pago-mensual` | Registrar pago mensual |
| `POST` | `estadisticas/update-pago-mensual` | Actualizar pago mensual |
| `POST` | `estadisticas/delete-pago-mensual` | Eliminar pago mensual |
| `POST` | `estadisticas/agenda-mensual` | Agenda mensual |

### Incidencias deportivas

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `incidencia-deportivos/create` | Registrar incidencia |
| `GET` | `incidencia-deportivos/find-by-user/{user_email}` | Incidencias del usuario |
| `GET` | `incidencia-deportivos/count-club/{user_email}` | Conteo por club |
| `GET` | `incidencia-deportivos/count-gravedad/{user_email}` | Conteo por gravedad |
| `GET` | `incidencia-deportivos/count-liga/{user_email}` | Conteo por liga |
| `GET` | `incidencia-deportivos/lesion-frecuente/{user_email}` | Lesión más frecuente |
| `GET` | `incidencia-deportivos/liga-casos/{user_email}` | Casos por liga |
| `GET` | `incidencia-deportivos/sp_estadistica_liga/{user_email}` | Estadística por liga |
| `GET` | `incidencia-deportivos/sp_estadistica_categoria/{user_email}` | Estadística por categoría |
| `GET` | `incidencia-deportivos/sp_estadistica_lesiones/{user_email}` | Estadística de lesiones |
| `GET` | `incidencia-deportivos/sp_estadistica_parte_cuerpo/{user_email}` | Estadística por parte del cuerpo |
| `GET` | `incidencia-deportivos/sp_estadistica_lesiones_fechas/{user_email}` | Lesiones por fecha |

### Asistentes de IA

| Método | Ruta | Acción |
|---|---|---|
| `POST` | `sam-assistant/as-question` | Chat clínico con contexto de paciente |
| `POST` | `sam-assistant/reset-patient` | Reiniciar el paciente de la sesión |
| `POST` | `sam-assistant-club/as-question` | Chat sobre los pacientes de un club |
| `POST` | `sam-assistant-club/reset-search` | Reiniciar el filtro de paciente de la sesión |
| `POST` | `chat-comercial/as-question` | Chat comercial «Ergosanito» del Home (público, con limitador por IP: 429 al superar 20/min o 200/día) |
| `POST` | `GPT/asistente-voz` | Asistente por voz |
| `POST` | `GPT/analisis-ecg` | Análisis de ECG a partir de imagen |

### Agenda, servicios, pagos y utilidades

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `agenda-horas/health` | Health check |
| `GET` | `agenda-horas` | Listar agenda |
| `POST` | `agenda-horas` | Reservar hora |
| `GET` | `servicios` | Listar servicios |
| `GET` | `servicios/{name}` | Detalle de servicio |
| `POST` | `servicios/like` | Búsqueda parcial de servicios |
| `POST` | `transbank/web-pay-request` | Iniciar pago WebPay |
| `GET` | `transbank/web-pay-response` | Callback de WebPay |
| `POST` | `email/reserva-hora` | Enviar correo de reserva |
| `POST` | `file-upload` | Subida genérica de archivos |
| `POST` | `carga-masiva/excel` | Carga masiva desde Excel |
| `GET` | `execute-link-simbolik` | Ejecuta `php artisan storage:link` vía HTTP |

### Carro de servicios (`cart-flow`, público)

Sobre `{success, message, data}`. Ver [Carro de servicios](#carro-de-servicios-cart-flow).

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `cart-flow/comunas` | Comunas con zona y cargos de domicilio |
| `GET` | `cart-flow/catalogo` | Catálogo visible (`?categoria=&tipo=&q=`) |
| `GET` | `cart-flow/catalogo/{sku}` | Detalle de un producto con sus componentes |
| `POST` | `cart-flow/carritos` | Crea un carro anónimo (`uuid`) |
| `GET` / `PUT` | `cart-flow/carritos/{uuid}` | Carro recalculado / cambia comuna, dirección o plan |
| `POST` | `cart-flow/carritos/{uuid}/pacientes` | Agrega un paciente en la misma dirección |
| `PUT` | `cart-flow/carritos/{uuid}/pacientes/{id}` | Datos del paciente |
| `POST` | `cart-flow/carritos/{uuid}/pacientes/{id}/quitar` | Quita un paciente (soft delete) |
| `POST` | `cart-flow/carritos/{uuid}/lineas` | Agrega un producto a un paciente |
| `PUT` | `cart-flow/carritos/{uuid}/lineas/{id}` | Cambia la cantidad |
| `POST` | `cart-flow/carritos/{uuid}/lineas/{id}/quitar` | Quita una línea (soft delete) |
| `GET` | `cart-flow/agenda/bloques` | Bloques con cupo para el carro (`?carrito=`) |
| `POST` | `cart-flow/carritos/{uuid}/checkout` | Convierte el carro en pedido (multipart, con órdenes médicas) |
| `GET` | `cart-flow/pedidos/{numero}` | Consulta un pedido (`?t=token_consulta`) |
| `POST` | `cart-flow/pedidos/{numero}/webpay` | Inicia el pago Webpay por el total del pedido |
| `GET` / `POST` | `cart-flow/webpay/retorno` | Retorno de Transbank; redirige al front |
| `POST` | `cart-flow/cotizaciones` | Solicitud de cotización |

### Carro de servicios — administración (`cart-flow/admin`)

Todas exigen `user_email` de perfil Administrador (403 si no), salvo la descarga, que exige una URL firmada.

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `cart-flow/admin/resumen` | KPIs: pedidos de hoy, pendientes de pago, pagados del mes, próximas visitas |
| `GET` | `cart-flow/admin/pedidos` | Pedidos paginados con filtros y búsqueda por RUT |
| `GET` | `cart-flow/admin/pedidos/{numero}` | Detalle completo con pagos, órdenes, historial y acciones permitidas |
| `PUT` | `cart-flow/admin/pedidos/{numero}/estado` | `confirmar_pago` · `confirmar` · `atender` · `anular` (con motivo) |
| `POST` | `cart-flow/admin/ordenes/{id}/enlace` | URL firmada (5 min) para la orden médica |
| `GET` | `cart-flow/admin/ordenes/{id}/descarga` | Descarga de la orden médica (`signed`) |
| `GET` | `cart-flow/admin/cotizaciones` | Solicitudes de cotización |
| `PUT` | `cart-flow/admin/cotizaciones/{id}/estado` | `nueva` · `contactada` · `cerrada` |
| `GET` | `cart-flow/admin/productos` | Catálogo completo, también lo oculto |
| `PUT` | `cart-flow/admin/productos/{sku}/visibilidad` | Muestra u oculta un producto (con registro) |

---

## Despliegue

**La producción corre en el hosting compartido**, en `https://ergosanitas.com/BackEnd/public/`, no en Docker. La imagen de Docker Hub se publica, pero hoy no es la que atiende producción.

### Pipeline (`.github/workflows/github-action.build.yml`)

Se dispara con un push a `main`, con un pull request a `main` y a mano desde la pestaña Actions (`workflow_dispatch`). Tiene tres jobs:

| Job | Depende de | Qué hace |
|---|---|---|
| `test` | — | `composer validate`, `composer install`, `.env` ficticio desde `.env.example` y `php artisan test --filter="CartFlow\|ChatComercial"`, los únicos tests que fuerzan SQLite en memoria. No usa secrets. |
| `build` | `test` | Calcula la versión con `PaulHatch/semantic-version@v5` y construye `nruz176/laravel-back-end-ergosanitas-app` con los tags `:<version>` y `:latest`. En un **push** a `main` la publica en Docker Hub; en un **pull request** solo la construye. |
| `deploy` | `test` | Sube el back por FTP con `lftp mirror -R` **sin `--delete`**, a `/public_html/BackEnd/`. Corre a mano desde Actions (por defecto `dry_run`, que solo lista lo que subiría) o en un push a `main` si la variable del repo `DEPLOY_FTP_ENABLED` vale `true`. |

El job `deploy` deja fuera todo `public/` (datos de pacientes, imágenes, firmas e `index.php`; los cambios ahí se suben a mano), `storage/`, los `.htaccess`, `.env*`, `.git`, `.claude/`, `.agents/`, `tests/`, `docs/`, `specs/`, `references/`, `base_datos/`, `dockerHub/`, `docker-compose*` y `node_modules/`. No genera `APP_KEY` ni corre migraciones.

Configuración en GitHub (*Settings → Secrets and variables → Actions*): los secrets `FTP_USERNAME` y `FTP_PASSWORD` (y los de Docker Hub para el job `build`), y las variables opcionales `FTP_HOST`, `FTP_REMOTE_DIR` y `DEPLOY_FTP_ENABLED`.

**El pipeline no ejecuta migraciones.** Los cambios de esquema y los procedimientos almacenados se aplican a mano en la base, primero en local o staging y con un respaldo verificable.

### Versión semántica

El incremento sale del **asunto** del commit (Conventional Commits):

| Asunto | Incremento |
|---|---|
| `tipo!: …` o `BREAKING CHANGE` en el asunto | major |
| `feat: …` / `feat(x): …` | minor |
| cualquier otro (`fix`, `docs`, `chore`…) | patch |

### Despliegue en contenedor (`dockerHub/`)

`dockerHub/docker-compose.yml` consume la imagen publicada y monta `.env`, `storage` y `bootstrap/cache` como volúmenes, **pero no `public/`**: lo subido viviría en la capa escribible del contenedor y un redeploy de `:latest` lo perdería. Además, su nginx no monta `/var/www/public`, así que no sirve archivos estáticos. El arranque lo maneja `dockerHub/entrypoint.sh`:

```sh
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan storage:link
exec php-fpm -F
```

`.dockerignore` deja fuera de la imagen `.env*`, `.git`, `.github`, `.claude`, `docs/`, `specs/`, `tests/`, `vendor/`, lo subido en `public/` y las firmas `*_OLD.*`.

### Puesta en marcha de `public/HomeImagenes` en el hosting

Se hace **una sola vez** (paso 8 de la Spec 03):

1. Crear `BackEnd/public/HomeImagenes/` en el hosting.
2. Subir ahí los 24 archivos de `resources/home-imagenes-semilla/` (los mismos nombres que siembra la migración `2026_11_03_120000_create_home_imagenes_table`).
3. Definir `API_PATH_HOME=https://ergosanitas.com/BackEnd/public/HomeImagenes` en el `.env` del hosting. Sin ella cada `src` sale relativo y el Home comercial queda sin imágenes.
4. Confirmar que el despliegue del back **no borra** `public/HomeImagenes/`. El job `deploy` excluye `public/` y no usa `--delete`. Ahí viven las imágenes que sube el Administrador, que no están en git.
5. Comprobar que `.../HomeImagenes/promo-mes-corazon-01.png` responde 200 y que `GET api/home-imagenes` trae `src` con el dominio de producción.

---

## Entorno de desarrollo con IA (Claude Code)

El proyecto se trabaja con **Claude Code**. Esta sección es el **inventario completo** de lo que se instaló para eso: qué es cada pieza, dónde vive, con qué comando se instala o se creó, dónde está su documentación y cómo comprobar que funciona. La receta para dejar una máquina nueva igual está en [Máquina nueva desde cero](#máquina-nueva-desde-cero-checklist).

Hay **tres niveles** de configuración:

| Nivel | Dónde vive | ¿Va en git? | Qué guarda |
|---|---|---|---|
| **Proyecto (compartido)** | `CLAUDE.md`, `.claude/CLAUDE.md`, `.claude/agents/`, `.claude/skills/`, `.agents/skills/`, `skills-lock.json` | ✅ Sí | Lo que cualquiera que clone el repo debe tener. Este repo **no** tiene `.claude/settings.json` ni `.mcp.json`. |
| **Proyecto (local)** | `.claude/settings.local.json` y la entrada del proyecto en `~/.claude.json` | ❌ No | Hooks de graphify y los servidores MCP registrados con `-s local`. |
| **Usuario** | `~/.claude/` y `~/.claude.json` (`C:\Users\<usuario>\`) | ❌ No | Plugins, MCP de alcance usuario (`snyk`), skills personales y reglas de `autoMode`. Se reinstala en cada PC. |

### Claude Code

| | |
|---|---|
| Qué es | El CLI de Anthropic que ejecuta los agentes, skills y MCP de este repo. |
| Versión instalada | `2.1.322` (al 2026-11-10) |
| Documentación | <https://docs.claude.com/en/docs/claude-code/overview> |
| Instalación | <https://docs.claude.com/en/docs/claude-code/setup> |
| Configuración | <https://docs.claude.com/en/docs/claude-code/settings> · memoria (`CLAUDE.md`) <https://docs.claude.com/en/docs/claude-code/memory> |

```powershell
# Windows (PowerShell): instalador nativo
irm https://claude.ai/install.ps1 | iex
# alternativa con npm
npm install -g @anthropic-ai/claude-code

claude --version          # comprobar
claude                    # abrir una sesión en la carpeta del repo
```

Comandos de la sesión que se usan en este proyecto:

| Comando | Para qué |
|---|---|
| `/mcp` | Ver el estado de los servidores MCP. |
| `/agents` | Listar los agentes de `.claude/agents/`. |
| `/plugin` | Instalar, activar y desactivar plugins. |
| `/spec-ergo-back`, `/spec-impl-ergo-back`, `/spec-verify-ergo-backend`, `/graphify`… | Skills del proyecto ([Skills](#skills)). |
| `/init` | Revisar o regenerar `CLAUDE.md` (aquí solo se usa para proponer mejoras: el archivo se mantiene a mano). |
| `! <comando>` | Ejecutar un comando de shell dentro de la sesión. |
| `claude mcp list` / `claude mcp get <nombre>` | Ver los MCP registrados, su alcance y su comando (desde la terminal). |
| `claude --agent ergo-father` | Arrancar con el agente padre de todo Ergosanitas (desde `D:\ErgoSanitas\Projectos`, ver [Agentes](#agentes)). |

### Mapa de archivos de IA del repositorio

```
ergosanitas-app/
├── CLAUDE.md                        convenciones, arquitectura y trampas (lo lee Claude al arrancar) [git]
├── skills-lock.json                 origen y hash de las skills importadas (archify, spec, spec-impl)  [git]
├── .graphifyignore                  lo que graphify no debe indexar                                    [git]
├── config/boost.php                 configuración de Laravel Boost (herramientas excluidas)            [git]
├── .agents/skills/                  skills importadas de Klerith/fernando-skills                       [git]
│   ├── spec/
│   └── spec-impl/
├── .claude/
│   ├── CLAUDE.md                    registro de la skill graphify (lo escribió graphify install)       [git]
│   ├── settings.local.json          hooks de graphify de ESTA máquina                                  [local]
│   ├── agents/                      5 agentes (ver "Agentes")                                         [git]
│   └── skills/                      16 skills (ver "Skills"); spec y spec-impl son symlinks a .agents/ [git]
└── graphify-out/                    grafo de conocimiento generado                                     [ignorado]
```

Contenido de `.claude/settings.local.json` (no versionado; recréalo a mano en otra PC o con `graphify install --project`, ver [graphify](#graphify)):

```json
{
  "hooks": {
    "PreToolUse": [
      { "matcher": "Bash|Grep", "hooks": [{ "type": "command", "command": "graphify hook-guard search", "timeout": 10 }] },
      { "matcher": "Read|Glob", "hooks": [{ "type": "command", "command": "graphify hook-guard read", "timeout": 10 }] }
    ],
    "SessionStart": [
      { "matcher": "compact", "hooks": [{ "type": "command", "command": "graphify hook-guard compact", "timeout": 10 }] }
    ]
  }
}
```

Los hooks están en el archivo **local** a propósito: llaman a `graphify` por nombre y, versionados, fallarían a quien no lo tenga instalado.

### Servidores MCP

Un servidor MCP le da a Claude herramientas nuevas (esquema de la base, grafo del código, documentación de librerías…). Documentación general: <https://docs.claude.com/en/docs/claude-code/mcp>.

| Servidor | Alcance | Para qué | Restricción | Enlace |
|---|---|---|---|---|
| `laravel-boost` | Local | Esquema de la BD, conexiones, búsqueda en la documentación de Laravel y URLs absolutas. | Sin herramientas que lean filas o logs (ver [Laravel Boost](#laravel-boost)). | <https://github.com/laravel/boost> |
| `mysql-server` | Local | Consultas de **solo lectura** a la MySQL de producción, sobre todo para esquema y metadatos. | Autorizado por Gerencia (2026-11-10). Filas de pacientes solo si la tarea lo pide, y nunca copiadas a docs, specs, tests ni commits. | <https://github.com/benborla/mcp-server-mysql> |
| `graphify` | Local | Consultar el grafo del código (`query_graph`, `get_node`, `get_neighbors`, `get_community`, `god_nodes`, `graph_stats`, `shortest_path`). | Requiere `graphify-out/graph.json`. | <https://pypi.org/project/graphifyy/> |
| `context7` | Local | Documentación al día de Laravel, PHP y librerías (`resolve-library-id` → `query-docs`). | — | <https://context7.com> · <https://github.com/upstash/context7> |
| `filesystem` | Local | Acceso a archivos limitado a la carpeta del repo. | — | <https://github.com/modelcontextprotocol/servers/tree/main/src/filesystem> |
| `github` | Local | Issues, PRs y búsqueda de código. | Necesita Docker Desktop corriendo; si no, falla con `CONNECTION_CLOSED`. | <https://github.com/github/github-mcp-server> |
| `snyk` | Usuario | Escaneo de seguridad de dependencias, código y secretos. | — | <https://docs.snyk.io/integrations/developer-guardrails-for-agentic-workflows> |

**Registro**, desde la carpeta del repo. Los `<...>` son valores tuyos: nunca los escribas en un archivo versionado.

```bash
# Laravel Boost (requiere composer install + php artisan package:discover)
claude mcp add -s local -t stdio laravel-boost php artisan boost:mcp

# MySQL en solo lectura. En Windows NO uses npx -y: reinstala en cada arranque,
# choca con EPERM y supera los 30 s de conexión. Instálalo global:
npm install -g @benborla29/mcp-server-mysql
npm root -g                      # p. ej. C:/nvm4w/nodejs/node_modules
claude mcp add -s local -t stdio mysql-server \
  -e MYSQL_HOST=<DB_HOST> -e MYSQL_PORT=<DB_PORT> -e MYSQL_USER=<DB_USERNAME> \
  -e MYSQL_PASS=<DB_PASSWORD> -e MYSQL_DB=<DB_DATABASE> \
  -e ALLOW_INSERT_OPERATION=false -e ALLOW_UPDATE_OPERATION=false \
  -e ALLOW_DELETE_OPERATION=false -e ALLOW_DDL_OPERATION=false \
  -- node <npm root -g>/@benborla29/mcp-server-mysql/dist/index.js

# graphify (ver la sección graphify; necesita el extra [mcp])
claude mcp add -s local -t stdio graphify -- <C:/Users/<usuario>/.local/bin/graphify-mcp.exe> <ruta absoluta>/graphify-out/graph.json

# Context7
claude mcp add -s local -t stdio context7 -- npx -y @upstash/context7-mcp@latest

# filesystem
claude mcp add -s local -t stdio filesystem -- npx -y @modelcontextprotocol/server-filesystem <ruta absoluta del repo>

# GitHub (imagen Docker oficial; el token se pasa por variable)
claude mcp add -s local -t stdio github -e GITHUB_PERSONAL_ACCESS_TOKEN=<token> -- docker run -i --rm -e GITHUB_PERSONAL_ACCESS_TOKEN ghcr.io/github/github-mcp-server

# Snyk (alcance usuario, sirve a todos los proyectos; requiere el CLI de Snyk)
npm install -g snyk && snyk auth
claude mcp add -s user -t stdio snyk -- snyk mcp -t stdio

claude mcp list                  # comprobar: todos en ✔ Connected
```

Notas:

- En **Git Bash**, exporta `MSYS_NO_PATHCONV=1` antes de `claude mcp add`, o convierte los argumentos que empiezan por `/` (como `/c`) a `C:/`. Si no, Git Bash los reescribe como rutas.
- Los valores de `MYSQL_*` salen de los `DB_*` de tu `.env`. Hoy se usa el usuario de la app, que en MySQL sí puede escribir: la protección son los cuatro `ALLOW_*=false`. Queda pendiente crear un usuario propio con `GRANT SELECT, SHOW VIEW`.
- El token de GitHub se crea en <https://github.com/settings/tokens> y solo se pasa por `-e`.
- **Conectores de claude.ai** (no son del repo, vienen de la cuenta): Claude Docs, Claude in Chrome y los MCP de los plugins `engineering`, `marketing`, `product-management` y `productivity` (Slack, Linear, Notion, Figma…) pueden aparecer en `claude mcp list` pidiendo autenticación. No hacen falta para este proyecto.

### Laravel Boost

| | |
|---|---|
| Qué es | Servidor MCP oficial de Laravel con herramientas sobre la app (esquema, documentación por versión, URLs). |
| Paquete | `laravel/boost` ^2.10, solo en `require-dev`: no llega a producción porque el `dockerfile` y el job `deploy` instalan con `--no-dev`. |
| Documentación | <https://github.com/laravel/boost> |
| Instalado con | `composer require --dev laravel/boost -W` (2026-11-10). Exige Laravel ≥ 11.45.3, así que subió `laravel/framework` de 11.24.0 a 11.57.1 (más guzzle 7.16 y symfony 7.4). |

- `config/boost.php` es una **copia completa** de `vendor/laravel/boost/config/boost.php` más el bloque `mcp`. Tiene que ser completa porque `mergeConfigFrom` solo mezcla claves de primer nivel. Si actualizas Boost, compárala con la del paquete.
- Excluye `DatabaseQuery`, `ReadLogEntries`, `LastError` y `BrowserLogs`, deja `tinker_tool_enabled = false` y desactiva el `browser_logs_watcher`. El motivo es que `.env` apunta a la MySQL con datos clínicos reales (Ley 21.719), y esas herramientas devuelven filas o logs con datos de requests. Quedan `database-schema`, `database-connections`, `search-docs`, `get-absolute-url` y `record-rule` (este último escribe en `.ai/rules/`).
- **No ejecutes `php artisan boost:install`**: añadiría un bloque `<laravel-boost-guidelines>` a `CLAUDE.md`, skills en `.claude/skills/`, `boost.json` y `.mcp.json`.
- Si el MCP falla con «no commands defined in the boost namespace», falta `php artisan package:discover`.

### graphify

| | |
|---|---|
| Qué es | Convierte el repositorio en un grafo (archivos, clases, métodos, relaciones, comunidades) para que Claude consulte el grafo en vez de leer archivo por archivo. |
| Paquete | `graphifyy` (con doble «y») en PyPI: <https://pypi.org/project/graphifyy/> |
| Versión instalada | `0.9.105` |
| Ejecutables | `graphify.exe` y `graphify-mcp.exe` en `C:\Users\<usuario>\.local\bin\` (debe estar en el `PATH`) |
| Instalado con | `uv` 0.7.2: <https://docs.astral.sh/uv/> |
| Skill | `.claude/skills/graphify/` (`SKILL.md`, `references/`, `.graphify_version`) |
| Salida | `graphify-out/` (`graph.json`, `GRAPH_REPORT.md`, `graph.html`), **no versionada** |

**Instalación:**

```powershell
irm https://astral.sh/uv/install.ps1 | iex             # 1. uv
uv tool install "graphifyy[mcp]==0.9.105" --force      # 2. graphify + graphify-mcp (el extra [mcp] trae el servidor)
graphify --version                                      #    → graphify 0.9.105
graphify install --project                              # 3. integración con Claude Code (ya hecha; solo si se pierde)
```

`graphify install --project` **no se limita a la skill**. Crea `.claude/skills/graphify/` y `.claude/CLAUDE.md`, añade la sección `## graphify` al final de `CLAUDE.md` y crea `.claude/settings.json` con los hooks. **Después hay que mover esos hooks a `.claude/settings.local.json` y borrar `.claude/settings.json`**. Para quitarlo todo: `graphify claude uninstall --project`.

La sección `## graphify` de `CLAUDE.md` está en inglés a propósito: graphify la reemplaza en cada reinstalación.

**Construir el grafo, solo con AST local.** No usa LLM ni API key y ningún archivo sale de la máquina. Hay que construirlo después de cada clon:

```bash
graphify extract . --code-only --no-label
graphify cluster-only . --no-label     # genera GRAPH_REPORT.md y graph.html
graphify update .                      # refrescar tras un cambio
```

Respeta `.gitignore` (deja fuera `.env`, `vendor/` y lo subido en `public/`) y `.graphifyignore`. Este último excluye `.claude/` (los JS de archify dominaban el grafo), `.agents/`, las copias muertas `api.php` y `app/Http/Controllers/bootstrap/`, y los artefactos generados de `docs/diagramas/` y `docs/postman/`.

⚠️ **No uses el modo semántico** (`/graphify .` o `graphify extract` sin `--code-only`): manda código y documentación a un LLM externo. Los `.sql` de `base_datos/references/sp/` no entran al grafo porque falta `tree_sitter_sql` (extra `graphifyy[sql]`).

**Consultar:**

```bash
graphify query "¿qué servicios usa CertificadoService?"
graphify path "ChequeoCardiovascularController" "EstadisticasService"
graphify explain "PagoMensual"
```

**Hooks** (en `.claude/settings.local.json`):

| Evento | Matcher | Comando | Qué hace |
|---|---|---|---|
| `PreToolUse` | `Bash\|Grep` | `graphify hook-guard search` | Antes de buscar, recuerda consultar el grafo. |
| `PreToolUse` | `Read\|Glob` | `graphify hook-guard read` | Antes de leer un archivo, avisa si el grafo ya lo cubre o está desactualizado. |
| `SessionStart` | `compact` | `graphify hook-guard compact` | Tras compactar la conversación, vuelve a inyectar el contexto del grafo. |

### Plugins

Se instalan a **nivel usuario** desde el marketplace oficial `claude-plugins-official` (<https://github.com/anthropics/claude-plugins-official>). Documentación: <https://docs.claude.com/en/docs/claude-code/plugins>.

```bash
/plugin install <nombre>@claude-plugins-official     # dentro de Claude Code
```

| Plugin | Estado (usuario, 2026-11-10) | ¿Aplica a este repo? |
|---|---|---|
| `code-simplifier` | ✅ Activo | Sí: agente que simplifica el código recién cambiado (`/simplify`). |
| `mcp-server-dev` | ✅ Activo | No: ayuda para crear servidores MCP. |
| `figma` | ✅ Activo | No. |
| `security-guidance` | Instalado | Lo activa el front en su `.claude/settings.json`; aquí cubre lo mismo la skill `ergosanitas-security`. |
| `typescript-lsp` | Instalado | No (es para el front). |
| `context7` | Instalado | No hace falta: el MCP `context7` ya está registrado. Con los dos activos, las herramientas salen duplicadas. |
| `frontend-design` | Instalado | No. |
| `superpowers` | Instalado | Opcional: skills genéricas de flujo de trabajo. |

**Reglas de `autoMode`** del usuario (`~/.claude/settings.json`, `autoMode.soft_deny`), que piden confirmación en este repo: `vendor/bin/pint` sin `--dirty` ni una ruta explícita, `php artisan config:cache`, y escribir un segundo archivo que declare una clase de prompt existente (la regla todavía dice `app/IA/`; los prompts viven hoy en `app/PromptSystem/`).

### Skills

Una skill es un paquete de instrucciones (`SKILL.md` con frontmatter) que Claude carga cuando la tarea lo pide, o cuando se invoca con `/nombre`. Documentación: <https://docs.claude.com/en/docs/claude-code/skills>. Las propias se **escribieron a mano** en `.claude/skills/<nombre>/SKILL.md`; no hay generador.

| Skill | Origen y cómo se creó | Invocación | Para qué |
|---|---|---|---|
| `ergosanitas-dev` | Propia (2026-09-05). Trae `references/`. | Automática | Recetario para crear o corregir módulos: controller/service/provider/model/ruta, queries por perfil, certificados y facturación, prompts, SP, PDF/Word/Excel, WebPay, y el checklist antes de commitear. |
| `ergosanitas-laravel` | Propia (2026-09-05) | Automática | PHP 8.2 / Laravel 11 en este repo: contenedor, skeleton slim, autenticación triple, Eloquent frente a Query Builder, PHPUnit y Pint, refactors seguros. |
| `ergosanitas-mysql` | Propia (2026-09-05) | Automática | MySQL 5.7 (sin CTE ni funciones de ventana): queries, EXPLAIN, índices, SP, migraciones, relaciones sin foreign key. |
| `ergo-docs` | Propia (2026-09-07) | Automática | Mantener `docs/openapi.yaml`, los cuatro Mermaid y los diagramas archify, con el checklist de validación. |
| `ergosanitas-api` | Propia (2026-11-10, sin commit) | Automática | Diseñar o cambiar endpoints sin romper a los clientes: sobres, consumidores y cierre en `openapi.yaml`. |
| `ergosanitas-architecture` | Propia (2026-11-10, sin commit) | Automática | Análisis de impacto con graphify y `docs/` antes de un cambio estructural. |
| `ergosanitas-security` | Propia (2026-11-10, sin commit) | Automática | Auditoría pasiva: auth, autorización por perfil, secretos, uploads, dependencias y Ley 21.719. |
| `ergosanitas-testing` | Propia (2026-11-10, sin commit) | Automática | Tests PHPUnit 11 sin tocar la MySQL remota: patrón SQLite forzado, `OpenAI::fake()` y el filtro del CI. |
| `spec-ergo-back` | Propia (2026-11-09), envuelve `/spec` | **Solo manual**: `/spec-ergo-back <descripción \| NN-slug>` | Diseñar una spec con las convenciones del repo (sobre de respuesta, provider, perfiles 3/6, fase `ergo-docs`, consumidores). Con una spec existente entra en modo revisión. Nunca la aprueba. |
| `spec-impl-ergo-back` | Propia (2026-11-09; antes `spec-impl-ergo`), envuelve `/spec-impl` | **Solo manual**: `/spec-impl-ergo-back <NN-slug>` | Implementar una spec `Aprobado`: crea la rama, ejecuta paso a paso con pausas, revisión por agente y cierre de documentación. |
| `spec-verify-ergo-backend` | Propia (2026-11-10, sin commit) | **Solo manual**: `/spec-verify-ergo-backend <NN-slug \| todas>` | Verificar specs implementadas y dejar evidencia por criterio (ver [Verificador](#verificador-de-specs-spec-verify-ergo-backend)). |
| `spec` | [Klerith/fernando-skills](https://github.com/Klerith/fernando-skills): `npx skills@latest add Klerith/fernando-skills` (2026-09-05) | **Solo manual**: `/spec <descripción>` | Diseño spec-driven genérico; nunca escribe código. |
| `spec-impl` | [Klerith/fernando-skills](https://github.com/Klerith/fernando-skills), mismo comando | **Solo manual**: `/spec-impl <NN-slug>` | Implementación genérica de una spec aprobada. |
| `archify` | [tt-a1i/archify](https://github.com/tt-a1i/archify) v2.17, fijada en `skills-lock.json` (2026-09-07) | Automática | Diagramas explorables en HTML/SVG de `docs/diagramas/`. El `.html` es generado; la fuente es su `.json` hermano. |
| `graphify` | `graphify install --project` (2026-11-10, sin commit) | `/graphify` o automática | Construir y consultar el grafo (ver [graphify](#graphify)). |

«Solo manual» quiere decir que la skill declara `disable-model-invocation: true`: Claude no la lanza por su cuenta y hay que escribir el comando.

**Reinstalar las skills importadas:**

```bash
npx skills@latest add Klerith/fernando-skills     # vuelve a traer spec y spec-impl a .agents/skills/
npx skills@latest add tt-a1i/archify              # archify
```

`skills-lock.json` guarda la ruta de origen y el hash de cada una, para saber si cambiaron.

`.claude/skills/spec` y `.claude/skills/spec-impl` son **symlinks** a `.agents/skills/spec*`. Windows sin modo desarrollador no crea symlinks al clonar: si `/spec` no aparece en la sesión, recréalos (PowerShell como administrador):

```powershell
Remove-Item .claude\skills\spec, .claude\skills\spec-impl
New-Item -ItemType Junction -Path .claude\skills\spec      -Target .agents\skills\spec
New-Item -ItemType Junction -Path .claude\skills\spec-impl -Target .agents\skills\spec-impl
```

### Agentes

Viven en `.claude/agents/*.md` (frontmatter `name`, `description`, `tools` + instrucciones) y se escribieron a mano. Documentación: <https://docs.claude.com/en/docs/claude-code/sub-agents>. Se listan con `/agents`.

| Agente | Misión | Herramientas | Skill que carga |
|---|---|---|---|
| `ergosanitas-developer` | Implementar, corregir y probar módulos. El de uso general. | Read, Write, Edit, Glob, Grep, Bash, Skill, AskUserQuestion | `ergosanitas-dev` |
| `ergosanitas-laravel` | Dudas y decisiones de framework y lenguaje sin romper las convenciones. | Las mismas | `ergosanitas-laravel` |
| `ergosanitas-mysql` | Queries, índices, SP y migraciones en MySQL 5.7. | Las mismas | `ergosanitas-mysql` |
| `ergo-docs` | Mantener `docs/` (contrato, diagramas, Postman). Documenta; nunca toca código. | Las mismas | `ergo-docs` + `archify` |
| `spec-verify-ergo-backend` | Verificar specs implementadas con evidencia. No implementa ni corrige, y solo escribe en `specs/`. | Read, Grep, Glob, Bash, PowerShell, Edit, Write, ToolSearch + MCP `context7`, `graphify`, `laravel-boost`, `mysql-server` | `spec-verify-ergo-backend` |

Uso: Claude los elige solo por su `description`, o se piden explícitamente («usa el agente `ergo-docs` para…»). Al cerrar un cambio que toque `routes/`, controladores, servicios, modelos o el esquema, pasa por `ergo-docs`.

**Agente padre `ergo-father`.** Vive **fuera** de este repo, en `D:\ErgoSanitas\Projectos\.claude\agents\ergo-father.md`, con sus skills `ergo-father`, `ergo-contrato-api` y `ergo-fullstack` en `D:\ErgoSanitas\Projectos\.claude\skills\`. Orquesta el back-end, el front-end y la app móvil, y delega en los 12 agentes hijos (estos 5 y los 7 del front). Para que pueda delegar tiene que ser el agente principal:

```bash
cd D:\ErgoSanitas\Projectos
claude --agent ergo-father
```

### Flujo spec-driven

Las features nuevas pasan por una especificación antes del código:

```bash
/spec-ergo-back <descripción>        # diseña una spec nueva → specs/NN-slug.md en Borrador
/spec-ergo-back <NN-slug>            # modo revisión de una spec existente
# (una persona cambia el Estado a Aprobado: nunca el agente)
/spec-impl-ergo-back <NN-slug>       # implementa: rama spec-NN-slug, pasos con pausa, revisión y ergo-docs
/spec-verify-ergo-backend <NN-slug>  # verifica: cada criterio queda ✅/❌/⏸ con evidencia
```

Estado al 2026-11-10:

| Spec | Estado |
|---|---|
| `01-asistente-chat-club` | Aprobado |
| `02-juego-cartas-niveles` | Implementado |
| `03-mantenedor-imagenes-home` | Implementado |
| `04-servicio-cart-flow` | Implementado |
| `05-mantenedor-carrito-compra` | Implementado |
| `06-chat-comercial-ergosanito` | Implementado |

Si durante la implementación una decisión de la spec resulta equivocada, **se corrige en la spec**, no en el código por sorpresa.

### Verificador de specs (`spec-verify-ergo-backend`)

Skill `.claude/skills/spec-verify-ergo-backend/SKILL.md` + agente `.claude/agents/spec-verify-ergo-backend.md`. Comprueba lo que `/spec-impl-ergo-back` dio por implementado y lo deja escrito en la spec.

```bash
/spec-verify-ergo-backend 06-chat-comercial-ergosanito   # una spec
/spec-verify-ergo-backend todas                          # todas, en secuencia
```

| Fase | Herramienta | Qué hace |
|---|---|---|
| 0. Alcance y preparación | `git`, `claude mcp` | Resuelve el argumento, comprueba las herramientas y lee la spec y su contexto. |
| 1. Mapa | graphify | Lista real de clases, métodos y rutas de la spec. |
| 2. Dependencias e impactos | graphify (`path`), `grep` | Consumidores y reglas duras del `CLAUDE.md`. |
| 3. Buenas prácticas | Context7 y Laravel Boost (`search-docs`) | Observaciones por librería y versión. No marcan checks. |
| 4. Criterios | Tests en SQLite, `artisan`, `grep` | Clasifica y ejecuta cada criterio verificable sin red. |
| 5. Esquema | `laravel-boost` y `mysql-server`, solo lectura | Contrasta tablas y columnas con la BD real. |
| 6. Evidencia | Edición de la spec | ✅ / ❌ / ⏸ con su evidencia, más la sección «Estado de verificación». |

Reglas: **no hace peticiones HTTP** (el `.env` apunta a la BD de producción), **no implementa ni corrige código** y **no cambia `Estado:`**.

---

## Problemas frecuentes

| Síntoma | Causa | Solución |
|---|---|---|
| Certificados, logos, WebPay o el login de Google devuelven `null` o fallan tras un deploy | Se cacheó la configuración y los `env()` fuera de `config/` devuelven `null` | `php artisan config:clear` (o `optimize:clear`). Nunca `config:cache`. |
| 500 al subir un certificado o un ECG | Falta la fila `VALOR-ECG` en `params` (`firstOrFail()`) | Insertar la fila en `params`. |
| 500 en `chat-comercial/as-question` | Faltan las tablas `cache` / `cache_locks` (`CACHE_STORE=database`) | `php artisan migrate` (migración `0001_01_01_000001_create_cache_table`). |
| 429 en el chat comercial | Se alcanzó el limitador `throttle:chat-comercial` (20/min, 200/día por IP) | Esperar el `Retry-After`. En local, `php artisan cache:clear`. |
| Una ruta nueva responde con otro controlador | Orden de rutas en `routes/api.php`: una ruta con parámetro captura antes que la específica | Declarar la ruta específica antes que la de `{param}`. |
| Un servicio nuevo no se comporta como singleton | Falta su provider en `bootstrap/providers.php` | Crear `XServiceProvider` y registrarlo. |
| Clase de prompt «equivocada» en producción | Dos archivos de `app/PromptSystem/` declaran la misma clase | Dejar un solo archivo; versionar con git. |
| Un test escribe en la MySQL remota | `phpunit.xml` no fuerza SQLite | Heredar de un `TestCase` que fuerce SQLite (patrón `CartFlowTestCase`). |
| `vendor/bin/pint` cambia cientos de archivos | Se corrió sin argumentos | Revertir con `git restore <archivo>` solo los archivos que no tocaste, y usar `--dirty`. |
| Las imágenes del Home salen sin dominio | Falta `API_PATH_HOME` | Definirla sin barra final. |
| Archivos subidos que desaparecen tras un redeploy en Docker | `dockerHub/` no monta `public/` como volumen | Ver [Despliegue en contenedor](#despliegue-en-contenedor-dockerhub). |
| `WebPayResponse` devuelve un cuerpo vacío | El `catch` graba en `logs_api` y no responde | Revisar la tabla `logs_api`. |
| MCP `laravel-boost`: «no commands defined in the boost namespace» | Falta descubrir paquetes | `php artisan package:discover`. |
| MCP `mysql-server` no conecta en Windows (`EPERM`, timeout de 30 s) | Registrado con `npx -y` | Instalarlo global y registrarlo con `node …/dist/index.js` (ver [Servidores MCP](#servidores-mcp)). |
| MCP `github`: `CONNECTION_CLOSED` | Docker Desktop no está corriendo | Abrir Docker Desktop y reiniciar la sesión. |
| MCP `graphify` falla al arrancar | No existe `graphify-out/graph.json` | `graphify extract . --code-only --no-label` + `graphify cluster-only . --no-label`. |
| `graphify` no se encuentra | `~/.local/bin` no está en el `PATH` | Agregarlo al `PATH` de usuario y abrir otra terminal. |
| `/spec` o `/spec-impl` no aparecen en Claude Code | Los symlinks de `.claude/skills/` no se crearon al clonar en Windows | Recrearlos como junction (ver [Skills](#skills)). |
| Git Bash cambia argumentos como `/c` en `claude mcp add` | Conversión de rutas de MSYS | `export MSYS_NO_PATHCONV=1`. |
| `composer install` falla por una extensión | Extensión de PHP desactivada en XAMPP | Activarla en `C:\xampp\php\php.ini` (ver [Requisitos](#requisitos)). |

---

## Cómo contribuir

1. **Rama por trabajo**, nunca directo sobre `main`. Las de spec siguen el patrón `spec-NN-slug` y las crea `/spec-impl-ergo-back`.
2. **Asunto del commit en Conventional Commits** (`feat`, `fix`, `docs`, `chore`…): define el bump de versión en la CI (ver [Versión semántica](#versión-semántica)).
3. **Formatea solo lo tuyo**: `vendor/bin/pint --dirty` o rutas explícitas. Nunca `pint` sin argumentos.
4. **Tests**: `php artisan test --filter="CartFlow|ChatComercial"` en verde (es lo que corre la CI). Un dominio nuevo copia el patrón de `CartFlowTestCase` / `ChatComercialTestCase`.
5. **Contrato y diagramas a mano**: al tocar `routes/api.php`, actualiza `docs/openapi.yaml` en el mismo commit, contrasta con `php artisan route:list --json`, regenera Postman (`php docs/postman/generar-coleccion.php` + `verificar-cobertura.php`) y actualiza el diagrama afectado. El agente `ergo-docs` lo hace.
6. **No cambies el sobre de respuesta** de un endpoint que ya consumen el front o la app móvil sin revisar sus consumidores.
7. **Datos clínicos (Ley 21.719)**: nada de `DELETE`, `TRUNCATE` ni `DROP` sobre datos productivos, migraciones aditivas y sin datos reales en desarrollo, pruebas ni herramientas de IA.
8. **Tras cambiar código**: `graphify update .`.

---

## Máquina nueva desde cero (checklist)

Para dejar otra PC (o esta misma tras formatear) igual que hoy. Ve marcando cada paso.

**A. Herramientas base** (ver [Requisitos](#requisitos))

- [ ] XAMPP con PHP 8.2 y las extensiones activadas → `php -v`, `php -m`
- [ ] Composer 2 → `composer --version`
- [ ] Git → `git --version`
- [ ] Node.js 22 (nvm-windows) → `node -v`
- [ ] (Opcional) Docker Desktop → `docker --version`

**B. El proyecto**

```bash
git clone https://github.com/Nruz-app/laravel-back-end-ergosanitas-app.git ergosanitas-app
cd ergosanitas-app
composer install
php artisan package:discover
cp .env.example .env
php artisan key:generate
```

- [ ] Completar `.env`: `DB_*`, `OPENAI_API_KEY`, `WEBPAY_URL|ID|SECRET|RETURN`, `GOOGLE_CLIENT_ID|SECRET`, `JWT_SECRET`, `JWT_ALGO`, `API_PATH_CER`, `API_PATH_LOGO` (no viene en `.env.example`), `API_PATH_HOME` y, si hacen falta, `WEBPAY_CART_FLOW_RETURN` y `CART_FLOW_FRONT_URL`. Los valores **no** están en ningún documento: pídelos o cópialos del `.env` de la otra PC, nunca por un canal público.
- [ ] `php artisan test --filter="CartFlow|ChatComercial"` en verde.
- [ ] `php artisan serve` → `http://localhost:8000/api/...` responde.

**C. Claude Code**

- [ ] `irm https://claude.ai/install.ps1 | iex` y `claude` para iniciar sesión.
- [ ] Abrir `claude` dentro de `ergosanitas-app` y aceptar la confianza de la carpeta.

**D. graphify**

```powershell
irm https://astral.sh/uv/install.ps1 | iex
uv tool install "graphifyy[mcp]==0.9.105" --force
graphify extract . --code-only --no-label
graphify cluster-only . --no-label
```

- [ ] Crear `.claude/settings.local.json` con los hooks de [Mapa de archivos](#mapa-de-archivos-de-ia-del-repositorio).

**E. MCP** (comandos en [Servidores MCP](#servidores-mcp))

- [ ] `laravel-boost`, `mysql-server` (instalado global), `graphify`, `context7`, `filesystem` y `github` con `-s local`.
- [ ] `snyk` con `-s user`.
- [ ] `claude mcp list` → todos en ✔ Connected (`github` solo con Docker Desktop corriendo).

**F. Plugins**

```bash
/plugin install code-simplifier@claude-plugins-official
```

**G. Skills importadas**

- [ ] Si `/spec` no aparece: recrear los enlaces `.claude/skills/spec` y `spec-impl` (ver [Skills](#skills)).

**H. Comprobación final**

- [ ] `/agents` muestra los 5 agentes.
- [ ] Al escribir `/` aparecen `spec-ergo-back`, `spec-impl-ergo-back`, `spec-verify-ergo-backend`, `spec`, `spec-impl` y `graphify`.
- [ ] `/mcp` en verde.
- [ ] `graphify query "CertificadoService"` responde.

---

## Historial de lo instalado

Registro de qué se instaló y cuándo, para reconstruir el porqué. Las fechas salen del primer commit de cada archivo (`git log --diff-filter=A`). «Sin commit» indica que está en la rama `chore/laravel-boost`, pendiente de commit. **Agrega una fila cada vez que instales o quites algo.**

| Fecha | Qué | Cómo | Notas |
|---|---|---|---|
| — | PHP 8.2.12 (XAMPP) · Composer 2.8.5 · Node 22.23.4 · Git 2.46.0 · Docker 29.7.2 | Instaladores oficiales | Base del proyecto. |
| — | Claude Code (2.1.322 al 2026-11-10) | Instalador nativo | |
| 2026-09-05 | Agentes `ergosanitas-developer`, `-laravel`, `-mysql` y sus skills | Escritos a mano en `.claude/` | Llegaron con el asistente de chat por club. |
| 2026-09-05 | Skills `spec` / `spec-impl` | `npx skills@latest add Klerith/fernando-skills` | Hash en `skills-lock.json`; symlinks desde `.claude/skills/`. |
| 2026-09-05 | Contrato `docs/openapi.yaml` y `redocly.yaml` | A mano | |
| 2026-09-07 | Agente y skill `ergo-docs`; skill `archify` y diagramas explorables | A mano · `tt-a1i/archify` | |
| 2026-11-04 | Colección Postman y entornos | `php docs/postman/generar-coleccion.php` | Generada desde `openapi.yaml`. |
| 2026-11-09 | Skills `spec-ergo-back` / `spec-impl-ergo-back` | Escritas a mano | Envoltorios de `/spec` y `/spec-impl`; `spec-impl-ergo` pasó a llamarse `spec-impl-ergo-back`. |
| 2026-11-10 | Laravel Boost 2.10 + `config/boost.php`; Laravel 11.24.0 → 11.57.1 | `composer require --dev laravel/boost -W` | Sin commit. Herramientas con filas o logs excluidas. |
| 2026-11-10 | MCP `laravel-boost` y `mysql-server` (solo lectura, autorizado por Gerencia) | `claude mcp add -s local …` | `mysql-server` instalado global por el problema de `npx` en Windows. |
| 2026-11-10 | graphify 0.9.105, skill, `.claude/CLAUDE.md`, `.graphifyignore`, hooks y MCP | `uv tool install "graphifyy[mcp]==0.9.105"` + `graphify install --project` | Sin commit. Hooks movidos a `settings.local.json`; grafo solo con AST. |
| 2026-11-10 | Skills `ergosanitas-api`, `-architecture`, `-security`, `-testing` | Escritas a mano a partir de plantillas genéricas | Sin commit. Reescritas contra este repo. |
| 2026-11-10 | Skill y agente `spec-verify-ergo-backend` | Escritos a mano | Sin commit. Tercer paso del flujo spec-driven. |
| 2026-11-10 | Este README ampliado (enlaces, entorno IA, checklist, historial) | — | — |
