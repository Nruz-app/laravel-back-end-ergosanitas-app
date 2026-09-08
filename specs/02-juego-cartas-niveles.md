# SPEC 02 — Juego de cartas por niveles (SP_juego_cartas_club)

> **Estado:** Implementado
> **Depende de:** —
> **Fecha:** 2026-09-05 · **Reescrita:** 2026-09-07 tras la implementación
> **Objetivo:** Exponer tres endpoints GET que conviertan a cada paciente de un club en una "carta" con cuatro atributos comparables 0–100, puntaje, nivel, estrellas y porcentaje de completitud, calculados al vuelo por un procedimiento almacenado contra umbrales configurables en la tabla `juego_niveles`.

---

## 1 — Por qué existe esta spec

El HTML de referencia (`references/html/juego_cartas_evaluacion_pacientes.html`) pinta una
grilla de cartas de paciente con cuatro indicadores visuales que **no son el mismo dato**:
un badge `ALTO`/`MEDIO`/`BAJO`, un filtro `inicial`/`evaluado`/`completo`, estrellas de 1 a 5
y una barra de progreso.

En el mock esos valores no correlacionan (Ana tiene 55 % y es `BAJO`; Carlos tiene 25 % y es
`MEDIO`), lo que confirma que son **dos ejes independientes**: uno mide la salud del paciente y
el otro cuánta información tiene cargada.

Lo que el mock no da es la **mecánica de juego**: un puntaje agregado no permite comparar
alumnos entre sí. Por eso cada carta expone además **cuatro atributos 0–100** —
❤️ Corazón, 🫁 Vitalidad, 💪 Composición, 🛡️ Resistencia — que sí son comparables carta a carta.

No hay nada equivalente hoy: `SP_ficha_clinica` consolida los datos de **un** paciente pero no
los evalúa, y `SP_chequeos_club_prompt` lista los de un club solo para alimentar al asistente de
IA. Esta spec toma de `SP_ficha_clinica` el **estilo** (un SP que devuelve una única columna
`resultado_json`) y de `SP_chequeos_club_prompt` la **firma** (`p_search`, `p_club`).

### 1.1 Lo que se verificó contra la base real

Comprobado contra `ergosan1_bdd` (MySQL 5.7.44-48) el 2026-09-05 y **reverificado el 2026-09-07**.
Estos hallazgos condicionan la fórmula del §3.

| Hallazgo | Consecuencia |
| --- | --- |
| `cc.imc` está **vacío en 1.634 de 1.634 filas**; el IMC real vive en `imc_paciente` (298 vacíos) | El SP lee `imc_paciente`. `SP_chequeos_club_prompt` lee `cc.imc`, y por eso ese campo sale siempre vacío en el asistente: bug heredado que aquí no se replica. |
| `cc.pulso` está **vacío en 1.634 de 1.634 filas** y ningún PHP lo lee | Se elimina como indicador. |
| `presionArterial` = 75 y `presion_sistolica` = 121 en la misma fila | Están **invertidos**: `presionArterial` guarda la diastólica (rango real 63–83) y `presion_sistolica` la sistólica (110–135). Solo 1 fila anómala en toda la base. |
| **No existe la columna `sexo`** en `chequeo_cardiovascular`: es `sexo_paciente` (`Masculino` 1.445 / `Femenino` 189) | La carta mapea `sexo` desde `sexo_paciente`. En `bioimpedancia` sí se llama `sexo`, con valores `Hombre`/`Mujer`. |
| `bioimpedancia`: 10 filas, 9 RUT, y **solo 7 cruzan con un paciente con chequeos** | El bloque de bioimpedancia daría 0 al 99,5 % de los pacientes → se saca del 100 y pasa a ser bonus (§3.3). |
| `bioimpedancia` es `updateOrCreate` por `rut` → **una fila por RUT** | "La fila más reciente" es indiferente; se acota igual con `MAX(id)` por las 10 filas / 9 RUT. |
| `incidentes_deportivos`: 15 filas | Excluidas del puntaje por indicación expresa. |
| `estado_paciente`: `Normal` (2.998) / `Alterado` (393) | Binario limpio, sin valores raros. |
| `derivacion_paciente`: `na` (2.656), `No requiere` (286), `No` (39), el resto derivaciones reales | El conjunto de valores "sin derivación" es conocido y cerrado. |
| 2.065 ECG huérfanos; 3.391 ECG en total | Justifica el `LEFT JOIN` en vez del `INNER JOIN` del SP hermano. |
| `App\Imports\ChequeoImport` solo puebla nombre, rut, fechaNacimiento, sexo_paciente, edad, division_paciente y user_email | **Un alumno cargado por Excel no tiene ningún signo vital.** Con "ausencia = 0" caería a `BAJO` por un problema administrativo. De ahí el estado `SIN EVALUAR` (§3.4). |
| Máximo 4 chequeos por RUT, promedio 1,05 | El límite de 1.024 bytes de `GROUP_CONCAT` es inalcanzable aquí. |
| `estatura` llega a 162 (mezcla metros y centímetros), `frecuencia_cardiaca_paciente` va de 9 a 800 | Ninguno entra en la fórmula, pero confirma que **todo `CAST` necesita rango de validez**. |

**Datos de prueba:** club `brisas@ergosanitas.com` (118 pacientes), RUT `25527383-3`.
Otros clubes con volumen: `Colegio.altair@ergosanitas.com` (147),
`cobresal.buin@ergosanitas.com` (109), `Colocolo.pa.gabriela@ergosanitas.com` (102).

---

## 2 — Alcance

**Dentro:**

- `GET api/juego-cartas/{user_email}` — todas las cartas del club, con `?search=` opcional.
- `GET api/juego-cartas/detalle/{rut_paciente}` — una carta por RUT.
- `GET api/juego-cartas/niveles` — la configuración, para que el front no hardcodee nada.
- SP `SP_juego_cartas_club(p_search, p_club)`, versionado en `base_datos/references/sp/`.
- Tablas `juego_niveles` (7 filas) y `juego_atributos` (4 filas), sembradas en el `up()`.
- Modelos, service, provider y controlador siguiendo el vertical slice de `FichaClinica`.
- Actualización de `docs/openapi.yaml`, `docs/modelo-datos.md`, `README.md` y `CLAUDE.md`.

**Fuera de alcance:**

- **Persistir el puntaje**: no hay tabla snapshot ni histórico. Cada llamada recalcula.
- **Evolución en el tiempo**, XP y progresión: exigen ese histórico.
- **Enfrentamiento entre cartas** (duelos, mazos, ranking con posición): los atributos ya viajan
  en cada carta, así que el front puede comparar; la mecánica es otra spec.
- **Incidencias deportivas en el puntaje**: indicación expresa, y solo hay 15 filas.
- **Paginación**: el club más grande tiene 147 pacientes y cabe en una respuesta.
- **Autenticación** y **filtrado por `perfiles_id` 3/6**: el ámbito es el `user_email` recibido.
- **Tests automatizados**: `phpunit.xml` apunta al MySQL remoto y en SQLite no hay SP.
- **Frontend**: el HTML de `references/` es referencia visual, no se versiona una página.
- **Corregir los bugs heredados** del §1.1 (`SP_chequeos_club_prompt` leyendo `cc.imc`, los
  nombres invertidos de la presión). Se documentan y se esquivan.

---

## 3 — Modelo de datos y fórmula

### 3.1 Tabla `juego_niveles`

Una sola tabla para los **dos** ejes, discriminados por `tipo`: un único endpoint de
configuración y un único lugar donde retunear umbrales sin tocar el SP.

Columnas: `id`, `tipo`, `slug`, `nombre`, `valor_min`, `valor_max`, `color_fondo`,
`color_texto`, `orden`, `activo`, timestamps, con `unique(tipo, slug)` e
`index(tipo, valor_min, valor_max)`.

| tipo | slug | nombre | valor_min | valor_max | color_fondo | color_texto | orden |
| --- | --- | --- | --- | --- | --- | --- | --- |
| clinico | sin_evaluar | SIN EVALUAR | -1 | -1 | `#f3f4f6` | `#9ca3af` | 0 |
| clinico | bajo | BAJO | 0 | 74 | `#fee2e2` | `#dc2626` | 1 |
| clinico | medio | MEDIO | 75 | 94 | `#fef3c7` | `#b45309` | 2 |
| clinico | alto | ALTO | 95 | 100 | `#dcfce7` | `#15803d` | 3 |
| completitud | inicial | Inicial | 0 | 2 | `#e5e7eb` | `#6b7280` | 1 |
| completitud | evaluado | Evaluado | 3 | 4 | `#e0e7ff` | `#4f46e5` | 2 |
| completitud | completo | Completo | 5 | 5 | `#dcfce7` | `#15803d` | 3 |

El `-1/-1` de `sin_evaluar` es un **centinela**, no un puntaje: mantiene el badge dentro de la
tabla, así que `nivel` nunca vuelve `null` y las bandas se siguen retuneando con un `UPDATE`.

Los cortes clínicos **no son los "naturales" 0-39/40-74/75-100**: se fijaron sobre la
distribución real (§7).

### 3.2 Tabla `juego_atributos`

Catálogo de los cuatro atributos, para que el front no hardcodee iconos ni etiquetas.
Columnas: `id`, `slug` (unique), `nombre`, `icono`, `descripcion`, `orden`, `activo`, timestamps.

| slug | nombre | icono | descripcion |
| --- | --- | --- | --- |
| corazon | Corazon | ❤️ | Electrocardiograma y antecedente cardiovascular |
| vitalidad | Vitalidad | 🫁 | Saturacion de oxigeno y presion arterial |
| composicion | Composicion | 💪 | Indice de masa corporal y bioimpedancia |
| resistencia | Resistencia | 🛡️ | Hemoglucotest y antecedentes generales |

Ambas migraciones **siembran en el propio `up()`**. Ninguna otra migración del repo lo hace; es
una desviación deliberada para que `php artisan migrate` deje un entorno utilizable.

### 3.3 Los cuatro atributos

Regla única:

> **atributo = ROUND(100 × puntos_obtenidos / puntos_máximos_de_los_sub-indicadores presentes)**
> Sin ningún sub-indicador presente → el atributo es `null` y la carta pinta `––`.

Así un alumno con ECG pero sin presión sigue teniendo un Corazón comparable con el de otro.
"Sano" significa `LOWER(TRIM(...))` en (`no presenta`, `sin alteraciones`, `sin ateraciones`,
`ninguna`, `no`). `sin ateraciones` es un typo real en la base y se acepta a propósito.

**❤️ Corazón**

| Sub-indicador | Columna | Sano | Con hallazgo | Ausente |
| --- | --- | --- | --- | --- |
| Estado ECG | `ec.estado_paciente` | `Normal` → 50 | `Alterado` → 15 | sin fila ECG |
| Derivación | `ec.derivacion_paciente` | `na`/`no`/`no requiere`/vacío → 30 | otro texto → 8 | sin fila ECG |
| Antecedente | `cc.sistemaCardiovascular` | 20 | 8 | vacío |

**🫁 Vitalidad**

| Sub-indicador | Columna | 50 | 30 | 10 | Ausente |
| --- | --- | --- | --- | --- | --- |
| Saturación O₂ | `cc.saturacionOxigeno` | ≥ 95 | 90–94 | resto en rango | fuera de 50–100 |
| Presión | `presion_sistolica` (sist.) / `presionArterial` (diast.) | sist < 120 y diast 30–79 | sist 120–129 y diast 30–79 | resto en rango | sist fuera de 60–250 |

**💪 Composición** — IMC (`cc.imc_paciente`, rango 10–60): 18,5–24,9 → 100 · 17–18,4 o
25–29,9 → 60 · resto en rango → 20. Cuando hay bioimpedancia se promedia con sus cuatro
indicadores (`puntaje_corporal`, `grasa_corporal_pct`, `grasa_visceral`, `smi`, 100 pts c/u),
con cortes por sexo: el sexo sale de `bioimpedancia.sexo`, con `cc.sexo_paciente` de respaldo y
el rango de Hombre por defecto.

**🛡️ Resistencia**

| Sub-indicador | Columna | Sano | Con hallazgo | Ausente |
| --- | --- | --- | --- | --- |
| Hemoglucotest | `cc.hemoglucotest` (rango 30–500) | 70–140 → 40 | 60–69 o 141–199 → 24 · resto → 8 | fuera de rango |
| Enf. crónicas | `cc.enfermedadesCronicas` | 15 | 6 | vacío |
| Medicamentos | `cc.medicamentosDiarios` | 15 | 6 | vacío |
| Osteoarticular | `cc.sistemaOsteoarticular` | 15 | 6 | vacío |
| Enf. anteriores | `cc.enfermedadesAnteriores` | 15 | 6 | vacío |

### 3.4 Puntaje, nivel y estrellas

```
puntaje = LEAST(100, ROUND(AVG(atributos no nulos)) + bonus)
```

- **Sin ningún atributo medido** → `puntaje = null`, `banda = -1` → `SIN EVALUAR`, `estrellas = 0`.
  Es la regla que impide que un alumno cargado por Excel aparezca como `BAJO`.
- `bonus` = 0–10, proporcional a la **calidad** de la bioimpedancia (no a tenerla); `0` sin fila.
  La carta lleva además `insignia: "InBody"` cuando existe.
- `estrellas = LEAST(5, GREATEST(1, CEIL(puntaje / 20)))`, y `0` si `puntaje` es `null`.

Al quedar la bioimpedancia fuera del 100, el techo es 100 real: con la fórmula anterior (cuatro
bloques de 25 y ausencia = 0) el máximo observable era **75** y 213 pacientes empataban ahí.

### 3.5 Completitud (segundo eje)

| Bloque | Se cuenta cuando |
| --- | --- |
| `chequeo` | Siempre — existe la fila que origina la carta. |
| `signos_vitales` | `imc_paciente`, `presion_sistolica`, `presionArterial`, `saturacionOxigeno` y `hemoglucotest` todos > 0. |
| `ecg` | Hay fila en `electro_cardiogranas` por `(rut, id_chequeo)` con `estado_paciente` informado. |
| `bioimpedancia` | Hay fila en `bioimpedancia` con ese `rut`. |
| `certificado` | Hay fila en `certificado_url` por `(rut_paciente, id_chequeo)`. |

`progreso = bloques_completos * 20`. Mínimo 20 %, porque `chequeo` siempre es verdadero.

### 3.6 Firma del SP y elección de la fila

```sql
SP_juego_cartas_club(IN p_search VARCHAR(255), IN p_club VARCHAR(255))
```

`p_club` informado → solo ese `cc.user_email`; `p_club` NULL → sin filtro de club (existe
únicamente para el detalle por RUT). `p_search` NULL o vacío → sin filtro; si no, `LIKE
'%search%'` contra `cc.rut` y `cc.nombre`.

Una carta por RUT, sobre su chequeo más reciente. Sin funciones de ventana en 5.7:

```sql
INNER JOIN (
    SELECT rut,
           CAST(SUBSTRING_INDEX(
               GROUP_CONCAT(id ORDER BY COALESCE(fecha_atencion, created_at) DESC, id DESC),
               ',', 1
           ) AS UNSIGNED) AS id_ultimo
    FROM chequeo_cardiovascular
    WHERE (p_club IS NULL OR user_email = p_club)
    GROUP BY rut
) ult ON ult.id_ultimo = cc.id
```

ECG y bioimpedancia entran con `LEFT JOIN` acotado por un `MAX(id)` correlacionado. La
clasificación contra `juego_niveles` va en el `SELECT` exterior, sobre una tabla derivada que ya
trae `banda` y `bloques_completos`: en 5.7 no se puede referenciar un alias del `SELECT` dentro
de un `JOIN` del mismo nivel. El SP está estructurado en cuatro capas anidadas
(`src` → `base` → `calc` → `fin`).

### 3.7 Contrato de los tres endpoints

Los tres usan el **sobre A** (`{success, message, data}`), el de `FichaClinicaController`.
La forma completa de la carta está en `docs/openapi.yaml` (`components/schemas/JuegoCarta`).

- `GET api/juego-cartas/{user_email}?search=` → `data: {club, search, total, cartas[]}`.
- `GET api/juego-cartas/detalle/{rut_paciente}` → `data` es una carta, o `null` con
  `message: "Paciente sin chequeos registrados"` y **status 200** si el RUT no existe.
- `GET api/juego-cartas/niveles` → `data: {clinico[], completitud[], atributos[]}`.

Error (`catch`, 500): `{success: false, message: "Error obteniendo las cartas", error: "..."}`.

---

## 4 — Lo implementado

| Paso | Archivo |
| --- | --- |
| 1 | `database/migrations/2026_09_07_120000_create_juego_niveles_table.php` · `..._120100_create_juego_atributos_table.php` |
| 2 | `base_datos/references/sp/SP_juego_cartas_club.sql` |
| 3 | `app/Models/JuegoCartaClub.php` · `JuegoNivel.php` · `JuegoAtributo.php` |
| 4 | `app/Services/JuegoCartasService.php` |
| 5 | `app/Providers/JuegoCartasServiceProvider.php` + `bootstrap/providers.php` |
| 6 | `app/Http/Controllers/JuegoCartasController.php` |
| 7 | `routes/api.php` (literales antes del comodín) |
| 8 | `docs/openapi.yaml`, `docs/modelo-datos.md`, `docs/README.md`, `README.md`, `CLAUDE.md` |

El orden por puntaje **se hace en PHP** (`JuegoCartasService::ordenar`), no en el SP: en MySQL
5.7 `JSON_ARRAYAGG` no respeta `ORDER BY`. Desempata por `atributos_medidos` descendente y
manda las cartas `sin_evaluar` al final.

---

## 5 — Criterios de aceptación

Todos verificados contra la base real el 2026-09-07.

- [x] `php artisan route:list --path=juego-cartas` muestra exactamente 3 rutas, y `niveles` **no** cae en `{user_email}`.
- [x] `SELECT COUNT(*)` → 7 en `juego_niveles`, 4 en `juego_atributos`.
- [x] `bootstrap/providers.php` contiene el provider y `app(JuegoCartasService::class) === app(...)` es `true`.
- [x] `CALL SP_juego_cartas_club(NULL, 'brisas@ergosanitas.com')` → 118 cartas / 118 RUT distintos. Sobre toda la base: **1.554 cartas para 1.554 RUT**.
- [x] Listado 200, sobre A, ordenado por puntaje descendente, `sin_evaluar` al final.
- [x] `?search=25527383-3` → una sola carta, la de ese RUT.
- [x] Detalle: el promedio de los atributos medidos más `bonus` es exactamente el `puntaje`.
- [x] `niveles` → 4 filas en `clinico`, 3 en `completitud`, 4 en `atributos`, con los emojis intactos.
- [x] Club inexistente → 200 con `total: 0` y `cartas: []`. RUT inexistente → 200 con `data: null`.
- [x] `estrellas` siempre 0–5; `progreso` múltiplo de 20 entre 20 y 100; `puntaje` 0–100 o `null`; `medido` coherente con `valor !== null`. Comprobado sobre las 1.554 cartas.
- [x] `nivel` y `estado` nunca `null`.
- [x] Un `UPDATE` de `valor_min` en `clinico/alto` cambia el badge del mismo paciente (`alto → medio → alto`) **sin tocar el SP ni el código**.
- [x] 201 cartas quedan en `SIN EVALUAR` con `puntaje: null`, `estrellas: 0` y los 4 atributos en `medido: false`.
- [x] 7 cartas traen `insignia: "InBody"`, con `bonus` variable (9, 5, 4, 0) según la calidad.
- [x] `docs/openapi.yaml` documenta 84 operaciones, 0 `$ref` rotos, 0 `operationId` duplicados.
- [x] `php artisan test` en verde; `GET api/ficha-clinica/{rut}` sigue en 200 y sin diff.

---

## 6 — Decisiones tomadas y descartadas

| Decisión | Por qué |
| --- | --- |
| **Cuatro atributos comparables**, no solo un total | Un puntaje agregado no permite comparar alumnos: sin atributos es una tabla ordenada con estética de cartas, no un juego. |
| **Dos ejes independientes**: clínico y completitud | Es lo que muestra el HTML de referencia. Descartado unificarlos, que habría perdido la idea de "progreso de la ficha". |
| **Normalizar cada atributo sobre los sub-indicadores presentes** | Hace las cartas comparables aunque tengan datos parciales. La alternativa (ausencia = 0) confunde "no medido" con "enfermo". |
| **Estado `SIN EVALUAR` como cuarta banda con centinela `-1`** | 201 alumnos solo tienen la carga de Excel. Con ausencia = 0 aparecerían como `BAJO` ante el colegio por un problema administrativo. El centinela mantiene el badge en la tabla, así `nivel` nunca es `null`. |
| **Bioimpedancia como bonus fuera del 100** | Solo 7 de 1.554 pacientes la tienen. Dentro del 100 (25 pts) el techo real del puntaje era 75 y `ALTO` resultaba inalcanzable. |
| **El bonus mide calidad, no posesión** | Premiar el mero hecho de tener el examen mezclaría "está sano" con "le hicieron más pruebas", que es justo lo que separa el eje de completitud. |
| **Tabla de configuración + cálculo al vuelo** | Descartada la tabla snapshot: obliga a decidir cuándo recalcular y el dato queda viejo. Descartado hardcodear umbrales en el SP: retunear exigiría modificarlo. |
| **Dos tablas (`juego_niveles` + `juego_atributos`)** | Los dos ejes comparten forma (rango → etiqueta → colores) y caben en una; el catálogo de atributos no tiene rangos y habría desnaturalizado el modelo. |
| **Siembra dentro del `up()`** | Ninguna migración del repo lo hacía, pero sin las filas los endpoints devuelven cartas sin badge. Se asume la desviación. |
| **`imc_paciente` en vez de `cc.imc`** | `cc.imc` está vacío en las 1.634 filas. No se replica el bug de `SP_chequeos_club_prompt`. |
| **`cc.pulso` eliminado** | Vacío en las 1.634 filas. Descartado sustituirlo por `ec.frecuencia_cardiaca_paciente`: habría acoplado Vitalidad a que exista ECG y contado el ECG dos veces. |
| **Presión leída "invertida" a propósito** | El SP lee cada columna por su contenido real, no por su nombre. Renombrarlas rompería endpoints en producción. |
| **`LEFT JOIN` con ECG** | El `INNER JOIN` de `SP_chequeos_club_prompt` dejaría fuera a pacientes que el club sí cargó. |
| **Un solo SP con `p_club` nullable** | Un `SP_juego_carta_detalle` aparte habría duplicado la fórmula, con riesgo de divergir al primer ajuste. |
| **Orden en PHP, no en el SP** | En MySQL 5.7 `JSON_ARRAYAGG` no respeta `ORDER BY`. El `usort` es el único punto determinista. |
| **Desempate por `atributos_medidos`** | Sin él, una carta con 2 atributos al 100 % adelanta a una completa igual de sana. |
| **Bandas 0-74 / 75-94 / 95-100** | Los cortes naturales dejaban 1.225 de 1.353 pacientes en `ALTO`. Ver §7. |
| **Carta sobre el chequeo más reciente** | Descartado el promedio histórico (diluye una mejora) y el mejor histórico (oculta un deterioro). |
| **Sin ranking con posición** | El listado ordenado basta para pintar la grilla. Una posición estable exige decidir empates y ámbito. |

---

## 7 — Riesgos identificados

- **Las bandas se calibraron sobre la distribución actual, no sobre criterio clínico.** Con los
  cortes naturales (0-39/40-74/75-100) el reparto era 0 `BAJO` / 128 `MEDIO` / 1.225 `ALTO`: el
  badge no discriminaba nada. Los cortes actuales dan **201 sin evaluar · 128 bajo · 779 medio ·
  446 alto**, que sí es usable, pero son percentiles de esta base en esta fecha. Al crecer los
  datos hay que recalibrar con un `UPDATE`.
- **Un atributo puede valer 100 con un solo sub-indicador.** Es la contracara de normalizar sobre
  lo presente: un alumno con `sistemaCardiovascular = 'No Presenta'` y sin ECG tiene Corazón 100.
  El eje de completitud lo delata (`ecg: false`, progreso 20 %) y el desempate del orden lo baja
  en la grilla, pero la carta muestra un 100 que descansa en un solo campo de texto. Si molesta,
  la mitigación es exigir un mínimo de sub-indicadores por atributo — cambio en el SP.
- **Las estrellas casi no discriminan**: 892 de 1.353 cartas evaluadas tienen 5. Derivan de
  `CEIL(puntaje/20)` y el puntaje se concentra entre 65 y 100. Cambiarlas para que sigan las
  bandas de `juego_niveles` en vez del puntaje crudo exige tocar el SP.
- **El bloque de antecedentes premia el origen del dato.** El controlador escribe los defaults
  `'No Presenta'`/`'Sin Alteraciones'` con `filled()`, así que todo paciente ingresado por
  formulario se lleva el máximo de esos sub-indicadores.
- **El SP no queda versionado en las migraciones.** Un entorno levantado solo con
  `php artisan migrate` tendrá las tablas pero no el procedimiento, y los tres endpoints
  devolverán 500 desde el `catch`. **Confirmar el despliegue del SP en producción antes de dar
  los endpoints por disponibles.**
- **Sin control de acceso.** Cualquiera que conozca el email de un club lee la evaluación clínica
  de sus alumnos, y `detalle/{rut}` ni siquiera exige club. Es el mismo riesgo que el resto de
  `routes/api.php`, pero aquí se expone salud de menores.
- **Ordenar en PHP asume que el club cabe en memoria.** Con 147 pacientes es trivial; si algún
  `user_email` agrupara miles habría que mover orden y paginación al SP.
- **El puntaje no es un diagnóstico.** Es una heurística de gamificación sobre datos incompletos.
  Cualquier texto que la UI muestre junto a la carta debería dejarlo claro.

---

## 8 — Lo que **no** entra en esta spec

Persistir puntajes o su histórico · progresión y XP · enfrentamiento entre cartas y ranking con
posición · incidencias deportivas en la fórmula · paginación, autenticación y filtrado por
`perfiles_id` · tests automatizados · el frontend del juego · corregir los bugs heredados de
`SP_chequeos_club_prompt` y de los nombres de las columnas de presión.

Cada uno, si llega, va en su propia spec.
