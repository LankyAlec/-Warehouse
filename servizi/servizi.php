<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

/* Tipi presi dal DB (solo quelli esistenti) */
$tipi = [];
$resTipi = $mysqli->query("SELECT DISTINCT tipo FROM servizi ORDER BY tipo ASC");
if ($resTipi) {
    while ($r = $resTipi->fetch_assoc()) {
        if ($r['tipo'] !== null && $r['tipo'] !== '') $tipi[] = $r['tipo'];
    }
}
?>

<style>
  /* colonna azioni: 3 bottoni sempre allineati */
  .actions-grid{
    display: grid;
    grid-template-columns: repeat(3, 72px);
    gap: 8px;
    justify-content: end;
    align-items: center;
  }

  /* bottoni tutti uguali */
  .btn-action{
    width: 72px;
    height: 36px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
  }

  /* placeholder per “Tariffe” nei figli */
  .actions-empty{
    width: 72px;
    height: 36px;
  }

  /* stato: switch + badge centrati e compatti */
  .stato-wrap{
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }

  /* badge a larghezza fissa */
  .badge-stato{
    width: 90px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }


</style>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0"><i class="bi bi-stars"></i> Gestione Servizi</h3>
    <div class="text-muted small">Solo root</div>
  </div>

  <a class="btn btn-primary" href="servizio_edit.php">
    <i class="bi bi-plus-circle"></i> Nuovo servizio
  </a>
</div>

<!-- Filtri (realtime) -->
<div class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <input id="q"
           class="form-control form-control-lg"
           placeholder="Cerca per nome servizio..."
           autocomplete="off">
  </div>
</div>


<!-- Risultati -->
<div class="card shadow-sm border-0">
  <div class="card-body">

    <div id="loading" class="alert alert-info d-none mb-3">
      <i class="bi bi-arrow-repeat"></i> Aggiornamento...
    </div>

    <div id="results"></div>

  </div>
</div>

<script>
(function () {
  const qEl = document.getElementById('q');
  const resultsEl = document.getElementById('results');
  const loadingEl = document.getElementById('loading');

  let t = null;
  let lastController = null;

  // pagina corrente (default 1)
  let currentPage = 1;

  function debounce(fn, ms) {
    return function () {
      clearTimeout(t);
      t = setTimeout(fn, ms);
    };
  }

  async function load() {
    // abort chiamata precedente
    if (lastController) lastController.abort();
    lastController = new AbortController();

    const params = new URLSearchParams();
    params.set('q', (qEl.value || '').trim());
    params.set('page', String(currentPage));

    loadingEl.classList.remove('d-none');

    try {
      const res = await fetch('servizi_ajax.php?' + params.toString(), {
        signal: lastController.signal,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const html = await res.text();
      resultsEl.innerHTML = html;

    } catch (e) {
      if (e.name !== 'AbortError') {
        resultsEl.innerHTML = "<div class='alert alert-danger'>Errore nel caricamento.</div>";
      }
    } finally {
      loadingEl.classList.add('d-none');
    }
  }

  const loadDebounced = debounce(load, 250);

  // ricerca realtime: reset pagina a 1
  qEl.addEventListener('input', function () {
    currentPage = 1;
    loadDebounced();
  });

  // paginazione: UN solo listener (fuori da load)
  resultsEl.addEventListener('click', function (e) {
    const a = e.target.closest('a[data-page]');
    if (!a) return;

    e.preventDefault();
    const p = parseInt(a.getAttribute('data-page'), 10);
    if (!p || p < 1) return;

    currentPage = p;
    load();
  });

  // primo caricamento
  load();
})();
</script>

<script>
(function(){
  const resultsEl = document.getElementById('results');

  resultsEl.addEventListener('change', async (e) => {
    const sw = e.target.closest('.js-attivo-switch');
    if(!sw) return;

    const id = sw.getAttribute('data-id');
    const attivo = sw.checked ? 1 : 0;

    // UI ottimistica: aggiorno badge subito
    const badge = resultsEl.querySelector('.js-stato-badge[data-id="'+id+'"]');
    if (badge){
      badge.classList.remove('bg-success','bg-danger');
      badge.classList.add(attivo ? 'bg-success' : 'bg-danger');
      badge.textContent = attivo ? 'Attivo' : 'Disattivo';
    }

    const fd = new FormData();
    fd.append('id', id);
    fd.append('attivo', String(attivo));

    try{
      const res = await fetch('servizio_toggle_ajax.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      // se HTTP non ok, leggo testo (tipico: 404 HTML)
      if (!res.ok){
        const t = await res.text();
        alert('Errore toggle (HTTP ' + res.status + ')\n\n' + t);
        sw.checked = !sw.checked;
        // rollback badge
        if (badge){
          badge.classList.remove('bg-success','bg-danger');
          badge.classList.add(!attivo ? 'bg-success' : 'bg-danger');
          badge.textContent = !attivo ? 'Attivo' : 'Disattivo';
        }
        return;
      }

      const j = await res.json();
      if(!j.ok){
        alert(j.msg || 'Errore salvataggio stato');
        sw.checked = !sw.checked;
        if (badge){
          badge.classList.remove('bg-success','bg-danger');
          badge.classList.add(!attivo ? 'bg-success' : 'bg-danger');
          badge.textContent = !attivo ? 'Attivo' : 'Disattivo';
        }
      }

    }catch(err){
      alert('Errore rete nel salvataggio stato');
      sw.checked = !sw.checked;
      if (badge){
        badge.classList.remove('bg-success','bg-danger');
        badge.classList.add(!attivo ? 'bg-success' : 'bg-danger');
        badge.textContent = !attivo ? 'Attivo' : 'Disattivo';
      }
    }
  });
})();
</script>






<?php include __DIR__ . '/../includes/footer.php'; ?>
