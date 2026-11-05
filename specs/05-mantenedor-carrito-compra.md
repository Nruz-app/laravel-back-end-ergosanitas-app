# SPEC 05 — Mantenedor del carro para el Administrador (`cart-flow/admin/*`)

> **Estado:** Implementado
> **Depende de:** SPEC 04 (`servicio-cart-flow`, tablas `cf_*`).
> **Fecha:** 2026-11-05
> **Consumidores:** Spec 01 de `carrito-compra` en `Front-end/ergosanitas-app/specs/` (ítem «Carrito Compra» del menú del Administrador).
> **Objetivo:** Dar al Administrador control de lo que entra por el carro: resumen, pedidos y pagos con su detalle, gestión de estados con historial, cotizaciones y visibilidad del catálogo.

---

## 1 — Por qué existe esta spec

Con la SPEC 04 los pedidos, pagos, órdenes médicas y cotizaciones se guardan en `cf_*`, pero ningún endpoint los lista
para Ergo. Una transferencia o un pago en efectivo no tienen cómo marcarse como pagados, una visita no tiene cómo
confirmarse ni anularse, y las cotizaciones no tienen seguimiento.

### 1.1 Lo que se verificó en el código

| Hallazgo | Consecuencia |
| --- | --- |
| `CheckoutService` deja transferencia y efectivo en `pendiente_pago` con un `cf_pagos` `iniciado` | La acción `confirmar_pago` cierra ese pago como `aprobado`. |
| `AgendaService::usados()` cuenta pedidos con `estado != anulado` | Anular libera el cupo sin lógica extra. |
| `CatalogoService::esAdministrador()` ya valida `user_email` contra `perfiles.nombre = 'Administrador'` | Se reutiliza en todo el mantenedor (mismo control que la Spec 03; no es autenticación). |
| La orden médica vive en el disco privado `local` y `PedidoOrdenMedica` oculta `path` | La descarga va por URL firmada temporal: no se expone el path ni se pone `user_email` en la URL. |
| `cf_pedidos.estado` cambia sin registro | Tabla nueva `cf_pedido_historial` (quién, cuándo, de qué a qué, motivo). |

---

## 2 — Alcance

**Dentro:** migración aditiva del historial, `AdminPedidoService`, `AdminCartFlowController`, 9 rutas nuevas bajo
`cart-flow/admin/*` (más la de visibilidad que ya existe), tests de feature y documentación.

**Fuera de alcance:** autenticación real (Sanctum), exportar a Excel, edición de pedidos (cambiar líneas, fecha o
montos), reembolsos en Transbank, correo al cliente al cambiar de estado, edición de bloques de agenda y reglas.

---

## 3 — Modelo de datos y contrato

### 3.1 Cambios de esquema (`2026_11_05_120000_create_cf_pedido_historial_table`)

| Tabla | Cambio |
| --- | --- |
| `cf_pedido_historial` (nueva) | `pedido_id` FK, `user_email`, `campo` (`estado` \| `estado_pago`), `anterior`, `nuevo`, `motivo` nullable, timestamps |
| `cf_pedidos` | `+ anulado_motivo` (string 500, nullable), `+ atendido_en` (timestamp, nullable) |

### 3.2 Máquina de estados del pedido

| Acción | Desde | Hasta | Efecto |
| --- | --- | --- | --- |
| `confirmar_pago` | `pendiente_pago` | `pagado` | Solo `transferencia` o `efectivo`. El `cf_pagos` del medio pasa a `aprobado`; `estado_pago = pagado`. |
| `confirmar` | `pagado` | `confirmado` | Ruta confirmada. |
| `atender` | `confirmado`, o `pendiente_pago`/`pagado` si el medio es `efectivo` | `atendido` | Fija `atendido_en`; con efectivo pendiente marca además el pago como aprobado. |
| `anular` | todos menos `atendido` y `anulado` | `anulado` | `motivo` obligatorio (`anulado_motivo`). Libera el cupo. |

Transición inválida → **409** `transicion_invalida`. Cada cambio de `estado` o `estado_pago` escribe una fila en
`cf_pedido_historial` dentro de la misma transacción. **Nada se borra.**

### 3.3 Endpoints (todos exigen `user_email` Administrador; 403 `forbidden` si no)

| Método | Ruta | Cuerpo / query | Respuesta `data` |
| --- | --- | --- | --- |
| GET | `cart-flow/admin/resumen` | `user_email` | KPIs |
| GET | `cart-flow/admin/pedidos` | `user_email, estado?, estado_pago?, medio_pago?, desde?, hasta?, por=visita\|creacion, q?, page?` | `{items, total, pagina, por_pagina, ultima_pagina}` |
| GET | `cart-flow/admin/pedidos/{numero}` | `user_email` | Pedido completo + pagos + órdenes + historial + `acciones` permitidas |
| PUT | `cart-flow/admin/pedidos/{numero}/estado` | `{user_email, accion, motivo?}` | Pedido completo |
| POST | `cart-flow/admin/ordenes/{id}/enlace` | `{user_email}` | `{url, expira}` (5 min) |
| GET | `cart-flow/admin/ordenes/{id}/descarga` | firma (`signed`) | Archivo |
| GET | `cart-flow/admin/cotizaciones` | `user_email, estado?, q?` | Lista |
| PUT | `cart-flow/admin/cotizaciones/{id}/estado` | `{user_email, estado}` | Cotización |
| GET | `cart-flow/admin/productos` | `user_email, q?, categoria?, visible?` | Productos (sin costos) |
| PUT | `cart-flow/admin/productos/{sku}/visibilidad` | (SPEC 04) | Producto |

- `q` busca en número, nombre/email/teléfono de contacto y RUT de los pacientes (RUT sin puntos).
- El detalle del pedido para el Administrador **sí** incluye RUT y fecha de nacimiento; los pagos van sin `token` ni
  payload crudo.
- La descarga firmada responde 403 si la firma no coincide o venció.

---

## 4 — Plan de implementación

1. Migración + modelo `PedidoHistorial` + relación `Pedido::historial()`.
2. `AdminPedidoService` (resumen, listar, detalle, cambiarEstado, enlaceOrden, cotizaciones, productos) registrado en `CartFlowServiceProvider`.
3. `AdminCartFlowController` + rutas (literales antes de `{numero}`; `{id}` con `whereNumber`; descarga con `signed`).
4. `tests/Feature/CartFlow/AdminCartFlowTest.php`.
5. OpenAPI, Postman, `docs/modelo-datos.md`, `CLAUDE.md`.
6. Base remota: solo la migración nueva con `--path`, previo `--pretend`.

---

## 5 — Criterios de aceptación

- [ ] Sin `user_email` de Administrador, todo `cart-flow/admin/*` (salvo la descarga firmada) responde 403.
- [ ] La lista filtra por estado, estado de pago, medio, rango de fechas (visita o creación) y busca por RUT.
- [ ] `confirmar_pago` de una transferencia deja el pedido `pagado`, el pago `aprobado` y dos filas de historial.
- [ ] `confirmar_pago` sobre un pedido Webpay responde 409.
- [ ] `anular` sin motivo → 422; con motivo → `anulado`, y el cupo del bloque vuelve a estar libre.
- [ ] El enlace firmado descarga la orden; uno alterado responde 403.
- [ ] El resumen cuenta bien pendientes, pagados del mes y monto.
- [ ] Ninguna ruta nueva borra filas.
- [ ] `vendor/bin/pint` limpio en los archivos tocados.

---

## 6 — Decisiones tomadas y descartadas

- **D1 · Sí:** acciones nombradas (`confirmar_pago`, `confirmar`, `atender`, `anular`) en vez de «poner cualquier estado». Evita saltos imposibles.
- **D2 · Sí:** historial en tabla propia. **No:** columnas `*_por` en `cf_pedidos`, que perderían los cambios anteriores.
- **D3 · Sí:** URL firmada para la orden médica. **No:** endpoint con `user_email` en la query (quedaría en logs y en el historial del navegador).
- **D4 · Sí:** paginación de servidor (20). **No:** traer todos los pedidos: crecen sin límite.
- **D5 · No:** confirmar manualmente un pago Webpay: lo confirma solo Transbank.

---

## 7 — Riesgos identificados

| Riesgo | Mitigación |
| --- | --- |
| `user_email` falsificable | Igual que la Spec 03; queda registrado en el historial. Autenticación real es una spec aparte. |
| Datos clínicos visibles para el Administrador | Solo en el detalle, nunca en la lista; la orden médica por enlace que caduca en 5 minutos. |
| Anular un pedido Webpay ya pagado | Se permite (devolución fuera del sistema); queda el motivo en el historial. |

---

## 8 — Lo que **no** entra en esta spec

- Reembolsos automáticos en Transbank, boleta y correo al cliente.
- Edición del pedido, de la agenda (bloques/cupos) y de las reglas de precio desde la interfaz.
- Exportación a Excel.
