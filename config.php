<?php
declare(strict_types=1);

/**
 * Environment-aware config loader.
 *
 * - Local (WAMP): uses config.local.php (typically BASE_URL=/AKAS and local DB creds)
 * - Production (Hostinger): uses config.production.php (typically BASE_URL='' and hosting DB creds)
 *
 * IMPORTANT: Do NOT commit config.local.php / config.production.php (they may contain secrets).
 */
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = in_array($host, ['localhost', '127.0.0.1'], true);

$localFile = __DIR__ . '/config.local.php';
$prodFile  = __DIR__ . '/config.production.php';

if ($isLocal && file_exists($localFile)) {
  require_once $localFile;
} elseif (!$isLocal && file_exists($prodFile)) {
  require_once $prodFile;
} elseif (file_exists($localFile)) {
  // fallback: if production config is missing but local exists
  require_once $localFile;
} else {
  // absolute fallback (prevents fatal errors during setup)
  if (!defined('BASE_URL')) define('BASE_URL', '');
  if (!defined('DB_HOST'))  define('DB_HOST', 'localhost');
  if (!defined('DB_NAME'))  define('DB_NAME', '');
  if (!defined('DB_USER'))  define('DB_USER', '');
  if (!defined('DB_PASS'))  define('DB_PASS', '');
}
