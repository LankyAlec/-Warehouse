<?php
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$azione = $_POST['azione'] ?? '';

if ($azione === 'toggle_attivo') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { header("Location: utenti.php"); exit; }

    // Non disattivare te stesso per errore (facoltativo ma consigliato)
    if ((int)$_SESSION['utente_id'] === $id) {
        header("Location: utenti.php?tab=tutti");
        exit;
    }

    $stmt = $mysqli->prepare("UPDATE utenti SET attivo = IF(attivo=1,0,1) WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: utenti.php?tab=tutti");
    exit;
}

if ($azione === 'salva_utente') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { die("ID non valido."); }

    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $privilegi = $_POST['privilegi'] ?? 'standard';
    $attivo = !empty($_POST['attivo']) ? 1 : 0;
    $richiesta = !empty($_POST['richiesta_registrazione']) ? 1 : 0;
    $nuova_password = $_POST['nuova_password'] ?? '';
    $gruppi = $_POST['gruppi'] ?? [];
    $permOverride = $_POST['permesso_override'] ?? [];

    if ($username === '' || $email === '') { die("Username/email mancanti."); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { die("Email non valida."); }
    if (!in_array($privilegi, ['guest','standard','root'], true)) $privilegi = 'standard';

    // evita collisioni username/email con altri utenti
    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE (username=? OR email=?) AND id<>? LIMIT 1");
    $stmt->bind_param("ssi", $username, $email, $id);
    $stmt->execute();
    $dup = $stmt->get_result()->fetch_assoc();
    if ($dup) { die("Username o email già usati da un altro utente."); }

    $mysqli->begin_transaction();

    try {
        if ($nuova_password !== '') {
            if (strlen($nuova_password) < 8) throw new Exception("Password troppo corta.");
            $hash = password_hash($nuova_password, PASSWORD_DEFAULT);

            $stmt = $mysqli->prepare("UPDATE utenti
                SET nome=?, cognome=?, username=?, email=?, privilegi=?, attivo=?, richiesta_registrazione=?,
                    password_hash=?
                WHERE id=?");
            $stmt->bind_param("sssssissi", $nome, $cognome, $username, $email, $privilegi, $attivo, $richiesta, $hash, $id);
        } else {
            $stmt = $mysqli->prepare("UPDATE utenti
                SET nome=?, cognome=?, username=?, email=?, privilegi=?, attivo=?, richiesta_registrazione=?
                WHERE id=?");
            $stmt->bind_param("sssssiii", $nome, $cognome, $username, $email, $privilegi, $attivo, $richiesta, $id);
        }
        $stmt->execute();

        // Gruppi: reset e reinsert
        $stmt = $mysqli->prepare("DELETE FROM utenti_gruppi WHERE utente_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if (is_array($gruppi) && !empty($gruppi)) {
            $stmt = $mysqli->prepare("INSERT INTO utenti_gruppi (utente_id, gruppo_id) VALUES (?, ?)");
            foreach ($gruppi as $gid) {
                $gid = (int)$gid;
                if ($gid > 0) {
                    $stmt->bind_param("ii", $id, $gid);
                    $stmt->execute();
                }
            }
        }

        // Permessi override: pulisci e reinsert solo quelli valorizzati
        $stmt = $mysqli->prepare("DELETE FROM permessi_utenti WHERE utente_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if (is_array($permOverride)) {
            $stmt = $mysqli->prepare("INSERT INTO permessi_utenti (utente_id, permesso_id, valore) VALUES (?, ?, ?)");
            foreach ($permOverride as $permId => $val) {
                $permId = (int)$permId;
                $val = (string)$val;
                if ($permId > 0 && in_array($val, ['consenti','nega'], true)) {
                    $stmt->bind_param("iis", $id, $permId, $val);
                    $stmt->execute();
                }
            }
        }

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        die("Errore salvataggio: " . $e->getMessage());
    }

    header("Location: utente_edit.php?id=" . $id);
    exit;
}

header("Location: utenti.php");
exit;
