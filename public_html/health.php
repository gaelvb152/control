<?php

/**
 * health.php — estado de la aplicación para el pipeline y el monitoreo.
 *
 * Usa la misma conexión que el sistema (db.php) y confirma que la tabla usuarios responde.
 * Responde 200 si todo funciona; si la base no conecta, db.php corta con 500,
 * y si falta el esquema se responde 503. No muestra datos sensibles.
 */

declare(strict_types=1);

require_once __DIR__ . '/security_headers.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex');

$inicio  = microtime(true);
$version = is_file(__DIR__ . '/version.txt') ? trim((string) file_get_contents(__DIR__ . '/version.txt')) : 'local';

$local   = is_file(__DIR__ . '/config.local.php') ? require __DIR__ . '/config.local.php' : [];
$entorno = (is_array($local) && !empty($local['entorno'])) ? (string) $local['entorno'] : 'local';

require_once __DIR__ . '/db.php';   // si no conecta, responde 500 y termina

$bdOk = false;
$t = microtime(true);
try {
    $pdo->query('SELECT 1 FROM usuarios LIMIT 1');
    $bdOk = true;
} catch (Throwable $e) {
    error_log('health.php: la tabla usuarios no responde');
}
$bdMs = (int) round((microtime(true) - $t) * 1000);

http_response_code($bdOk ? 200 : 503);
echo json_encode([
    'estado'   => $bdOk ? 'ok' : 'error',
    'version'  => $version,
    'entorno'  => $entorno,
    'bd'       => ['ok' => $bdOk, 'ms' => $bdMs],
    'total_ms' => (int) round((microtime(true) - $inicio) * 1000),
    'hora'     => date('c'),
], JSON_UNESCAPED_UNICODE);
