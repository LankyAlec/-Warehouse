<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

$db_host = 'localhost';
$db_user = 'LankyAlec';
$db_pass = '-Alessio89-';
$db_name = 'magazzino';
$db_port = 3306;

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$conn) {
  http_response_code(500);
  echo "Errore DB: impossibile connettersi.";
  exit;
}
mysqli_set_charset($conn, 'utf8mb4');

function esc(mysqli $conn, string $s): string {
  return mysqli_real_escape_string($conn, $s);
}

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
  header('Location: ' . $url);
  exit;
}