<?php
/**
 * api_reportes.php — SOLO administradores.
 * GET ?desde=<ms>&hasta=<ms>[&tipo=..][&codigo=..]
 *   movimientos:    bitácora de cambios de inventario
 *   discrepancias:  viajes donde el operador contó distinto a lo que decía el sistema
 */
require_once __DIR__ . '/lib.php';
requireAdmin();

$desde = (int)($_GET['desde'] ?? 0);
$hasta = (int)($_GET['hasta'] ?? 0);
if ($hasta <= 0) $hasta = nowMs() + 86400000;
if ($desde <= 0) $desde = $hasta - 7 * 86400000;
$tipo = preg_replace('/[^a-z_]/', '', (string)($_GET['tipo'] ?? ''));
$codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['codigo'] ?? '')));

try {
    $sql = 'SELECT ts, usuario, tipo, ubicacion, codigo, antes, despues, diferencia, motivo, viaje_id FROM movimientos_inventario WHERE ts >= ? AND ts < ?';
    $p = [$desde, $hasta];
    if ($tipo !== '' && $tipo !== 'todos') { $sql .= ' AND tipo = ?'; $p[] = $tipo; }
    if ($codigo !== '') { $sql .= ' AND (codigo = ? OR (codigo IS NULL AND ubicacion = ?))'; $p[] = $codigo; $p[] = $codigo; }
    $sql .= ' ORDER BY ts DESC, id DESC LIMIT 1000';
    $s = $pdo->prepare($sql); $s->execute($p);
    $mov = array_map(function ($r) {
        return [
            'ts' => (int)$r['ts'], 'usuario' => $r['usuario'], 'tipo' => $r['tipo'], 'ubicacion' => $r['ubicacion'], 'codigo' => $r['codigo'],
            'antes' => $r['antes'] === null ? null : (int)$r['antes'], 'despues' => (int)$r['despues'], 'diferencia' => (int)$r['diferencia'],
            'motivo' => $r['motivo'], 'viaje_id' => $r['viaje_id'],
        ];
    }, $s->fetchAll(PDO::FETCH_ASSOC));

    // Discrepancias por fecha del viaje (YYYY-MM-DD), tomando la zona horaria del navegador del admin como referencia
    $fd = date('Y-m-d', (int)($desde / 1000) - 86400); $fh = date('Y-m-d', (int)($hasta / 1000) + 86400);
    $s = $pdo->prepare("SELECT id, fecha, op_num, op_nombre, nodriza_codigo, conteo_sistema, conteo_operador, (foto_nodriza IS NOT NULL AND foto_nodriza <> '') AS has_photo, foto_fecha
        FROM viajes WHERE verificado = 1 AND fecha BETWEEN ? AND ? AND (conteo_sistema IS NULL OR conteo_sistema <> conteo_operador) ORDER BY fecha DESC, op_nombre ASC LIMIT 500");
    $s->execute([$fd, $fh]);
    $disc = array_map(function ($r) {
        return [
            'id' => $r['id'], 'fecha' => $r['fecha'], 'opNum' => $r['op_num'], 'opName' => $r['op_nombre'], 'nodriza' => $r['nodriza_codigo'],
            'sys' => $r['conteo_sistema'] === null ? null : (int)$r['conteo_sistema'], 'base' => (int)$r['conteo_operador'], 'hasPhoto' => (bool)$r['has_photo'],
        ];
    }, $s->fetchAll(PDO::FETCH_ASSOC));

    jout(['success' => true, 'movimientos' => $mov, 'discrepancias' => $disc]);
} catch (Throwable $e) {
    jout(['success' => false, 'error' => 'Error al generar el reporte: ' . $e->getMessage()]);
}