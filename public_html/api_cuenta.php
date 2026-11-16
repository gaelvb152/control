<?php
/**
 * api_cuenta.php — cualquier usuario con sesión: cambiar su propia contraseña.
 * POST { accion:'cambiar_password', actual, nueva }
 */
require_once __DIR__ . '/lib.php';
requireLogin();
csrfGuard();
$in = readJson();

if (($in['accion'] ?? '') !== 'cambiar_password') jout(['success' => false, 'message' => 'Acción no válida']);

$actual = (string)($in['actual'] ?? '');
$nueva  = (string)($in['nueva'] ?? '');
$u = sessionUser($pdo);

if (strlen($nueva) < 8) jout(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres']);
if (strcasecmp($nueva, (string)$u['num_empleado']) === 0) jout(['success' => false, 'message' => 'La contraseña no puede ser igual a tu número de empleado']);
if ($nueva === $actual) jout(['success' => false, 'message' => 'La nueva contraseña debe ser distinta a la actual']);
if (!preg_match('/[A-Za-z]/', $nueva) || !preg_match('/\d/', $nueva)) jout(['success' => false, 'message' => 'Usa letras y números en la contraseña']);

try {
    $s = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = ?'); $s->execute([(int)$u['id']]);
    $hash = (string)$s->fetchColumn();
    if (!password_verify($actual, $hash)) jout(['success' => false, 'message' => 'La contraseña actual no es correcta']);
    $pdo->prepare('UPDATE usuarios SET password_hash = ?, debe_cambiar_pwd = 0 WHERE id = ?')->execute([password_hash($nueva, PASSWORD_DEFAULT), (int)$u['id']]);
    session_regenerate_id(true);
    jout(['success' => true]);
} catch (Throwable $e) {
    jout(['success' => false, 'message' => errDetail($e)], 500);
}
