<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

/**
 * NON usare require_root() qui perché spesso fa redirect/HTML.
 * Qui dobbiamo sempre rispondere JSON.
 */
if (!isset($_SESSION)) { session_start(); }

if (($_SESSION['privilegi'] ?? '') !== 'root') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'msg' => 'Permessi insufficienti (solo root).']);
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$attivo = isset($_POST['attivo']) ? (int)$_POST['attivo'] : -1;

if ($id <= 0 || ($attivo !== 0 && $attivo !== 1)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'msg' => 'Parametri non validi.']);
  exit;
}

$stmt = $mysqli->prepare("UPDATE servizi SET attivo=? WHERE id=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'msg' => 'Prepare failed: '.$mysqli->error]);
  exit;
}

$stmt->bind_param("ii", $attivo, $id);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'msg' => 'Execute failed: '.$stmt->error]);
  exit;
}

echo json_encode(['ok' => true, 'id' => $id, 'attivo' => $attivo]);
