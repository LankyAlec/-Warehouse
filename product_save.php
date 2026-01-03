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

$anno = trim((string)($_POST['anno_produzione'] ?? ''));
$anno_produzione = null;
if ($anno !== '' && preg_match('/^\d{4}$/', $anno)) $anno_produzione = (int)$anno;

$prezzo = qfloat($_POST['prezzo'] ?? '0', 0);
$scaffale = trim((string)($_POST['scaffale'] ?? ''));
$ripiano  = trim((string)($_POST['ripiano'] ?? ''));

$quantita = qfloat($_POST['quantita'] ?? '0', 0);
$unita = (string)($_POST['unita'] ?? 'pz');
$allowedU = ['pz','kg','g','l','ml','altro'];
if (!in_array($unita, $allowedU, true)) $unita = 'pz';

$data_scadenza = trim((string)($_POST['data_scadenza'] ?? ''));
if ($data_scadenza === '' || !is_valid_date($data_scadenza)) $data_scadenza = null;

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
            SET id_magazzino=:mid, id_categoria=:cat, nome=:nome, descrizione=:descr,
                anno_produzione=:anno, prezzo=:prezzo, scaffale=:scaff, ripiano=:ripi,
                quantita=:qta, unita=:unita, data_scadenza=:scad
            WHERE id=:id
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':mid' => $mid,
      ':cat' => $id_categoria,
      ':nome' => $nome,
      ':descr' => ($descrizione !== '' ? $descrizione : null),
      ':anno' => $anno_produzione,
      ':prezzo' => number_format($prezzo, 2, '.', ''),
      ':scaff' => ($scaffale !== '' ? $scaffale : null),
      ':ripi' => ($ripiano !== '' ? $ripiano : null),
      ':qta' => $quantita,
      ':unita' => $unita,
      ':scad' => $data_scadenza,
      ':id' => $id,
    ]);
    flash_set('success', 'Prodotto aggiornato');
  } else {
    $sql = "INSERT INTO prodotti
            (id_magazzino, id_categoria, nome, descrizione, anno_produzione, prezzo, scaffale, ripiano, quantita, unita, data_scadenza)
            VALUES
            (:mid, :cat, :nome, :descr, :anno, :prezzo, :scaff, :ripi, :qta, :unita, :scad)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':mid' => $mid,
      ':cat' => $id_categoria,
      ':nome' => $nome,
      ':descr' => ($descrizione !== '' ? $descrizione : null),
      ':anno' => $anno_produzione,
      ':prezzo' => number_format($prezzo, 2, '.', ''),
      ':scaff' => ($scaffale !== '' ? $scaffale : null),
      ':ripi' => ($ripiano !== '' ? $ripiano : null),
      ':qta' => $quantita,
      ':unita' => $unita,
      ':scad' => $data_scadenza,
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
