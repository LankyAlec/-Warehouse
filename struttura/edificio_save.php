<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

$id   = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$note = trim($_POST['note'] ?? '');

if($nome==='') die("Nome mancante");

if($id>0){
  $stmt=$mysqli->prepare("UPDATE edifici SET nome=?, note=? WHERE id=?");
  $stmt->bind_param("ssi",$nome,$note,$id);
}else{
  $stmt=$mysqli->prepare("INSERT INTO edifici (nome,note) VALUES (?,?)");
  $stmt->bind_param("ss",$nome,$note);
}
$stmt->execute();
header("Location: struttura.php");
