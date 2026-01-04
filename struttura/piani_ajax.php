<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/struttura_status.php';
if (!function_exists('require_root')) { function require_root(){} }
require_root();

$edificio_id = (int)($_GET['edificio_id'] ?? 0);
$pianoSel    = (int)($_GET['piano_id'] ?? 0);

if ($edificio_id <= 0){
  echo "<div class='muted-empty'>Seleziona un edificio.</div>";
  exit;
}

$stmt = $mysqli->prepare("SELECT id, edificio_id, nome, livello, attivo FROM piani WHERE edificio_id=? ORDER BY livello ASC, nome ASC");
if (!$stmt){
  echo "<div class='alert alert-danger mb-0'>Errore DB: ".h($mysqli->error)."</div>";
  exit;
}
$stmt->bind_param("i", $edificio_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0){
  echo "<div class='muted-empty'>Nessun piano per questo edificio. Clicca “+” per crearne uno.</div>";
  exit;
}

while($r = $res->fetch_assoc()){
  $id = (int)$r['id'];
  $nome = (string)$r['nome'];
  $liv  = (int)($r['livello'] ?? 0);

  $isActive = ($pianoSel === $id) ? " active" : "";
  $on = ((int)$r['attivo'] === 1);

  $edit = "piano_edit.php?id=$id&edificio_id=$edificio_id&back=" . urlencode("struttura.php?edificio_id=$edificio_id&piano_id=$id");
  $back = "struttura.php?edificio_id=$edificio_id&piano_id=$id";
  $schedule = struttura_schedule_next($mysqli, 'piano', $id);
  ?>
  <div class="item<?= $isActive ?>"
     data-id="<?= $id ?>"
     data-nome="<?= h($nome) ?>">

    <div class="main">
      <div class="name"><?= h($nome) ?></div>
      <div class="sub text-muted small">Livello: <?= $liv ?></div>
      <?php if ($schedule): ?>
        <div class="schedule-note">
          Programma: <?= ((int)$schedule['stato'] === 1 ? 'Attiva' : 'Disattiva') ?>
          dal <?= h($schedule['start_date']) ?>
          <?= $schedule['end_date'] ? ' al ' . h($schedule['end_date']) : '' ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="acts" onclick="event.stopPropagation()">

      <a class="btn btn-outline-primary btn-mini" href="<?= h($edit) ?>" title="Modifica">
        <i class="bi bi-pencil"></i>
      </a>

      <button type="button"
              class="btn btn-outline-secondary btn-mini js-btn-schedule"
              title="Programma attivazione/disattivazione"
              data-tipo="piano"
              data-id="<?= $id ?>"
              data-label="<?= h($nome) ?>"
              data-current="<?= $on ? 1 : 0 ?>">
        <i class="bi bi-clock-history"></i>
      </button>

      <form method="post" action="struttura_save.php" class="m-0"
            onsubmit="event.stopPropagation(); return confirm('Eliminare il piano? Verranno eliminate anche le camere collegate.');">
        <input type="hidden" name="tipo" value="piano">
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
                 data-tipo="piano"
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
