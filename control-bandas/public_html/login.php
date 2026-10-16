<?php
/**
 * login.php
 * Espera JSON por POST: { "num_empleado": "...", "password": "..." }
 * Responde: { success: true, data: {num_empleado, nombre, rol} } o { success:false, message }
 *
 * Guarda la sesión con las MISMAS claves que ya usa api_usuarios.php:
 * $_SESSION['usuario_id'] (el id numérico de la tabla) y $_SESSION['rol'].
 */
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/db.php';

function respond($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Método no permitido'], 405);
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['login_block_until'])) {
    $_SESSION['login_block_until'] = 0;
}

if (time() < $_SESSION['login_block_until']) {
    respond(['success' => false, 'message' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.'], 429);
}

$body = json_decode(file_get_contents('php://input'), true);
$num  = trim((string)($body['num_empleado'] ?? ''));
$pwd  = (string)($body['password'] ?? '');

if ($num === '' || $pwd === '') {
    respond(['success' => false, 'message' => 'Faltan datos']);
}

try {
    $stmt = $pdo->prepare('SELECT id, num_empleado, nombre, rol, password_hash, estado FROM usuarios WHERE num_empleado = ? LIMIT 1');
    $stmt->execute([$num]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('login.php DB error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Error del servidor al consultar el usuario. Revisa el log de errores.'], 500);
}

if (!$user || !password_verify($pwd, $user['password_hash'])) {
    $_SESSION['login_attempts']++;
    if ($_SESSION['login_attempts'] >= 6) {
        $_SESSION['login_block_until'] = time() + 60;
        $_SESSION['login_attempts'] = 0;
    }
    respond(['success' => false, 'message' => 'Número de empleado o contraseña incorrectos']);
}

if (isset($user['estado']) && $user['estado'] !== 'activo') {
    respond(['success' => false, 'message' => 'Esta cuenta está desactivada. Habla con el administrador.']);
}

$_SESSION['login_attempts'] = 0;
session_regenerate_id(true);

// Mismas claves que espera api_usuarios.php / api_viajes.php
$_SESSION['usuario_id']   = $user['id'];
$_SESSION['rol']          = $user['rol'];
// Claves propias, usadas por check_session.php y el frontend
$_SESSION['num_empleado'] = $user['num_empleado'];
$_SESSION['nombre']       = $user['nombre'];

try {
    $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE num_empleado = ?')->execute([$num]);
} catch (PDOException $e) {
    error_log('login.php ultimo_acceso update warning: ' . $e->getMessage());
}

respond([
    'success' => true,
    'data' => [
        'num_empleado' => $user['num_empleado'],
        'nombre'       => $user['nombre'],
        'rol'          => $user['rol'],
    ],
]);