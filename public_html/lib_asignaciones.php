<?php
/**
 * lib_asignaciones.php — lectura inteligente de la lista de asignaciones que pega el administrador.
 *
 * Acepta renglones en cualquiera de estos formatos (con tabuladores de Excel o con espacios):
 *   1    24063   ARMANDO CARDENAS   AB2402   6 MAVERICKS      (no. de fila / empleado, tractor, nombre, nodriza, cantidad)
 *   24063   ARMANDO CARDENAS   AB2402   6 MAVERICKS            (tractor, nombre, nodriza, cantidad)
 *   134432   JESUS MANUEL CANTU MONTES   AB2406   6 MAVERICKS (empleado, nombre, nodriza, cantidad)
 *   134432   AB2406   6 MAVERICKS                              (solo empleado)
 *   24063   AB2402   6 MAVERICKS                               (solo tractor: usa al operador que lo trae normalmente)
 * No importa el encabezado, ni el orden de las columnas de número/nombre.
 *
 * Al operador se le identifica, en este orden de prioridad, por:
 *   1) número de empleado  2) tractor habitual  3) nombre (tolera acentos, ñ, nombres truncados y apellido primero)
 * No toca la base de datos: recibe los usuarios ya cargados, así se puede probar aislado.
 */

const ASIG_TIPOS = ['BRONCO', 'MAVERICK'];

function asigNorm($s) {
    $s = (string)$s;
    $s = strtr($s, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','À'=>'A','È'=>'E','Ì'=>'I','Ò'=>'O','Ù'=>'U',
    ]);
    $s = strtoupper($s);
    $s = preg_replace('/[^A-Z0-9 ]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}
function asigCompact($s) { return str_replace(' ', '', asigNorm($s)); }
function asigNameTokens($s) {
    $out = [];
    foreach (explode(' ', preg_replace('/[0-9]+/', ' ', asigNorm($s))) as $t) { if (strlen($t) >= 2) $out[] = $t; }
    return $out;
}

/** 0..1 — qué tanto se parece el nombre escrito en la lista al nombre guardado en Usuarios. */
function asigNameScore($dbName, $inName) {
    $a = asigCompact($dbName); $b = asigCompact($inName);
    if ($a === '' || $b === '' || min(strlen($a), strlen($b)) < 8) return 0.0;
    if ($a === $b) return 1.0;
    $best = 0.0;
    // 1) Mismo orden. La base guarda nombres truncados (25 letras) y a veces sin la Ñ → se compara contra el pedazo equivalente.
    $short = min(strlen($a), strlen($b));
    foreach ([-1, 0, 1] as $d) {
        $n = max(6, $short + $d);
        $x = substr($a, 0, $n); $y = substr($b, 0, $n);
        $lev = levenshtein($x, $y);
        $best = max($best, 1 - $lev / max(strlen($x), strlen($y)));
    }
    // 2) Cualquier orden de palabras (apellido primero, etc.)
    $ta = asigNameTokens($dbName); $tb = asigNameTokens($inName);
    if (count($ta) >= 2 && count($tb) >= 2) {
        $hitA = 0;
        foreach ($ta as $x) { if (asigTokenIn($x, $tb)) $hitA++; }
        $hitB = 0;
        foreach ($tb as $y) { if (asigTokenIn($y, $ta)) $hitB++; }
        if ($hitA >= 2 && $hitB / count($tb) >= 0.6) $best = max($best, min(1.0, $hitA / count($ta)) * 0.97);
    }
    return $best;
}
function asigTokenIn($t, array $list) {
    foreach ($list as $u) {
        if ($t === $u) return true;
        if (strlen($t) >= 3 && strlen($u) >= 3 && (strpos($u, $t) === 0 || strpos($t, $u) === 0)) return true;
        if (strlen($t) >= 5 && strlen($u) >= 5 && levenshtein($t, $u) <= 1) return true;
    }
    return false;
}

/**
 * Descompone un renglón. Devuelve null si está vacío.
 * @param string[] $nodSet códigos de nodriza que existen (para elegir la correcta si hubiera dos)
 */
function asigParseLine($line, array $nodSet = []) {
    $l = trim(str_replace("\xC2\xA0", ' ', (string)$line));
    if ($l === '') return null;
    $r = ['linea' => $l, 'nums' => [], 'nombre' => '', 'nodriza' => '', 'reqText' => '', 'req' => [], 'units' => 0];

    // 1) Cantidad: "6 MAVERICKS", "7 BRONCOS" (lo que sigue, p. ej. "EL MIRAGE", se conserva como texto)
    if (preg_match_all('/(\d{1,3})\s*(BRONCO|MAVERICK)S?(?![A-Za-z])/iu', $l, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        $first = $m[0][0][1];
        $lastM = $m[count($m) - 1][0];
        $lastEnd = $lastM[1] + strlen($lastM[0]);
        $acc = [];
        foreach ($m as $x) {
            $t = strtoupper($x[2][0]); $n = (int)$x[1][0];
            $acc[$t] = ($acc[$t] ?? 0) + $n;
        }
        foreach ($acc as $t => $n) { $r['req'][] = ['tipo' => $t, 'n' => $n]; $r['units'] += $n; }
        if (trim(substr($l, 0, $first)) === '') {
            // La cantidad viene al principio del renglón: lo demás es operador / nodriza
            $r['reqText'] = trim(substr($l, $first, $lastEnd - $first));
            $rest = substr($l, $lastEnd);
        } else {
            $r['reqText'] = trim(substr($l, $first));
            $rest = substr($l, 0, $first);
        }
    } else {
        $rest = $l;
    }

    // 2) Nodriza y números / nombre
    $nods = []; $nameParts = [];
    foreach (preg_split('/[\s,;|]+/u', $rest, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
        $tok = trim($tok, ".:()#\"'");
        if ($tok === '') continue;
        if (preg_match('/^[A-Za-z]{1,3}\d{3,5}$/', $tok)) { $nods[] = strtoupper($tok); continue; }
        if (preg_match('/^\d+$/', $tok)) { $r['nums'][] = $tok; continue; }
        $nameParts[] = $tok;
    }
    foreach ($nods as $c) { if (isset($nodSet[$c])) { $r['nodriza'] = $c; break; } }
    if ($r['nodriza'] === '' && $nods) $r['nodriza'] = $nods[0];
    $r['nombre'] = trim(preg_replace('/\s+/', ' ', implode(' ', $nameParts)));
    return $r;
}

/**
 * Identifica al operador y el tractor de un renglón ya descompuesto.
 * @param array $users filas de usuarios activos con rol operador: id, num_empleado, nombre, tractor
 * @return array resultado con: opNum, opName, tractor, habitual, metodo, estado (ok|revisar|error), avisos[], candidatos[]
 */
function asigResolve(array $p, array $users, array $nodSet) {
    $nums = array_map('strval', $p['nums']);
    $out = [
        'opNum' => null, 'opName' => $p['nombre'], 'tractor' => '', 'habitual' => '', 'metodo' => '',
        'nodriza' => $p['nodriza'], 'reqText' => $p['reqText'], 'req' => $p['req'], 'units' => $p['units'],
        'estado' => 'ok', 'avisos' => [], 'candidatos' => [], 'linea' => $p['linea'], 'crearSugerido' => null,
    ];
    $hasName = strlen(asigCompact($p['nombre'])) >= 8;

    $cands = [];
    foreach ($users as $u) {
        $emp = in_array((string)$u['num_empleado'], $nums, true);
        $tr  = ((string)($u['tractor'] ?? '') !== '') && in_array((string)$u['tractor'], $nums, true);
        $ns  = $hasName ? asigNameScore($u['nombre'], $p['nombre']) : 0.0;
        if (!$emp && !$tr && $ns < 0.86) continue;
        $cands[] = ['u' => $u, 'emp' => $emp, 'tr' => $tr, 'ns' => $ns, 'score' => ($emp ? 10 : 0) + ($tr ? 5 : 0) + $ns * 4];
    }
    usort($cands, function ($a, $b) { return $b['score'] <=> $a['score']; });

    if ($cands) {
        foreach (array_slice($cands, 0, 4) as $c) $out['candidatos'][] = ['num' => $c['u']['num_empleado'], 'nombre' => $c['u']['nombre']];
    } elseif ($hasName) {
        // Sugerencias para cuando no hay coincidencia clara
        $sug = [];
        foreach ($users as $u) { $s = asigNameScore($u['nombre'], $p['nombre']); if ($s >= 0.6) $sug[] = ['num' => $u['num_empleado'], 'nombre' => $u['nombre'], 's' => $s]; }
        usort($sug, function ($a, $b) { return $b['s'] <=> $a['s']; });
        foreach (array_slice($sug, 0, 3) as $c) $out['candidatos'][] = ['num' => $c['num'], 'nombre' => $c['nombre']];
    }

    $best = $cands[0] ?? null;
    if ($best && isset($cands[1]) && ($best['score'] - $cands[1]['score']) < 1.0) {
        // Empate: dos operadores podrían ser (p. ej. comparten tractor y el renglón no trae nombre)
        $out['estado'] = 'error';
        $out['avisos'][] = 'Hay más de un operador posible. Elige el correcto.';
        $best = null;
    }

    $matched = null;
    if ($best) {
        $u = $best['u'];
        $out['opNum'] = (string)$u['num_empleado'];
        $out['habitual'] = (string)($u['tractor'] ?? '');
        $out['metodo'] = $best['emp'] ? 'empleado' : ($best['tr'] ? 'tractor' : 'nombre');
        $matched = $best;
        if ($best['emp'] && $hasName && $best['ns'] < 0.5) {
            $out['estado'] = 'revisar';
            $out['avisos'][] = 'El número ' . $u['num_empleado'] . ' es de ' . $u['nombre'] . ', pero el renglón dice "' . $p['nombre'] . '". Revisa.';
            $out['opName'] = $u['nombre'];
        } elseif (!$hasName || $best['ns'] < 0.5) {
            $out['opName'] = $u['nombre'];
        }
    } elseif ($out['estado'] !== 'error') {
        $out['estado'] = 'error';
        $out['sinOperador'] = true;
    }

    // Tractor: el número grande que no sea el de empleado del operador
    $big = array_values(array_filter($nums, function ($n) { return strlen($n) >= 4; }));
    $tractor = '';
    if ($matched && $matched['tr']) {
        $tractor = (string)$matched['u']['tractor'];
    } else {
        $emp = $matched && $matched['emp'] ? (string)$matched['u']['num_empleado'] : null;
        $others = array_values(array_filter($big, function ($n) use ($emp) { return $n !== $emp; }));
        if ($others) $tractor = $others[count($others) - 1];
    }
    if ($tractor === '' && $out['habitual'] !== '') $tractor = $out['habitual'];
    $out['tractor'] = $tractor;
    if ($tractor !== '' && $out['habitual'] !== '' && $tractor !== $out['habitual']) {
        $out['avisos'][] = 'Cambio de tractor: habitual ' . $out['habitual'] . ', hoy ' . $tractor . '.';
        if ($out['estado'] === 'ok') $out['estado'] = 'revisar';
    }

    // Operador que no existe: se propone darlo de alta (con el número que venga: empleado, o el tractor si es lo único)
    if (!empty($out['sinOperador'])) {
        unset($out['sinOperador']);
        if ($big) {
            $num = $big[0];
            $out['crearSugerido'] = ['num' => $num, 'nombre' => $p['nombre']];
            $out['estado'] = 'revisar';
            $out['avisos'][] = 'Operador nuevo: se dará de alta con el No. ' . $num . ($num === $tractor ? ' (el número de tractor)' : '') . '. Si ya tiene otro número de empleado, cámbialo aquí.';
        } else {
            $out['avisos'][] = 'No se encontró al operador y no trae número de empleado ni de tractor. Elige uno de la lista.';
        }
    }

    // Nodriza (si no está en Inventario se registra sola con 0 bandas) y cantidad
    if ($p['nodriza'] === '') { $out['estado'] = 'error'; $out['avisos'][] = 'No se encontró el código de la nodriza (ej. AB2402).'; }
    if (!$p['units']) { $out['estado'] = 'error'; $out['avisos'][] = 'No se entendió la cantidad (ej. 6 MAVERICKS o 7 BRONCOS).'; }
    return $out;
}

/** Texto completo → renglones resueltos. */
function asigPreview($text, array $users, array $nodSet) {
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
        $p = asigParseLine($line, $nodSet);
        if ($p === null) continue;
        // Encabezados de Excel ("NUMERO DE EMPLEADO  TRACTOR  NOMBRE OP ...") no son datos
        if (!$p['nums'] && !$p['nodriza'] && !$p['units']) continue;
        $rows[] = asigResolve($p, $users, $nodSet);
    }
    return $rows;
}