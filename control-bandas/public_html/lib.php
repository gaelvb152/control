<?php
/**
 * lib.php — utilidades compartidas por todos los api_*.php
 * - Sesión + permisos (auth_check.php)
 * - Transacciones con manejo de errores uniforme
 * - Inventario (nodrizas / almacén / oficina) con bloqueo de filas y bitácora de movimientos
 * - Fotos de nodriza
 * - Formato de viajes para el frontend
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

// Respaldo por si el servidor no tiene la extensión mbstring
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $len = null) {
        preg_match_all('/./us', (string)$s, $m);
        return implode('', array_slice($m[0], $start, $len));
    }
}

class AppError extends Exception {
    public $extra = [];
    public function __construct($m, $extra = []) { parent::__construct($m); $this->extra = $extra; }
}
function fail($msg, $extra = []) { throw new AppError($msg, $extra); }

function jout($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function readJson() {
    $b = json_decode(file_get_contents('php://input'), true);
    return is_array($b) ? $b : [];
}
function nowMs() { return (int)round(microtime(true) * 1000); }
/** Ids de texto (varchar 50) para viajes_entregas / viajes_ajustes / viajes_solicitudes: esas tablas NO son autoincrementales. */
function newId($prefix) { return $prefix . str_pad(dechex(nowMs()), 11, '0', STR_PAD_LEFT) . bin2hex(random_bytes(3)); } // la hora va primero: ordenar por id = orden cronológico
/** Fecha de una columna TIMESTAMP/DATETIME como milisegundos (NULL si está vacía o es 0000-00-00). */
function msCol($col) { return 'NULLIF(UNIX_TIMESTAMP(' . $col . '), 0) * 1000'; }
function meNum()  { return (string)($_SESSION['num_empleado'] ?? ''); }
function isAdminSession() { return ($_SESSION['rol'] ?? '') === 'admin'; }

/** Los POST de la app mandan este header; evita que otro sitio dispare acciones con tu sesión. */
function csrfGuard() {
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
        jout(['success' => false, 'message' => 'Solicitud no válida'], 400);
    }
}

/** Ejecuta $fn dentro de una transacción. Cualquier error revierte TODO. */
function tx($pdo, $fn) {
    try {
        $pdo->beginTransaction();
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (AppError $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jout(array_merge(['success' => false, 'message' => $e->getMessage()], $e->extra));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('tx error: ' . $e->getMessage());
        jout(['success' => false, 'message' => isAdminSession() ? 'Error del servidor: ' . $e->getMessage() : 'Error del servidor'], 500);
    }
}

/* ===================== INVENTARIO ===================== */

function movLog($pdo, $tipo, $ubic, $codigo, $antes, $despues, $motivo, $viajeId = null) {
    $dif = ($antes === null) ? (int)$despues : ((int)$despues - (int)$antes);
    $pdo->prepare('INSERT INTO movimientos_inventario (ts, usuario, tipo, ubicacion, codigo, antes, despues, diferencia, motivo, viaje_id) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([nowMs(), meNum() ?: 'sistema', $tipo, $ubic, $codigo, $antes, (int)$despues, $dif, mb_substr((string)$motivo, 0, 250), $viajeId]);
}

function nodLock($pdo, $codigo) {
    $s = $pdo->prepare('SELECT codigo, bandas FROM nodrizas WHERE codigo = ? FOR UPDATE');
    $s->execute([$codigo]);
    $r = $s->fetch();
    if (!$r) fail('La nodriza ' . $codigo . ' no está registrada en Inventario');
    return $r;
}
/** Fija el total de una nodriza y deja registro del cambio. */
function nodSet($pdo, $codigo, $nuevo, $tipo, $motivo, $viajeId = null) {
    $r = nodLock($pdo, $codigo);
    $antes = $r['bandas'] === null ? null : (int)$r['bandas'];
    $nuevo = max(0, (int)$nuevo);
    if ($antes === $nuevo) return $nuevo;
    $pdo->prepare('UPDATE nodrizas SET bandas = ?, actualizado_por = ?, ultima_actualizacion = NOW() WHERE codigo = ?')
        ->execute([$nuevo, meNum() ?: 'sistema', $codigo]);
    movLog($pdo, $tipo, 'NODRIZA', $codigo, $antes, $nuevo, $motivo, $viajeId);
    return $nuevo;
}
/** Suma/resta a una nodriza. No permite quedar en negativo. */
function nodAdd($pdo, $codigo, $delta, $tipo, $motivo, $viajeId = null) {
    $r = nodLock($pdo, $codigo);
    $cur = (int)($r['bandas'] ?? 0);
    if ($cur + $delta < 0) fail('La nodriza ' . $codigo . ' solo tiene ' . $cur . ' bandas');
    return nodSet($pdo, $codigo, $cur + $delta, $tipo, $motivo, $viajeId);
}

function ubicCode($u) {
    $u = strtolower((string)$u);
    if ($u === 'almacen') return 'ALMACEN';
    if ($u === 'oficina') return 'OFICINA';
    fail('Ubicación no válida');
}
function ubicLabel($code) { return $code === 'ALMACEN' ? 'Almacén' : 'Oficina'; }

function stockLock($pdo, $ubic) {
    $s = $pdo->prepare('SELECT cantidad FROM stock_general WHERE ubicacion = ? FOR UPDATE');
    $s->execute([$ubic]);
    $r = $s->fetch();
    if (!$r) {
        $pdo->prepare('INSERT INTO stock_general (ubicacion, cantidad, ultima_actualizacion) VALUES (?, 0, NOW())')->execute([$ubic]);
        return 0;
    }
    return (int)$r['cantidad'];
}
function stockSet($pdo, $ubic, $nuevo, $tipo, $motivo, $viajeId = null) {
    $antes = stockLock($pdo, $ubic);
    $nuevo = max(0, (int)$nuevo);
    if ($antes === $nuevo) return $nuevo;
    $pdo->prepare('UPDATE stock_general SET cantidad = ?, ultima_actualizacion = NOW() WHERE ubicacion = ?')->execute([$nuevo, $ubic]);
    movLog($pdo, $tipo, $ubic, null, $antes, $nuevo, $motivo, $viajeId);
    return $nuevo;
}
function stockAdd($pdo, $ubic, $delta, $tipo, $motivo, $viajeId = null) {
    $cur = stockLock($pdo, $ubic);
    if ($cur + $delta < 0) fail(ubicLabel($ubic) . ' solo tiene ' . $cur . ' bandas');
    return stockSet($pdo, $ubic, $cur + $delta, $tipo, $motivo, $viajeId);
}
function validNod($c) {
    $c = strtoupper(trim((string)$c));
    if (!preg_match('/^[A-Z]{1,3}\d{3,5}$/', $c)) fail('Código de nodriza no válido');
    return $c;
}
/** Quita $n bandas de un origen (almacen | oficina | nodriza | otro). Devuelve la etiqueta del origen. */
function takeFrom($pdo, $origen, $codigo, $n, $tipo, $motivo, $viajeId = null, $excluirNod = null) {
    if ($origen === 'almacen' || $origen === 'oficina') {
        $u = ubicCode($origen);
        stockAdd($pdo, $u, -$n, $tipo, $motivo, $viajeId);
        return ubicLabel($u);
    }
    if ($origen === 'nodriza') {
        $c = validNod($codigo);
        if ($excluirNod !== null && $c === $excluirNod) fail('La nodriza de origen no puede ser la misma del destino');
        nodAdd($pdo, $c, -$n, $tipo, $motivo, $viajeId);
        return 'Nodriza ' . $c;
    }
    if ($origen === 'otro') return 'Otro origen';
    fail('Origen no válido');
}
function putInto($pdo, $destino, $codigo, $n, $tipo, $motivo, $viajeId = null) {
    if ($destino === 'almacen' || $destino === 'oficina') {
        $u = ubicCode($destino);
        stockAdd($pdo, $u, $n, $tipo, $motivo, $viajeId);
        return ubicLabel($u);
    }
    if ($destino === 'nodriza') {
        $c = validNod($codigo);
        nodAdd($pdo, $c, $n, $tipo, $motivo, $viajeId);
        return 'Nodriza ' . $c;
    }
    fail('Destino no válido');
}

/* ===================== NOTIFICACIONES ===================== */
function notif($pdo, $opNum, $viajeId, $texto) {
    $pdo->prepare('INSERT INTO notificaciones (op_num, viaje_id, mensaje, leido, fecha) VALUES (?,?,?,0,NOW())')
        ->execute([(string)$opNum, $viajeId, mb_substr($texto, 0, 490)]);
}

/* ===================== FOTOS ===================== */
function uploadsDir() {
    $d = __DIR__ . '/uploads_nodrizas';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    $ht = $d . '/.htaccess';
    if (!file_exists($ht)) @file_put_contents($ht, "Require all denied\nOptions -Indexes\n");
    return $d;
}
function savePhoto($dataUrl, $viajeId) {
    if (!is_string($dataUrl) || !preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#', $dataUrl)) fail('La foto no es válida');
    $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($raw === false || strlen($raw) < 500 || strlen($raw) > 6 * 1024 * 1024) fail('La foto es inválida o pesa demasiado');
    $info = @getimagesizefromstring($raw);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) fail('El archivo no es una imagen válida');
    if (function_exists('imagecreatefromstring')) {
        // Se re-codifica a JPEG: quita metadatos y cualquier contenido que no sea imagen
        $im = @imagecreatefromstring($raw);
        if (!$im) fail('No se pudo procesar la foto');
        ob_start(); imagejpeg($im, null, 82); $raw = ob_get_clean(); imagedestroy($im);
    } elseif ($info[2] !== IMAGETYPE_JPEG) {
        fail('Formato de foto no soportado');
    }
    $name = substr(sha1($viajeId . '|' . nowMs() . '|' . bin2hex(random_bytes(6))), 0, 28) . '.jpg';
    if (file_put_contents(uploadsDir() . '/' . $name, $raw) === false) fail('No se pudo guardar la foto en el servidor');
    return $name;
}
function deletePhoto($name) {
    if ($name && preg_match('/^[a-f0-9]{28}\.jpg$/', $name)) @unlink(uploadsDir() . '/' . $name);
}

/* ===================== VIAJES ===================== */
const VIAJE_COLS = "id, fecha, op_num, op_nombre, nodriza_codigo, req_texto, requerido, estado, verificado, conteo_sistema, conteo_operador,
    fuente_conteo, total_actual, firmado, total_firmado, NULLIF(UNIX_TIMESTAMP(firma_fecha), 0) * 1000 AS firma_ts, aceptado, aceptado_fecha, foto_nodriza, foto_fecha,
    (firma_img IS NOT NULL AND firma_img <> '') AS has_sig";

function statusOf($v, $hasPending) {
    if (!empty($v['firmado'])) return 'confirmado';
    if ($hasPending) return 'esperando';
    if (!empty($v['verificado'])) return 'listo';
    if (!empty($v['aceptado'])) return 'aceptado';
    return 'asignado';
}

function tripsFormat($pdo, $rows) {
    if (!$rows) return [];
    $ids = array_values(array_unique(array_column($rows, 'id')));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $fetch = function ($sql, $params) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC); };
    $group = function ($list) { $g = []; foreach ($list as $r) { $g[$r['viaje_id']][] = $r; } return $g; };

    $ent  = $group($fetch("SELECT viaje_id, cantidad AS n, origen AS src, nota AS note, " . msCol('fecha') . " AS ts FROM viajes_entregas WHERE viaje_id IN ($in) ORDER BY fecha ASC, id ASC", $ids));
    $adj  = $group($fetch("SELECT viaje_id, diferencia AS delta, cantidad_nueva AS `to`, nota AS note, " . msCol('fecha') . " AS ts FROM viajes_ajustes WHERE viaje_id IN ($in) ORDER BY fecha ASC, id ASC", $ids));
    $sol  = $group($fetch("SELECT viaje_id, id, cantidad AS n, nota AS note, estado AS status, " . msCol('fecha_solicitud') . " AS ts, " . msCol('fecha_cierre') . " AS doneTs FROM viajes_solicitudes WHERE viaje_id IN ($in) ORDER BY fecha_solicitud ASC, id ASC", $ids));
    $req  = $group($fetch("SELECT viaje_id, tipo, cantidad AS n FROM viajes_requisitos WHERE viaje_id IN ($in)", $ids));
    $log  = $group($fetch("SELECT viaje_id, usuario AS who, accion AS text, " . msCol('fecha') . " AS ts FROM viajes_historial WHERE viaje_id IN ($in) ORDER BY fecha ASC, id ASC", $ids));

    $codes = array_values(array_unique(array_column($rows, 'nodriza_codigo')));
    $nodNow = [];
    if ($codes) {
        $inC = implode(',', array_fill(0, count($codes), '?'));
        foreach ($fetch("SELECT codigo, bandas FROM nodrizas WHERE codigo IN ($inC)", $codes) as $r) {
            $nodNow[$r['codigo']] = $r['bandas'] === null ? null : (int)$r['bandas'];
        }
    }

    $out = [];
    foreach ($rows as $v) {
        $id = $v['id'];
        $entregas = array_map(function ($e) { return ['n' => (int)$e['n'], 'src' => $e['src'], 'note' => $e['note'], 'ts' => (int)$e['ts']]; }, isset($ent[$id]) ? $ent[$id] : []);
        $ajustes  = array_map(function ($a) { return ['delta' => (int)$a['delta'], 'to' => (int)$a['to'], 'note' => $a['note'], 'ts' => (int)$a['ts']]; }, isset($adj[$id]) ? $adj[$id] : []);
        $requests = array_map(function ($r) { return ['id' => (string)$r['id'], 'n' => (int)$r['n'], 'note' => $r['note'], 'status' => $r['status'], 'ts' => (int)$r['ts'], 'doneTs' => $r['doneTs'] !== null ? (int)$r['doneTs'] : null]; }, isset($sol[$id]) ? $sol[$id] : []);
        $hasPending = (bool)array_filter($requests, function ($r) { return $r['status'] === 'pendiente'; });
        $verified = !empty($v['verificado']);
        $total = $verified ? ((int)$v['conteo_operador'] + array_sum(array_column($entregas, 'n')) + array_sum(array_column($ajustes, 'delta'))) : null;
        $out[] = [
            'id' => $id, 'fecha' => $v['fecha'], 'opNum' => $v['op_num'], 'opName' => $v['op_nombre'],
            'nodriza' => $v['nodriza_codigo'], 'reqText' => $v['req_texto'], 'required' => (int)$v['requerido'],
            'status' => statusOf($v, $hasPending),
            'accepted' => !empty($v['aceptado']), 'acceptedTs' => !empty($v['aceptado_fecha']) ? (int)$v['aceptado_fecha'] : null,
            'verified' => $verified, 'firmado' => !empty($v['firmado']),
            'sysInv' => $v['conteo_sistema'] !== null ? (int)$v['conteo_sistema'] : null,
            'base' => $v['conteo_operador'] !== null ? (int)$v['conteo_operador'] : null,
            'baseSource' => $v['fuente_conteo'], 'invOk' => $verified ? ($v['fuente_conteo'] === 'sistema') : null,
            'total' => $total, 'totalFirmado' => $v['total_firmado'] !== null ? (int)$v['total_firmado'] : null,
            'firmaTs' => !empty($v['firma_ts']) ? (int)$v['firma_ts'] : null,
            'hasSig' => !empty($v['has_sig']), 'hasPhoto' => !empty($v['foto_nodriza']),
            'photoTs' => !empty($v['foto_fecha']) ? (int)$v['foto_fecha'] : null,
            'nodNow' => $nodNow[$v['nodriza_codigo']] ?? null,
            'entregas' => $entregas, 'ajustes' => $ajustes, 'requests' => $requests,
            'req' => array_map(function ($r) { return ['tipo' => $r['tipo'], 'n' => (int)$r['n']]; }, isset($req[$id]) ? $req[$id] : []),
            'log' => array_map(function ($l) { return ['who' => $l['who'], 'text' => $l['text'], 'ts' => (int)$l['ts']]; }, isset($log[$id]) ? $log[$id] : []),
        ];
    }
    return $out;
}