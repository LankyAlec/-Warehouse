<?php
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die("ID non valido."); }

$stmt = $mysqli->prepare("SELECT * FROM utenti WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$utente = $stmt->get_result()->fetch_assoc();
if (!$utente) { die("Utente non trovato."); }

$gruppi = $mysqli->query("SELECT id, codice, nome FROM gruppi ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);

$stmt = $mysqli->prepare("SELECT gruppo_id FROM utenti_gruppi WHERE utente_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$ug = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$gruppiSelezionati = array_flip(array_map(fn($r)=> (int)$r['gruppo_id'], $ug));

$permessi = $mysqli->query("SELECT id, codice, descrizione FROM permessi ORDER BY codice ASC")->fetch_all(MYSQLI_ASSOC);

$stmt = $mysqli->prepare("SELECT permesso_id, valore FROM permessi_utenti WHERE utente_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$pu = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$permessiUtente = [];
foreach ($pu as $r) $permessiUtente[(int)$r['permesso_id']] = $r['valore'];
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Gestisci utente</h3>
        <div class="text-muted small"><?= h($utente['username']) ?> — <?= h($utente['email']) ?></div>
    </div>
    <a class="btn btn-outline-secondary" href="utenti.php">← Torna</a>
</div>

<form method="post" action="utente_save.php" class="row g-3">
    <input type="hidden" name="azione" value="salva_utente">
    <input type="hidden" name="id" value="<?= (int)$utente['id'] ?>">

    <div class="col-12 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5 class="mb-3">Dati principali</h5>

                <div class="mb-2">
                    <label class="form-label">Nome</label>
                    <input class="form-control" name="nome" value="<?= h($utente['nome']) ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label">Cognome</label>
                    <input class="form-control" name="cognome" value="<?= h($utente['cognome']) ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" value="<?= h($utente['username']) ?>" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" value="<?= h($utente['email']) ?>" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Privilegi</label>
                    <select class="form-select" name="privilegi">
                        <option value="guest"    <?= $utente['privilegi']==='guest' ? 'selected' : '' ?>>guest</option>
                        <option value="standard" <?= $utente['privilegi']==='standard' ? 'selected' : '' ?>>standard</option>
                        <option value="root"     <?= $utente['privilegi']==='root' ? 'selected' : '' ?>>root</option>
                    </select>
                    <div class="form-text">Solo un root può promuovere a root.</div>
                </div>

                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="attivoSwitch" name="attivo"
                           value="1" <?= ((int)$utente['attivo']===1 ? 'checked' : '') ?>>
                    <label class="form-check-label" for="attivoSwitch">Utente attivo</label>
                </div>

                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="richiestaSwitch" name="richiesta_registrazione"
                           value="1" <?= ((int)$utente['richiesta_registrazione']===1 ? 'checked' : '') ?>>
                    <label class="form-check-label" for="richiestaSwitch">Flag “richiesta registrazione”</label>
                </div>

                <hr>

                <div class="mb-2">
                    <label class="form-label">Nuova password (opzionale)</label>
                    <input class="form-control" type="password" name="nuova_password" minlength="8" placeholder="Lascia vuoto per non cambiare">
                    <div class="form-text">Minimo 8 caratteri.</div>
                </div>

                <button class="btn btn-primary w-100 mt-3">Salva</button>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h5 class="mb-3">Gruppi (multi-selezione)</h5>

                <div class="row">
                    <?php foreach ($gruppi as $g): ?>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="gruppi[]" value="<?= (int)$g['id'] ?>"
                                       id="g<?= (int)$g['id'] ?>"
                                       <?= isset($gruppiSelezionati[(int)$g['id']]) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="g<?= (int)$g['id'] ?>">
                                    <?= h($g['nome']) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-text mt-2">
                    I gruppi servono per menu e permessi “di default” (via permessi_gruppi).
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="mb-3">Permessi utente (override)</h5>
                <div class="text-muted small mb-2">
                    Se non imposti nulla qui, valgono i permessi del gruppo. Qui puoi “consenti” o “nega”.
                </div>

                <div class="table-responsive" style="max-height: 420px; overflow:auto;">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Permesso</th>
                                <th class="text-end">Override</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($permessi as $p): ?>
                            <?php $pid = (int)$p['id']; $val = $permessiUtente[$pid] ?? ''; ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= h($p['codice']) ?></div>
                                    <div class="text-muted small"><?= h($p['descrizione']) ?></div>
                                </td>
                                <td class="text-end" style="min-width:220px;">
                                    <select class="form-select form-select-sm" name="permesso_override[<?= $pid ?>]">
                                        <option value="" <?= ($val==='' ? 'selected' : '') ?>>(nessuno)</option>
                                        <option value="consenti" <?= ($val==='consenti' ? 'selected' : '') ?>>consenti</option>
                                        <option value="nega" <?= ($val==='nega' ? 'selected' : '') ?>>nega</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-text mt-2">
                    “nessuno” = rimuove override e lascia decidere al gruppo.
                </div>
            </div>
        </div>

    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
