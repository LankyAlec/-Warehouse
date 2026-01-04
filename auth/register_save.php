<?php
require_once '../config/db.php';

function back_err($msg){
    header("Location: register.php?err=" . urlencode($msg));
    exit;
}

$nome     = trim($_POST['nome'] ?? '');
$cognome  = trim($_POST['cognome'] ?? '');
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$pass     = $_POST['password'] ?? '';

if ($nome==='' || $cognome==='' || $username==='' || $email==='' || $pass==='') {
    back_err("Compila tutti i campi.");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back_err("Email non valida.");
}
if (strlen($pass) < 8) {
    back_err("La password deve avere almeno 8 caratteri.");
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$token = bin2hex(random_bytes(32)); // 64 char
$scadenza = date('Y-m-d H:i:s', time() + 48*3600); // 48 ore

$stmt = $mysqli->prepare("SELECT id FROM utenti WHERE username=? OR email=? LIMIT 1");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
if ($exists) back_err("Username o email già in uso.");

$stmt = $mysqli->prepare("
    INSERT INTO utenti (username,email,password_hash,nome,cognome,privilegi,attivo,richiesta_registrazione,registrazione_token,registrazione_scadenza)
    VALUES (?,?,?,?,?,'standard',0,1,?,?)
");
$stmt->bind_param("sssssss", $username, $email, $hash, $nome, $cognome, $token, $scadenza);

if (!$stmt->execute()) {
    back_err("Errore salvataggio registrazione.");
}

/*
  Qui puoi inviare una mail all’operatore/root con link di approvazione.
  Per ora, semplice pagina OK.
*/
header("Location: register_ok.php");
exit;
