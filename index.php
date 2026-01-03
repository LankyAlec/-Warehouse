<?php
declare(strict_types=1);
$PAGE_TITLE = 'Gestionale Magazzino';
require __DIR__ . '/header.php';

/* =========================
 * INPUT
 * ======================= */
$q            = trim((string)($_GET['q'] ?? ''));
$magazzino_id = (int)($_GET['magazzino_id'] ?? 0);
$categoria_id = (int)($_GET['categoria_id'] ?? 0);
$expiring     = (int)($_GET['expiring'] ?? 0);
$days         = max(0, (int)($_GET['days'] ?? 30));

$per_page = (int)($_GET['per_page'] ?? 25);
$per_page = in_array($per_page, [10, 25, 50, 100], true) ? $per_page : 25;

$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$flash = trim((string)($_GET['msg'] ?? ''));

/* =========================
 * HELPERS
 * ======================= */
function money($v): string { return number_format((float)$v, 2, ',', '.'); }
function date_it(?string $ymd): string {
  $ymd = trim((string)$ymd);
  if ($ymd === '') return '—';
  $ts = strtotime($ymd);
  if (!$ts) return '—';
  return date('d/m/Y', $ts);
}

/* =========================
 * DELETE
 * ======================= */
if (($_POST['action'] ?? '') === 'delete') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    mysqli_query($conn, "DELETE FROM prodotti WHERE id=$id LIMIT 1");
    redirect('index.php?msg=' . urlencode('Prodotto eliminato'));
  }
  redirect('index.php?msg=' . urlencode('ID non valido'));
}

/* =========================
 * LISTE
 * ======================= */
$mag = [];
$res = mysqli_query($conn, "SELECT id, nome FROM magazzini WHERE attivo=1 ORDER BY nome ASC");
while ($res && ($r = mysqli_fetch_assoc($res))) $mag[] = $r;

$cat = [];
$res = mysqli_query($conn, "SELECT id, nome, tipo FROM categorie WHERE attivo=1 ORDER BY tipo ASC, nome ASC");
while ($res && ($r = mysqli_fetch_assoc($res))) $cat[] = $r;

/* =========================
 * WHERE (prodotti + match su lotti)
 * ======================= */
$where = ["p.attivo=1"];

if ($q !== '') {
  $qq = esc($conn, $q);
  $where[] = "(
    p.nome LIKE '%$qq%'
    OR p.descrizione LIKE '%$qq%'
    OR p.unita LIKE '%$qq%'
    OR EXISTS (
      SELECT 1
      FROM lotti lx
      WHERE lx.prodotto_id = p.id
        AND (
          lx.scaffale LIKE '%$qq%'
          OR lx.ripiano LIKE '%$qq%'
        )
    )
  )";
}

if ($magazzino_id > 0) $where[] = "p.magazzino_id=".(int)$magazzino_id;
if ($categoria_id > 0) $where[] = "p.categoria_id=".(int)$categoria_id;

if ($expiring === 1) {
  $where[] = "EXISTS (
    SELECT 1 FROM lotti l2
    WHERE l2.prodotto_id = p.id
      AND l2.data_scadenza IS NOT NULL
      AND l2.data_scadenza <= DATE_ADD(CURDATE(), INTERVAL $days DAY)
  )";
}

$w = 'WHERE ' . implode(' AND ', $where);

/* =========================
 * COUNT (prodotti)
 * ======================= */
$sqlCount = "
SELECT COUNT(DISTINCT p.id) AS n
FROM prodotti p
JOIN magazzini m ON m.id = p.magazzino_id
LEFT JOIN categorie c ON c.id = p.categoria_id
$w
";
$res = mysqli_query($conn, $sqlCount);
$tot = 0;
if ($res && ($r = mysqli_fetch_assoc($res))) $tot = (int)$r['n'];

$total_pages = max(1, (int)ceil($tot / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

/* =========================
 * DATA (prodotti paginati)
 * ======================= */
$sql = "
SELECT
  p.*,
  m.nome AS magazzino_nome,
  c.nome AS categoria_nome,
  c.tipo AS categoria_tipo
FROM prodotti p
JOIN magazzini m ON m.id = p.magazzino_id
LEFT JOIN categorie c ON c.id = p.categoria_id
$w
ORDER BY p.nome ASC
LIMIT $per_page OFFSET $offset
";
$rows = [];
$res = mysqli_query($conn, $sql);
while ($res && ($r = mysqli_fetch_assoc($res))) $rows[] = $r;

/* =========================
 * LOTTI (per i prodotti della pagina)
 * - nuova logica: giacenza da movimenti, prezzo = ultimo prezzo CARICO (se presente)
 * ======================= */
$ids = array_map(fn($x) => (int)$x['id'], $rows);
$lotsByProd = [];

if ($ids) {
  $idList = implode(',', $ids);

  $sqlLots = "
  SELECT
    l.id,
    l.prodotto_id,
    l.scaffale,
    l.ripiano,
    l.data_scadenza,

    COALESCE(SUM(
      CASE
        WHEN mv.tipo='CARICO'  THEN mv.quantita
        WHEN mv.tipo='SCARICO' THEN -mv.quantita
        ELSE 0
      END
    ),0) AS giacenza,

    (
      SELECT mv2.prezzo
      FROM movimenti mv2
      WHERE mv2.lotto_id = l.id
        AND mv2.tipo='CARICO'
        AND mv2.prezzo IS NOT NULL
      ORDER BY mv2.ts DESC, mv2.id DESC
      LIMIT 1
    ) AS ultimo_prezzo

  FROM lotti l
  LEFT JOIN movimenti mv ON mv.lotto_id = l.id
  WHERE l.prodotto_id IN ($idList)
  GROUP BY l.id
  ORDER BY
    (l.data_scadenza IS NULL) ASC,
    l.data_scadenza ASC,
    l.id ASC
  ";
  $res = mysqli_query($conn, $sqlLots);
  while ($res && ($l = mysqli_fetch_assoc($res))) {
    $pid = (int)$l['prodotto_id'];
    $lotsByProd[$pid][] = $l;
  }
}

/* =========================
 * META
 * ======================= */
$from = $tot ? ($offset + 1) : 0;
$to   = min($offset + $per_page, $tot);

/* CSV URL (stessi filtri, NO page) */
$params = $_GET;
unset($params['page']);
$csvUrl = 'export_csv.php?' . http_build_query($params);

/* Pagination URLs */
$prev = max(1, $page - 1);
$next = min($total_pages, $page + 1);

$baseParams = $_GET;
$baseParams['page'] = $prev;
$prevUrl = 'index.php?' . http_build_query($baseParams);

$baseParams['page'] = $next;
$nextUrl = 'index.php?' . http_build_query($baseParams);
?>

<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h3 mb-0">Gestionale Magazzino</h1>
    <div class="text-secondary">Gestione prodotti, lotti, scadenze e posizioni</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-primary" href="product_form.php">
      <i class="bi bi-plus-lg me-1"></i> Nuovo prodotto
    </a>
  </div>
</div>

<?php if ($flash !== ''): ?>
  <div id="flashAlert" class="alert alert-info"><?= h($flash) ?></div>
<?php endif; ?>

<div class="card toolbar-card mb-3 border-0 shadow-sm">
  <div class="card-body">
    <form id="filterForm" class="row g-3 align-items-end" method="get" action="index.php">

      <div class="col-12 col-lg-4">
        <label class="form-label">Cerca</label>
        <input class="form-control" name="q" id="q"
               value="<?= h($q) ?>"
               placeholder="Nome, descrizione, scaffale/ripiano lotto, unità...">
      </div>

      <div class="col-12 col-lg-4">
        <label class="form-label">Magazzino</label>
        <select class="form-select" name="magazzino_id" id="magazzino_id">
          <option value="0">Tutti</option>
          <?php foreach ($mag as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= ((int)$m['id'] === $magazzino_id ? 'selected' : '') ?>>
              <?= h($m['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-lg-4">
        <label class="form-label">Categoria</label>
        <select class="form-select" name="categoria_id" id="categoria_id">
          <option value="0">Tutte</option>
          <?php foreach ($cat as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $categoria_id ? 'selected' : '') ?>>
              <?= h($c['tipo']) ?> • <?= h($c['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-lg-4">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" value="1" id="expiring" name="expiring" <?= ($expiring === 1 ? 'checked' : '') ?>>
          <label class="form-check-label" for="expiring">Solo in scadenza</label>
        </div>
        <div class="input-group">
          <span class="input-group-text">Entro</span>
          <input type="number" class="form-control" name="days" id="days" min="0" value="<?= (int)$days ?>">
          <span class="input-group-text">giorni</span>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <label class="form-label">Record per pagina</label>
        <select class="form-select" name="per_page" id="per_page">
          <?php foreach ([10, 25, 50, 100] as $pp): ?>
            <option value="<?= $pp ?>" <?= ($pp === $per_page ? 'selected' : '') ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <input type="hidden" name="page" id="page" value="<?= (int)$page ?>">
    </form>
  </div>
</div>

<!-- META: record + download + visualizzati -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
  <div class="d-inline-flex align-items-center gap-2 of-recbar">
    <span class="text-secondary">Record:</span>
    <span class="fw-semibold" id="totRec"><?= (int)$tot ?></span>

    <a class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
       id="csvLink"
       href="<?= h($csvUrl) ?>"
       title="Scarica CSV dei record filtrati"
       aria-label="Scarica CSV">
      <i class="bi bi-download"></i>
      <span class="d-none d-sm-inline">CSV</span>
    </a>
  </div>

  <div class="text-secondary">
    Visualizzati: <strong><span id="fromRec"><?= (int)$from ?></span>–<span id="toRec"><?= (int)$to ?></span></strong>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0 of-table">
      <thead>
        <tr>
          <th>Prodotto</th>
          <th style="width:240px">Categoria</th>
          <th style="width:220px">Magazzino</th>

          <th style="min-width:560px" class="text-center">
            Lotti
            <div class="small text-secondary">Clicca una riga lotto per modificarla</div>

            <!-- intestazione lotti: UNA SOLA VOLTA -->
            <div class="of-lots-head mt-2">
              <span>Qtà</span>
              <span>Prezzo</span>
              <span>Scaffale</span>
              <span>Ripiano</span>
              <span class="text-end">Scadenza</span>
            </div>
          </th>

          <th style="width:170px" class="text-end">Azioni</th>
        </tr>
      </thead>

      <tbody id="rowsTbody">
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="text-center py-5 text-secondary">Nessun risultato</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php
          $pid = (int)$r['id'];
          $catTxt = !empty($r['categoria_nome']) ? ($r['categoria_tipo'].' • '.$r['categoria_nome']) : '—';
          $lots = $lotsByProd[$pid] ?? [];

          $totQty = 0;
          foreach ($lots as $l) $totQty += (int)($l['giacenza'] ?? 0);
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h($r['nome']) ?></div>

            <?php if (!empty($r['descrizione'])): ?>
              <div class="small text-secondary"><?= h($r['descrizione']) ?></div>
            <?php endif; ?>

            <div class="small text-secondary mt-1">
              Totale: <strong><?= (int)$totQty ?></strong> <?= h($r['unita']) ?>
              <?php if (count($lots) > 1): ?>
                <div class="mt-1">
                  <span class="badge text-bg-light border of-lots-badge">Lotti: <?= count($lots) ?></span>
                </div>
              <?php endif; ?>
            </div>
          </td>

          <td><?= h($catTxt) ?></td>
          <td><?= h($r['magazzino_nome']) ?></td>

          <td>
            <?php if (!$lots): ?>
              <span class="text-secondary">— nessun lotto —</span>
            <?php else: ?>
              <div class="of-lots">
                <?php foreach ($lots as $l): ?>
                  <?php
                    $lottoId = (int)$l['id'];
                    $qty = (int)($l['giacenza'] ?? 0);

                    $scaff = trim((string)($l['scaffale'] ?? ''));
                    $ripi  = trim((string)($l['ripiano'] ?? ''));
                    if ($scaff === '') $scaff = '—';
                    if ($ripi  === '') $ripi  = '—';

                    $prezzo = ((float)($l['ultimo_prezzo'] ?? 0) > 0) ? ('€ '.money($l['ultimo_prezzo'])) : '—';

                    $today = date('Y-m-d');
                    $rawScad = !empty($l['data_scadenza']) ? (string)$l['data_scadenza'] : '';
                    if ($rawScad === '') {
                      $badge = 'of-exp-na';
                      $scadLabel = '—';
                    } else {
                      $isExpired = ($rawScad < $today);
                      $badge = $isExpired ? 'of-exp-bad' : 'of-exp-ok';
                      $scadLabel = date_it($rawScad);
                    }
                  ?>

                  <a class="of-lotrow"
                     href="product_form.php?id=<?= $pid ?>&lotto_id=<?= $lottoId ?>#movimenti"
                     title="Modifica lotto">
                    <span class="fw-semibold"><?= $qty ?> <?= h($r['unita']) ?></span>
                    <span><?= h($prezzo) ?></span>
                    <span><?= h($scaff) ?></span>
                    <span><?= h($ripi) ?></span>
                    <span class="text-end">
                      <span class="of-exp <?= $badge ?>"><?= h($scadLabel) ?></span>
                    </span>
                  </a>

                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>

          <td class="text-end">
            <!-- MODIFICA -->
            <a href="product_form.php?id=<?= $pid ?>"
               class="btn btn-sm btn-outline-primary of-icon-btn"
               title="Modifica prodotto"
               aria-label="Modifica">
              <i class="bi bi-pencil-square"></i>
            </a>

            <!-- ELIMINA -->
            <form class="d-inline"
                  method="post"
                  action="index.php"
                  onsubmit="return confirm('Eliminare il prodotto?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $pid ?>">
              <button type="submit"
                      class="btn btn-sm btn-outline-danger of-icon-btn"
                      title="Elimina prodotto"
                      aria-label="Elimina">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>

        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="paginationWrap" class="d-flex justify-content-center mt-3">
  <div class="btn-group" role="group" aria-label="Paginazione">
    <a class="btn btn-outline-secondary rounded-start-pill <?= ($page <= 1 ? 'disabled' : '') ?>"
       href="<?= h($prevUrl) ?>">← Precedente</a>

    <span class="btn btn-outline-secondary rounded-0 disabled">
      Pagina <?= (int)$page ?> / <?= (int)$total_pages ?>
    </span>

    <a class="btn btn-outline-secondary rounded-end-pill <?= ($page >= $total_pages ? 'disabled' : '') ?>"
       href="<?= h($nextUrl) ?>">Successiva →</a>
  </div>
</div>

<style>
  .of-table thead th{
    font-weight: 800;
    border-bottom: 1px solid rgba(0,0,0,.08);
    background: #fff;
    vertical-align: bottom;
  }

  .of-recbar .btn{ border-radius: .75rem; }

  .of-lots { display: grid; gap: .35rem; }

  /* header lotti (una sola volta) */
  .of-lots-head{
    display: grid;
    grid-template-columns: 1.1fr .9fr .9fr .9fr 1fr;
    gap: .5rem;
    padding: .35rem .6rem;
    font-weight: 800;
    color: #6c757d;
    background: rgba(0,0,0,.02);
    border: 1px solid rgba(0,0,0,.06);
    border-radius: .75rem;
  }

  .of-lots-badge{
    display: inline-block;
    padding: .35rem .6rem;
    border-radius: 999px;
    font-weight: 800;
  }

  .of-lotrow{
    display: grid;
    grid-template-columns: 1.1fr .9fr .9fr .9fr 1fr;
    gap: .5rem;
    align-items: center;
    padding: .48rem .6rem;
    border: 1px solid rgba(0,0,0,.08);
    border-radius: .75rem;
    background: #fff;
    text-decoration: none;
    transition: all .12s ease;
    box-shadow: 0 1px 0 rgba(0,0,0,.02);
  }
  .of-lotrow:hover{
    background: rgba(13,110,253,.04);
    border-color: rgba(13,110,253,.25);
  }

  .of-exp{
    padding: .18rem .55rem;
    border-radius: 999px;
    font-weight: 900;
    font-size: .85rem;
    display: inline-block;
    min-width: 98px;
    text-align: center;
  }
  .of-exp-ok  { background: rgba(25,135,84,.12);  color:#198754; }
  .of-exp-bad { background: rgba(220,53,69,.14);  color:#dc3545; }
  .of-exp-na  { background: rgba(108,117,125,.12); color:#6c757d; }

  @media (max-width: 992px){
    .of-lots-head, .of-lotrow{
      grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
    }
  }
</style>

<script>
(() => {
  const form   = document.getElementById('filterForm');
  const q      = document.getElementById('q');
  const pageEl = document.getElementById('page');

  const tbody  = document.getElementById('rowsTbody');
  const totEl  = document.getElementById('totRec');
  const fromEl = document.getElementById('fromRec');
  const toEl   = document.getElementById('toRec');
  const pagWrap= document.getElementById('paginationWrap');
  const csvLink= document.getElementById('csvLink');

  const buildQuery = () => new URLSearchParams(new FormData(form));

  let t = null;
  let lastController = null;

  async function refresh() {
    if (lastController) lastController.abort();
    lastController = new AbortController();

    const qs  = buildQuery();
    const url = 'rows_ajax.php?' + qs.toString();

    try {
      const res = await fetch(url, { signal: lastController.signal });
      const txt = await res.text();

      let j;
      try { j = JSON.parse(txt); }
      catch (e) {
        console.error('rows_ajax.php non JSON. Status=', res.status, 'Body=', txt);
        return;
      }

      if (!j.ok) {
        console.error('rows_ajax.php ok=false:', j);
        return;
      }

      tbody.innerHTML  = j.rows_html;
      totEl.textContent= j.tot;
      fromEl.textContent= j.from;
      toEl.textContent = j.to;
      pagWrap.innerHTML= j.pagination_html;

      // aggiorna URL browser (senza reload)
      const newUrl = 'index.php?' + qs.toString();
      window.history.replaceState({}, '', newUrl);

      // aggiorna CSV (stessi filtri, NO page)
      const qsCsv = new URLSearchParams(qs.toString());
      qsCsv.delete('page');
      csvLink.href = 'export_csv.php?' + qsCsv.toString();
    } catch (e) {
      if (e.name !== 'AbortError') console.error(e);
    }
  }

  function debounceRefresh() {
    clearTimeout(t);
    t = setTimeout(() => {
      pageEl.value = '1';
      refresh();
    }, 250);
  }

  q.addEventListener('input', debounceRefresh);

  ['magazzino_id','categoria_id','per_page','expiring','days'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('change', () => {
      pageEl.value = '1';
      refresh();
    });
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    pageEl.value = '1';
    refresh();
  });

  document.addEventListener('click', (e) => {
    const a = e.target.closest('#paginationWrap a');
    if (!a) return;

    if (a.classList.contains('disabled') || a.getAttribute('aria-disabled') === 'true') {
      e.preventDefault();
      return;
    }

    e.preventDefault();
    const u = new URL(a.href);
    pageEl.value = u.searchParams.get('page') || '1';
    refresh();
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
