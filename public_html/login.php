<?php
/**
 * login.php
 * Espera JSON por POST: { "num_empleado": "...", "password": "..." }
 * Responde: { success: true, data: {num_empleado, nombre, rol, debe_cambiar} } o { success:false, message }
 *
 * Protecciones: bloqueo temporal por intentos fallidos (por IP y por IP+usuario, guardado en la base),
 * tiempo de respuesta parejo exista o no el usuario, regeneración del id de sesión y reencriptado de hashes viejos.
 */
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib.php';

const LOGIN_VENTANA = 600;       // segundos que se recuerdan los fallos
const LOGIN_MAX_USUARIO_IP = 6;  // fallos con el mismo usuario desde la misma IP
const LOGIN_MAX_IP = 30;         // fallos totales desde una IP

function respond($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success' => false, 'message' => 'Método no permitido'], 405);
csrfGuard();

$ip   = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$body = json_decode(file_get_contents('php://input'), true);
$num  = substr(trim((string)($body['num_empleado'] ?? '')), 0, 40);
$pwd  = (string)($body['password'] ?? '');

if ($num === '' || $pwd === '') respond(['success' => false, 'message' => 'Faltan datos']);
if (strlen($pwd) > 200) respond(['success' => false, 'message' => 'Número de empleado o contraseña incorrectos']);

/* ---- bloqueo por intentos (si la tabla no existe todavía, se omite sin romper el login) ---- */
$throttle = true;
try {
    $desde = time() - LOGIN_VENTANA;
    $s = $pdo->prepare('SELECT COUNT(*) FROM login_intentos WHERE ip = ? AND num_empleado = ? AND ts > ?');
    $s->execute([$ip, $num, $desde]);
    $porUsuario = (int)$s->fetchColumn();
    $s = $pdo->prepare('SELECT COUNT(*) FROM login_intentos WHERE ip = ? AND ts > ?');
    $s->execute([$ip, $desde]);
    $porIp = (int)$s->fetchColumn();
    if ($porUsuario >= LOGIN_MAX_USUARIO_IP || $porIp >= LOGIN_MAX_IP) {
        respond(['success' => false, 'message' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.'], 429);
    }
} catch (Throwable $e) {
    $throttle = false;
    error_log('login throttle off: ' . $e->getMessage());
}

try {
    $stmt = $pdo->prepare('SELECT id, num_empleado, nombre, rol, password_hash, estado, debe_cambiar_pwd FROM usuarios WHERE num_empleado = ? LIMIT 1');
    $stmt->execute([$num]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('login.php DB error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Error del servidor al consultar el usuario.'], 500);
}

// Siempre se compara contra un hash (aunque el usuario no exista) para que no se note la diferencia
$hash = $user ? $user['password_hash'] : '$2y$10$ib.Nw93VX4Kvxno.GhrOOelki3Lo/SRh.gmzfNklmWt19t4Mt5Sdy';
$ok = password_verify($pwd, $hash) && $user;

if (!$ok) {
    if ($throttle) {
        try {
            $pdo->prepare('INSERT INTO login_intentos (ip, num_empleado, ts) VALUES (?,?,?)')->execute([$ip, $num, time()]);
            if (random_int(1, 40) === 1) $pdo->prepare('DELETE FROM login_intentos WHERE ts < ?')->execute([time() - 86400]);
        } catch (Throwable $e) { error_log('login intento: ' . $e->getMessage()); }
    }
    respond(['success' => false, 'message' => 'Número de empleado o contraseña incorrectos']);
}

if ($user['estado'] !== 'activo') {
    respond(['success' => false, 'message' => 'Esta cuenta está desactivada. Habla con el administrador.']);
}

if ($throttle) {
    try { $pdo->prepare('DELETE FROM login_intentos WHERE ip = ? AND num_empleado = ?')->execute([$ip, $num]); } catch (Throwable $e) {}
}

session_regenerate_id(true);
$_SESSION = [];
$_SESSION['usuario_id']    = (int)$user['id'];
$_SESSION['rol']           = $user['rol'];
$_SESSION['num_empleado']  = $user['num_empleado'];
$_SESSION['nombre']        = $user['nombre'];
$_SESSION['login_time']    = time();
$_SESSION['last_activity'] = time();

try {
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([password_hash($pwd, PASSWORD_DEFAULT), $user['id']]);
    }
    $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$user['id']]);
} catch (PDOException $e) {
    error_log('login.php update warning: ' . $e->getMessage());
}

respond([
    'success' => true,
    'data' => [
        'num_empleado' => $user['num_empleado'],
        'nombre'       => $user['nombre'],
        'rol'          => $user['rol'],
        'debe_cambiar' => (bool)$user['debe_cambiar_pwd'],
    ],
]);
