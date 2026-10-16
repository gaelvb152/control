<?php
/**
 * api_inventario.php — SOLO administradores.
 * GET  → nodrizas, stock, configuración
 * POST → actualizar_nodriza · crear_nodriza · eliminar_nodriza · stock_movimiento · transferir · actualizar_config · importar
 * Cada cambio de bandas queda en movimientos_inventario (quién, cuándo, antes/después, motivo).
 */
require_once __DIR__ . '/lib.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfGuard();
    $in = readJson();
    $accion = $in['accion'] ?? '';

    tx($pdo, function () use ($pdo, $in, $accion) {
        if ($accion === 'actualizar_nodriza') {
            $c = validNod($in['codigo'] ?? '');
            $n = $in['bandas'] ?? null;
            if (!is_numeric($n) || (int)$n < 0 || (int)$n > 9999) fail('Escribe un número válido');
            $motivo = trim((string)($in['motivo'] ?? ''));
            nodSet($pdo, $c, (int)$n, 'ajuste_manual', $motivo !== '' ? $motivo : 'Ajuste manual del administrador');
        }
        elseif ($accion === 'crear_nodriza') {
            $c = validNod($in['codigo'] ?? '');
            $n = (int)($in['bandas'] ?? 0);
            if ($n < 0 || $n > 9999) fail('Cantidad no válida');
            $s = $pdo->prepare('SELECT codigo FROM nodrizas WHERE codigo = ? FOR UPDATE'); $s->execute([$c]);
            if ($s->fetch()) fail('La nodriza ' . $c . ' ya existe');
            $pdo->prepare('INSERT INTO nodrizas (codigo, bandas, actualizado_por, ultima_actualizacion) VALUES (?,?,?,NOW())')->execute([$c, $n, meNum()]);
            movLog($pdo, 'alta', 'NODRIZA', $c, null, $n, 'Alta de nodriza');
        }
        elseif ($accion === 'eliminar_nodriza') {
            $c = validNod($in['codigo'] ?? '');
            $s = $pdo->prepare('SELECT COUNT(*) FROM viajes WHERE nodriza_codigo = ?'); $s->execute([$c]);
            if ((int)$s->fetchColumn() > 0) fail('No se puede eliminar: ' . $c . ' tiene viajes registrados. Déjala en 0.');
            $r = nodLock($pdo, $c);
            $pdo->prepare('DELETE FROM nodrizas WHERE codigo = ?')->execute([$c]);
            movLog($pdo, 'baja', 'NODRIZA', $c, $r['bandas'] === null ? null : (int)$r['bandas'], 0, 'Nodriza eliminada del inventario');
        }
        elseif ($accion === 'stock_movimiento') {
            $u = ubicCode($in['ubicacion'] ?? '');
            $modo = $in['modo'] ?? '';
            $n = $in['cantidad'] ?? null;
            if (!is_numeric($n) || (int)$n < 0 || (int)$n > 100000) fail('Escribe una cantidad válida');
            $n = (int)$n;
            $motivo = mb_substr(trim((string)($in['motivo'] ?? '')), 0, 200);
            if ($modo === 'entrada') {
                if ($n <= 0) fail('La cantidad debe ser mayor a 0');
                stockAdd($pdo, $u, $n, 'stock_entrada', $motivo !== '' ? $motivo : 'Entrada a ' . ubicLabel($u));
            } elseif ($modo === 'salida') {
                if ($n <= 0) fail('La cantidad debe ser mayor a 0');
                stockAdd($pdo, $u, -$n, 'stock_salida', $motivo !== '' ? $motivo : 'Salida de ' . ubicLabel($u));
            } elseif ($modo === 'fijar') {
                stockSet($pdo, $u, $n, 'stock_ajuste', $motivo !== '' ? $motivo : 'Conteo físico de ' . ubicLabel($u));
            } else fail('Tipo de movimiento no válido');
        }
        elseif ($accion === 'transferir') {
            $n = (int)($in['n'] ?? 0);
            if ($n <= 0 || $n > 9999) fail('Escribe cuántas bandas mover');
            $o = (string)($in['origen'] ?? ''); $d = (string)($in['destino'] ?? '');
            if (!in_array($o, ['almacen', 'oficina', 'nodriza'], true) || !in_array($d, ['almacen', 'oficina', 'nodriza'], true)) fail('Origen o destino no válido');
            $oc = strtoupper(trim((string)($in['origenCodigo'] ?? ''))); $dc = strtoupper(trim((string)($in['destinoCodigo'] ?? '')));
            if ($o === $d && ($o !== 'nodriza' || $oc === $dc)) fail('El origen y el destino no pueden ser el mismo');
            $nota = mb_substr(trim((string)($in['nota'] ?? '')), 0, 150);
            $lo = takeFrom($pdo, $o, $oc, $n, 'transferencia', 'Transferencia hacia ' . ($d === 'nodriza' ? 'nodriza ' . $dc : $d) . ($nota !== '' ? ' (' . $nota . ')' : ''));
            putInto($pdo, $d, $dc, $n, 'transferencia', 'Transferencia desde ' . $lo . ($nota !== '' ? ' (' . $nota . ')' : ''));
        }
        elseif ($accion === 'actualizar_config') {
            $p = (int)($in['perUnit'] ?? 0);
            if ($p < 1 || $p > 100) fail('Escribe un número entre 1 y 100');
            $pdo->prepare("INSERT INTO configuracion (llave, valor) VALUES ('bandas_por_unidad', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([(string)$p]);
        }
        elseif ($accion === 'importar') {
            $exists = $pdo->prepare('SELECT codigo FROM nodrizas WHERE codigo = ?');
            $ins = $pdo->prepare('INSERT INTO nodrizas (codigo, bandas, actualizado_por, ultima_actualizacion) VALUES (?,?,?,NOW())');
            foreach ((array)($in['nodrizas'] ?? []) as $row) {
                $c = validNod($row[0] ?? ''); $n = (int)($row[1] ?? 0);
                if ($n < 0 || $n > 9999) fail('Cantidad no válida para ' . $c);
                $exists->execute([$c]);
                if ($exists->fetch()) nodSet($pdo, $c, $n, 'importacion', 'Carga masiva de inventario');
                else { $ins->execute([$c, $n, meNum()]); movLog($pdo, 'alta', 'NODRIZA', $c, null, $n, 'Alta por carga masiva'); }
            }
            $st = $in['stock'] ?? null;
            if (is_array($st)) {
                foreach (['almacen', 'oficina'] as $k) {
                    if (isset($st[$k]) && is_numeric($st[$k]) && (int)$st[$k] >= 0) stockSet($pdo, ubicCode($k), (int)$st[$k], 'importacion', 'Carga masiva de inventario');
                }
            }
        }
        else fail('Acción no válida');
    });
    jout(['success' => true]);
}

try {
    $nodrizas = [];
    foreach ($pdo->query('SELECT codigo, bandas, actualizado_por, UNIX_TIMESTAMP(ultima_actualizacion) * 1000 AS ts FROM nodrizas ORDER BY codigo') as $r) {
        $nodrizas[$r['codigo']] = ['bandas' => $r['bandas'] !== null ? (int)$r['bandas'] : null, 'updated' => $r['ts'] !== null ? (int)$r['ts'] : null, 'by' => $r['actualizado_por']];
    }
    $stock = ['almacen' => null, 'oficina' => null]; $stockTs = ['almacen' => null, 'oficina' => null];
    foreach ($pdo->query('SELECT ubicacion, cantidad, UNIX_TIMESTAMP(ultima_actualizacion) * 1000 AS ts FROM stock_general') as $r) {
        $k = strtolower($r['ubicacion']);
        if (array_key_exists($k, $stock)) { $stock[$k] = (int)$r['cantidad']; $stockTs[$k] = $r['ts'] !== null ? (int)$r['ts'] : null; }
    }
    $perUnit = 4;
    $row = $pdo->query("SELECT valor FROM configuracion WHERE llave = 'bandas_por_unidad'")->fetch();
    if ($row) $perUnit = max(1, (int)$row['valor']);
    jout(['success' => true, 'nodrizas' => (object)$nodrizas, 'stock' => $stock, 'stockTs' => $stockTs, 'perUnit' => $perUnit]);
} catch (Throwable $e) {
    jout(['success' => false, 'error' => 'Error al cargar inventario: ' . $e->getMessage()]);
}