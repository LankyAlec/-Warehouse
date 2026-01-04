<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
if (!function_exists('require_root')) { function require_root(){} }
require_root();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$piano_id  = (int)($_GET['piano_id'] ?? 0);
$cameraSel = (int)($_GET['camera_id'] ?? 0);

if ($piano_id <= 0){
  echo "<div class='muted-empty'>Seleziona un piano.</div>";
  exit;
}

$stmt = $mysqli->prepare("SELECT id, piano_id, codice, nome, capienza_base, attiva FROM camere WHERE piano_id=? ORDER BY codice ASC, nome ASC");
if (!$stmt){
  echo "<div class='alert alert-danger mb-0'>Errore DB: ".h($mysqli->error)."</div>";
  exit;
}
$stmt->bind_param("i", $piano_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0){
  echo "<div class='muted-empty'>Nessuna camera su questo piano. Clicca “+” per crearne una.</div>";
  exit;
}

while($r = $res->fetch_assoc()){
  $id = (int)$r['id'];
  $cod = (string)$r['codice'];
  $nome = (string)($r['nome'] ?? '');
  $cap = (int)($r['capienza_base'] ?? 2);

  $isActive = ($cameraSel === $id) ? " active" : "";
  $on = ((int)$r['attiva'] === 1);

  $edit = "camera_edit.php?id=$id&piano_id=$piano_id&back=" . urlencode("struttura.php?piano_id=$piano_id&camera_id=$id");
  $back = "struttura.php?piano_id=$piano_id&camera_id=$id";
  ?>
  <div class="item<?= $isActive ?>" data-id="<?= $id ?>">

    <div class="main">
      <div class="name">
        <?= h($cod) ?><?= $nome !== '' ? " <span class='text-muted fw-normal'>— ".h($nome)."</span>" : "" ?>
      </div>
      <div class="sub text-muted small">Capienza base: <?= $cap ?></div>
    </div>

    <div class="acts" onclick="event.stopPropagation()">

      <a class="btn btn-outline-primary btn-mini" href="<?= h($edit) ?>" title="Modifica">
        <i class="bi bi-pencil"></i>
      </a>

      <form method="post" action="struttura_save.php" class="m-0"
            onsubmit="event.stopPropagation(); return confirm('Eliminare la camera?');">
        <input type="hidden" name="tipo" value="camera">
        <input type="hidden" name="azione" value="delete">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="back" value="<?= h($back) ?>">
        <button class="btn btn-outline-danger btn-mini" title="Elimina" type="submit">
          <i class="bi bi-trash"></i>
        </button>
      </form>

      <div class="togglebox d-flex align-items-center gap-2">
        <div class="form-check form-switch m-0">
          <input class="form-check-input js-toggle-attivo"
                 type="checkbox" role="switch"
                 data-tipo="camera"
                 data-id="<?= $id ?>"
                 <?= $on ? 'checked' : '' ?>
                 onclick="event.stopPropagation()">
        </div>

        <span class="badge js-badge-stato <?= $on ? 'bg-success' : 'bg-danger' ?> badge-stato">
          <?= $on ? 'Attivo' : 'Disattivo' ?>
        </span>
      </div>

    </div>
  </div>
  <?php
}