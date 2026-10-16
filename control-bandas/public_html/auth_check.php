<?php
/**
 * auth_check.php
 * Inclúyelo al inicio de endpoints que requieran sesión, por ejemplo en
 * api_inventario.php:
 *
 *   require_once __DIR__ . '/auth_check.php';
 *   requireAdmin(); // o requireLogin() si basta con estar logueado
 *
 * (api_usuarios.php ya hace esta misma validación a mano con
 * $_SESSION['usuario_id'] / $_SESSION['rol']; estas funciones son el
 * equivalente reutilizable para los demás endpoints.)
 */
require_once __DIR__ . '/session_config.php';

function requireLogin() {
    if (empty($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesión no válida, vuelve a iniciar sesión']);
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (($_SESSION['rol'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para esto']);
        exit;
    }
}