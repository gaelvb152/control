<?php
// api_usuarios.php — SOLO administradores
require_once __DIR__ . '/lib.php';
requireAdmin();
csrfGuard();

$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? '';

// CREACIÓN INDIVIDUAL (Admin u Operador)
if ($accion === 'crear') {
    $num = trim($input['num_empleado'] ?? '');
    $nom = trim($input['nombre'] ?? '');
    $pwd = (string)($input['password'] ?? '');
    $rol = $input['rol'] ?? 'operador';
    if (!in_array($rol, ['admin', 'operador'], true)) { echo json_encode(['success' => false, 'message' => 'Rol no válido']); exit; }
    
    if (empty($num) || empty($nom) || empty($pwd)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos']);
        exit;
    }
    
    $hash = password_hash($pwd, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (num_empleado, password_hash, nombre, rol) VALUES (?, ?, ?, ?)");
        $stmt->execute([$num, $hash, $nom, $rol]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'message' => 'El número de empleado ya existe']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error de BD']);
        }
    }
    exit;
}

// CREACIÓN MASIVA INTELIGENTE (Operadores)
if ($accion === 'masivo') {
    $datos = trim($input['datos']);
    if (empty($datos)) {
        echo json_encode(['success' => false, 'message' => 'No hay datos para procesar']);
        exit;
    }
    
    $lineas = explode("\n", $datos);
    $creados = 0;
    $omitidos = 0;
    
    $stmt = $pdo->prepare("INSERT INTO usuarios (num_empleado, password_hash, nombre, rol) VALUES (?, ?, ?, 'operador')");
    
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if (empty($linea)) continue;
        
        // Expresión regular que separa el primer bloque alfanumérico (Número) del resto (Nombre)
        // Ignora cualquier espacio, tabulación de Excel (\t), guion (-) o diagonal (/) que esté en medio.
        if (preg_match('/^([A-Za-z0-9]+)[\s\t\/\-,]+(.+)$/', $linea, $coincidencias)) {
            $num = trim($coincidencias[1]);
            $nom = trim($coincidencias[2]);
        } else {
            // Si por alguna razón la línea no tiene formato legible, se omite
            $omitidos++;
            continue;
        }
        
        // Su contraseña inicial será su mismo número de empleado
        $hash = password_hash($num, PASSWORD_DEFAULT); 
        
        try {
            $stmt->execute([$num, $hash, $nom]);
            $creados++;
        } catch (PDOException $e) {
            // Si el número de empleado ya existe en la base de datos, lo salta para evitar errores
            $omitidos++;
        }
    }
    
    echo json_encode(['success' => true, 'creados' => $creados, 'omitidos' => $omitidos]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>