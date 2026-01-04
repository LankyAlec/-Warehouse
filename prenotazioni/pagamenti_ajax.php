<?php
declare(strict_types=1);

require_once __DIR__ . '/pagamenti_lib.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

$action = strtolower(trim((string)($_REQUEST['action'] ?? 'panel')));
$soggiornoId = (int)($_REQUEST['soggiorno_id'] ?? 0);

if ($soggiornoId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ID prenotazione mancante.']);
    exit;
}

ensure_pagamenti_table($mysqli);
$booking = load_booking($mysqli, $soggiornoId);

if (!$booking) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Prenotazione non trovata.']);
    exit;
}

if ($action === 'export_csv' || $action === 'export_pdf') {
    $pagamenti = load_pagamenti($mysqli, $soggiornoId);
    $filename = 'pagamenti_prenotazione_' . $soggiornoId;

    if ($action === 'export_csv') {
        $csv = render_pagamenti_csv($booking, $pagamenti);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        echo $csv;
        exit;
    }

    $pdf = render_pagamenti_pdf($booking, $pagamenti);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    echo $pdf;
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    if ($action === 'add') {
        $importo = parse_importo((string)($_POST['importo'] ?? ''));
        $metodo = normalize_metodo((string)($_POST['metodo'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        $saldoFinale = !empty($_POST['saldo_finale']) ? 1 : 0;

        $stmt = $mysqli->prepare("
            INSERT INTO soggiorni_pagamenti (soggiorno_id, importo, metodo, note, is_saldo_finale, utente_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            throw new RuntimeException('Errore DB: ' . $mysqli->error);
        }
        $uid = (int)($_SESSION['utente_id'] ?? 0);
        $stmt->bind_param('idssii', $soggiornoId, $importo, $metodo, $note, $saldoFinale, $uid);
        $stmt->execute();
    }

    $pagamenti = load_pagamenti($mysqli, $soggiornoId);
    $html = render_pagamenti_panel($booking, booking_total($booking), $pagamenti);

    echo json_encode(['ok' => true, 'html' => $html]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
