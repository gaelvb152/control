<?php
/**
 * foto.php?id=<viaje>&t=foto|firma
 * Entrega la foto de la nodriza o la firma SOLO a: el administrador, o el operador dueño del viaje.
 */
require_once __DIR__ . '/lib.php';
requireLogin();

$id = (string)($_GET['id'] ?? '');
$t = ($_GET['t'] ?? 'foto') === 'firma' ? 'firma' : 'foto';

$s = $pdo->prepare('SELECT op_num, foto_nodriza, firma_img FROM viajes WHERE id = ?');
$s->execute([$id]);
$v = $s->fetch();
if (!$v || (!isAdminSession() && (string)$v['op_num'] !== meNum())) { http_response_code(404); header('Content-Type: text/plain'); echo 'No encontrado'; exit; }

header_remove('Content-Type');
header('Cache-Control: private, max-age=86400');
if ($t === 'firma') {
    $img = (string)$v['firma_img'];
    if (strpos($img, 'data:image/png;base64,') !== 0) { http_response_code(404); header('Content-Type: text/plain'); echo 'Sin firma'; exit; }
    header('Content-Type: image/png');
    echo base64_decode(substr($img, 22));
    exit;
}
$name = (string)$v['foto_nodriza'];
$path = uploadsDir() . '/' . basename($name);
if (!preg_match('/^[a-f0-9]{28}\.jpg$/', $name) || !is_file($path)) { http_response_code(404); header('Content-Type: text/plain'); echo 'Sin foto'; exit; }
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
readfile($path);
