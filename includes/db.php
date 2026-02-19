<?php
require_once __DIR__ . '/../config.php';
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
  $dbName = defined('DB_NAME') ? DB_NAME : '';
  $dbUser = defined('DB_USER') ? DB_USER : '';
  $dbPass = defined('DB_PASS') ? DB_PASS : '';

  $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  
  $pdo->exec("SET time_zone = '+08:00'");


  return $pdo;
}
