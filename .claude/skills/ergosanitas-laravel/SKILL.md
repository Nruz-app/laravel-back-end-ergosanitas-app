---
name: ergosanitas-laravel
description: Referencia de PHP 8.2 y Laravel 11 aplicada a Ergosanitas. Úsala para resolver dudas del framework en este repo (contenedor y providers, ciclo de vida en el skeleton slim de Laravel 11, rutas, validación, Eloquent vs Query Builder, los tres mecanismos de autenticación, correo, colas, PHPUnit 11 y Pint) y para decidir qué refactor idiomático es seguro y cuál rompe las convenciones del proyecto.
argument-hint: 'la duda de PHP/Laravel o el refactor que estás evaluando'
allowed-tools: Read, Write, Edit, Glob, Grep, Bash, AskUserQuestion
---

# PHP 8.2 + Laravel 11 en Ergosanitas

Esta skill responde dos preguntas: **cómo se hace en Laravel 11** y **cómo se hace aquí**. Cuando difieren, gana el repo salvo que el usuario decida lo contrario — y en ese caso dilo explícitamente.

Para implementar módulos completos usa la skill `ergosanitas-dev`. Esta es la referencia de framework y lenguaje.

---

## 1. Verifica antes de afirmar

```bash
composer show laravel/framework      # versión real instalada
composer show --direct               # todo el stack directo
php -v                               # 8.2.12 local
php artisan --version
php artisan about                    # entorno, drivers, cachés activas
```

Nunca afirmes que una API existe por recordarla: 11.x cambió mucho respecto a 10.x. Si dudas, `grep` en `vendor/laravel/framework/src`.

---

## 2. Laravel 11 slim — el mapa real

| Lo que buscas | Dónde está aquí |
|---|---|
| Kernel HTTP / Console | **No existen.** Todo en `bootstrap/app.php` |
| Middleware global | `withMiddleware()` — **closure vacío**, y no existe `app/Http/Middleware/` |
| Handler de excepciones | `withExceptions()` — **closure vacío** |
| Providers | `bootstrap/providers.php` (array plano, registro manual) |
| Rutas API | `routes/api.php`, prefijo `/api` automático |
| Health check | `/up`, declarado en `withRouting(health: '/up')` |
| Comandos | `routes/console.php` |

```php
// bootstrap/app.php — estado real
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

**Consecuencia de diseño**: sin handler global, *cada* acción de controlador es responsable de su `try/catch` y de su sobre JSON. No es un olvido a corregir de paso.

---

## 3. Contenedor de servicios y providers

Registro manual, sin auto-discovery para los servicios del proyecto:

```php
// app/Providers/XServiceProvider.php
$this->app->singleton(XService::class, function ($app) {
    return new XService();
});
```

```php
// bootstrap/providers.php  ← sin esta línea NO es singleton
App\Providers\XServiceProvider::class,
```

- Si omites el provider, Laravel **igual resuelve** el servicio por autowiring cuando el constructor no tiene dependencias — pero se instancia en cada inyección. Registrarlo es lo que lo hace singleton.
- Dependencia entre servicios: único precedente, `CertificadoProvider` pasando `EstadisticasService` a `CertificadoService` (`$app->make(...)`).
- Nombres inconsistentes por diseño histórico: `CertificadoProvider` vs `BioimpedanciaServiceProvider`. Sigue el vecino del dominio.

Comprobar que quedó singleton:

```bash
php artisan tinker --execute="var_dump(app(App\Services\XService::class) === app(App\Services\XService::class));"
```

---

## 4. Los tres mecanismos de autenticación

Conviven, y ninguno protege las rutas de negocio:

| Mecanismo | Dónde |
|---|---|
| `Auth::attempt()` + sesión | `Auth/UserController::AuthRegister` — el login real |
| Sanctum | solo la ruta de ejemplo `/user` (`->middleware('auth:sanctum')`) |
| JWT (`php-open-source-saver/jwt-auth`) | guard `api` en `config/auth.php` |
| Google OAuth | `google/apiclient` directo en `GoogleAuthControlle` (no Socialite), con `env('GOOGLE_CLIENT_ID')`/`env('GOOGLE_CLIENT_SECRET')` leídos en el propio controlador |

**Las rutas de negocio de `routes/api.php` no llevan middleware de autenticación.** `user_email` y `perfiles_id` llegan como parámetros del request. Por lo tanto **el filtrado por perfil es presentación, no control de acceso** — no lo describas como seguridad ni lo uses como si lo fuera.

`/user` está declarado **dos veces** (líneas 7 y 44). Laravel se queda con la última.

Usuarios en dos tablas: `users` (Laravel, password hasheada) y `users_metadata` (perfil, logo, rut, `ergo_pass`, y una copia **en claro** de la contraseña). `UserMetadataService::userSave()` y `UserUpdatePassowrd()` escriben en ambas y deben mantenerse sincronizadas. Si vas a tocar contraseñas, menciónale al usuario que la copia en claro es un riesgo — pero no la elimines sin que lo pida: hay clientes que dependen de ella.

---

## 5. Eloquent vs Query Builder — cuál usar

Regla observada en el repo:

- **Eloquent** para CRUD simple sobre una tabla: `AgendaHoras`, `Servicios`, `Params`, `LogsApi`, `CertificadoURl`.
- **Query Builder** (`DB::table('x as cc')`) para todo lo que tenga joins, subqueries, filtros por perfil o formateo SQL: `ChequeoCardiovascularService`.
- **`DB::select('CALL SP_x(?)')`** para estadísticas y agregados: viven en MySQL, no en PHP.

Modelos configurados: `$table` explícito en casi todos (los nombres no siguen la pluralización de Laravel), `public $timestamps = false` en `Perfiles` y `UsersMetadata`, `$fillable` solo en `User`, `ChatHistory` y `ChatSessions`. **`FichaClinica` tiene `$table` comentado a propósito**: solo envuelve `SP_ficha_clinica`, no mapea tabla.

Sin `$fillable`, no uses `create()`/`fill()` con arrays: asigna propiedad por propiedad como hace `AgendaHorasController::StoreAgenda`, o añade `$fillable` conscientemente.

---

## 6. Validación y lectura del request

El repo **casi no valida**. Lee `$request->campo` directo y confía. Al añadir código nuevo:

- Puedes usar `$request->validate([...])` — es lo correcto y no rompe nada — pero recuerda que **sin handler global** una `ValidationException` sale con el formato por defecto de Laravel (422 con `{message, errors}`), que **no** es ninguno de los dos sobres del proyecto. Si el endpoint debe ser consistente, captura y reformatea:

```php
try {
    $datos = $request->validate([...]);
} catch (\Illuminate\Validation\ValidationException $e) {
    return response()->json([
        'success' => false,
        'message' => 'Datos inválidos',
        'error'   => $e->errors()
    ], 422);
}
```

- **Antipatrón presente, no lo copies**: `AgendaHorasController::StoreAgenda` hace `json_decode(file_get_contents('php://input'), true)` solo para comprobar que el body es un array, y `WebPayController::WebPayResponse` lee `$_GET['token_ws']` en vez de `$request->query('token_ws')`. Úsalo como referencia de *qué existe*, no de qué escribir.

---

## 7. PHP 8.2 — qué puedes usar

El lenguaje permite readonly properties, enums, constructor property promotion, `never`, argumentos con nombre, first-class callables. **El repo no usa casi nada de eso**: estilo procedural, `array(...)` en vez de `[]` en partes, propiedades `protected $x` declaradas y asignadas en el constructor.

Criterio: en **código nuevo aislado**, usar features modernas está bien si no choca visualmente con el archivo. En **código existente**, iguala el estilo del archivo. Nunca introduzcas un enum o un DTO para reemplazar strings de negocio (`status`, perfiles) sin pedirlo: esos valores viajan a la BD y a los SP como texto libre.

---

## 8. Configuración, `env()` y la trampa del caché

```bash
php artisan config:clear && php artisan route:clear && php artisan cache:clear
```

**Nunca `php artisan config:cache`.** Estos `env()` viven fuera de `config/` y devolverían `null`:

| Variable | Dónde se lee |
|---|---|
| `API_PATH_CER` | `CertificadoService`, `CertificadoUrlController` |
| `API_PATH_LOGO` | `Auth/UserController` — **falta en `.env.example`** |
| `WEBPAY_URL/ID/SECRET/RETURN` | `WebPayController` |
| `GOOGLE_CLIENT_ID/SECRET` | `GoogleAuthControlle` |

Por eso `dockerHub/entrypoint.sh` hace `config:clear` en cada arranque.

Ojo también: `.env.example` trae `DB_CONNECTION=sqlite` con las líneas MySQL comentadas — no refleja el entorno real.

**El fix correcto**, si el usuario quiere poder cachear config algún día: mover esos valores a un archivo de `config/` y leerlos con `config('x.y')`. Es un cambio transversal; propónlo, no lo hagas de paso.

---

## 9. Tests con PHPUnit 11

```bash
php artisan test
php artisan test --testsuite=Feature
php artisan test --filter=NombreDelTest
vendor/bin/phpunit tests/Feature/ExampleTest.php
```

Hoy solo existen los stubs de Laravel. Para escribir tests con BD, primero descomenta en `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Sin eso, los tests golpean el **MySQL remoto de `.env`**. Y con eso, **no existen los procedimientos almacenados** en SQLite: todo lo que dependa de `SP_*` no es testeable ahí. Estrategia realista:

- Testea servicios cuya lógica sea PHP puro (formateo, armado de HTML, validación de RUT en `ChequeoImport`).
- Para lo que llama SPs, mockea el modelo o extrae la lógica no-SQL a un método aparte.
- No prometas cobertura de estadísticas: viven en MySQL.

---

## 10. Formateo

```bash
vendor/bin/pint --dirty                          # solo lo modificado
vendor/bin/pint app/Services/BioimpedanciaService.php
```

No hay `pint.json`, así que aplica el preset `laravel`, que el código existente **no cumple**. `vendor/bin/pint` sin argumentos reformatea medio repositorio y entierra el cambio real en el diff.

---

## 11. Refactors: cuáles son seguros y cuáles no

| Refactor | Veredicto |
|---|---|
| Extraer lógica de un controlador a su Service | ✅ Seguro y alineado con el patrón |
| Añadir `try/catch` faltante | ✅ Seguro |
| Añadir validación con reformateo del error al sobre del endpoint | ✅ Seguro |
| Registrar un provider que faltaba | ✅ Seguro |
| Renombrar métodos a camelCase | ❌ Rompe rutas y convención deliberada |
| Unificar los dos sobres de respuesta | ❌ Rompe clientes que ya consumen |
| Añadir handler global en `withExceptions()` | ⚠️ Cambia el formato de error de todos los endpoints. Solo si el usuario lo pide |
| Añadir middleware de auth a rutas de negocio | ⚠️ Cambio funcional mayor. Solo si el usuario lo pide |
| Corregir `GoogleAuthControlle` / `UserUpdatePassowrd` / `electro_cardiogranas` | ❌ Typos consolidados; requiere tocar rutas, BD y clientes |
| Mover `env()` a `config/` | ⚠️ Correcto a futuro, transversal. Propónlo |
| Eliminar `phpmailer` de `composer.json` | ⚠️ No se usa, pero es limpieza fuera de alcance. Menciónalo |
| Migrar subidas de `public/` a `storage/` | ⚠️ Correcto (producción pierde `public/` al redeploy) pero cambia URLs públicas |

---

## 12. Antes de cerrar

```bash
php -l <archivo tocado>
vendor/bin/pint --dirty
php artisan config:clear && php artisan route:clear && php artisan cache:clear
php artisan route:list --path=<prefijo>
php artisan test
```

Y reporta: qué corriste de verdad, qué no pudiste verificar (SPs, endpoints con datos reales, facturación) y qué antipatrón detectaste pero dejaste intacto por estar fuera de alcance.
