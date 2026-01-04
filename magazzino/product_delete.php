<?php
// prodotto_delete.php
declare(strict_types=1);
require __DIR__ . '/init.php';

$id  = qint($_GET['id'] ?? 0, 0);
$mid = qint($_GET['mid'] ?? 0, 0);

if ($id <= 0) {
  flash_set('danger', 'ID non valido');
  mag_redirect('magazzini.php?mid='.$mid);
}

try {
  $stmt = $pdo->prepare("DELETE FROM prodotti WHERE id = :id LIMIT 1");
  $stmt->execute([':id' => $id]);
  flash_set('success', 'Prodotto eliminato');
} catch (Throwable $e) {
  error_log("prodotto_delete.php: ".$e->getMessage());
  flash_set('danger', 'Errore eliminazione (controlla error_log PHP).');
}

mag_redirect('magazzini.php?mid='.$mid);
