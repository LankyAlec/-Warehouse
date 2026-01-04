<?php
require_once '../config/db.php';

$token = $_POST['token'] ?? '';
$pass  = $_POST['password'] ?? '';

if ($token==='' || strlen($pass) < 8) {
    header("Location: reset.php?token=" . urlencode($token) . "&err=" . urlencode("Password non valida (min 8)."));
    exit;
}

$stmt = $mysqli->prepare("SELECT id, reset_scadenza FROM utenti WHERE reset_token=? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

if (!$u) die("Token non valido.");
if (empty($u['reset_scadenza']) || strtotime($u['reset_scadenza']) < time()) die("Token scaduto.");

$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare("UPDATE utenti SET password_hash=?, reset_token=NULL, reset_scadenza=NULL WHERE id=?");
$stmt->bind_param("si", $hash, $u['id']);
$stmt->execute();

header("Location: login.php");
exit;
