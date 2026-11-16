<?php
/**
 * session_config.php
 * Sesión segura compartida por todos los endpoints.
 *  - Cookie HttpOnly + SameSite + Secure (si hay HTTPS), modo estricto (no acepta ids de sesión inventados).
 *  - Caduca por inactividad (IDLE) y por duración total (ABSOLUTE).
 * Inclúyelo con require_once ANTES de cualquier salida.
 */
require_once __DIR__ . '/security_headers.php';

const SESSION_IDLE_SECONDS     = 8 * 3600;   // sin actividad
const SESSION_ABSOLUTE_SECONDS = 14 * 3600;  // desde que entró

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
// Sin session_name() personalizado: todos los endpoints comparten la misma sesión (PHPSESSID).
session_start();

// Caducidad
$now = time();
if (!empty($_SESSION['usuario_id'])) {
    $idleOk = $now - (int)($_SESSION['last_activity'] ?? $now) <= SESSION_IDLE_SECONDS;
    $absOk  = $now - (int)($_SESSION['login_time'] ?? $now) <= SESSION_ABSOLUTE_SECONDS;
    if (!$idleOk || !$absOk) {
        $_SESSION = [];
    } else {
        $_SESSION['last_activity'] = $now;
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
