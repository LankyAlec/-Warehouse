<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$PAGE_TITLE = $PAGE_TITLE ?? 'Magazzino';
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
<div class="container py-4">
