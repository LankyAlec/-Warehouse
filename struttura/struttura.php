<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/struttura_status.php';

/* fallback se in helpers non esiste require_root */
if (!function_exists('require_root')) { function require_root(){} }
require_root();

// Applica eventuali schedulazioni dovute per la data odierna
try {
  struttura_schedule_apply_due($mysqli);
} catch (Throwable $e) {
  error_log('Errore applicazione schedulazioni: ' . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';

$edificio_id = (int)($_GET['edificio_id'] ?? 0);
$piano_id    = (int)($_GET['piano_id'] ?? 0);
?>

<style>
  .structure-grid{ display:grid; grid-template-columns: 1fr 1fr 1.35fr; gap:16px; }
  @media (max-width: 992px){ .structure-grid{ grid-template-columns: 1fr; } }

  .box-card{ border:0; border-radius:16px; box-shadow:0 .35rem 1rem rgba(0,0,0,.08); overflow:hidden; background:#fff; }
  .box-head{
    padding:12px 14px;
    border-bottom:1px solid rgba(0,0,0,.06);
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    background: linear-gradient(180deg, rgba(0,0,0,.02), rgba(0,0,0,0));
  }
  .box-head .title{ min-width:0; display:flex; flex-direction:column; gap:2px; }
  .box-head .title b{ font-size:.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .box-head .subtitle{ font-size:.78rem; color:#6c757d; line-height:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

  .list{ padding:10px; max-height:70vh; overflow:auto; }

  .item{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding:10px 10px; border-radius:14px; cursor:pointer; transition:.15s ease;
    border:1px solid rgba(0,0,0,.06); background:#fff; margin-bottom:10px;
  }
  .item:hover{ transform: translateY(-1px); box-shadow:0 .25rem .75rem rgba(0,0,0,.06); }
  .item.active{
    border-color: rgba(13,110,253,.45);
    background: rgba(13,110,253,.06);
    box-shadow: 0 .35rem 1rem rgba(13,110,253,.08);
  }

  .item .main{ min-width:0; display:flex; flex-direction:column; gap:2px; }
  .item .main .name{ font-weight:650; font-size:.93rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .item .main .meta{ font-size:.78rem; color:#6c757d; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:flex; align-items:center; gap:8px; }

  .acts{ display:flex; align-items:center; gap:8px; flex-shrink:0; }
  .btn-mini{
    width:38px; height:34px; padding:0;
    display:inline-flex; align-items:center; justify-content:center;
    border-radius:10px;
  }

  .muted-empty{ padding:10px; color:#6c757d; font-size:.9rem; }

  .crumb{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
  .pill{ padding:6px 12px; border-radius:999px; background:rgba(0,0,0,.04); font-size:.82rem; display:flex; align-items:center; gap:8px; }
  .pill b{ font-weight:700; }

  .btn-plus{
    width:40px; height:36px; padding:0;
    display:inline-flex; align-items:center; justify-content:center;
    border-radius:12px;
  }

  .hint-sel{
    display:flex; gap:10px; align-items:center;
    padding:10px 12px; border-radius:14px;
    border:1px dashed rgba(0,0,0,.15);
    background:rgba(0,0,0,.02);
    font-size:.9rem; color:#6c757d;
  }

  .badge-stato{
    width:92px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
  }

  .schedule-note{
    color:#0d6efd;
    font-size:.8rem;
  }
</style>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-building"></i> Struttura Hotel</h3>
      <div class="text-muted small">Gestisci Edifici → Piani → Camere dalla stessa schermata</div>
    </div>

    <div class="crumb">
      <span class="pill"><i class="bi bi-buildings"></i> Edificio: <b id="crumbEdificio">—</b></span>
      <span class="pill"><i class="bi bi-layers"></i> Piano: <b id="crumbPiano">—</b></span>
    </div>
  </div>

  <div class="structure-grid">
    <!-- EDIFICI -->
    <div class="box-card">
      <div class="box-head">
        <div class="title">
          <b><i class="bi bi-buildings"></i> Edifici</b>
          <div class="subtitle">Seleziona un edificio per vedere i piani</div>
        </div>
        <button class="btn btn-primary btn-plus" id="btnNewEdificio" title="Nuovo edificio">
          <i class="bi bi-plus-circle"></i>
        </button>
      </div>
      <div class="list" id="edifici"><div class="muted-empty">Caricamento…</div></div>
    </div>

    <!-- PIANI -->
    <div class="box-card">
      <div class="box-head">
        <div class="title">
          <b><i class="bi bi-layers"></i> Piani</b>
          <div class="subtitle" id="subtitlePiani">Seleziona un edificio</div>
        </div>
        <button class="btn btn-primary btn-plus" id="btnNewPiano" title="Nuovo piano" disabled>
          <i class="bi bi-plus-circle"></i>
        </button>
      </div>
      <div class="list" id="piani">
        <div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>edificio</b> per visualizzare i piani.</div>
      </div>
    </div>

    <!-- CAMERE -->
    <div class="box-card">
      <div class="box-head">
        <div class="title">
          <b><i class="bi bi-door-closed"></i> Camere</b>
          <div class="subtitle" id="subtitleCamere">Seleziona un piano</div>
        </div>
        <button class="btn btn-primary btn-plus" id="btnNewCamera" title="Nuova camera" disabled>
          <i class="bi bi-plus-circle"></i>
        </button>
      </div>
      <div class="list" id="camere">
        <div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>piano</b> per visualizzare le camere.</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal cascata -->
<div class="modal fade" id="modalCascade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-exclamation-triangle"></i> Conferma operazione
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div id="cascadeMsg" class="mb-2">…</div>
        <div class="small text-muted" id="cascadeHint">…</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-danger" id="btnCascadeConfirm">
          Conferma
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal schedulazione -->
<div class="modal fade" id="modalSchedule" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clock-history"></i> Programma attivazione/disattivazione</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <form id="scheduleForm">
        <div class="modal-body">
          <input type="hidden" name="tipo" id="scheduleTipo">
          <input type="hidden" name="id" id="scheduleId">

          <div class="mb-3">
            <label class="form-label text-muted small mb-1">Elemento</label>
            <div class="fw-semibold" id="scheduleLabel">—</div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="scheduleStart">Data inizio</label>
              <input type="date" class="form-control" id="scheduleStart" name="start_date" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="scheduleEnd">Data fine <span class="text-muted">(opzionale)</span></label>
              <input type="date" class="form-control" id="scheduleEnd" name="end_date">
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label" for="scheduleAction">Azione programmata</label>
            <select class="form-select" id="scheduleAction" name="stato" required>
              <option value="1">Attiva</option>
              <option value="0">Disattiva</option>
            </select>
            <div class="form-text">Se imposti una data di fine, al termine verrà ripristinato lo stato attuale.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary" id="btnScheduleSubmit">Salva schedulazione</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const edificiEl = document.getElementById('edifici');
  const pianiEl   = document.getElementById('piani');
  const camereEl  = document.getElementById('camere');

  const crumbEdificio = document.getElementById('crumbEdificio');
  const crumbPiano    = document.getElementById('crumbPiano');

  const subtitlePiani  = document.getElementById('subtitlePiani');
  const subtitleCamere = document.getElementById('subtitleCamere');

  const btnNewEdificio = document.getElementById('btnNewEdificio');
  const btnNewPiano    = document.getElementById('btnNewPiano');
  const btnNewCamera   = document.getElementById('btnNewCamera');

  // ✅ stato globale coerente (usato anche dallo script toggle)
  window.edificioSel = <?= (int)$edificio_id ?> || null;
  window.pianoSel    = <?= (int)$piano_id ?> || null;

  function qs(obj){
    const p = new URLSearchParams();
    Object.entries(obj).forEach(([k,v]) => { if(v !== null && v !== undefined && v !== '') p.set(k, String(v)); });
    return p.toString();
  }

  function setActive(container, id){
    container.querySelectorAll('.item[data-id]').forEach(el => {
      el.classList.toggle('active', String(el.dataset.id) === String(id));
    });
  }

  function resetPianiUI(){
    pianiEl.innerHTML = `<div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>edificio</b> per visualizzare i piani.</div>`;
    subtitlePiani.textContent = 'Seleziona un edificio';
    btnNewPiano.disabled = true;
  }
  function resetCamereUI(){
    camereEl.innerHTML = `<div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>piano</b> per visualizzare le camere.</div>`;
    subtitleCamere.textContent = 'Seleziona un piano';
    btnNewCamera.disabled = true;
  }

  async function loadEdifici(){
    const r = await fetch('edifici_ajax.php?' + qs({ edificio_id: window.edificioSel }), { headers:{'X-Requested-With':'XMLHttpRequest'} });
    edificiEl.innerHTML = await r.text();

    edificiEl.querySelectorAll('.item[data-id]').forEach(el => {
      el.addEventListener('click', (e) => {
        if (e.target.closest('button, form, a, input, label')) return;

        window.edificioSel = parseInt(el.dataset.id, 10);
        window.pianoSel = null;

        crumbEdificio.textContent = el.dataset.nome || '—';
        crumbPiano.textContent = '—';

        btnNewPiano.disabled = false;
        resetCamereUI();

        setActive(edificiEl, window.edificioSel);
        loadPiani();

        history.replaceState(null, '', 'struttura.php?' + qs({ edificio_id: window.edificioSel }));
      });
    });

    if (window.edificioSel){
      const el = edificiEl.querySelector('.item[data-id="'+window.edificioSel+'"]');
      if (el){
        crumbEdificio.textContent = el.dataset.nome || '—';
        setActive(edificiEl, window.edificioSel);
        btnNewPiano.disabled = false;
        subtitlePiani.textContent = 'Edificio selezionato';
      } else {
        window.edificioSel = null;
        window.pianoSel = null;
        crumbEdificio.textContent = '—';
        crumbPiano.textContent = '—';
        resetPianiUI(); resetCamereUI();
      }
    } else {
      crumbEdificio.textContent = '—';
      crumbPiano.textContent = '—';
      resetPianiUI(); resetCamereUI();
    }
  }

  async function loadPiani(){
    if (!window.edificioSel){ resetPianiUI(); return; }

    subtitlePiani.textContent = 'Edificio selezionato';
    btnNewPiano.disabled = false;

    const r = await fetch('piani_ajax.php?' + qs({ edificio_id: window.edificioSel, piano_id: window.pianoSel }), { headers:{'X-Requested-With':'XMLHttpRequest'} });
    pianiEl.innerHTML = await r.text();

    pianiEl.querySelectorAll('.item[data-id]').forEach(el => {
      el.addEventListener('click', (e) => {
        if (e.target.closest('button, form, a, input, label')) return;

        window.pianoSel = parseInt(el.dataset.id, 10);

        crumbPiano.textContent = el.dataset.nome || '—';
        subtitleCamere.textContent = 'Piano selezionato';
        btnNewCamera.disabled = false;

        setActive(pianiEl, window.pianoSel);
        loadCamere();

        history.replaceState(null, '', 'struttura.php?' + qs({ edificio_id: window.edificioSel, piano_id: window.pianoSel }));
      });
    });

    if (window.pianoSel){
      const el = pianiEl.querySelector('.item[data-id="'+window.pianoSel+'"]');
      if (el){
        crumbPiano.textContent = el.dataset.nome || '—';
        setActive(pianiEl, window.pianoSel);
        btnNewCamera.disabled = false;
        subtitleCamere.textContent = 'Piano selezionato';
      } else {
        window.pianoSel = null;
        crumbPiano.textContent = '—';
        resetCamereUI();
      }
    } else {
      crumbPiano.textContent = '—';
      resetCamereUI();
    }
  }

  async function loadCamere(){
    if (!window.pianoSel){ resetCamereUI(); return; }

    subtitleCamere.textContent = 'Piano selezionato';
    btnNewCamera.disabled = false;

    const r = await fetch('camere_ajax.php?' + qs({ piano_id: window.pianoSel }), { headers:{'X-Requested-With':'XMLHttpRequest'} });
    camereEl.innerHTML = await r.text();
  }

  // espongo i loader per lo script toggle
  window.loadEdifici = loadEdifici;
  window.loadPiani   = loadPiani;
  window.loadCamere  = loadCamere;

  btnNewEdificio.addEventListener('click', () => {
    window.location = 'edificio_edit.php?back=' + encodeURIComponent('struttura.php?' + qs({ edificio_id: window.edificioSel, piano_id: window.pianoSel }));
  });
  btnNewPiano.addEventListener('click', () => {
    if(!window.edificioSel) return;
    window.location = 'piano_edit.php?edificio_id=' + window.edificioSel + '&back=' + encodeURIComponent('struttura.php?' + qs({ edificio_id: window.edificioSel, piano_id: window.pianoSel }));
  });
  btnNewCamera.addEventListener('click', () => {
    if(!window.pianoSel) return;
    window.location = 'camera_edit.php?piano_id=' + window.pianoSel + '&back=' + encodeURIComponent('struttura.php?' + qs({ edificio_id: window.edificioSel, piano_id: window.pianoSel }));
  });

  (async function init(){
    await loadEdifici();
    if (window.edificioSel) await loadPiani();
    if (window.pianoSel) await loadCamere();
  })();
})();
</script>

<script>
(function(){
  const root = document.querySelector('.container-fluid');

  // cascata consigliata
  const CASCADE = 'always'; // cascata sia in accensione che spegnimento

  const modalEl = document.getElementById('modalCascade');
  const msgEl   = document.getElementById('cascadeMsg');
  const hintEl  = document.getElementById('cascadeHint');
  const btnOk   = document.getElementById('btnCascadeConfirm');

  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

  const scheduleModalEl = document.getElementById('modalSchedule');
  const scheduleModal   = scheduleModalEl ? new bootstrap.Modal(scheduleModalEl) : null;
  const scheduleForm    = document.getElementById('scheduleForm');
  const scheduleTipo    = document.getElementById('scheduleTipo');
  const scheduleId      = document.getElementById('scheduleId');
  const scheduleLabel   = document.getElementById('scheduleLabel');
  const scheduleStart   = document.getElementById('scheduleStart');
  const scheduleEnd     = document.getElementById('scheduleEnd');
  const scheduleAction  = document.getElementById('scheduleAction');
  const btnScheduleSubmit = document.getElementById('btnScheduleSubmit');

  let pending = null; // { sw, tipo, id, val }

  function qs(obj){
    const p = new URLSearchParams();
    Object.entries(obj).forEach(([k,v]) => {
      if(v !== null && v !== undefined && v !== '') p.set(k, String(v));
    });
    return p.toString();
  }

  async function reloadUI(){
    await loadEdifici();
    if (window.edificioSel) await loadPiani();
    else document.getElementById('piani').innerHTML = "<div class='muted-empty'>—</div>";

    if (window.pianoSel) await loadCamere();
    else document.getElementById('camere').innerHTML = "<div class='muted-empty'>—</div>";
  }

  async function previewCascade(tipo, id, val){
    const fd = new FormData();
    fd.append('tipo', tipo);
    fd.append('id', id);
    fd.append('val', String(val));
    fd.append('cascade', CASCADE);

    const res = await fetch('struttura_cascade_preview.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const j = await res.json().catch(()=>null);
    if(!j || !j.ok) throw new Error((j && j.msg) ? j.msg : 'Errore preview');
    return j;
  }

  async function doToggle(tipo, id, val){
    const fd = new FormData();
    fd.append('tipo', tipo);
    fd.append('id', id);
    fd.append('val', String(val));
    fd.append('cascade', CASCADE);

    const res = await fetch('struttura_toggle_ajax.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const j = await res.json().catch(()=>null);
    if(!j || !j.ok) throw new Error((j && j.msg) ? j.msg : 'Errore salvataggio');
    return j;
  }

  function setSaving(sw, saving){
    sw.disabled = saving;
    sw.closest('.item')?.classList.toggle('opacity-75', saving);
  }

  function makeMessage(pre){
    const turningOff = (pre.val === 0);
    const counts = pre.counts || {piani:0, camere:0};

    if (!pre.doCascade) {
      return {
        title: turningOff ? 'Stai disattivando un elemento.' : 'Stai attivando un elemento.',
        hint: 'L’operazione non coinvolge elementi collegati.'
      };
    }

    if (pre.tipo === 'edificio') {
      return {
        title: turningOff
          ? `Disattivando questo edificio verranno disattivati anche ${counts.piani} piani e ${counts.camere} camere.`
          : `Attivando questo edificio verranno attivati anche ${counts.piani} piani e ${counts.camere} camere.`,
        hint: 'Confermi di procedere?'
      };
    }

    if (pre.tipo === 'piano') {
      return {
        title: turningOff
          ? `Disattivando questo piano verranno disattivate anche ${counts.camere} camere.`
          : `Attivando questo piano verranno attivate anche ${counts.camere} camere.`,
        hint: 'Confermi di procedere?'
      };
    }

    return { title:'Confermi l’operazione?', hint:'' };
  }

  function todayStr(){
    const d = new Date();
    const m = String(d.getMonth()+1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
  }

  // Intercetto i toggle
  root.addEventListener('change', async (e) => {
    const sw = e.target.closest('.js-toggle-attivo');
    if (!sw) return;

    const tipo = sw.dataset.tipo;
    const id   = parseInt(sw.dataset.id, 10);
    const val  = sw.checked ? 1 : 0;

    // Se manca bootstrap modal, fallback: conferma base solo su cascata "potenziale"
    const hasModal = !!modal;

    // Regola: mostro modale SOLO se è edificio/piano e l’azione causa cascata (off_only e val=0) o always
    let mustAsk = false;
    if (tipo === 'edificio' || tipo === 'piano') {
      mustAsk = (CASCADE === 'always') || (CASCADE === 'off_only' && val === 0);
    }

    // se devo chiedere conferma: faccio preview e apro modale
    if (mustAsk && hasModal) {
      // metto subito lo switch in disabled (evita doppio click) ma NON salvo
      setSaving(sw, true);

      try {
        const pre = await previewCascade(tipo, id, val);
        const m = makeMessage(pre);

        msgEl.textContent = m.title;
        hintEl.textContent = m.hint;

        pending = { sw, tipo, id, val };

        // colore bottone conferma coerente
        btnOk.classList.toggle('btn-danger', val === 0);
        btnOk.classList.toggle('btn-success', val === 1);

        modal.show();
      } catch(err) {
        alert(err.message || 'Errore preview');
        // rollback immediato
        sw.checked = !sw.checked;
        setSaving(sw, false);
      }
      return;
    }

    // Se non devo chiedere, salvo subito
    setSaving(sw, true);

    try {
      await doToggle(tipo, id, val);
      await reloadUI(); // sempre dal DB
    } catch(err) {
      alert(err.message || 'Errore salvataggio');
      // rollback UI
      sw.checked = !sw.checked;
    } finally {
      setSaving(sw, false);
    }
  });

  // Apertura modale schedulazione
  root.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-btn-schedule');
    if (!btn) return;
    e.stopPropagation();
    if (!scheduleModal) return;

    scheduleTipo.value = btn.dataset.tipo || '';
    scheduleId.value = btn.dataset.id || '';
    scheduleLabel.textContent = btn.dataset.label || btn.dataset.nome || `${btn.dataset.tipo} #${btn.dataset.id}`;
    scheduleAction.value = btn.dataset.current === '1' ? '0' : '1';
    scheduleStart.value = todayStr();
    scheduleEnd.value = '';

    scheduleModal.show();
  });

  // Salvataggio schedulazione
  scheduleForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!scheduleModal) return;

    btnScheduleSubmit.disabled = true;
    try {
      const fd = new FormData(scheduleForm);
      fd.append('cascade', CASCADE);

      const res = await fetch('struttura_schedule_save.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const j = await res.json().catch(()=>null);
      if(!j || !j.ok) throw new Error((j && j.msg) ? j.msg : 'Errore salvataggio');

      alert(j.msg || 'Schedulazione salvata');
      await reloadUI();
      scheduleModal.hide();
    } catch(err){
      alert(err.message || 'Errore salvataggio');
    } finally {
      btnScheduleSubmit.disabled = false;
    }
  });

  // Conferma dalla modale
  btnOk.addEventListener('click', async () => {
    if (!pending) return;

    const { sw, tipo, id, val } = pending;
    pending = null;
    modal.hide();

    try {
      await doToggle(tipo, id, val);
      await reloadUI();
    } catch(err) {
      alert(err.message || 'Errore salvataggio');
      // rollback (lui aveva già cambiato)
      sw.checked = !sw.checked;
    } finally {
      setSaving(sw, false);
    }
  });

  // Se annulli la modale → rollback dello switch
  modalEl?.addEventListener('hidden.bs.modal', () => {
    if (!pending) return;
    const { sw } = pending;
    // rollback: torniamo allo stato prima del change
    sw.checked = !sw.checked;
    setSaving(sw, false);
    pending = null;
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
