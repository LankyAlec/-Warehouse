<?php
include __DIR__ . '/includes/header.php';

$oggi = date('Y-m-d');

/* ================== CAMERE OCCUPATE vs DISPONIBILI (oggi) ================== */

/* Tot camere attive */
$stmt = $mysqli->prepare("SELECT COUNT(*) AS tot FROM camere WHERE attiva = 1");
$stmt->execute();
$camere_attive = (int)($stmt->get_result()->fetch_assoc()['tot'] ?? 0);

/* Camere occupate oggi (attive) */
$sql_camere_occupate = "
    SELECT COUNT(DISTINCT s.camera_id) AS tot
    FROM soggiorni s
    JOIN camere c ON c.id = s.camera_id
    WHERE c.attiva = 1
      AND s.stato = 'occupato'
      AND ? >= s.data_checkin
      AND ? <  s.data_checkout
";
$stmt = $mysqli->prepare($sql_camere_occupate);
$stmt->bind_param("ss", $oggi, $oggi);
$stmt->execute();
$camere_occupate = (int)($stmt->get_result()->fetch_assoc()['tot'] ?? 0);

/* Camere disponibili oggi:
   - attive
   - NON occupate oggi
   - NON in pulizia oggi (DA_FARE o IN_CORSO)
   - NON con manutenzione APERTO o IN_CORSO
*/
$sql_camere_disponibili = "
    SELECT COUNT(*) AS tot
    FROM camere c
    LEFT JOIN (
        SELECT DISTINCT camera_id
        FROM soggiorni
        WHERE stato = 'occupato'
          AND ? >= data_checkin
          AND ? <  data_checkout
    ) occ ON occ.camera_id = c.id
    LEFT JOIN (
        SELECT DISTINCT camera_id
        FROM task_pulizie
        WHERE data = ?
          AND stato IN ('DA_FARE','IN_CORSO')
    ) pul ON pul.camera_id = c.id
    LEFT JOIN (
        SELECT DISTINCT camera_id
        FROM ticket_manutenzione
        WHERE camera_id IS NOT NULL
          AND stato IN ('APERTO','IN_CORSO')
    ) man ON man.camera_id = c.id
    WHERE c.attiva = 1
      AND occ.camera_id IS NULL
      AND pul.camera_id IS NULL
      AND man.camera_id IS NULL
";
$stmt = $mysqli->prepare($sql_camere_disponibili);
$stmt->bind_param("sss", $oggi, $oggi, $oggi);
$stmt->execute();
$camere_disponibili = (int)($stmt->get_result()->fetch_assoc()['tot'] ?? 0);

/* (facoltativo) Camere “non disponibili” = attive - disponibili */
$camere_non_disponibili = max(0, $camere_attive - $camere_disponibili);


/*
  Definizioni:
  - "presenti in struttura": soggiorni con stato='occupato' e oggi dentro [checkin, checkout)
  - checkin attesi: data_checkin=oggi e stato='prenotato'
  - checkout attesi: data_checkout=oggi e stato='occupato'
  - camere da rifare: task_pulizie oggi con stato DA_FARE/IN_CORSO
  - manutenzioni: ticket_manutenzione stato APERTO/IN_CORSO
*/

/* ================== 1) Tot clienti presenti ================== */
$sql_presenti = "
    SELECT COUNT(DISTINCT sc.cliente_id) AS tot
    FROM soggiorni s
    JOIN soggiorni_clienti sc ON sc.soggiorno_id = s.id
    WHERE s.stato = 'occupato'
      AND ? >= s.data_checkin
      AND ? <  s.data_checkout
";
$stmt = $mysqli->prepare($sql_presenti);
$stmt->bind_param("ss", $oggi, $oggi);
$stmt->execute();
$presenti = (int)($stmt->get_result()->fetch_assoc()['tot'] ?? 0);

/* ================== 2) Checkin attesi (oggi) ================== */
$sql_checkin = "
    SELECT COUNT(*) AS tot
    FROM soggiorni
    WHERE data_checkin = ?
      AND stato = 'prenotato'
";
$stmt = $mysqli->prepare($sql_checkin);
$stmt->bind_param("s", $oggi);
$stmt->execute();
$checkin_attesi = (int)($stmt->get_result()->fetch_assoc()['tot'] ?? 0);

/* ================== 3) Checkout attesi (oggi) ================== */
$sql_checkout = "
    SELECT COUNT(*) AS tot
    FROM soggiorni
    WHERE data_checkout = ?
      AND stato = 'occupato'
";
$stmt = $mysqli->prepare($sql_checkout);
$stmt->bind_param("s", $oggi);
$stmt->execute();
$checkout_attesi = (int)($stmt->get_result()->fetch_assoc()['tot'] ?? 0);

/* ================== 4) Camere da rifare (oggi) ================== */
$sql_camere_rifare = "
    SELECT COUNT(DISTINCT camera_id) AS tot
    FROM task_pulizie
    WHERE data = ?
      AND stato IN ('DA_FARE','IN_CORSO')
";
$stmt = $mysqli->prepare($sql_camere_rifare);
$stmt->bind_param("s", $oggi);
$stmt->execute();
$camere_da_rifare = (int)($stmt->get_result()->fetch_assoc()['tot'] ?? 0);

/* ================== 5) Manutenzioni da compiere ================== */
$sql_manut = "
    SELECT COUNT(*) AS tot
    FROM ticket_manutenzione
    WHERE stato IN ('APERTO','IN_CORSO')
";
$res = $mysqli->query($sql_manut);
$manutenzioni = (int)(($res && ($r=$res->fetch_assoc())) ? $r['tot'] : 0);

/* ================== 6) Persone attese al ristorante ==================
   Calcoliamo quante PERSONE (ospiti) sono presenti oggi e con quali piani pasto.
   In più gestiamo HB: pranzo o cena (hb_servizio).
*/
$sql_ristorante = "
    SELECT
      s.piano_pasto_sigla,
      COALESCE(s.hb_servizio,'CENA') AS hb_servizio,
      COUNT(DISTINCT sc.cliente_id) AS persone
    FROM soggiorni s
    JOIN soggiorni_clienti sc ON sc.soggiorno_id = s.id
    WHERE s.stato = 'occupato'
      AND ? >= s.data_checkin
      AND ? <  s.data_checkout
    GROUP BY s.piano_pasto_sigla, hb_servizio
";
$stmt = $mysqli->prepare($sql_ristorante);
$stmt->bind_param("ss", $oggi, $oggi);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$attesi_colazione = 0;
$attesi_pranzo    = 0;
$attesi_cena      = 0;

foreach ($rows as $rw) {
    $sigla = strtoupper((string)$rw['piano_pasto_sigla']);
    $hb    = strtoupper((string)$rw['hb_servizio']);
    $p     = (int)$rw['persone'];

    // Colazione: BB, HB, FB
    if (in_array($sigla, ['BB','HB','FB'], true)) {
        $attesi_colazione += $p;
    }

    // Pranzo: FB sempre, HB solo se hb_servizio=PRANZO
    if ($sigla === 'FB') {
        $attesi_pranzo += $p;
    } elseif ($sigla === 'HB' && $hb === 'PRANZO') {
        $attesi_pranzo += $p;
    }

    // Cena: FB sempre, HB solo se hb_servizio=CENA
    if ($sigla === 'FB') {
        $attesi_cena += $p;
    } elseif ($sigla === 'HB' && $hb === 'CENA') {
        $attesi_cena += $p;
    }
}

/* ================== 7) Magazzino: prodotti e scadenze ================== */
$magazzino_ok  = false;
$prodotti_mag  = 0;
$prodotti_scad = 0;
$magazzinoDays = 30;

$sql_magazzino = "
    SELECT
        SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) AS prodotti_presenti,
        SUM(CASE WHEN stock > 0 AND expiring > 0 THEN 1 ELSE 0 END) AS prodotti_in_scadenza
    FROM (
        SELECT
            p.id,
            COALESCE((
                SELECT SUM(
                    CASE WHEN mv.tipo='CARICO' THEN mv.quantita ELSE -mv.quantita END
                )
                FROM movimenti mv
                WHERE mv.prodotto_id = p.id
            ), 0) AS stock,
            (
                SELECT COUNT(1)
                FROM lotti l
                WHERE l.prodotto_id = p.id
                  AND l.data_scadenza IS NOT NULL
                  AND l.data_scadenza <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ) AS expiring
        FROM prodotti p
        WHERE p.attivo = 1
    ) AS t
";

$stmt = $mysqli->prepare($sql_magazzino);
if ($stmt) {
    $stmt->bind_param('i', $magazzinoDays);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    $magazzino_ok  = true;
    $prodotti_mag  = (int)($row['prodotti_presenti'] ?? 0);
    $prodotti_scad = (int)($row['prodotti_in_scadenza'] ?? 0);
} else {
    error_log('Magazzino stats prepare error: ' . $mysqli->error);
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="mb-0">Dashboard</h2>
    <div class="text-muted">Benvenuto, <strong><?= h($_SESSION['username']) ?></strong> — <?= date('d/m/Y') ?></div>
  </div>
</div>

<div class="row g-3">

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5 class="mb-1"><i class="bi bi-door-open"></i> Camere</h5>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-12 col-md-4">
            <div class="p-3 rounded border bg-light">
              <div class="text-muted small">Attive</div>
              <div class="fs-2 fw-semibold"><?= (int)$camere_attive ?></div>
            </div>
          </div>

          <div class="col-12 col-md-4">
            <div class="p-3 rounded border bg-light">
              <div class="text-muted small">Occupate</div>
              <div class="fs-2 fw-semibold"><?= (int)$camere_occupate ?></div>
            </div>
          </div>

          <div class="col-12 col-md-4">
            <div class="p-3 rounded border bg-light">
              <div class="text-muted small">Disponibili</div>
              <div class="fs-2 fw-semibold"><?= (int)$camere_disponibili ?></div>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <a class="small" href="<?= BASE_URL ?>/struttura/camere.php">Vai alle camere →</a>
        </div>
      </div>
    </div>
  </div>


  <div class="col-12 col-md-4 col-lg-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small">Clienti presenti</div>
            <div class="display-6 mb-0"><?= $presenti ?></div>
          </div>
          <i class="bi bi-people fs-3 text-primary"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-4 col-lg-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small">Check-in attesi</div>
            <div class="display-6 mb-0"><?= $checkin_attesi ?></div>
          </div>
          <i class="bi bi-box-arrow-in-right fs-3 text-success"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-4 col-lg-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small">Check-out attesi</div>
            <div class="display-6 mb-0"><?= $checkout_attesi ?></div>
          </div>
          <i class="bi bi-box-arrow-left fs-3 text-warning"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-4 col-lg-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small">Camere da rifare</div>
            <div class="display-6 mb-0"><?= $camere_da_rifare ?></div>
          </div>
          <i class="bi bi-bucket fs-3 text-info"></i>
        </div>
        <div class="mt-2">
          <a class="small" href="<?= BASE_URL ?>/pulizie/pulizie.php">Vai alle pulizie →</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-4 col-lg-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small">Prodotti in magazzino</div>
            <div class="display-6 mb-0">
              <?= $magazzino_ok ? $prodotti_mag : '—' ?>
            </div>
            <div class="text-secondary small mt-1">
              In scadenza (30 gg): <strong><?= $magazzino_ok ? $prodotti_scad : '—' ?></strong>
            </div>
          </div>
          <i class="bi bi-box-seam fs-3 text-secondary"></i>
        </div>
        <div class="mt-2">
          <a class="small" href="<?= BASE_URL ?>/magazzino/index.php">Vai al magazzino →</a>
          <?php if (!$magazzino_ok): ?>
            <div class="small text-danger mt-1">Dati non disponibili</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- RISTORANTE -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5 class="mb-1"><i class="bi bi-cup-hot"></i> Persone attese al ristorante</h5>
            <div class="text-muted small">Calcolato dai presenti in struttura e dal piano pasto</div>
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-12 col-md-4">
            <div class="p-3 rounded border bg-light">
              <div class="text-muted small">Colazione</div>
              <div class="fs-2 fw-semibold"><?= $attesi_colazione ?></div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="p-3 rounded border bg-light">
              <div class="text-muted small">Pranzo</div>
              <div class="fs-2 fw-semibold"><?= $attesi_pranzo ?></div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="p-3 rounded border bg-light">
              <div class="text-muted small">Cena</div>
              <div class="fs-2 fw-semibold"><?= $attesi_cena ?></div>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <a class="small" href="<?= BASE_URL ?>/ristorante/tavoli.php">Vai al ristorante →</a>
        </div>
      </div>
    </div>
  </div>

  <!-- MANUTENZIONE -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5 class="mb-1"><i class="bi bi-tools"></i> Manutenzioni da compiere</h5>
            <div class="text-muted small">Ticket in stato APERTO o IN_CORSO</div>
          </div>
          <div class="display-6 mb-0"><?= $manutenzioni ?></div>
        </div>

        <div class="mt-2">
          <a class="small" href="<?= BASE_URL ?>/manutenzione/ticket.php">Vai ai ticket →</a>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
