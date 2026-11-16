<?php
/**
 * auth_check.php — control de acceso por rol.
 *
 * Roles:
 *   admin          → todo (asignaciones, usuarios, inventario, entregas, estadísticas)
 *   administrativo → estadísticas, calendario, reportes, ver asignaciones/firmas/comentarios y actualizar inventario
 *   operador       → solo sus propios viajes
 *
 * En CADA petición se vuelve a leer al usuario de la base: si lo desactivaron o le cambiaron el rol,
 * el cambio aplica de inmediato (no hay que esperar a que caduque su sesión).
 */
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/db.php';

const ROLES_VALIDOS = ['admin', 'administrativo', 'operador'];

function authDeny($code, $msg) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

/** Revalida la sesión contra la base. Devuelve la fila del usuario o null. */
function sessionUser($pdo) {
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (empty($_SESSION['usuario_id'])) return null;
    try {
        $s = $pdo->prepare('SELECT id, num_empleado, nombre, rol, estado, tractor, debe_cambiar_pwd FROM usuarios WHERE id = ? LIMIT 1');
        $s->execute([(int)$_SESSION['usuario_id']]);
        $u = $s->fetch();
    } catch (Throwable $e) {
        error_log('sessionUser: ' . $e->getMessage());
        return null;
    }
    if (!$u || $u['estado'] !== 'activo') { $_SESSION = []; return null; }
    $_SESSION['rol'] = $u['rol'];
    $_SESSION['num_empleado'] = $u['num_empleado'];
    $_SESSION['nombre'] = $u['nombre'];
    return $cache = $u;
}

function requireLogin() {
    global $pdo;
    if (!sessionUser($pdo)) authDeny(401, 'Sesión no válida, vuelve a iniciar sesión');
}

/** Permite solo los roles indicados. */
function requireRole(array $roles) {
    requireLogin();
    if (!in_array($_SESSION['rol'] ?? '', $roles, true)) authDeny(403, 'No tienes permiso para esto');
}

/** Solo administrador. */
function requireAdmin() { requireRole(['admin']); }

/** Administrador o administrativo. */
function requireStaff() { requireRole(['admin', 'administrativo']); }
