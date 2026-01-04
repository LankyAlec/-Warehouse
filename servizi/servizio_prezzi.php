<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$id = (int)($_GET['id'] ?? 0);
$editTariffaId = (int)($_GET['edit'] ?? 0);

if ($id <= 0) {
  die("ID servizio non valido.");
}

/* Flash */
$flash_ok  = $_SESSION['flash_ok'] ?? '';
$flash_err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* Carico servizio */
$stmt = $mysqli->prepare("SELECT * FROM servizi WHERE id=? LIMIT 1");
if (!$stmt) die("Errore DB: " . $mysqli->error);
$stmt->bind_param("i", $id);
$stmt->execute();
$servizio = $stmt->get_result()->fetch_assoc();

if (!$servizio) die("Servizio non trovato.");

/* ✅ Regola: tariffe SOLO sul genitore */
if (!empty($servizio['parent_id'])) {
  $_SESSION['flash_err'] = "Questo servizio è un componente: le tariffe si gestiscono sul genitore.";
  header("Location: servizio_prezzi.php?id=" . (int)$servizio['parent_id']);
  exit;
}

/* Step extra (solo se non illimitato) */
$stepExtra = (int)($servizio['step_extra_min'] ?? 0);
$illimitato = (int)($servizio['slot_illimitato'] ?? 0) === 1;

/* Tariffe esistenti */
$tariffe = [];
$stmt = $mysqli->prepare("SELECT * FROM servizi_tariffe WHERE servizio_id=? ORDER BY dal DESC, id DESC");
if (!$stmt) die("Errore DB: " . $mysqli->error);
$stmt->bind_param("i", $id);
$stmt->execute();
$tariffe = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Se sto editando una tariffa, la pre-carico nel form */
$editing = null;
if ($editTariffaId > 0) {
  $stmt = $mysqli->prepare("SELECT * FROM servizi_tariffe WHERE id=? AND servizio_id=? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param("ii", $editTariffaId, $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
  }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <h2 class="mb-0">Tariffe servizio</h2>
    <div class="text-muted">
      <strong><?= h($servizio['nome']) ?></strong>
      <?php if ($illimitato): ?>
        — <span class="badge bg-info text-dark">Tempo illimitato</span>
      <?php else: ?>
        — extra a step da <strong><?= $stepExtra ?> min</strong>
      <?php endif; ?>
    </div>
    <div class="text-muted small mt-1">
      Prezzi sempre <strong>per persona</strong>. Periodi <strong>senza sovrapposizioni</strong>.
    </div>
  </div>

  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="servizio_edit.php?id=<?= (int)$id ?>">← Torna al servizio</a>
    <a class="btn btn-outline-primary" href="servizi.php">
      <i class="bi bi-list"></i> Vai ai servizi
    </a>
  </div>
</div>

<?php if ($flash_ok): ?>
  <div class="alert alert-success"><?= h($flash_ok) ?></div>
<?php endif; ?>
<?php if ($flash_err): ?>
  <div class="alert alert-danger"><?= h($flash_err) ?></div>
<?php endif; ?>

<div class="row g-3">
  <!-- Nuova tariffa -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <h4 class="mb-1"><?= $editing ? 'Modifica tariffa' : 'Nuova tariffa' ?></h4>
        <div class="text-muted small mb-3">
          Puoi lasciare “Al” vuoto per una tariffa senza scadenza.
        </div>

        <form method="post" action="servizio_prezzi_save.php" class="row g-3">
          <input type="hidden" name="azione" value="<?= $editing ? 'update' : 'insert' ?>">
          <input type="hidden" name="servizio_id" value="<?= (int)$id ?>">
          <input type="hidden" name="tariffa_id" value="<?= (int)($editing['id'] ?? 0) ?>">

          <div class="col-6">
            <label class="form-label">Dal</label>
            <input type="date" class="form-control" name="dal" required
                   value="<?= h($editing['dal'] ?? '') ?>">
          </div>

          <div class="col-6">
            <label class="form-label">Al</label>
            <input type="date" class="form-control" name="al"
                   value="<?= h($editing['al'] ?? '') ?>">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" id="senza_scadenza">
              <label class="form-check-label" for="senza_scadenza">Senza scadenza</label>
            </div>
          </div>

          <div class="col-6">
            <label class="form-label">Prezzo slot (€) <span class="text-muted small">(per persona)</span></label>
            <input type="number" class="form-control" name="prezzo_slot" min="0" step="0.01" inputmode="decimal" required value="<?= h($editing['prezzo_slot'] ?? '0.00') ?>">
          </div>

          <div class="col-6">
            <label class="form-label">Prezzo extra (€) <span class="text-muted small">(per persona)</span></label>
            <input type="number" class="form-control" name="prezzo_extra" min="0" step="0.01" inputmode="decimal" required value="<?= h($editing['prezzo_extra'] ?? '0.00') ?>" required>
            <?php if (!$illimitato): ?>
              <div class="form-text">per step (<?= (int)$stepExtra ?> min)</div>
            <?php else: ?>
              <div class="form-text text-muted">Extra-time non usato (tempo illimitato)</div>
            <?php endif; ?>
          </div>

          <div class="col-12">
            <label class="form-label">Note (opzionale)</label>
            <input type="text" class="form-control" name="note" value="<?= h($editing['note'] ?? '') ?>">
          </div>

          <div class="col-12 d-flex justify-content-between align-items-center">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="attiva"
                     name="attiva" value="1" <?= ((int)($editing['attiva'] ?? 1) === 1 ? 'checked' : '') ?>>
              <label class="form-check-label" for="attiva">Tariffa attiva</label>
            </div>

            <div class="d-flex gap-2">
              <?php if ($editing): ?>
                <a class="btn btn-outline-secondary" href="servizio_prezzi.php?id=<?= (int)$id ?>">Annulla</a>
              <?php endif; ?>
              <button class="btn btn-primary">
                <i class="bi bi-save"></i> Salva
              </button>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>

  <!-- Tariffe esistenti -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <h4 class="mb-3">Tariffe esistenti</h4>

        <?php if (empty($tariffe)): ?>
          <div class="alert alert-info mb-0">Nessuna tariffa inserita.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Periodo</th>
                  <th class="text-end">Slot</th>
                  <th class="text-end">Extra</th>
                  <th>Stato</th>
                  <th class="text-end">Azioni</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tariffe as $t): ?>
                  <tr>
                    <td>
                      <strong><?= h($t['dal']) ?></strong>
                      →
                      <?php if (empty($t['al'])): ?>
                        <span class="badge bg-info text-dark">Senza scadenza</span>
                      <?php else: ?>
                        <strong><?= h($t['al']) ?></strong>
                      <?php endif; ?>
                    </td>

                    <td class="text-end">€ <?= number_format((float)$t['prezzo_slot'], 2, ',', '.') ?></td>
                    <td class="text-end">€ <?= number_format((float)$t['prezzo_extra'], 2, ',', '.') ?></td>

                    <td>
                      <?php if ((int)$t['attiva'] === 1): ?>
                        <span class="badge bg-success">Attiva</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">Non attiva</span>
                      <?php endif; ?>
                    </td>

                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary"
                         href="servizio_prezzi.php?id=<?= (int)$id ?>&edit=<?= (int)$t['id'] ?>">
                        <i class="bi bi-pencil"></i> Modifica
                      </a>

                      <form class="d-inline" method="post" action="servizio_prezzi_save.php"
                            onsubmit="return confirm('Eliminare questa tariffa?');">
                        <input type="hidden" name="azione" value="delete">
                        <input type="hidden" name="servizio_id" value="<?= (int)$id ?>">
                        <input type="hidden" name="tariffa_id" value="<?= (int)$t['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const chk = document.getElementById('senza_scadenza');
  const al  = document.querySelector('input[name="al"]');
  if(!chk || !al) return;

  function sync(){
    if(chk.checked){
      al.value = '';
      al.disabled = true;
    } else {
      al.disabled = false;
    }
  }

  chk.addEventListener('change', sync);

  // se "al" è vuoto e sto editando una tariffa senza scadenza → check automatico
  if(al.value === ''){
    chk.checked = true;
  }
  sync();
})();

document.querySelector('form')?.addEventListener('submit', function(e){
  const slot  = document.querySelector('[name="prezzo_slot"]');
  const extra = document.querySelector('[name="prezzo_extra"]');

  const re = /^\d+(\.\d{1,2})?$/;

  if (!re.test(slot.value)) {
    alert('Prezzo slot non valido. Usa solo numeri (es. 30.00)');
    slot.focus();
    e.preventDefault();
    return;
  }

  if (!re.test(extra.value)) {
    alert('Prezzo extra non valido. Usa solo numeri (es. 10.50)');
    extra.focus();
    e.preventDefault();
    return;
  }
});

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
