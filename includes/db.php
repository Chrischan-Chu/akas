<?php
// includes/db.php
// Simple PDO connection (edit credentials as needed)

declare(strict_types=1);

putenv('KtC7rw.-df6sw:fEo5tVYYnOpj_RrNA450TNLUaST_v6qYWplSC79SdmU');

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dbHost = getenv('AKAS_DB_HOST') ?: '127.0.0.1';
  $dbName = getenv('AKAS_DB_NAME') ?: 'akas_db';
  $dbUser = getenv('AKAS_DB_USER') ?: 'root';
  $dbPass = getenv('AKAS_DB_PASS') ?: '';

  $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  return $pdo;
}
