<?php
require_once '../config/db.php';

if (!empty($_SESSION['utente_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$err = $_GET['err'] ?? '';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Registrazione | <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container vh-100 d-flex justify-content-center align-items-center">
  <div class="card shadow-sm" style="width: 420px;">
    <div class="card-body">
      <h4 class="mb-3">Registrazione</h4>

      <?php if ($err): ?>
        <div class="alert alert-danger small">
          <?= htmlspecialchars($err) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="register_save.php" autocomplete="off">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Nome</label>
            <input class="form-control" name="nome" required>
          </div>
          <div class="col-6">
            <label class="form-label">Cognome</label>
            <input class="form-control" name="cognome" required>
          </div>
        </div>

        <div class="mt-2">
          <label class="form-label">Username</label>
          <input class="form-control" name="username" required>
        </div>

        <div class="mt-2">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required>
        </div>

        <div class="mt-2">
          <label class="form-label">Password</label>
          <input class="form-control" type="password" name="password" minlength="8" required>
          <div class="form-text">Minimo 8 caratteri.</div>
        </div>

        <button class="btn btn-primary w-100 mt-3">Invia richiesta</button>

        <div class="text-center mt-3 small">
          Hai già un account? <a href="login.php">Accedi</a>
        </div>
      </form>

    </div>
  </div>
</div>
</body>
</html>
