<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Restituisce lo stato corrente (0/1) dell'entità richiesta.
 */
function struttura_get_current_state(mysqli $mysqli, string $tipo, int $id): int {
    if (!in_array($tipo, ['edificio', 'piano', 'camera'], true)) {
        throw new InvalidArgumentException('Tipo non supportato');
    }

    $sql = [
        'edificio' => 'SELECT attivo AS stato FROM edifici WHERE id=?',
        'piano'    => 'SELECT attivo AS stato FROM piani WHERE id=?',
        'camera'   => 'SELECT attiva AS stato FROM camere WHERE id=?'
    ][$tipo];

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Errore DB: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        throw new InvalidArgumentException('Elemento non trovato');
    }

    return (int)($row['stato'] ?? 0);
}

/**
 * Applica l'attivazione/disattivazione con eventuale cascata.
 */
function struttura_apply_toggle(mysqli $mysqli, string $tipo, int $id, int $val, string $cascadeMode = 'off_only'): void {
    if (!in_array($tipo, ['edificio', 'piano', 'camera'], true)) {
        throw new InvalidArgumentException('Tipo non supportato');
    }
    if (!in_array($val, [0, 1], true)) {
        throw new InvalidArgumentException('Valore non valido');
    }

    // Validazione esistenza (serve anche per registrare lo stato attuale)
    struttura_get_current_state($mysqli, $tipo, $id);

    // cascadeMode: off_only | always
    $doCascade = ($cascadeMode === 'always') || ($cascadeMode === 'off_only' && $val === 0);

    $mysqli->begin_transaction();
    try {
        if ($tipo === 'edificio') {
            $stmt = $mysqli->prepare('UPDATE edifici SET attivo=? WHERE id=?');
            if (!$stmt) {
                throw new RuntimeException('Errore DB: ' . $mysqli->error);
            }
            $stmt->bind_param('ii', $val, $id);
            $stmt->execute();
            $stmt->close();

            if ($doCascade) {
                $stmt = $mysqli->prepare('UPDATE piani SET attivo=? WHERE edificio_id=?');
                if (!$stmt) {
                    throw new RuntimeException('Errore DB: ' . $mysqli->error);
                }
                $stmt->bind_param('ii', $val, $id);
                $stmt->execute();
                $stmt->close();

                $stmt = $mysqli->prepare('UPDATE camere c JOIN piani p ON p.id = c.piano_id SET c.attiva=? WHERE p.edificio_id=?');
                if (!$stmt) {
                    throw new RuntimeException('Errore DB: ' . $mysqli->error);
                }
                $stmt->bind_param('ii', $val, $id);
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($tipo === 'piano') {
            $stmt = $mysqli->prepare('UPDATE piani SET attivo=? WHERE id=?');
            if (!$stmt) {
                throw new RuntimeException('Errore DB: ' . $mysqli->error);
            }
            $stmt->bind_param('ii', $val, $id);
            $stmt->execute();
            $stmt->close();

            if ($doCascade) {
                $stmt = $mysqli->prepare('UPDATE camere SET attiva=? WHERE piano_id=?');
                if (!$stmt) {
                    throw new RuntimeException('Errore DB: ' . $mysqli->error);
                }
                $stmt->bind_param('ii', $val, $id);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $mysqli->prepare('UPDATE camere SET attiva=? WHERE id=?');
            if (!$stmt) {
                throw new RuntimeException('Errore DB: ' . $mysqli->error);
            }
            $stmt->bind_param('ii', $val, $id);
            $stmt->execute();
            $stmt->close();
        }

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }
}

/**
 * Crea una schedulazione di attivazione/disattivazione.
 */
function struttura_schedule_create(mysqli $mysqli, string $tipo, int $id, int $stato, string $startDate, ?string $endDate = null, string $cascadeMode = 'off_only'): int {
    if (!in_array($tipo, ['edificio', 'piano', 'camera'], true)) {
        throw new InvalidArgumentException('Tipo non supportato');
    }
    if (!in_array($stato, [0, 1], true)) {
        throw new InvalidArgumentException('Stato non valido');
    }
    if (!in_array($cascadeMode, ['off_only', 'always'], true)) {
        throw new InvalidArgumentException('Modalità cascata non valida');
    }

    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end   = $endDate ? DateTime::createFromFormat('Y-m-d', $endDate) : null;

    if (!$start || $start->format('Y-m-d') !== $startDate) {
        throw new InvalidArgumentException('Data di inizio non valida');
    }
    if ($end && $end->format('Y-m-d') !== $endDate) {
        throw new InvalidArgumentException('Data di fine non valida');
    }
    if ($end && $end < $start) {
        throw new InvalidArgumentException('La data di fine deve essere successiva o uguale alla data di inizio');
    }

    $restoreState = struttura_get_current_state($mysqli, $tipo, $id);

    $stmt = $mysqli->prepare("
        INSERT INTO struttura_schedules
        (tipo, ref_id, stato, start_date, end_date, restore_state, cascade_mode)
        VALUES (?,?,?,?,?,?,?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Errore DB: ' . $mysqli->error);
    }

    $stmt->bind_param(
        'siissis',
        $tipo,
        $id,
        $stato,
        $startDate,
        $endDate,
        $restoreState,
        $cascadeMode
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Errore salvataggio schedulazione: ' . $mysqli->error);
    }

    $newId = $stmt->insert_id;
    $stmt->close();

    return $newId;
}

/**
 * Restituisce la prossima schedulazione attiva/non completata per l'elemento.
 */
function struttura_schedule_next(mysqli $mysqli, string $tipo, int $id): ?array {
    if (!in_array($tipo, ['edificio', 'piano', 'camera'], true)) {
        return null;
    }

    $stmt = $mysqli->prepare("
        SELECT id, stato, start_date, end_date, applied_start, applied_end, cascade_mode
        FROM struttura_schedules
        WHERE tipo=? AND ref_id=? AND applied_end = 0
        ORDER BY start_date ASC, id ASC
        LIMIT 1
    ");
    if (!$stmt) {
        throw new RuntimeException('Errore DB: ' . $mysqli->error);
    }
    $stmt->bind_param('si', $tipo, $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

/**
 * Applica le schedulazioni dovute fino alla data indicata.
 * Restituisce un array con i conteggi delle operazioni eseguite.
 */
function struttura_schedule_apply_due(mysqli $mysqli, ?string $today = null): array {
    $today = $today ?: date('Y-m-d');

    $stmt = $mysqli->prepare("
        SELECT id, tipo, ref_id, stato, start_date, end_date, restore_state, cascade_mode, applied_start, applied_end
        FROM struttura_schedules
        WHERE (applied_start = 0 AND start_date <= ?)
           OR (end_date IS NOT NULL AND applied_end = 0 AND end_date <= ?)
        ORDER BY start_date ASC, end_date ASC
    ");
    if (!$stmt) {
        throw new RuntimeException('Errore DB: ' . $mysqli->error);
    }
    $stmt->bind_param('ss', $today, $today);
    $stmt->execute();
    $res = $stmt->get_result();

    $appliedStart = 0;
    $appliedEnd   = 0;

    while ($row = $res->fetch_assoc()) {
        $id          = (int)$row['id'];
        $tipo        = (string)$row['tipo'];
        $refId       = (int)$row['ref_id'];
        $stato       = (int)$row['stato'];
        $startDate   = (string)$row['start_date'];
        $endDate     = $row['end_date'] ? (string)$row['end_date'] : null;
        $restore     = (int)$row['restore_state'];
        $cascadeMode = (string)$row['cascade_mode'];
        $startDone   = ((int)$row['applied_start'] === 1);
        $endDone     = ((int)$row['applied_end'] === 1);

        $shouldSave = false;

        if (!$startDone && $startDate <= $today) {
            struttura_apply_toggle($mysqli, $tipo, $refId, $stato, $cascadeMode);
            $startDone = true;
            $appliedStart++;
            $shouldSave = true;
            // Se non è prevista fine, consideriamo la schedulazione completa lato start
            if ($endDate === null) {
                $endDone = true;
            }
        }

        if ($endDate !== null && !$endDone && $endDate <= $today) {
            struttura_apply_toggle($mysqli, $tipo, $refId, $restore, $cascadeMode);
            $endDone = true;
            $appliedEnd++;
            $shouldSave = true;
        }

        if ($shouldSave) {
            $stmtUpd = $mysqli->prepare("UPDATE struttura_schedules SET applied_start=?, applied_end=? WHERE id=?");
            if ($stmtUpd) {
                $startInt = $startDone ? 1 : 0;
                $endInt   = $endDone ? 1 : 0;
                $stmtUpd->bind_param('iii', $startInt, $endInt, $id);
                $stmtUpd->execute();
                $stmtUpd->close();
            }
        }
    }
    $stmt->close();

    return [
        'applied_start' => $appliedStart,
        'applied_end'   => $appliedEnd,
        'date'          => $today,
    ];
}
