<?php

// db.php — conexión PDO.
// Las credenciales ya NO viven en el código: se leen de config.local.php o de variables
// de entorno (ver config.php). El comportamiento de la conexión no cambió.
require_once __DIR__ . '/config.php';

$cfg = cb_db_config();

try {
    $pdo = new PDO(
        cb_db_dsn($cfg),
        $cfg['user'],
        $cfg['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // fuerza prepared statements reales
        ]
    );
} catch (PDOException $e) {
    // Escribimos el error real en el log del servidor para futuras depuraciones
    error_log("Fallo la conexion a la BD: " . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    // Nunca se muestra el mensaje real al cliente
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}
unset($cfg);
