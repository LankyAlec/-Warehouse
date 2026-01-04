<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/struttura_status.php';

header('Content-Type: application/json');
if (!function_exists('require_root')) { function require_root(){} }
require_root();

$tipo    = $_POST['tipo'] ?? '';
$id      = (int)($_POST['id'] ?? 0);
$val     = (int)($_POST['val'] ?? 0);
$cascade = $_POST['cascade'] ?? 'off_only';

if(!in_array($tipo,['edificio','piano','camera'], true) || $id<=0 || !in_array($val,[0,1], true)){
  echo json_encode(['ok'=>false,'msg'=>'Parametri non validi']);exit;
}

try {
  struttura_apply_toggle($mysqli, $tipo, $id, $val, $cascade);
  echo json_encode(['ok'=>true]);exit;
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);exit;
}
