<?php
declare(strict_types=1);

require __DIR__ . '/init.php'; // $conn (mysqli) solo backend

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

/* =========================
 * HELPERS
 * ======================= */

function json_out(array $payload): void {
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function fmtDateIt(?string $ymd): string {
  $ymd = trim((string)$ymd);
  if ($ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return '—';
  $t = strtotime($ymd);
  return $t ? date('d/m/Y', $t) : '—';
}

function fmtTsIt(?string $ts): string {
  $ts = trim((string)$ts);
  if ($ts === '') return '—';
  $t = strtotime($ts);
  return $t ? date('d/m/Y H:i', $t) : '—';
}

/**
 * Prezzo opzionale:
 * - vuoto => NULL
 * - valido => float >= 0
 * - non valido => eccezione
 */
function parse_price_nullable(string $raw): ?float {
  $raw = str_replace(',', '.', trim($raw));
  if ($raw === '') return null;
  if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
    throw new RuntimeException('Prezzo unitario non valido.');
  }
  $v = (float)$raw;
  if ($v < 0) throw new RuntimeException('Prezzo unitario non valido.');
  return $v;
}

/**
 * datetime-local (YYYY-MM-DDTHH:MM) -> MySQL (YYYY-MM-DD HH:MM:SS)
 * Se vuoto: fallback ad adesso (così la UI può precompilare ma non rompiamo se manca).
 */
function parse_datetime_local_to_mysql(string $raw): string {
  $raw = trim($raw);
  if ($raw === '') return date('Y-m-d H:i:s');

  $raw = str_replace('T', ' ', $raw);
  if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) $raw .= ':00';

  if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
    throw new RuntimeException('Data/ora movimento non valida.');
  }
  if (strtotime($raw) === false) throw new RuntimeException('Data/ora movimento non valida.');

  return $raw;
}

$MOV_PER_PAGE = 7;

/* =========================
 * INPUT (GET/POST)
 * ======================= */
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$pid  = (int)($_REQUEST['id'] ?? 0);
$lid  = (int)($_REQUEST['lotto_id'] ?? 0);
$page = (int)($_REQUEST['page'] ?? 1);
if ($page < 1) $page = 1;

if ($pid <= 0 || $lid <= 0) {
  json_out(['ok'=>false, 'html'=>'Parametri non validi']);
}

/* =========================
 * PRODOTTO / LOTTO
 * ======================= */
$res = mysqli_query($conn, "SELECT id, unita FROM prodotti WHERE id=$pid LIMIT 1");
if (!$res || !($prod = mysqli_fetch_assoc($res))) json_out(['ok'=>false, 'html'=>'Prodotto non trovato']);
$unita = (string)($prod['unita'] ?? 'pz');

$res = mysqli_query($conn, "SELECT id, prodotto_id, data_scadenza, scaffale, ripiano
                            FROM lotti WHERE id=$lid AND prodotto_id=$pid LIMIT 1");
if (!$res || !($lot = mysqli_fetch_assoc($res))) json_out(['ok'=>false, 'html'=>'Lotto non trovato']);

/* =========================
 * RENDER HTML (panel)
 * ======================= */
$render_panel = function(int $pid, int $lid, int $page) use ($conn, $MOV_PER_PAGE, $unita) : string {
  $offset = ($page - 1) * $MOV_PER_PAGE;

  $tot = 0;
  $resC = mysqli_query($conn, "SELECT COUNT(*) AS c FROM movimenti WHERE prodotto_id=$pid AND lotto_id=$lid");
  if ($resC && ($cc = mysqli_fetch_assoc($resC))) $tot = (int)$cc['c'];

  $pages = max(1, (int)ceil($tot / $MOV_PER_PAGE));
  if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $MOV_PER_PAGE; }

  $movimenti = [];
  $sqlM = "
    SELECT mv.id, mv.ts, mv.tipo, mv.quantita, mv.prezzo,
           mv.note, mv.fornitore_id, mv.doc_tipo, mv.doc_numero, mv.doc_data,
           f.nome AS fornitore_nome
    FROM movimenti mv
    LEFT JOIN fornitori f ON f.id = mv.fornitore_id
    WHERE mv.prodotto_id = $pid AND mv.lotto_id = $lid
    ORDER BY mv.ts DESC, mv.id DESC
    LIMIT $MOV_PER_PAGE OFFSET $offset
  ";
  $res = mysqli_query($conn, $sqlM);
  while ($res && ($r = mysqli_fetch_assoc($res))) $movimenti[] = $r;

  ob_start();
  ?>
  <div class="border rounded p-3 h-100 d-flex flex-column">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
      <div class="fw-semibold">Storico</div>
      <div class="text-secondary small">
        Pagina <b><?= (int)$page ?></b> / <b><?= (int)$pages ?></b> • Movimenti: <b><?= (int)$tot ?></b>
      </div>
    </div>

    <?php if (!$movimenti): ?>
      <div class="py-3 text-center text-secondary">Nessun movimento registrato</div>
    <?php else: ?>
      <div class="table-responsive flex-grow-1">
        <table class="table table-sm align-middle mb-0">
          <thead class="text-secondary">
            <tr>
              <th>Data</th>
              <th>Tipo</th>
              <th class="text-end">Qtà</th>
              <th class="text-end">Azioni</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($movimenti as $m): ?>
            <?php
              $tipo = (string)$m['tipo'];
              $tipoBadge = ($tipo === 'CARICO') ? 'text-bg-success' : 'text-bg-danger';
              $tsDb = (string)($m['ts'] ?? '');
              $tsLbl = fmtTsIt($tsDb);

              $tsLocal = '';
              if ($tsDb !== '') {
                $t = strtotime($tsDb);
                if ($t) $tsLocal = date('Y-m-d\TH:i', $t);
              }

              $fornId = (int)($m['fornitore_id'] ?? 0);
              $fornNome = trim((string)($m['fornitore_nome'] ?? ''));
              $prezzo = ($m['prezzo'] === null ? '' : (string)$m['prezzo']);

              $docTipo = (string)($m['doc_tipo'] ?? '');
              $docNumero = (string)($m['doc_numero'] ?? '');
              $docData = (string)($m['doc_data'] ?? '');

              if ($tipo !== 'CARICO') {
                $fornId = 0; $fornNome = ''; $prezzo = '';
                $docTipo=''; $docNumero=''; $docData='';
              }
            ?>
            <tr>
              <td><?= h($tsLbl) ?></td>
              <td><span class="badge of-mov-badge <?= h($tipoBadge) ?>"><?= h($tipo) ?></span></td>
              <td class="text-end"><b><?= (int)$m['quantita'] ?></b> <?= h($unita) ?></td>
              <td class="text-end">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary of-icon-btn js-view-mov"
                        title="Dettaglio"
                        data-bs-toggle="modal"
                        data-bs-target="#viewMovModal"
                        data-mov-ts="<?= h($tsLbl) ?>"
                        data-mov-tipo="<?= h($tipo) ?>"
                        data-mov-qta="<?= (int)$m['quantita'] ?>"
                        data-mov-unita="<?= h($unita) ?>"
                        data-mov-prezzo="<?= h($prezzo) ?>"
                        data-mov-forn="<?= (int)$fornId ?>"
                        data-mov-forn-nome="<?= h($fornNome) ?>"
                        data-mov-doc-tipo="<?= h($docTipo) ?>"
                        data-mov-doc-numero="<?= h($docNumero) ?>"
                        data-mov-doc-data="<?= h($docData) ?>"
                        data-mov-note="<?= h((string)($m['note'] ?? '')) ?>">
                  <i class="bi bi-eye"></i>
                </button>

                <button type="button"
                        class="btn btn-sm btn-outline-primary of-icon-btn js-edit-mov"
                        title="Modifica"
                        data-bs-toggle="modal"
                        data-bs-target="#editMovModal"
                        data-mov-id="<?= (int)$m['id'] ?>"
                        data-mov-tipo="<?= h($tipo) ?>"
                        data-mov-qta="<?= (int)$m['quantita'] ?>"
                        data-mov-tslocal="<?= h($tsLocal) ?>"
                        data-mov-prezzo="<?= h($prezzo) ?>"
                        data-mov-forn="<?= (int)$fornId ?>"
                        data-mov-doc-tipo="<?= h($docTipo) ?>"
                        data-mov-doc-numero="<?= h($docNumero) ?>"
                        data-mov-doc-data="<?= h($docData) ?>"
                        data-mov-note="<?= h((string)($m['note'] ?? '')) ?>">
                  <i class="bi bi-pencil-square"></i>
                </button>

                <button type="button"
                        class="btn btn-sm btn-outline-danger of-icon-btn js-del-mov"
                        title="Elimina"
                        data-mov-id="<?= (int)$m['id'] ?>">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php
        $prev = max(1, $page - 1);
        $next = min($pages, $page + 1);
      ?>
      <div class="mt-auto pt-3 d-flex align-items-center justify-content-center">
        <div class="btn-group" role="group" aria-label="Paginazione movimenti">
          <button type="button"
                  class="btn btn-sm btn-outline-secondary rounded-start-pill of-page-btn js-mov-prev"
                  data-page="<?= (int)$prev ?>"
                  <?= ($page <= 1 ? 'disabled' : '') ?>>
            ← Precedente
          </button>

          <span class="btn btn-sm btn-outline-secondary of-page-mid disabled">
            Pagina &nbsp;<b><?= (int)$page ?></b>
          </span>

          <button type="button"
                  class="btn btn-sm btn-outline-secondary rounded-end-pill of-page-btn js-mov-next"
                  data-page="<?= (int)$next ?>"
                  <?= ($page >= $pages ? 'disabled' : '') ?>>
            Successiva →
          </button>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
};

/* =========================
 * POST ACTIONS
 * ======================= */
if ($method === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  try {
    mysqli_begin_transaction($conn);

    // lock lotto
    $resL = mysqli_query($conn, "SELECT id FROM lotti WHERE id=$lid AND prodotto_id=$pid LIMIT 1 FOR UPDATE");
    if (!$resL || mysqli_num_rows($resL) === 0) throw new RuntimeException('Lotto non trovato.');

    // giacenza attuale
    $curQty = 0;
    $resG = mysqli_query($conn, "SELECT COALESCE(SUM(CASE WHEN tipo='CARICO' THEN quantita ELSE -quantita END),0) AS g
                                FROM movimenti WHERE prodotto_id=$pid AND lotto_id=$lid");
    if ($resG && ($gg = mysqli_fetch_assoc($resG))) $curQty = (int)$gg['g'];

    /* ---------- ADD ---------- */
    if ($action === 'add_mov') {
      $tipo = strtoupper(trim((string)($_POST['mov_tipo'] ?? '')));
      $qta  = (int)($_POST['mov_quantita'] ?? 0);

      $ts_mysql = parse_datetime_local_to_mysql((string)($_POST['mov_ts'] ?? ''));

      $prezzo = parse_price_nullable((string)($_POST['mov_prezzo'] ?? ''));
      $fornitore_id = (int)($_POST['mov_fornitore_id'] ?? 0);

      $doc_tipo   = strtoupper(trim((string)($_POST['mov_doc_tipo'] ?? '')));
      $doc_numero = trim((string)($_POST['mov_doc_numero'] ?? ''));
      $doc_data   = trim((string)($_POST['mov_doc_data'] ?? ''));

      $note = trim((string)($_POST['mov_note'] ?? ''));

      if (!in_array($tipo, ['CARICO','SCARICO'], true)) throw new RuntimeException('Tipo movimento non valido.');
      if ($qta <= 0) throw new RuntimeException('Quantità non valida.');

      if ($tipo === 'CARICO') {        if ($doc_tipo !== '' && !in_array($doc_tipo, ['FATTURA','DDT','ALTRO'], true)) throw new RuntimeException('Tipo documento non valido.');
        if ($doc_data !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $doc_data)) throw new RuntimeException('Data documento non valida.');
      } else {
        $fornitore_id = 0;
        $prezzo = null;
        $doc_tipo = '';
        $doc_numero = '';
        $doc_data = '';
      }

      if ($tipo === 'SCARICO' && $curQty < $qta) throw new RuntimeException('Scarico non possibile: quantità lotto insufficiente.');

      $tsE   = "'".mysqli_real_escape_string($conn, $ts_mysql)."'";
      $tipoE = "'".mysqli_real_escape_string($conn, $tipo)."'";
      $noteE = ($note !== '') ? ("'".mysqli_real_escape_string($conn, $note)."'") : "NULL";

      $prezzoE = ($tipo === 'CARICO' && $prezzo !== null)
        ? (string)number_format($prezzo, 2, '.', '')
        : "NULL";
      $fornE = ($tipo === 'CARICO' && $fornitore_id > 0) ? (string)$fornitore_id : "NULL";

      $docTipoE   = ($tipo === 'CARICO' && $doc_tipo   !== '') ? ("'".mysqli_real_escape_string($conn, $doc_tipo)."'") : "NULL";
      $docNumeroE = ($tipo === 'CARICO' && $doc_numero !== '') ? ("'".mysqli_real_escape_string($conn, $doc_numero)."'") : "NULL";
      $docDataE   = ($tipo === 'CARICO' && $doc_data   !== '') ? ("'".mysqli_real_escape_string($conn, $doc_data)."'") : "NULL";

      $sqlIns = "
        INSERT INTO movimenti (ts, tipo, prodotto_id, lotto_id, quantita, prezzo, fornitore_id, operatore_id, note, doc_tipo, doc_numero, doc_data)
        VALUES ($tsE, $tipoE, $pid, $lid, $qta, $prezzoE, $fornE, NULL, $noteE, $docTipoE, $docNumeroE, $docDataE)
      ";
      if (!mysqli_query($conn, $sqlIns)) throw new RuntimeException('Errore inserimento movimento: '.(mysqli_error($conn) ?: 'query failed'));

      mysqli_commit($conn);

      $html = $render_panel($pid, $lid, $page);
      json_out(['ok'=>true, 'html'=>$html, 'refreshSide'=>true]);
    }

    /* ---------- EDIT ---------- */
    if ($action === 'edit_mov') {
      $mov_id = (int)($_POST['mov_id'] ?? 0);
      if ($mov_id <= 0) throw new RuntimeException('Movimento non valido.');

      $ts_mysql = parse_datetime_local_to_mysql((string)($_POST['edit_ts'] ?? ''));

      // lock movimento vecchio
      $resM = mysqli_query($conn, "SELECT id, tipo, quantita
                                  FROM movimenti
                                  WHERE id=$mov_id AND prodotto_id=$pid AND lotto_id=$lid
                                  LIMIT 1 FOR UPDATE");
      if (!$resM || !($old = mysqli_fetch_assoc($resM))) throw new RuntimeException('Movimento non trovato.');

      $oldTipo = (string)$old['tipo'];
      $oldQta  = (int)$old['quantita'];

      $newTipo = strtoupper(trim((string)($_POST['edit_tipo'] ?? '')));
      $newQta  = (int)($_POST['edit_quantita'] ?? 0);

      $newPrezzo = parse_price_nullable((string)($_POST['edit_prezzo'] ?? ''));
      $newForn = (int)($_POST['edit_fornitore_id'] ?? 0);

      $newDocTipo   = strtoupper(trim((string)($_POST['edit_doc_tipo'] ?? '')));
      $newDocNumero = trim((string)($_POST['edit_doc_numero'] ?? ''));
      $newDocData   = trim((string)($_POST['edit_doc_data'] ?? ''));

      $newNote = trim((string)($_POST['edit_note'] ?? ''));

      if (!in_array($newTipo, ['CARICO','SCARICO'], true)) throw new RuntimeException('Tipo movimento non valido.');
      if ($newQta <= 0) throw new RuntimeException('Quantità non valida.');

      if ($newTipo === 'CARICO') {        if ($newDocTipo !== '' && !in_array($newDocTipo, ['FATTURA','DDT','ALTRO'], true)) throw new RuntimeException('Tipo documento non valido.');
        if ($newDocData !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDocData)) throw new RuntimeException('Data documento non valida.');
      } else {
        $newForn = 0;
        $newPrezzo = null;
        $newDocTipo = '';
        $newDocNumero = '';
        $newDocData = '';
      }

      // undo + apply per controllo giacenza
      $qtyAfterUndo = ($oldTipo === 'CARICO') ? ($curQty - $oldQta) : ($curQty + $oldQta);

      if ($newTipo === 'SCARICO' && $qtyAfterUndo < $newQta) throw new RuntimeException('Scarico non possibile: quantità lotto insufficiente.');
      $qtyAfterApply = ($newTipo === 'CARICO') ? ($qtyAfterUndo + $newQta) : ($qtyAfterUndo - $newQta);
      if ($qtyAfterApply < 0) throw new RuntimeException('Errore coerenza: giacenza negativa.');

      $tsE   = "'".mysqli_real_escape_string($conn, $ts_mysql)."'";
      $tipoE = "'".mysqli_real_escape_string($conn, $newTipo)."'";
      $noteE = ($newNote !== '') ? ("'".mysqli_real_escape_string($conn, $newNote)."'") : "NULL";

      $prezzoE = ($newTipo === 'CARICO' && $newPrezzo !== null)
        ? (string)number_format($newPrezzo, 2, '.', '')
        : "NULL";
      $fornE = ($newTipo === 'CARICO' && $newForn > 0) ? (string)$newForn : "NULL";

      $docTipoE   = ($newTipo === 'CARICO' && $newDocTipo   !== '') ? ("'".mysqli_real_escape_string($conn, $newDocTipo)."'") : "NULL";
      $docNumeroE = ($newTipo === 'CARICO' && $newDocNumero !== '') ? ("'".mysqli_real_escape_string($conn, $newDocNumero)."'") : "NULL";
      $docDataE   = ($newTipo === 'CARICO' && $newDocData   !== '') ? ("'".mysqli_real_escape_string($conn, $newDocData)."'") : "NULL";

      $sqlUpdM = "UPDATE movimenti
                  SET ts=$tsE,
                      tipo=$tipoE,
                      quantita=$newQta,
                      prezzo=$prezzoE,
                      fornitore_id=$fornE,
                      note=$noteE,
                      doc_tipo=$docTipoE,
                      doc_numero=$docNumeroE,
                      doc_data=$docDataE
                  WHERE id=$mov_id AND prodotto_id=$pid AND lotto_id=$lid
                  LIMIT 1";
      if (!mysqli_query($conn, $sqlUpdM)) throw new RuntimeException('Errore update movimento: '.(mysqli_error($conn) ?: 'query failed'));

      mysqli_commit($conn);

      $html = $render_panel($pid, $lid, $page);
      json_out(['ok'=>true, 'html'=>$html, 'refreshSide'=>true]);
    }

    /* ---------- DELETE ---------- */
    if ($action === 'del_mov') {
      $mov_id = (int)($_POST['mov_id'] ?? 0);
      if ($mov_id <= 0) throw new RuntimeException('Movimento non valido.');

      $resM = mysqli_query($conn, "SELECT id, tipo, quantita
                                  FROM movimenti
                                  WHERE id=$mov_id AND prodotto_id=$pid AND lotto_id=$lid
                                  LIMIT 1 FOR UPDATE");
      if (!$resM || !($old = mysqli_fetch_assoc($resM))) throw new RuntimeException('Movimento non trovato.');

      $oldTipo = (string)$old['tipo'];
      $oldQta  = (int)$old['quantita'];

      $newQty = ($oldTipo === 'CARICO') ? ($curQty - $oldQta) : ($curQty + $oldQta);
      if ($newQty < 0) throw new RuntimeException('Errore coerenza: giacenza negativa.');

      if (!mysqli_query($conn, "DELETE FROM movimenti WHERE id=$mov_id AND prodotto_id=$pid AND lotto_id=$lid LIMIT 1")) {
        throw new RuntimeException('Errore delete movimento: '.(mysqli_error($conn) ?: 'query failed'));
      }

      mysqli_commit($conn);

      $html = $render_panel($pid, $lid, $page);
      json_out(['ok'=>true, 'html'=>$html, 'refreshSide'=>true]);
    }

    throw new RuntimeException('Azione non valida.');

  } catch (Throwable $e) {
    mysqli_rollback($conn);
    json_out(['ok'=>false, 'html'=>'<div class="alert alert-danger mb-0">'.h($e->getMessage()).'</div>']);
  }
}

/* =========================
 * GET: render panel
 * ======================= */
$html = $render_panel($pid, $lid, $page);
json_out(['ok'=>true, 'html'=>$html]);
