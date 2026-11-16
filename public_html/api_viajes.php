<?php
/**
 * api_viajes.php — Viajes / asignaciones.
 * Toda la lógica vive aquí (el navegador ya no manda el viaje completo):
 *   Operador:  aceptar · contar · solicitar · cancelar_solicitud · firmar
 *   Admin:     previsualizar · importar_viajes · aprobar_solicitud · rechazar_solicitud · entregar · ajustar_total
 *              cambiar_tractor · eliminar_viaje · eliminar_dia
 *   Administrativo: solo lectura (GET) de todos los viajes, firmas, fotos y comentarios
 */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib_asignaciones.php';
requireLogin();
$isAdmin = isAdminSession();
$isStaff = isStaffSession();
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
/** Operadores activos (para identificar a quién va cada renglón de la lista). */
function asigUsuarios($pdo) {
    return $pdo->query("SELECT id, num_empleado, nombre, tractor FROM usuarios WHERE rol = 'operador' AND estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
}
function asigNodrizas($pdo) {
    $o = [];
    foreach ($pdo->query('SELECT codigo FROM nodrizas') as $r) $o[$r['codigo']] = 1;
    return $o;
}
function asigPerUnit($pdo) {
    $r = $pdo->query("SELECT valor FROM configuracion WHERE llave = 'bandas_por_unidad'")->fetch();
    return $r ? max(1, (int)$r['valor']) : 4;
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

            $coment = mb_substr(trim((string)($in['comentario'] ?? '')), 0, 500);
            $total = recalcViaje($pdo, $id);
            $pdo->prepare("UPDATE viajes SET firmado = 1, firma_fecha = NOW(), total_firmado = ?, firma_img = ?, comentario = ?, estado = 'confirmado' WHERE id = ?")->execute([$total, $img, $coment !== '' ? $coment : null, $id]);
            nodSet($pdo, $v['nodriza_codigo'], $total, 'firma', 'Firma del operador ' . $v['op_num'] . ': la nodriza queda con ' . $total, $id);
            logV($pdo, $id, 'operador', 'Firmó y confirmó el viaje con ' . $total . ' bandas' . ($coment !== '' ? ' · Comentario: ' . $coment : ''));
        });
        jout(['success' => true, 'trip' => tripOut($pdo, $id)]);
    }

    /* ---------- ADMIN ---------- */
    if (in_array($accion, ['previsualizar', 'importar_viajes', 'cambiar_tractor', 'aprobar_solicitud', 'rechazar_solicitud', 'entregar', 'ajustar_total', 'eliminar_viaje', 'eliminar_dia'], true)) {
        requireAdmin();
    }

    if ($accion === 'previsualizar') {
        try {
            jout(['success' => true, 'filas' => asigPreview((string)($in['texto'] ?? ''), asigUsuarios($pdo), asigNodrizas($pdo)), 'perUnit' => asigPerUnit($pdo)]);
        } catch (Throwable $e) {
            jout(['success' => false, 'message' => errDetail($e, 'No se pudo leer la lista')], 500);
        }
    }

    if ($accion === 'importar_viajes') {
        $fecha = (string)($in['fecha'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) jout(['success' => false, 'message' => 'Fecha no válida']);
        $filas = (array)($in['viajes'] ?? []);
        if (!$filas) jout(['success' => false, 'message' => 'No hay asignaciones para cargar']);
        if (count($filas) > 300) jout(['success' => false, 'message' => 'Máximo 300 asignaciones por carga']);
        $perUnit = asigPerUnit($pdo);
        $made = 0; $skipped = 0; $creados = 0; $errores = []; $nodCreadas = [];
        $stmtCheck = $pdo->prepare('SELECT id FROM viajes WHERE id = ?');
        $stmtNod   = $pdo->prepare('SELECT codigo FROM nodrizas WHERE codigo = ?');
        $stmtOp    = $pdo->prepare("SELECT num_empleado, nombre, tractor FROM usuarios WHERE num_empleado = ? AND rol = 'operador' AND estado = 'activo'");
        $insV = $pdo->prepare("INSERT INTO viajes (id, fecha, op_num, op_nombre, tractor, nodriza_codigo, req_texto, requerido, estado, verificado, firmado, aceptado) VALUES (?,?,?,?,?,?,?,?,'asignado',0,0,0)");
        $insR = $pdo->prepare('INSERT INTO viajes_requisitos (viaje_id, tipo, cantidad) VALUES (?,?,?)');
        $insH = $pdo->prepare('INSERT INTO viajes_historial (viaje_id, usuario, accion, fecha) VALUES (?,?,?,NOW())');

        foreach ($filas as $a) {
            $a = is_array($a) ? $a : [];
            $opNum = trim((string)($a['opNum'] ?? '')); $nod = strtoupper(trim((string)($a['nodriza'] ?? '')));
            $tractor = trim((string)($a['tractor'] ?? ''));
            $etiqueta = 'Operador ' . ($opNum !== '' ? $opNum : '(sin elegir)') . ' / Nodriza ' . $nod;
            try {
                if (!preg_match('/^[A-Za-z]{1,3}\d{3,5}$/', $nod)) { $errores[] = $etiqueta . ': código de nodriza no válido'; continue; }
                if ($tractor !== '' && !preg_match('/^[A-Za-z0-9\-]{1,20}$/', $tractor)) { $errores[] = $etiqueta . ': el tractor "' . $tractor . '" no es válido'; continue; }
                // Cantidad: se calcula aquí (no se confía en el navegador)
                $req = []; $units = 0;
                foreach ((array)($a['req'] ?? []) as $r) {
                    $t = strtoupper((string)($r['tipo'] ?? '')); $n = (int)($r['n'] ?? 0);
                    if (!in_array($t, ASIG_TIPOS, true) || $n < 1 || $n > 99) continue;
                    $req[$t] = ($req[$t] ?? 0) + $n; $units += $n;
                }
                if (!$units) { $errores[] = $etiqueta . ': no trae cantidad (ej. 6 MAVERICKS)'; continue; }
                $pdo->beginTransaction();
                // Nodriza que no existe: se registra con 0 bandas (no se sabe cuántas trae). Luego se corrige en Inventario.
                $stmtNod->execute([$nod]);
                if (!$stmtNod->fetch()) {
                    $pdo->prepare('INSERT IGNORE INTO nodrizas (codigo, bandas, actualizado_por, ultima_actualizacion) VALUES (?,0,?,NOW())')->execute([$nod, meNum()]);
                    movLog($pdo, 'alta', 'NODRIZA', $nod, null, 0, 'Alta automática al cargar una asignación (cantidad pendiente de capturar)');
                    $nodCreadas[$nod] = 1;
                }
                // Operador nuevo: se crea con su número de empleado (contraseña inicial = su número; debe cambiarla al entrar)
                $crear = is_array($a['crear'] ?? null) ? $a['crear'] : null;
                if ($crear) {
                    $cn = trim((string)($crear['num'] ?? '')); $cname = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($crear['nombre'] ?? ''))), 0, 150);
                    if ($cname === '') $cname = 'Operador ' . $cn;
                    if (!preg_match('/^[A-Za-z0-9]{3,20}$/', $cn)) { $pdo->rollBack(); $errores[] = $etiqueta . ': el número del operador nuevo no es válido (3 a 20 letras o números)'; continue; }
                    $stmtOp->execute([$cn]);
                    if (!$stmtOp->fetch()) {
                        $pdo->prepare("INSERT INTO usuarios (num_empleado, password_hash, nombre, tractor, rol, debe_cambiar_pwd) VALUES (?,?,?,?, 'operador', 1)")
                            ->execute([$cn, password_hash($cn, PASSWORD_DEFAULT), $cname, $tractor !== '' ? $tractor : null]);
                        $creados++;
                    }
                    $opNum = $cn;
                }
                $viaje_id = $fecha . '_' . $opNum . '_' . $nod;
                $stmtOp->execute([$opNum]);
                $op = $stmtOp->fetch();
                if (!$op) { $pdo->rollBack(); $errores[] = $etiqueta . ': el operador no está registrado (o está inactivo). Elígelo en la lista o créalo.'; continue; }
                $stmtCheck->execute([$viaje_id]);
                if ($stmtCheck->fetch()) { $pdo->rollBack(); $skipped++; continue; }

                if ($tractor === '') $tractor = (string)($op['tractor'] ?? '');
                $opName = mb_substr(trim((string)($a['opName'] ?? '')), 0, 150);
                if ($opName === '') $opName = $op['nombre'];
                $reqText = mb_substr(trim((string)($a['reqText'] ?? '')), 0, 200);
                if ($reqText === '') { $parts = []; foreach ($req as $t => $n) $parts[] = $n . ' ' . $t . 'S'; $reqText = implode(' ', $parts); }
                $insV->execute([$viaje_id, $fecha, $opNum, $opName, $tractor !== '' ? $tractor : null, $nod, $reqText, $units * $perUnit]);
                foreach ($req as $t => $n) { $insR->execute([$viaje_id, $t, $n]); }
                $insH->execute([$viaje_id, 'admin', 'Viaje asignado: ' . $reqText . ($tractor !== '' ? ' · Tractor ' . $tractor : '')]);
                // El tractor habitual se aprende solo la primera vez, o cuando el administrador lo pide
                if ($tractor !== '' && ((string)($op['tractor'] ?? '') === '' || !empty($a['guardarTractor']))) {
                    $pdo->prepare('UPDATE usuarios SET tractor = ? WHERE num_empleado = ?')->execute([$tractor, $opNum]);
                }
                $pdo->commit();
                $made++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errores[] = $etiqueta . ': ' . errDetail($e, 'No se pudo guardar');
            }
        }
        jout(['success' => true, 'made' => $made, 'skipped' => $skipped, 'creados' => $creados, 'nodrizasCreadas' => array_keys($nodCreadas), 'errores' => $errores]);
    }

    if ($accion === 'cambiar_tractor') {
        $tractor = trim((string)($in['tractor'] ?? ''));
        if ($tractor !== '' && !preg_match('/^[A-Za-z0-9\-]{1,20}$/', $tractor)) jout(['success' => false, 'message' => 'Tractor no válido (solo letras, números y guion)']);
        tx($pdo, function () use ($pdo, $id, $in, $tractor) {
            $v = viajeLock($pdo, $id);
            $pdo->prepare('UPDATE viajes SET tractor = ? WHERE id = ?')->execute([$tractor !== '' ? $tractor : null, $id]);
            logV($pdo, $id, 'admin', 'Cambió el tractor de ' . ($v['tractor'] ?: '—') . ' a ' . ($tractor ?: '—'));
            if (!empty($in['habitual']) && $tractor !== '') {
                $pdo->prepare('UPDATE usuarios SET tractor = ? WHERE num_empleado = ?')->execute([$tractor, $v['op_num']]);
            }
        });
        jout(['success' => true, 'trip' => tripOut($pdo, $id)]);
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
        if (!$isStaff) jout(['success' => false, 'error' => 'No autorizado'], 403);
        $stmt = $pdo->query("SELECT " . VIAJE_COLS . " FROM viajes v WHERE EXISTS (SELECT 1 FROM viajes_solicitudes s WHERE s.viaje_id = v.id AND s.estado = 'pendiente') ORDER BY fecha ASC");
    } elseif ($isStaff) {
        $stmt = $pdo->prepare('SELECT ' . VIAJE_COLS . ' FROM viajes WHERE fecha = ?');
        $stmt->execute([$fecha]);
    } else {
        $stmt = $pdo->prepare('SELECT ' . VIAJE_COLS . ' FROM viajes WHERE fecha = ? AND op_num = ?');
        $stmt->execute([$fecha, $me]);
    }
    jout(['success' => true, 'data' => tripsFormat($pdo, $stmt->fetchAll(PDO::FETCH_ASSOC))]);
} catch (Throwable $e) {
    jout(['success' => false, 'error' => errDetail($e, 'Error al obtener los viajes')]);
}