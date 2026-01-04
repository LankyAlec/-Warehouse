<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

define('APP_NAME', 'Hotel Manager');
define('BASE_URL', '/hotel');
define('MIGRATIONS_DIR', __DIR__ . '/migrations');

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

function run_migrations(mysqli $mysqli, string $dir): void {
  if (!is_dir($dir)) {
    return;
  }

  $createTableSql = "
    CREATE TABLE IF NOT EXISTS migrations (
      id INT AUTO_INCREMENT PRIMARY KEY,
      filename VARCHAR(255) NOT NULL UNIQUE,
      applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ";

  if (!$mysqli->query($createTableSql)) {
    throw new RuntimeException('Unable to initialize migrations table: ' . $mysqli->error);
  }

  $applied = [];
  $res = $mysqli->query("SELECT filename FROM migrations");
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $applied[$row['filename']] = true;
    }
    $res->free();
  }

  $files = glob($dir . '/*.sql');
  if ($files === false) {
    throw new RuntimeException('Unable to read migration directory.');
  }
  sort($files, SORT_NATURAL | SORT_FLAG_CASE);

  foreach ($files as $file) {
    $base = basename($file);
    if (isset($applied[$base])) {
      continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
      throw new RuntimeException("Impossibile leggere il file di migrazione {$base}");
    }
    $sql = trim($sql);
    if ($sql === '') {
      continue;
    }

    if (!$mysqli->multi_query($sql)) {
      throw new RuntimeException("Migrazione {$base} fallita: " . $mysqli->error);
    }

    do {
      if ($result = $mysqli->store_result()) {
        $result->free();
      }
    } while ($mysqli->more_results() && $mysqli->next_result());

    if ($mysqli->errno) {
      throw new RuntimeException("Migrazione {$base} fallita: " . $mysqli->error);
    }

    $stmt = $mysqli->prepare("INSERT INTO migrations (filename) VALUES (?)");
    if (!$stmt) {
      throw new RuntimeException("Impossibile tracciare la migrazione {$base}: " . $mysqli->error);
    }

    $stmt->bind_param("s", $base);
    if (!$stmt->execute()) {
      $stmt->close();
      throw new RuntimeException("Impossibile tracciare la migrazione {$base}: " . $mysqli->error);
    }
    $stmt->close();
  }
}

try {
  run_migrations($mysqli, MIGRATIONS_DIR);
} catch (Throwable $e) {
  http_response_code(500);
  echo "Errore DB: migrazione fallita.";
  error_log('DB migration error: ' . $e->getMessage());
  exit;
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
