<?php
require_once '../config/db.php';

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: forgot.php?ok=1");
    exit;
}

$token = bin2hex(random_bytes(32));
$scadenza = date('Y-m-d H:i:s', time() + 60*60); // 1 ora

$stmt = $mysqli->prepare("SELECT id, attivo FROM utenti WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

/*
  Non rivelare se l’email esiste o no.
  Se l’utente esiste e attivo, salva token.
*/
if ($u && (int)$u['attivo'] === 1) {
    $stmt = $mysqli->prepare("UPDATE utenti SET reset_token=?, reset_scadenza=? WHERE id=?");
    $stmt->bind_param("ssi", $token, $scadenza, $u['id']);
    $stmt->execute();

    // Link di reset (adatta BASE_URL al tuo percorso)
    $link = "http://" . $_SERVER['HTTP_HOST'] . BASE_URL . "/auth/reset.php?token=" . $token;

    /*
      QUI: invio email.
      In produzione usa PHPMailer.
      Per ora: logga / stampa / salva.
    */
    // error_log("RESET LINK per $email: $link");
}

header("Location: forgot.php?ok=1");
exit;
