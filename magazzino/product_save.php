<?php
// prodotto_save.php
declare(strict_types=1);
require __DIR__ . '/config.php';

$id  = qint($_POST['id'] ?? 0, 0);
$mid = qint($_POST['mid'] ?? 0, 0);

$nome        = trim((string)($_POST['nome'] ?? ''));
$descrizione = trim((string)($_POST['descrizione'] ?? ''));

$id_categoria = $_POST['id_categoria'] ?? null;
$id_categoria = ($id_categoria === '' || $id_categoria === null) ? null : qint($id_categoria, 1);

$unita = (string)($_POST['unita'] ?? 'pz');
$allowedU = ['pz','kg','g','l','ml','altro'];
if (!in_array($unita, $allowedU, true)) $unita = 'pz';

if ($nome === '') {
  flash_set('danger', 'Nome obbligatorio');
  header('Location: prodotto_form.php?mid='.$mid.($id>0?'&id='.$id:''));
  exit;
}
if ($mid <= 0) {
  flash_set('danger', 'Magazzino non valido');
  header('Location: index.php');
  exit;
}

try {
  if ($id > 0) {
    $sql = "UPDATE prodotti
            SET id_magazzino=:mid, id_categoria=:cat, nome=:nome, descrizione=:descr, unita=:unita
            WHERE id=:id
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':mid' => $mid,
      ':cat' => $id_categoria,
      ':nome' => $nome,
      ':descr' => ($descrizione !== '' ? $descrizione : null),
      ':unita' => $unita,
      ':id' => $id,
    ]);
    flash_set('success', 'Prodotto aggiornato');
  } else {
    $sql = "INSERT INTO prodotti
            (id_magazzino, id_categoria, nome, descrizione, unita)
            VALUES
            (:mid, :cat, :nome, :descr, :unita)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':mid' => $mid,
      ':cat' => $id_categoria,
      ':nome' => $nome,
      ':descr' => ($descrizione !== '' ? $descrizione : null),
      ':unita' => $unita,
    ]);
    flash_set('success', 'Prodotto creato');
  }
} catch (Throwable $e) {
  error_log("prodotto_save.php: ".$e->getMessage());
  flash_set('danger', 'Errore salvataggio (controlla error_log PHP).');
  header('Location: prodotto_form.php?mid='.$mid.($id>0?'&id='.$id:''));
  exit;
}

header('Location: index.php?mid='.$mid);
exit;