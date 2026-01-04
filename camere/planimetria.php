<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
include __DIR__ . '/../includes/header.php';

$edifici = [];
$piani = [];

$resE = $mysqli->query("SELECT id, nome FROM edifici WHERE attivo = 1 ORDER BY nome ASC");
if ($resE) {
    $edifici = $resE->fetch_all(MYSQLI_ASSOC);
}

$resP = $mysqli->query("SELECT id, edificio_id, nome, livello FROM piani WHERE attivo = 1 ORDER BY livello ASC, nome ASC");
if ($resP) {
    $piani = $resP->fetch_all(MYSQLI_ASSOC);
}

$edificioSel = (int)($_GET['edificio_id'] ?? ($edifici[0]['id'] ?? 0));
$pianoSel = (int)($_GET['piano_id'] ?? 0);

if ($pianoSel === 0 && $edificioSel > 0) {
    foreach ($piani as $p) {
        if ((int)$p['edificio_id'] === $edificioSel) {
            $pianoSel = (int)$p['id'];
            break;
        }
    }
}
?>

<style>
  .filters-card{ border:0; border-radius:16px; box-shadow:0 .35rem 1rem rgba(0,0,0,.08); background:#fff; }
  .filters-card .card-body{ padding:16px; }

  .legend{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
  .legend .item{ display:inline-flex; align-items:center; gap:6px; font-size:.9rem; color:#555; }
  .legend .dot{ width:14px; height:14px; border-radius:50%; display:inline-block; box-shadow:0 0 0 1px rgba(0,0,0,.06) inset; }

  .planimetria-grid{
    --cols:4;
    display:grid;
    grid-template-columns: repeat(var(--cols), minmax(180px,1fr));
    grid-auto-rows: minmax(120px, auto);
    gap:14px;
    align-items:start;
  }
  @media (max-width: 992px){
    .planimetria-grid{ grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
  }

  .room-card{
    border-radius:14px;
    border:1px solid rgba(0,0,0,.06);
    background:#fff;
    padding:12px 14px;
    box-shadow:0 .25rem .75rem rgba(0,0,0,.05);
    cursor:pointer;
    transition: transform .12s ease, box-shadow .12s ease;
    position:relative;
    overflow:hidden;
  }
  .room-card:hover{ transform:translateY(-1px); box-shadow:0 .35rem 1.1rem rgba(0,0,0,.08); }
  .room-card .badge{ font-size:.75rem; }
  .room-card h6{ margin:0; font-weight:700; display:flex; align-items:center; gap:8px; }
  .room-card .subtitle{ color:#6c757d; font-size:.88rem; margin-top:2px; }
  .room-card .status-pill{
    position:absolute;
    inset:0 auto 0 0;
    width:6px;
  }
  .room-card.active{ outline:2px solid rgba(13,110,253,.35); box-shadow:0 .35rem 1.1rem rgba(13,110,253,.16); }

  .room-card.stato-occupata{ background: linear-gradient(180deg, rgba(220,53,69,.06), rgba(255,255,255,1)); }
  .room-card.stato-pulizia{ background: linear-gradient(180deg, rgba(255,193,7,.12), rgba(255,255,255,1)); }
  .room-card.stato-manutenzione{ background: linear-gradient(180deg, rgba(108,117,125,.12), rgba(255,255,255,1)); }
  .room-card.stato-libera{ background: linear-gradient(180deg, rgba(25,135,84,.08), rgba(255,255,255,1)); }

  .action-panel{ position:sticky; top:80px; }
  .action-card{ border:0; border-radius:16px; box-shadow:0 .35rem 1rem rgba(0,0,0,.08); }
  .action-card .card-body{ padding:16px; }

  .muted-empty{ color:#6c757d; font-size:.95rem; padding:12px; }
  .toast-inline{
    position:fixed; bottom:24px; right:24px;
    padding:12px 14px; border-radius:12px;
    background:#0d6efd; color:#fff; box-shadow:0 .45rem 1.3rem rgba(13,110,253,.35);
    opacity:0; transform:translateY(12px);
    transition: all .2s ease;
    z-index:999;
  }
  .toast-inline.show{ opacity:1; transform:translateY(0); }
</style>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-grid-3x3-gap"></i> Planimetria camere</h3>
      <div class="text-muted small">Visualizza lo stato delle camere per piano con interazioni rapide</div>
    </div>
  </div>

  <div class="card filters-card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Edificio</label>
          <select id="selEdificio" class="form-select">
            <?php foreach ($edifici as $e): ?>
              <option value="<?= (int)$e['id'] ?>" <?= ((int)$e['id'] === $edificioSel ? 'selected' : '') ?>>
                <?= h($e['nome'] ?? 'Edificio '.$e['id']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Piano</label>
          <select id="selPiano" class="form-select"></select>
        </div>
        <div class="col-12 col-md-4 d-flex align-items-center justify-content-md-end">
          <div class="legend">
            <div class="item"><span class="dot" style="background:#dc3545"></span> Occupata</div>
            <div class="item"><span class="dot" style="background:#ffc107"></span> Pulizia</div>
            <div class="item"><span class="dot" style="background:#6c757d"></span> Manutenzione</div>
            <div class="item"><span class="dot" style="background:#198754"></span> Libera</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-8">
      <div id="planimetriaGrid" class="planimetria-grid">
        <div class="muted-empty">Seleziona un piano per visualizzare la planimetria.</div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="action-panel">
        <div class="card action-card">
          <div class="card-body" id="actionCardBody">
            <div class="muted-empty">Clicca su una camera per aprire le azioni rapide.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="toastInline" class="toast-inline"></div>

<script>
  (function(){
    const edifici = <?= json_encode($edifici, JSON_UNESCAPED_UNICODE) ?>;
    const piani = <?= json_encode($piani, JSON_UNESCAPED_UNICODE) ?>;

    const selEdificio = document.getElementById('selEdificio');
    const selPiano = document.getElementById('selPiano');
    const gridEl = document.getElementById('planimetriaGrid');
    const actionCardBody = document.getElementById('actionCardBody');
    const toastEl = document.getElementById('toastInline');

    let edificioSel = <?= (int)$edificioSel ?>;
    let pianoSel = <?= (int)$pianoSel ?>;
    let camereCache = [];
    let selectedId = null;

    function renderPianiOptions() {
      const options = piani.filter(p => String(p.edificio_id) === String(edificioSel));
      selPiano.innerHTML = '';
      if (options.length === 0) {
        selPiano.innerHTML = "<option value=\"\">Nessun piano attivo</option>";
        pianoSel = 0;
        gridEl.innerHTML = "<div class='muted-empty'>Nessun piano disponibile per questo edificio.</div>";
        return;
      }

      options.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nome || `Piano ${p.id}`;
        if (String(p.id) === String(pianoSel)) opt.selected = true;
        selPiano.appendChild(opt);
      });

      if (!pianoSel || !options.some(p => String(p.id) === String(pianoSel))) {
        pianoSel = options[0].id;
      }
    }

    function buildTooltip(cam) {
      const righe = [];
      righe.push(`<b>${cam.codice}${cam.nome ? ' · ' + cam.nome : ''}</b>`);
      righe.push(`Stato: ${cam.stato_label}`);
      if (cam.detail && cam.stato === 'occupata') {
        const ospiti = cam.detail.ospiti ? cam.detail.ospiti : '—';
        righe.push(`Ospiti: ${ospiti}`);
        righe.push(`Periodo: ${cam.detail.checkin} → ${cam.detail.checkout}`);
      } else if (cam.detail && cam.stato === 'pulizia') {
        righe.push(`Pulizia: ${cam.detail.stato}`);
      } else if (cam.detail && cam.stato === 'manutenzione') {
        righe.push(`Ticket: ${cam.detail.stato}`);
      }
      return righe.join('<br>');
    }

    function initTooltips(){
      const tooltipTriggerList = [].slice.call(gridEl.querySelectorAll('[data-bs-toggle=\"tooltip\"]'));
      tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
      });
    }

    function renderGrid(data){
      camereCache = data.camere || [];
      const cols = data.grid?.cols || 4;
      gridEl.style.setProperty('--cols', cols);
      gridEl.innerHTML = '';

      if (camereCache.length === 0) {
        gridEl.innerHTML = "<div class='muted-empty'>Nessuna camera trovata per questo piano.</div>";
        actionCardBody.innerHTML = "<div class='muted-empty'>Clicca su una camera per aprire le azioni rapide.</div>";
        return;
      }

      camereCache.forEach(cam => {
        const cell = document.createElement('div');
        cell.className = `room-card stato-${cam.stato}`;
        if (selectedId && String(selectedId) === String(cam.id)) cell.classList.add('active');
        cell.dataset.id = cam.id;
        if (cam.pos) {
          if (cam.pos.row) cell.style.gridRowStart = cam.pos.row;
          if (cam.pos.col) cell.style.gridColumnStart = cam.pos.col;
          if (cam.pos.h) cell.style.gridRowEnd = `span ${cam.pos.h}`;
          if (cam.pos.w) cell.style.gridColumnEnd = `span ${cam.pos.w}`;
        }
        cell.setAttribute('data-bs-toggle','tooltip');
        cell.setAttribute('data-bs-html','true');
        cell.setAttribute('title', buildTooltip(cam));
        cell.innerHTML = `
          <span class="status-pill" style="background:${cam.color}"></span>
          <h6>${cam.codice} <span class="badge ${cam.badge_class}">${cam.stato_label}</span></h6>
          <div class="subtitle">${cam.nome || '—'} · Capienza ${cam.capienza || '—'}</div>
        `;
        cell.addEventListener('click', () => selectCamera(cam.id));
        gridEl.appendChild(cell);
      });

      initTooltips();
    }

    function selectCamera(id){
      selectedId = id;
      gridEl.querySelectorAll('.room-card').forEach(el => {
        el.classList.toggle('active', String(el.dataset.id) === String(id));
      });
      const cam = camereCache.find(c => String(c.id) === String(id));
      if (!cam) return;
      renderActionCard(cam);
    }

    function renderActionCard(cam){
      const ospiti = cam.detail && cam.detail.ospiti ? cam.detail.ospiti : '—';
      const periodo = cam.detail && cam.detail.checkin ? `${cam.detail.checkin} → ${cam.detail.checkout}` : '—';
      const statoExtra = cam.stato === 'pulizia' && cam.detail ? cam.detail.stato : (cam.stato === 'manutenzione' && cam.detail ? cam.detail.stato : null);

      actionCardBody.innerHTML = `
        <div class="d-flex align-items-start justify-content-between mb-2">
          <div>
            <div class="text-muted small">Camera</div>
            <div class="fs-5 fw-semibold">${cam.codice}${cam.nome ? ' · ' + cam.nome : ''}</div>
            <div class="small text-muted">Capienza: ${cam.capienza || '—'}</div>
          </div>
          <span class="badge ${cam.badge_class}">${cam.stato_label}</span>
        </div>
        <div class="mb-2">
          <div class="text-muted small">Stato dettagli</div>
          <div>${statoExtra ? statoExtra : cam.stato_label}</div>
          ${cam.stato === 'occupata' ? `<div class="small text-muted">Periodo: ${periodo}</div><div class="small text-muted">Ospiti: ${ospiti}</div>` : ''}
        </div>
        <div class="d-grid gap-2">
          <button class="btn btn-success" data-action="checkin"><i class="bi bi-door-open"></i> Check-in</button>
          <button class="btn btn-outline-primary" data-action="checkout"><i class="bi bi-box-arrow-right"></i> Check-out</button>
          <button class="btn btn-warning text-dark" data-action="pulizia"><i class="bi bi-bucket"></i> Passa a pulizia</button>
          <button class="btn btn-secondary" data-action="manutenzione"><i class="bi bi-tools"></i> Segnala manutenzione</button>
        </div>
      `;

      actionCardBody.querySelectorAll('[data-action]').forEach(btn => {
        btn.addEventListener('click', () => quickAction(btn.dataset.action, cam));
      });
    }

    function showToast(msg){
      toastEl.textContent = msg;
      toastEl.classList.add('show');
      setTimeout(() => toastEl.classList.remove('show'), 1800);
    }

    function quickAction(action, cam){
      const labels = {
        checkin: 'Check-in',
        checkout: 'Check-out',
        pulizia: 'Passa a pulizia',
        manutenzione: 'Segnala manutenzione'
      };
      showToast(`${labels[action] || 'Azione'} per ${cam.codice} registrata (simulata).`);
    }

    async function loadData(){
      if (!pianoSel) {
        gridEl.innerHTML = "<div class='muted-empty'>Seleziona un piano per visualizzare la planimetria.</div>";
        return;
      }

      gridEl.innerHTML = "<div class='muted-empty'>Caricamento planimetria…</div>";
      actionCardBody.innerHTML = "<div class='muted-empty'>Clicca su una camera per aprire le azioni rapide.</div>";
      selectedId = null;

      const params = new URLSearchParams();
      if (edificioSel) params.set('edificio_id', edificioSel);
      if (pianoSel) params.set('piano_id', pianoSel);

      try{
        const res = await fetch('planimetria_ajax.php?' + params.toString(), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Errore nel recupero dati');
        renderGrid(data);
      }catch(err){
        gridEl.innerHTML = `<div class='alert alert-danger mb-0'>${err.message}</div>`;
      }
    }

    selEdificio?.addEventListener('change', () => {
      edificioSel = selEdificio.value || 0;
      renderPianiOptions();
      loadData();
    });

    selPiano?.addEventListener('change', () => {
      pianoSel = selPiano.value || 0;
      loadData();
    });

    renderPianiOptions();
    loadData();
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
