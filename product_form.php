<?php
declare(strict_types=1);
$PAGE_TITLE = 'Prodotto';
require __DIR__ . '/header.php';

$id  = (int)($_GET['id'] ?? 0);
$msg = trim((string)($_GET['msg'] ?? ''));
$err = '';

/** se arrivi da index.php con &lotto_id=... */
$openLottoId = (int)($_GET['lotto_id'] ?? 0);

/** lotto selezionato (master) */
$selectedLottoId = (int)($_GET['lotto_sel'] ?? 0);
if ($openLottoId > 0) $selectedLottoId = $openLottoId;

$lotti_page = (int)($_GET['lotti_page'] ?? 1);
if ($lotti_page < 1) $lotti_page = 1;

$LOTTI_PER_PAGE = 7;


/* =========================
 * HELPERS
 * ======================= */
function money($v): string { return number_format((float)$v, 2, ',', '.'); }

function fmtDateIt(?string $ymd): string {
  $ymd = trim((string)$ymd);
  if ($ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return '—';
  $t = strtotime($ymd);
  return $t ? date('d/m/Y', $t) : '—';
}

function expBadgeClass(?string $ymd): string {
  $ymd = trim((string)$ymd);
  if ($ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return 'of-badge-na';

  $today = date('Y-m-d');
  if ($ymd < $today) return 'of-badge-bad';

  $limit = date('Y-m-d', strtotime('+30 days'));
  if ($ymd <= $limit) return 'of-badge-warn';

  return 'of-badge-ok';
}

/** label leggibile lotto */
function lotto_label(array $l): string {
  $scad = fmtDateIt($l['data_scadenza'] ?? '');
  $scaff = trim((string)($l['scaffale'] ?? ''));
  $ripi  = trim((string)($l['ripiano'] ?? ''));

  $pos = [];
  if ($scaff !== '') $pos[] = "Scaff. $scaff";
  if ($ripi  !== '') $pos[] = "Rip. $ripi";
  $posTxt = $pos ? (' • '.implode(' / ', $pos)) : '';

  return "Scad. $scad".$posTxt;
}

/* =========================
 * UNITÀ
 * ======================= */
$UNITA_OPZ = [
  'pz' => 'pz',
  'kg' => 'kg',
  'g'  => 'g',
  'l'  => 'l',
  'ml' => 'ml',
  'conf' => 'conf.',
  'scat' => 'scat.',
  'bott' => 'bott.'
];

/* =========================
 * BASE ROW PRODOTTO
 * ======================= */
$row = [
  'id' => 0,
  'nome'=>'',
  'descrizione'=>'',
  'categoria_id'=>0,
  'magazzino_id'=>0,
  'unita'=>'pz',
  'attivo'=>1
];

if ($id > 0) {
  $res = mysqli_query($conn, "SELECT * FROM prodotti WHERE id=$id LIMIT 1");
  if ($res && ($db = mysqli_fetch_assoc($res))) $row = array_merge($row, $db);
  else $id = 0;
}

/* =========================
 * SALVA PRODOTTO
 * ======================= */
if (($_POST['action'] ?? '') === 'save') {
  $id = (int)($_POST['id'] ?? 0);

  $row['id'] = $id;
  $row['nome'] = trim((string)($_POST['nome'] ?? ''));
  $row['descrizione'] = trim((string)($_POST['descrizione'] ?? ''));
  $row['categoria_id'] = (int)($_POST['categoria_id'] ?? 0);
  $row['magazzino_id'] = (int)($_POST['magazzino_id'] ?? 0);

  $unita = trim((string)($_POST['unita'] ?? 'pz'));
  $row['unita'] = array_key_exists($unita, $UNITA_OPZ) ? $unita : 'pz';

  if ($row['nome'] === '' || $row['magazzino_id'] <= 0) {
    $err = 'Nome e Magazzino sono obbligatori.';
  } else {
    $nomeE  = "'" . esc($conn, $row['nome']) . "'";
    $descE  = $row['descrizione'] !== '' ? ("'".esc($conn,$row['descrizione'])."'") : "NULL";
    $catE   = ($row['categoria_id'] > 0) ? (string)$row['categoria_id'] : "NULL";
    $magE   = (string)$row['magazzino_id'];
    $unitaE = "'" . esc($conn, $row['unita']) . "'";

    // prodotto pulito: giacenza/scadenza/prezzo non su prodotto
    $quantitaReset = 0;
    $scadReset     = "NULL";
    $prezzoReset   = "0.00";
    $annoReset     = "NULL";
    $scaffReset    = "NULL";
    $ripiReset     = "NULL";

    if ($id > 0) {
      $sql = "UPDATE prodotti SET
        nome=$nomeE,
        descrizione=$descE,
        categoria_id=$catE,
        magazzino_id=$magE,
        unita=$unitaE,
        quantita=$quantitaReset,
        data_scadenza=$scadReset,
        prezzo=$prezzoReset,
        anno_produzione=$annoReset,
        scaffale=$scaffReset,
        ripiano=$ripiReset
      WHERE id=$id LIMIT 1";

      $ok = mysqli_query($conn, $sql);
      if ($ok) redirect('product_form.php?id='.$id.'&msg='.urlencode('Prodotto salvato'));
      $err = 'Errore salvataggio: ' . (mysqli_error($conn) ?: 'query failed');
    } else {
      $sql = "INSERT INTO prodotti
        (nome, descrizione, categoria_id, magazzino_id, unita, quantita, data_scadenza, prezzo, anno_produzione, scaffale, ripiano, attivo)
      VALUES
        ($nomeE, $descE, $catE, $magE, $unitaE, $quantitaReset, $scadReset, $prezzoReset, $annoReset, $scaffReset, $ripiReset, 1)";

      $ok = mysqli_query($conn, $sql);
      if ($ok) {
        $newId = (int)mysqli_insert_id($conn);
        redirect('product_form.php?id='.$newId.'&msg='.urlencode('Prodotto creato. Ora aggiungi i lotti.'));
      }
      $err = 'Errore inserimento: ' . (mysqli_error($conn) ?: 'query failed');
    }
  }
}

/* =========================
 * LOTTI: ADD / EDIT / DELETE
 * ======================= */
if (($_POST['action'] ?? '') === 'add_lotto') {
  $pid = (int)($_POST['id'] ?? 0);

  $scad = trim((string)($_POST['lotto_scadenza'] ?? ''));

  $anno = trim((string)($_POST['lotto_anno'] ?? ''));
  $anno = ($anno !== '' && preg_match('/^\d{4}$/', $anno)) ? (int)$anno : null;

  $scaff = trim((string)($_POST['lotto_scaffale'] ?? ''));
  $ripi  = trim((string)($_POST['lotto_ripiano'] ?? ''));

  if ($pid <= 0) {
    $err = 'Salva prima il prodotto, poi aggiungi i lotti.';
  } elseif ($scad !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scad)) {
    $err = 'Data scadenza lotto non valida.';
  } else {
    // magazzino lotto = magazzino del prodotto (coerenza)
    $resP = mysqli_query($conn, "SELECT magazzino_id FROM prodotti WHERE id=$pid LIMIT 1");
    $magId = 0;
    if ($resP && ($pp = mysqli_fetch_assoc($resP))) $magId = (int)($pp['magazzino_id'] ?? 0);

    $annoE  = ($anno === null) ? "NULL" : (string)$anno;
    $scadE  = ($scad !== '') ? ("'".esc($conn, $scad)."'") : "NULL";
    $scaffE = ($scaff !== '') ? ("'".esc($conn,$scaff)."'") : "NULL";
    $ripiE  = ($ripi  !== '') ? ("'".esc($conn,$ripi )."'") : "NULL";
    $magE   = ($magId > 0) ? (string)$magId : "NULL";

    $sql = "INSERT INTO lotti (prodotto_id, magazzino_id, anno_produzione, data_scadenza, scaffale, ripiano)
            VALUES ($pid, $magE, $annoE, $scadE, $scaffE, $ripiE)";
    $ok = mysqli_query($conn, $sql);
    if ($ok) redirect('product_form.php?id='.$pid.'&msg='.urlencode('Lotto aggiunto'));
    $err = 'Errore inserimento lotto: ' . (mysqli_error($conn) ?: 'query failed');
  }
}

if (($_POST['action'] ?? '') === 'edit_lotto') {
  $pid = (int)($_POST['id'] ?? 0);
  $lid = (int)($_POST['lotto_id'] ?? 0);

  $scad = trim((string)($_POST['edit_scadenza'] ?? ''));

  $anno = trim((string)($_POST['edit_anno'] ?? ''));
  $anno = ($anno !== '' && preg_match('/^\d{4}$/', $anno)) ? (int)$anno : null;

  $scaff = trim((string)($_POST['edit_scaffale'] ?? ''));
  $ripi  = trim((string)($_POST['edit_ripiano'] ?? ''));

  if ($pid <= 0 || $lid <= 0) {
    $err = 'Lotto non valido.';
  } elseif ($scad !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scad)) {
    $err = 'Data scadenza non valida.';
  } else {
    $annoE  = ($anno === null) ? "NULL" : (string)$anno;
    $scadE  = ($scad !== '') ? ("'".esc($conn, $scad)."'") : "NULL";
    $scaffE = ($scaff !== '') ? ("'".esc($conn,$scaff)."'") : "NULL";
    $ripiE  = ($ripi  !== '') ? ("'".esc($conn,$ripi )."'") : "NULL";

    $sql = "UPDATE lotti SET
              anno_produzione=$annoE,
              data_scadenza=$scadE,
              scaffale=$scaffE,
              ripiano=$ripiE
            WHERE id=$lid AND prodotto_id=$pid
            LIMIT 1";
    $ok = mysqli_query($conn, $sql);
    if ($ok) redirect('product_form.php?id='.$pid.'&msg='.urlencode('Lotto modificato'));
    $err = 'Errore modifica lotto: ' . (mysqli_error($conn) ?: 'query failed');
  }
}

if (($_POST['action'] ?? '') === 'del_lotto') {
  $pid = (int)($_POST['id'] ?? 0);
  $lid = (int)($_POST['lotto_id'] ?? 0);

  if ($pid > 0 && $lid > 0) {
    $ok = mysqli_query($conn, "DELETE FROM lotti WHERE id=$lid AND prodotto_id=$pid LIMIT 1");
    if ($ok) redirect('product_form.php?id='.$pid.'&msg='.urlencode('Lotto eliminato'));
    $err = 'Errore eliminazione lotto: ' . (mysqli_error($conn) ?: 'query failed');
  } else $err = 'Lotto non valido.';
}

/* =========================
 * MOVIMENTI
 * (gestiti via AJAX in movimenti_ajax.php)
 * ======================= */

/* =========================
 * LISTE
 * ======================= */
$mag = [];
$res = mysqli_query($conn, "SELECT id, nome FROM magazzini WHERE attivo=1 ORDER BY nome ASC");
while ($res && ($r = mysqli_fetch_assoc($res))) $mag[] = $r;

$cat = [];
$res = mysqli_query($conn, "SELECT id, nome, tipo FROM categorie WHERE attivo=1 ORDER BY tipo ASC, nome ASC");
while ($res && ($r = mysqli_fetch_assoc($res))) $cat[] = $r;

$fornitori = [];
$res = mysqli_query($conn, "SELECT id, nome FROM fornitori WHERE attivo=1 ORDER BY nome ASC");
while ($res && ($r = mysqli_fetch_assoc($res))) $fornitori[] = $r;

/* =========================
 * LOTTI + GIACENZA
 * ======================= */
$lotti = [];
$tot_qta = 0;

$lotti_total = 0;
$lotti_pages = 1;

if ($id > 0) {
  // totale lotti
  $resC = mysqli_query($conn, "SELECT COUNT(*) AS c FROM lotti WHERE prodotto_id=$id");
  if ($resC && ($cc = mysqli_fetch_assoc($resC))) $lotti_total = (int)$cc['c'];

  $lotti_pages = max(1, (int)ceil($lotti_total / $LOTTI_PER_PAGE));
  if ($lotti_page > $lotti_pages) $lotti_page = $lotti_pages;

  $offset = ($lotti_page - 1) * $LOTTI_PER_PAGE;

  // totale giacenza (NON paginato)
  $resT = mysqli_query($conn, "SELECT COALESCE(SUM(CASE WHEN tipo='CARICO' THEN quantita ELSE -quantita END),0) AS s FROM movimenti WHERE prodotto_id=$id");
  if ($resT && ($tt = mysqli_fetch_assoc($resT))) $tot_qta = (int)$tt['s'];

  // lotti paginati
  $sqlL = "SELECT l.id, l.anno_produzione, l.data_scadenza, l.scaffale, l.ripiano,
                  COALESCE(m.giacenza,0) AS giacenza
           FROM lotti l
           LEFT JOIN (
             SELECT lotto_id, SUM(CASE WHEN tipo='CARICO' THEN quantita ELSE -quantita END) AS giacenza
             FROM movimenti
             WHERE prodotto_id=$id
             GROUP BY lotto_id
           ) m ON m.lotto_id = l.id
           WHERE l.prodotto_id=$id
           ORDER BY l.data_scadenza ASC, l.id ASC
           LIMIT $LOTTI_PER_PAGE OFFSET $offset";
  $res = mysqli_query($conn, $sqlL);
  while ($res && ($r = mysqli_fetch_assoc($res))) $lotti[] = $r;
}


// se lotto_sel non esiste più nel DB, reset (non dipende dalla pagina corrente)
if ($selectedLottoId > 0) {
  $chk = mysqli_query($conn, "SELECT 1 FROM lotti WHERE id=$selectedLottoId AND prodotto_id=$id LIMIT 1");
  if (!$chk || mysqli_num_rows($chk) === 0) $selectedLottoId = 0;
}
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div><h1 class="h4 mb-0"><?= $id>0 ? 'Modifica prodotto' : 'Nuovo prodotto' ?></h1></div>
  <a class="btn btn-outline-secondary" href="index.php">← Indietro</a>
</div>

<?php if ($msg !== ''): ?><div id="flashAlert" class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div id="flashAlert" class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

<!-- ANAGRAFICA PRODOTTO -->
<div class="card toolbar-card mb-3">
  <div class="card-body">
    <form method="post" class="row g-3">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

      <div class="col-12 col-md-6">
        <label class="form-label">Magazzino *</label>
        <select class="form-select" name="magazzino_id" required>
          <option value="0">Seleziona...</option>
          <?php foreach ($mag as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= ((int)$row['magazzino_id']===(int)$m['id']?'selected':'') ?>><?= h($m['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Nome *</label>
        <input class="form-control" name="nome" required value="<?= h($row['nome']) ?>">
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Unità</label>
        <select class="form-select" name="unita">
          <?php foreach ($UNITA_OPZ as $k=>$label): ?>
            <option value="<?= h($k) ?>" <?= ($row['unita']===$k?'selected':'') ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Categoria</label>
        <select class="form-select" name="categoria_id">
          <option value="0">—</option>
          <?php foreach ($cat as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)$row['categoria_id']===(int)$c['id']?'selected':'') ?>>
              <?= h($c['tipo']) ?> • <?= h($c['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label">Descrizione</label>
        <textarea class="form-control" name="descrizione" rows="2"><?= h($row['descrizione']) ?></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary">Salva</button>
        <a class="btn btn-outline-secondary" href="index.php">Annulla</a>
      </div>
    </form>
  </div>
</div>

<?php if ($id <= 0): ?>
  <div class="alert alert-warning">Salva prima il prodotto, poi potrai aggiungere lotti e movimenti.</div>
<?php else: ?>

<div class="row g-3">
  <!-- LOTTI (SINISTRA) -->
  <div class="col-12 col-xl-6">
    <div class="card table-card h-100 d-flex flex-column">
      <div class="card-body d-flex flex-column flex-grow-1">
        <div class="d-flex align-items-start justify-content-between mb-3 js-lotti-header">
          <div>
            <div class="fw-semibold fs-5">Lotti</div>
          </div>
          <button class="btn btn-primary btn-sm of-top-action-btn" type="button" data-bs-toggle="modal" data-bs-target="#addLottoModal">
            <i class="bi bi-plus-lg"></i> Lotto
          </button>
        </div>

        <!-- Stile allineato ai movimenti: blocco interno con border + table + pager -->
        <div class="border rounded p-3 h-100 d-flex flex-column">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="fw-semibold">Storico</div>
            <div class="text-secondary small">
              Pagina <b><?= (int)$lotti_page ?></b> / <b><?= (int)$lotti_pages ?></b> • Giacenza: <b><?= (int)$tot_qta ?></b> <?= h($row['unita']) ?></b>
            </div>
          </div>
          <div class="table-responsive js-lotti-table flex-grow-1">
            <table class="table table-sm align-middle mb-0">
              <thead class="text-secondary">
                <tr>
                  <th>Scadenza</th>
                  <th>Scaffale</th>
                  <th>Ripiano</th>
                  <th class="text-end">Giacenza</th>
                  <th class="text-end">Azioni</th>
                </tr>
              </thead>
              <tbody>
              <?php if (!$lotti): ?>
                <tr><td colspan="5" class="text-center py-4 text-secondary">Nessun lotto inserito</td></tr>
              <?php else: foreach ($lotti as $l): ?>
                <?php
                  $lid = (int)$l['id'];
                  $scaff = trim((string)($l['scaffale'] ?? '')); if ($scaff === '') $scaff = '—';
                  $ripi  = trim((string)($l['ripiano'] ?? ''));  if ($ripi  === '') $ripi  = '—';
                  $badgeClass = expBadgeClass((string)($l['data_scadenza'] ?? ''));
                  $scadIt = fmtDateIt((string)($l['data_scadenza'] ?? ''));
                  $giacenza = (int)($l['giacenza'] ?? 0);
                  $isSel = ($selectedLottoId > 0 && $selectedLottoId === $lid);

                  // badge bootstrap "soft" come movimenti
                  $expMap = [
                    'of-badge-ok'   => 'text-bg-success',
                    'of-badge-warn' => 'text-bg-warning',
                    'of-badge-bad'  => 'text-bg-danger',
                    'of-badge-na'   => 'text-bg-secondary',
                  ];
                  $expCls = $expMap[$badgeClass] ?? $expMap['of-badge-na'];
?>
                <tr class="js-lotto-row <?= $isSel ? 'table-primary' : '' ?>" data-lotto-id="<?= $lid ?>">
                  <td><span class="badge <?= h($expCls) ?>"><?= h($scadIt) ?></span></td>
                  <td><?= h($scaff) ?></td>
                  <td><?= h($ripi) ?></td>
                  <td class="text-end"><b><?= (int)$giacenza ?></b> <?= h($row['unita']) ?></td>
                  <td class="text-end">
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary of-icon-btn js-select-lotto"
                            data-lotto-id="<?= $lid ?>"
                            title="Apri movimenti lotto" aria-label="Apri movimenti lotto">
                      <i class="bi bi-box-arrow-in-right"></i>
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-outline-primary of-icon-btn btn-edit-lotto"
                            data-bs-toggle="modal"
                            data-bs-target="#editLottoModal"
                            title="Modifica lotto"
                            aria-label="Modifica lotto"
                            data-lotto-id="<?= $lid ?>"
                            data-scad="<?= h((string)($l['data_scadenza'] ?? '')) ?>"
                            data-anno="<?= h((string)($l['anno_produzione'] ?? '')) ?>"
                            data-scaff="<?= h((string)($l['scaffale'] ?? '')) ?>"
                            data-ripi="<?= h((string)($l['ripiano'] ?? '')) ?>">
                      <i class="bi bi-pencil-square"></i>
                    </button>

                    <form method="post" class="d-inline" onsubmit="return confirm('Eliminare questo lotto?');">
                      <input type="hidden" name="action" value="del_lotto">
                      <input type="hidden" name="id" value="<?= (int)$id ?>">
                      <input type="hidden" name="lotto_id" value="<?= $lid ?>">
                      <button class="btn btn-sm btn-outline-danger of-icon-btn"
                              title="Elimina lotto" aria-label="Elimina lotto">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($lotti_total > 0): ?>
            <div class="mt-auto pt-3 d-flex align-items-center justify-content-center js-lotti-pager">
              <?php
                $prev = max(1, $lotti_page - 1);
                $next = min($lotti_pages, $lotti_page + 1);
              ?>
              <div class="btn-group" role="group" aria-label="Paginazione lotti">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary rounded-start-pill of-page-btn js-lotti-prev"
                        <?= ($lotti_page <= 1 ? 'disabled' : '') ?>
                        data-page="<?= (int)$prev ?>">
                  ← Precedente
                </button>

                <span class="btn btn-sm btn-outline-secondary of-page-mid disabled">
                  Pagina &nbsp<b><?= (int)$lotti_page ?></b>
                </span>

                <button type="button"
                        class="btn btn-sm btn-outline-secondary rounded-end-pill of-page-btn js-lotti-next"
                        <?= ($lotti_page >= $lotti_pages ? 'disabled' : '') ?>
                        data-page="<?= (int)$next ?>">
                  Successiva →
                </button>
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>

  <!-- MOVIMENTI (DESTRA) -->
  <div class="col-12 col-xl-6">
  <div class="card table-card h-100 d-flex flex-column">
    <div class="card-body d-flex flex-column flex-grow-1">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="fw-semibold fs-5">Movimenti</div>
        <button type="button" id="btnAddMov" class="btn btn-primary btn-sm of-top-action-btn" disabled>
          <i class="bi bi-plus-lg"></i> Movimento
        </button>
      </div>

      <div class="text-secondary small mb-0"></div>

      <!-- ✅ QUESTO È IL PANNELLO CHE RIEMPIAMO VIA AJAX -->
      <div id="movimentiPanel" class="of-mov-panel d-flex flex-column flex-grow-1">
        <div id="movimentiEmpty"
             class="border rounded p-4 text-center text-muted d-flex flex-column justify-content-center align-items-center flex-grow-1">
          Seleziona un lotto per registrare movimenti e vedere lo storico.
        </div>
      </div>

      <div id="movimentiContent" class="d-none">
        <!-- qui dentro renderizzi tabella/form movimenti -->
        <div id="movimentiTableWrap"></div>
      </div>
    </div>
  </div>
</div>
</div>

<?php endif; ?>

<!-- MODAL ADD LOTTO -->
<div class="modal fade" id="addLottoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="add_lotto">
      <input type="hidden" name="id" value="<?= (int)$id ?>">

      <div class="modal-header">
        <h5 class="modal-title">Aggiungi lotto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>

      
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-12 col-md-4">
            <label class="form-label">Scadenza</label>
            <input type="date" class="form-control" name="lotto_scadenza">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Anno prod.</label>
            <input class="form-control" name="lotto_anno" inputmode="numeric" placeholder="es. 2024">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Magazzino</label>
            <input class="form-control" value="<?= h((string)($row['magazzino_id'] ?? '')) ?>" disabled>
            <div class="form-text">Il magazzino del lotto segue il magazzino del prodotto.</div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Scaffale</label>
            <input class="form-control" name="lotto_scaffale">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Ripiano</label>
            <input class="form-control" name="lotto_ripiano">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button class="btn btn-primary">Aggiungi lotto</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT LOTTO -->
<div class="modal fade" id="editLottoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content" onsubmit="return confirm('Confermi la modifica del lotto?');">
      <input type="hidden" name="action" value="edit_lotto">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <input type="hidden" name="lotto_id" id="edit_lotto_id" value="">

      <div class="modal-header">
        <h5 class="modal-title">Modifica lotto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>

      
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-12 col-md-6">
            <label class="form-label">Anno prod.</label>
            <input class="form-control" name="edit_anno" id="edit_anno" placeholder="es. 2024">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Scadenza</label>
            <input type="date" class="form-control" name="edit_scadenza" id="edit_scadenza">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Scaffale</label>
            <input class="form-control" name="edit_scaffale" id="edit_scaffale">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Ripiano</label>
            <input class="form-control" name="edit_ripiano" id="edit_ripiano">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>


<!-- MODAL NUOVO MOVIMENTO -->
<div class="modal fade" id="newMovModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="newMovForm" class="modal-content" autocomplete="off">
      <input type="hidden" name="action" value="add_mov">
      <input type="hidden" name="id" id="new_mov_pid" value="<?= (int)$id ?>">
      <input type="hidden" name="lotto_id" id="new_mov_lid" value="">
      <input type="hidden" name="page" id="new_mov_page" value="1">

      <div class="modal-header">
        <h5 class="modal-title">Aggiungi movimento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>

      
      <div class="modal-body">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Tipo</label>
            <select class="form-select" name="mov_tipo" id="new_mov_tipo" required>
              <option value="CARICO">CARICO</option>
              <option value="SCARICO">SCARICO</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Quantità</label>
            <input type="number" class="form-control" name="mov_quantita" id="new_mov_quantita" min="1" step="1" required>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Data e ora</label>
            <input type="datetime-local" class="form-control" name="mov_ts" id="new_mov_ts" required>
          </div>

          <div class="col-12 col-md-4 of-only-carico-new">
            <label class="form-label mb-1">Prezzo unitario</label>
            <input class="form-control" name="mov_prezzo" id="new_mov_prezzo" inputmode="decimal" placeholder="es. 12.50">
          </div>

          <div class="col-12 col-md-6 of-only-carico-new">
            <label class="form-label mb-1">Fornitore</label>
            <select class="form-select" name="mov_fornitore_id" id="new_mov_fornitore_id">
              <option value="0">—</option>
              <?php foreach ($fornitori as $f): ?>
                <option value="<?= (int)$f['id'] ?>"><?= h((string)$f['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-3 of-only-carico-new">
            <label class="form-label mb-1">Doc. tipo</label>
            <select class="form-select" name="mov_doc_tipo" id="new_mov_doc_tipo">
              <option value="">—</option>
              <option value="FATTURA">FATTURA</option>
              <option value="DDT">DDT</option>
              <option value="ALTRO">ALTRO</option>
            </select>
          </div>

          <div class="col-12 col-md-3 of-only-carico-new">
            <label class="form-label mb-1">Doc. numero</label>
            <input class="form-control" name="mov_doc_numero" id="new_mov_doc_numero" placeholder="es. 123/2026">
          </div>

          <div class="col-12 col-md-3 of-only-carico-new">
            <label class="form-label mb-1">Doc. data</label>
            <input type="date" class="form-control" name="mov_doc_data" id="new_mov_doc_data">
          </div>

          <div class="col-12">
            <label class="form-label mb-1">Note</label>
            <input class="form-control" name="mov_note" id="new_mov_note" placeholder="es. reso, inventario, rottura...">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button class="btn btn-success">Registra movimento</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL DETTAGLIO MOVIMENTO -->
<div class="modal fade" id="viewMovModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Dettaglio movimento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div id="viewMovAlert" class="alert alert-primary mb-0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL MODIFICA MOVIMENTO -->
<div class="modal fade" id="editMovModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="editMovForm" class="modal-content">
      <input type="hidden" name="action" value="edit_mov">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <input type="hidden" name="lotto_id" id="edit_mov_lid" value="">
      <input type="hidden" name="page" id="edit_mov_page" value="1">
      <input type="hidden" name="mov_id" id="edit_mov_id" value="">

      <div class="modal-header">
        <h5 class="modal-title">Modifica movimento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>

      
      <div class="modal-body">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Tipo</label>
            <select class="form-select" name="edit_tipo" id="edit_tipo" required>
              <option value="CARICO">CARICO</option>
              <option value="SCARICO">SCARICO</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Quantità</label>
            <input type="number" class="form-control" name="edit_quantita" id="edit_quantita" min="1" step="1" required>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label mb-1">Data e ora</label>
            <input type="datetime-local" class="form-control" name="edit_ts" id="edit_ts" required>
          </div>

          <div class="col-12 col-md-4 of-only-carico-edit">
            <label class="form-label mb-1">Prezzo unitario</label>
            <input class="form-control" name="edit_prezzo" id="edit_prezzo" inputmode="decimal" placeholder="es. 12.50">
          </div>

          <div class="col-12 col-md-4 of-only-carico-edit">
            <label class="form-label mb-1">Fornitore</label>
            <select class="form-select" name="edit_fornitore_id" id="edit_fornitore_id">
              <option value="0">—</option>
              <?php foreach ($fornitori as $f): ?>
                <option value="<?= (int)$f['id'] ?>"><?= h((string)$f['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-4 of-only-carico-edit">
            <label class="form-label mb-1">Doc. tipo</label>
            <select class="form-select" name="edit_doc_tipo" id="edit_doc_tipo">
              <option value="">—</option>
              <option value="FATTURA">FATTURA</option>
              <option value="DDT">DDT</option>
              <option value="ALTRO">ALTRO</option>
            </select>
          </div>

          <div class="col-12 col-md-4 of-only-carico-edit">
            <label class="form-label mb-1">Doc. numero</label>
            <input class="form-control" name="edit_doc_numero" id="edit_doc_numero" placeholder="es. 123/2026">
          </div>

          <div class="col-12 col-md-4 of-only-carico-edit">
            <label class="form-label mb-1">Doc. data</label>
            <input type="date" class="form-control" name="edit_doc_data" id="edit_doc_data">
          </div>

          <div class="col-12">
            <label class="form-label mb-1">Note</label>
            <input class="form-control" name="edit_note" id="edit_note" placeholder="es. reso, inventario, rottura...">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>

<style>
  .of-icon-btn{
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
  }

  
  .of-top-action-btn{
    height: 42px;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding-left: .9rem;
    padding-right: .9rem;
  }

  .of-page-btn{
    height: 38px;
    display: inline-flex;
    align-items: center;
  }
  .of-page-mid{
    height: 38px;

    border-left: 0 !important;
    border-right: 0 !important;
    border-radius: 0 !important;
    pointer-events: none;
    display: inline-flex;
    align-items: center;
  }
.of-badge{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: .35rem .7rem;
    border-radius: 999px;
    font-weight: 800;
    min-width: 110px;
  }
  .of-badge-ok   { background: rgba(25,135,84,.12);   color:#198754; }
  .of-badge-warn { background: rgba(255,193,7,.20);   color:#b58100; }
  .of-badge-bad  { background: rgba(220,53,69,.14);   color:#dc3545; }
  .of-badge-na   { background: rgba(108,117,125,.12); color:#6c757d; }

  /* stabilizza altezza pannello movimenti */
  .of-mov-panel { min-height: 520px; }

  .border.rounded.p-3 .table { margin-bottom: 0; }
</style>


<script>
(function () {
  const lottoSelect = document.getElementById('lottoSelect');
  const rowSelector = 'tr.js-lotto-row[data-lotto-id]';
  const panel = document.getElementById('movimentiPanel');
  const emptyBox = document.getElementById('movimentiEmpty');
  const btnAddMov = document.getElementById('btnAddMov');

  const newMovModalEl  = document.getElementById('newMovModal');
  const editMovModalEl = document.getElementById('editMovModal');
  const viewMovModalEl = document.getElementById('viewMovModal');

  const urlParams = new URLSearchParams(window.location.search);
  const prodottoId = urlParams.get('id') || '0';

  // Flash message auto-hide (5s)
  const flash = document.getElementById('flashAlert');
  if (flash) {
    setTimeout(() => {
      flash.classList.add('fade');
      flash.classList.remove('show');
      flash.style.transition = 'opacity .35s ease';
      flash.style.opacity = '0';
      setTimeout(() => flash.remove(), 400);
    }, 5000);
  }

  // Popola modal modifica lotto
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-edit-lotto');
    if (!btn) return;

    const idEl = document.getElementById('edit_lotto_id');
    const scadEl = document.getElementById('edit_scadenza');
    const annoEl = document.getElementById('edit_anno');
    const scaffEl = document.getElementById('edit_scaffale');
    const ripiEl = document.getElementById('edit_ripiano');

    if (idEl) idEl.value = btn.dataset.lottoId || '';
    if (scadEl) scadEl.value = btn.dataset.scad || '';
    if (annoEl) annoEl.value = btn.dataset.anno || '';
    if (scaffEl) scaffEl.value = btn.dataset.scaff || '';
    if (ripiEl) ripiEl.value = btn.dataset.ripi || '';
  });

  let currentPage = 1;
  let currentLottiPage = parseInt(urlParams.get('lotti_page') || '1', 10) || 1;
  let currentLottoId = (urlParams.get('lotto_sel') || (lottoSelect && lottoSelect.value) || '0');

  const escHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  function nowForDateTimeLocal() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function htmlToText(html) {
    try {
      const div = document.createElement('div');
      div.innerHTML = String(html || '');
      return (div.textContent || div.innerText || '').trim() || 'Operazione non riuscita';
    } catch (e) {
      return 'Operazione non riuscita';
    }
  }

  function setUrlState(params = {}) {
    try {
      const u = new URL(window.location.href);
      Object.entries(params).forEach(([k,v]) => {
        if (v === null || v === undefined || v === '' || v === '0') u.searchParams.delete(k);
        else u.searchParams.set(k, String(v));
      });
      history.replaceState({}, '', u.toString());
    } catch (e) {}
  }

  function highlightRowByLottoId(lottoId) {
    document.querySelectorAll(rowSelector).forEach(tr => {
      tr.classList.toggle('table-primary', tr.dataset.lottoId === String(lottoId));
    });
  }

  function setSelectToLottoId(lottoId) {
    if (!lottoSelect) return;
    const opt = lottoSelect.querySelector(`option[value="${lottoId}"]`);
    if (opt) lottoSelect.value = String(lottoId);
  }

  function cleanupModalArtifacts() {
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
  }

  function hideModalAndWait(modalEl) {
    return new Promise(resolve => {
      if (!modalEl || !window.bootstrap) { cleanupModalArtifacts(); return resolve(); }
      const inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modalEl.addEventListener('hidden.bs.modal', () => { cleanupModalArtifacts(); resolve(); }, { once: true });
      inst.hide();
      setTimeout(() => { cleanupModalArtifacts(); resolve(); }, 500);
    });
  }

  function toggleCaricoFieldsNew() {
    const tipo = document.getElementById('new_mov_tipo');
    if (!tipo) return;
    const isCarico = (tipo.value === 'CARICO');

    document.querySelectorAll('.of-only-carico-new').forEach(el => el.classList.toggle('d-none', !isCarico));
    if (!isCarico) {
      const forn = document.getElementById('new_mov_fornitore_id');
      const prezzo = document.getElementById('new_mov_prezzo');
      const dt = document.getElementById('new_mov_doc_tipo');
      const dn = document.getElementById('new_mov_doc_numero');
      const dd = document.getElementById('new_mov_doc_data');
      if (forn) forn.value = '0';
      if (prezzo) prezzo.value = '';
      if (dt) dt.value = '';
      if (dn) dn.value = '';
      if (dd) dd.value = '';
    }
  }

  function toggleCaricoFieldsEdit() {
    const tipo = document.getElementById('edit_tipo');
    if (!tipo) return;
    const isCarico = (tipo.value === 'CARICO');

    document.querySelectorAll('.of-only-carico-edit').forEach(el => el.classList.toggle('d-none', !isCarico));
    if (!isCarico) {
      const forn = document.getElementById('edit_fornitore_id');
      const prezzo = document.getElementById('edit_prezzo');
      const dt = document.getElementById('edit_doc_tipo');
      const dn = document.getElementById('edit_doc_numero');
      const dd = document.getElementById('edit_doc_data');
      if (forn) forn.value = '0';
      if (prezzo) prezzo.value = '';
      if (dt) dt.value = '';
      if (dn) dn.value = '';
      if (dd) dd.value = '';
    }
  }

  function openNewMovModal() {
    if (!currentLottoId || currentLottoId === '0' || !window.bootstrap) return;

    document.getElementById('new_mov_lid').value = String(currentLottoId);
    document.getElementById('new_mov_page').value = String(currentPage);

    // reset campi
    document.getElementById('new_mov_tipo').value = 'CARICO';
    document.getElementById('new_mov_quantita').value = '';
    document.getElementById('new_mov_prezzo').value = '';
    document.getElementById('new_mov_fornitore_id').value = '0';
    document.getElementById('new_mov_doc_tipo').value = '';
    document.getElementById('new_mov_doc_numero').value = '';
    document.getElementById('new_mov_doc_data').value = '';
    document.getElementById('new_mov_note').value = '';
    const tsEl = document.getElementById('new_mov_ts');
    if (tsEl) tsEl.value = nowForDateTimeLocal();

    toggleCaricoFieldsNew();

    const inst = bootstrap.Modal.getOrCreateInstance(newMovModalEl);
    inst.show();

    setTimeout(() => {
      const q = document.getElementById('new_mov_quantita');
      if (q) q.focus();
    }, 150);
  }

  async function refreshSideFromPage(lottoId, lottiPage = currentLottiPage) {
    try {
      lottiPage = parseInt(lottiPage, 10) || 1;
      if (lottiPage < 1) lottiPage = 1;
      currentLottiPage = lottiPage;

      const u = new URL(window.location.href);
      u.searchParams.set('lotti_page', String(lottiPage));
      if (lottoId && lottoId !== '0') u.searchParams.set('lotto_sel', String(lottoId));

      const res = await fetch(u.toString(), { headers: { 'Accept': 'text/html' } });
      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');

      const newHeader = doc.querySelector('.js-lotti-header');
      const oldHeader = document.querySelector('.js-lotti-header');
      if (newHeader && oldHeader) oldHeader.innerHTML = newHeader.innerHTML;

      const newWrap = doc.querySelector('.js-lotti-table');
      const oldWrap = document.querySelector('.js-lotti-table');
      if (newWrap && oldWrap) oldWrap.innerHTML = newWrap.innerHTML;

      // aggiorna anche la paginazione lotti (altrimenti i bottoni restano "stale")
      const newPager = doc.querySelector('.js-lotti-pager');
      const oldPager = document.querySelector('.js-lotti-pager');
      if (newPager && oldPager) oldPager.innerHTML = newPager.innerHTML;

      const newSelect = doc.querySelector('#lottoSelect');
      if (newSelect && lottoSelect) {
        lottoSelect.innerHTML = newSelect.innerHTML;
        setSelectToLottoId(lottoId);
      }

      if (lottoId && lottoId !== '0') currentLottoId = String(lottoId);
      highlightRowByLottoId(lottoId);
      setUrlState({ lotti_page: lottiPage, lotto_sel: lottoId });
    } catch (e) {
      console.warn('refreshSideFromPage fallito', e);
    }
  }

  async function refreshMovimenti(lottoId, page = 1) {
    lottoId = String(lottoId || '').trim();
    page = parseInt(page, 10) || 1;
    if (page < 1) page = 1;

    currentPage = page;
    currentLottoId = lottoId;

    if (!lottoId || lottoId === '0') {
      if (btnAddMov) btnAddMov.disabled = true;
      if (panel && emptyBox) {
        panel.innerHTML = '';
        panel.appendChild(emptyBox);
        emptyBox.classList.remove('d-none');
      }
      return;
    }

    if (panel) panel.innerHTML = '<div class="text-muted small">Caricamento...</div>';

    try {
      const res = await fetch(
        `movimenti_ajax.php?id=${encodeURIComponent(prodottoId)}&lotto_id=${encodeURIComponent(lottoId)}&page=${encodeURIComponent(page)}`,
        { headers: { 'Accept': 'application/json' } }
      );

      const text = await res.text();
      let json;
      try { json = JSON.parse(text); }
      catch { throw new Error('Risposta non JSON: ' + text.slice(0, 200)); }

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      if (!json.ok) {
        if (panel) panel.innerHTML = json.html || '<div class="alert alert-danger mb-0">Errore</div>';
        return;
      }

      if (panel) panel.innerHTML = json.html;

      if (btnAddMov) btnAddMov.disabled = false;

      // auto-chiudi alert lotto dopo 5s (se presente)
      try {
        const al = panel.querySelector('.js-lotto-alert');
        if (al && window.bootstrap) {
          setTimeout(() => {
            try { bootstrap.Alert.getOrCreateInstance(al).close(); } catch(e) {}
          }, 5000);
        }
      } catch(e) {}

      cleanupModalArtifacts();
    } catch (err) {
      console.error(err);
      if (panel) panel.innerHTML = `<div class="alert alert-danger mb-0">Errore: ${escHtml(String(err.message || err))}</div>`;
      cleanupModalArtifacts();
    }
  }

  // selezione lotto dal select
  if (lottoSelect) {
    lottoSelect.addEventListener('change', () => {
      const id = lottoSelect.value;
      currentLottoId = id;
      setUrlState({ lotto_sel: id });
      highlightRowByLottoId(id);
      refreshMovimenti(id, 1);
    });
  }

  // click icona su riga lotto -> apri movimenti
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-select-lotto');
    if (!btn) return;

    const lottoId = btn.dataset.lottoId;
    currentLottoId = lottoId;
    setUrlState({ lotto_sel: lottoId });
    setSelectToLottoId(lottoId);
    highlightRowByLottoId(lottoId);
    refreshMovimenti(lottoId, 1);
  });

  // click "+ Aggiungi movimento" (header)
  if (btnAddMov) {
    btnAddMov.addEventListener('click', () => openNewMovModal());
  }

  // change tipo (nuovo/edit)
  document.addEventListener('change', (e) => {
    if (e.target && e.target.id === 'new_mov_tipo') toggleCaricoFieldsNew();
    if (e.target && e.target.id === 'edit_tipo') toggleCaricoFieldsEdit();
  });

  // Dettaglio movimento
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-view-mov');
    if (!btn) return;

    const ts   = btn.dataset.movTs || '—';
    const tipo = (btn.dataset.movTipo || '—').toUpperCase();
    const qta  = btn.dataset.movQta || '—';
    const un   = btn.dataset.movUnita || '';
    const prezzo = (btn.dataset.movPrezzo || '').trim();
    const fornNome = (btn.dataset.movFornNome || '').trim();
    const docTipo = (btn.dataset.movDocTipo || '').trim();
    const docNumero = (btn.dataset.movDocNumero || '').trim();
    const docData = (btn.dataset.movDocData || '').trim();
    const note = (btn.dataset.movNote || '').trim();

const showOrDash = (v) => {
  v = (v ?? '').toString().trim();
  return v !== '' ? escHtml(v) : '—';
};

const lines = [];
lines.push(`<div><b>Data:</b> ${showOrDash(ts)}</div>`);
lines.push(`<div><b>Tipo:</b> ${showOrDash(tipo)}</div>`);
lines.push(`<div><b>Quantità:</b> ${showOrDash(String(qta))} ${escHtml(un)}</div>`);

// Per CARICO mostriamo SEMPRE prezzo/fornitore/documento (con placeholder)
if (tipo === 'CARICO') {
  lines.push(`<div><b>Prezzo unitario:</b> ${showOrDash(prezzo)} €</div>`);
  lines.push(`<div><b>Fornitore:</b> ${showOrDash(fornNome)}</div>`);
  const docTxt = [docTipo, docNumero, docData ? ('del ' + docData) : ''].filter(Boolean).join(' ');
  lines.push(`<div><b>Documento:</b> ${showOrDash(docTxt)}</div>`);
}

// Note sempre (con placeholder)
lines.push(`<div><b>Note:</b> ${showOrDash(note)}</div>`);

const alertEl = document.getElementById('viewMovAlert');
if (alertEl) {
  alertEl.classList.remove('alert-danger','alert-success');
  if (tipo === 'SCARICO') alertEl.classList.add('alert-danger');
  else if (tipo === 'CARICO') alertEl.classList.add('alert-success');
  else alertEl.classList.add('alert-primary');
  alertEl.innerHTML = lines.join('');
}

    if (!viewMovModalEl || !window.bootstrap) return;
    bootstrap.Modal.getOrCreateInstance(viewMovModalEl).show();
  });

  // apri modal edit e popola campi
  document.addEventListener('click', (e) => {
    const btnEdit = e.target.closest('.js-edit-mov');
    if (!btnEdit) return;

    document.getElementById('edit_mov_lid').value = String(currentLottoId || '0');
    document.getElementById('edit_mov_page').value = String(currentPage);

    document.getElementById('edit_mov_id').value = btnEdit.dataset.movId || '';
    document.getElementById('edit_tipo').value = btnEdit.dataset.movTipo || 'CARICO';
    document.getElementById('edit_quantita').value = btnEdit.dataset.movQta || '1';
    document.getElementById('edit_prezzo').value = btnEdit.dataset.movPrezzo || '';
    document.getElementById('edit_fornitore_id').value = btnEdit.dataset.movForn || '0';
    document.getElementById('edit_doc_tipo').value = btnEdit.dataset.movDocTipo || '';
    document.getElementById('edit_doc_numero').value = btnEdit.dataset.movDocNumero || '';
    document.getElementById('edit_doc_data').value = btnEdit.dataset.movDocData || '';
    document.getElementById('edit_note').value = btnEdit.dataset.movNote || '';
    document.getElementById('edit_ts').value = btnEdit.dataset.movTslocal || nowForDateTimeLocal();

    setTimeout(toggleCaricoFieldsEdit, 0);
  });

  // elimina movimento
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-del-mov');
    if (!btn) return;

    const movId = btn.dataset.movId;
    if (!movId) return;

    if (!confirm('Eliminare questo movimento?')) return;

    const fd = new FormData();
    fd.set('action', 'del_mov');
    fd.set('id', String(prodottoId));
    fd.set('lotto_id', String(currentLottoId));
    fd.set('page', String(currentPage));
    fd.set('mov_id', String(movId));

    try {
      const res = await fetch('movimenti_ajax.php', { method: 'POST', body: fd });
      const t = await res.text();
      let j;
      try { j = JSON.parse(t); } catch { throw new Error('Risposta non JSON: ' + t.slice(0, 200)); }
      if (!j.ok) throw new Error(htmlToText(j.html || j.msg || 'Operazione non riuscita'));

      if (panel) panel.innerHTML = j.html;
      if (j.refreshSide) await refreshSideFromPage(currentLottoId);

      cleanupModalArtifacts();
    } catch (err) {
      alert('Errore: ' + (err.message || err));
      cleanupModalArtifacts();
    }
  });

  // paginazione storico movimenti (prev/next)
  document.addEventListener('click', (e) => {
    const prev = e.target.closest('.js-mov-prev');
    const next = e.target.closest('.js-mov-next');
    if (!prev && !next) return;

    e.preventDefault();
    e.stopPropagation();

    const btn = prev || next;
    if (btn.hasAttribute('disabled')) return;

    const page = parseInt(btn.dataset.page || '1', 10) || 1;
    if (!currentLottoId || currentLottoId === '0') return;

    refreshMovimenti(currentLottoId, page);
  });

  // paginazione LOTTI (sinistra)
  document.addEventListener('click', async (e) => {
    const prev = e.target.closest('.js-lotti-prev');
    const next = e.target.closest('.js-lotti-next');
    if (!prev && !next) return;

    e.preventDefault();
    e.stopPropagation();

    const btn = prev || next;
    if (btn.hasAttribute('disabled')) return;

    const page = parseInt(btn.dataset.page || '1', 10) || 1;
    const lottoId = (currentLottoId && currentLottoId !== '0') ? currentLottoId : (lottoSelect ? lottoSelect.value : '0');

    await refreshSideFromPage(lottoId, page);
  });

  // submit NUOVO MOVIMENTO (modal)
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('#newMovForm');
    if (!form) return;

    e.preventDefault();

    const fd = new FormData(form);
    fd.set('id', String(prodottoId));
    fd.set('lotto_id', String(currentLottoId));
    fd.set('page', String(currentPage));

    const btn = form.querySelector('button[type="submit"], button:not([type])');
    if (btn) btn.disabled = true;

    try {
      const res = await fetch('movimenti_ajax.php', { method: 'POST', body: fd });
      const t = await res.text();

      let j;
      try { j = JSON.parse(t); } catch { throw new Error('Risposta non JSON: ' + t.slice(0, 200)); }
      if (!j.ok) throw new Error(htmlToText(j.html || j.msg || 'Operazione non riuscita'));

      await hideModalAndWait(newMovModalEl);

      if (panel) panel.innerHTML = j.html;
      if (j.refreshSide) await refreshSideFromPage(currentLottoId);

      cleanupModalArtifacts();
    } catch (err) {
      alert('Errore: ' + (err.message || err));
      cleanupModalArtifacts();
    } finally {
      if (btn) btn.disabled = false;
    }
  });

  // submit EDIT MOVIMENTO (modal)
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('#editMovForm');
    if (!form) return;

    e.preventDefault();

    const fd = new FormData(form);
    fd.set('id', String(prodottoId));
    fd.set('lotto_id', String(currentLottoId));
    fd.set('page', String(currentPage));

    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;

    try {
      const res = await fetch('movimenti_ajax.php', { method: 'POST', body: fd });
      const t = await res.text();

      let j;
      try { j = JSON.parse(t); } catch { throw new Error('Risposta non JSON: ' + t.slice(0, 200)); }
      if (!j.ok) throw new Error(htmlToText(j.html || j.msg || 'Operazione non riuscita'));

      await hideModalAndWait(editMovModalEl);

      if (panel) panel.innerHTML = j.html;
      if (j.refreshSide) await refreshSideFromPage(currentLottoId);

      cleanupModalArtifacts();
    } catch (err) {
      alert('Errore: ' + (err.message || err));
      cleanupModalArtifacts();
    } finally {
      if (btn) btn.disabled = false;
    }
  });

  // bootstrap: quando apro il modal nuovo, riallineo campi carico
  if (newMovModalEl) {
    newMovModalEl.addEventListener('shown.bs.modal', toggleCaricoFieldsNew);
  }
  if (editMovModalEl) {
    editMovModalEl.addEventListener('shown.bs.modal', toggleCaricoFieldsEdit);
  }

  // init: se la pagina arriva con lotto selezionato (via GET), carico subito
  const initialLotto = (urlParams.get('lotto_sel') || (lottoSelect && lottoSelect.value) || '0');
  if (initialLotto !== '0') {
    highlightRowByLottoId(initialLotto);
    refreshMovimenti(initialLotto, 1);
  }
})();
</script>




<?php require __DIR__ . '/footer.php'; ?>
