<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (empty($_SESSION['utente_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Non autorizzato']);
    exit;
}

function json_response(bool $ok, string $message, array $data = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function column_exists(mysqli $db, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = ($res && $res->num_rows > 0);
    $cache[$key] = $exists;
    return $exists;
}

function get_action(): string {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        $_POST = array_merge($_POST, $input);
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    return strtolower((string)$action);
}

function is_range_available(mysqli $db, int $cameraId, string $checkin, string $checkout, ?int $excludeId = null): bool {
    $sql = "
        SELECT COUNT(*) AS tot
        FROM soggiorni
        WHERE camera_id = ?
          AND stato IN ('prenotato','occupato')
          AND NOT (? >= data_checkout OR ? <= data_checkin)
    ";

    if ($excludeId) {
        $sql .= " AND id <> ?";
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }

    if ($excludeId) {
        $stmt->bind_param('issi', $cameraId, $checkin, $checkout, $excludeId);
    } else {
        $stmt->bind_param('iss', $cameraId, $checkin, $checkout);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return ((int)($row['tot'] ?? 0) === 0);
}

function get_camere(mysqli $db): array {
    $res = $db->query("SELECT id, codice, nome, capienza_base FROM camere WHERE attiva = 1 ORDER BY codice ASC");
    if (!$res) {
        return [];
    }

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$r['id'],
            'codice' => (string)$r['codice'],
            'nome' => (string)($r['nome'] ?? ''),
            'capienza' => (int)($r['capienza_base'] ?? 0),
        ];
    }
    return $rows;
}

function list_bookings(mysqli $db): void {
    $selectNote = column_exists($db, 'soggiorni', 'note') ? ', s.note' : '';
    $selectLead = column_exists($db, 'soggiorni', 'referente') ? ', s.referente' : '';
    $selectPasto = column_exists($db, 'soggiorni', 'piano_pasto_sigla') ? ', s.piano_pasto_sigla' : '';
    $selectHb = column_exists($db, 'soggiorni', 'hb_servizio') ? ', s.hb_servizio' : '';

    $sql = "
        SELECT
            s.id,
            s.camera_id,
            s.stato,
            s.data_checkin,
            s.data_checkout
            $selectNote
            $selectLead
            $selectPasto
            $selectHb
        FROM soggiorni s
        ORDER BY s.data_checkin DESC
        LIMIT 200
    ";

    $res = $db->query($sql);
    if (!$res) {
        json_response(false, 'Errore durante il caricamento delle prenotazioni: ' . $db->error, [], 500);
    }

    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $bookings = [];

    foreach ($rows as $row) {
        $bookingId = (int)$row['id'];

        $stmtGuests = $db->prepare("
            SELECT
                CONCAT(COALESCE(c.nome,''), ' ', COALESCE(c.cognome,'')) AS nominativo,
                c.id
            FROM soggiorni_clienti sc
            JOIN clienti c ON c.id = sc.cliente_id
            WHERE sc.soggiorno_id = ?
            ORDER BY sc.id ASC
        ");
        $stmtGuests?->bind_param('i', $bookingId);
        $stmtGuests?->execute();
        $guestsRes = $stmtGuests ? $stmtGuests->get_result() : null;

        $firstGuest = $guestsRes && $guestsRes->num_rows > 0 ? ($guestsRes->fetch_assoc()['nominativo'] ?? '') : '';
        $guestsCount = $guestsRes ? $guestsRes->num_rows : 0;

        $stmtCamera = $db->prepare("SELECT codice, nome FROM camere WHERE id = ?");
        $cameraId = (int)$row['camera_id'];
        $stmtCamera?->bind_param('i', $cameraId);
        $stmtCamera?->execute();
        $cameraRes = $stmtCamera ? $stmtCamera->get_result() : null;
        $camera = $cameraRes ? $cameraRes->fetch_assoc() : null;

        $bookings[] = [
            'id' => $bookingId,
            'camera_id' => (int)$row['camera_id'],
            'camera_label' => $camera ? trim(($camera['codice'] ?? '') . ' ' . ($camera['nome'] ?? '')) : '',
            'stato' => (string)($row['stato'] ?? 'prenotato'),
            'checkin' => (string)$row['data_checkin'],
            'checkout' => (string)$row['data_checkout'],
            'note' => $row['note'] ?? '',
            'referente' => $row['referente'] ?? $firstGuest,
            'pasto' => $row['piano_pasto_sigla'] ?? '',
            'hb' => $row['hb_servizio'] ?? '',
            'ospiti' => $guestsCount,
        ];
    }

    json_response(true, 'OK', ['bookings' => $bookings]);
}

function save_booking(mysqli $db, array $payload): void {
    $id = (int)($payload['id'] ?? 0);

    $cameraId = isset($payload['camera_id']) ? (int)$payload['camera_id'] : null;
    $checkin = $payload['data_checkin'] ?? null;
    $checkout = $payload['data_checkout'] ?? null;
    $stato = $payload['stato'] ?? null;
    $referente = $payload['referente'] ?? null;
    $note = $payload['note'] ?? null;
    $pasto = $payload['piano_pasto_sigla'] ?? null;
    $hb = $payload['hb_servizio'] ?? null;

    if ($checkin && $checkout && $checkin >= $checkout) {
        json_response(false, 'La data di check-out deve essere successiva al check-in', ['toast' => ['variant' => 'warning']]);
    }

    if ($cameraId && $checkin && $checkout && !is_range_available($db, $cameraId, $checkin, $checkout, $id ?: null)) {
        json_response(false, 'La camera non è disponibile per l\'intervallo selezionato', ['conflict' => true, 'toast' => ['variant' => 'danger']]);
    }

    $fields = [];
    $values = [];
    $types = '';

    if ($cameraId !== null) {
        $fields[] = 'camera_id = ?';
        $values[] = $cameraId;
        $types .= 'i';
    }
    if ($stato !== null) {
        $fields[] = 'stato = ?';
        $values[] = $stato;
        $types .= 's';
    }
    if ($checkin !== null) {
        $fields[] = 'data_checkin = ?';
        $values[] = $checkin;
        $types .= 's';
    }
    if ($checkout !== null) {
        $fields[] = 'data_checkout = ?';
        $values[] = $checkout;
        $types .= 's';
    }
    if ($referente !== null && column_exists($db, 'soggiorni', 'referente')) {
        $fields[] = 'referente = ?';
        $values[] = $referente;
        $types .= 's';
    }
    if ($note !== null && column_exists($db, 'soggiorni', 'note')) {
        $fields[] = 'note = ?';
        $values[] = $note;
        $types .= 's';
    }
    if ($pasto !== null && column_exists($db, 'soggiorni', 'piano_pasto_sigla')) {
        $fields[] = 'piano_pasto_sigla = ?';
        $values[] = $pasto;
        $types .= 's';
    }
    if ($hb !== null && column_exists($db, 'soggiorni', 'hb_servizio')) {
        $fields[] = 'hb_servizio = ?';
        $values[] = $hb;
        $types .= 's';
    }

    if (empty($fields)) {
        json_response(false, 'Nessun campo da aggiornare');
    }

    if ($id > 0) {
        $sql = "UPDATE soggiorni SET " . implode(', ', $fields) . " WHERE id = ?";
        $values[] = $id;
        $types .= 'i';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            json_response(false, 'Errore DB: ' . $db->error, [], 500);
        }
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        json_response(true, 'Prenotazione aggiornata', ['toast' => ['variant' => 'success']]);
    }

    if ($cameraId === null || $checkin === null || $checkout === null) {
        json_response(false, 'Camera, check-in e check-out sono obbligatori per creare una prenotazione');
    }

    $columns = ['camera_id', 'data_checkin', 'data_checkout'];
    $placeholders = ['?', '?', '?'];
    $insertTypes = 'iss';
    $insertValues = [$cameraId, $checkin, $checkout];

    if ($stato !== null) {
        $columns[] = 'stato';
        $placeholders[] = '?';
        $insertTypes .= 's';
        $insertValues[] = $stato;
    } else {
        $columns[] = 'stato';
        $placeholders[] = '?';
        $insertTypes .= 's';
        $insertValues[] = 'prenotato';
    }
    if ($referente !== null && column_exists($db, 'soggiorni', 'referente')) {
        $columns[] = 'referente';
        $placeholders[] = '?';
        $insertTypes .= 's';
        $insertValues[] = $referente;
    }
    if ($note !== null && column_exists($db, 'soggiorni', 'note')) {
        $columns[] = 'note';
        $placeholders[] = '?';
        $insertTypes .= 's';
        $insertValues[] = $note;
    }
    if ($pasto !== null && column_exists($db, 'soggiorni', 'piano_pasto_sigla')) {
        $columns[] = 'piano_pasto_sigla';
        $placeholders[] = '?';
        $insertTypes .= 's';
        $insertValues[] = $pasto;
    }
    if ($hb !== null && column_exists($db, 'soggiorni', 'hb_servizio')) {
        $columns[] = 'hb_servizio';
        $placeholders[] = '?';
        $insertTypes .= 's';
        $insertValues[] = $hb;
    }

    $sql = "INSERT INTO soggiorni (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        json_response(false, 'Errore DB: ' . $db->error, [], 500);
    }
    $stmt->bind_param($insertTypes, ...$insertValues);
    $stmt->execute();
    $newId = $stmt->insert_id;

    json_response(true, 'Prenotazione creata', ['id' => $newId, 'toast' => ['variant' => 'success']]);
}

function check_availability(mysqli $db, array $payload): void {
    $cameraId = (int)($payload['camera_id'] ?? 0);
    $checkin = $payload['data_checkin'] ?? null;
    $checkout = $payload['data_checkout'] ?? null;
    $exclude = isset($payload['id']) ? (int)$payload['id'] : null;

    if (!$cameraId || !$checkin || !$checkout) {
        json_response(false, 'Parametri mancanti per la verifica della disponibilità');
    }

    $available = is_range_available($db, $cameraId, $checkin, $checkout, $exclude);
    json_response(true, $available ? 'Camera disponibile' : 'Camera non disponibile', [
        'available' => $available,
        'toast' => ['variant' => $available ? 'success' : 'warning']
    ]);
}

$action = get_action();

switch ($action) {
    case 'list':
        list_bookings($mysqli);
        break;

    case 'metadata':
        json_response(true, 'OK', [
            'camere' => get_camere($mysqli),
            'stati' => ['prenotato', 'occupato', 'annullato', 'checkout'],
        ]);
        break;

    case 'check_availability':
        check_availability($mysqli, $_POST);
        break;

    case 'assign_room':
    case 'save_booking':
        save_booking($mysqli, $_POST);
        break;

    default:
        json_response(false, 'Azione non supportata', [], 400);
}
