<?php

/**
 * config.php — carga la configuración de la base de datos SIN guardar secretos en el código.
 *
 * Orden de prioridad (gana el primero que exista):
 *   1. Variables de entorno  CB_DB_HOST, CB_DB_PORT, CB_DB_NAME, CB_DB_USER, CB_DB_PASS
 *      (las usan Docker, CI y las pruebas automáticas).
 *   2. Archivo config.local.php junto a este archivo (producción/staging en Hostinger).
 *      Ese archivo NO se versiona (.gitignore) ni se despliega (se crea una sola vez a mano).
 *   3. Valores por defecto (solo sirven para desarrollo local).
 *
 * Este archivo está bloqueado por .htaccess: no se puede abrir desde el navegador.
 */
function cb_db_config()
{
    $file = __DIR__ . '/config.local.php';
    $local = is_file($file) ? require $file : [];
    if (!is_array($local)) {
        $local = [];
    }

    $pick = function ($envName, $localKey, $default) use ($local) {
        $env = getenv($envName);
        if ($env !== false && $env !== '') {
            return $env;
        }
        return isset($local[$localKey]) && $local[$localKey] !== '' ? $local[$localKey] : $default;
    };

    return [
        'host' => $pick('CB_DB_HOST', 'host', 'localhost'),
        'port' => $pick('CB_DB_PORT', 'port', ''),
        'name' => $pick('CB_DB_NAME', 'name', ''),
        'user' => $pick('CB_DB_USER', 'user', ''),
        'pass' => $pick('CB_DB_PASS', 'pass', ''),
    ];
}

/** DSN de PDO a partir de la configuración (el puerto es opcional). */
function cb_db_dsn(array $cfg)
{
    $dsn = 'mysql:host=' . $cfg['host'];
    if ($cfg['port'] !== '') {
        $dsn .= ';port=' . $cfg['port'];
    }
    return $dsn . ';dbname=' . $cfg['name'] . ';charset=utf8mb4';
}
