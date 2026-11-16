<?php
/**
 * api_notificaciones.php
 * GET  → avisos sin leer del usuario en sesión (el operador los ve en su pantalla)
 * POST → { accion:'leer', id } | { accion:'leer_todas' } | (admin) { accion:'borrar_leidas' }
 */
require_once __DIR__ . '/lib.php';
requireLogin();
$me = meNum();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfGuard();
    $in = readJson();
    $a = $in['accion'] ?? '';
    try {
        if ($a === 'leer') {
            $pdo->prepare('UPDATE notificaciones SET leido = 1 WHERE id = ? AND op_num = ?')->execute([(int)($in['id'] ?? 0), $me]);
        } elseif ($a === 'leer_todas') {
            $pdo->prepare('UPDATE notificaciones SET leido = 1 WHERE op_num = ?')->execute([$me]);
        } elseif ($a === 'borrar_leidas') {
            requireAdmin();
            $n = $pdo->exec('DELETE FROM notificaciones WHERE leido = 1');
            jout(['success' => true, 'borradas' => $n]);
        } else {
            jout(['success' => false, 'message' => 'Acción no válida']);
        }
        jout(['success' => true]);
    } catch (Throwable $e) {
        jout(['success' => false, 'message' => 'Error del servidor']);
    }
}

try {
    $s = $pdo->prepare("SELECT id, viaje_id, mensaje, " . msCol('fecha') . " AS ts FROM notificaciones WHERE op_num = ? AND leido = 0 ORDER BY fecha DESC, id DESC LIMIT 30");
    $s->execute([$me]);
    $rows = array_map(function ($r) {
        return ['id' => (string)$r['id'], 'tripId' => $r['viaje_id'], 'text' => $r['mensaje'], 'ts' => (int)$r['ts'], 'read' => false];
    }, $s->fetchAll(PDO::FETCH_ASSOC));
    jout(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    jout(['success' => false, 'error' => 'No se pudieron cargar los avisos']);
}