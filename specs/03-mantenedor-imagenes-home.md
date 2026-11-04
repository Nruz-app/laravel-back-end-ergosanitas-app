# SPEC 03 — Mantenedor de imágenes del Home (`home_imagenes`)

> **Estado:** Implementado
> **Depende de:** —
> **Fecha:** 2026-11-03
> **Consumidores:** Spec 04 de `home-ergo` (lectura pública) y Spec 02 de `chequeo-administrador` (mantenedor), ambas en `Front-end/ergosanitas-app/specs/`.
> **Objetivo:** Guardar en base de datos las imágenes de promociones, alianzas y galería del Home comercial y exponer endpoints para listarlas, subirlas, editarlas, ocultarlas y borrarlas.

---

## 1 — Por qué existe esta spec

El Home comercial del front (`src/home-ergo/`) lee sus imágenes de dos JSON compilados en el
bundle: `home-promociones.json` (20 entradas, con `destacado` `true`/`false` para promociones o
alianzas) y `home-galeria.json` (4 entradas). Agregar o quitar una promoción exige editar el JSON,
hacer commit y esperar el despliegue por FTP.

El Administrador necesita hacerlo desde la aplicación. Para eso las imágenes tienen que vivir en
el back: una tabla con los metadatos y una carpeta pública con los archivos.

Se decidió con el usuario que el Home lea **solo de la API**, sin respaldo en JSON, y que el
mantenedor cubra **solo imágenes**. Los videos (139 MB, subidos a mano por FTP) quedan fuera.

### 1.1 Lo que se verificó en el código

| Hallazgo | Consecuencia |
| --- | --- |
| `Auth/UserController::FileUpload` sube el logo con `$file->move(public_path('Logo'), ...)` y arma la URL con `env('API_PATH_LOGO')` | Se sigue el mismo patrón: carpeta `public/HomeImagenes` y una variable nueva, `API_PATH_HOME`. |
| `env()` fuera de `config/` devuelve `null` con `config:cache`. El repo lo esquiva no cacheando nunca (`entrypoint.sh` hace `config:clear`) | Igual se lee con `config('services.home_imagenes.url')`, y esa clave toma `API_PATH_HOME` en `config/services.php`. Así no se suma una lectura más de `env()` a la lista de trampas. |
| `dockerHub/docker-compose.yml` monta volúmenes para `storage/` y `bootstrap/cache`, pero no para `public/` | **Corregido durante la implementación (D6):** producción **no** corre en ese Docker. Corre en el hosting compartido, en `https://ergosanitas.com/BackEnd/public/` (así lo confirman `API_PATH_LOGO` y `API_PATH_CER`, y el humano). Ese Docker tampoco serviría imágenes: su nginx no monta `/var/www/public`. No se toca Docker. |
| El back **no autentica** ninguna ruta (`auth:sanctum` comentado) | Un `DELETE` público permitiría a cualquiera borrar el Home. Ver §7 y la decisión D3. |
| `JuegoCartasController` responde `{success, message, data}` / `{success:false, message, error}` | Los endpoints nuevos usan ese sobre, el más extendido entre los controladores recientes. |
| La Spec 02 siembra sus tablas en el `up()` de la migración | Aquí se hace igual con las 24 entradas actuales de los JSON. |

---

## 2 — Alcance

**Dentro:**

- Migración `create_home_imagenes_table`, que siembra 24 filas en el `up()`: 11 `promocion`, 9 `alianza` y 4 `galeria`. La entrada que hoy tiene `activo: false` se siembra con `activo = 0`.
- Copia de los 24 archivos desde `Front-end/ergosanitas-app/public/home-ergo/img/` a `resources/home-imagenes-semilla/` del back, con el mismo nombre. Desde ahí se siembra `public/HomeImagenes/` **una vez**, a mano, en local y en el hosting.
- Modelo `HomeImagen`, `HomeImagenService`, `HomeImagenServiceProvider` (registrado en `bootstrap/providers.php`) y `HomeImagenController`, siguiendo el vertical slice de la Spec 02.
- Seis endpoints (§3.3) y la validación de la subida.
- Variable `API_PATH_HOME` en `.env.example` y en `config/services.php`.
- Puesta en marcha en el hosting (D6, paso 8): crear `public/HomeImagenes/`, subir los 24 archivos semilla una sola vez y definir `API_PATH_HOME`. Queda documentada en `CLAUDE.md`.
- Actualización de `docs/openapi.yaml`, `docs/modelo-datos.md`, `README.md` y `CLAUDE.md`.

**Fuera de alcance:**

- Videos, hero y logo. Siguen en el front, y los `poster` de los videos siguen apuntando a `/home-ergo/img/`. La única `info-*` que entra es `info-holter-instrucciones.jpg`, porque ya estaba en `home-promociones.json` como alianza inactiva y la siembra replica el JSON tal cual.
- Borrar las imágenes de `public/home-ergo/img/` del front: los posters de video las usan.
- Redimensionar o comprimir en el servidor. Se valida el tamaño, no se transforma.
- Autenticación real con Sanctum. Ver D3.
- Tests automatizados: `phpunit.xml` apunta al MySQL remoto.

---

## 3 — Modelo de datos y contrato

### 3.1 Tabla `home_imagenes`

| Columna | Tipo | Nota |
| --- | --- | --- |
| `id` | `bigint` PK | |
| `seccion` | `enum('promocion','alianza','galeria')` | Reemplaza a `destacado`: `true` → `promocion`, `false` → `alianza`. |
| `archivo` | `varchar(255)` | Solo el nombre del archivo (`promo-bioimpedancia-01.png`). La URL se arma al responder. |
| `alt` | `varchar(255)` NOT NULL | Obligatorio: accesibilidad y SEO, como en el front. |
| `caption` | `varchar(150)` | Texto bajo la imagen. También se usa en el mensaje de WhatsApp de la promo. |
| `activo` | `tinyint(1)` default `1` | Ocultar sin borrar. |
| `orden` | `int unsigned` default `0` | Orden dentro de la sección. |
| `created_at`, `updated_at` | `timestamp` | |

Índice `(seccion, activo, orden)`.

Se guarda `archivo` y no la URL completa. Así un cambio de dominio se resuelve en el `.env`, sin
un `UPDATE` masivo.

### 3.2 Forma de una imagen en la respuesta

```json
{
  "id": 7,
  "seccion": "promocion",
  "src": "https://<API_PATH_HOME>/promo-bioimpedancia-01.png",
  "alt": "Oferta de bioimpedancia de Ergo SaniTas",
  "caption": "Bioimpedancia",
  "activo": true,
  "orden": 5
}
```

`activo` sale como booleano (cast en el modelo) y `src` es la URL absoluta.

### 3.3 Endpoints

| Método | Ruta | Cuerpo | Respuesta `data` |
| --- | --- | --- | --- |
| `GET` | `api/home-imagenes` | — | Solo `activo = 1`, ordenadas por `seccion`, `orden`, `id`. Público. |
| `GET` | `api/home-imagenes/admin` | — | Todas, activas e inactivas, en el mismo orden. |
| `POST` | `api/home-imagenes` | multipart: `file`, `seccion`, `alt`, `caption?`, `orden?`, `user_email` | La imagen creada. `201`. |
| `PUT` | `api/home-imagenes/{id}` | JSON: `seccion`, `alt`, `caption`, `orden`, `user_email` | La imagen actualizada. |
| `PUT` | `api/home-imagenes/{id}/activo` | JSON: `activo` (bool), `user_email` | La imagen actualizada. |
| `DELETE` | `api/home-imagenes/{id}` | query `user_email` | `null`. Borra la fila y el archivo. |

- La ruta literal `home-imagenes/admin` se declara **antes** de las rutas con `{id}`, como en la Spec 02.
- Sobre: `{success, message, data}`. Errores: `422` de validación, `403` si `user_email` no es Administrador (D3), `404` si no existe el id y `500` con `{success:false, message, error}`.
- Validación del `POST`: `file` obligatorio, `mimes:jpg,jpeg,png,webp`, `max:5120` (5 MB). `seccion` en el enum. `alt` obligatorio, máximo 255. `caption` máximo 150.
- Nombre del archivo subido: `{seccion}-{Str::slug(caption o alt)}-{uniqid()}.{ext}`. Nunca se usa el nombre que envía el cliente.
- Si `orden` no viene, se usa `MAX(orden) + 1` de la sección.
- El `DELETE` borra primero la fila y después el archivo. Si el archivo no existe, la operación responde éxito igual y deja un `Log::warning`.

---

## 4 — Plan de implementación

1. Migración `database/migrations/2026_11_03_120000_create_home_imagenes_table.php`, con la siembra de las 24 filas en `up()`. Copiar los 24 archivos a `resources/home-imagenes-semilla/` y, en local, a `public/HomeImagenes/`. Prueba: `php artisan migrate` y contar las filas.
2. `config/services.php` con la clave `home_imagenes.url` ← `API_PATH_HOME`, y la variable en `.env.example`.
3. `app/Models/HomeImagen.php` con `$fillable`, el cast de `activo` y el accessor `src`.
4. `app/Services/HomeImagenService.php` (listar, crear, actualizar, cambiar activo, borrar) y `app/Providers/HomeImagenServiceProvider.php`, registrado en `bootstrap/providers.php`.
5. `app/Http/Controllers/HomeImagenController.php`, con la validación y la comprobación de perfil (D3).
6. Rutas en `routes/api.php`: la literal `admin` antes de `{id}`. Prueba con Postman o `curl`: los seis endpoints.
7. Documentación con el agente `ergo-docs`: `docs/openapi.yaml`, `docs/modelo-datos.md`, la colección Postman regenerada con `php docs/postman/generar-coleccion.php`, `README.md` y `CLAUDE.md` (sobre usado, carpeta pública, variable nueva y la puesta en marcha en el hosting del paso 8).
8. **Puesta en marcha en el hosting** (corregido; antes era un volumen Docker, ver D6). Documentar en `CLAUDE.md` el procedimiento de una sola vez:
   - crear `BackEnd/public/HomeImagenes/` en el hosting;
   - subir ahí los 24 archivos de `resources/home-imagenes-semilla/`;
   - definir `API_PATH_HOME=https://ergosanitas.com/BackEnd/public/HomeImagenes` en el `.env` del hosting;
   - confirmar que el método de despliegue del back **no borra** `public/HomeImagenes/` (si usa un `mirror`, sin `--delete` o excluyendo la carpeta).

   La migración ya corrió contra la base remota en el paso 1.

---

## 5 — Criterios de aceptación

- [ ] Tras `php artisan migrate`, `home_imagenes` tiene 24 filas: 11 `promocion`, 9 `alianza` y 4 `galeria`.
- [ ] `GET api/home-imagenes` devuelve 23 filas: la inactiva no sale.
- [ ] Cada `src` de la respuesta abre la imagen en el navegador.
- [ ] `GET api/home-imagenes/admin` devuelve las 24.
- [ ] `POST` con un PNG de 1 MB crea la fila, guarda el archivo en `public/HomeImagenes/` y responde `201`.
- [ ] `POST` con un PDF o con un archivo de 6 MB responde `422` y no crea fila ni archivo.
- [ ] `POST` sin `alt` responde `422`.
- [ ] `PUT .../activo` con `false` hace que la imagen deje de salir en `GET api/home-imagenes`.
- [ ] `PUT` cambia `caption`, `orden` y `seccion`, y el cambio se ve en el `GET`.
- [ ] `DELETE` elimina la fila y el archivo físico.
- [ ] Con un `user_email` que no es Administrador, `POST`, los dos `PUT` y `DELETE` responden `403`.
- [ ] `src` se arma desde `config('services.home_imagenes.url')`, y `grep -rn "API_PATH_HOME" app/` sale vacío.
- [ ] En el hosting, `https://ergosanitas.com/BackEnd/public/HomeImagenes/promo-mes-corazon-01.png` responde `200` con la imagen.
- [ ] En el hosting, `GET api/home-imagenes` devuelve `src` con el dominio de producción.
- [ ] Una imagen subida en producción sigue disponible después del siguiente despliegue del back.
- [ ] `CLAUDE.md` documenta el procedimiento del paso 8.
- [ ] `php artisan route:list` muestra `home-imagenes/admin` antes de `home-imagenes/{id}`.
- [ ] `vendor/bin/pint --dirty` queda limpio.

---

## 6 — Decisiones tomadas y descartadas

- **D1 · Sí:** guardar `archivo` y armar `src` al responder. Cambiar de dominio es un cambio de `.env`.
- **D1 · No:** guardar la URL absoluta, como hace `user_logo`. Ata la base al dominio.
- **D2 · Sí:** `seccion` como enum de tres valores. Expresa «¿dónde?» mejor que `destacado` y deja meter la galería en la misma tabla.
- **D2 · No:** una tabla por sección. Son el mismo dato con un campo distinto.
- **D3 · Sí (pendiente de confirmar por el humano):** las escrituras exigen un `user_email` de un usuario con perfil Administrador, comprobado en `users_metadata` → `perfiles`. No es autenticación: quien conozca un email de administrador puede falsificarlo. Pero cierra la puerta a un `DELETE` anónimo, y el front ya conoce el email del usuario logueado.
- **D3 · No:** activar Sanctum solo para estas rutas. El front no envía token ni header `Authorization`, y estrenar autenticación es una spec aparte.
- **D4 · Sí:** `public_path('HomeImagenes')` con `move()`, igual que el logo. Es el patrón que ya funciona en el hosting.
- **D4 · No:** `Storage::disk('public')`. Exige el enlace `storage:link`, que no consta en el hosting compartido.
- **D5 · Sí:** sobre `{success, message, data}`, el de `JuegoCartasController`.
- **D7 · Sí:** `PUT .../{id}/activo` y no `PATCH`. El `ApiAdapter` del front no tiene `patch`, y agregarlo tocaría `src/common/`, del que dependen 64 archivos.
- **D6 · Sí (corregida el 2026-11-03, durante el paso 8):** producción es el hosting compartido, no Docker. `public/HomeImagenes/` persiste igual que `Logo` y `Certificado`: basta con sembrarla una vez y que el despliegue no la borre.
- **D6 · No (decisión original, descartada):** volumen Docker con siembra por `cp -n` en `entrypoint.sh`. Partía de que producción corría en `dockerHub/docker-compose.yml`, y no es así. Además ese nginx no monta `/var/www/public` y la ruta escrita (`/var/www/html`) era incorrecta: el contenedor trabaja en `/var/www`.
- **D6 · No:** arreglar aquí que el Docker de `dockerHub/` no sirva archivos estáticos (afecta también a `Logo` y `Certificado`). Otra spec, si ese Docker llega a usarse.

---

## 7 — Riesgos identificados

| Riesgo | Mitigación |
| --- | --- |
| Escrituras sin autenticación real | D3 reduce el riesgo, pero no lo elimina. Queda escrito en `CLAUDE.md` como deuda. |
| `upload_max_filesize` o `post_max_size` del hosting por debajo de 5 MB | Verificar con `phpinfo()` en producción. Si es menor, se baja `max:` en la validación. |
| El despliegue del back al hosting borra `public/HomeImagenes/` | Paso 8: confirmar que el método de subida no borra esa carpeta. Hay un criterio que lo prueba en el siguiente despliegue. |
| Falta `API_PATH_HOME` en el `.env` del hosting | Cada `src` sale relativo y el Home no muestra imágenes. Es un paso explícito del 8. |
| Resuelto: «el servidor de producción no usa `dockerHub/docker-compose.yml`» | Confirmado con el humano: producción es el hosting (D6 corregida). |
| El Home queda vacío si la API cae (sin respaldo JSON, por decisión del usuario) | El front oculta la sección afectada (Spec 04 de `home-ergo`). |
| Borrar la fila deja el archivo huérfano si falla `unlink` | Se registra un `Log::warning`. Un huérfano no rompe nada. |

---

## 8 — Lo que **no** entra en esta spec

- Videos, hero, logo e imágenes `info-*`.
- Autenticación con Sanctum.
- Recorte, compresión o conversión de formato en el servidor.
- Reordenar con arrastrar y soltar en un solo endpoint: el orden se edita imagen por imagen con `PUT`.
- Tests automatizados.
