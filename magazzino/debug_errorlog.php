<?php
// debug_errorlog.php (pagina per vedere error_log PHP al volo)
declare(strict_types=1);

$paths = [
  ini_get('error_log'),
  '/var/log/php_errors.log',
  '/var/log/httpd/error_log',
  '/var/log/nginx/error.log',
  '/volume1/@appstore/WebStation/var/log/php_error.log',
  '/volume1/@appstore/WebStation/var/log/nginx/error.log',
  '/volume1/@appstore/WebStation/var/log/apache2/error_log',
];

$found = null;
foreach ($paths as $p) {
  if ($p && @is_file($p) && @is_readable($p)) { $found = $p; break; }
}

header('Content-Type: text/plain; charset=utf-8');

if (!$found) {
  echo "Nessun error_log leggibile trovato.\n";
  echo "ini_get('error_log') = ".(ini_get('error_log') ?: '(vuoto)')."\n";
  exit;
}

$maxLines = 300;
$lines = @file($found, FILE_IGNORE_NEW_LINES);
if (!$lines) {
  echo "Impossibile leggere: $found\n";
  exit;
}
$tail = array_slice($lines, -$maxLines);
echo "FILE: $found\n";
echo "ULTIME ".count($tail)." righe:\n\n";
echo implode("\n", $tail);
