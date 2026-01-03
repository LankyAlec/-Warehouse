<?php
declare(strict_types=1);
$PAGE_TITLE = 'Login';
require __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h4 mb-3">Accedi</h1>
        <p class="text-secondary">Funzionalità di autenticazione in arrivo. Nel frattempo, questo spazio può ospitare il form di accesso.</p>
        <form>
          <div class="mb-3">
            <label class="form-label" for="login-email">Email</label>
            <input class="form-control" type="email" id="login-email" placeholder="you@example.com" disabled>
          </div>
          <div class="mb-3">
            <label class="form-label" for="login-password">Password</label>
            <input class="form-control" type="password" id="login-password" placeholder="••••••••" disabled>
          </div>
          <button class="btn btn-primary w-100" type="button" disabled>Login</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
