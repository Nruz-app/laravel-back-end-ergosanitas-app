# Colección Postman de la API de Ergosanitas

Colección **Postman v2.1** con las **123 operaciones** de la API, generada desde
[`docs/openapi.yaml`](../openapi.yaml). Cubre el 100 % de las rutas que devuelve
`php artisan route:list` bajo el prefijo `/api`.

| Archivo | Qué es |
|---|---|
| `Ergosanitas-API.postman_collection.json` | La colección: 123 requests en 19 carpetas, una por dominio |
| `Ergosanitas-Local.postman_environment.json` | Entorno `http://127.0.0.1:8000/api` (`php artisan serve`) |
| `Ergosanitas-Docker.postman_environment.json` | Entorno `http://localhost:6162/api` (`docker compose up`) |
| `Ergosanitas-Prod.postman_environment.json` | Entorno `https://api.ergosanitas.com/api` — **ajusta el host real** |
| `generar-coleccion.php` | Generador: convierte el contrato OpenAPI en la colección y los entornos |
| `verificar-cobertura.php` | Compara la colección con `php artisan route:list --json` |

## Importar

En Postman: **Import → Files** y selecciona el `.json` de la colección más el entorno que
uses. Luego elige ese entorno en el selector de arriba a la derecha.

Con Newman (línea de comandos):

```bash
npx newman run docs/postman/Ergosanitas-API.postman_collection.json \
  -e docs/postman/Ergosanitas-Local.postman_environment.json \
  --folder "Utilidades"
```

## Variables

La colección trae valores por defecto; el entorno los pisa. Los ejemplos son **ficticios**
(salen del contrato), así que apúntalos a datos reales de tu base antes de probar los
endpoints que consultan por identificador.

| Variable | Valor por defecto | Para qué |
|---|---|---|
| `baseUrl` | `http://127.0.0.1:8000/api` | Host + prefijo `/api` |
| `user_email` | `club.deportivo@ergosanitas.com` | Email del usuario; define el `perfiles_id` y con él el filtrado |
| `rut_paciente` | `21345678-9` | RUT sin puntos y con guion; es la clave de negocio que cruza todos los dominios |
| `id_paciente` | `1842` | Id de `chequeo_cardiovascular` (el nombre dice "paciente", pero es el id del chequeo) |
| `perfil` | `3` | Id de perfil: 1 admin, 2 tester, 3 club, 5 paciente, 6 médico |
| `name` | `Chequeo Cardiovascular Deportivo` | Nombre del servicio en `GET /servicios/{name}` |
| `id` | `5` | Id de `home_imagenes` en las rutas `home-imagenes/{id}` |
| `admin_email` | `admin@ergosanitas.com` | Email de perfil Administrador para `cart-flow/admin/*` y la visibilidad del catálogo. **Ponle uno real** |
| `sku` | `PERF-BASICO` | Producto que se agrega al carro |
| `comuna_id`, `uuid`, `paciente_id`, `paciente2_id`, `linea_id`, `fecha`, `bloque_id`, `numero`, `t` | (los guarda la colección) | Ids del carro: cada request los guarda en sus *Tests* para el siguiente |
| `orden_id`, `orden_expires`, `orden_signature`, `cotizacion_id` | (los guarda la colección) | Orden médica y cotización de la carpeta de administración |

## Carro de servicios: correr el flujo completo

Las carpetas **Carro de servicios** y **Carro de servicios — administración** están ordenadas para ejecutarse de
corrido en el *Runner* de Postman o con Newman. Cada request guarda en variables lo que necesita el siguiente
(`uuid`, `paciente_id`, `fecha`, `bloque_id`, `numero`, `t`…). Se guardan en el entorno activo, que tiene prioridad,
y también en la colección.

```bash
npx newman run docs/postman/Ergosanitas-API.postman_collection.json \
  --folder "Carro de servicios" --folder "Carro de servicios — administración" \
  --env-var "baseUrl=http://127.0.0.1:8000/api" --env-var "admin_email=<un Administrador real>"
```

Orden de la carpeta pública:
1. comunas
2. catálogo
3. producto
4. crear carro
5. ver carro
6. plan
7. agregar paciente
8. datos del paciente
9. quitar el segundo paciente
10. agregar línea
11. cantidad
12. bloques
13. checkout (transferencia)
14. consultar pedido
15. Webpay
16. retorno GET
17. retorno POST
18. cotización
19. quitar línea
20. visibilidad

Respuestas que **son las esperadas** en una corrida limpia:

| Request | Respuesta | Por qué |
| --- | --- | --- |
| Inicia el pago Webpay | 409 | El checkout de la colección es por transferencia: Webpay solo aplica a pedidos `webpay` |
| Retorno de Transbank (GET/POST) | 302 | Sin `token_ws` redirige al front con `pago=error`. La colección no sigue la redirección |
| Quita una línea | 409 | Corre después del checkout, cuando el carro ya está convertido |
| Enlace a la orden médica / descarga | 404 / 403 | Solo hay orden si el producto la pide (p. ej. `sku = LAB-302048` con archivo en el checkout) |

Probado el 2026-11-05 contra el back local sobre SQLite de demo: **29 requests, 0 fallos**.

> Esta carpeta **escribe**: crea carros, pedidos, cotizaciones e historial en la base del entorno. No la corras contra
> producción. Para Webpay real usa las credenciales de integración y la tarjeta de prueba del README de la raíz.

## Antes de ejecutar, ten en cuenta

- **Ninguna ruta de negocio lleva middleware de autenticación.** `user_email` y
  `perfiles_id` viajan como parámetros del request, así que el filtrado por perfil es una
  regla de presentación, no un control de acceso. La única ruta autenticada es
  `GET /user` (`auth:sanctum`), que responderá 401 sin sesión ni token.
- **Hay endpoints que facturan.** `POST /certificado/save-url`, `POST /carga-masiva-ecg` y
  `POST /electro-cardiograma/save` llaman a `SP_pago_mensual(..., 'ADD')`: cada ejecución
  suma un cargo al mes del club, sin idempotencia. No los repitas contra producción.
  `POST /estadisticas/pago-mensual` y `POST /estadisticas/delete-pago-mensual` escriben
  directamente sobre `pago_mensual`.
- **Hay endpoints que gastan tokens de OpenAI**: toda la carpeta *Asistentes IA*,
  `POST /bioimpedancia/form-upload` y `GET /bioimpedancia/pdfRut/{rut}`.
- **Los `multipart/form-data` no traen archivo**: hay que seleccionarlo a mano en la
  pestaña *Body*. En `POST /carga-masiva-ecg` el campo es `files[]` y admite varias filas,
  una por archivo, nombradas `{rut}.pdf`.
- **Las escrituras de *Home comercial* exigen un `user_email` de perfil Administrador**
  (403 si no). El de ejemplo es ficticio: ponle uno real. `DELETE /home-imagenes/{id}`
  borra la fila **y el archivo**; para probar sin perder nada usa `PUT .../activo`.
- **`POST /email/reserva-hora` envía correo de verdad**, con copia fija a
  `ergosanitas@gmail.com`.
- **`GET /transbank/web-pay-response` llama a Transbank** con el `token_ws` de la query.
- Cada request lleva en su descripción el detalle del contrato: qué hace, sus efectos
  secundarios y la lista de códigos de respuesta documentados.

## Regenerar

La colección y los entornos son **artefactos generados**: no los edites a mano, el
siguiente `generar-coleccion.php` los pisa. La fuente es `docs/openapi.yaml`.

```bash
php docs/postman/generar-coleccion.php    # regenera colección + 3 entornos
php docs/postman/verificar-cobertura.php  # contrasta con php artisan route:list --json
```

`generar-coleccion.php` usa `Symfony\Component\Yaml` (ya viene con Laravel) y no necesita
Node. De cada operación toma el `summary` como nombre, el `description` y la tabla de
respuestas como documentación, el primer `examples[].value` como cuerpo JSON, y convierte
`{param}` en `{{param}}` registrando cada uno como variable de colección.

**Extensión `x-postman`.** Una operación del contrato puede llevar `x-postman` para que la colección sea ejecutable
sin cambiar lo que muestra Swagger. Las claves están documentadas al inicio de `generar-coleccion.php`:
- `orden`: orden dentro de la carpeta.
- `vars`: renombra un `{param}` de ruta, p. ej. `id` → `linea_id`.
- `query`, `body` y `form`: valores con `{{variables}}`.
- `guardar`: script de *Tests* que guarda valores de la respuesta en variables.
- `redirecciones: false`: no seguir el 302.

En la raíz del contrato, `x-postman-variables` declara las variables extra. Hoy la usan solo las dos carpetas del
carro.

Como el contrato se mantiene a mano, el flujo al tocar una ruta es: `routes/api.php` →
`docs/openapi.yaml` → regenerar aquí, todo en el mismo commit.
