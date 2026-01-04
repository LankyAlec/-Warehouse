<?php
// gestione magazzini (CRUD minimo per multi-magazzino)
declare(strict_types=1);
require __DIR__ . '/init.php';

$PAGE_TITLE = 'Gestione magazzini';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
  $nome = trim((string)($_POST['nome'] ?? ''));
  $note = trim((string)($_POST['note'] ?? ''));
  if ($nome === '') {
    flash_set('danger', 'Nome obbligatorio');
    mag_redirect('gestione_magazzini.php');
  }
  try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO magazzini (nome, note) VALUES (:n, :note)");
    $stmt->execute([':n' => $nome, ':note' => ($note !== '' ? $note : null)]);
    flash_set('success', 'Magazzino salvato');
  } catch (Throwable $e) {
    error_log("magazzini.php add: ".$e->getMessage());
    flash_set('danger', 'Errore (controlla error_log PHP).');
  }
  mag_redirect('gestione_magazzini.php');
}

if (isset($_GET['del'])) {
  $id = qint($_GET['del'], 0);
  if ($id > 0) {
    try {
      $stmt = $pdo->prepare("DELETE FROM magazzini WHERE id=:id LIMIT 1");
      $stmt->execute([':id' => $id]);
      flash_set('success', 'Magazzino eliminato');
    } catch (Throwable $e) {
      error_log("magazzini.php del: ".$e->getMessage());
      flash_set('danger', 'Errore eliminazione (probabile magazzino usato da prodotti).');
    }
  }
  mag_redirect('gestione_magazzini.php');
}

$rows = $pdo->query("SELECT id, nome, note FROM magazzini ORDER BY nome")->fetchAll();

$flash = flash_take();

require __DIR__ . '/../includes/header.php';
?>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <div class="fw-semibold">Magazzini</div>
    <a class="btn btn-outline-secondary btn-sm" href="magazzini.php">← Indietro</a>
  </div>
  <div class="card-body">
    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
    <?php endif; ?>
    <form method="post" class="row g-2 align-items-end mb-3">
      <div class="col-12 col-md-4">
        <label class="form-label">Nome</label>
        <input class="form-control" name="nome" required>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Note</label>
        <input class="form-control" name="note">
      </div>
      <div class="col-12 col-md-2">
        <button class="btn btn-primary w-100" name="add" value="1">Aggiungi</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-dark">
          <tr>
            <th style="width:80px">ID</th>
            <th>Nome</th>
            <th>Note</th>
            <th style="width:140px" class="text-end">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="text-muted"><?= (int)$r['id'] ?></td>
              <td><?= h($r['nome']) ?></td>
              <td><?= $r['note'] ? h($r['note']) : '—' ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-danger"
                   href="magazzini.php?del=<?= (int)$r['id'] ?>"
                   onclick="return confirm('Eliminare magazzino?');">Elimina</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="4" class="text-center text-secondary py-4">Nessun magazzino</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="small text-secondary">
      Nota: se un magazzino è usato da prodotti, l'eliminazione può fallire (vincoli DB).
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
