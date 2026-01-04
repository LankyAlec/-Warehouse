<?php
declare(strict_types=1);

// Bootstrap magazzino pages with the main app stack.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

/**
 * Safe int casting with minimum bound.
 */
function qint($value, int $min = 0): int {
    return max($min, (int)$value);
}

/**
 * Flash helpers (scoped to the magazzino module).
 */
function flash_set(string $type, string $msg): void {
    $_SESSION['flash_magazzino'] = ['type' => $type, 'msg' => $msg];
}

function flash_take(): ?array {
    if (!isset($_SESSION['flash_magazzino'])) {
        return null;
    }
    $f = $_SESSION['flash_magazzino'];
    unset($_SESSION['flash_magazzino']);
    return $f;
}

/**
 * Redirect helper that keeps the magazzino base path.
 */
function mag_redirect(string $path): void {
    $clean = ltrim($path, '/');
    header('Location: ' . BASE_URL . '/magazzino/' . $clean);
    exit;
}

/**
 * PDO helper (used by legacy magazzino code). We reuse the main DB credentials.
 */
if (!isset($pdo)) {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db_host, $db_port, $db_name);
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        error_log('PDO connection error (magazzino): ' . $e->getMessage());
        http_response_code(500);
        echo 'Errore DB: connessione non disponibile.';
        exit;
    }
}
