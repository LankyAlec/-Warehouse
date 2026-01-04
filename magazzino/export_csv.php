<?php
declare(strict_types=1);

/* ======================
 * SILENZIA ERRORI
 * ====================== */
ini_set('display_errors', '0');
error_reporting(0);
while (ob_get_level() > 0) { @ob_end_clean(); }

require __DIR__ . '/init.php';

/* fallback esc() se manca */
if (!function_exists('esc')) {
  function esc($conn, string $s): string {
    return mysqli_real_escape_string($conn, $s);
  }
}

/* ======================
 * INPUT (come index)
 * ====================== */
$q            = trim((string)($_GET['q'] ?? ''));
$magazzino_id = (int)($_GET['magazzino_id'] ?? 0);
$categoria_id = (int)($_GET['categoria_id'] ?? 0);
$expiring     = (int)($_GET['expiring'] ?? 0);
$days         = max(0, (int)($_GET['days'] ?? 30));

/* ======================
 * WHERE (senza rompere LEFT JOIN)
 * ====================== */
$where = ["p.attivo = 1"];

if ($q !== '') {
  $qq = esc($conn, $q);
  $where[] = "(
    p.nome LIKE '%$qq%'
    OR p.descrizione LIKE '%$qq%'
    OR p.unita LIKE '%$qq%'
    OR EXISTS (
      SELECT 1 FROM lotti lx
      WHERE lx.prodotto_id = p.id
        AND (lx.scaffale LIKE '%$qq%' OR lx.ripiano LIKE '%$qq%')
    )
  )";
}

if ($magazzino_id > 0) $where[] = "p.magazzino_id = $magazzino_id";
if ($categoria_id > 0) $where[] = "p.categoria_id = $categoria_id";

/* filtro “Scadenza entro X gg”: usa EXISTS, così i prodotti senza lotti spariscono SOLO se spunti il filtro */
if ($expiring === 1) {
  $where[] = "EXISTS (
    SELECT 1 FROM lotti l2
    WHERE l2.prodotto_id = p.id
      AND l2.data_scadenza IS NOT NULL
      AND l2.data_scadenza <= DATE_ADD(CURDATE(), INTERVAL $days DAY)
  )";
}

$w = 'WHERE ' . implode(' AND ', $where);

/* ======================
 * QUERY: prodotti + lotti (LEFT JOIN)
 *  - 0 lotti -> 1 riga con campi lotto vuoti
 *  - N lotti -> N righe
 * ====================== */
$sql = "
SELECT
  p.nome              AS prodotto,
  p.descrizione       AS descrizione,
  c.tipo              AS categoria_tipo,
  c.nome              AS categoria_nome,
  m.nome              AS magazzino,
  p.unita             AS unita,

  l.quantita          AS quantita,
  l.prezzo            AS prezzo,
  l.scaffale          AS scaffale,
  l.ripiano           AS ripiano,
  l.data_scadenza     AS scadenza

FROM prodotti p
JOIN magazzini m ON m.id = p.magazzino_id
LEFT JOIN categorie c ON c.id = p.categoria_id
LEFT JOIN lotti l ON l.prodotto_id = p.id

$w
ORDER BY p.nome ASC, l.data_scadenza ASC, l.id ASC
";

$res = mysqli_query($conn, $sql);
if (!$res) {
  http_response_code(500);
  exit;
}

/* ======================
 * HEADERS CSV
 * ====================== */
$filename = 'magazzino_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM Excel

/* header */
fputcsv($out, [
  'Prodotto',
  'Descrizione',
  'CategoriaTipo',
  'CategoriaNome',
  'Magazzino',
  'Quantita',
  'Unita',
  'Prezzo',
  'Scaffale',
  'Ripiano',
  'Scadenza'
], ';', "\0");

/* ======================
 * ROWS
 * ====================== */
while ($r = mysqli_fetch_assoc($res)) {

  $qta = ($r['quantita'] === null || $r['quantita'] === '') ? '' : (int)$r['quantita'];

  $prezzo = '';
  if ($r['prezzo'] !== null && $r['prezzo'] !== '' && (float)$r['prezzo'] > 0) {
    $prezzo = number_format((float)$r['prezzo'], 2, ',', '.');
  }

  $scad = '';
  if ($r['scadenza'] !== null && $r['scadenza'] !== '') {
    $scad = date('d/m/Y', strtotime($r['scadenza']));
  }

  fputcsv($out, [
    $r['prodotto'],
    $r['descrizione'],
    $r['categoria_tipo'],
    $r['categoria_nome'],
    $r['magazzino'],
    $qta,
    $r['unita'],
    $prezzo,
    $r['scaffale'],
    $r['ripiano'],
    $scad,
  ], ';', "\0");
}

fclose($out);
exit;
