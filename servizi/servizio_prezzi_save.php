<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function go_back(int $servizio_id, string $ok = '', string $err = ''): void {
  if ($ok)  $_SESSION['flash_ok']  = $ok;
  if ($err) $_SESSION['flash_err'] = $err;
  header("Location: servizio_prezzi.php?id=" . (int)$servizio_id);
  exit;
}

function parse_price(string $label, $value): float {
  $v = trim((string)$value);

  if ($v === '') {
    return 0.0;
  }

  // accetta 10 | 10.5 | 10.50 | 0.99
  if (!preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
    throw new Exception("Valore non valido per $label.");
  }

  return (float)$v;
}


function date_or_null($s): ?string {
  $s = trim((string)$s);
  if ($s === '') return null;

  // accetta YYYY-MM-DD (input type date)
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

  // accetta DD/MM/YYYY
  if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
    return $m[3] . '-' . $m[2] . '-' . $m[1];
  }
  return null;
}

$azione = $_POST['azione'] ?? '';
$servizio_id = (int)($_POST['servizio_id'] ?? 0);
$tariffa_id  = (int)($_POST['tariffa_id'] ?? 0);

if ($servizio_id <= 0) {
  go_back(0, '', "Servizio non valido.");
}

/* Carico servizio e forzo tariffe sul genitore */
$stmt = $mysqli->prepare("SELECT id, parent_id FROM servizi WHERE id=? LIMIT 1");
if (!$stmt) go_back($servizio_id, '', "Errore DB: " . $mysqli->error);
$stmt->bind_param("i", $servizio_id);
$stmt->execute();
$srv = $stmt->get_result()->fetch_assoc();
if (!$srv) go_back($servizio_id, '', "Servizio non trovato.");

if (!empty($srv['parent_id'])) {
  go_back((int)$srv['parent_id'], '', "Questo servizio è un componente: le tariffe si gestiscono sul genitore.");
}

/* DELETE */
if ($azione === 'delete') {
  if ($tariffa_id <= 0) go_back($servizio_id, '', "Tariffa non valida.");

  $stmt = $mysqli->prepare("DELETE FROM servizi_tariffe WHERE id=? AND servizio_id=?");
  if (!$stmt) go_back($servizio_id, '', "Errore DB: " . $mysqli->error);
  $stmt->bind_param("ii", $tariffa_id, $servizio_id);
  $stmt->execute();

  go_back($servizio_id, "Tariffa eliminata.");
}

/* INSERT / UPDATE */
if ($azione !== 'insert' && $azione !== 'update') {
  go_back($servizio_id, '', "Azione non valida.");
}

$dal = date_or_null($_POST['dal'] ?? '');
$al  = date_or_null($_POST['al'] ?? ''); // può diventare NULL

if (!$dal) go_back($servizio_id, '', "Data 'Dal' non valida.");
// se AL non valida ma non vuota => errore
if (trim((string)($_POST['al'] ?? '')) !== '' && !$al) go_back($servizio_id, '', "Data 'Al' non valida.");

if ($al !== null && $al < $dal) {
  go_back($servizio_id, '', "La data 'Al' non può essere prima di 'Dal'.");
}

try {
  $prezzo_slot  = parse_price('Prezzo slot',  $_POST['prezzo_slot']  ?? '');
  $prezzo_extra = parse_price('Prezzo extra', $_POST['prezzo_extra'] ?? '');
} catch (Exception $e) {
  go_back($servizio_id, '', $e->getMessage());
}

$note = trim($_POST['note'] ?? '');
$attiva = !empty($_POST['attiva']) ? 1 : 0;

/* ✅ NO SOVRAPPOSIZIONI
   Intervalli [dal, al] con al=NULL = infinito.
   Sovrapposizione se: dal1 <= al2(inf) AND dal2 <= al1(inf)
*/
$endNew = $al ?? '9999-12-31';

$sqlOverlap = "
  SELECT id, dal, al
  FROM servizi_tariffe
  WHERE servizio_id=?
    AND id <> ?
    AND dal <= ?
    AND COALESCE(al, '9999-12-31') >= ?
  LIMIT 1
";
$stmt = $mysqli->prepare($sqlOverlap);
if (!$stmt) go_back($servizio_id, '', "Errore DB: " . $mysqli->error);
$stmt->bind_param("iiss", $servizio_id, $tariffa_id, $endNew, $dal);
$stmt->execute();
$over = $stmt->get_result()->fetch_assoc();
if ($over) {
  $msg = "Periodo sovrapposto a una tariffa esistente (" . $over['dal'] . " → " . ($over['al'] ?: "senza scadenza") . ").";
  go_back($servizio_id, '', $msg);
}

/* Salva */
if ($azione === 'update') {
  if ($tariffa_id <= 0) go_back($servizio_id, '', "Tariffa non valida.");

  $sql = "UPDATE servizi_tariffe
          SET dal=?, al=?, prezzo_slot=?, prezzo_extra=?, note=?, attiva=?
          WHERE id=? AND servizio_id=?";
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) go_back($servizio_id, '', "Errore DB: " . $mysqli->error);

  $stmt->bind_param(
    "ssddsiis",
    $dal,
    $al,
    $prezzo_slot,
    $prezzo_extra,
    $note,
    $attiva,
    $tariffa_id,
    $servizio_id
  );

  if (!$stmt->execute()) go_back($servizio_id, '', "Errore salvataggio: " . $stmt->error);
  go_back($servizio_id, "Tariffa aggiornata ✅");

} else {
  $sql = "INSERT INTO servizi_tariffe (servizio_id, dal, al, prezzo_slot, prezzo_extra, note, attiva)
          VALUES (?,?,?,?,?,?,?)";
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) go_back($servizio_id, '', "Errore DB: " . $mysqli->error);

  $stmt->bind_param(
    "issddsi",
    $servizio_id,
    $dal,
    $al,
    $prezzo_slot,
    $prezzo_extra,
    $note,
    $attiva
  );

  if (!$stmt->execute()) go_back($servizio_id, '', "Errore salvataggio: " . $stmt->error);
  go_back($servizio_id, "Tariffa salvata ✅");
}
