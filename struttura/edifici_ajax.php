<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
if (!function_exists('require_root')) { function require_root(){} }
require_root();

$edificioSel = (int)($_GET['edificio_id'] ?? 0);

$res = $mysqli->query("SELECT id, nome, attivo FROM edifici ORDER BY nome ASC");
if (!$res) {
  echo "<div class='alert alert-danger mb-0'>Errore DB: ".h($mysqli->error)."</div>";
  exit;
}

if ($res->num_rows === 0){
  echo "<div class='muted-empty'>Nessun edificio. Clicca “+” per crearne uno.</div>";
  exit;
}

while($r = $res->fetch_assoc()){
  $id = (int)$r['id'];
  $nome = (string)$r['nome'];
  $isActive = ($edificioSel === $id) ? " active" : "";

  $on = ((int)$r['attivo'] === 1);
  $edit = "edificio_edit.php?id=$id&back=" . urlencode("struttura.php?edificio_id=$id");
  $back = "struttura.php?edificio_id=$id";
  ?>
  <div class="item<?= $isActive ?>"
       data-id="<?= $id ?>"
       data-nome="<?= h($nome) ?>">

    <div class="main">
      <div class="name"><?= h($nome) ?></div>
    </div>

    <div class="acts" onclick="event.stopPropagation()">

      <a class="btn btn-outline-primary btn-mini" href="<?= h($edit) ?>" title="Modifica">
        <i class="bi bi-pencil"></i>
      </a>

      <form method="post" action="struttura_save.php" class="m-0"
            onsubmit="event.stopPropagation(); return confirm('Eliminare edificio? Verranno eliminati anche piani e camere.');">
        <input type="hidden" name="tipo" value="edificio">
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
                 data-tipo="edificio"
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
