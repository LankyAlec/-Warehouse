<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'msg' => 'Bad request']);
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$attivo = (int)($_POST['attivo'] ?? -1);

if ($id <= 0 || ($attivo !== 0 && $attivo !== 1)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'msg' => 'Parametri non validi']);
  exit;
}

$stmt = $mysqli->prepare("UPDATE edifici SET attivo=? WHERE id=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'msg' => 'Errore DB: '.$mysqli->error]);
  exit;
}

$stmt->bind_param("ii", $attivo, $id);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'msg' => 'Errore DB: '.$stmt->error]);
  exit;
}

echo json_encode(['ok' => true]);
