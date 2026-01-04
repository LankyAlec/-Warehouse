<?php
require_once '../config/db.php';

if (isset($_SESSION['utente_id'])) {
    header("Location: ../dashboard.php");
    exit;
}
?>

<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Login | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container vh-100 d-flex justify-content-center align-items-center">
    <div class="card shadow-sm" style="width: 360px;">
        <div class="card-body">
            <h4 class="text-center mb-4"><?= APP_NAME ?></h4>

            <?php if (!empty($_GET['error'])): ?>
                <div class="alert alert-danger small">
                    Credenziali non valide
                </div>
            <?php endif; ?>

            <form method="post" action="login_check.php">
                <div class="mb-3">
                    <label class="form-label">Username o Email</label>
                    <input type="text" name="login" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button class="btn btn-primary w-100">Accedi</button>
                <div class="text-center mt-3 small">
                  <a href="forgot.php">Password dimenticata?</a>
                  <span class="mx-2">•</span>
                  <a href="register.php">Registrati</a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
