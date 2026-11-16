<?php
/**
 * api_usuarios.php — SOLO administrador.
 * POST: listar · crear · masivo · actualizar · reset_password · pedir_cambio_todos
 * Las contraseñas iniciales obligan a cambiarla en el primer acceso (debe_cambiar_pwd = 1).
 */
require_once __DIR__ . '/lib.php';
requireAdmin();
csrfGuard();

$in = readJson();
$accion = $in['accion'] ?? '';
$yo = (int)$_SESSION['usuario_id'];

function validNum($n) { return (bool)preg_match('/^[A-Za-z0-9]{3,20}$/', $n); }
function validTractor($t) { return $t === '' || (bool)preg_match('/^[A-Za-z0-9\-]{1,20}$/', $t); }
function adminsActivos($pdo, $excluirId = 0) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND estado = 'activo' AND id <> ?");
    $s->execute([$excluirId]);
    return (int)$s->fetchColumn();
}

try {
    if ($accion === 'listar') {
        $rows = $pdo->query("SELECT id, num_empleado, nombre, tractor, rol, estado, debe_cambiar_pwd, UNIX_TIMESTAMP(ultimo_acceso) * 1000 AS ult
                             FROM usuarios ORDER BY FIELD(rol,'admin','administrativo','operador'), nombre")->fetchAll();
        $users = array_map(function ($r) {
            return ['id' => (int)$r['id'], 'num' => $r['num_empleado'], 'nombre' => $r['nombre'], 'tractor' => (string)($r['tractor'] ?? ''), 'rol' => $r['rol'],
                    'estado' => $r['estado'], 'debe' => (bool)$r['debe_cambiar_pwd'], 'ultimo' => $r['ult'] !== null ? (int)$r['ult'] : null];
        }, $rows);
        jout(['success' => true, 'usuarios' => $users]);
    }

    if ($accion === 'crear') {
        $num = trim((string)($in['num_empleado'] ?? ''));
        $nom = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in['nombre'] ?? ''))), 0, 150);
        $pwd = (string)($in['password'] ?? '');
        $rol = (string)($in['rol'] ?? 'operador');
        $trac = trim((string)($in['tractor'] ?? ''));
        if (!in_array($rol, ROLES_VALIDOS, true)) jout(['success' => false, 'message' => 'Rol no válido']);
        if (!validNum($num)) jout(['success' => false, 'message' => 'El número de empleado debe tener de 3 a 20 letras o números, sin espacios']);
        if ($nom === '' || $pwd === '') jout(['success' => false, 'message' => 'Faltan datos']);
        if (strlen($pwd) < 6) jout(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
        if (!validTractor($trac)) jout(['success' => false, 'message' => 'Tractor no válido']);
        try {
            $pdo->prepare('INSERT INTO usuarios (num_empleado, password_hash, nombre, tractor, rol, debe_cambiar_pwd) VALUES (?,?,?,?,?,1)')
                ->execute([$num, password_hash($pwd, PASSWORD_DEFAULT), $nom, $trac !== '' ? $trac : null, $rol]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) jout(['success' => false, 'message' => 'El número de empleado ya existe']);
            throw $e;
        }
        jout(['success' => true]);
    }

    if ($accion === 'masivo') {
        $datos = trim((string)($in['datos'] ?? ''));
        if ($datos === '') jout(['success' => false, 'message' => 'No hay datos para procesar']);
        $creados = 0; $omitidos = 0; $detalle = [];
        $stmt = $pdo->prepare("INSERT INTO usuarios (num_empleado, password_hash, nombre, tractor, rol, debe_cambiar_pwd) VALUES (?,?,?,?, 'operador', 1)");
        foreach (preg_split('/\r\n|\r|\n/', $datos) as $linea) {
            $linea = trim($linea);
            if ($linea === '') continue;
            // "134432  JESUS MANUEL CANTU MONTES"  ó  "134432  JESUS MANUEL CANTU MONTES  24063" (tractor al final, opcional)
            if (!preg_match('/^([A-Za-z0-9]{3,20})[\s,;\/\-]+(.+)$/u', $linea, $m)) { $omitidos++; $detalle[] = $linea; continue; }
            $num = $m[1]; $nom = trim($m[2]); $trac = '';
            if (preg_match('/^(.+?)[\s,;]+(\d{4,6})$/u', $nom, $mm)) { $nom = trim($mm[1]); $trac = $mm[2]; }
            try {
                $stmt->execute([$num, password_hash($num, PASSWORD_DEFAULT), mb_substr($nom, 0, 150), $trac !== '' ? $trac : null]);
                $creados++;
            } catch (PDOException $e) { $omitidos++; $detalle[] = $linea; }
        }
        jout(['success' => true, 'creados' => $creados, 'omitidos' => $omitidos, 'omitidosDetalle' => array_slice($detalle, 0, 20)]);
    }

    if ($accion === 'actualizar') {
        $id = (int)($in['id'] ?? 0);
        $s = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?'); $s->execute([$id]); $u = $s->fetch();
        if (!$u) jout(['success' => false, 'message' => 'El usuario no existe']);
        $sets = []; $vals = [];
        if (array_key_exists('nombre', $in)) {
            $nom = mb_substr(trim(preg_replace('/\s+/', ' ', (string)$in['nombre'])), 0, 150);
            if ($nom === '') jout(['success' => false, 'message' => 'El nombre no puede quedar vacío']);
            $sets[] = 'nombre = ?'; $vals[] = $nom;
        }
        if (array_key_exists('tractor', $in)) {
            $t = trim((string)$in['tractor']);
            if (!validTractor($t)) jout(['success' => false, 'message' => 'Tractor no válido (solo letras, números y guion)']);
            $sets[] = 'tractor = ?'; $vals[] = $t !== '' ? $t : null;
        }
        if (array_key_exists('rol', $in) && $in['rol'] !== $u['rol']) {
            if (!in_array($in['rol'], ROLES_VALIDOS, true)) jout(['success' => false, 'message' => 'Rol no válido']);
            if ($id === $yo) jout(['success' => false, 'message' => 'No puedes cambiar tu propio rol']);
            if ($u['rol'] === 'admin' && adminsActivos($pdo, $id) < 1) jout(['success' => false, 'message' => 'Debe quedar al menos un administrador activo']);
            $sets[] = 'rol = ?'; $vals[] = $in['rol'];
        }
        if (array_key_exists('estado', $in) && $in['estado'] !== $u['estado']) {
            if (!in_array($in['estado'], ['activo', 'inactivo'], true)) jout(['success' => false, 'message' => 'Estado no válido']);
            if ($id === $yo) jout(['success' => false, 'message' => 'No puedes desactivar tu propia cuenta']);
            if ($in['estado'] === 'inactivo' && $u['rol'] === 'admin' && adminsActivos($pdo, $id) < 1) jout(['success' => false, 'message' => 'Debe quedar al menos un administrador activo']);
            $sets[] = 'estado = ?'; $vals[] = $in['estado'];
        }
        if (!$sets) jout(['success' => true]);
        $vals[] = $id;
        $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        jout(['success' => true]);
    }

    if ($accion === 'reset_password') {
        $id = (int)($in['id'] ?? 0);
        $pwd = (string)($in['password'] ?? '');
        if (strlen($pwd) < 6) jout(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
        $s = $pdo->prepare('UPDATE usuarios SET password_hash = ?, debe_cambiar_pwd = 1 WHERE id = ?');
        $s->execute([password_hash($pwd, PASSWORD_DEFAULT), $id]);
        jout(['success' => true]);
    }

    if ($accion === 'pedir_cambio_todos') {
        $n = $pdo->exec("UPDATE usuarios SET debe_cambiar_pwd = 1 WHERE rol = 'operador'");
        jout(['success' => true, 'afectados' => (int)$n]);
    }

    jout(['success' => false, 'message' => 'Acción no válida']);
} catch (Throwable $e) {
    jout(['success' => false, 'message' => errDetail($e)], 500);
}
