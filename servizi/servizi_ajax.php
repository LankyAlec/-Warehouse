<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

header('Content-Type: text/html; charset=UTF-8');

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$like = '%' . $q . '%';

/*
  Prezzo "in atto":
  tariffa attiva che copre oggi (CURDATE()).
*/
$priceSub = "
  SELECT t.prezzo_slot
  FROM servizi_tariffe t
  WHERE t.servizio_id = s.id
    AND t.attiva = 1
    AND t.dal <= CURDATE()
    AND (t.al IS NULL OR t.al >= CURDATE())
  ORDER BY t.dal DESC, t.id DESC
  LIMIT 1
";
$extraSub = "
  SELECT t.prezzo_extra
  FROM servizi_tariffe t
  WHERE t.servizio_id = s.id
    AND t.attiva = 1
    AND t.dal <= CURDATE()
    AND (t.al IS NULL OR t.al >= CURDATE())
  ORDER BY t.dal DESC, t.id DESC
  LIMIT 1
";

/* ✅ Ricerca che include anche figli e genitori */
$where  = "WHERE s.parent_id IS NULL";
$params = [];
$types  = "";

if ($q !== '') {
  $where .= "
    AND (
      s.nome LIKE ?
      OR s.descrizione LIKE ?
      OR EXISTS (
        SELECT 1 FROM servizi c
        WHERE c.parent_id = s.id
          AND (c.nome LIKE ? OR c.descrizione LIKE ?)
      )
    )
  ";
  $params = [$like, $like, $like, $like];
  $types  = "ssss";
}

/* Conteggio record (solo genitori) */
$sqlCount = "SELECT COUNT(*) AS cnt FROM servizi s $where";
$stmt = $mysqli->prepare($sqlCount);
if (!$stmt) {
  echo "<div class='alert alert-danger'>Errore DB (count): " . h($mysqli->error) . "</div>";
  exit;
}
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

/* Lista genitori con prezzi "in atto" */
$sql = "
  SELECT
    s.*,
    ($priceSub) AS prezzo_slot_attuale,
    ($extraSub) AS prezzo_extra_attuale
  FROM servizi s
  $where
  ORDER BY s.nome ASC
  LIMIT $perPage OFFSET $offset
";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  echo "<div class='alert alert-danger'>Errore DB (list): " . h($mysqli->error) . "</div>";
  exit;
}
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$parents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Figli per i genitori in pagina */
$childrenByParent = [];
if (!empty($parents)) {
  $ids = array_map(fn($r) => (int)$r['id'], $parents);
  $in  = implode(',', $ids);

  $resC = $mysqli->query("
    SELECT c.*
    FROM servizi c
    WHERE c.parent_id IN ($in)
    ORDER BY c.nome ASC
  ");
  if ($resC) {
    while ($c = $resC->fetch_assoc()) {
      $pid = (int)$c['parent_id'];
      $childrenByParent[$pid] ??= [];
      $childrenByParent[$pid][] = $c;
    }
  }
}

function euro_or_dash($v): string {
  if ($v === null || $v === '') return "<span class='text-muted'>—</span>";
  return "€ " . number_format((float)$v, 2, ',', '.');
}
?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <div class="text-muted">Record: <strong><?= (int)$total ?></strong></div>
  <div class="text-muted">Pagina <strong><?= (int)$page ?></strong> / <strong><?= (int)$totalPages ?></strong></div>
</div>

<?php if (empty($parents)): ?>
  <div class="alert alert-info mb-0">Nessun servizio trovato.</div>
  <?php exit; ?>
<?php endif; ?>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Servizio</th>
        <th class="text-center">Capienza</th>
        <th class="text-center">Slot</th>
        <th class="text-center">Extra</th>
        <th class="text-center">Prezzo</th>
        <th class="text-center">Extra</th>
        <th class="text-center">Stato</th>
        <th class="text-end">Azioni</th>
      </tr>
    </thead>

    <tbody>
    <?php foreach ($parents as $p): ?>
      <?php
        $pid = (int)$p['id'];
        $isIllim = ((int)($p['slot_illimitato'] ?? 0) === 1);

        $slotLabel = $isIllim
          ? "<span class='badge bg-info text-dark'>Illimitato</span>"
          : ((int)$p['durata_slot_min'] . " min");

        $extraLabel = $isIllim
          ? "<span class='text-muted'>—</span>"
          : ((int)$p['step_extra_min'] . " min");

        $prezzoSlot  = euro_or_dash($p['prezzo_slot_attuale']);
        $prezzoExtra = $isIllim ? "<span class='text-muted'>—</span>" : euro_or_dash($p['prezzo_extra_attuale']);

        $isOn = ((int)$p['attivo'] === 1);
      ?>

      <!-- GENITORE -->
      <tr>
        <td><strong><?= h($p['nome']) ?></strong></td>
        <td class="text-center"><?= (int)$p['max_persone'] ?></td>
        <td class="text-center"><?= $slotLabel ?></td>
        <td class="text-center"><?= $extraLabel ?></td>
        <td class="text-center"><?= $prezzoSlot ?></td>
        <td class="text-center"><?= $prezzoExtra ?></td>

        <!-- STATO (switch autosave + badge) -->
        <td class="text-center">
          <div class="stato-wrap">
            <div class="form-check form-switch m-0">
              <input class="form-check-input js-attivo-switch"
                     type="checkbox"
                     role="switch"
                     data-id="<?= $pid ?>"
                     <?= ($isOn ? 'checked' : '') ?>>
            </div>

            <span class="badge badge-stato js-stato-badge <?= ($isOn ? 'bg-success' : 'bg-danger') ?>"
                  data-id="<?= $pid ?>">
              <?= ($isOn ? 'Attivo' : 'Disattivo') ?>
            </span>
          </div>
        </td>

        <!-- AZIONI -->
        <td class="text-end">
          <div class="actions-grid">

            <!-- Modifica -->
            <form method="get" action="servizio_edit.php" class="m-0">
              <input type="hidden" name="id" value="<?= $pid ?>">
              <button type="submit" class="btn btn-sm btn-outline-primary btn-action" title="Modifica">
                <i class="bi bi-pencil"></i>
              </button>
            </form>

            <!-- Tariffe (solo genitore) -->
            <form method="get" action="servizio_prezzi.php" class="m-0">
              <input type="hidden" name="id" value="<?= $pid ?>">
              <button type="submit" class="btn btn-sm btn-outline-secondary btn-action" title="Tariffe">
                <i class="bi bi-currency-euro"></i>
              </button>
            </form>

            <!-- Elimina (genitore: elimina anche i figli) -->
            <form method="post" action="servizio_save.php" class="m-0"
                  onsubmit="return confirm('Eliminare il servizio? Se è un genitore eliminerà anche i componenti.');">
              <input type="hidden" name="azione" value="delete">
              <input type="hidden" name="id" value="<?= $pid ?>">
              <button class="btn btn-sm btn-outline-danger btn-action" title="Elimina">
                <i class="bi bi-trash"></i>
              </button>
            </form>

          </div>
        </td>
      </tr>

      <!-- FIGLI -->
      <?php if (!empty($childrenByParent[$pid])): ?>
        <?php foreach ($childrenByParent[$pid] as $c): ?>
          <?php
            $cid = (int)$c['id'];
            $cOn = ((int)$c['attivo'] === 1);
          ?>
          <tr class="table-light">
            <td class="ps-4 text-muted small">↳ <?= h($c['nome']) ?></td>
            <td class="text-center text-muted">—</td>
            <td class="text-center text-muted">—</td>
            <td class="text-center text-muted">—</td>
            <td class="text-center text-muted">—</td>
            <td class="text-center text-muted">—</td>

            <!-- STATO figlio -->
            <td class="text-center">
              <div class="stato-wrap">
                <div class="form-check form-switch m-0">
                  <input class="form-check-input js-attivo-switch"
                         type="checkbox"
                         role="switch"
                         data-id="<?= $cid ?>"
                         <?= ($cOn ? 'checked' : '') ?>>
                </div>

                <span class="badge badge-stato js-stato-badge <?= ($cOn ? 'bg-success' : 'bg-danger') ?>"
                      data-id="<?= $cid ?>">
                  <?= ($cOn ? 'Attivo' : 'Disattivo') ?>
                </span>
              </div>
            </td>

            <!-- AZIONI figlio (NO tariffe) -->
            <td class="text-end">
              <div class="actions-grid">

                <!-- Modifica -->
                <form method="get" action="servizio_edit.php" class="m-0">
                  <input type="hidden" name="id" value="<?= $cid ?>">
                  <button type="submit" class="btn btn-sm btn-outline-primary btn-action" title="Modifica">
                    <i class="bi bi-pencil"></i>
                  </button>
                </form>

                <!-- placeholder tariffe -->
                <span class="actions-empty" aria-hidden="true"></span>

                <!-- Elimina figlio -->
                <form method="post" action="servizio_save.php" class="m-0"
                      onsubmit="return confirm('Eliminare questo componente?');">
                  <input type="hidden" name="azione" value="delete">
                  <input type="hidden" name="id" value="<?= $cid ?>">
                  <button class="btn btn-sm btn-outline-danger btn-action" title="Elimina">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>

              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-2">
  <ul class="pagination justify-content-end mb-0">
    <?php
      $prev = max(1, $page - 1);
      $next = min($totalPages, $page + 1);

      $mk = function($p, $label, $disabled=false, $active=false){
        $cls = "page-item";
        if ($disabled) $cls .= " disabled";
        if ($active) $cls .= " active";
        echo "<li class='$cls'><a class='page-link' href='#' data-page='$p'>$label</a></li>";
      };

      $mk($prev, "«", $page === 1);
      $start = max(1, $page - 2);
      $end   = min($totalPages, $page + 2);

      for ($i=$start; $i<=$end; $i++){
        $mk($i, (string)$i, false, $i === $page);
      }
      $mk($next, "»", $page === $totalPages);
    ?>
  </ul>
</nav>
<?php endif; ?>
