<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (empty($_SESSION['utente_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

$oggi = date('Y-m-d');
$edificio_id = (int)($_GET['edificio_id'] ?? 0);
$piano_id = (int)($_GET['piano_id'] ?? 0);

function table_exists(mysqli $db, string $table): bool
{
    $tableEsc = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$tableEsc}'");
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $db, string $table, string $column): bool
{
    $tableEsc = $db->real_escape_string($table);
    $colEsc = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'");
    return $res && $res->num_rows > 0;
}

function bind_params(mysqli_stmt $stmt, string $types, array $params): void
{
    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    $stmt->bind_param($types, ...$refs);
}

$layoutCols = column_exists($mysqli, 'camere', 'layout_col');
$layoutRows = column_exists($mysqli, 'camere', 'layout_row');
$posX = column_exists($mysqli, 'camere', 'pos_x');
$posY = column_exists($mysqli, 'camere', 'pos_y');
$posW = column_exists($mysqli, 'camere', 'pos_w');
$posH = column_exists($mysqli, 'camere', 'pos_h');

$edificioInfo = null;
$pianoInfo = null;

if ($piano_id > 0) {
    $stmtInfo = $mysqli->prepare("
        SELECT p.id, p.nome, p.livello, e.id AS edificio_id, e.nome AS edificio_nome
        FROM piani p
        JOIN edifici e ON e.id = p.edificio_id
        WHERE p.id = ?
        LIMIT 1
    ");
    if ($stmtInfo) {
        $stmtInfo->bind_param("i", $piano_id);
        $stmtInfo->execute();
        $pianoInfo = $stmtInfo->get_result()->fetch_assoc() ?: null;
        if ($pianoInfo) {
            $edificioInfo = [
                'id' => (int)$pianoInfo['edificio_id'],
                'nome' => (string)$pianoInfo['edificio_nome'],
            ];
        }
    }
} elseif ($edificio_id > 0) {
    $stmtInfo = $mysqli->prepare("SELECT id, nome FROM edifici WHERE id=? LIMIT 1");
    if ($stmtInfo) {
        $stmtInfo->bind_param("i", $edificio_id);
        $stmtInfo->execute();
        $edificioInfo = $stmtInfo->get_result()->fetch_assoc() ?: null;
    }
}

$extra = [];
if ($layoutRows) $extra[] = "c.layout_row";
if ($layoutCols) $extra[] = "c.layout_col";
if ($posX) $extra[] = "c.pos_x";
if ($posY) $extra[] = "c.pos_y";
if ($posW) $extra[] = "c.pos_w";
if ($posH) $extra[] = "c.pos_h";

$sql = "
    SELECT
        c.id, c.codice, c.nome, c.capienza_base, c.note, c.attiva,
        p.id AS piano_id, p.nome AS piano_nome, p.livello,
        e.id AS edificio_id, e.nome AS edificio_nome
        " . ($extra ? ", " . implode(',', $extra) : "") . "
    FROM camere c
    JOIN piani p ON p.id = c.piano_id
    JOIN edifici e ON e.id = p.edificio_id
    WHERE c.attiva = 1
";

$bind = [];
$types = '';

if ($piano_id > 0) {
    $sql .= " AND c.piano_id = ?";
    $bind[] = $piano_id;
    $types .= 'i';
} elseif ($edificio_id > 0) {
    $sql .= " AND p.edificio_id = ?";
    $bind[] = $edificio_id;
    $types .= 'i';
}

$sql .= " ORDER BY p.livello ASC, c.codice ASC, c.nome ASC";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Errore DB: ' . $mysqli->error]);
    exit;
}

if ($bind) {
    bind_params($stmt, $types, $bind);
}

$stmt->execute();
$res = $stmt->get_result();
$roomsRaw = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$roomIds = array_map('intval', array_column($roomsRaw, 'id'));

$occupazioni = [];
$pulizie = [];
$manutenzioni = [];

if ($roomIds) {
    $ph = implode(',', array_fill(0, count($roomIds), '?'));
    $commonTypes = str_repeat('i', count($roomIds));

    $hasGuests = table_exists($mysqli, 'soggiorni_clienti') && table_exists($mysqli, 'clienti');
    $guestField = $hasGuests
        ? ", GROUP_CONCAT(DISTINCT TRIM(CONCAT(COALESCE(cl.nome,''),' ',COALESCE(cl.cognome,''))) SEPARATOR ', ') AS ospiti"
        : ", NULL AS ospiti";
    $guestJoin = $hasGuests
        ? "LEFT JOIN soggiorni_clienti sc ON sc.soggiorno_id = s.id
           LEFT JOIN clienti cl ON cl.id = sc.cliente_id"
        : "";

    $sqlOcc = "
        SELECT s.camera_id, s.data_checkin, s.data_checkout {$guestField}
        FROM soggiorni s
        {$guestJoin}
        WHERE s.stato = 'occupato'
          AND ? >= s.data_checkin
          AND ? <  s.data_checkout
          AND s.camera_id IN ({$ph})
        GROUP BY s.camera_id
    ";
    $stmtOcc = $mysqli->prepare($sqlOcc);
    if ($stmtOcc) {
        $paramsOcc = array_merge([$oggi, $oggi], $roomIds);
        bind_params($stmtOcc, 'ss' . $commonTypes, $paramsOcc);
        $stmtOcc->execute();
        $resOcc = $stmtOcc->get_result();
        while ($r = $resOcc->fetch_assoc()) {
            $occupazioni[(int)$r['camera_id']] = [
                'checkin' => $r['data_checkin'],
                'checkout' => $r['data_checkout'],
                'ospiti' => $r['ospiti'] ?? null,
            ];
        }
    }

    if (table_exists($mysqli, 'task_pulizie')) {
        $sqlPul = "
            SELECT camera_id, stato
            FROM task_pulizie
            WHERE data = ?
              AND camera_id IN ({$ph})
        ";
        $stmtPul = $mysqli->prepare($sqlPul);
        if ($stmtPul) {
            $paramsPul = array_merge([$oggi], $roomIds);
            bind_params($stmtPul, 's' . $commonTypes, $paramsPul);
            $stmtPul->execute();
            $resPul = $stmtPul->get_result();
            $priority = ['IN_CORSO' => 3, 'DA_FARE' => 2, 'COMPLETATO' => 1];
            while ($r = $resPul->fetch_assoc()) {
                $cid = (int)$r['camera_id'];
                $st = (string)$r['stato'];
                $cur = $pulizie[$cid]['stato'] ?? null;
                if (!$cur || ($priority[$st] ?? 0) > ($priority[$cur] ?? 0)) {
                    $pulizie[$cid] = ['stato' => $st];
                }
            }
        }
    }

    if (table_exists($mysqli, 'ticket_manutenzione')) {
        $sqlMan = "
            SELECT camera_id, stato
            FROM ticket_manutenzione
            WHERE camera_id IS NOT NULL
              AND stato IN ('APERTO','IN_CORSO')
              AND camera_id IN ({$ph})
        ";
        $stmtMan = $mysqli->prepare($sqlMan);
        if ($stmtMan) {
            bind_params($stmtMan, $commonTypes, $roomIds);
            $stmtMan->execute();
            $resMan = $stmtMan->get_result();
            $priority = ['IN_CORSO' => 2, 'APERTO' => 1];
            while ($r = $resMan->fetch_assoc()) {
                $cid = (int)$r['camera_id'];
                $st = (string)$r['stato'];
                $cur = $manutenzioni[$cid]['stato'] ?? null;
                if (!$cur || ($priority[$st] ?? 0) > ($priority[$cur] ?? 0)) {
                    $manutenzioni[$cid] = ['stato' => $st];
                }
            }
        }
    }
}

$stateMeta = [
    'occupata' => ['label' => 'Occupata', 'badge' => 'bg-danger', 'color' => '#dc3545'],
    'pulizia' => ['label' => 'In pulizia', 'badge' => 'bg-warning text-dark', 'color' => '#ffc107'],
    'manutenzione' => ['label' => 'Manutenzione', 'badge' => 'bg-secondary', 'color' => '#6c757d'],
    'libera' => ['label' => 'Libera', 'badge' => 'bg-success', 'color' => '#198754'],
];

$gridCols = max(3, min(6, (int)ceil(sqrt(max(1, count($roomsRaw))))));
$autoRow = 1;
$autoCol = 1;

$rooms = [];
foreach ($roomsRaw as $row) {
    $cid = (int)$row['id'];

    $pos = [
        'row' => $layoutRows ? (int)($row['layout_row'] ?? 0) : null,
        'col' => $layoutCols ? (int)($row['layout_col'] ?? 0) : null,
        'x' => $posX ? (int)($row['pos_x'] ?? 0) : null,
        'y' => $posY ? (int)($row['pos_y'] ?? 0) : null,
        'w' => $posW ? (int)($row['pos_w'] ?? 0) : null,
        'h' => $posH ? (int)($row['pos_h'] ?? 0) : null,
    ];

    if (empty($pos['row']) || empty($pos['col'])) {
        $pos['row'] = $autoRow;
        $pos['col'] = $autoCol;
        $autoCol++;
        if ($autoCol > $gridCols) {
            $autoCol = 1;
            $autoRow++;
        }
    }

    $state = 'libera';
    $detail = null;

    if (isset($manutenzioni[$cid])) {
        $state = 'manutenzione';
        $detail = $manutenzioni[$cid];
    } elseif (isset($pulizie[$cid])) {
        $state = 'pulizia';
        $detail = $pulizie[$cid];
    } elseif (isset($occupazioni[$cid])) {
        $state = 'occupata';
        $detail = $occupazioni[$cid];
    }

    $rooms[] = [
        'id' => $cid,
        'codice' => (string)$row['codice'],
        'nome' => (string)($row['nome'] ?? ''),
        'capienza' => (int)($row['capienza_base'] ?? 0),
        'note' => (string)($row['note'] ?? ''),
        'piano' => [
            'id' => (int)$row['piano_id'],
            'nome' => (string)$row['piano_nome'],
            'livello' => (int)($row['livello'] ?? 0),
        ],
        'edificio' => [
            'id' => (int)$row['edificio_id'],
            'nome' => (string)$row['edificio_nome'],
        ],
        'stato' => $state,
        'stato_label' => $stateMeta[$state]['label'],
        'badge_class' => $stateMeta[$state]['badge'],
        'color' => $stateMeta[$state]['color'],
        'detail' => $detail,
        'pos' => $pos,
    ];
}

echo json_encode([
    'success' => true,
    'edificio' => $edificioInfo ? [
        'id' => (int)$edificioInfo['id'],
        'nome' => (string)$edificioInfo['nome'],
    ] : null,
    'piano' => $pianoInfo ? [
        'id' => (int)$pianoInfo['id'],
        'nome' => (string)$pianoInfo['nome'],
        'livello' => (int)($pianoInfo['livello'] ?? 0),
    ] : null,
    'grid' => ['cols' => $gridCols],
    'camere' => $rooms,
]);
