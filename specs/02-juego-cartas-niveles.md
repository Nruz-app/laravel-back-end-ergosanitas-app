# SPEC 02 — Juego de cartas por niveles (SP_juego_cartas_club)

> **Estado:** Borrador
> **Depende de:** —
> **Fecha:** 2026-09-05
> **Objetivo:** Exponer tres endpoints GET que conviertan a cada paciente de un club en una "carta" con puntaje clínico 0–100, nivel ALTO/MEDIO/BAJO, estrellas 1–5 y porcentaje de completitud, calculados al vuelo por un procedimiento almacenado nuevo contra umbrales configurables en la tabla `juego_niveles`.

---

## 1 — Por qué existe esta spec

El HTML de referencia (`references/html/juego_cartas_evaluacion_pacientes.html`) pinta una
grilla de cartas de paciente con cuatro indicadores visuales que **no son el mismo dato**:

- un badge `ALTO` / `MEDIO` / `BAJO`,
- un filtro `inicial` / `evaluado` / `completo`,
- estrellas de 1 a 5,
- una barra de progreso con un porcentaje.

En el mock esos valores no correlacionan (Ana tiene 55% y es `BAJO`; Carlos tiene 25% y es
`MEDIO`), lo que confirma que son **dos ejes independientes**: uno mide la salud del paciente
y el otro cuánta información tiene cargada. Esta spec fija ambos ejes como cálculos
deterministas sobre los datos que ya existen, sin inventar tablas de juego ni mecánicas nuevas.

No hay nada equivalente hoy: `SP_ficha_clinica` consolida los datos de **un** paciente pero no
los evalúa, y `SP_chequeos_club_prompt` lista los de un club pero solo para alimentar al
asistente de IA. Esta spec toma de `SP_ficha_clinica` el **estilo** (un SP que devuelve una
única columna `resultado_json`) y de `SP_chequeos_club_prompt` la **firma**
(`p_search`, `p_club`).

### 1.1 Lo que se verificó contra la base real antes de escribir esta spec

Comprobado el 2026-09-05 contra `ergosan1_bdd` (MySQL 5.7.44-48). Estos hallazgos no son
contexto: condicionan la fórmula del §3.2.

| Hallazgo | Consecuencia |
| --- | --- |
| `cc.imc` está **vacío en 1.635 de 1.635 filas**; el IMC real vive en `imc_paciente` (301 vacíos) | El SP nuevo lee `imc_paciente`. `SP_chequeos_club_prompt` lee `cc.imc`, y por eso ese campo sale siempre vacío en el asistente: bug heredado que aquí no se replica. |
| `cc.pulso` está **vacío en 1.635 de 1.635 filas** | Se elimina como indicador. |
| `presionArterial` = 75 y `presion_sistolica` = 121 en la misma fila | Están **invertidos**: `presionArterial` guarda la diastólica y `presion_sistolica` la sistólica. |
| `bioimpedancia`: 7 filas, 2 de ellas con todos los indicadores en NULL | Con la regla "ausencia = 0" el bloque D da 0 al 99,5% de los pacientes. |
| `incidentes_deportivos`: 15 filas | Excluidas del puntaje por indicación expresa. |
| `estado_paciente`: `Normal` (2.985) / `Alterado` (393) | Binario limpio, sin valores raros. |
| `derivacion_paciente`: `na` (2.643), `No requiere` (286), `No` (39), el resto son derivaciones reales | El conjunto de valores "sin derivación" es conocido y cerrado. |
| Antecedentes: `No Presenta` / `Sin Alteraciones` / `Sin Ateraciones` (typo, 5 filas) = sano; cadena vacía = sin dato | Clasificación en tres estados, no en dos. |
| 2.065 ECG huérfanos; 1.313 chequeos con ECG; 1.336 con certificado | Justifica el `LEFT JOIN` en vez del `INNER JOIN` del SP hermano. |
| `estatura` llega a 162 (mezcla metros y centímetros), `temperatura` a 99, `frecuencia_cardiaca_paciente` va de 9 a 800 | Ninguno entra en la fórmula, pero confirma que todo `CAST` necesita rango de validez. |

**Datos de prueba:** club `brisas@ergosanitas.com` (116 pacientes), RUT `25527383-3`.
Otros clubes con volumen: `Colegio.altair@ergosanitas.com` (109),
`cobresal.buin@ergosanitas.com` (86), `ue.colina@ergosanitas.com` (78).

---

## 2 — Alcance

**Dentro:**

- `GET api/juego-cartas/{user_email}` — todas las cartas del club, con `?search=` opcional.
- `GET api/juego-cartas/detalle/{rut_paciente}` — una carta con el desglose de puntos.
- `GET api/juego-cartas/niveles` — la tabla de configuración, para que el front no hardcodee colores ni umbrales.
- SP nuevo `SP_juego_cartas_club(p_search, p_club)`, con su copia versionada en `base_datos/references/sp/`.
- Tabla nueva `juego_niveles` con su migración, incluida la siembra de las 6 filas iniciales.
- Modelos, service, provider y controlador nuevos siguiendo el vertical slice de `FichaClinica`.
- Actualización de `docs/openapi.yaml`, `README.md` y `CLAUDE.md` en el mismo commit.

**Fuera de alcance (para specs futuras):**

- **Persistir el puntaje**: no hay tabla snapshot ni histórico. Cada llamada recalcula.
- **Evolución en el tiempo**: graficar cómo cambió el puntaje de un paciente entre chequeos.
- **Ranking con posición**: no se devuelve `posicion_en_club` ni un top N. El listado va ordenado por puntaje y con eso basta para pintar la grilla.
- **Incidencias deportivas en el puntaje**: excluidas por indicación expresa. `incidentes_deportivos` no se consulta.
- **Paginación**: el club más grande tiene 116 pacientes y cabe en una respuesta.
- **Autenticación**: las rutas quedan públicas como el resto de `routes/api.php`.
- **Filtrado por `perfiles_id` 3/6**: el ámbito es siempre el `user_email` recibido.
- **Tests automatizados**: `phpunit.xml` apunta al MySQL remoto y en SQLite no existen los procedimientos almacenados.
- **Frontend**: el HTML de `references/` es la referencia visual; no se construye ni se versiona una página.
- **Corregir los bugs heredados detectados en §1.1** (`SP_chequeos_club_prompt` leyendo `cc.imc`, y los nombres invertidos de las columnas de presión). Se documentan y se esquivan; arreglarlos toca endpoints que ya están en producción.

---

## 3 — Modelo de datos

### 3.1 Tabla nueva `juego_niveles`

Una sola tabla para los **dos** ejes, discriminados por `tipo`. Así hay un único endpoint de
configuración y un único lugar donde retunear umbrales sin tocar el SP.

```php
// create_juego_niveles_table
Schema::create('juego_niveles', function (Blueprint $table) {
    $table->id();
    $table->string('tipo', 20);          // 'clinico' | 'completitud'
    $table->string('slug', 30);          // clase CSS del HTML de referencia
    $table->string('nombre', 30);        // etiqueta visible
    $table->integer('valor_min');        // clinico: 0-100 | completitud: 0-5
    $table->integer('valor_max');
    $table->string('color_fondo', 10);
    $table->string('color_texto', 10);
    $table->unsignedTinyInteger('orden');
    $table->boolean('activo')->default(true);
    $table->timestamps();

    $table->unique(['tipo', 'slug']);
    $table->index(['tipo', 'valor_min', 'valor_max']);
});
```

Filas sembradas por la propia migración (colores tomados del CSS del HTML de referencia):

| tipo | slug | nombre | valor_min | valor_max | color_fondo | color_texto | orden |
| --- | --- | --- | --- | --- | --- | --- | --- |
| clinico | bajo | BAJO | 0 | 39 | `#fee2e2` | `#dc2626` | 1 |
| clinico | medio | MEDIO | 40 | 74 | `#fef3c7` | `#b45309` | 2 |
| clinico | alto | ALTO | 75 | 100 | `#dcfce7` | `#15803d` | 3 |
| completitud | inicial | Inicial | 0 | 2 | `#e5e7eb` | `#6b7280` | 1 |
| completitud | evaluado | Evaluado | 3 | 4 | `#e0e7ff` | `#4f46e5` | 2 |
| completitud | completo | Completo | 5 | 5 | `#dcfce7` | `#15803d` | 3 |

Las bandas clínicas son los cortes "naturales" y se ajustan después con un `UPDATE` viendo la
distribución real (Paso 10). Ver §7: con la cobertura actual el techo práctico es 75.

### 3.2 Fórmula del puntaje clínico (0–100)

Cuatro bloques de **25 puntos** cada uno. **La ausencia de dato puntúa 0** — es la regla que
define el resto del diseño.

**Bloque A — Signos vitales (25 pts, 4 indicadores × 6,25)**

| Indicador | Columna | 6,25 pts | 3,75 pts | 1,25 pts | 0 pts |
| --- | --- | --- | --- | --- | --- |
| IMC | `cc.imc_paciente` | 18,5–24,9 | 17,0–18,4 o 25,0–29,9 | resto > 0 | vacío o 0 |
| Presión | `presion_sistolica` (sist.) / `presionArterial` (diast.) | sist < 120 y diast < 80 | sist 120–129 y diast < 80 | resto > 0 | vacío o 0 |
| Saturación O₂ | `cc.saturacionOxigeno` | ≥ 95 | 90–94 | > 0 y < 90 | vacío o 0 |
| Hemoglucotest | `cc.hemoglucotest` | 70–140 | 60–69 o 141–199 | resto > 0 | vacío o 0 |

`cc.pulso` **no entra**: está vacío en el 100% de las filas.
`cc.imc` **no se usa**: también está vacío en el 100% de las filas (§1.1).

**Bloque B — Electrocardiograma (25 pts)**

| Indicador | Regla | Máx |
| --- | --- | --- |
| `ec.estado_paciente` | `Normal` → 15 · `Alterado` → 5 · sin fila ECG → 0 | 15 |
| `ec.derivacion_paciente` | en (`na`, `No`, `No requiere`) o vacío → 10 · cualquier otro texto → 3 · sin fila ECG → 0 | 10 |

**Bloque C — Antecedentes (25 pts, 5 campos × 5)**

Campos: `enfermedadesCronicas`, `medicamentosDiarios`, `sistemaOsteoarticular`,
`sistemaCardiovascular`, `enfermedadesAnteriores`.

| Valor | Puntos |
| --- | --- |
| `No Presenta`, `Sin Alteraciones`, `Sin Ateraciones`, `No presenta`, `Ninguna`, `No` | 5 |
| cualquier otro texto no vacío (hay un hallazgo) | 2 |
| NULL o cadena vacía | 0 |

`Sin Ateraciones` es un typo real en la base (5 filas) y se acepta a propósito.
La comparación es insensible a mayúsculas y con `TRIM`.

**Bloque D — Bioimpedancia (25 pts)**

Se toma la fila más reciente de `bioimpedancia` por `rut`. Sin fila, o con los indicadores en
NULL, el bloque completo da 0.

| Indicador | 100% del sub-puntaje | 60% | 20% | Máx |
| --- | --- | --- | --- | --- |
| `puntaje_corporal` | ≥ 80 | 60–79 | > 0 | 7 |
| `grasa_corporal_pct` | Hombre 10–20 · Mujer 18–28 | ±5 fuera del rango | resto > 0 | 6 |
| `grasa_visceral` | < 10 | 10–14 | ≥ 15 | 6 |
| `smi` | Hombre ≥ 7,0 · Mujer ≥ 5,7 | — | > 0 | 6 |

El sexo sale de `bioimpedancia.sexo` (`Hombre` / `Mujer`); si es NULL se usa el rango de Hombre.

**Total** = `ROUND(A + B + C + D)`, entero entre 0 y 100.

### 3.3 Estrellas

`estrellas = LEAST(5, GREATEST(1, CEIL(puntaje / 20)))` — entero de 1 a 5, derivado del mismo
puntaje clínico que da el badge. Un puntaje de 0 muestra 1 estrella, nunca 0.

### 3.4 Eje de completitud (0–5 bloques)

| Bloque | Se cuenta cuando |
| --- | --- |
| `chequeo` | Siempre — existe la fila de `chequeo_cardiovascular` que origina la carta. |
| `signos_vitales` | `imc_paciente`, `presion_sistolica`, `presionArterial`, `saturacionOxigeno` y `hemoglucotest` están todos informados y > 0. |
| `ecg` | Existe fila en `electro_cardiogranas` por `(rut, id_chequeo)` con `estado_paciente` informado. |
| `bioimpedancia` | Existe fila en `bioimpedancia` con ese `rut`. |
| `certificado` | Existe fila en `certificado_url` por `(rut_paciente, id_chequeo)`. |

`progreso = bloques_completos * 20` (%). El estado (`inicial` / `evaluado` / `completo`) sale de
`juego_niveles` con `tipo = 'completitud'`. Como el bloque `chequeo` es siempre verdadero, el
progreso mínimo posible es 20%.

### 3.5 Firma y salida del SP

```sql
SP_juego_cartas_club(IN p_search VARCHAR(255), IN p_club VARCHAR(255))
```

- `p_club` informado → solo pacientes de ese `cc.user_email`.
- `p_club` NULL → sin filtro de club. **Existe únicamente para el endpoint de detalle por rut**; no se expone ningún listado global.
- `p_search` NULL o vacío → sin filtro; si no, `LIKE '%search%'` contra `cc.rut` y `cc.nombre`, igual que `SP_chequeos_club_prompt`.

Devuelve **una fila** con la columna `resultado_json`: un array (posiblemente vacío por el
`COALESCE(..., JSON_ARRAY())`) donde cada elemento es una carta:

```json
{
  "rut": "23503714-9",
  "nombre": "Matías Fuentes Rojas",
  "edad": "16",
  "sexo": "Masculino",
  "club": "brisas@ergosanitas.com",
  "id_chequeo": 1842,
  "ficha": "#001842",
  "fecha_atencion": "2026-08-14 10:32:00",
  "total_chequeos": 7,
  "puntaje": 62,
  "estrellas": 4,
  "nivel": { "slug": "medio", "nombre": "MEDIO", "color_fondo": "#fef3c7", "color_texto": "#b45309" },
  "bloques_completos": 3,
  "progreso": 60,
  "estado": { "slug": "evaluado", "nombre": "Evaluado", "color_fondo": "#e0e7ff", "color_texto": "#4f46e5" },
  "completitud": {
    "chequeo": true, "signos_vitales": true, "ecg": true,
    "bioimpedancia": false, "certificado": false
  },
  "desglose": {
    "signos_vitales": { "obtenido": 18.75, "maximo": 25 },
    "ecg":            { "obtenido": 25,    "maximo": 25 },
    "antecedentes":   { "obtenido": 18,    "maximo": 25 },
    "bioimpedancia":  { "obtenido": 0,     "maximo": 25 }
  }
}
```

`ficha` es `CONCAT('#', LPAD(cc.id, 6, '0'))`, para el `Ficha #001` del HTML.
`total_chequeos` es el `COUNT(*)` de chequeos de ese rut, para el pie `📋 7 chequeos`.
El `desglose` va en **todas** las cartas, no solo en el detalle: es una sola fórmula.

### 3.6 Cómo elige el SP la fila de cada paciente

Una carta por RUT, sobre su **chequeo más reciente**. MySQL 5.7 no tiene funciones de ventana,
así que se usa el idioma clásico:

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

ECG y bioimpedancia entran con `LEFT JOIN` acotado por un `MAX(id)` correlacionado, para no
duplicar cartas cuando hay más de una fila por chequeo o por rut:

```sql
LEFT JOIN electro_cardiogranas ec
    ON ec.id_chequeo = cc.id
   AND ec.rut_paciente = cc.rut
   AND ec.id = (SELECT MAX(e2.id) FROM electro_cardiogranas e2
                 WHERE e2.id_chequeo = cc.id AND e2.rut_paciente = cc.rut)
```

La clasificación contra `juego_niveles` va en el `SELECT` exterior, sobre una tabla derivada que
ya trae `puntaje` y `bloques_completos` calculados: en MySQL 5.7 no se puede referenciar un
alias del `SELECT` dentro de un `JOIN` del mismo nivel.

### 3.7 Contrato de los tres endpoints

Los tres usan el **sobre A** (`{success, message, data}`), el mismo de `FichaClinicaController`.

`GET api/juego-cartas/{user_email}?search=juan`

```json
{
  "success": true,
  "message": "Cartas obtenidas correctamente",
  "data": { "club": "brisas@ergosanitas.com", "search": "juan", "total": 3, "cartas": [] }
}
```

`GET api/juego-cartas/detalle/{rut_paciente}` → `data` es **una** carta con la forma de §3.5, o
`null` con `message: "Paciente sin chequeos registrados"` y status 200 si el rut no existe.

`GET api/juego-cartas/niveles` → `data` con las filas activas agrupadas por eje:

```json
{
  "success": true,
  "message": "Niveles obtenidos correctamente",
  "data": { "clinico": [], "completitud": [] }
}
```

Error (`catch`, 500): `{ "success": false, "message": "Error obteniendo las cartas", "error": "..." }`.

---

## 4 — Plan de implementación

Cada paso deja el sistema funcional. El orden importa: base primero, cableado después.

**Paso 1 — Migración y siembra de `juego_niveles`.**
Crear la migración de §3.1 con el `insert` de las 6 filas dentro del mismo `up()`, para que un
entorno nuevo quede utilizable con `php artisan migrate`. Verificar con `migrate:status` y
`SELECT * FROM juego_niveles`.

**Paso 2 — Escribir y crear el SP.**
`base_datos/references/sp/SP_juego_cartas_club.sql` con la fórmula de §3.2–§3.6, y crearlo en la
base. Probar directo con `CALL SP_juego_cartas_club(NULL, 'brisas@ergosanitas.com')` y
`CALL SP_juego_cartas_club('25527383-3', NULL)`. Sin CTEs ni funciones de ventana: la base es
MySQL 5.7.44.

**Paso 3 — Modelos.**

- `app/Models/JuegoCartaClub.php`: sin `$table` (solo envuelve el SP, igual que `FichaClinica`), con el wrapper estático:

```php
public static function SP_juego_cartas_club($search, $club) {
    return DB::select('CALL SP_juego_cartas_club(?, ?)', [$search, $club]);
}
```

- `app/Models/JuegoNivel.php`: `$table = 'juego_niveles'`, `$fillable` con las 8 columnas de negocio.

**Paso 4 — Service.**
`app/Services/JuegoCartasService.php`:

- `CartasClub(?string $search, string $club): array` — llama al wrapper, `json_decode` de `resultado_json`, **ordena en PHP por `puntaje` descendente** (ver §6) y devuelve el array de cartas.
- `CartaDetalle(string $rut): ?object` — `SP_juego_cartas_club($rut, null)` y devuelve el primer elemento o `null`.
- `Niveles(): array` — `JuegoNivel::where('activo', 1)->orderBy('orden')->get()->groupBy('tipo')`.

**Paso 5 — Provider.**
`app/Providers/JuegoCartasServiceProvider.php` con un `singleton()`, copiando
`FichaClinicaServiceProvider`, **y su línea en `bootstrap/providers.php`** en orden alfabético
(entre `IncidenciaServiceProvider` y `OpenAIServiceProvider`). Sin ese registro deja de ser
singleton, aunque el autowiring lo resuelva igual.

**Paso 6 — Controlador.**
`app/Http/Controllers/JuegoCartasController.php` con el service inyectado por constructor, los
tres métodos en `PascalCase` (`CartasClub`, `CartaDetalle`, `Niveles`) y todo el cuerpo en
`try/catch`: no hay handler global de excepciones en `bootstrap/app.php`.

**Paso 7 — Rutas.**
En `routes/api.php`, junto al bloque de `ficha-clinica`. **El orden es obligatorio**: las rutas
literales van antes de la que captura `{user_email}`, o `juego-cartas/niveles` se resolvería
con `user_email = "niveles"`.

```php
Route::get('juego-cartas/niveles',[JuegoCartasController::class,'Niveles'])
    ->name('Niveles');

Route::get('juego-cartas/detalle/{rut_paciente}',[JuegoCartasController::class,'CartaDetalle'])
    ->name('CartaDetalle');

Route::get('juego-cartas/{user_email}',[JuegoCartasController::class,'CartasClub'])
    ->name('CartasClub');
```

Más el `use App\Http\Controllers\JuegoCartasController;` arriba.

**Paso 8 — Documentación.**
Añadir las tres operaciones a `docs/openapi.yaml` con sus ejemplos, la fila del SP nuevo en las
tablas de SP de `README.md` y `CLAUDE.md`, y `juego_niveles` en la lista de tablas. El contrato
se mantiene a mano: contrastar con `php artisan route:list --json`.

**Paso 9 — Verificación.**
`php -l` de cada archivo tocado, `vendor/bin/pint --dirty`,
`php artisan config:clear && php artisan route:clear && php artisan cache:clear`
(**nunca `config:cache`**), `php artisan route:list --path=juego-cartas`, comprobación de
singleton con `tinker`, `php artisan test` y las pruebas de §5.

**Paso 10 — Ajuste de bandas.**
Con el SP ya corriendo, mirar la distribución real de puntajes del club de prueba y ajustar
`juego_niveles` con un `UPDATE` si todas las cartas caen en la misma banda. Sin tocar código
ni el procedimiento.

---

## 5 — Criterios de aceptación

- [ ] `php artisan route:list --path=juego-cartas` muestra exactamente las tres rutas, y `GET api/juego-cartas/niveles` **no** cae en la ruta `{user_email}`.
- [ ] `php artisan migrate:status` muestra la migración aplicada y `SELECT COUNT(*) FROM juego_niveles` devuelve 6.
- [ ] `bootstrap/providers.php` contiene `App\Providers\JuegoCartasServiceProvider::class` y `app(JuegoCartasService::class) === app(JuegoCartasService::class)` devuelve `true`.
- [ ] `CALL SP_juego_cartas_club(NULL, 'brisas@ergosanitas.com')` devuelve una fila con `resultado_json` y **exactamente una carta por RUT distinto** del club.
- [ ] **Postman A (listado):** `GET api/juego-cartas/brisas@ergosanitas.com` → 200, sobre A, `cartas` ordenado por `puntaje` descendente.
- [ ] **Postman B (search):** el mismo endpoint con `?search=25527383-3` → 200 con una sola carta, la de ese RUT.
- [ ] **Postman C (detalle):** `GET api/juego-cartas/detalle/25527383-3` → 200 con una carta cuyo `desglose` suma exactamente el `puntaje`.
- [ ] **Postman D (niveles):** `GET api/juego-cartas/niveles` → 200 con 3 filas en `clinico` y 3 en `completitud`.
- [ ] Un club inexistente → 200 con `total: 0` y `cartas: []`, sin error.
- [ ] Un RUT inexistente en `detalle` → 200 con `data: null`.
- [ ] Un paciente **sin fila de ECG** aparece en el listado, con `desglose.ecg.obtenido = 0` y `completitud.ecg = false`.
- [ ] Un paciente **sin bioimpedancia** aparece con `desglose.bioimpedancia.obtenido = 0` y su `puntaje` no supera 75.
- [ ] `estrellas` está siempre entre 1 y 5, y `progreso` es siempre múltiplo de 20 entre 20 y 100.
- [ ] `nivel` y `estado` nunca vienen `null`: las bandas de `juego_niveles` cubren 0–100 y 0–5 sin huecos.
- [ ] Cambiar `valor_min` de la fila `clinico/alto` con un `UPDATE` cambia el badge devuelto, **sin** modificar el SP ni el código PHP.
- [ ] `docs/openapi.yaml` contiene las tres operaciones y `README.md` la tabla `juego_niveles` y el SP nuevo.
- [ ] `vendor/bin/pint --dirty` sin cambios pendientes y `php artisan test` en verde.
- [ ] `GET api/ficha-clinica/{rut}` y `POST api/sam-assistant-club/as-question` siguen funcionando igual; no hay diff en sus archivos.

---

## 6 — Decisiones tomadas y descartadas

| Decisión | Por qué |
| --- | --- |
| **Dos ejes independientes**: puntaje clínico (badge + estrellas) y completitud (barra + filtro) | Es lo que muestra el HTML de referencia: Ana tiene 55% y es `BAJO`, Carlos tiene 25% y es `MEDIO`. Descartado unificarlos en un solo eje, que habría perdido la idea de "progreso de la ficha". |
| **Tabla de configuración + cálculo al vuelo** | Descartada la tabla snapshot: obliga a decidir cuándo recalcular y el dato queda viejo. Descartado hardcodear los umbrales en el SP: retunear exigiría modificar el procedimiento. |
| **Una sola tabla `juego_niveles` con columna `tipo`** | Los dos ejes tienen la misma forma (rango → etiqueta → colores). Dos tablas habrían duplicado el modelo, el provider y el endpoint. |
| **4 bloques de 25 puntos, ausencia = 0** | Decisión explícita del usuario, tomada sabiendo la cobertura real. Consecuencia asumida y documentada en §7: el techo práctico hoy es 75. |
| **Incidencias deportivas fuera del puntaje** | Indicación expresa del usuario. Además solo hay 15 filas: no discriminarían nada. |
| **`imc_paciente` en vez de `cc.imc`** | `cc.imc` está vacío en las 1.635 filas. `SP_chequeos_club_prompt` lo lee y por eso devuelve siempre vacío; no se replica el bug. |
| **`cc.pulso` eliminado como indicador** | Vacío en las 1.635 filas. Descartado sustituirlo por `ec.frecuencia_cardiaca_paciente`: habría acoplado el bloque de signos vitales a que exista ECG y contado el ECG dos veces. |
| **Presión leída "invertida" a propósito** | En la base `presion_sistolica` guarda la sistólica y `presionArterial` la diastólica. El SP lee cada una por su contenido real, no por su nombre. Renombrar las columnas rompería endpoints en producción. |
| **`LEFT JOIN` con ECG** | Descartado el `INNER JOIN` de `SP_chequeos_club_prompt`: dejaría fuera del listado a pacientes que el club sí cargó, y eso se reporta como bug. |
| **Un solo SP con `p_club` nullable** | Descartado un `SP_juego_carta_detalle` aparte: habría duplicado la fórmula del puntaje en dos procedimientos, con riesgo de que divergieran en el primer ajuste. |
| **Orden por puntaje en PHP, no en el SP** | En MySQL 5.7 `JSON_ARRAYAGG` **no respeta `ORDER BY`**: el orden de los elementos del array no está garantizado. El `usort` del service es el único punto donde el orden es determinista. |
| **`search` en el SP, sin paginación** | Mismo contrato que `SP_chequeos_club_prompt`. El club más grande tiene 116 pacientes. |
| **Sobre A (`{success, message, data}`)** | Es el que usa `FichaClinicaController`, el vertical slice que esta spec replica. El repo tiene dos sobres incompatibles y no hay uno canónico. |
| **Bandas 0-39 / 40-74 / 75-100, ajustables después** | Se siembran los cortes naturales y se ajustan con un `UPDATE` tras ver la distribución real. Por eso los umbrales viven en una tabla y no en el SP. |
| **Carta sobre el chequeo más reciente** | Descartado el promedio histórico (diluye una mejora reciente) y el mejor histórico (oculta un deterioro actual). |
| **El `desglose` va en todas las cartas** | Descartado devolverlo solo en el detalle: obligaría a una segunda fórmula o a una segunda consulta. |
| **Sin ranking con posición** | El listado ordenado por puntaje ya permite pintar la grilla. Una posición estable exige decidir empates y ámbito; es otra spec. |

---

## 7 — Riesgos identificados

- **El techo práctico del puntaje es 75, no 100.** Con 4 bloques de 25 y ausencia = 0, un paciente sin bioimpedancia no puede pasar de 75 — y hoy eso son 1.547 de 1.554 pacientes. Con la banda `alto` empezando en 75, solo un paciente perfecto llega a `ALTO`. Es una consecuencia directa y aceptada de las reglas elegidas; la mitigación es el Paso 10 (ajustar `juego_niveles` con un `UPDATE`, sin tocar código).
- **`bioimpedancia` tiene 7 filas y 2 de ellas están completamente en NULL.** El bloque D dará 0 casi siempre, incluso para pacientes que sí tienen una fila.
- **Los datos numéricos vienen sucios.** `estatura` mezcla metros y centímetros (max 162), `temperatura` llega a 99 y `frecuencia_cardiaca_paciente` va de 9 a 800. Ninguno de esos tres entra en la fórmula, pero confirma que todo `CAST` necesita un rango de validez y que los valores fuera de rango deben tratarse como "sin dato".
- **2.065 ECG son huérfanos** (su `id_chequeo` no existe en `chequeo_cardiovascular`). El `LEFT JOIN` por `(rut, id_chequeo)` los ignora, que es lo correcto, pero explica por qué hay 3.378 ECG y solo 1.313 chequeos con ECG.
- **El SP no queda versionado en las migraciones**, igual que el resto. Vive en la base y su copia está en `base_datos/references/sp/`. Un entorno nuevo levantado solo con `php artisan migrate` tendrá la tabla pero no el procedimiento, y los tres endpoints devolverán 500 desde el `catch`. Confirmar el despliegue del SP en producción antes de dar los endpoints por disponibles.
- **`GROUP_CONCAT` tiene un límite de 1.024 bytes por defecto.** Se usa para elegir el último chequeo por RUT. Con los datos actuales (pocos chequeos por paciente) no se alcanza, pero si algún RUT acumulara ~100 chequeos el id elegido podría ser incorrecto **sin dar error**.
- **Sin control de acceso.** Cualquiera que conozca el email de un club puede leer el puntaje clínico de sus pacientes, y `detalle/{rut}` ni siquiera exige club. Es el mismo riesgo que el resto de `routes/api.php` y está fuera de alcance, pero aquí se expone una evaluación de salud, no solo datos.
- **Ordenar en PHP asume que el club cabe en memoria.** Con 116 pacientes es trivial; si algún día un `user_email` agrupara miles, habría que mover el orden y la paginación al SP.
- **El puntaje no es un diagnóstico.** Es una heurística de gamificación sobre datos incompletos. Cualquier texto que la UI muestre junto a la carta debería dejarlo claro.

---

## 8 — Lo que **no** entra en esta spec

- Persistir puntajes o su histórico.
- Ranking con posición dentro del club.
- Incidencias deportivas en la fórmula.
- Paginación, autenticación y filtrado por `perfiles_id`.
- Tests automatizados.
- El frontend del juego.
- Corregir los bugs heredados de `SP_chequeos_club_prompt` y de los nombres de las columnas de presión.

Cada uno, si llega, va en su propia spec.
