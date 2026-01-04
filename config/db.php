<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

define('APP_NAME', 'Hotel Manager');
define('BASE_URL', '/hotel');

$db_host = '';
$db_user = '';
$db_pass = '';
$db_name = 'Hotel';
$db_port = 3306;

$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($mysqli->connect_errno) {
  http_response_code(500);
  echo "Errore DB: impossibile connettersi.";
  error_log('DB connection error: ' . $mysqli->connect_error);
  exit;
}

if (!$mysqli->set_charset('utf8mb4')) {
  error_log('DB charset error: ' . $mysqli->error);
}

// Alias for legacy code that may still reference $conn
$conn = $mysqli;

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
