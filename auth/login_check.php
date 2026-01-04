<?php
require_once '../config/db.php';

$login    = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    header("Location: login.php?error=1");
    exit;
}

$stmt = $mysqli->prepare("
    SELECT id, username, email, password_hash, privilegi, attivo
    FROM utenti
    WHERE username = ? OR email = ?
    LIMIT 1
");
$stmt->bind_param("ss", $login, $login);
$stmt->execute();
$result = $stmt->get_result();

$utente = $result->fetch_assoc();

if (!$utente || !$utente['attivo'] || !password_verify($password, $utente['password_hash'])) {
    header("Location: login.php?error=1");
    exit;
}

$_SESSION['utente_id']  = $utente['id'];
$_SESSION['username']   = $utente['username'];
$_SESSION['email']      = $utente['email'];
$_SESSION['privilegi']  = $utente['privilegi'];

$mysqli->query("UPDATE utenti SET ultimo_login = NOW() WHERE id = {$utente['id']}");

header("Location: ../dashboard.php");
exit;
