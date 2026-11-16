#!/usr/bin/env php
<?php

/**
 * bin/create-admin.php — crea el PRIMER usuario administrador (la app no tiene pantalla para eso).
 *
 *   CB_ADMIN_PASSWORD='una-clave-larga' php bin/create-admin.php 9001 "Nombre Apellido"
 *
 * La contraseña se recibe por variable de entorno para que no quede en el historial del shell
 * ni en la lista de procesos. Mínimo 10 caracteres.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../public_html/config.php';

$num = isset($argv[1]) ? trim($argv[1]) : '';
$nombre = isset($argv[2]) ? trim($argv[2]) : '';
$pwd = getenv('CB_ADMIN_PASSWORD');

if ($num === '' || $nombre === '' || $pwd === false || strlen($pwd) < 10) {
    fwrite(STDERR, "Uso: CB_ADMIN_PASSWORD='(mín. 10 caracteres)' php bin/create-admin.php <num_empleado> \"<nombre>\"\n");
    exit(2);
}

$cfg = cb_db_config();
try {
    $pdo = new PDO(cb_db_dsn($cfg), $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->prepare("INSERT INTO usuarios (num_empleado, password_hash, nombre, rol, estado) VALUES (?, ?, ?, 'admin', 'activo')")
        ->execute([$num, password_hash($pwd, PASSWORD_DEFAULT), $nombre]);
} catch (PDOException $e) {
    fwrite(STDERR, ($e->getCode() == 23000 ? 'Ese número de empleado ya existe.' : 'Error: ' . $e->getMessage()) . "\n");
    exit(1);
}
echo "Administrador $num creado.\n";
