# Arquitectura y componentes

Vista de conjunto del backend de Ergosanitas: qué piezas hay, cómo se llaman entre ellas y cómo se despliega. Para el detalle en prosa, el [README](../README.md); para el contrato de cada endpoint, [`openapi.yaml`](openapi.yaml).

- [Vista de componentes](#vista-de-componentes)
- [Las tres capas y sus excepciones](#las-tres-capas-y-sus-excepciones)
- [Mapa de dominios](#mapa-de-dominios)
- [Inyección de dependencias](#inyección-de-dependencias)
- [Despliegue](#despliegue)

---

## Vista de componentes

API REST en Laravel 11 sin frontend propio. Todo se expone bajo `/api`, desde un único archivo de rutas plano y **sin middleware de autenticación en ninguna ruta de negocio**.

```mermaid
flowchart TB
    subgraph cliente["Clientes"]
        WEB["Frontend web / movil"]
        NAV["Navegador del paciente<br/>formulario de Transbank"]
    end

    subgraph laravel["API Laravel 11 · PHP 8.2"]
        RUTAS["routes/api.php<br/>81 operaciones · prefijo /api<br/>plano, sin agrupar, sin middleware"]
        CTRL["18 controladores<br/>app/Http/Controllers"]
        SRV["12 servicios<br/>app/Services"]
        MOD["21 modelos Eloquent<br/>app/Models"]
        IA["6 prompts estaticos<br/>app/IA"]
    end

    subgraph datos["Persistencia"]
        MYSQL[("MySQL 5.7<br/>18 tablas de negocio")]
        SP[["19 procedimientos almacenados<br/>solo uno versionado en el repo"]]
        FS["public/<br/>Certificado · Logo<br/>Electrocardiograma · Bioimpedancia"]
    end

    subgraph externos["Servicios externos"]
        OAI["OpenAI<br/>gpt-4o-mini · gpt-4.1-mini"]
        TBK["Transbank WebPay<br/>HTTP crudo, sin SDK"]
        SMTP["SMTP"]
    end

    subgraph libs["Generacion de documentos"]
        MPDF["mPDF"]
        WORD["PHPWord"]
        XLS["maatwebsite/excel"]
    end

    WEB --> RUTAS
    RUTAS --> CTRL
    CTRL --> SRV
    SRV --> MOD
    MOD --> MYSQL
    MOD --> SP
    SP --> MYSQL

    CTRL -.->|"6 controladores saltan la capa de servicio"| MOD
    CTRL --> FS
    SRV --> FS
    CTRL --> IA
    SRV --> IA
    IA --> OAI
    CTRL --> OAI
    SRV --> OAI
    CTRL --> TBK
    NAV --> TBK
    TBK -->|"return_url"| RUTAS
    CTRL --> SMTP
    CTRL --> MPDF
    CTRL --> WORD
    CTRL --> XLS
    SRV --> MPDF
```

Dos cosas que el diagrama deja ver y conviene no olvidar:

- **`user_email` y `perfiles_id` llegan como datos del request**, no como identidad verificada. El filtrado por perfil es una regla de presentación, no un control de acceso.
- **Los archivos subidos van a `public/` con `$file->move()`**, no al disco de storage de Laravel, y `public/` no está montado como volumen en producción (ver [Despliegue](#despliegue)).

---

## Las tres capas y sus excepciones

El patrón declarado es Controller → Service → Model. Se cumple en la mayoría de los dominios, pero **seis controladores no inyectan ningún servicio** y hablan directo con modelos o facades.

```mermaid
flowchart TB
    C1["1 · Controlador<br/>valida el Request, arma el sobre JSON,<br/>captura excepciones"]
    C2["2 · Service<br/>logica de negocio, Query Builder,<br/>generacion de documentos"]
    C3["3 · Model<br/>Eloquent + wrappers estaticos de SP"]

    C1 --> C2 --> C3

    SALTAN["Sin servicio alguno<br/>ServiciosController · AgendaHorasController<br/>EmailController · WebPayController<br/>FileUploadController · Auth/GoogleAuthControlle"]
    PARCIAL["Con servicio, pero lo saltan en algun metodo<br/>EstadisticasController::deletePagoMensual<br/>ClubAssistantController::ResetSearch<br/>CertificadoUrlController::FileUploadCer"]

    SALTAN -.-> C3
    PARCIAL -.-> C3

    SALTAN:::warn
    PARCIAL:::warn
    classDef warn fill:#fff4e5,stroke:#d97706,stroke-width:2px,color:#5d4037
```

`bootstrap/app.php` no registra middleware ni manejador de excepciones (ambos closures están vacíos) y **no existe `app/Http/Middleware`**. Por eso cada acción envuelve su cuerpo en `try/catch` y arma el sobre a mano: no hay handler global que lo haga.

> **Conviven siete formatos de respuesta**, no uno. Al tocar un endpoint hay que respetar el que ya devuelve: cambiarlo rompe a los clientes. Ver la tabla completa en el [README](../README.md#formatos-de-respuesta).

---

## Mapa de dominios

Qué controlador atiende cada dominio y con qué servicio trabaja.

```mermaid
flowchart LR
    subgraph D1["Chequeo cardiovascular"]
        CCC["ChequeoCardiovascularController<br/>17 acciones"]
        CMC["CargaMasivaController"]
        CWC["ChequeoCardiovascularWordController"]
    end
    subgraph D2["Certificados y ECG"]
        CUC["CertificadoUrlController"]
        ECC["ElectroCardiogramaController"]
        FUC["FileUploadController"]
    end
    subgraph D3["Asistentes de IA"]
        OAC["OpenAIController"]
        CAC["ClubAssistantController"]
    end
    subgraph D4["Clinico complementario"]
        BIC["BioimpedanciaController"]
        FCC["FichaClinicaController"]
        INC["IncidenciasController"]
    end
    subgraph D5["Negocio y usuarios"]
        UC["Auth/UserController"]
        GAC["Auth/GoogleAuthControlle"]
        ESC["EstadisticasController"]
        SEC["ServiciosController"]
        AHC["AgendaHorasController"]
        EMC["EmailController"]
        WPC["WebPayController"]
    end

    S_CCS["ChequeoCardiovascularService"]
    S_PDF["ChequeoCardiovascularPDFService"]
    S_WS["ChequeoCardiovascularWordService"]
    S_UMS["UserMetadataService"]
    S_CS["CertificadoService"]
    S_ECS["ElectroCardiogramaService"]
    S_ES["EstadisticasService"]
    S_OAS["OpenAIService"]
    S_CAS["ClubAssistantService"]
    S_BIS["BioimpedanciaService"]
    S_FCS["FichaClinicaService"]
    S_IS["IncidentesService"]
    IMP["App\Imports\ChequeoImport"]

    CCC --> S_CCS
    CCC --> S_PDF
    CCC --> S_UMS
    CCC --> S_CS
    CCC --> S_ECS
    CMC --> IMP
    CWC --> S_WS

    CUC --> S_CS
    CUC --> S_ES
    ECC --> S_ES

    OAC --> S_OAS
    OAC --> S_ES
    CAC --> S_CAS

    BIC --> S_BIS
    FCC --> S_FCS
    INC --> S_IS

    UC --> S_UMS
    ESC --> S_ES
```

`ServiciosController`, `AgendaHorasController`, `EmailController`, `GoogleAuthControlle`, `FileUploadController` y `WebPayController` no aparecen conectados a ningún servicio porque no tienen ninguno: van directo al modelo.

> `CargaMasivaController` **sí inyecta** `ChequeoCardiovascularService` en su constructor, pero nunca lo usa: la carga masiva pasa por `App\Imports\ChequeoImport`. El diagrama muestra la llamada real, no la dependencia declarada.

---

## Inyección de dependencias

Todos los servicios se registran **a mano** en `bootstrap/providers.php` — no hay auto-discovery. Cada provider es un `singleton()` con una closure trivial; el único que resuelve una dependencia es `CertificadoProvider`.

```mermaid
flowchart LR
    CCC["ChequeoCardiovascularController"]
    CUC["CertificadoUrlController"]
    OAC["OpenAIController"]
    ECC["ElectroCardiogramaController"]
    CWC["ChequeoCardiovascularWordController"]

    UMS["UserMetadataService"]
    CCS["ChequeoCardiovascularService"]
    PDFS["ChequeoCardiovascularPDFService"]
    ECS["ElectroCardiogramaService"]
    CS["CertificadoService"]
    ES["EstadisticasService"]
    OAS["OpenAIService"]
    WS["ChequeoCardiovascularWordService<br/>sin provider · no es singleton"]

    CCC --> UMS
    CCC --> CCS
    CCC --> PDFS
    CCC --> ECS
    CCC --> CS
    CUC --> CS
    CUC --> ES
    OAC --> OAS
    OAC --> ES
    ECC --> ES
    CWC --> WS

    CS ==>|"composicion registrada en CertificadoProvider"| ES
    WS ==>|"composicion por autowiring"| PDFS

    WS:::warn
    classDef warn fill:#fff4e5,stroke:#d97706,stroke-width:2px,color:#5d4037
```

Los 12 providers registrados en `bootstrap/providers.php`:

| Provider | Registra |
|---|---|
| `AppServiceProvider` | nada (`register` y `boot` vacíos) |
| `BioimpedanciaServiceProvider` | `BioimpedanciaService` |
| `CertificadoProvider` | `CertificadoService`, **inyectándole `EstadisticasService`** |
| `ChequeoCardiovascularPDFProvider` | `ChequeoCardiovascularPDFService` |
| `ChequeoCardiovascularProvider` | `ChequeoCardiovascularService` |
| `ClubAssistantServiceProvider` | `ClubAssistantService` |
| `ElectroCardiogramaProvider` | `ElectroCardiogramaService` |
| `EstadisticasProvider` | `EstadisticasService` |
| `FichaClinicaServiceProvider` | `FichaClinicaService` |
| `IncidenciaServiceProvider` | `IncidentesService` |
| `OpenAIServiceProvider` | `OpenAIService` |
| `UserMetadataProvider` | `UserMetadataService` |

> ⚠️ **`ChequeoCardiovascularWordService` no tiene provider.** Laravel lo resuelve igual por autowiring —su constructor solo pide otro servicio—, pero **deja de ser singleton**: se construye una instancia nueva en cada request. Es la excepción real a la regla «cada servicio necesita su propio ServiceProvider».

Al crear un servicio nuevo hay que hacer las tres cosas: `app/Services/XService.php`, `app/Providers/XServiceProvider.php` con un `singleton()`, y la línea en `bootstrap/providers.php`. Los nombres de provider son inconsistentes (`CertificadoProvider` vs `BioimpedanciaServiceProvider`); sigue el existente del dominio que toques.

---

## Despliegue

Push a `main` publica directo: **no hay lint, ni tests, ni migraciones en el pipeline**.

```mermaid
flowchart LR
    PUSH["git push a main"] --> GHA

    subgraph GHA["GitHub Actions · github-action.build.yml"]
        direction TB
        VER["PaulHatch/semantic-version<br/>major / feat / resto to patch<br/>coincidencia de subcadena<br/>en el mensaje del commit"]
        BUILD["docker build"]
        VER --> BUILD
    end

    GHA --> HUB[("Docker Hub<br/>nruz176/laravel-back-end-ergosanitas-app<br/>tags :version y :latest")]
    HUB --> PULL["docker compose pull<br/>en el servidor"]
    PULL --> RUNTIME

    subgraph RUNTIME["Servidor · dockerHub/docker-compose.yml"]
        direction TB
        NGINX["nginx:alpine<br/>puerto 6162"]
        APP["laravel_app · php-fpm<br/>entrypoint.sh: config:clear, cache:clear,<br/>view:clear, route:clear, storage:link"]
        DB[("mysql:5.7.22<br/>puerto 3337")]
        PMA["phpmyadmin:5.2.1<br/>puerto 8383"]
        REDIS[("redis:7.2-alpine<br/>puerto 7379")]

        NGINX --> APP
        APP --> DB
        PMA --> DB
    end
```

Dos consecuencias del diagrama:

- **El bump de versión lo decide el texto del mensaje de commit.** Son coincidencias de subcadena, así que un mensaje como *«refactor: quita el feature flag»* sube minor sin querer.
- **`entrypoint.sh` hace `config:clear` en cada arranque a propósito.** Varios controladores y servicios leen `env()` fuera de `config/` (`API_PATH_CER`, `API_PATH_LOGO`, `WEBPAY_*`, `GOOGLE_CLIENT_*`); con la config cacheada esas llamadas devuelven `null` y se rompen certificados, logos, pagos y login de Google. **Nunca ejecutes `php artisan config:cache`.**

### Volúmenes

```mermaid
flowchart LR
    subgraph vol["Volumenes declarados"]
        V1["storage_data → /var/www/storage"]
        V2["bootstrap_cache → /var/www/bootstrap/cache"]
        V3["./.env → /var/www/.env"]
        V4["laravel_mysql_data → /var/lib/mysql"]
    end

    P["SIN volumen<br/>public/Certificado · public/Logo<br/>public/Electrocardiograma · public/Bioimpedancia"]
    P --> PERDIDA["Viven en la capa escribible del contenedor:<br/>un redeploy de :latest los pierde"]

    P:::danger
    PERDIDA:::danger
    classDef danger fill:#fee2e2,stroke:#dc2626,stroke-width:2px,color:#7f1d1d
```

Tenlo presente antes de proponer que algo nuevo se guarde en `public/`.

---

**Siguiente:** [Flujos end-to-end](flujos.md) · [Diagrama de clases](diagrama-clases.md) · [Modelo de datos](modelo-datos.md)
