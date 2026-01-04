<?php
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header("Location: servizi.php"); exit; }

/*
  Se in futuro vuoi bloccare l’eliminazione se ci sono prenotazioni servizi,
  basta controllare prenotazioni_servizi WHERE servizio_id = ?
*/

$stmt = $mysqli->prepare("DELETE FROM servizi WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: servizi.php");
exit;
