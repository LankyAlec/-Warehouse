<?php
declare(strict_types=1);

/**
 * Connessione al DB del magazzino, riutilizzata tra le chiamate.
 * Restituisce null se non configurato o non raggiungibile.
 */
function magazzino_db(): ?mysqli {
    static $magConn = null;

    if ($magConn instanceof mysqli) {
        if (@$magConn->ping()) {
            return $magConn;
        }
        $magConn = null;
    }

    $magConn = (function () {
        if (!defined('MAGAZZINO_SILENT')) {
            define('MAGAZZINO_SILENT', true);
        }

        $conn = null;
        require __DIR__ . '/../magazzino/config.php';

        return ($conn ?? null) instanceof mysqli ? $conn : null;
    })();

    return $magConn;
}

/**
 * Calcola i numeri di magazzino richiesti in dashboard.
 *
 * @param int $days numero di giorni entro cui considerare la scadenza (incluso l'oggi).
 * @return array{ok:bool, prodotti_presenti:int, prodotti_in_scadenza:int}
 */
function magazzino_stats(int $days = 30): array {
    $conn = magazzino_db();

    if (!$conn) {
        error_log('Magazzino non raggiungibile: connessione nulla');
        return [
            'ok' => false,
            'prodotti_presenti' => 0,
            'prodotti_in_scadenza' => 0,
        ];
    }

    $days = max(0, $days);

    $sql = "
        SELECT
            SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) AS prodotti_presenti,
            SUM(CASE WHEN stock > 0 AND expiring > 0 THEN 1 ELSE 0 END) AS prodotti_in_scadenza
        FROM (
            SELECT
                p.id,
                COALESCE((
                    SELECT SUM(
                        CASE WHEN mv.tipo='CARICO' THEN mv.quantita ELSE -mv.quantita END
                    )
                    FROM movimenti mv
                    WHERE mv.prodotto_id = p.id
                ), 0) AS stock,
                (
                    SELECT COUNT(1)
                    FROM lotti l
                    WHERE l.prodotto_id = p.id
                      AND l.data_scadenza IS NOT NULL
                      AND l.data_scadenza <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ) AS expiring
            FROM prodotti p
            WHERE p.attivo = 1
        ) AS t
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Magazzino stats prepare error: ' . $conn->error);
        return [
            'ok' => false,
            'prodotti_presenti' => 0,
            'prodotti_in_scadenza' => 0,
        ];
    }

    $stmt->bind_param('i', $days);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    return [
        'ok' => true,
        'prodotti_presenti' => (int)($row['prodotti_presenti'] ?? 0),
        'prodotti_in_scadenza' => (int)($row['prodotti_in_scadenza'] ?? 0),
    ];
}
