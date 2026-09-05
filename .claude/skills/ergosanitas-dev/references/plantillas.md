# Plantillas — dominio nuevo en Ergosanitas

Copiadas del estilo real del repo (referencia viva: el vertical slice de `FichaClinica`).
Sustituye `X` / `x` por el nombre del dominio. Todo en español.

---

## 1. Modelo — `app/Models/X.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class X extends Model
{
    use HasFactory;

    protected $table = 'x';

    // Wrapper de procedimiento almacenado (el SP no está versionado en el repo)
    public static function SP_x($param1) {
        return DB::select('CALL SP_x(?)', [$param1]);
    }
}
```

Si el modelo **solo** envuelve un SP y no mapea una tabla, deja `$table` comentado como en `FichaClinica`.

---

## 2. Service — `app/Services/XService.php`

```php
<?php

namespace App\Services;

use App\Models\X;
use Illuminate\Support\Facades\DB;

class XService
{
    public function ObtenerX($rut_paciente)
    {
        $results = X::SP_x($rut_paciente);

        return json_decode($results[0]->resultado_json);
    }

    public function ListarX($perfilId, $user_email)
    {
        $query = DB::table('x as cc')
            ->select('cc.id', 'cc.rut', 'cc.status');

        // Perfil 3 (club deportivo): solo sus propios registros
        if ($perfilId == 3) {
            $query->where('cc.user_email', $user_email);
        }

        return $query->get();
    }
}
```

Toda la lógica de negocio, queries y generación de documentos van aquí — nunca en el controlador.

---

## 3. Provider — `app/Providers/XServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\XService;

class XServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(XService::class, function ($app) {
            return new XService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
```

Con dependencia entre servicios (patrón de `CertificadoProvider`):

```php
$this->app->singleton(XService::class, function ($app) {
    return new XService($app->make(EstadisticasService::class));
});
```

**Registrarlo en `bootstrap/providers.php`** (orden alfabético):

```php
return [
    App\Providers\AppServiceProvider::class,
    // ...
    App\Providers\XServiceProvider::class,
];
```

Sin esta línea, Laravel resuelve el servicio por autowiring pero **deja de ser singleton**.

---

## 4. Controlador — `app/Http/Controllers/XController.php`

### Sobre A: `{success, message, data}`

Usado por `Auth/UserController`, `Auth/GoogleAuthControlle`, `BioimpedanciaController`, `CertificadoUrlController`, `FichaClinicaController`, `FileUploadController`, `IncidenciasController`, `OpenAIController`. **Es el default para dominios nuevos.**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\XService;

class XController extends Controller
{
    protected $xService;

    public function __construct(XService $xService)
    {
        $this->xService = $xService;
    }

    public function ObtenerX(string $rut_paciente)
    {
        try {
            $x = $this->xService->ObtenerX($rut_paciente);

            return response()->json([
                'success' => true,
                'message' => 'Datos obtenidos correctamente',
                'data'    => $x
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo los datos',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
```

### Sobre B: `{response: {status, mensaje}}`

Usado por `AgendaHorasController`, `ChequeoCardiovascularController`, `ElectroCardiogramaController`, `EmailController`, `EstadisticasController`. Úsalo **solo** si estás extendiendo uno de esos controladores.

```php
    public function StoreX(Request $request)
    {
        try {
            $x = $this->xService->GuardarX($request->all());

            return response()->json([
                'data'    => $x,
                'status'  => 'OK',
                'mensaje' => 'Guardado con Exito'
            ], 200);
        }
        catch (\Exception $e) {
            $array = array('response' => array(
                'status'  => 'Error en ejecucion',
                'mensaje' => $e->getMessage()));

            return response()->json($array, 500);
        }
    }
```

**No mezcles los dos sobres en un mismo archivo.** Ya pasa en `OpenAIController` y `GoogleAuthControlle`; no lo propagues. Y no le cambies el sobre a un endpoint existente: rompe al cliente que ya lo consume.

---

## 5. Rutas — `routes/api.php`

```php
use App\Http\Controllers\XController;

Route::get('x/{rut_paciente}', [XController::class, 'ObtenerX'])
    ->name('ObtenerX');

Route::post('x/store', [XController::class, 'StoreX'])
    ->name('StoreX');
```

- Archivo plano, sin agrupar, prefijo `/api` automático.
- Las rutas **específicas van antes** que las paramétricas: `x/pdf/{id}` antes de `x/{id}`.
- Comprueba que no exista ya una ruta con el mismo URI: Laravel se queda con la última declaración y el duplicado queda muerto.

---

## Nomenclatura observada en el repo

| Elemento | Convención | Ejemplo |
|---|---|---|
| Método de controlador | `PascalCase` | `FindByEmail`, `ChequeoPDFRut` |
| Método de service | `PascalCase` o `camelCase` (mixto) | `FichaClinica()`, `filterCalendar()` |
| Wrapper de SP en modelo | `SP_nombre` estático | `SP_ficha_clinica()` |
| Provider | `XProvider` o `XServiceProvider` (mixto) | `CertificadoProvider`, `BioimpedanciaServiceProvider` |
| Ruta | kebab-case + `->name('MetodoDelControlador')` | `chequeo-cardiovascular/pdf/{id}` |

Ante la duda, copia el dominio vecino en lugar de aplicar el estándar Laravel.
