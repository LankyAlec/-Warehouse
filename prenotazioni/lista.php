<?php
require_once __DIR__ . '/../includes/header.php';

if (!$isRoot && !in_gruppo('Reception')) {
    redirect('/dashboard.php');
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0">Prenotazioni</h3>
    <div class="text-muted small">Gestione rapida con salvataggio automatico e controlli disponibilità</div>
  </div>

  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#nuovaPrenotazioneModal">
      <i class="bi bi-plus-lg"></i> Nuova prenotazione
    </button>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h5 class="mb-1">Elenco</h5>
            <div class="text-muted small">Modifica inline (blur) con auto-save; conflitti gestiti con toast</div>
          </div>
          <button class="btn btn-outline-secondary btn-sm" id="refreshBtn">
            <i class="bi bi-arrow-repeat"></i> Aggiorna
          </button>
        </div>

        <div class="table-responsive">
          <table class="table align-middle table-sm" id="prenotazioniTable">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Ospite / riferimento</th>
                <th>Camera</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Stato</th>
                <th>Note</th>
                <th class="text-end">Azioni</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <div class="alert alert-info small mb-0">
          <i class="bi bi-info-circle"></i>
          Suggerimento: cambia un campo e lascia il focus (blur) per salvare automaticamente. Il sistema verifica
          la disponibilità camera prima di confermare.
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <h5 class="mb-2">Disponibilità rapida</h5>
        <div class="text-muted small mb-2">Controlla la disponibilità in tempo reale senza creare/modificare</div>
        <form id="checkForm" class="row g-2">
          <div class="col-12">
            <label class="form-label small mb-1">Camera</label>
            <select class="form-select" name="camera_id" required></select>
          </div>
          <div class="col-6">
            <label class="form-label small mb-1">Check-in</label>
            <input type="date" class="form-control" name="data_checkin" required>
          </div>
          <div class="col-6">
            <label class="form-label small mb-1">Check-out</label>
            <input type="date" class="form-control" name="data_checkout" required>
          </div>
          <div class="col-12 d-grid mt-1">
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> Verifica</button>
          </div>
        </form>

        <div id="availabilityResult" class="mt-3"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modale nuova prenotazione -->
<div class="modal fade" id="nuovaPrenotazioneModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nuova prenotazione</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="nuovaPrenotazioneForm" class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label small">Ospite / riferimento</label>
            <input class="form-control" name="referente" placeholder="Es. Mario Rossi">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small">Camera</label>
            <select class="form-select" name="camera_id" required></select>
          </div>
          <div class="col-6">
            <label class="form-label small">Check-in</label>
            <input type="date" class="form-control" name="data_checkin" required>
          </div>
          <div class="col-6">
            <label class="form-label small">Check-out</label>
            <input type="date" class="form-control" name="data_checkout" required>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small">Stato</label>
            <select class="form-select" name="stato">
              <option value="prenotato">Prenotato</option>
              <option value="occupato">Occupato</option>
              <option value="annullato">Annullato</option>
            </select>
          </div>
          <div class="col-12 col-md-8">
            <label class="form-label small">Note</label>
            <input class="form-control" name="note" placeholder="Richieste, orari, ecc.">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
        <button class="btn btn-primary" id="createBookingBtn"><i class="bi bi-save"></i> Salva</button>
      </div>
    </div>
  </div>
</div>

<!-- Modale documenti ospite -->
<div class="modal fade" id="documentiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Documenti ospiti</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="documentiContainer"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
      </div>
    </div>
  </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastArea" style="z-index: 1080;"></div>

<style>
  #prenotazioniTable input,
  #prenotazioniTable select,
  #prenotazioniTable textarea {
    min-width: 120px;
  }
  #prenotazioniTable textarea.form-control {
    min-height: 36px;
  }
</style>

<script>
  const tableBody = document.querySelector('#prenotazioniTable tbody');
  const refreshBtn = document.querySelector('#refreshBtn');
  const checkForm = document.querySelector('#checkForm');
  const availabilityResult = document.querySelector('#availabilityResult');
  const toastArea = document.querySelector('#toastArea');
  const nuovaPrenotazioneModal = document.getElementById('nuovaPrenotazioneModal');
  const createBookingBtn = document.getElementById('createBookingBtn');
  const nuovaPrenotazioneForm = document.getElementById('nuovaPrenotazioneForm');
  const documentiModal = document.getElementById('documentiModal');
  const documentiContainer = document.getElementById('documentiContainer');

  let meta = { camere: [], stati: [] };

  function showToast(message, variant = 'primary') {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
      <div class="toast align-items-center text-bg-${variant} border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">${message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;
    const toastEl = wrapper.firstElementChild;
    toastArea.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
  }

  async function fetchJson(url, options = {}) {
    const res = await fetch(url, Object.assign({
      headers: { 'Content-Type': 'application/json' }
    }, options));
    return res.json();
  }

  function populateCamereSelect(selectEl) {
    selectEl.innerHTML = '';
    meta.camere.forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = `${c.codice}${c.nome ? ' — ' + c.nome : ''}`;
      selectEl.appendChild(opt);
    });
  }

  async function loadMeta() {
    const data = await fetchJson('prenotazioni_ajax.php?action=metadata');
    if (data.ok) {
      meta = data;
      const selects = document.querySelectorAll('select[name="camera_id"]');
      selects.forEach(populateCamereSelect);
    } else {
      showToast(data.message || 'Impossibile caricare le camere', 'danger');
    }
  }

  function renderBookings(bookings) {
    tableBody.innerHTML = '';
    bookings.forEach(b => {
      const tr = document.createElement('tr');
      tr.dataset.id = b.id;
      tr.innerHTML = `
        <td class="text-muted small">#${b.id}</td>
        <td>
          <input class="form-control form-control-sm js-auto-save" data-field="referente" value="${b.referente ?? ''}" placeholder="Riferimento">
          <div class="small text-muted">Ospiti: ${b.ospiti}</div>
        </td>
        <td>
          <select class="form-select form-select-sm js-auto-save" data-field="camera_id">
            ${meta.camere.map(c => `<option value="${c.id}" ${c.id === b.camera_id ? 'selected' : ''}>${c.codice}${c.nome ? ' — ' + c.nome : ''}</option>`).join('')}
          </select>
        </td>
        <td>
          <input type="date" class="form-control form-control-sm js-auto-save" data-field="data_checkin" value="${b.checkin}">
        </td>
        <td>
          <input type="date" class="form-control form-control-sm js-auto-save" data-field="data_checkout" value="${b.checkout}">
        </td>
        <td>
          <select class="form-select form-select-sm js-auto-save" data-field="stato">
            ${meta.stati.map(s => `<option value="${s}" ${s === b.stato ? 'selected' : ''}>${s}</option>`).join('')}
          </select>
        </td>
        <td>
          <textarea class="form-control form-control-sm js-auto-save" data-field="note" rows="1" placeholder="Note">${b.note ?? ''}</textarea>
        </td>
        <td class="text-end">
          <button class="btn btn-outline-secondary btn-sm js-documenti" data-id="${b.id}">
            <i class="bi bi-file-earmark-text"></i> Documenti
          </button>
        </td>
      `;
      tableBody.appendChild(tr);
    });
  }

  async function loadBookings() {
    const data = await fetchJson('prenotazioni_ajax.php?action=list');
    if (data.ok) {
      renderBookings(data.bookings || []);
    } else {
      showToast(data.message || 'Errore nel caricamento', 'danger');
    }
  }

  async function checkAvailability(cameraId, checkin, checkout, id) {
    const data = await fetchJson('prenotazioni_ajax.php', {
      method: 'POST',
      body: JSON.stringify({
        action: 'check_availability',
        camera_id: cameraId,
        data_checkin: checkin,
        data_checkout: checkout,
        id
      })
    });
    if (data.toast?.variant) {
      showToast(data.message, data.toast.variant === 'warning' ? 'warning' : 'success');
    }
    return data.available !== false;
  }

  async function saveField(id, field, value) {
    const payload = { action: 'save_booking', id };
    payload[field] = value;

    const row = tableBody.querySelector(`tr[data-id="${id}"]`);
    const camera = row?.querySelector('[data-field="camera_id"]')?.value;
    const checkin = row?.querySelector('[data-field="data_checkin"]')?.value;
    const checkout = row?.querySelector('[data-field="data_checkout"]')?.value;

    if (['camera_id', 'data_checkin', 'data_checkout'].includes(field) && camera && checkin && checkout) {
        const available = await checkAvailability(parseInt(camera, 10), checkin, checkout, id);
        if (!available) {
          await loadBookings();
          return;
        }
        Object.assign(payload, { camera_id: camera, data_checkin: checkin, data_checkout: checkout });
    }

    const data = await fetchJson('prenotazioni_ajax.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    if (data.toast?.variant) {
      showToast(data.message, data.toast.variant);
    } else {
      showToast(data.message, data.ok ? 'success' : 'danger');
    }
    if (data.ok) {
      loadBookings();
    }
  }

  tableBody.addEventListener('change', (ev) => {
    const target = ev.target.closest('.js-auto-save');
    if (!target) return;
    const row = target.closest('tr');
    saveField(parseInt(row.dataset.id, 10), target.dataset.field, target.value);
  });

  tableBody.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.js-documenti');
    if (!btn) return;
    const bookingId = parseInt(btn.dataset.id, 10);
    await loadDocumenti(bookingId);
    const modal = new bootstrap.Modal(documentiModal);
    modal.show();
  });

  refreshBtn.addEventListener('click', loadBookings);

  checkForm.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const data = new FormData(checkForm);
    const cameraId = parseInt(data.get('camera_id'), 10);
    const checkin = data.get('data_checkin');
    const checkout = data.get('data_checkout');
    const res = await checkAvailability(cameraId, checkin, checkout, null);
    availabilityResult.innerHTML = res
      ? '<div class="alert alert-success mb-0">La camera è disponibile</div>'
      : '<div class="alert alert-warning mb-0">La camera è occupata nel periodo selezionato</div>';
  });

  createBookingBtn.addEventListener('click', async () => {
    const data = new FormData(nuovaPrenotazioneForm);
    const payload = Object.fromEntries(data.entries());
    payload.action = 'save_booking';
    const res = await fetchJson('prenotazioni_ajax.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    showToast(res.message, res.toast?.variant || (res.ok ? 'success' : 'danger'));
    if (res.ok) {
      bootstrap.Modal.getInstance(nuovaPrenotazioneModal)?.hide();
      nuovaPrenotazioneForm.reset();
      loadBookings();
    }
  });

  async function loadDocumenti(bookingId) {
    documentiContainer.innerHTML = '<div class="text-center py-4 text-muted">Caricamento...</div>';
    const res = await fetchJson('ospiti_ajax.php', {
      method: 'POST',
      body: JSON.stringify({ action: 'list', soggiorno_id: bookingId })
    });
    if (!res.ok) {
      documentiContainer.innerHTML = `<div class="alert alert-danger">${res.message || 'Errore'}</div>`;
      return;
    }

    if (!res.ospiti || res.ospiti.length === 0) {
      documentiContainer.innerHTML = '<div class="alert alert-info mb-0">Nessun ospite associato.</div>';
      return;
    }

    documentiContainer.innerHTML = res.ospiti.map(o => `
      <div class="border rounded p-3 mb-3" data-cliente="${o.id}" data-soggiorno="${bookingId}">
        <div class="fw-semibold mb-2">${(o.nome || '')} ${(o.cognome || '')}</div>
        <div class="row g-2">
          <div class="col-6 col-md-4">
            <label class="form-label small mb-1">Tipo documento</label>
            <input class="form-control form-control-sm" name="documento_tipo" value="${o.documento_tipo ?? ''}">
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label small mb-1">Numero</label>
            <input class="form-control form-control-sm" name="documento_numero" value="${o.documento_numero ?? ''}">
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label small mb-1">Scadenza</label>
            <input type="date" class="form-control form-control-sm" name="documento_scadenza" value="${o.documento_scadenza ?? ''}">
          </div>
          <div class="col-6 col-md-6">
            <label class="form-label small mb-1">Rilasciato da</label>
            <input class="form-control form-control-sm" name="documento_rilasciato_da" value="${o.documento_rilasciato_da ?? ''}">
          </div>
          <div class="col-12">
            <label class="form-label small mb-1">Note</label>
            <input class="form-control form-control-sm" name="documento_note" value="${o.documento_note ?? ''}">
          </div>
        </div>
        <div class="text-end mt-2">
          <button class="btn btn-outline-primary btn-sm js-save-doc"><i class="bi bi-save"></i> Salva documenti</button>
        </div>
      </div>
    `).join('');
  }

  documentiContainer.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.js-save-doc');
    if (!btn) return;
    const wrapper = btn.closest('[data-cliente]');
    const clienteId = parseInt(wrapper.dataset.cliente, 10);
    const soggiornoId = parseInt(wrapper.dataset.soggiorno, 10);
    const inputs = wrapper.querySelectorAll('input[name]');
    const payload = { action: 'save_documenti', cliente_id: clienteId, soggiorno_id: soggiornoId };
    inputs.forEach(i => payload[i.name] = i.value);
    const res = await fetchJson('ospiti_ajax.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    showToast(res.message, res.toast?.variant || (res.ok ? 'success' : 'danger'));
  });

  (async () => {
    await loadMeta();
    await loadBookings();
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
