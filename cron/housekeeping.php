<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/housekeeping.php';

header('Content-Type: text/plain; charset=utf-8');

$oggi = date('Y-m-d');

$sql = "
    SELECT s.id AS soggiorno_id, s.camera_id
    FROM soggiorni s
    JOIN camere c ON c.id = s.camera_id
    WHERE s.stato = 'occupato'
      AND c.attiva = 1
      AND s.data_checkin <= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
      AND CURDATE() < s.data_checkout
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo "Errore DB: " . $mysqli->error . PHP_EOL;
    exit;
}

$stmt->execute();
$res = $stmt->get_result();

$scheduled = 0;
while ($row = $res->fetch_assoc()) {
    $cameraId    = (int)($row['camera_id'] ?? 0);
    $soggiornoId = (int)($row['soggiorno_id'] ?? 0) ?: null;

    if ($cameraId <= 0) {
        continue;
    }

    try {
        housekeeping_schedule_cleaning(
            $mysqli,
            $cameraId,
            $oggi,
            $soggiornoId,
            'cron',
            'Soggiorno in struttura da almeno 2 giorni'
        );
        $scheduled++;
    } catch (Throwable $e) {
        error_log('Housekeeping cron error (camera ' . $cameraId . '): ' . $e->getMessage());
    }
}

echo "Pulizie programmate: $scheduled — Data: $oggi" . PHP_EOL;
