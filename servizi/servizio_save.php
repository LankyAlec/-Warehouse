<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function flash_back(string $msg, array $old = [], int $id = 0): void {
    $_SESSION['flash_err'] = $msg;
    $_SESSION['flash_old'] = $old;

    $url = "servizio_edit.php" . ($id > 0 ? "?id=$id" : "");
    header("Location: $url");
    exit;
}

$azione = $_POST['azione'] ?? '';

/* =========================
   Toggle attivo (form classico)
========================= */
if ($azione === 'toggle_attivo') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $_SESSION['flash_err'] = "ID non valido.";
        header("Location: servizi.php");
        exit;
    }

    $stmt = $mysqli->prepare("UPDATE servizi SET attivo = IF(attivo=1,0,1) WHERE id=?");
    if (!$stmt) {
        $_SESSION['flash_err'] = "Errore DB: " . $mysqli->error;
        header("Location: servizi.php");
        exit;
    }

    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        $_SESSION['flash_err'] = "Errore DB: " . $stmt->error;
        header("Location: servizi.php");
        exit;
    }

    header("Location: servizi.php");
    exit;
}

/* =========================
   Cancella (manuale: figli + genitore)
========================= */
if ($azione === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) die("ID non valido.");

    $mysqli->begin_transaction();

    try {
        // cancella prima i figli (se id è un figlio, non cancella nulla qui: ok)
        $stmt = $mysqli->prepare("DELETE FROM servizi WHERE parent_id=?");
        if (!$stmt) throw new RuntimeException($mysqli->error);
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error);

        // poi il record richiesto
        $stmt = $mysqli->prepare("DELETE FROM servizi WHERE id=?");
        if (!$stmt) throw new RuntimeException($mysqli->error);
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) throw new RuntimeException($stmt->error);

        $mysqli->commit();
        header("Location: servizi.php");
        exit;

    } catch (Throwable $e) {
        $mysqli->rollback();
        die("Errore eliminazione: " . $e->getMessage());
    }
}

/* =========================
   Salva
========================= */
if ($azione !== 'salva') {
    flash_back("Azione non valida o mancante.", $_POST, (int)($_POST['id'] ?? 0));
}

$id = (int)($_POST['id'] ?? 0);

/* OLD per ripopolare */
$old = [
    'id' => $id,
    'nome' => $_POST['nome'] ?? '',
    'descrizione' => $_POST['descrizione'] ?? '',
    'note' => $_POST['note'] ?? '',
    'max_persone' => $_POST['max_persone'] ?? 1,
    'durata_slot_min' => $_POST['durata_slot_min'] ?? '',
    'step_extra_min' => $_POST['step_extra_min'] ?? '',
    'attivo' => !empty($_POST['attivo']) ? 1 : 0,
    'prenotabile' => !empty($_POST['prenotabile']) ? 1 : 0,
    'slot_illimitato' => !empty($_POST['slot_illimitato']) ? 1 : 0,
    'parent_id' => $_POST['parent_id'] ?? '',
];

$nome        = trim((string)$old['nome']);
$descrizione = trim((string)$old['descrizione']);
$note        = trim((string)$old['note']);

$max_persone = (int)($old['max_persone'] ?? 1);
if ($max_persone < 1) $max_persone = 1;

$slot_illimitato = (int)($old['slot_illimitato'] ?? 0);

$durata_slot_min = ($old['durata_slot_min'] === '' ? null : (int)$old['durata_slot_min']);
$step_extra_min  = ($old['step_extra_min'] === '' ? null : (int)$old['step_extra_min']);

$attivo      = (int)($old['attivo'] ?? 0);
$prenotabile = (int)($old['prenotabile'] ?? 0);

$parent_id_raw = (string)($old['parent_id'] ?? '');
$parent_id = ($parent_id_raw === '' ? null : (int)$parent_id_raw);

/* Validazioni base */
if ($nome === '') flash_back("Nome mancante.", $old, $id);

if ($id > 0 && $parent_id !== null && $parent_id === $id) {
    flash_back("Errore: un servizio non può appartenere a se stesso.", $old, $id);
}

/* Se parent_id valorizzato: deve esistere ed essere un padre */
if ($parent_id !== null) {
    $stmt = $mysqli->prepare("SELECT id FROM servizi WHERE id=? AND parent_id IS NULL LIMIT 1");
    if (!$stmt) flash_back("Errore DB: " . $mysqli->error, $old, $id);

    $stmt->bind_param("i", $parent_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        flash_back("Servizio padre non valido.", $old, $id);
    }

    // regole componente
    $prenotabile = 0;
    $slot_illimitato = 0;
    $durata_slot_min = null;
    $step_extra_min  = null;
}

/* Se tempo illimitato: NULL su durata/extra */
if ($slot_illimitato === 1) {
    $durata_slot_min = null;
    $step_extra_min  = null;
} else {
    if ($durata_slot_min === null || $durata_slot_min < 15) $durata_slot_min = 60;
    if ($step_extra_min  === null || $step_extra_min  < 5)  $step_extra_min  = 30;
}

/* Nome unico */
if ($id > 0) {
    $stmt = $mysqli->prepare("SELECT id FROM servizi WHERE nome=? AND id<>? LIMIT 1");
    if (!$stmt) flash_back("Errore DB: " . $mysqli->error, $old, $id);
    $stmt->bind_param("si", $nome, $id);
} else {
    $stmt = $mysqli->prepare("SELECT id FROM servizi WHERE nome=? LIMIT 1");
    if (!$stmt) flash_back("Errore DB: " . $mysqli->error, $old, $id);
    $stmt->bind_param("s", $nome);
}
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    flash_back("Esiste già un servizio con questo nome.", $old, $id);
}

/* UPDATE / INSERT */
if ($id > 0) {

    $sql = "
        UPDATE servizi
        SET nome=?,
            descrizione=?,
            max_persone=?,
            durata_slot_min=?,
            step_extra_min=?,
            attivo=?,
            prenotabile=?,
            slot_illimitato=?,
            parent_id=?,
            note=?
        WHERE id=?
    ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) flash_back("Errore DB: " . $mysqli->error, $old, $id);

    // ✅ 11 parametri:
    // nome(s), descr(s), max(i), durata(i), step(i), attivo(i), pren(i), illim(i), parent(i), note(s), id(i)
    // => "ssiiiiiiisii"? NO
    // => "ss" + 7*i + "s" + "i" = "ssiiiiiiisi"
    $stmt->bind_param(
        "ssiiiiiiisi",
        $nome,
        $descrizione,
        $max_persone,
        $durata_slot_min,
        $step_extra_min,
        $attivo,
        $prenotabile,
        $slot_illimitato,
        $parent_id,
        $note,
        $id
    );

    if (!$stmt->execute()) flash_back("Errore DB: " . $stmt->error, $old, $id);
    $savedId = $id;

} else {

    $sql = "
        INSERT INTO servizi
          (nome, descrizione, max_persone, durata_slot_min, step_extra_min, attivo, prenotabile, slot_illimitato, parent_id, note)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) flash_back("Errore DB: " . $mysqli->error, $old, 0);

    // ✅ 10 parametri:
    // nome(s), descr(s), max(i), durata(i), step(i), attivo(i), pren(i), illim(i), parent(i), note(s)
    // => "ss" + 7*i + "s" = "ssiiiiiiis"
    $stmt->bind_param(
        "ssiiiiiiis",
        $nome,
        $descrizione,
        $max_persone,
        $durata_slot_min,
        $step_extra_min,
        $attivo,
        $prenotabile,
        $slot_illimitato,
        $parent_id,
        $note
    );

    if (!$stmt->execute()) flash_back("Errore DB: " . $stmt->error, $old, 0);

    $savedId = (int)$mysqli->insert_id;
    if ($savedId <= 0) flash_back("Inserimento eseguito ma ID non ottenuto.", $old, 0);
}

/* Redirect tariffe */
if ($parent_id !== null) {
    header("Location: servizio_prezzi.php?id=" . (int)$parent_id);
} else {
    header("Location: servizio_prezzi.php?id=" . (int)$savedId);
}
exit;
