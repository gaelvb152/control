<?php
/**
 * session_config.php
 * Usa el MISMO nombre de sesión por defecto que api_usuarios.php / api_viajes.php
 * (no lo cambies con session_name(), o dejarán de compartir la sesión).
 * Inclúyelo con require_once ANTES de cualquier salida.
 */

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
// Sin session_name() personalizado: usa el nombre por defecto (PHPSESSID),
// el mismo que ya usan api_usuarios.php y api_viajes.php.
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');