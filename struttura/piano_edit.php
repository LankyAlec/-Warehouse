<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
if (!function_exists('require_root')) { function require_root(){} }
require_root();

include __DIR__ . '/../includes/header.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$edificio_id = (int)($_GET['edificio_id'] ?? 0);
$back = $_GET['back'] ?? 'struttura.php';

$row = ['edificio_id'=>$edificio_id,'nome'=>'','livello'=>0,'note'=>'','attivo'=>1];

if ($id > 0){
  $stmt = $mysqli->prepare("SELECT * FROM piani WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  if ($r) $row = $r;
  $edificio_id = (int)($row['edificio_id'] ?? 0);
}
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><?= $id>0 ? "Modifica piano" : "Nuovo piano" ?></h3>
    <a class="btn btn-outline-secondary" href="<?= h($back) ?>">← Torna</a>
  </div>

  <form method="post" action="struttura_save.php" class="card shadow-sm border-0">
    <div class="card-body">
      <input type="hidden" name="tipo" value="piano">
      <input type="hidden" name="azione" value="save">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="edificio_id" value="<?= (int)$edificio_id ?>">
      <input type="hidden" name="back" value="<?= h($back) ?>">

      <div class="mb-2">
        <label class="form-label">Nome</label>
        <input class="form-control" name="nome" value="<?= h($row['nome'] ?? '') ?>" required>
      </div>

      <div class="mb-2">
        <label class="form-label">Livello</label>
        <input class="form-control" type="number" name="livello" value="<?= (int)($row['livello'] ?? 0) ?>">
      </div>

      <div class="mb-2">
        <label class="form-label">Note</label>
        <textarea class="form-control" name="note" rows="3"><?= h($row['note'] ?? '') ?></textarea>
      </div>

      <div class="form-check form-switch mt-2">
        <input class="form-check-input" type="checkbox" role="switch" name="attivo" value="1" <?= ((int)($row['attivo'] ?? 1)===1?'checked':'') ?>>
        <label class="form-check-label">Attivo</label>
      </div>

      <button class="btn btn-primary w-100 mt-3"><i class="bi bi-save"></i> Salva</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>