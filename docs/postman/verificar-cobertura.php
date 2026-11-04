<?php
/**
 * Compara los requests de la coleccion Postman con las rutas realmente
 * registradas por Laravel. Uso, desde la raiz del repo:
 *   php docs/postman/verificar-cobertura.php
 *
 * Salida: las diferencias en ambos sentidos y un resumen. Devuelve codigo 1
 * si falta cobertura, para poder encadenarlo en un hook o en CI.
 */
$coleccion = $argv[1] ?? __DIR__.'/Ergosanitas-API.postman_collection.json';

$routes = json_decode((string) shell_exec('php artisan route:list --json'), true);
if (! is_array($routes)) {
    fwrite(STDERR, "No se pudo leer 'php artisan route:list --json'.\n");
    exit(1);
}

// Normaliza a "METODO /ruta-sin-prefijo-api", con {param} en vez de {{param}}.
$normaliza = fn (string $uri): string => preg_replace('/\{\{([a-zA-Z0-9_]+)\}\}/', '{$1}', $uri);

$reales = [];
foreach ($routes as $r) {
    if (! str_starts_with($r['uri'], 'api/')) {
        continue;
    }
    foreach (explode('|', $r['method']) as $metodo) {
        if (in_array($metodo, ['HEAD', 'OPTIONS'], true)) {
            continue;
        }
        $reales[strtoupper($metodo).' /'.substr($r['uri'], 4)] = $r['name'] ?? '';
    }
}

$col = json_decode((string) file_get_contents($coleccion), true);
$enColeccion = [];
foreach ($col['item'] as $carpeta) {
    foreach ($carpeta['item'] as $item) {
        $ruta = substr($item['request']['url']['raw'], strlen('{{baseUrl}}'));
        $ruta = $normaliza(explode('?', $ruta)[0]);
        $enColeccion[$item['request']['method'].' '.$ruta] = $item['name'];
    }
}

// Los nombres de los placeholders pueden diferir del contrato ({id} vs
// {id_paciente}): comparar tambien con los parametros anonimizados.
$anonimo = fn (array $set): array => array_combine(
    array_map(fn ($k) => preg_replace('/\{[a-zA-Z0-9_]+\}/', '{}', $k), array_keys($set)),
    array_values($set)
);

$faltan = array_diff_key($anonimo($reales), $anonimo($enColeccion));
$sobran = array_diff_key($anonimo($enColeccion), $anonimo($reales));

echo 'Rutas registradas: '.count($reales).' | requests en la coleccion: '.count($enColeccion)."\n";

if ($faltan) {
    echo "\nEn routes/api.php pero NO en la coleccion:\n";
    foreach (array_keys($faltan) as $r) {
        echo "  $r\n";
    }
}
if ($sobran) {
    echo "\nEn la coleccion pero NO en routes/api.php:\n";
    foreach (array_keys($sobran) as $r) {
        echo "  $r\n";
    }
}
if (! $faltan && ! $sobran) {
    echo "\nCobertura completa: la coleccion cubre todas las rutas registradas.\n";
}

exit($faltan || $sobran ? 1 : 0);
