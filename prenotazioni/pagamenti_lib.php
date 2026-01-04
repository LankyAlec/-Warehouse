<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isset($_SESSION['utente_id'])) {
    http_response_code(401);
    exit('Sessione scaduta, effettua di nuovo il login.');
}

/**
 * Tabella di appoggio per i pagamenti dei soggiorni.
 */
function ensure_pagamenti_table(mysqli $mysqli): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS soggiorni_pagamenti (
            id INT AUTO_INCREMENT PRIMARY KEY,
            soggiorno_id INT NOT NULL,
            importo DECIMAL(10,2) NOT NULL DEFAULT 0,
            metodo VARCHAR(20) NOT NULL,
            note TEXT NULL,
            is_saldo_finale TINYINT(1) NOT NULL DEFAULT 0,
            utente_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_soggiorno (soggiorno_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $mysqli->query($sql);
}

function load_booking(mysqli $mysqli, int $id): ?array {
    $stmt = $mysqli->prepare("SELECT * FROM soggiorni WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function booking_total(array $booking): float {
    $candidates = ['totale', 'totale_previsto', 'importo_totale', 'prezzo_totale', 'importo', 'prezzo'];
    foreach ($candidates as $field) {
        if (isset($booking[$field]) && $booking[$field] !== null && $booking[$field] !== '') {
            return max(0, (float)$booking[$field]);
        }
    }
    return 0.0;
}

function booking_label(array $booking): string {
    $parts = [];
    if (!empty($booking['codice'])) {
        $parts[] = 'Codice ' . $booking['codice'];
    }
    if (!empty($booking['camera_id'])) {
        $parts[] = 'Camera #' . $booking['camera_id'];
    }
    if (!empty($booking['stato'])) {
        $parts[] = ucfirst((string)$booking['stato']);
    }
    return $parts ? implode(' • ', $parts) : 'Prenotazione';
}

function load_pagamenti(mysqli $mysqli, int $soggiornoId): array {
    $stmt = $mysqli->prepare("
        SELECT p.*, u.username
        FROM soggiorni_pagamenti p
        LEFT JOIN utenti u ON u.id = p.utente_id
        WHERE p.soggiorno_id = ?
        ORDER BY p.created_at DESC, p.id DESC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $soggiornoId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
}

function parse_importo(string $raw): float {
    $raw = str_replace(',', '.', trim($raw));
    if ($raw === '' || !preg_match('/^-?\d+(\.\d{1,2})?$/', $raw)) {
        throw new InvalidArgumentException('Importo non valido.');
    }
    $value = round((float)$raw, 2);
    if ($value <= 0) {
        throw new InvalidArgumentException('L\'importo deve essere maggiore di zero.');
    }
    return $value;
}

function allowed_metodi(): array {
    return [
        'contanti' => 'Contanti',
        'carta'    => 'Carta',
        'bonifico' => 'Bonifico',
    ];
}

function normalize_metodo(string $value): string {
    $value = strtolower(trim($value));
    $allowed = allowed_metodi();
    if (!array_key_exists($value, $allowed)) {
        throw new InvalidArgumentException('Metodo di pagamento non valido.');
    }
    return $value;
}

function format_currency(float $amount): string {
    return '€ ' . number_format($amount, 2, ',', '.');
}

function saldo_info(float $totale, float $pagato): array {
    $saldo = max(0, round($totale - $pagato, 2));

    if ($totale <= 0 && $pagato <= 0) {
        return ['etichetta' => 'Importo non impostato', 'badge' => 'secondary', 'saldo' => $saldo];
    }

    if ($saldo <= 0 && $totale > 0) {
        return ['etichetta' => 'Pagato', 'badge' => 'success', 'saldo' => 0.0];
    }

    if ($pagato <= 0) {
        return ['etichetta' => 'Non pagato', 'badge' => 'secondary', 'saldo' => $saldo];
    }

    return ['etichetta' => 'Parziale', 'badge' => 'warning', 'saldo' => $saldo];
}

function render_pagamenti_panel(array $booking, float $totale, array $pagamenti): string {
    $pagato = 0.0;
    foreach ($pagamenti as $p) {
        $pagato += (float)($p['importo'] ?? 0);
    }
    $saldo = saldo_info($totale, $pagato);
    $metodi = allowed_metodi();

    ob_start();
    ?>
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <div class="text-uppercase text-secondary fw-semibold small">Saldo in tempo reale</div>
                    <div class="fs-4 fw-bold"><?= h(format_currency($saldo['saldo'])) ?></div>
                    <div class="text-muted small">Totale prenotazione: <?= h(format_currency($totale)) ?></div>
                </div>
                <div class="text-end">
                    <span class="badge text-bg-<?= h($saldo['badge']) ?> px-3 py-2"><?= h($saldo['etichetta']) ?></span>
                    <div class="small text-muted mt-2">Pagato: <b><?= h(format_currency($pagato)) ?></b></div>
                </div>
            </div>

            <form id="js-payment-form" class="row g-3 mb-4">
                <input type="hidden" name="soggiorno_id" value="<?= (int)$booking['id'] ?>">
                <div class="col-12 col-lg-4">
                    <label class="form-label mb-1">Importo</label>
                    <div class="input-group">
                        <span class="input-group-text">€</span>
                        <input type="number" min="0.01" step="0.01" class="form-control" name="importo" placeholder="0,00" required>
                    </div>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label mb-1">Metodo</label>
                    <select class="form-select" name="metodo" required>
                        <option value="">Scegli...</option>
                        <?php foreach ($metodi as $val => $lbl): ?>
                            <option value="<?= h($val) ?>"><?= h($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label mb-1">Note</label>
                    <input type="text" class="form-control" name="note" placeholder="Causale / note">
                </div>
                <div class="col-12 col-lg-2 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="saldo_finale" name="saldo_finale">
                        <label class="form-check-label" for="saldo_finale">Saldo finale</label>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-cash-coin"></i> Registra pagamento
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2 js-refresh-panel">
                        <i class="bi bi-arrow-repeat"></i> Aggiorna
                    </button>
                    <div class="float-end">
                        <a class="btn btn-outline-success btn-sm" href="<?= BASE_URL ?>/prenotazioni/pagamenti_ajax.php?action=export_csv&soggiorno_id=<?= (int)$booking['id'] ?>">
                            <i class="bi bi-filetype-csv"></i> Export CSV
                        </a>
                        <a class="btn btn-outline-danger btn-sm ms-2" href="<?= BASE_URL ?>/prenotazioni/pagamenti_ajax.php?action=export_pdf&soggiorno_id=<?= (int)$booking['id'] ?>">
                            <i class="bi bi-file-earmark-pdf"></i> Export PDF
                        </a>
                    </div>
                </div>
            </form>

            <div class="d-flex align-items-center gap-3 mb-3">
                <div>
                    <div class="text-uppercase small text-secondary">Totale</div>
                    <div class="fw-bold"><?= h(format_currency($totale)) ?></div>
                </div>
                <div>
                    <div class="text-uppercase small text-secondary">Pagato</div>
                    <div class="fw-bold text-success"><?= h(format_currency($pagato)) ?></div>
                </div>
                <div>
                    <div class="text-uppercase small text-secondary">Saldo</div>
                    <div class="fw-bold text-danger"><?= h(format_currency($saldo['saldo'])) ?></div>
                </div>
            </div>

            <div class="border rounded p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-semibold">Timeline movimenti</div>
                    <div class="text-secondary small">Ultimo aggiornamento: <?= h(date('d/m/Y H:i')) ?></div>
                </div>
                <?php if (!$pagamenti): ?>
                    <div class="text-muted">Nessun pagamento registrato.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($pagamenti as $p): ?>
                            <?php
                                $dt = $p['created_at'] ?? '';
                                $ts = $dt ? strtotime($dt) : false;
                                $labelData = $ts ? date('d/m/Y H:i', $ts) : 'Data non disponibile';
                                $metodoKey = strtolower((string)($p['metodo'] ?? ''));
                                $metodoLbl = $metodi[$metodoKey] ?? ucfirst($metodoKey);
                                $note = trim((string)($p['note'] ?? ''));
                                $utente = trim((string)($p['username'] ?? ''));
                            ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold"><?= h(format_currency((float)$p['importo'])) ?></div>
                                        <div class="small text-secondary">
                                            <?= h($labelData) ?> •
                                            <span class="badge rounded-pill text-bg-light text-dark border"><?= h($metodoLbl) ?></span>
                                            <?php if (!empty($p['is_saldo_finale'])): ?>
                                                <span class="badge text-bg-primary ms-1">Saldo</span>
                                            <?php endif; ?>
                                            <?php if ($note !== ''): ?>
                                                <span class="ms-2"><?= h($note) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($utente !== ''): ?>
                                            <div class="small text-muted">Operatore: <?= h($utente) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end text-muted small">
                                        ID movimento: <?= (int)$p['id'] ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function render_booking_badge(array $booking): string {
    $stato = strtolower((string)($booking['stato'] ?? ''));
    $map = [
        'prenotato' => 'info',
        'occupato'  => 'success',
        'cancellato'=> 'danger',
    ];
    $color = $map[$stato] ?? 'secondary';
    $label = $stato ? ucfirst($stato) : 'N/D';
    return '<span class="badge text-bg-' . h($color) . '">' . h($label) . '</span>';
}

function render_pagamenti_csv(array $booking, array $pagamenti): string {
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, ['Prenotazione', (string)($booking['id'] ?? 'N/D')], ';');
    fputcsv($fh, ['Data', 'Metodo', 'Importo', 'Note', 'Operatore', 'Saldo finale'], ';');
    foreach ($pagamenti as $p) {
        $dt = $p['created_at'] ?? '';
        $ts = $dt ? strtotime($dt) : false;
        $metodi = allowed_metodi();
        $metodoKey = strtolower((string)($p['metodo'] ?? ''));
        $metodoLbl = $metodi[$metodoKey] ?? ucfirst($metodoKey);
        fputcsv($fh, [
            $ts ? date('d/m/Y H:i', $ts) : $dt,
            $metodoLbl,
            number_format((float)$p['importo'], 2, ',', '.'),
            trim((string)($p['note'] ?? '')),
            trim((string)($p['username'] ?? '')),
            !empty($p['is_saldo_finale']) ? 'Sì' : 'No',
        ], ';');
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return $csv ?: '';
}

function render_pagamenti_pdf(array $booking, array $pagamenti): string {
    $lines = [];
    $lines[] = 'Ricevute prenotazione #' . (int)($booking['id'] ?? 0);
    $totale = booking_total($booking);
    $pagato = 0.0;
    foreach ($pagamenti as $p) {
        $pagato += (float)($p['importo'] ?? 0);
    }
    $saldo = max(0, round($totale - $pagato, 2));
    $lines[] = 'Totale: € ' . number_format($totale, 2, ',', '.') . ' | Pagato: € ' . number_format($pagato, 2, ',', '.') . ' | Saldo: € ' . number_format($saldo, 2, ',', '.');
    $lines[] = str_repeat('-', 40);
    foreach ($pagamenti as $p) {
        $dt = $p['created_at'] ?? '';
        $ts = $dt ? strtotime($dt) : false;
        $metodi = allowed_metodi();
        $metodoKey = strtolower((string)($p['metodo'] ?? ''));
        $metodoLbl = $metodi[$metodoKey] ?? ucfirst($metodoKey);
        $line = ($ts ? date('d/m/Y H:i', $ts) : $dt) . ' | ' . $metodoLbl . ' | € ' . number_format((float)$p['importo'], 2, ',', '.');
        if (!empty($p['is_saldo_finale'])) {
            $line .= ' (Saldo finale)';
        }
        $note = trim((string)($p['note'] ?? ''));
        if ($note !== '') {
            $line .= ' - ' . $note;
        }
        $user = trim((string)($p['username'] ?? ''));
        if ($user !== '') {
            $line .= ' [' . $user . ']';
        }
        $lines[] = $line;
    }
    if (!$pagamenti) {
        $lines[] = 'Nessun pagamento registrato.';
    }

    $objects = [];
    $pdf = "%PDF-1.4\n";

    $contentLines = ["BT", "/F1 12 Tf", "50 800 Td"];
    foreach ($lines as $i => $line) {
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        $contentLines[] = ($i === 0 ? "($safe) Tj" : "T* ($safe) Tj");
    }
    $contentLines[] = "ET";
    $stream = implode("\n", $contentLines);

    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[] = "<< /Type /Pages /Count 1 /Kids [3 0 R] >>";
    $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 5 0 R /Resources << /Font << /F1 4 0 R >> >> >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /Name /F1 /BaseFont /Helvetica >>";
    $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";

    $offsets = [0];
    foreach ($objects as $i => $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

    return $pdf;
}
