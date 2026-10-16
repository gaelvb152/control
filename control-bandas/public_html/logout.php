<?php
/**
 * logout.php
 * Destruye la sesión por completo en el servidor (no solo la borra del navegador).
 */
require_once __DIR__ . '/session_config.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

echo json_encode(['success' => true]);