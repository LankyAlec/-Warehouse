<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
if (!function_exists('require_root')) { function require_root(){} }
require_root();

include __DIR__ . '/../includes/header.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$back = $_GET['back'] ?? 'struttura.php';

$row = ['nome'=>'','note'=>'','attivo'=>1];
if ($id > 0){
  $stmt = $mysqli->prepare("SELECT * FROM edifici WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  if ($r) $row = $r;
}
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><?= $id>0 ? "Modifica edificio" : "Nuovo edificio" ?></h3>
    <a class="btn btn-outline-secondary" href="<?= h($back) ?>">← Torna</a>
  </div>

  <form method="post" action="struttura_save.php" class="card shadow-sm border-0">
    <div class="card-body">
      <input type="hidden" name="tipo" value="edificio">
      <input type="hidden" name="azione" value="save">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="back" value="<?= h($back) ?>">

      <div class="mb-2">
        <label class="form-label">Nome</label>
        <input class="form-control" name="nome" value="<?= h($row['nome'] ?? '') ?>" required>
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