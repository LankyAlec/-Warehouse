<?php
require_once '../config/db.php';
$ok = !empty($_GET['ok']);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Recupero password | <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container vh-100 d-flex justify-content-center align-items-center">
  <div class="card shadow-sm" style="width: 420px;">
    <div class="card-body">
      <h4 class="mb-3">Recupero password</h4>

      <?php if ($ok): ?>
        <div class="alert alert-success small">
          Se l’email esiste, riceverai un link per reimpostare la password.
        </div>
      <?php endif; ?>

      <form method="post" action="forgot_send.php">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" required>
        <button class="btn btn-primary w-100 mt-3">Invia link</button>
      </form>

      <div class="text-center mt-3 small">
        <a href="login.php">Torna al login</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
