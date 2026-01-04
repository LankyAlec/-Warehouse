<?php
declare(strict_types=1);

/**
 * Utility centralizzate per la gestione stati camera/pulizie.
 * Stati camera armonizzati: occupata, pulizia, manutenzione, libera.
 * Stati task pulizia: DA_PULIRE, IN_CORSO, COMPLETATA, ANNULLATA.
 */

const HOUSEKEEPING_CAMERA_STATI = ['occupata', 'pulizia', 'manutenzione', 'libera'];
const HOUSEKEEPING_TASK_STATI   = ['DA_PULIRE', 'IN_CORSO', 'COMPLETATA', 'ANNULLATA'];

function housekeeping_camera_states(): array {
    return HOUSEKEEPING_CAMERA_STATI;
}

function housekeeping_task_states(): array {
    return HOUSEKEEPING_TASK_STATI;
}

function housekeeping_ensure_tasks_table(mysqli $db): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS housekeeping_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            camera_id INT NOT NULL,
            soggiorno_id INT NULL,
            data_riferimento DATE NOT NULL,
            stato VARCHAR(32) NOT NULL,
            note TEXT NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'manuale',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_camera_data (camera_id, data_riferimento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $db->query($sql);
}

function housekeeping_with_transaction(mysqli $db, callable $fn) {
    $db->begin_transaction();
    try {
        $result = $fn();
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function housekeeping_normalize_camera_state(?string $state): string {
    $state = trim((string)$state);
    if ($state === '') return 'libera';
    return strtolower($state);
}

function housekeeping_lock_camera(mysqli $db, int $cameraId): array {
    $stmt = $db->prepare("SELECT id, stato FROM camere WHERE id=? LIMIT 1 FOR UPDATE");
    if (!$stmt) throw new RuntimeException('Errore DB (lock camera): ' . $db->error);
    $stmt->bind_param("i", $cameraId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) throw new RuntimeException('Camera non trovata.');
    $row['stato'] = housekeeping_normalize_camera_state($row['stato'] ?? null);
    return $row;
}

function housekeeping_require_task_state(string $state): string {
    $state = strtoupper(trim($state));
    if (!in_array($state, HOUSEKEEPING_TASK_STATI, true)) {
        throw new InvalidArgumentException('Stato task non valido: ' . $state);
    }
    return $state;
}

function housekeeping_require_camera_state(string $state): string {
    $state = housekeeping_normalize_camera_state($state);
    if (!in_array($state, HOUSEKEEPING_CAMERA_STATI, true)) {
        throw new InvalidArgumentException('Stato camera non valido: ' . $state);
    }
    return $state;
}

function housekeeping_upsert_task_locked(
    mysqli $db,
    int $cameraId,
    string $taskState,
    string $dataRiferimento,
    string $source = 'manuale',
    ?int $soggiornoId = null,
    ?string $note = null
): int {
    $taskState = housekeeping_require_task_state($taskState);

    $stmtSel = $db->prepare("
        SELECT id
        FROM housekeeping_tasks
        WHERE camera_id = ? AND data_riferimento = ?
        LIMIT 1
        FOR UPDATE
    ");
    if (!$stmtSel) {
        throw new RuntimeException('Errore DB (selezione task): ' . $db->error);
    }
    $stmtSel->bind_param("is", $cameraId, $dataRiferimento);
    $stmtSel->execute();
    $existing = $stmtSel->get_result()->fetch_assoc();

    if ($existing) {
        $taskId = (int)$existing['id'];
        $stmtUp = $db->prepare("
            UPDATE housekeeping_tasks
            SET stato = ?, note = COALESCE(?, note), source = ?, soggiorno_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if (!$stmtUp) throw new RuntimeException('Errore DB (update task): ' . $db->error);
        $stmtUp->bind_param("sssii", $taskState, $note, $source, $soggiornoId, $taskId);
        $stmtUp->execute();
        return $taskId;
    }

    $stmtIns = $db->prepare("
        INSERT INTO housekeeping_tasks
            (camera_id, soggiorno_id, stato, data_riferimento, note, source, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    if (!$stmtIns) throw new RuntimeException('Errore DB (insert task): ' . $db->error);
    $stmtIns->bind_param("iissss", $cameraId, $soggiornoId, $taskState, $dataRiferimento, $note, $source);
    $stmtIns->execute();
    return (int)$db->insert_id;
}

function housekeeping_update_camera_state_locked(mysqli $db, int $cameraId, string $newState, ?array $lockedRow = null): array {
    $newState = housekeeping_require_camera_state($newState);
    $oldState = housekeeping_normalize_camera_state($lockedRow['stato'] ?? null);

    $stmt = $db->prepare("UPDATE camere SET stato = ? WHERE id = ?");
    if (!$stmt) throw new RuntimeException('Errore DB (update camera): ' . $db->error);
    $stmt->bind_param("si", $newState, $cameraId);
    $stmt->execute();

    return ['old' => $oldState, 'new' => $newState];
}

function housekeeping_schedule_cleaning(
    mysqli $db,
    int $cameraId,
    string $dataRiferimento,
    ?int $soggiornoId = null,
    string $source = 'cron',
    ?string $note = null
): int {
    housekeeping_ensure_tasks_table($db);
    return housekeeping_with_transaction($db, function() use ($db, $cameraId, $dataRiferimento, $soggiornoId, $source, $note) {
        return housekeeping_upsert_task_locked($db, $cameraId, 'DA_PULIRE', $dataRiferimento, $source, $soggiornoId, $note);
    });
}

function housekeeping_start_cleaning(
    mysqli $db,
    int $cameraId,
    ?int $soggiornoId = null,
    ?string $note = null,
    ?string $dataRiferimento = null,
    ?callable $extraWork = null
): array {
    housekeeping_ensure_tasks_table($db);
    $data = $dataRiferimento ?: date('Y-m-d');

    return housekeeping_with_transaction($db, function() use ($db, $cameraId, $soggiornoId, $note, $extraWork, $data) {
        $locked = housekeeping_lock_camera($db, $cameraId);

        $taskId = housekeeping_upsert_task_locked(
            $db,
            $cameraId,
            'IN_CORSO',
            $data,
            'checkout',
            $soggiornoId,
            $note
        );

        $cameraState = housekeeping_update_camera_state_locked($db, $cameraId, 'pulizia', $locked);

        if ($extraWork) {
            $extraWork($db, $locked);
        }

        return [
            'task_id' => $taskId,
            'camera_state' => $cameraState,
        ];
    });
}
