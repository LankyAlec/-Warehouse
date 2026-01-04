<?php
require_once __DIR__ . '/../includes/header.php';

if (!$isRoot && !in_gruppo('Reception')) {
    redirect('/dashboard.php');
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0">Calendario prenotazioni</h3>
    <div class="text-muted small">Vista calendario in arrivo. Usa l'elenco per operazioni immediate.</div>
  </div>
  <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/prenotazioni/lista.php">
    <i class="bi bi-list"></i> Vai all'elenco
  </a>
</div>

<div class="alert alert-info">
  Stiamo lavorando alla vista calendario. Nel frattempo puoi utilizzare l'elenco prenotazioni con salvataggio inline
  e controlli di conflitto già attivi.
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
