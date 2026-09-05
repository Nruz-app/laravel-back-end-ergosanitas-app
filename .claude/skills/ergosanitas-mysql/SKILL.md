---
name: ergosanitas-mysql
description: Referencia de MySQL 5.7 para la base de datos de Ergosanitas. Úsala para escribir u optimizar queries y joins, diagnosticar con EXPLAIN, diseñar índices, crear o modificar procedimientos almacenados, escribir migraciones, inspeccionar el esquema real (que va por delante de las migraciones del repo) y resolver problemas de tipos varchar, charset o integridad sin foreign keys.
argument-hint: 'la query, el SP o el problema de base de datos'
allowed-tools: Read, Write, Edit, Glob, Grep, Bash, AskUserQuestion
---

# MySQL 5.7 en Ergosanitas

Dos cosas mandan sobre todo lo demás: **la versión es MySQL 5.7.22** y **el esquema real está por delante del repo**. Casi todos los errores en esta base vienen de olvidar una de las dos.

---

## 1. Límite duro: MySQL 5.7.22

Versión declarada en `docker-compose.yml` y `dockerHub/docker-compose.yml`. Confírmala antes de proponer sintaxis moderna:

```bash
php artisan tinker --execute="print_r(DB::select('SELECT VERSION() as v'));"
```

**No existen en 5.7** — usarlos es un error de sintaxis, no un detalle de estilo:

| No disponible | Alternativa en 5.7 |
|---|---|
| `WITH ... AS` (CTE) | Subquery derivada `( SELECT ... ) as alias` |
| `ROW_NUMBER()`, `RANK()`, `LAG()`, `OVER()` | `MAX(id)` + `GROUP BY` + `JOIN`, o subquery correlacionada |
| `JSON_TABLE()` | Devolver JSON armado desde el SP y decodificar en PHP |
| Índices funcionales | Columna generada + índice, o rediseñar la query |
| `DEFAULT (expresión)` | Valor literal, o `useCurrent()` para timestamps |
| `SKIP LOCKED`, `NOWAIT` | No hay equivalente |

Sí están disponibles: columnas generadas, tipo `JSON` con `JSON_EXTRACT` / `->>`, `ON DUPLICATE KEY UPDATE`, índices sobre prefijo de varchar.

---

## 2. Inspeccionar el esquema real (siempre primero)

Las migraciones **mienten por omisión**. Existen solo en la BD:

- **Tablas sin migración**: `users_metadata`, `params`, `electro_cardiogranas`, `pago_mensual`
- **Todos los procedimientos almacenados**
- **Columnas sin migración** en tablas que sí la tienen: `chequeo_cardiovascular.status`, `.user_email`, `.fecha_atencion`; `certificado_url.id_chequeo`, `.derivado_medico`

```bash
# tablas reales
php artisan tinker --execute="print_r(DB::select('SHOW TABLES'));"

# definición completa de una tabla (tipos, índices, charset, engine)
php artisan tinker --execute="print_r(DB::select('SHOW CREATE TABLE chequeo_cardiovascular'));"

# columnas
php artisan tinker --execute="print_r(DB::select('SHOW FULL COLUMNS FROM certificado_url'));"

# índices existentes
php artisan tinker --execute="print_r(DB::select('SHOW INDEX FROM chequeo_cardiovascular'));"

# procedimientos
php artisan tinker --execute="print_r(DB::select('SHOW PROCEDURE STATUS WHERE Db = DATABASE()'));"
php artisan tinker --execute="print_r(DB::select('SHOW CREATE PROCEDURE SP_ficha_clinica'));"

# a qué base estás apuntando realmente
php artisan tinker --execute="print_r(DB::select('SELECT DATABASE() as db, VERSION() as v'));"
```

Con Docker local: MySQL en `:3337`, phpMyAdmin en `:8383` (`docker compose up -d --build`).

**`php artisan migrate` sobre una base vacía deja el esquema incompleto.** No lo uses como forma de "reconstruir producción".

---

## 3. El esquema en la práctica

### Claves y relaciones

- **El RUT es la clave de negocio.** `varchar(20)`, formato `12345678-9`, validado en PHP con `/^\d{7,8}-[0-9kK]$/` (sin dígito verificador).
- **No hay foreign keys.** Las tablas se cruzan por `(rut_paciente, id_chequeo)` o por `rut`:

```sql
ON cc.rut = cu.rut_paciente AND cc.id = cu.id_chequeo
```

- Nada garantiza integridad referencial. Por eso el código usa `LEFT JOIN` y `COALESCE(ec.campo, '-')`. Si escribes un `INNER JOIN` donde el repo usa `LEFT`, estás cambiando el conjunto de resultados — asegúrate de que es intencional.

### Tipos: casi todo es varchar

`chequeo_cardiovascular` guarda como `varchar` incluso lo numérico: `edad(3)`, `estatura(10)`, `peso(10)`, `hemoglucotest(3)`, `pulso(3)`, `presionArterial(15)`, `saturacionOxigeno(15)`, `temperatura(5)`, `imc(4)`.

Consecuencias que debes tener presentes al escribir SQL:

```sql
-- MAL: ordena alfabéticamente ('9' > '80')
ORDER BY peso DESC

-- BIEN
ORDER BY CAST(peso AS DECIMAL(10,2)) DESC

-- MAL: falla o da 0 si hay '' o '-' en la columna
AVG(imc)

-- BIEN: filtra lo no numérico antes de agregar
AVG(CASE WHEN imc REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN CAST(imc AS DECIMAL(10,2)) END)
```

Con `strict => true` en `config/database.php`, un `CAST` inválido o un valor demasiado largo lanza error en vez de truncar silenciosamente.

### Estados

`chequeo_cardiovascular.status` es **texto libre**: `ingresado` → `Testiado` → `ECG FOTO` → `REVISION MEDICA` (respeta mayúsculas y acentos tal cual). Orden de negocio:

```sql
ORDER BY FIELD(cc.status, 'ECG FOTO', 'REVISION MEDICA', 'Testiado', 'ingresado')
```

### Charset

`utf8mb4` / `utf8mb4_unicode_ci`. Si una tabla vieja quedó en `utf8mb3`, un join entre columnas de collation distinta impide usar el índice — compruébalo con `SHOW CREATE TABLE` antes de culpar a la query.

---

## 4. Patrón clave del repo: último ECG por chequeo

Es el "greatest-n-per-group" de MySQL 5.7, y en el código conviven **dos variantes**. Usa la segunda como referencia para código nuevo.

**Variante A — subquery correlacionada** (`SearchChequeo`). Legible, pero se evalúa por fila:

```php
$subEC = DB::table('electro_cardiogranas as e1')
    ->select('e1.id_chequeo', 'e1.rut_paciente', 'e1.estado_paciente', /* ... */)
    ->whereRaw('e1.id = (
        SELECT e2.id
        FROM electro_cardiogranas e2
        WHERE e2.id_chequeo = e1.id_chequeo
        ORDER BY e2.created_at DESC
        LIMIT 1
    )');

$query = DB::table('chequeo_cardiovascular as cc')
    ->leftJoinSub($subEC, 'ec', function ($join) {
        $join->on('cc.id', '=', 'ec.id_chequeo');
    });
```

**Variante B — agregación + join** (`ChequeoEmailAll`). Una sola pasada, es la que escala:

```sql
SELECT e1.id_chequeo, e1.estado_paciente, e1.frecuencia_cardiaca_paciente
FROM electro_cardiogranas e1
INNER JOIN (
    SELECT id_chequeo, MAX(id) AS max_id
    FROM electro_cardiogranas
    GROUP BY id_chequeo
) e2 ON e1.id = e2.max_id
```

Diferencia sutil: A desempata por `created_at DESC`, B por `MAX(id)`. Con varios ECG del mismo chequeo insertados en el mismo segundo pueden devolver filas distintas. Si unificas, decide el criterio y dilo.

⚠️ En 5.7 una tabla derivada dentro de `LEFT JOIN` se **materializa sin índice**. Si crece, es el primer sitio a mirar en un `EXPLAIN`.

---

## 5. Optimizar: EXPLAIN y log de queries

```bash
# ver el SQL y los bindings que genera el Query Builder
php artisan tinker --execute="
DB::enableQueryLog();
app(App\Services\ChequeoCardiovascularService::class)->ChequeoEmailAll('correo@club.cl', 1);
print_r(DB::getQueryLog());
"

# EXPLAIN de una query concreta
php artisan tinker --execute="print_r(DB::select('EXPLAIN SELECT ...'));"
```

Qué mirar en 5.7: `type: ALL` (full scan), `key: NULL` (sin índice), `Using temporary` + `Using filesort` juntos, `rows` desproporcionado, y `DERIVED` materializado.

Candidatos naturales a índice en este esquema — **verifica primero cuáles ya existen** con `SHOW INDEX`:

| Tabla | Columnas | Por qué |
|---|---|---|
| `chequeo_cardiovascular` | `rut` | join con ECG y búsquedas por paciente |
| `chequeo_cardiovascular` | `user_email` | filtro de perfil 3 en tres queries |
| `chequeo_cardiovascular` | `status` | filtro de perfil 6 y ordenamiento |
| `electro_cardiogranas` | `id_chequeo`, `rut_paciente` | ambas variantes del último-ECG |
| `certificado_url` | `(rut_paciente, id_chequeo)` | join del perfil médico y borrado en `subirCertificado` |

Antes de proponer un `ALTER TABLE ... ADD INDEX`, avisa del bloqueo y del tamaño de la tabla. No lo ejecutes sin confirmación.

`FIELD()` en `ORDER BY` y `LOWER(nombre) LIKE '%...%'` (en `ServiciosController`) **impiden usar índice** por definición. Es aceptable en tablas chicas; no lo es en las grandes.

---

## 6. Procedimientos almacenados

Gran parte de las estadísticas vive en MySQL, no en PHP. **Ninguno está versionado en el repo.**

### Inventario

| Modelo | SPs |
|---|---|
| `ChequeoCardiovascular` | `SP_estadistica_IMC(?)`, `SP_estado_general(?)`, `sp_estadistica_presion(?)`, `SP_estadistica_hemoglucotest(?)`, `SP_estadistica_monto()`, `SP_pago_mensual(?,?,?,?)` |
| `IncidentesDeportivos` | `SP_estadistica_liga(?)`, `SP_estadistica_categoria(?)`, `SP_estadistica_lesiones(?)`, `SP_estadistica_parte_cuerpo(?)`, `SP_estadistica_lesiones_fechas(?)` |
| `PagoMensual` | `SP_agenda_mensual(?)`, `SP_estadistica_monto_mdc()`, `SP_update_pago_mensual(?)`, `SP_chequeos_prompt(?)` |
| `FichaClinica` | `SP_ficha_clinica(?)` → devuelve columna `resultado_json` |
| `Bioimpedancia` | `SP_bioimpedacia_rut(?)` (nombre con typo: *bioimpedacia*) |

Nota: `sp_estadistica_presion` va en minúsculas, a diferencia del resto.

### Wrapper en el modelo

```php
public static function SP_ficha_clinica($param1) {
    return DB::select('CALL SP_ficha_clinica(?)', [$param1]);
}
```

`DB::select('CALL ...')` devuelve **siempre un array de `stdClass`**, incluso para una fila. De ahí el `$results[0]->resultado_json` de `FichaClinicaService`.

### Escribir o modificar un SP

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS SP_x$$

CREATE PROCEDURE SP_x(IN p_rut VARCHAR(20))
BEGIN
    SELECT cc.id, cc.nombre, cc.status
    FROM chequeo_cardiovascular cc
    WHERE cc.rut = p_rut
    ORDER BY cc.id DESC;
END$$

DELIMITER ;
```

Reglas al tocar SPs:

1. **Guarda primero el original**: `SHOW CREATE PROCEDURE SP_x` y pégalo en la respuesta o en un archivo, porque no hay historial.
2. Nombra los parámetros con prefijo (`p_rut`) para no colisionar con nombres de columna — dentro de un SP, `WHERE rut = rut` siempre es verdadero.
3. Un SP que devuelve JSON debe entregarlo en una columna llamada `resultado_json` si el servicio PHP lo espera así.
4. Si cambias la firma (número o tipo de parámetros), **hay que actualizar el wrapper del modelo en el mismo cambio** y decírselo al usuario: el SP se aplica a mano en la BD y no aparece en el diff.
5. `SP_pago_mensual`, `SP_update_pago_mensual` y `SP_agenda_mensual` son **facturación**. Avisa antes de tocarlos.

---

## 7. Migraciones

```bash
php artisan migrate:status
php artisan migrate
```

Estilo del repo: `Schema::create` con `$table->string("campo", largo)` y timestamps explícitos donde hace falta:

```php
$table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
```

Criterio para decidir si escribir migración:

- **Tabla nueva creada por ti** → sí, escribe migración.
- **Columna nueva en tabla con migración** → sí, `Schema::table(...)->after('otra')` con su `down()`, como hacen `2024_09_25_...update` y `2024_09_26_...add`.
- **Tabla que solo existe en la BD** (`users_metadata`, `params`, `electro_cardiogranas`, `pago_mensual`) → **no improvises una migración** que "reconstruya" lo que ya existe: si no coincide exactamente con producción, romperás entornos. Si el usuario quiere versionarlas, genera la migración **a partir del `SHOW CREATE TABLE` real** y díselo.
- **Procedimientos almacenados** → no se versionan hoy. Si el usuario quiere hacerlo, lo idiomático es una migración con `DB::unprepared(...)`; propónlo como decisión, no como paso rutinario.

---

## 8. Escrituras: precauciones

- El código no usa transacciones. `CertificadoService::subirCertificado()` hace **borrado + update de status + inserción de facturación** sin `DB::transaction()`: un fallo a mitad deja estado inconsistente. Si te piden endurecerlo, envolverlo en `DB::transaction()` es el fix correcto — pero cambia el comportamiento ante error, así que dilo.
- Cualquier `UPDATE`/`DELETE` que propongas debe llevar `WHERE` acotado y mostrarse antes de ejecutar. Pide confirmación.
- Prueba primero la versión `SELECT` de la misma condición para ver cuántas filas afecta:

```sql
SELECT COUNT(*) FROM chequeo_cardiovascular WHERE <la misma condición>;
```

---

## 9. Checklist de cierre

- [ ] Sintaxis válida en **MySQL 5.7** (sin CTE, sin funciones de ventana)
- [ ] Esquema verificado contra la BD viva, no contra `database/migrations/`
- [ ] `CAST` aplicado donde se compara o agrega una columna `varchar` numérica
- [ ] `LEFT` vs `INNER JOIN` elegido conscientemente (no hay foreign keys)
- [ ] `EXPLAIN` revisado si hay join o subquery
- [ ] Índices propuestos, no ejecutados sin confirmación
- [ ] SP: original guardado antes de reemplazarlo; wrapper del modelo actualizado si cambió la firma
- [ ] Avisado si el cambio toca facturación (`SP_pago_mensual`, `SP_update_pago_mensual`, `SP_agenda_mensual`)
- [ ] Reportado contra qué base se ejecutó cada cosa y qué quedó sin verificar
