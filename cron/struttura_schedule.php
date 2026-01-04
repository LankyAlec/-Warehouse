<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../struttura/struttura_status.php';

header('Content-Type: text/plain; charset=utf-8');

$oggi = date('Y-m-d');

try {
    $res = struttura_schedule_apply_due($mysqli, $oggi);
    echo "Schedulazioni applicate — start: {$res['applied_start']}, end: {$res['applied_end']} — Data: {$oggi}" . PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo "Errore schedulazioni: " . $e->getMessage() . PHP_EOL;
    error_log('Schedulazione struttura error: ' . $e->getMessage());
    exit;
}
