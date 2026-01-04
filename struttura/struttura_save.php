<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
if (!function_exists('require_root')) { function require_root(){} }
require_root();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$tipo   = $_POST['tipo']   ?? '';
$azione = $_POST['azione'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$back   = $_POST['back'] ?? 'struttura.php';

if ($azione === 'delete'){
  if ($id <= 0) { header("Location: $back"); exit; }

  // Con FK ON DELETE CASCADE ti basta eliminare il record padre.
  if ($tipo === 'edificio'){
    $stmt = $mysqli->prepare("DELETE FROM edifici WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
  } elseif ($tipo === 'piano'){
    $stmt = $mysqli->prepare("DELETE FROM piani WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
  } elseif ($tipo === 'camera'){
    $stmt = $mysqli->prepare("DELETE FROM camere WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
  }

  header("Location: $back");
  exit;
}

if ($azione !== 'save'){
  $_SESSION['flash_err'] = "Azione non valida.";
  header("Location: $back");
  exit;
}

/* SAVE */
if ($tipo === 'edificio'){
  $nome  = trim($_POST['nome'] ?? '');
  $note  = trim($_POST['note'] ?? '');
  $attivo = !empty($_POST['attivo']) ? 1 : 0;

  if ($nome === ''){
    $_SESSION['flash_err'] = "Nome edificio mancante.";
    header("Location: $back"); exit;
  }

  if ($id > 0){
    $stmt = $mysqli->prepare("UPDATE edifici SET nome=?, note=?, attivo=? WHERE id=?");
    $stmt->bind_param("ssii", $nome, $note, $attivo, $id);
    $stmt->execute();
    header("Location: struttura.php?edificio_id=".$id);
  } else {
    $stmt = $mysqli->prepare("INSERT INTO edifici (nome, note, attivo) VALUES (?,?,?)");
    $stmt->bind_param("ssi", $nome, $note, $attivo);
    $stmt->execute();
    $newId = (int)$mysqli->insert_id;
    header("Location: struttura.php?edificio_id=".$newId);
  }
  exit;
}

if ($tipo === 'piano'){
  $edificio_id = (int)($_POST['edificio_id'] ?? 0);
  $nome  = trim($_POST['nome'] ?? '');
  $livello = (int)($_POST['livello'] ?? 0);
  $note  = trim($_POST['note'] ?? '');
  $attivo = !empty($_POST['attivo']) ? 1 : 0;

  if ($edificio_id <= 0){
    $_SESSION['flash_err'] = "Edificio non valido.";
    header("Location: $back"); exit;
  }
  if ($nome === ''){
    $_SESSION['flash_err'] = "Nome piano mancante.";
    header("Location: $back"); exit;
  }

  if ($id > 0){
    $stmt = $mysqli->prepare("UPDATE piani SET edificio_id=?, nome=?, livello=?, note=?, attivo=? WHERE id=?");
    $stmt->bind_param("isissi", $edificio_id, $nome, $livello, $note, $attivo, $id);
    $stmt->execute();
    header("Location: struttura.php?edificio_id=$edificio_id&piano_id=$id");
  } else {
    $stmt = $mysqli->prepare("INSERT INTO piani (edificio_id, nome, livello, note, attivo) VALUES (?,?,?,?,?)");
    $stmt->bind_param("isisi", $edificio_id, $nome, $livello, $note, $attivo);
    $stmt->execute();
    $newId = (int)$mysqli->insert_id;
    header("Location: struttura.php?edificio_id=$edificio_id&piano_id=$newId");
  }
  exit;
}

if ($tipo === 'camera'){
  $piano_id = (int)($_POST['piano_id'] ?? 0);
  $codice = trim($_POST['codice'] ?? '');
  $nome   = trim($_POST['nome'] ?? '');
  $cap    = (int)($_POST['capienza_base'] ?? 2);
  $note   = trim($_POST['note'] ?? '');
  $attiva = !empty($_POST['attiva']) ? 1 : 0;

  if ($piano_id <= 0){
    $_SESSION['flash_err'] = "Piano non valido.";
    header("Location: $back"); exit;
  }
  if ($codice === ''){
    $_SESSION['flash_err'] = "Codice camera mancante.";
    header("Location: $back"); exit;
  }
  if ($cap < 1) $cap = 1;

  if ($id > 0){
    $stmt = $mysqli->prepare("UPDATE camere SET piano_id=?, codice=?, nome=?, capienza_base=?, note=?, attiva=? WHERE id=?");
    $stmt->bind_param("ississi", $piano_id, $codice, $nome, $cap, $note, $attiva, $id);
    $stmt->execute();
  } else {
    $stmt = $mysqli->prepare("INSERT INTO camere (piano_id, codice, nome, capienza_base, note, attiva) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("issisi", $piano_id, $codice, $nome, $cap, $note, $attiva);
    $stmt->execute();
  }

  header("Location: struttura.php?piano_id=$piano_id");
  exit;
}

$_SESSION['flash_err'] = "Tipo non valido.";
header("Location: $back");
exit;