<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['utente_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

/* -------------------------------------------------
   Gruppi utente (cache in sessione)
------------------------------------------------- */
if (!isset($_SESSION['gruppi'])) {
    $_SESSION['gruppi'] = [];
    $stmt = $mysqli->prepare("
        SELECT g.codice
        FROM gruppi g
        JOIN utenti_gruppi ug ON ug.gruppo_id = g.id
        WHERE ug.utente_id = ?
    ");
    $stmt->bind_param("i", $_SESSION['utente_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $_SESSION['gruppi'][] = $r['codice'];
    }
}

$isRoot = (($_SESSION['privilegi'] ?? '') === 'root');
$gruppi = $_SESSION['gruppi'];

function in_gruppo($codice){
    return in_array($codice, $_SESSION['gruppi'] ?? [], true);
}
?>

<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title><?= APP_NAME ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background-color:#f8f9fa; }
        .navbar-brand { font-weight:600; }
        .nav-section-title {
            font-size:.75rem;
            text-transform:uppercase;
            opacity:.7;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">

        <!-- BRAND -->
        <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard.php">
            <i class="bi bi-building"></i> <?= APP_NAME ?>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">

            <!-- MENU SINISTRA -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- DASHBOARD -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>

                <!-- PRENOTAZIONI -->
                <?php if ($isRoot || in_gruppo('Reception')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar-check"></i> Prenotazioni
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/prenotazioni/lista.php">Elenco prenotazioni</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/prenotazioni/calendario.php">Calendario</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/clienti/clienti.php">Clienti</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- PULIZIE -->
                <?php if ($isRoot || in_gruppo('Pulizia')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/pulizie/pulizie.php">
                        <i class="bi bi-bucket"></i> Pulizie
                    </a>
                </li>
                <?php endif; ?>

                <!-- MANUTENZIONE -->
                <?php if ($isRoot || in_gruppo('Manutenzione')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/manutenzione/ticket.php">
                        <i class="bi bi-tools"></i> Manutenzione
                    </a>
                </li>
                <?php endif; ?>

                <!-- RISTORANTE -->
                <?php if ($isRoot || in_gruppo('Ristorante')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-cup-hot"></i> Ristorante
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/ristorante/tavoli.php">Tavoli</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/ristorante/ordini.php">Ordini</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/ristorante/menu.php">Menu</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- MAGAZZINO -->
                <?php if ($isRoot || in_gruppo('Magazzino')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/magazzino/index.php">
                        <i class="bi bi-box-seam"></i> Magazzino
                    </a>
                </li>
                <?php endif; ?>

                <!-- AMMINISTRAZIONE -->
                <?php if ($isRoot): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-shield-lock"></i> Amministrazione
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/utenti.php">Utenti</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/struttura/struttura.php">Struttura</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/tariffe.php">Tariffe</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/servizi/servizi.php">Servizi</a></li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>

            <!-- MENU DESTRA -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?= ucfirst(h($_SESSION['username'])) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted">
                            <?= h($_SESSION['email'] ?? '') ?>
                        </span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= BASE_URL ?>/profilo.php">
                                <i class="bi bi-person"></i> Profilo
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>

            </ul>

        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
