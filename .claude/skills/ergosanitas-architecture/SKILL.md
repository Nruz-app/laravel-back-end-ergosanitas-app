---
name: ergosanitas-architecture
description: Analiza la arquitectura del back-end de Ergosanitas (capas Controller→Service→Model, providers, procedimientos almacenados, contratos con React y la app móvil) para diseño, análisis de impacto y refactorización. Úsala antes de proponer un cambio estructural o de documentar cómo encaja una pieza. Dentro del flujo de specs la cargan solas spec-ergo-backend, spec-impl-ergo-backend y spec-verify-ergo-backend cuando el Kit de ejecución activa sus señales (S-base, es decir siempre).
---

# Ergosanitas Architecture

Objetivo: entender lo que **ya existe** antes de proponer cambios. No impongas una arquitectura nueva sin evidencia de que resuelve un problema real.

## 1. Fuentes, en este orden

1. **graphify** (si existe `graphify-out/graph.json`): `graphify query "<pregunta>"`, `graphify path "A" "B"`, `graphify explain "X"`, `graphify affected "X"` para el impacto inverso. `GRAPH_REPORT.md` solo para una revisión amplia. Es AST: no ve SP ni lógica dentro de strings SQL.
2. `CLAUDE.md` del repo (arquitectura, trampas) y las specs de `specs/` del dominio que toques.
3. Diagramas mantenidos a mano: `docs/arquitectura.md`, `docs/flujos.md`, `docs/diagrama-clases.md`, `docs/modelo-datos.md` (índice en `docs/README.md`), y los explorables de `docs/diagramas/` (la fuente es el `.json`, el `.html` es generado).
4. El código, para confirmar: lo anterior puede estar desfasado.

## 2. El mapa real

- Flujo: **ruta** (`routes/api.php`, sin middleware de auth) → **controlador** (valida, arma el sobre, `try/catch`) → **service** (lógica, Query Builder, documentos) → **modelo** (Eloquent + wrappers estáticos `DB::select('CALL SP_xxx(?)')`).
- Cada servicio es singleton vía su **provider manual** en `bootstrap/providers.php`. `CertificadoProvider` es el único que inyecta otro servicio. `ChequeoCardiovascularWordService` no tiene provider (contraejemplo).
- **No existen** `app/Http/Middleware`, Requests, Resources, Policies, Jobs, Events ni Listeners. No los busques ni los supongas en un análisis.
- Gran parte de la lógica estadística vive en **procedimientos almacenados** (MySQL 5.7, sin CTE ni funciones de ventana). Sus fuentes están repartidas y la verdad es la BD: ver `CLAUDE.md` § Procedimientos almacenados y la skill `ergosanitas-mysql`.
- Dominios aislados a propósito: los tres chats (clínico, por club, comercial) y el carro `cart-flow` (`app/*/CartFlow/`, tablas `cf_*`). No los fusiones.
- Copias muertas que no se cargan: `api.php` en la raíz y `app/Http/Controllers/bootstrap/`.

## 3. Análisis de impacto

- Duplicaciones conocidas que hay que mover juntas: la regla de perfiles 3/6 en tres métodos de `ChequeoCardiovascularService` (hoy divergentes), los seis puntos que escriben `chequeo_cardiovascular.status`, la subida de certificados que factura (`CertificadoService` y `CertificadoUrlController`).
- Clientes: front (`Front-end/…/common/api/api.adapter.ts`) y app móvil (`APP/…/src/config/api/api.adapter.ts`). Siete sobres de respuesta: cambiar uno rompe clientes (skill `ergosanitas-api`).
- Producción corre en el **hosting compartido** (`ergosanitas.com/BackEnd`), no en el Docker de `dockerHub/`. El deploy por FTP excluye `public/` y no corre migraciones.

## 4. Proponer un cambio

1. El problema observado, con archivo y línea.
2. Consumidores y dependencias (graphify `affected` + clientes).
3. La solución más pequeña que mejora la mantenibilidad. Nada de refactors masivos por preferencia.
4. Compatibilidad con la API y la BD; migraciones aditivas y reversibles (Ley 21.719: no borrar ni sobrescribir datos clínicos).
5. Rollback y pruebas de regresión.
6. Las features nuevas van por spec, no directo a código: `/spec-ergo-backend` la diseña con su Kit de ejecución, una persona la aprueba, `/spec-impl-ergo-backend` la implementa y `/spec-verify-ergo-backend` la verifica.

## 5. Documentar

- Separa lo verificado de lo supuesto y cita archivos o clases concretos.
- Actualiza el diagrama `.md` que corresponda vía el agente `ergo-docs`; no crees documentos paralelos.
- Sin secretos ni datos de pacientes.

---

## Uso en el flujo de specs

Esta skill forma parte del **Kit de ejecución** de las specs del back (`.claude/skills/spec-ergo-backend/references/enrutado.md`).

- **Señales que la activan:** S-base (siempre).
- **Quién la carga:** quien orquesta la fase (no tiene agente propio).

| Fase | Qué aporta |
|---|---|
| Diseño | Impacto del dominio vecino con graphify antes de proponer el plan. |
| Implementación | Impacto del diff en la Fase 4b (`graphify affected`). |
| Verificación | Lectura de impactos de la Fase 2. |

Las reglas transversales (§4 de `enrutado.md`: graphify solo AST, BD solo metadatos, sin HTTP, tests solo en SQLite, sin commits) aplican también aquí. El mapa completo de relaciones está en §8 de ese archivo.
