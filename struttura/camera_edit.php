<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
if (!function_exists('require_root')) { function require_root(){} }
require_root();

include __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$piano_id = (int)($_GET['piano_id'] ?? 0);
$back = $_GET['back'] ?? 'struttura.php';

$row = ['piano_id'=>$piano_id,'codice'=>'','nome'=>'','capienza_base'=>2,'note'=>'','attiva'=>1];

if ($id > 0){
  $stmt = $mysqli->prepare("SELECT * FROM camere WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  if ($r) $row = $r;
  $piano_id = (int)($row['piano_id'] ?? 0);
}
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><?= $id>0 ? "Modifica camera" : "Nuova camera" ?></h3>
    <a class="btn btn-outline-secondary" href="<?= h($back) ?>">← Torna</a>
  </div>

  <form method="post" action="struttura_save.php" class="card shadow-sm border-0">
    <div class="card-body">
      <input type="hidden" name="tipo" value="camera">
      <input type="hidden" name="azione" value="save">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="piano_id" value="<?= (int)$piano_id ?>">
      <input type="hidden" name="back" value="<?= h($back) ?>">

      <div class="row g-2">
        <div class="col-12 col-md-4">
          <label class="form-label">Codice</label>
          <input class="form-control" name="codice" value="<?= h($row['codice'] ?? '') ?>" required>
        </div>
        <div class="col-12 col-md-8">
          <label class="form-label">Nome (opzionale)</label>
          <input class="form-control" name="nome" value="<?= h($row['nome'] ?? '') ?>">
        </div>
      </div>

      <div class="mt-2">
        <label class="form-label">Capienza base</label>
        <input class="form-control" type="number" min="1" name="capienza_base" value="<?= (int)($row['capienza_base'] ?? 2) ?>">
      </div>

      <div class="mt-2">
        <label class="form-label">Note</label>
        <textarea class="form-control" name="note" rows="3"><?= h($row['note'] ?? '') ?></textarea>
      </div>

      <div class="form-check form-switch mt-2">
        <input class="form-check-input" type="checkbox" role="switch" name="attiva" value="1" <?= ((int)($row['attiva'] ?? 1)===1?'checked':'') ?>>
        <label class="form-check-label">Attiva</label>
      </div>

      <button class="btn btn-primary w-100 mt-3"><i class="bi bi-save"></i> Salva</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
