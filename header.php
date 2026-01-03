<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$PAGE_TITLE = $PAGE_TITLE ?? 'Magazzino';
$currentPath = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$navActive = static function(array $names) use ($currentPath): string {
  return in_array($currentPath, $names, true) ? 'active fw-semibold' : '';
};
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($PAGE_TITLE) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    .toolbar-card { border: 0; border-radius: 1rem; box-shadow: 0 8px 25px rgba(0,0,0,.08); }
    .table-card { border: 0; border-radius: 1rem; box-shadow: 0 8px 25px rgba(0,0,0,.08); overflow: hidden; }
    .badge-soft { background: rgba(13,110,253,.1); color: #0d6efd; }
    .meta-right { color:#6c757d; font-size:.95rem; }
    .btn-icon { display:inline-flex; align-items:center; gap:.4rem; }
    .pagination .page-link { border-radius:.8rem; }
    .of-recbar { padding: .25rem .25rem; border-radius: 999px; }
    .of-badge{
      background:#0d6efd; color:#fff; border-radius:999px;
      padding:.45rem .75rem; font-weight:600;
    }
    .of-csvbtn{
      border-radius:999px;
      border:1px solid rgba(13,110,253,.35);
      background:#fff;
      padding:.35rem .55rem;
      line-height:1;
    }
    .of-csvbtn:hover{ background:rgba(13,110,253,.06); }
</style>

</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <i class="bi bi-box-seam"></i>
      <span>Magazzino</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= $navActive(['index.php']) ?>" href="index.php">Prodotti</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $navActive(['categories.php']) ?>" href="categories.php">Categorie</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $navActive(['magazzini.php']) ?>" href="magazzini.php">Magazzini</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $navActive(['fornitori.php']) ?>" href="fornitori.php">Fornitori</a>
        </li>
      </ul>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-light btn-sm" href="login.php">
          <i class="bi bi-person-circle me-1"></i>
          Login
        </a>
      </div>
    </div>
  </div>
</nav>
<div class="container py-4">
