<?php
/**
 * api_estadisticas.php — administrador y administrativo (solo lectura).
 *   GET ?tipo=panel&desde=YYYY-MM-DD&hasta=YYYY-MM-DD
 *       → KPIs, ranking por operador, ranking por nodriza y resumen por semana (lunes a domingo)
 *   GET ?tipo=calendario&mes=YYYY-MM
 *       → resumen por día del mes (viajes, firmados, solicitudes, entregadas, comentarios)
 *   GET ?tipo=comentarios&fecha=YYYY-MM-DD
 *       → todas las notas del día (operador y administración)
 * "Pidió bandas" = solicitudes del operador que no fueron canceladas.
 * "Se le pusieron" = bandas entregadas a la nodriza durante el viaje (aprobando solicitudes o directo).
 */
require_once __DIR__ . '/lib.php';
requireStaff();

function fechaOk($f) { return is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f) !== false; }

/**
 * Agrupa viajes / solicitudes / entregas por una expresión SQL fija (nunca viene del usuario).
 * Se hace en consultas separadas para que un join no multiplique las filas.
 */
function agrupar($pdo, $keyExpr, $desde, $hasta) {
    $out = [];
    $q = function ($sql) use ($pdo, $desde, $hasta) { $s = $pdo->prepare($sql); $s->execute([$desde, $hasta]); return $s->fetchAll(); };
    foreach ($q("SELECT $keyExpr AS k, COUNT(*) AS viajes, SUM(v.firmado = 1) AS firmados,
                        SUM(v.verificado = 1 AND (v.conteo_sistema IS NULL OR v.conteo_sistema <> v.conteo_operador)) AS difs,
                        MAX(v.fecha) AS ultima
                 FROM viajes v WHERE v.fecha BETWEEN ? AND ? GROUP BY k") as $r) {
        $out[$r['k']] = ['viajes' => (int)$r['viajes'], 'firmados' => (int)$r['firmados'], 'difs' => (int)$r['difs'], 'ultimoViaje' => $r['ultima'],
                         'solicitudes' => 0, 'bandasPedidas' => 0, 'viajesConSolicitud' => 0, 'bandasPuestas' => 0, 'ultimaSolicitud' => null];
    }
    foreach ($q("SELECT $keyExpr AS k, COUNT(*) AS n, COALESCE(SUM(s.cantidad),0) AS bandas, COUNT(DISTINCT v.id) AS viajes, MAX(v.fecha) AS ultima
                 FROM viajes_solicitudes s JOIN viajes v ON v.id = s.viaje_id
                 WHERE v.fecha BETWEEN ? AND ? AND s.estado <> 'cancelada' GROUP BY k") as $r) {
        if (!isset($out[$r['k']])) continue;
        $out[$r['k']]['solicitudes'] = (int)$r['n']; $out[$r['k']]['bandasPedidas'] = (int)$r['bandas'];
        $out[$r['k']]['viajesConSolicitud'] = (int)$r['viajes']; $out[$r['k']]['ultimaSolicitud'] = $r['ultima'];
    }
    foreach ($q("SELECT $keyExpr AS k, COALESCE(SUM(e.cantidad),0) AS bandas
                 FROM viajes_entregas e JOIN viajes v ON v.id = e.viaje_id
                 WHERE v.fecha BETWEEN ? AND ? GROUP BY k") as $r) {
        if (isset($out[$r['k']])) $out[$r['k']]['bandasPuestas'] = (int)$r['bandas'];
    }
    return $out;
}

try {
    $tipo = (string)($_GET['tipo'] ?? 'panel');

    if ($tipo === 'panel') {
        $hasta = fechaOk($_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');
        $desde = fechaOk($_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-d', strtotime($hasta . ' -55 days'));
        if ($desde > $hasta) { $t = $desde; $desde = $hasta; $hasta = $t; }
        if (strtotime($hasta) - strtotime($desde) > 400 * 86400) jout(['success' => false, 'error' => 'El periodo máximo es de 400 días']);

        $ops = agrupar($pdo, 'v.op_num', $desde, $hasta);
        $nombres = []; $tractores = [];
        foreach ($pdo->query('SELECT num_empleado, nombre, tractor FROM usuarios') as $r) { $nombres[$r['num_empleado']] = $r['nombre']; $tractores[$r['num_empleado']] = (string)($r['tractor'] ?? ''); }
        $operadores = [];
        foreach ($ops as $k => $o) { $operadores[] = array_merge($o, ['opNum' => (string)$k, 'opName' => $nombres[$k] ?? (string)$k, 'tractor' => $tractores[$k] ?? '']); }
        usort($operadores, function ($a, $b) { return [$b['bandasPedidas'], $b['solicitudes'], $b['viajes']] <=> [$a['bandasPedidas'], $a['solicitudes'], $a['viajes']]; });

        $nods = agrupar($pdo, 'v.nodriza_codigo', $desde, $hasta);
        $actual = [];
        foreach ($pdo->query('SELECT codigo, bandas FROM nodrizas') as $r) $actual[$r['codigo']] = $r['bandas'] === null ? null : (int)$r['bandas'];
        $nodrizas = [];
        foreach ($nods as $k => $o) { $nodrizas[] = array_merge($o, ['codigo' => (string)$k, 'bandasAhora' => $actual[$k] ?? null]); }
        usort($nodrizas, function ($a, $b) { return [$b['bandasPuestas'], $b['bandasPedidas'], $b['viajes']] <=> [$a['bandasPuestas'], $a['bandasPedidas'], $a['viajes']]; });

        $sem = agrupar($pdo, 'DATE_SUB(v.fecha, INTERVAL WEEKDAY(v.fecha) DAY)', $desde, $hasta);
        ksort($sem);
        $semanas = [];
        foreach ($sem as $k => $o) { $semanas[] = array_merge($o, ['inicio' => (string)$k, 'fin' => date('Y-m-d', strtotime($k . ' +6 days'))]); }

        $dd = agrupar($pdo, 'v.fecha', $desde, $hasta);
        ksort($dd);
        $dias = [];
        foreach ($dd as $k => $o) { $dias[] = array_merge($o, ['fecha' => (string)$k]); }

        $k = ['viajes' => 0, 'firmados' => 0, 'difs' => 0, 'solicitudes' => 0, 'bandasPedidas' => 0, 'bandasPuestas' => 0, 'viajesConSolicitud' => 0];
        foreach ($operadores as $o) foreach ($k as $f => $_) $k[$f] += $o[$f];
        $k['operadores'] = count($operadores); $k['nodrizas'] = count($nodrizas);

        jout(['success' => true, 'desde' => $desde, 'hasta' => $hasta, 'kpis' => $k, 'operadores' => $operadores, 'nodrizas' => $nodrizas, 'semanas' => $semanas, 'dias' => $dias]);
    }

    if ($tipo === 'detalle') {
        $hasta = fechaOk($_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');
        $desde = fechaOk($_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-d', strtotime($hasta . ' -55 days'));
        $por = (string)($_GET['por'] ?? '');
        $valor = trim((string)($_GET['valor'] ?? ''));
        if ($valor === '' || !in_array($por, ['op', 'nod'], true)) jout(['success' => false, 'error' => 'Datos incompletos']);
        $col = $por === 'op' ? 'v.op_num' : 'v.nodriza_codigo';
        $s = $pdo->prepare("SELECT v.id AS viaje_id, v.fecha, v.op_num, v.op_nombre, v.tractor, v.nodriza_codigo AS nodriza, v.firmado,
                                   s.cantidad, s.estado, s.nota, " . msCol('s.fecha_solicitud') . " AS ts,
                                   (SELECT COALESCE(SUM(e.cantidad),0) FROM viajes_entregas e WHERE e.viaje_id = v.id) AS puestas
                            FROM viajes_solicitudes s JOIN viajes v ON v.id = s.viaje_id
                            WHERE $col = ? AND v.fecha BETWEEN ? AND ? AND s.estado <> 'cancelada'
                            ORDER BY v.fecha DESC, s.fecha_solicitud DESC LIMIT 500");
        $s->execute([$valor, $desde, $hasta]);
        $rows = array_map(function ($r) {
            return ['tripId' => $r['viaje_id'], 'fecha' => $r['fecha'], 'opNum' => $r['op_num'], 'opName' => $r['op_nombre'], 'tractor' => (string)($r['tractor'] ?? ''),
                    'nodriza' => $r['nodriza'], 'firmado' => (int)$r['firmado'] === 1, 'cantidad' => (int)$r['cantidad'], 'estado' => $r['estado'], 'nota' => (string)($r['nota'] ?? ''),
                    'ts' => $r['ts'] === null ? null : (int)$r['ts'], 'puestas' => (int)$r['puestas']];
        }, $s->fetchAll());
        jout(['success' => true, 'solicitudes' => $rows]);
    }

    if ($tipo === 'calendario') {
        $mes = (string)($_GET['mes'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $mes) || !strtotime($mes . '-01')) $mes = date('Y-m');
        $ini = $mes . '-01'; $fin = date('Y-m-t', strtotime($ini));
        $dias = [];
        $q = function ($sql) use ($pdo, $ini, $fin) { $s = $pdo->prepare($sql); $s->execute([$ini, $fin]); return $s->fetchAll(); };
        $blank = ['viajes' => 0, 'firmados' => 0, 'pendientes' => 0, 'solicitudes' => 0, 'bandasPedidas' => 0, 'bandasPuestas' => 0, 'comentarios' => 0, 'difs' => 0];
        foreach ($q("SELECT v.fecha AS d, COUNT(*) AS viajes, SUM(v.firmado = 1) AS firmados,
                            SUM(v.verificado = 1 AND (v.conteo_sistema IS NULL OR v.conteo_sistema <> v.conteo_operador)) AS difs,
                            SUM(v.comentario IS NOT NULL AND v.comentario <> '') AS coment
                     FROM viajes v WHERE v.fecha BETWEEN ? AND ? GROUP BY v.fecha") as $r) {
            $dias[$r['d']] = array_merge($blank, ['viajes' => (int)$r['viajes'], 'firmados' => (int)$r['firmados'], 'difs' => (int)$r['difs'], 'comentarios' => (int)$r['coment']]);
        }
        foreach ($q("SELECT v.fecha AS d, COUNT(*) AS n, COALESCE(SUM(s.cantidad),0) AS bandas, SUM(s.estado = 'pendiente') AS pend,
                            SUM(s.nota IS NOT NULL AND s.nota <> '') AS coment
                     FROM viajes_solicitudes s JOIN viajes v ON v.id = s.viaje_id
                     WHERE v.fecha BETWEEN ? AND ? AND s.estado <> 'cancelada' GROUP BY v.fecha") as $r) {
            if (!isset($dias[$r['d']])) continue;
            $dias[$r['d']]['solicitudes'] = (int)$r['n']; $dias[$r['d']]['bandasPedidas'] = (int)$r['bandas'];
            $dias[$r['d']]['pendientes'] = (int)$r['pend']; $dias[$r['d']]['comentarios'] += (int)$r['coment'];
        }
        foreach ($q("SELECT v.fecha AS d, COALESCE(SUM(e.cantidad),0) AS bandas
                     FROM viajes_entregas e JOIN viajes v ON v.id = e.viaje_id WHERE v.fecha BETWEEN ? AND ? GROUP BY v.fecha") as $r) {
            if (isset($dias[$r['d']])) $dias[$r['d']]['bandasPuestas'] = (int)$r['bandas'];
        }
        jout(['success' => true, 'mes' => $mes, 'dias' => (object)$dias]);
    }

    if ($tipo === 'comentarios') {
        $f = fechaOk($_GET['fecha'] ?? '') ? $_GET['fecha'] : date('Y-m-d');
        $rows = [];
        $add = function ($sql) use ($pdo, $f, &$rows) { $s = $pdo->prepare($sql); $s->execute([$f]); foreach ($s->fetchAll() as $r) $rows[] = $r; };
        $cols = "v.id AS viaje_id, v.op_num, v.op_nombre, v.nodriza_codigo AS nodriza";
        $add("SELECT $cols, 'operador' AS de, 'Comentario al firmar' AS tipo, v.comentario AS texto, " . msCol('v.firma_fecha') . " AS ts
              FROM viajes v WHERE v.fecha = ? AND v.comentario IS NOT NULL AND v.comentario <> ''");
        $add("SELECT $cols, 'operador' AS de, CONCAT('Solicitó ', s.cantidad, ' bandas') AS tipo, s.nota AS texto, " . msCol('s.fecha_solicitud') . " AS ts
              FROM viajes_solicitudes s JOIN viajes v ON v.id = s.viaje_id WHERE v.fecha = ? AND s.nota IS NOT NULL AND s.nota <> ''");
        $add("SELECT $cols, 'administración' AS de, CONCAT('Entregó ', e.cantidad, ' bandas (', e.origen, ')') AS tipo, e.nota AS texto, " . msCol('e.fecha') . " AS ts
              FROM viajes_entregas e JOIN viajes v ON v.id = e.viaje_id WHERE v.fecha = ? AND e.nota IS NOT NULL AND e.nota <> ''");
        $add("SELECT $cols, 'administración' AS de, CONCAT('Ajustó el total a ', a.cantidad_nueva) AS tipo, a.nota AS texto, " . msCol('a.fecha') . " AS ts
              FROM viajes_ajustes a JOIN viajes v ON v.id = a.viaje_id WHERE v.fecha = ? AND a.nota IS NOT NULL AND a.nota <> ''");
        usort($rows, function ($a, $b) { return (int)$a['ts'] <=> (int)$b['ts']; });
        $rows = array_map(function ($r) {
            return ['tripId' => $r['viaje_id'], 'opNum' => $r['op_num'], 'opName' => $r['op_nombre'], 'nodriza' => $r['nodriza'], 'de' => $r['de'], 'tipo' => $r['tipo'], 'texto' => $r['texto'], 'ts' => $r['ts'] === null ? null : (int)$r['ts']];
        }, $rows);
        jout(['success' => true, 'fecha' => $f, 'comentarios' => $rows]);
    }

    jout(['success' => false, 'error' => 'Tipo no válido']);
} catch (Throwable $e) {
    jout(['success' => false, 'error' => errDetail($e, 'Error al calcular las estadísticas')]);
}