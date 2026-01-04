<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/struttura_status.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('require_root')) { function require_root(){} }
require_root();

$tipo    = $_POST['tipo'] ?? '';
$id      = (int)($_POST['id'] ?? 0);
$stato   = (int)($_POST['stato'] ?? 0);
$dal     = trim((string)($_POST['start_date'] ?? ''));
$al      = trim((string)($_POST['end_date'] ?? ''));
$cascade = $_POST['cascade'] ?? 'off_only';

if ($id <= 0 || !in_array($stato, [0,1], true)) {
  echo json_encode(['ok'=>false, 'msg'=>'Parametri non validi']); exit;
}

try {
  $newId = struttura_schedule_create($mysqli, $tipo, $id, $stato, $dal, $al !== '' ? $al : null, $cascade);
  $applied = struttura_schedule_apply_due($mysqli);

  echo json_encode([
    'ok' => true,
    'id' => $newId,
    'msg' => 'Schedulazione salvata',
    'applied' => $applied,
  ]); exit;
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]); exit;
}
