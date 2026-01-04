<?php
declare(strict_types=1);
require __DIR__ . '/init.php';

$PAGE_TITLE = 'Fornitori';

$fornitori = [];
$res = mysqli_query($conn, "SELECT id, nome, attivo FROM fornitori ORDER BY nome ASC");
while ($res && ($r = mysqli_fetch_assoc($res))) $fornitori[] = $r;

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h4 mb-0">Fornitori</h1>
    <div class="text-secondary small">Anagrafica fornitori disponibili nei movimenti di carico</div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-dark">
        <tr>
          <th style="width:80px">ID</th>
          <th>Nome</th>
          <th style="width:140px" class="text-end">Stato</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$fornitori): ?>
        <tr><td colspan="3" class="text-center py-4 text-secondary">Nessun fornitore disponibile</td></tr>
      <?php else: foreach ($fornitori as $f): ?>
        <tr>
          <td class="text-muted"><?= (int)$f['id'] ?></td>
          <td><?= h($f['nome']) ?></td>
          <td class="text-end">
            <?php if ((int)($f['attivo'] ?? 0) === 1): ?>
              <span class="badge bg-success-subtle text-success">Attivo</span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary">Disattivo</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
