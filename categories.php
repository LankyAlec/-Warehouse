<?php
declare(strict_types=1);
$PAGE_TITLE = 'Categorie';
require __DIR__ . '/header.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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

$rows = [];
$res = mysqli_query($conn, "SELECT id,nome,tipo FROM categorie ORDER BY tipo ASC, nome ASC");
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

$tipi = ['alcolici','analcolici','food','non_food','altro'];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h4 mb-0">Categorie</h1>
    <div class="text-secondary small">Alcolici / Analcolici / Food / Non-food</div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="action" value="add">
      <div class="col-12 col-md-7">
        <label class="form-label">Nome categoria</label>
        <input class="form-control" name="nome" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Tipo</label>
        <select class="form-select" name="tipo">
          <?php foreach ($tipi as $t): ?>
            <option value="<?= h($t) ?>"><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <button class="btn btn-primary w-100">Aggiungi</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-dark">
        <tr>
          <th style="width:80px">ID</th>
          <th>Nome</th>
          <th style="width:160px">Tipo</th>
          <th style="width:140px" class="text-end">Azioni</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="text-center py-5 text-secondary">Nessuna categoria</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td class="text-muted"><?= (int)$r['id'] ?></td>
          <td><?= h($r['nome']) ?></td>
          <td><span class="badge text-bg-secondary"><?= h($r['tipo']) ?></span></td>
          <td class="text-end">
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
</div>

<?php require __DIR__ . '/footer.php'; ?>
