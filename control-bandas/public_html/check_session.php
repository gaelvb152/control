<?php
/**
 * check_session.php
 * La app lo llama al arrancar/refrescar para saber si ya hay sesión activa.
 */
require_once __DIR__ . '/session_config.php';

if (!empty($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => true,
        'data' => [
            'num_empleado' => $_SESSION['num_empleado'] ?? '',
            'nombre'       => $_SESSION['nombre'] ?? '',
            'rol'          => $_SESSION['rol'] ?? 'operador',
        ],
    ]);
} else {
    echo json_encode(['success' => false]);
}