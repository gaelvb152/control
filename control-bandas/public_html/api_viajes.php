<?php
/**
 * api_viajes.php — Viajes / asignaciones.
 * Toda la lógica vive aquí (el navegador ya no manda el viaje completo):
 *   Operador:  aceptar · contar · solicitar · cancelar_solicitud · firmar
 *   Admin:     importar_viajes · aprobar_solicitud · rechazar_solicitud · entregar · ajustar_total
 *              eliminar_viaje · eliminar_dia
 */
require_once __DIR__ . '/lib.php';
requireLogin();
$isAdmin = isAdminSession();
$me = meNum();

function viajeLock($pdo, $id) {
    $s = $pdo->prepare('SELECT * FROM viajes WHERE id = ? FOR UPDATE');
    $s->execute([(string)$id]);
    $v = $s->fetch();
    if (!$v) fail('El viaje no existe');
    return $v;
}
function ownTrip($v) {
    if ((string)$v['op_num'] !== meNum()) fail('Este viaje no es tuyo');
}
function logV($pdo, $id, $who, $text) {
    $pdo->prepare('INSERT INTO viajes_historial (viaje_id, usuario, accion, fecha) VALUES (?,?,?,NOW())')->execute([$id, $who, mb_substr($text, 0, 490)]);
}
function pendingCount($pdo, $id) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM viajes_solicitudes WHERE viaje_id = ? AND estado = 'pendiente'");
    $s->execute([$id]);
    return (int)$s->fetchColumn();
}
/** Recalcula total y estado del viaje a partir de sus datos. Devuelve el total (o null si no se ha contado). */
function recalcViaje($pdo, $id) {
    $s = $pdo->prepare('SELECT * FROM viajes WHERE id = ?'); $s->execute([$id]); $v = $s->fetch();
    $e = $pdo->prepare('SELECT COALESCE(SUM(cantidad),0) FROM viajes_entregas WHERE viaje_id = ?'); $e->execute([$id]);
    $a = $pdo->prepare('SELECT COALESCE(SUM(diferencia),0) FROM viajes_ajustes WHERE viaje_id = ?'); $a->execute([$id]);
    $total = $v['verificado'] ? ((int)$v['conteo_operador'] + (int)$e->fetchColumn() + (int)$a->fetchColumn()) : null;
    $estado = statusOf($v, pendingCount($pdo, $id) > 0);
    $pdo->prepare('UPDATE viajes SET total_actual = ?, estado = ? WHERE id = ?')->execute([$total, $estado, $id]);
    return $total;
}
function tripOut($pdo, $id) {
    $s = $pdo->prepare('SELECT ' . VIAJE_COLS . ' FROM viajes WHERE id = ?');
    $s->execute([$id]);
    $t = tripsFormat($pdo, $s->fetchAll(PDO::FETCH_ASSOC));
    return $t[0] ?? null;
}
function plural($n) { return $n . ' ' . ($n === 1 ? 'banda' : 'bandas'); }

/** Entrega de bandas a un viaje: descuenta del origen, suma a la nodriza del viaje y deja todo registrado. */
function doEntrega($pdo, $v, $n, $origen, $codigo, $nota, $solId = '') {
    $solId = (string)$solId;
    if (!$v['verificado']) fail('El operador aún no confirma el conteo');
    $n = (int)$n;
    if ($n <= 0 || $n > 999) fail('Escribe cuántas bandas entregas');
    $nota = trim((string)$nota);
    $motivo = 'Entrega al viaje ' . $v['op_num'] . ' / ' . $v['nodriza_codigo'] . ($nota !== '' ? ' (' . $nota . ')' : '');
    $label = takeFrom($pdo, $origen, $codigo, $n, 'entrega', $motivo, $v['id'], $v['nodriza_codigo']);
    nodAdd($pdo, $v['nodriza_codigo'], $n, 'entrega', 'Recibió bandas de ' . $label . ' (viaje ' . $v['op_num'] . ')', $v['id']);
    $pdo->prepare('INSERT INTO viajes_entregas (id, viaje_id, cantidad, origen, nota, fecha) VALUES (?,?,?,?,?,NOW())')->execute([newId('e'), $v['id'], $n, $label, $nota]);
    if ($solId !== '') {
        $pdo->prepare("UPDATE viajes_solicitudes SET estado = 'atendida', fecha_cierre = NOW() WHERE id = ? AND viaje_id = ? AND estado = 'pendiente'")->execute([$solId, $v['id']]);
    }
    logV($pdo, $v['id'], 'admin', 'Entregó ' . plural($n) . ' desde ' . $label . ($nota !== '' ? ' (' . $nota . ')' : ''));
    $total = recalcViaje($pdo, $v['id']);
    notif($pdo, $v['op_num'], $v['id'], 'Se te entregaron ' . plural($n) . ' (' . $label . '). Tu total ahora es ' . $total . '.');
    return [$total, $label];
}

/* ======================= POST ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfGuard();
    $in = readJson();
    $accion = $in['accion'] ?? '';
    $id = (string)($in['id'] ?? '');

    /* ---------- OPERADOR ---------- */
    if ($accion === 'aceptar') {
        tx($pdo, function () use ($pdo, $id, $me) {
            $v = viajeLock($pdo, $id); ownTrip($v);
            if ($v['firmado']) fail('El viaje ya está firmado');
            if (!$v['aceptado']) {
                $pdo->prepare('UPDATE viajes SET aceptado = 1, aceptado_fecha = ? WHERE id = ?')->execute([nowMs(), $id]);
                logV($pdo, $id, 'operador', 'Aceptó la asignación: ' . $v['req_texto']);
                recalcViaje($pdo, $id);
            }
        });
        jout(['success' => true, 'trip' => tripOut($pdo, $id)]);
    }

    if ($accion === 'contar') {
        tx($pdo, function () use ($pdo, $id, $in) {
            $v = viajeLock($pdo, $id); ownTrip($v);
            if ($v['firmado']) fail('El viaje ya está firmado');
            if (!$v['aceptado']) fail('Primero acepta la asignación');
            if ($v['verificado']) fail('El conteo ya fue confirmado. Si está mal, pídele al administrador que lo ajuste.');
            if (!isset($in['conteo']) || !is_numeric($in['conteo'])) fail('Escribe cuántas bandas trae la nodriza');
            $cnt = (int)$in['conteo'];
            if ($cnt < 0 || $cnt > 9999) fail('Cantidad no válida');

            $nod = nodLock($pdo, $v['nodriza_codigo']);
            $sys = (int)($nod['bandas'] ?? 0);
            $foto = $in['foto'] ?? null;

            // Regla: si el operador dice que hay MENOS de lo que indica el sistema, la foto es obligatoria
            if ($sys > 0 && $cnt < $sys && !$foto) {
                fail('El conteo es menor al del sistema (' . $sys . '). Toma una foto de la nodriza para continuar.', ['needPhoto' => true, 'sys' => $sys]);
            }
            $fname = $foto ? savePhoto($foto, $id) : null;
            $invOk = ($sys > 0 && $cnt === $sys);

            $pdo->prepare('UPDATE viajes SET verificado = 1, conteo_sistema = ?, conteo_operador = ?, fuente_conteo = ?, foto_nodriza = ?, foto_fecha = ? WHERE id = ?')
                ->execute([$sys, $cnt, $invOk ? 'sistema' : 'operador', $fname, $fname ? nowMs() : null, $id]);

            if ($invOk) logV($pdo, $id, 'operador', 'Confirmó que la nodriza trae ' . $cnt . ' bandas (coincide con el sistema)');
            elseif ($sys > 0) logV($pdo, $id, 'operador', 'Corrigió el total: el sistema decía ' . $sys . ' y el operador reporta ' . $cnt . ($fname ? ' (con foto)' : ''));
            else logV($pdo, $id, 'operador', 'Reportó ' . $cnt . ' bandas (no había total registrado)' . ($fname ? ' (con foto)' : ''));

            // El inventario de la nodriza queda igual a lo que el operador contó (queda en la bitácora)
            if ($cnt !== $sys) {
                nodSet($pdo, $v['nodriza_codigo'], $cnt, 'conteo_operador', 'Conteo del operador ' . $v['op_num'] . ' (el sistema decía ' . $sys . ')' . ($fname ? ' · con foto' : ''), $id);
            }
            recalcViaje($pdo, $id);
        });
        jout(['success' => true, 'trip' => tripOut($pdo, $id)]);
    }

    if ($accion === 'solicitar') {
        tx($pdo, function () use ($pdo, $id, $in) {
            $v = viajeLock($pdo, $id); ownTrip($v);
            if ($v['firmado']) fail('El viaje ya está firmado');
            if (!$v['verificado']) fail('Primero cuenta las bandas de la nodriza');
            $n = (int)($in['n'] ?? 0);
            if ($n <= 0 || $n > 999) fail('Escribe cuántas bandas necesitas');
            if (pendingCount($pdo, $id) > 0) fail('Ya tienes una solicitud pendiente. Cancélala si quieres cambiarla.');
            $nota = mb_substr(trim((string)($in['nota'] ?? '')), 0, 200);
            $pdo->prepare("INSERT INTO viajes_solicitudes (id, viaje_id, cantidad, nota, estado, fecha_solicitud) VALUES (?,?,?,?,'pendiente',NOW())")->execute([newId('s'), $id, $n, $nota]);
            logV($pdo, $id, 'operador', 'Solicitó ' . plural($n) . ($nota !== '' ? ' (' . $nota . ')' : ''));
            recalcViaje($pdo, $id);
        });
        jout(['success' => true, 'trip' => tripOut($pdo, $id)]);
    }

    if ($accion === 'cancelar_solicitud') {
        tx($pdo, function () use ($pdo, $id) {
            $v = viajeLock($pdo, $id); ownTrip($v);
            $pdo->prepare("UPDATE viajes_solicitudes SET estado = 'cancelada', fecha_cierre = NOW() WHERE viaje_id = ? AND estado = 'pendiente'")->execute([$id]);
            logV($pdo, $id, 'operador', 'Canceló su solicitud de bandas');
            recalcViaje($pdo, $id);
        });
        jout(['success' => true, 'trip' => tripOut($pdo, $id)]);
    }

    if ($accion === 'firmar') {
        tx($pdo, function () use ($pdo, $id, $in) {
            $v = viajeLock($pdo, $id); ownTrip($v);
            if ($v['firmado']) fail('Este viaje ya fue firmado');
            if (!$v['aceptado'] || !$v['verificado']) fail('Primero acepta la asignación y cuenta las bandas');
            if (pendingCount($pdo, $id) > 0) fail('Todavía hay una solicitud pendiente de aprobar');
            $img = (string)($in['firma'] ?? '');
            if (strpos($img, 'data:image/png;base64,') !== 0 || strlen($img) > 1500000) fail('La firma no es válida');
            $bin = base64_decode(substr($img, 22), true);
            if ($bin === false || substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") fail('La firma no es válida');

            $total = recalcViaje($pdo, $id);
            $pdo->prepare("UPDATE viajes SET firmado = 1, firma_fecha = NOW(), total_firmado = ?, firma_img = ?, estado = 'confirmado' WHERE id = ?")->execute([$total, $img, $id]);
            nodSet($pdo, $v['nodriza_codigo'], $total, 'firma', 'Firma del operador ' . $v['op_num'] . ': la nodriza queda con ' . $total, $id);
            logV($pdo, $id, 'operador', 'Firmó y confirmó el viaje con ' . $total . ' bandas');
        });
        jout(['success' => true, 'trip' => tripOut($pdo, $id)]);
    }

    /* ---------- ADMIN ---------- */
    if (in_array($accion, ['importar_viajes', 'aprobar_solicitud', 'rechazar_solicitud', 'entregar', 'ajustar_total', 'eliminar_viaje', 'eliminar_dia'], true)) {
        requireAdmin();
    }

    if ($accion === 'importar_viajes') {
        $fecha = (string)($in['fecha'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) jout(['success' => false, 'message' => 'Fecha no válida']);
        $made = 0; $skipped = 0; $errores = [];
        $stmtCheck = $pdo->prepare('SELECT id FROM viajes WHERE id = ?');
        $stmtNod   = $pdo->prepare('SELECT codigo FROM nodrizas WHERE codigo = ?');
        $stmtOp    = $pdo->prepare('SELECT num_empleado FROM usuarios WHERE num_empleado = ?');
        $insV = $pdo->prepare("INSERT INTO viajes (id, fecha, op_num, op_nombre, nodriza_codigo, req_texto, requerido, estado, verificado, firmado, aceptado) VALUES (?,?,?,?,?,?,?,'asignado',0,0,0)");
        $insR = $pdo->prepare('INSERT INTO viajes_requisitos (viaje_id, tipo, cantidad) VALUES (?,?,?)');
        $insH = $pdo->prepare('INSERT INTO viajes_historial (viaje_id, usuario, accion, fecha) VALUES (?,?,?,NOW())');

        foreach ((array)($in['viajes'] ?? []) as $a) {
            $opNum = (string)($a['opNum'] ?? ''); $nod = strtoupper((string)($a['nodriza'] ?? ''));
            $viaje_id = $fecha . '_' . $opNum . '_' . $nod;
            $etiqueta = 'Operador ' . $opNum . ' / Nodriza ' . $nod;
            $stmtCheck->execute([$viaje_id]);
            if ($stmtCheck->fetch()) { $skipped++; continue; }
            $stmtNod->execute([$nod]);
            if (!$stmtNod->fetch()) { $errores[] = $etiqueta . ': la nodriza "' . $nod . '" no está registrada en Inventario. Regístrala ahí primero.'; continue; }
            $stmtOp->execute([$opNum]);
            if (!$stmtOp->fetch()) { $errores[] = $etiqueta . ': el operador "' . $opNum . '" no está registrado en Usuarios. Créalo ahí primero.'; continue; }
            try {
                $pdo->beginTransaction();
                $insV->execute([$viaje_id, $fecha, $opNum, (string)($a['opName'] ?? ''), $nod, (string)($a['reqText'] ?? ''), (int)($a['required'] ?? 0)]);
                foreach ((array)($a['req'] ?? []) as $req) { $insR->execute([$viaje_id, (string)$req['tipo'], (int)$req['n']]); }
                $insH->execute([$viaje_id, 'admin', 'Viaje asignado: ' . ($a['reqText'] ?? '')]);
                $pdo->commit();
                $made++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errores[] = $etiqueta . ': ERROR SQL - ' . $e->getMessage();
            }
        }
        jout(['success' => true, 'made' => $made, 'skipped' => $skipped, 'errores' => $errores]);
    }

    if ($accion === 'aprobar_solicitud') {
        $solId = (string)($in['solicitud_id'] ?? '');
        $res = tx($pdo, function () use ($pdo, $id, $in, $solId) {
            $v = viajeLock($pdo, $id);
            $s = $pdo->prepare('SELECT * FROM viajes_solicitudes WHERE id = ? AND viaje_id = ? FOR UPDATE');
            $s->execute([$solId, $id]);
            $sol = $s->fetch();
            if (!$sol) fail('La solicitud no existe');
            if ($sol['estado'] !== 'pendiente') fail('Esta solicitud ya fue atendida, cancelada o rechazada');
            $n = isset($in['cantidad']) && (int)$in['cantidad'] > 0 ? (int)$in['cantidad'] : (int)$sol['cantidad'];
            return doEntrega($pdo, $v, $n, (string)($in['origen'] ?? ''), $in['codigo'] ?? '', $in['nota'] ?? '', $solId);
        });
        jout(['success' => true, 'total' => $res[0], 'origen' => $res[1], 'trip' => tripOut($pdo, $id)]);
    }

    if ($accion === 'rechazar_solicitud') {
        $solId = (string)($in['solicitud_id'] ?? '');
        tx($pdo, function () use ($pdo, $id, $in, $solId) {
            $v = viajeLock($pdo, $id);
            $s = $pdo->prepare('SELECT * FROM viajes_solicitudes WHERE id = ? AND viaje_id = ? FOR UPDATE');
            $s->execute([$solId, $id]);
            $sol = $s->fetch();
            if (!$sol) fail('La solicitud no existe');
            if ($sol['estado'] !== 'pendiente') fail('Esta solicitud ya fue atendida, cancelada o rechazada');
            $nota = mb_substr(trim((string)($in['nota'] ?? '')), 0, 200);
            $pdo->prepare("UPDATE viajes_solicitudes SET estado = 'rechazada', fecha_cierre = NOW() WHERE id = ?")->execute([$solId]);
            logV($pdo, $id, 'admin', 'Rechazó la solicitud de ' . plural((int)$sol['cantidad']) . ($nota !== '' ? ' (' . $nota . ')' : ''));
            recalcViaje($pdo, $id);
            notif($pdo, $v['op_num'], $id, 'No se aprobó tu solicitud de ' . plural((int)$sol['cantidad']) . ($nota !== '' ? ': ' . $nota : '. Habla con el administrador.'));
        });
        jout(['success' => true, 'trip' => tripOut($pdo, $id)]);
    }

    if ($accion === 'entregar') {
        $res = tx($pdo, function () use ($pdo, $id, $in) {
            $v = viajeLock($pdo, $id);
            return doEntrega($pdo, $v, $in['n'] ?? 0, (string)($in['origen'] ?? ''), $in['codigo'] ?? '', $in['nota'] ?? '', (string)($in['solicitud_id'] ?? ''));
        });
        jout(['success' => true, 'total' => $res[0], 'trip' => tripOut($pdo, $id)]);
    }

    if ($accion === 'ajustar_total') {
        tx($pdo, function () use ($pdo, $id, $in) {
            $v = viajeLock($pdo, $id);
            if (!$v['verificado']) fail('El operador aún no confirma el conteo');
            $n = (int)($in['total'] ?? -1);
            if ($n < 0 || $n > 9999) fail('Escribe el total correcto');
            $cur = recalcViaje($pdo, $id);
            if ($cur === $n) fail('Ese ya es el total actual');
            $nota = mb_substr(trim((string)($in['nota'] ?? '')), 0, 200);
            $pdo->prepare('INSERT INTO viajes_ajustes (id, viaje_id, cantidad_anterior, cantidad_nueva, diferencia, nota, fecha) VALUES (?,?,?,?,?,?,NOW())')->execute([newId('a'), $id, $cur, $n, $n - $cur, $nota]);
            nodSet($pdo, $v['nodriza_codigo'], $n, 'ajuste_viaje', 'Ajuste de total del viaje ' . $v['op_num'] . ($nota !== '' ? ' (' . $nota . ')' : ''), $id);
            logV($pdo, $id, 'admin', 'Ajustó el total de ' . $cur . ' a ' . $n . ($nota !== '' ? ' (' . $nota . ')' : ''));
            recalcViaje($pdo, $id);
            notif($pdo, $v['op_num'], $id, 'El administrador ajustó tu total de bandas a ' . $n . ($nota !== '' ? ': ' . $nota : '.'));
        });
        jout(['success' => true, 'trip' => tripOut($pdo, $id)]);
    }

    if ($accion === 'eliminar_viaje' || $accion === 'eliminar_dia') {
        if ($id === '' && $accion === 'eliminar_viaje') jout(['success' => false, 'message' => 'Falta el id del viaje']);
        $ids = [$id];
        if ($accion === 'eliminar_dia') {
            $fecha = (string)($in['fecha'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) jout(['success' => false, 'message' => 'Fecha no válida']);
            $s = $pdo->prepare('SELECT id FROM viajes WHERE fecha = ?'); $s->execute([$fecha]);
            $ids = array_column($s->fetchAll(PDO::FETCH_ASSOC), 'id');
        }
        $n = tx($pdo, function () use ($pdo, $ids) {
            foreach ($ids as $vid) {
                $s = $pdo->prepare('SELECT foto_nodriza FROM viajes WHERE id = ?'); $s->execute([$vid]); $foto = $s->fetchColumn();
                foreach (['viajes_entregas', 'viajes_ajustes', 'viajes_solicitudes', 'viajes_historial', 'viajes_requisitos'] as $t) {
                    $pdo->prepare("DELETE FROM $t WHERE viaje_id = ?")->execute([$vid]);
                }
                $pdo->prepare('DELETE FROM notificaciones WHERE viaje_id = ?')->execute([$vid]);
                $pdo->prepare('DELETE FROM viajes WHERE id = ?')->execute([$vid]);
                deletePhoto($foto);
            }
            return count($ids);
        });
        jout(['success' => true, 'eliminados' => $n]);
    }

    jout(['success' => false, 'message' => 'Acción no válida']);
}

/* ======================= GET ======================= */
$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');

try {
    if (isset($_GET['pendientes'])) {
        if (!$isAdmin) jout(['success' => false, 'error' => 'No autorizado'], 403);
        $stmt = $pdo->query("SELECT " . VIAJE_COLS . " FROM viajes v WHERE EXISTS (SELECT 1 FROM viajes_solicitudes s WHERE s.viaje_id = v.id AND s.estado = 'pendiente') ORDER BY fecha ASC");
    } elseif ($isAdmin) {
        $stmt = $pdo->prepare('SELECT ' . VIAJE_COLS . ' FROM viajes WHERE fecha = ?');
        $stmt->execute([$fecha]);
    } else {
        $stmt = $pdo->prepare('SELECT ' . VIAJE_COLS . ' FROM viajes WHERE fecha = ? AND op_num = ?');
        $stmt->execute([$fecha, $me]);
    }
    jout(['success' => true, 'data' => tripsFormat($pdo, $stmt->fetchAll(PDO::FETCH_ASSOC))]);
} catch (Throwable $e) {
    error_log('api_viajes GET: ' . $e->getMessage());
    jout(['success' => false, 'error' => 'Error al obtener los viajes' . ($isAdmin ? ': ' . $e->getMessage() : '')]);
}