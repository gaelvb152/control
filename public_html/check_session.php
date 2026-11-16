<?php
/**
 * check_session.php
 * La app lo llama al arrancar/refrescar para saber si ya hay sesión activa.
 * Revalida contra la base: si el usuario fue desactivado, la sesión ya no sirve.
 */
require_once __DIR__ . '/auth_check.php';

$u = sessionUser($pdo);
if ($u) {
    echo json_encode([
        'success' => true,
        'data' => [
            'num_empleado' => $u['num_empleado'],
            'nombre'       => $u['nombre'],
            'rol'          => $u['rol'],
            'tractor'      => (string)($u['tractor'] ?? ''),
            'debe_cambiar' => (bool)$u['debe_cambiar_pwd'],
        ],
    ]);
} else {
    echo json_encode(['success' => false]);
}
