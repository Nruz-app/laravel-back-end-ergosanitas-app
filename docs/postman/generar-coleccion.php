<?php
/**
 * Convierte docs/openapi.yaml en una coleccion Postman v2.1.
 * Uso, desde la raiz del repo:
 *   php docs/postman/generar-coleccion.php
 *
 * Extension opcional `x-postman` por operacion (la usan las carpetas del carro, para que se
 * puedan correr en orden sin copiar ids a mano). No cambia el contrato que ve Swagger:
 *   orden   int                 orden dentro de la carpeta (las demas van despues, en orden del YAML)
 *   vars    {param: variable}   renombra la variable de un {param} de ruta (p. ej. id -> linea_id)
 *   query   {clave: valor}      valor de un query param (se habilita aunque sea opcional)
 *   body    objeto              cuerpo JSON en lugar del ejemplo del contrato
 *   form    {clave: valor}      valor de un campo de texto multipart
 *   guardar {variable: expr}    script de Tests: guarda expr (JS sobre `r`, el JSON de la respuesta)
 *   redirecciones false         no seguir el 302 (para ver la redireccion en vez de seguirla)
 * Y en la raiz, `x-postman-variables`: [{key, value, description}] variables extra de coleccion.
 */

require __DIR__.'/../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$specPath = $argv[1] ?? __DIR__.'/../openapi.yaml';
$outPath = $argv[2] ?? __DIR__.'/Ergosanitas-API.postman_collection.json';

$spec = Yaml::parseFile($specPath);

function deref(array $spec, $node, int $depth = 0)
{
    if ($depth > 30 || ! is_array($node)) {
        return $node;
    }
    if (isset($node['$ref']) && is_string($node['$ref'])) {
        $path = ltrim($node['$ref'], '#/');
        $cur = $spec;
        foreach (explode('/', $path) as $seg) {
            if (! isset($cur[$seg])) {
                return $node;
            }
            $cur = $cur[$seg];
        }
        $rest = $node;
        unset($rest['$ref']);

        return array_merge(deref($spec, $cur, $depth + 1), $rest);
    }
    foreach ($node as $k => $v) {
        $node[$k] = deref($spec, $v, $depth + 1);
    }

    return $node;
}

/** Genera un valor de ejemplo a partir de un schema OpenAPI. */
function sample(array $spec, $schema, int $depth = 0)
{
    $schema = deref($spec, $schema, $depth);
    if (! is_array($schema)) {
        return null;
    }
    if (array_key_exists('example', $schema)) {
        return $schema['example'];
    }
    if (isset($schema['enum'][0])) {
        return $schema['enum'][0];
    }
    $type = $schema['type'] ?? (isset($schema['properties']) ? 'object' : 'string');

    if ($type === 'object') {
        $out = [];
        foreach (($schema['properties'] ?? []) as $name => $prop) {
            $out[$name] = sample($spec, $prop, $depth + 1);
        }

        return $out;
    }
    if ($type === 'array') {
        return [sample($spec, $schema['items'] ?? ['type' => 'string'], $depth + 1)];
    }

    return match ($type) {
        'integer' => 0,
        'number' => 0,
        'boolean' => true,
        default => '',
    };
}

/** Primer ejemplo utilizable de un bloque content[mediaType]. */
function bodyExample(array $spec, array $media)
{
    if (isset($media['examples']) && is_array($media['examples'])) {
        foreach ($media['examples'] as $ex) {
            $ex = deref($spec, $ex);
            if (is_array($ex) && array_key_exists('value', $ex)) {
                return $ex['value'];
            }
        }
    }
    if (array_key_exists('example', $media)) {
        return $media['example'];
    }
    if (isset($media['schema'])) {
        return sample($spec, $media['schema']);
    }

    return null;
}

function jsonPretty($value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** Descripcion del request: description + tabla de respuestas + operationId. */
function buildDescription(array $spec, array $op): string
{
    $parts = [];
    if (! empty($op['description'])) {
        $parts[] = trim($op['description']);
    }
    $codes = [];
    foreach (($op['responses'] ?? []) as $code => $res) {
        $res = is_array($res) ? deref($spec, $res) : [];
        $desc = trim(explode("\n", trim($res['description'] ?? ''))[0]);
        $codes[] = '- `'.$code.'` - '.($desc !== '' ? $desc : 'sin descripcion');
    }
    if ($codes) {
        $parts[] = "**Respuestas documentadas**\n\n".implode("\n", $codes);
    }
    if (! empty($op['operationId'])) {
        $parts[] = '_operationId_: `'.$op['operationId'].'`';
    }

    return implode("\n\n---\n\n", $parts);
}

$collectionVars = [];
$varDescriptions = [];
$varSamples = []; // nombre => [ejemplo => veces]; el mas repetido gana

$folders = [];
$tagOrder = [];
foreach (($spec['tags'] ?? []) as $t) {
    $tagOrder[] = $t['name'];
    $folders[$t['name']] = [];
}

$methods = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];
$count = 0;

foreach ($spec['paths'] as $path => $pathItem) {
    $sharedParams = $pathItem['parameters'] ?? [];
    foreach ($methods as $method) {
        if (! isset($pathItem[$method])) {
            continue;
        }
        $op = $pathItem[$method];
        $count++;

        $xp = $op['x-postman'] ?? [];
        $renombres = $xp['vars'] ?? [];

        $params = array_merge($sharedParams, $op['parameters'] ?? []);
        $params = array_map(fn ($p) => deref($spec, $p), $params);

        // --- URL -------------------------------------------------------
        $rawPath = $path;
        $query = [];
        foreach ($params as $p) {
            $name = $p['name'] ?? null;
            if (! $name) {
                continue;
            }
            $example = sample($spec, $p['schema'] ?? ['type' => 'string']);
            $example = is_scalar($example) ? (string) $example : '';

            if (($p['in'] ?? '') === 'path' && isset($renombres[$name])) {
                $collectionVars[$renombres[$name]] = $collectionVars[$renombres[$name]] ?? $example;
                $varDescriptions[$renombres[$name]] = $varDescriptions[$renombres[$name]] ?? '';
            } elseif (($p['in'] ?? '') === 'path') {
                $collectionVars[$name] = $collectionVars[$name] ?? $example;
                if ($example !== '') {
                    $varSamples[$name][$example] = ($varSamples[$name][$example] ?? 0) + 1;
                }
                $varDescriptions[$name] = $varDescriptions[$name]
                    ?? trim(explode("\n", trim($p['description'] ?? ''))[0]);
            } elseif (($p['in'] ?? '') === 'query') {
                $fijo = array_key_exists($name, $xp['query'] ?? []);
                $query[] = [
                    'key' => $name,
                    'value' => $fijo ? (string) $xp['query'][$name] : $example,
                    'disabled' => ! ($fijo || ($p['required'] ?? false)),
                    'description' => trim($p['description'] ?? ''),
                ];
            }
        }

        // Un unico pase {param} -> {{param}}. Los no declarados en el contrato
        // igual se registran como variable de coleccion.
        if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $m)) {
            foreach ($m[1] as $name) {
                $var = $renombres[$name] ?? $name;
                $collectionVars[$var] = $collectionVars[$var] ?? '';
            }
        }
        $rawPath = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', fn ($m) => '{{'.($renombres[$m[1]] ?? $m[1]).'}}', $path);

        $segments = array_values(array_filter(explode('/', ltrim($rawPath, '/')), fn ($s) => $s !== ''));

        $url = [
            'raw' => '{{baseUrl}}'.$rawPath,
            'host' => ['{{baseUrl}}'],
            'path' => $segments,
        ];
        if ($query) {
            $url['query'] = $query;
            $enabled = array_values(array_filter($query, fn ($q) => ! $q['disabled']));
            if ($enabled) {
                $url['raw'] .= '?'.implode('&', array_map(fn ($q) => $q['key'].'='.$q['value'], $enabled));
            }
        }

        // --- Headers y body --------------------------------------------
        $headers = [['key' => 'Accept', 'value' => 'application/json']];
        $body = null;

        $content = $op['requestBody']['content'] ?? [];
        if (isset($content['application/json'])) {
            $value = array_key_exists('body', $xp) ? $xp['body'] : bodyExample($spec, $content['application/json']);
            $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
            $body = [
                'mode' => 'raw',
                'raw' => jsonPretty($value ?? new stdClass),
                'options' => ['raw' => ['language' => 'json']],
            ];
        } elseif (isset($content['multipart/form-data'])) {
            $schema = deref($spec, $content['multipart/form-data']['schema'] ?? []);
            $formdata = [];
            foreach (($schema['properties'] ?? []) as $name => $prop) {
                $prop = deref($spec, $prop);
                $desc = trim($prop['description'] ?? '');
                $esBinario = ($prop['format'] ?? '') === 'binary';
                // Array de binarios: PHP solo conserva el ultimo valor si el campo no
                // lleva corchetes, asi que el nombre real del campo es `nombre[]`.
                $esLote = ($prop['type'] ?? '') === 'array'
                    && (($prop['items']['format'] ?? '') === 'binary');

                if ($esBinario || $esLote) {
                    $formdata[] = [
                        'key' => $esLote ? $name.'[]' : $name,
                        'type' => 'file',
                        'src' => [],
                        'description' => $desc !== '' ? $desc : 'Archivo a subir (seleccionalo en Postman).',
                    ];
                } else {
                    $val = array_key_exists($name, $xp['form'] ?? []) ? $xp['form'][$name] : sample($spec, $prop);
                    $formdata[] = [
                        'key' => $name,
                        'type' => 'text',
                        'value' => is_scalar($val) ? (string) $val : jsonPretty($val),
                        'description' => $desc,
                    ];
                }
            }
            $body = ['mode' => 'formdata', 'formdata' => $formdata];
        }

        $request = [
            'method' => strtoupper($method),
            'header' => $headers,
            'url' => $url,
            'description' => buildDescription($spec, $op),
        ];
        if ($body) {
            $request['body'] = $body;
        }

        $item = [
            'name' => $op['summary'] ?? strtoupper($method).' '.$path,
            'request' => $request,
            'response' => [],
        ];

        // Guarda valores de la respuesta en variables: en el entorno activo (que tiene prioridad
        // sobre la coleccion) y en la coleccion, para que funcione con o sin entorno.
        if (! empty($xp['guardar'])) {
            $lineas = [
                'const r = pm.response.json();',
                'const guardar = (k, v) => { if (v === undefined || v === null) return; if (pm.environment.name) pm.environment.set(k, String(v)); pm.collectionVariables.set(k, String(v)); };',
            ];
            foreach ($xp['guardar'] as $var => $expr) {
                $lineas[] = 'try { guardar('.json_encode($var).', '.$expr.'); } catch (e) { console.log('.json_encode('No se pudo guardar '.$var).', e); }';
            }
            $item['event'] = [[
                'listen' => 'test',
                'script' => ['type' => 'text/javascript', 'exec' => $lineas],
            ]];
        }

        if (($xp['redirecciones'] ?? true) === false) {
            $item['protocolProfileBehavior'] = ['followRedirects' => false];
        }

        $item['_orden'] = $xp['orden'] ?? 1000 + $count;

        $tag = $op['tags'][0] ?? 'Sin categoria';
        if (! isset($folders[$tag])) {
            $folders[$tag] = [];
            $tagOrder[] = $tag;
        }
        $folders[$tag][] = $item;
    }
}

// --- Ensamblado de la coleccion -----------------------------------------
$tagDescriptions = [];
foreach (($spec['tags'] ?? []) as $t) {
    $tagDescriptions[$t['name']] = trim($t['description'] ?? '');
}

$items = [];
foreach ($tagOrder as $tag) {
    if (empty($folders[$tag])) {
        continue;
    }
    usort($folders[$tag], fn ($a, $b) => $a['_orden'] <=> $b['_orden']);
    $folders[$tag] = array_map(function ($i) {
        unset($i['_orden']);

        return $i;
    }, $folders[$tag]);
    $items[] = [
        'name' => $tag,
        'description' => $tagDescriptions[$tag] ?? '',
        'item' => $folders[$tag],
    ];
}

$variables = [
    [
        'key' => 'baseUrl',
        'value' => $spec['servers'][0]['url'] ?? 'http://127.0.0.1:8000/api',
        'type' => 'string',
        'description' => 'Host + prefijo /api. Servidores documentados: '
            .implode(' | ', array_map(fn ($s) => $s['url'], $spec['servers'] ?? [])),
    ],
];
foreach (($spec['x-postman-variables'] ?? []) as $extra) {
    $collectionVars[$extra['key']] = $collectionVars[$extra['key']] ?? (string) ($extra['value'] ?? '');
    $varDescriptions[$extra['key']] = $extra['description'] ?? ($varDescriptions[$extra['key']] ?? '');
}
ksort($collectionVars);
foreach ($collectionVars as $name => $value) {
    // Con varios ejemplos para el mismo parametro gana el mas repetido en el
    // contrato: es el que representa al caso de uso habitual.
    if (! empty($varSamples[$name])) {
        arsort($varSamples[$name]);
        $value = (string) array_key_first($varSamples[$name]);
    }
    $variables[] = [
        'key' => $name,
        'value' => $value,
        'type' => 'string',
        'description' => $varDescriptions[$name] ?? '',
    ];
}

$collection = [
    'info' => [
        'name' => ($spec['info']['title'] ?? 'API').' v'.($spec['info']['version'] ?? '1.0.0'),
        '_postman_id' => '6f2c1a34-e0a1-4a11-9c8e-e12345678901',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        'description' => 'Generada desde `docs/openapi.yaml` ('.$count." operaciones).\n\n"
            ."Las rutas de negocio **no llevan middleware de autenticacion**: `user_email` y `perfiles_id` viajan como parametros del request.\n\n"
            ."Ajusta `baseUrl` y las variables de coleccion (`user_email`, `admin_email`, `rut_paciente`, `id_paciente`, `perfil`) antes de ejecutar. Las carpetas del carro se ejecutan en orden: cada request guarda en variables lo que necesita el siguiente.\n\n"
            .'Los cuerpos son los ejemplos del contrato OpenAPI; en los `multipart/form-data` hay que seleccionar el archivo a mano.',
    ],
    'item' => $items,
    'variable' => $variables,
];

file_put_contents($outPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

// --- Entornos: uno por servidor declarado en el contrato -----------------
$slug = function (string $url): string {
    return match (true) {
        str_contains($url, '127.0.0.1'), str_contains($url, '8000') => 'Local',
        str_contains($url, '6162') => 'Docker',
        default => 'Prod',
    };
};

$entornos = [];
foreach (($spec['servers'] ?? []) as $n => $server) {
    $nombre = 'Ergosanitas - '.$slug($server['url']);
    $values = [];
    foreach ($variables as $v) {
        $values[] = [
            'key' => $v['key'],
            'value' => $v['key'] === 'baseUrl' ? $server['url'] : $v['value'],
            'type' => 'default',
            'enabled' => true,
        ];
    }
    $envPath = dirname($outPath).'/Ergosanitas-'.$slug($server['url']).'.postman_environment.json';
    file_put_contents($envPath, json_encode([
        'id' => sprintf('e1f0a1b2-%04d-4c00-9000-e12345678901', $n + 1),
        'name' => $nombre,
        'values' => $values,
        '_postman_variable_scope' => 'environment',
        '_postman_exported_using' => 'docs/postman/generar-coleccion.php',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $entornos[] = basename($envPath);
}

fwrite(STDERR, 'Operaciones: '.$count."\n".'Carpetas: '.count($items)."\n"
    .'Variables: '.count($variables)."\n"
    .'Entornos: '.implode(', ', $entornos)."\n");
