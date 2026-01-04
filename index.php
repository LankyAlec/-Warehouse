<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['utente_id'])) {
    header("Location: dashboard.php");
    exit;
}

header("Location: auth/login.php");
exit;
