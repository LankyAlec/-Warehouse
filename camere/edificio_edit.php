<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$id = (int)($_GET['id'] ?? 0);

/* Flash */
$flashErr = $_SESSION['flash_err'] ?? '';
$flashOld = $_SESSION['flash_old'] ?? null;
unset($_SESSION['flash_err'], $_SESSION['flash_old']);

$servizio = [
    'id' => 0,
    'nome' => '',
    'descrizione' => '',
    'max_persone' => 1,
    'durata_slot_min' => 60,
    'step_extra_min' => 30,
    'attivo' => 1,
    'prenotabile' => 1,
    'slot_illimitato' => 0,
    'parent_id' => null,
    'note' => ''
];

/* Se edit e NON ho old flash, carico dal DB */
if ($id > 0 && empty($flashOld)) {
    $stmt = $mysqli->prepare("SELECT * FROM servizi WHERE id=? LIMIT 1");
    if (!$stmt) die("Prepare failed: " . $mysqli->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) die("Servizio non trovato.");
    $servizio = $row;
}

/* Se ho flash old (errore) lo uso per ripopolare */
if (is_array($flashOld)) {
    // mantiene id se stai modificando
    if ($id > 0) $flashOld['id'] = $id;
    $servizio = array_merge($servizio, $flashOld);
    // parent_id da stringa a null/int coerente
    $servizio['parent_id'] = ($servizio['parent_id'] === '' ? null : (int)$servizio['parent_id']);
}

/* Lista servizi padre */
$sqlParent = "SELECT id, nome FROM servizi WHERE parent_id IS NULL";
if ($id > 0) $sqlParent .= " AND id <> " . (int)$id;
$sqlParent .= " ORDER BY nome ASC";

$parents = [];
$resP = $mysqli->query($sqlParent);
if ($resP) $parents = $resP->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0"><?= ($id > 0 ? 'Modifica servizio' : 'Nuovo servizio') ?></h3>
    <div class="text-muted small">Solo root</div>
  </div>
  <a class="btn btn-outline-secondary" href="servizi.php">← Torna</a>
</div>

<?php if (!empty($flashErr)): ?>
  <div class="alert alert-danger"><?= h($flashErr) ?></div>
<?php endif; ?>

<form method="post" action="servizio_save.php" class="row g-3">
  <input type="hidden" name="azione" value="salva">
  <input type="hidden" name="id" value="<?= (int)($servizio['id'] ?? 0) ?>">

  <div class="col-12 col-lg-7">
    <div class="card shadow-sm border-0">
      <div class="card-body">

        <div class="mb-2">
          <label class="form-label">Nome servizio</label>
          <input class="form-control" name="nome" value="<?= h($servizio['nome'] ?? '') ?>" required>
        </div>

        <div class="row g-2">
          <div class="col-12 col-md-8">
            <label class="form-label">Appartiene a (opzionale)</label>
            <select class="form-select" name="parent_id" id="parent_id">
              <option value="">— Nessuno (servizio principale) —</option>
              <?php foreach ($parents as $p): ?>
                <?php
                  $sel = ((string)($servizio['parent_id'] ?? '') !== '' && (int)$servizio['parent_id'] === (int)$p['id'])
                    ? 'selected' : '';
                ?>
                <option value="<?= (int)$p['id'] ?>" <?= $sel ?>><?= h($p['nome']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">
              Esempio: “Sauna” appartiene a “SPA”. Tempi e tariffe si gestiscono sul genitore.
            </div>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label d-block">Prenotabile</label>
            <div class="form-check form-switch mt-1">
              <input class="form-check-input" type="checkbox" role="switch" id="prenotabile"
                     name="prenotabile" value="1" <?= ((int)($servizio['prenotabile'] ?? 1) === 1 ? 'checked' : '') ?>>
              <label class="form-check-label" for="prenotabile">Prenotabile a calendario</label>
            </div>
            <div class="form-text">I componenti (figli) non sono prenotabili.</div>
          </div>
        </div>

        <div id="msgComponente" class="alert alert-info d-none mt-3">
          Questo servizio è un <strong>componente</strong>: capienza/slot/extra e tariffe si gestiscono sul <strong>genitore</strong>.
        </div>

        <hr class="my-3">

        <div class="mb-2">
          <label class="form-label">Descrizione</label>
          <input class="form-control" name="descrizione" value="<?= h($servizio['descrizione'] ?? '') ?>">
        </div>

        <div class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox" role="switch" id="slot_illimitato"
                 name="slot_illimitato" value="1" <?= ((int)($servizio['slot_illimitato'] ?? 0) === 1 ? 'checked' : '') ?>>
          <label class="form-check-label" for="slot_illimitato">
            Tempo illimitato (nessuno slot)
          </label>
          <div class="form-text">
            Esempio: piscina esterna. In questo caso non si usano durata slot / extra-time.
          </div>
        </div>

        <div id="boxTempi" class="mt-3">

          <div class="row g-2">
            <div class="col-6 col-md-6">
              <label class="form-label">Max persone</label>
              <input class="form-control" type="number" name="max_persone" min="1"
                     value="<?= (int)($servizio['max_persone'] ?? 1) ?>" required>
            </div>

            <div class="col-6 col-md-6">
              <label class="form-label">Durata slot (min)</label>
              <input class="form-control" type="number" name="durata_slot_min" min="15" step="5"
                     value="<?= h($servizio['durata_slot_min'] ?? '') ?>">
            </div>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-12 col-md-4">
              <label class="form-label">Step extra (min)</label>
              <input class="form-control" type="number" name="step_extra_min" min="5" step="5"
                     value="<?= h($servizio['step_extra_min'] ?? '') ?>">
              <div class="form-text">Esempio: 30 = extra a blocchi da 30 minuti.</div>
            </div>
          </div>

        </div>

        <div class="mt-2">
          <label class="form-label">Note</label>
          <textarea class="form-control" name="note" rows="3"><?= h($servizio['note'] ?? '') ?></textarea>
        </div>

        <div class="form-check form-switch mt-3">
          <input class="form-check-input" type="checkbox" role="switch" id="attivo"
                 name="attivo" value="1" <?= ((int)($servizio['attivo'] ?? 1) === 1 ? 'checked' : '') ?>>
          <label class="form-check-label" for="attivo">Servizio attivo</label>
        </div>

        <button class="btn btn-primary w-100 mt-3">
          <i class="bi bi-save"></i> Salva
        </button>

      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <h5 class="mb-2">Prezzi</h5>
        <div class="text-muted small">
          Le tariffe (per persona) sono gestite per periodo. I componenti ereditano tutto dal genitore.
        </div>

        <div class="mt-3">
          <?php if ($id > 0): ?>
            <?php if (!empty($servizio['parent_id'])): ?>
              <a class="btn btn-outline-primary w-100"
                 href="servizio_prezzi.php?id=<?= (int)$servizio['parent_id'] ?>">
                <i class="bi bi-currency-euro"></i> Vai alle tariffe del genitore
              </a>
            <?php else: ?>
              <a class="btn btn-outline-primary w-100"
                 href="servizio_prezzi.php?id=<?= (int)$id ?>">
                <i class="bi bi-currency-euro"></i> Gestisci tariffe
              </a>
            <?php endif; ?>
          <?php else: ?>
            <button class="btn btn-outline-secondary w-100" type="button" disabled>
              Salva prima il servizio per impostare le tariffe
            </button>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>

</form>

<script>
(function(){
  const parentSel = document.getElementById('parent_id');
  const boxTempi  = document.getElementById('boxTempi');
  const pren      = document.getElementById('prenotabile');
  const msg       = document.getElementById('msgComponente');
  const illim     = document.getElementById('slot_illimitato');

  function setDisabled(){
    const isChild = parentSel && parentSel.value !== '';
    const isIllim = illim && illim.checked;

    if (pren){
      if (isChild){
        pren.checked = false;
        pren.disabled = true;
      } else {
        pren.disabled = false;
      }
    }
    if (msg) msg.classList.toggle('d-none', !isChild);

    if (boxTempi){
      boxTempi.querySelectorAll('input, select, textarea').forEach(el => {
        if (el.name === 'max_persone') return;
        el.disabled = (isChild || isIllim);
      });
    }
  }

  if (parentSel) parentSel.addEventListener('change', setDisabled);
  if (illim) illim.addEventListener('change', setDisabled);
  setDisabled();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
