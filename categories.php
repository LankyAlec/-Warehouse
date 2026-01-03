<?php
declare(strict_types=1);
$PAGE_TITLE = 'Categorie';
require __DIR__ . '/header.php';

$q       = trim((string)($_GET['q'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$tipi = ['alcolici','analcolici','food','non_food','altro'];
$editId   = (int)($_GET['edit'] ?? 0);
$editRow  = null;

if (($_POST['action'] ?? '') === 'add') {
  $nome = trim((string)($_POST['nome'] ?? ''));
  $tipo = trim((string)($_POST['tipo'] ?? 'altro'));
  if ($nome !== '') {
    $nomeE = "'" . esc($conn, $nome) . "'";
    $tipoE = "'" . esc($conn, $tipo) . "'";
    mysqli_query($conn, "INSERT IGNORE INTO categorie (nome,tipo) VALUES ($nomeE,$tipoE)");
  }
  redirect('categories.php');
}

if (($_POST['action'] ?? '') === 'del') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) mysqli_query($conn, "DELETE FROM categorie WHERE id=$id LIMIT 1");
  redirect('categories.php');
}

if (($_POST['action'] ?? '') === 'edit') {
  $id   = (int)($_POST['id'] ?? 0);
  $nome = trim((string)($_POST['nome'] ?? ''));
  $tipo = trim((string)($_POST['tipo'] ?? 'altro'));
  if ($id > 0 && $nome !== '') {
    $nomeE = "'" . esc($conn, $nome) . "'";
    $tipoE = "'" . esc($conn, $tipo) . "'";
    mysqli_query($conn, "UPDATE categorie SET nome=$nomeE, tipo=$tipoE WHERE id=$id LIMIT 1");
  }
  redirect('categories.php?page=' . $page . '&q=' . urlencode($q));
}

if ($editId > 0) {
  $res = mysqli_query($conn, "SELECT id, nome, tipo FROM categorie WHERE id=$editId");
  if ($res && ($r = mysqli_fetch_assoc($res))) $editRow = $r;
}

$where = ["1=1"];
if ($q !== '') {
  $qq = esc($conn, $q);
  $where[] = "(c.nome LIKE '%$qq%' OR c.tipo LIKE '%$qq%')";
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$total = 0;
$res = mysqli_query($conn, "SELECT COUNT(*) AS n FROM categorie c $whereSql");
if ($res && ($r = mysqli_fetch_assoc($res))) $total = (int)$r['n'];

$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $perPage; }

$rows = [];
$sql = "
SELECT
  c.id,
  c.nome,
  c.tipo,
  COALESCE(p.n_prodotti, 0) AS n_prodotti
FROM categorie c
LEFT JOIN (
  SELECT categoria_id, COUNT(DISTINCT id) AS n_prodotti
  FROM prodotti
  WHERE attivo=1
  GROUP BY categoria_id
) p ON p.categoria_id = c.id
$whereSql
ORDER BY c.tipo ASC, c.nome ASC
LIMIT $perPage OFFSET $offset
";
$res = mysqli_query($conn, $sql);
while ($res && ($r = mysqli_fetch_assoc($res))) $rows[] = $r;
?>
<?php $from = $total ? ($offset + 1) : 0; $to = min($offset + $perPage, $total); ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Categorie</h1>
    <div class="text-secondary small">Gestisci categorie e prodotti associati, in linea con il resto del gestionale.</div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-primary-subtle text-primary py-2 px-3">Categorie totali: <?= $total ?></span>
    <span class="badge bg-secondary-subtle text-secondary py-2 px-3">Pagina <?= $page ?> / <?= $pages ?></span>
  </div>
</div>

<div class="card toolbar-card mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-12 col-lg-7">
        <form class="row g-2 align-items-end" method="post">
          <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
          <?php if ($editRow): ?>
            <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
          <?php endif; ?>
          <div class="col-12 col-md-6">
            <label class="form-label">Nome categoria</label>
            <input class="form-control" name="nome" value="<?= h($editRow['nome'] ?? '') ?>" required>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="tipo">
              <?php foreach ($tipi as $t): ?>
                <option value="<?= h($t) ?>" <?= ($editRow['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= h($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-primary w-100"><?= $editRow ? 'Salva' : 'Aggiungi' ?></button>
            <?php if ($editRow): ?>
              <a class="btn btn-outline-secondary" href="categories.php">Annulla</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <div class="col-12 col-lg-5">
        <form class="row g-2 align-items-end" method="get" id="filterForm">
          <input type="hidden" name="page" value="1">
          <div class="col-12">
            <label class="form-label">Cerca</label>
            <input class="form-control" name="q" id="searchInput" value="<?= h($q) ?>" placeholder="Nome o tipo...">
          </div>
          <?php if ($q !== ''): ?>
            <div class="col-auto">
              <label class="form-label d-block">&nbsp;</label>
              <a class="btn btn-link text-decoration-none" href="categories.php">Azzera</a>
            </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card table-card">
  <div class="d-flex justify-content-between align-items-center px-3 pt-3">
    <div class="text-secondary small">Visualizzati: <?= $from ?>–<?= $to ?> di <?= $total ?></div>
    <div class="text-secondary small">Mostra <?= $perPage ?> per pagina</div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Categoria</th>
          <th style="width:180px">Tipo</th>
          <th style="width:200px">Prodotti associati</th>
          <th style="width:200px" class="text-end">Azioni</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="text-center py-5 text-secondary">Nessuna categoria trovata</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= h($r['nome']) ?></td>
          <td><span class="badge bg-primary-subtle text-primary"><?= h($r['tipo']) ?></span></td>
          <td>
            <?php if ((int)$r['n_prodotti'] === 0): ?>
              <span class="text-secondary">0 prodotti</span>
            <?php else: ?>
              <span class="badge bg-success-subtle text-success"><?= (int)$r['n_prodotti'] ?> prodotti</span>
            <?php endif; ?>
          </td>
          <td class="text-end d-flex justify-content-end gap-2">
            <a class="btn btn-sm btn-outline-primary" href="categories.php?edit=<?= (int)$r['id'] ?>&page=<?= $page ?>&q=<?= urlencode($q) ?>">Modifica</a>
            <form class="d-inline" method="post" onsubmit="return confirm('Eliminare la categoria?');">
              <input type="hidden" name="action" value="del">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger">Elimina</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
      <div class="text-secondary small">Pagina <?= $page ?> di <?= $pages ?></div>
      <nav>
        <ul class="pagination mb-0">
          <?php
          $qsBase = 'q=' . urlencode($q);
          $prev = max(1, $page - 1);
          $next = min($pages, $page + 1);
          ?>
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="categories.php?<?= $qsBase ?>&page=<?= $prev ?>" aria-label="Precedente">&laquo;</a>
          </li>
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
              <a class="page-link" href="categories.php?<?= $qsBase ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
            <a class="page-link" href="categories.php?<?= $qsBase ?>&page=<?= $next ?>" aria-label="Successiva">&raquo;</a>
          </li>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
</div>

<script>
(() => {
  const form = document.getElementById('filterForm');
  const searchInput = document.getElementById('searchInput');
  if (!form || !searchInput) return;
  let t = null;
  const submit = () => form.submit();
  searchInput.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(submit, 350);
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
