<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');
if (!function_exists('require_root')) { function require_root(){} }
require_root();

$tipo=$_POST['tipo']??'';
$id=(int)($_POST['id']??0);
$val=(int)($_POST['val']??0);

if(!in_array($tipo,['edificio','piano','camera'])||$id<=0||!in_array($val,[0,1])){
  echo json_encode(['ok'=>false,'msg'=>'Parametri non validi']);exit;
}

$mysqli->begin_transaction();
try{

if($tipo==='edificio'){
  $mysqli->query("UPDATE edifici SET attivo=$val WHERE id=$id");
  if($val===0){
    $mysqli->query("UPDATE piani SET attivo=0 WHERE edificio_id=$id");
    $mysqli->query("UPDATE camere c JOIN piani p ON p.id=c.piano_id SET c.attiva=0 WHERE p.edificio_id=$id");
  }
}

if($tipo==='piano'){
  $mysqli->query("UPDATE piani SET attivo=$val WHERE id=$id");
  if($val===0){
    $mysqli->query("UPDATE camere SET attiva=0 WHERE piano_id=$id");
  }
}

if($tipo==='camera'){
  $mysqli->query("UPDATE camere SET attiva=$val WHERE id=$id");
}

$mysqli->commit();
echo json_encode(['ok'=>true]);exit;

}catch(Throwable $e){
  $mysqli->rollback();
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);exit;
}