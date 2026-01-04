<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/housekeeping.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['utente_id'])) {
    json_out(['ok' => false, 'error' => 'Autenticazione richiesta.'], 401);
}

$azione = $_POST['azione'] ?? $_GET['azione'] ?? '';

if ($azione === 'checkout') {
    $soggiornoId = (int)($_POST['soggiorno_id'] ?? 0);
    if ($soggiornoId <= 0) {
        json_out(['ok' => false, 'error' => 'Soggiorno non valido.'], 422);
    }

    $stmt = $mysqli->prepare("SELECT id, camera_id, stato FROM soggiorni WHERE id = ? LIMIT 1");
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'Errore DB: ' . $mysqli->error], 500);
    }
    $stmt->bind_param("i", $soggiornoId);
    $stmt->execute();
    $stay = $stmt->get_result()->fetch_assoc();

    if (!$stay) {
        json_out(['ok' => false, 'error' => 'Prenotazione non trovata.'], 404);
    }

    $cameraId = (int)($stay['camera_id'] ?? 0);
    if ($cameraId <= 0) {
        json_out(['ok' => false, 'error' => 'Nessuna camera associata al soggiorno.'], 422);
    }

    housekeeping_ensure_tasks_table($mysqli);

    try {
        $result = housekeeping_with_transaction($mysqli, function() use ($mysqli, $cameraId, $soggiornoId) {
            $locked = housekeeping_lock_camera($mysqli, $cameraId);

            $taskId = housekeeping_upsert_task_locked(
                $mysqli,
                $cameraId,
                'IN_CORSO',
                date('Y-m-d'),
                'checkout',
                $soggiornoId,
                'Checkout automatico'
            );

            $cameraState = housekeeping_update_camera_state_locked($mysqli, $cameraId, 'pulizia', $locked);

            return [
                'task_id'      => $taskId,
                'camera_state' => $cameraState,
            ];
        });
    } catch (Throwable $e) {
        error_log('Errore checkout/pulizia: ' . $e->getMessage());
        json_out(['ok' => false, 'error' => 'Impossibile avviare la pulizia: ' . $e->getMessage()], 500);
    }

    json_out([
        'ok'           => true,
        'task_id'      => $result['task_id'] ?? null,
        'camera_id'    => $cameraId,
        'camera_stato' => $result['camera_state']['new'] ?? 'pulizia',
        'messaggio'    => 'Pulizia in corso avviata al check-out.',
    ]);
}

json_out(['ok' => false, 'error' => 'Azione non riconosciuta.'], 400);
