# SPEC 04 — Carro de servicios `servicio-cart-flow` (`cf_*`)

> **Estado:** Implementado
> **Depende de:** —
> **Fecha:** 2026-11-04
> **Consumidores:** Spec 01 de `servicio-cart-flow` en `Front-end/ergosanitas-app/specs/` (página pública `/servicios`).
> **Objetivo:** Reemplazar el flujo «un servicio → una hora → Webpay» por un carro con catálogo completo (laboratorio, servicios, eventos), comunas y zonas, varios pacientes por dirección, agenda por bloques con cupo y pago por Webpay, transferencia o efectivo, con el total calculado siempre en el servidor.

---

## 1 — Por qué existe esta spec

El dueño entregó la especificación de negocio `ERGO_SANITAS_SERVICIOS_Y_CARRO.md` (Parte B) junto con el catálogo
exportado del Excel maestro: 245 productos, 50 comunas en 3 zonas, componentes de packs y tramos de operativos.
El flujo actual no puede soportarla: solo admite un servicio por reserva, no conoce comunas ni zonas y el cobro es
inseguro.

### 1.1 Lo que se verificó en el código

| Hallazgo | Consecuencia |
| --- | --- |
| `WebPayController::WebPayRequest` cobra el `monto` que manda el cliente | El flujo nuevo calcula el total en el servidor y Webpay cobra `cf_pedidos.total`. |
| `buy_order = Ymd + nombre del servicio`, no es único | `buy_order` nuevo es único por intento (`CF` + id del pago + sufijo aleatorio). |
| `WebPayResponse` no valida `status` ni `response_code` y marca pagada la **primera** reserva del RUT | El retorno nuevo valida `AUTHORIZED`, `response_code = 0` y monto, y marca el pedido por su id. |
| `agenda_horas.fecha_reserva_paciente` es texto libre, sin cupos | Tabla `cf_bloques_agenda` con cupo por bloque y conteo de pedidos activos. |
| `servicios` tiene 4 columnas y no distingue laboratorio de servicio | Tabla nueva `cf_productos` con los 35 campos del catálogo. |
| Front y app móvil consumen `servicios`, `agenda-horas` y `transbank/*` | **No se tocan.** Todo lo nuevo vive bajo `cart-flow/*` y tablas `cf_*`. |
| `productos.csv` trae `costo_domilab` y `nota_interna` (márgenes) | Se versiona una copia **sin** esas columnas; los costos se cargan aparte con `--dir` desde una carpeta privada. Nunca salen por la API (`$hidden`). |

---

## 2 — Alcance

**Dentro:**

- 17 tablas nuevas `cf_*` (catálogo, agenda, carro, pedido, pago, cotización, registro de visibilidad).
- Comando `cart-flow:importar-catalogo` (upsert por SKU; respeta `visible_tienda` existente).
- Seeder `CartFlowSeeder` con reglas de negocio y bloques de agenda por defecto.
- `CalculadoraCarroService`: el algoritmo §3 de la especificación de negocio, como función pura.
- Servicios de catálogo, carro, agenda, checkout, Webpay y cotización.
- 18 endpoints bajo `api/cart-flow/*` con sobre `{success, message, data}`.
- Tests unitarios de la calculadora con los 10 ejemplos de la §5 de la especificación y tests de feature del flujo.

**Fuera de alcance:**

- Mantenedor visual del catálogo en el front (solo existe el endpoint de visibilidad).
- Boleta electrónica, correo de confirmación y panel de pedidos para Gerencia.
- Cotizador de operativos por tramo (la tabla existe; el cálculo no).
- Cambiar o borrar el flujo viejo (`servicios`, `agenda_horas`, `web_pay_info`).

---

## 3 — Modelo de datos y contrato

### 3.1 Tablas

| Tabla | Clave | Contenido |
| --- | --- | --- |
| `cf_zonas` | `zona` único | `cargo_servicio`, `cargo_laboratorio` |
| `cf_comunas` | `comuna` único | `zona_id` (null = fuera de RM), `km_ida_vuelta`, `a_cotizar`, `comuna_lejana_chequeo_cv` |
| `cf_productos` | `sku` único | Las 35 columnas de `productos.csv` tipadas. `costo_domilab` y `nota_interna` ocultos al serializar |
| `cf_producto_componentes` | `pack_id`+`componente_id` | `orden` |
| `cf_operativo_tramos` | — | `sku_operativo`, `pacientes_min/max`, precios por región, `nota` |
| `cf_reglas` | `clave` único | `valor` JSON (toma de muestra, ticket mínimo, redondeo, insumos automáticos, horas de ayuno por defecto…) |
| `cf_visibilidad_log` | — | `producto_id`, `user_email`, `anterior`, `nuevo` |
| `cf_bloques_agenda` | — | `zona_id` (null = todas), `dia_semana` 1–7, `hora_inicio`, `hora_fin`, `franja` (`manana`/`tarde`), `cupo`, `activo` |
| `cf_carritos` | `uuid` único | `comuna_id`, `direccion`, `referencia`, `plan` (`ninguno`/`te_cuida`/`familiar`), `estado` (`abierto`/`convertido`/`expirado`), `expira_en` |
| `cf_carrito_pacientes` | — | Datos del paciente y consentimientos. **Soft delete** |
| `cf_carrito_lineas` | — | `paciente_id`, `producto_id`, `cantidad`. **Soft delete** |
| `cf_pedidos` | `numero` único (`ES-AAAA-NNNNNN`) | Snapshot de dirección y comuna, desglose completo, `fecha`, `bloque_id`, `estado`, `medio_pago`, `estado_pago`, `token_consulta`, contacto |
| `cf_pedido_pacientes` | — | Snapshot del paciente + `preparacion` JSON |
| `cf_pedido_lineas` | — | Snapshot de `sku`, `nombre`, `tipo`, `precio_unitario`, `cantidad`, `subtotal`, `automatica` |
| `cf_pedido_ordenes_medicas` | — | `pedido_paciente_id`, `path` en disco privado `local`, `mime`, `nombre_original` |
| `cf_pagos` | `buy_order` único | `pedido_id`, `medio`, `monto`, `token`, `status`, `response_code`, `authorization_code`, `payload` JSON, `estado` |
| `cf_cotizaciones` | — | `organizacion`, `comuna`, `personas`, `servicio`, `fecha_tentativa`, contacto, `estado` |

Estados del pedido: `pendiente_pago` → `pagado` → `confirmado` → `atendido`, o `anulado`. Un pedido **no se borra nunca**.

### 3.2 Forma del carro en la respuesta

```json
{
  "uuid": "9b1d…",
  "estado": "abierto",
  "comuna": { "id": 3, "comuna": "El Bosque", "zona": 1, "a_cotizar": false },
  "direccion": "Calle Ficticia 123", "referencia": null, "plan": "ninguno",
  "pacientes": [
    { "id": 1, "nombre": "Paciente Uno", "fecha_nac": "1980-01-01",
      "lineas": [ { "id": 10, "sku": "PERF-BASICO", "nombre": "Perfil Básico", "tipo": "perfil_lab",
                    "cantidad": 1, "precio_unitario": 19310, "subtotal": 19310, "automatica": false } ],
      "preparacion": { "ayuno": true, "horas_ayuno": 8, "requiere_orden_medica": false,
                       "indicaciones": ["Ayuno de 8 horas"] } } ],
  "desglose": { "subtotal_lab": 19310, "subtotal_servicios": 0, "toma_muestra": 0, "insumos": 0,
                "cargo_domicilio": 15000, "descuento_plan": 0, "total_sin_redondeo": 34310,
                "ajuste_redondeo": 690, "total": 35000 },
  "requiere_manana": true, "requiere_orden_medica": false, "a_cotizar": false,
  "bloqueos": [], "avisos": []
}
```

### 3.3 Endpoints

| Método | Ruta | Cuerpo | Respuesta `data` |
| --- | --- | --- | --- |
| GET | `cart-flow/comunas` | — | Comunas con zona y cargos |
| GET | `cart-flow/catalogo` | `?categoria=&tipo=&q=` | Productos visibles + `categorias` |
| GET | `cart-flow/catalogo/{sku}` | — | Producto con componentes |
| POST | `cart-flow/carritos` | `comuna_id`, `direccion?`, `plan?` | Carro (201) |
| GET | `cart-flow/carritos/{uuid}` | — | Carro |
| PUT | `cart-flow/carritos/{uuid}` | `comuna_id?`, `direccion?`, `referencia?`, `plan?` | Carro |
| POST | `cart-flow/carritos/{uuid}/pacientes` | Datos del paciente | Carro (201) |
| PUT | `cart-flow/carritos/{uuid}/pacientes/{id}` | Datos del paciente | Carro |
| POST | `cart-flow/carritos/{uuid}/pacientes/{id}/quitar` | — | Carro |
| POST | `cart-flow/carritos/{uuid}/lineas` | `paciente_id`, `sku`, `cantidad?` | Carro (201) |
| PUT | `cart-flow/carritos/{uuid}/lineas/{id}` | `cantidad` | Carro |
| POST | `cart-flow/carritos/{uuid}/lineas/{id}/quitar` | — | Carro |
| GET | `cart-flow/agenda/bloques` | `?carrito=&desde=&hasta=` | Días con bloques y cupos libres |
| POST | `cart-flow/carritos/{uuid}/checkout` | multipart: `fecha`, `bloque_id`, `medio_pago`, `contacto_*`, `acepta_terminos`, `ordenes[{paciente_id}]` | Pedido (201) |
| GET | `cart-flow/pedidos/{numero}` | `?t=token_consulta` | Pedido |
| POST | `cart-flow/pedidos/{numero}/webpay` | `t` | `{token, url}` |
| GET/POST | `cart-flow/webpay/retorno` | `token_ws` (Transbank) | Redirect al front |
| POST | `cart-flow/cotizaciones` | Datos de la solicitud | Cotización (201) |
| PUT | `cart-flow/admin/productos/{sku}/visibilidad` | `user_email`, `visible_tienda`, `confirmar?` | Producto |

- Sobre `{success, message, data}` / `{success:false, message, error}`; 201 en altas, 403 sin perfil Administrador,
  404 si el recurso no existe, 409 si el carro ya no está abierto o el bloque se llenó, 422 en validación o
  bloqueos del carro, 500 con registro en log.
- Las rutas literales van antes de las de parámetro; `{id}` con `whereNumber`.
- Visibilidad usa PUT y no PATCH porque el `ApiAdapter` del front no tiene `patch` (mismo criterio que la Spec 03).
- `pedidos/{numero}` y `pedidos/{numero}/webpay` exigen `token_consulta`: sin él, 404. Evita enumerar pedidos.

---

## 4 — Plan de implementación

1. Migraciones `2026_11_04_120000` (catálogo), `…120100` (agenda y carro) y `…120200` (pedido, pago y cotización).
   Prueba: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=CartFlow`.
2. Modelos en `app/Models/CartFlow/`.
3. `CartFlowSeeder` + comando `cart-flow:importar-catalogo`, con los CSV en `database/data/cart-flow/`.
4. Servicios en `app/Services/CartFlow/` + `CartFlowServiceProvider` registrado en `bootstrap/providers.php`.
5. Controladores en `app/Http/Controllers/CartFlow/` con un trait de respuestas.
6. Rutas al final de `routes/api.php`.
7. `config/services.php` → `cart_flow` (Webpay y URL del front) y variables en `.env.example`.
8. Tests en `tests/Unit/CartFlow/` y `tests/Feature/CartFlow/`.
9. Documentación: `docs/openapi.yaml`, `docs/modelo-datos.md`, `docs/flujos.md`, colección Postman, `CLAUDE.md`.
10. Puesta en marcha (Gerencia): respaldo, `php artisan migrate`, `db:seed --class=CartFlowSeeder`,
    `cart-flow:importar-catalogo`, variables `WEBPAY_CART_FLOW_RETURN` y `CART_FLOW_FRONT_URL`.

---

## 5 — Criterios de aceptación

- [ ] Los 10 ejemplos de la §5 de la especificación de negocio dan el total esperado.
- [ ] Una comuna sin zona deja el carro en `a_cotizar` y el checkout responde 422.
- [ ] Un total bajo $20.000 bloquea el checkout con el mensaje del ticket mínimo.
- [ ] Un carro con ayuno o `solo_ruta_manana` solo recibe bloques de franja `manana`.
- [ ] Un producto oculto no aparece en el catálogo, no se puede agregar y se quita del carro con aviso.
- [ ] Un producto `operativo`/`a_cotizar`/`solo_operativo` no se puede agregar al carro.
- [ ] Un paciente fuera de `edad_min`/`edad_max` o del `sexo` del producto bloquea la línea.
- [ ] El checkout exige orden médica por paciente cuando alguna línea la pide, y consentimiento del apoderado para menores.
- [ ] Un bloque con el cupo lleno responde 409.
- [ ] Webpay cobra `cf_pedidos.total`; un retorno con monto distinto o `response_code ≠ 0` no marca el pedido como pagado.
- [ ] Reimportar el catálogo no cambia `visible_tienda` de productos existentes.
- [ ] La API nunca devuelve `costo_domilab` ni `nota_interna`.
- [ ] Ninguna ruta nueva borra filas: quitar paciente o línea es soft delete; el pedido se anula.
- [ ] Los endpoints viejos (`servicios`, `agenda-horas`, `transbank/*`) responden igual que antes.
- [ ] `vendor/bin/pint --dirty` queda limpio.

---

## 6 — Decisiones tomadas y descartadas

- **D1 · Sí:** flujo paralelo con prefijo `cf_` y rutas `cart-flow/*`. **No:** ampliar `servicios`/`agenda_horas`, porque los consume la app móvil y el front viejo.
- **D2 · Sí:** el total se calcula solo en el servidor (`CalculadoraCarroService`). El front muestra el desglose.
- **D3 · Sí:** reglas de negocio en `cf_reglas` (JSON por clave) para que Gerencia cambie montos sin deploy. **No:** `params`, que no tiene migración ni tipos.
- **D4 · Sí:** cargos de domicilio por zona leídos de `cf_zonas` (no de valores fijos), con «visita mixta = el mayor».
- **D5 · Sí:** carro anónimo identificado por `uuid`. **No:** exigir login: la página es pública.
- **D6 · Sí:** soft delete en pacientes y líneas del carro; los pedidos se anulan. Respeta la §1 de la especificación legal.
- **D7 · Sí:** Webpay por HTTP con las mismas credenciales (`WEBPAY_URL/ID/SECRET`), leídas vía `config()`. **No:** reutilizar `WebPayController`, que queda intacto.
- **D8 · Sí:** transferencia y efectivo crean el pedido `pendiente_pago`; Gerencia lo confirma después.
- **D9 · Sí:** el cupo se descuenta con todos los pedidos no anulados del bloque y la fecha, dentro de una transacción con `lockForUpdate` sobre el bloque.
- **D10 · Sí:** orden médica en el disco privado `local` (`storage/app/private/cart-flow/ordenes`). **No:** `public/`, por ser dato clínico.
- **D11 · Sí:** el CSV versionado va sin `costo_domilab`, `nota_interna` ni `costo_componente`; la importación no pisa costos con vacío.

---

## 7 — Riesgos identificados

| Riesgo | Mitigación |
| --- | --- |
| `phpunit.xml` apunta al MySQL remoto | Los tests se corren con `DB_CONNECTION=sqlite DB_DATABASE=:memory:` y solo migran los archivos `cf_*`. |
| Carro anónimo: cualquiera con el `uuid` lo ve | El `uuid` es aleatorio (v4) y el carro expira en 7 días. Los datos del paciente no se listan en ningún endpoint público fuera de su carro. |
| Sin autenticación real en el admin | Mismo control que la Spec 03: `user_email` con perfil Administrador, más registro en `cf_visibilidad_log`. |
| URL de retorno de Webpay de producción | La configura Gerencia en `WEBPAY_CART_FLOW_RETURN`; no se toca el `.env` real. |
| Datos clínicos (RUT, fecha de nacimiento, orden médica) | Pruebas solo con datos ficticios; orden médica en disco privado; nada se borra. |
| Concurrencia en el último cupo | Transacción + `lockForUpdate` sobre la fila del bloque. |

---

## 8 — Lo que **no** entra en esta spec

- Mantenedor visual del catálogo, panel de pedidos y confirmación de rutas (spec futura del front `chequeo-administrador`).
- Correo de confirmación y boleta por línea de servicio.
- Cálculo de operativos por tramo y cotización automática.
- Planes Te Cuida / Familiar como contrato: hoy el cliente elige el plan y Gerencia lo verifica al confirmar.
- Migrar los datos de `agenda_horas` al modelo nuevo.
