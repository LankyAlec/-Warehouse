<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/pagamenti_lib.php';

$soggiornoId = (int)($_GET['id'] ?? 0);

if ($soggiornoId <= 0) {
    echo "<div class='alert alert-danger'>ID prenotazione non valido.</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$booking = load_booking($mysqli, $soggiornoId);
if (!$booking) {
    echo "<div class='alert alert-warning'>Prenotazione non trovata.</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

ensure_pagamenti_table($mysqli);
$totale = booking_total($booking);
$pagamenti = load_pagamenti($mysqli, $soggiornoId);
$panelHtml = render_pagamenti_panel($booking, $totale, $pagamenti);

$label = booking_label($booking);
$checkin = !empty($booking['data_checkin']) ? date('d/m/Y', strtotime($booking['data_checkin'])) : '—';
$checkout = !empty($booking['data_checkout']) ? date('d/m/Y', strtotime($booking['data_checkout'])) : '—';
$ospiti = (int)($booking['ospiti'] ?? 0);
$cameraId = (int)($booking['camera_id'] ?? 0);
$pasto = $booking['piano_pasto_sigla'] ?? '';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">Prenotazione #<?= (int)$soggiornoId ?></h1>
        <div class="text-secondary"><?= h($label) ?> <?= render_booking_badge($booking) ?></div>
    </div>
    <div class="text-end">
        <div class="text-uppercase small text-secondary">Totale previsto</div>
        <div class="fs-4 fw-bold"><?= h(format_currency($totale)) ?></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div id="js-payment-alert" class="alert d-none"></div>
        <div id="js-payments-panel">
            <?= $panelHtml ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h2 class="h5 mb-3">Riepilogo soggiorno</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-secondary text-uppercase small">Check-in</div>
                        <div class="fw-semibold"><?= h($checkin) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-secondary text-uppercase small">Check-out</div>
                        <div class="fw-semibold"><?= h($checkout) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-secondary text-uppercase small">Camera</div>
                        <div class="fw-semibold"><?= $cameraId > 0 ? 'Camera #' . (int)$cameraId : '—' ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-secondary text-uppercase small">Ospiti</div>
                        <div class="fw-semibold"><?= $ospiti > 0 ? (int)$ospiti : '—' ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-secondary text-uppercase small">Piano pasto</div>
                        <div class="fw-semibold"><?= $pasto !== '' ? h(strtoupper($pasto)) : '—' ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-secondary text-uppercase small">Stato</div>
                        <div class="fw-semibold"><?= render_booking_badge($booking) ?></div>
                    </div>
                    <?php if (!empty($booking['note'])): ?>
                        <div class="col-12">
                            <div class="text-secondary text-uppercase small">Note</div>
                            <div class="fw-semibold"><?= h((string)$booking['note']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            Usa il pannello pagamenti per registrare incassi parziali o saldare la prenotazione. Le esportazioni CSV/PDF includono la cronologia completa.
        </div>
    </div>
</div>

<script>
(function() {
    const panelContainer = document.getElementById('js-payments-panel');
    const alertBox = document.getElementById('js-payment-alert');
    const soggiornoId = <?= (int)$soggiornoId ?>;

    function showAlert(type, message) {
        if (!alertBox) return;
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function clearAlert() {
        if (!alertBox) return;
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    }

    function bindEvents() {
        const form = panelContainer.querySelector('#js-payment-form');
        const refresh = panelContainer.querySelector('.js-refresh-panel');

        if (form) {
            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                clearAlert();
                const fd = new FormData(form);
                fd.append('action', 'add');
                try {
                    const res = await fetch('pagamenti_ajax.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.ok) {
                        throw new Error(data.message || 'Errore sconosciuto');
                    }
                    panelContainer.innerHTML = data.html;
                    bindEvents();
                    showAlert('success', 'Pagamento registrato correttamente.');
                } catch (err) {
                    showAlert('danger', err.message);
                }
            });
        }

        if (refresh) {
            refresh.addEventListener('click', refreshPanel);
        }
    }

    async function refreshPanel() {
        clearAlert();
        try {
            const res = await fetch('pagamenti_ajax.php?action=panel&soggiorno_id=' + soggiornoId);
            const data = await res.json();
            if (!data.ok) {
                throw new Error(data.message || 'Impossibile aggiornare il pannello.');
            }
            panelContainer.innerHTML = data.html;
            bindEvents();
        } catch (err) {
            showAlert('danger', err.message);
        }
    }

    bindEvents();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
