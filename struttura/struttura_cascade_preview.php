<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if (!function_exists('require_root')) { function require_root(){} }
require_root();

$tipo    = $_POST['tipo'] ?? '';
$id      = (int)($_POST['id'] ?? 0);
$val     = (int)($_POST['val'] ?? 0);            // 0/1
$cascade = $_POST['cascade'] ?? 'off_only';      // off_only | always

if (!in_array($tipo, ['edificio','piano','camera'], true) || $id <= 0 || !in_array($val,[0,1], true)) {
  echo json_encode(['ok'=>false,'msg'=>'Parametri non validi']); exit;
}

// cascata solo se:
// - always (sia ON che OFF)
// - off_only e val=0 (solo spegnimento)
$doCascade = ($cascade === 'always') || ($cascade === 'off_only' && $val === 0);

$out = [
  'ok' => true,
  'doCascade' => $doCascade,
  'tipo' => $tipo,
  'id' => $id,
  'val' => $val,
  'counts' => ['piani'=>0,'camere'=>0],
];

try {
  if (!$doCascade) {
    echo json_encode($out); exit;
  }

  if ($tipo === 'edificio') {

    // piani dell'edificio
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM piani WHERE edificio_id=?");
    if(!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $out['counts']['piani'] = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);

    // camere dell'edificio (camere -> attiva)
    $stmt = $mysqli->prepare("
      SELECT COUNT(*) AS n
      FROM camere c
      JOIN piani p ON p.id = c.piano_id
      WHERE p.edificio_id = ?
    ");
    if(!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $out['counts']['camere'] = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);

  } elseif ($tipo === 'piano') {

    // camere del piano
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM camere WHERE piano_id=?");
    if(!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $out['counts']['camere'] = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);

  } else {
    // camera: cascata non ha senso (lasciamo counts a 0)
  }

  echo json_encode($out); exit;

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); exit;
}