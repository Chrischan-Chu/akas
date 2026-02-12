<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php'; // must use same db() as your system
$pdo = db();

$email = 'superadmin@akas.local';   // MUST be valid email
$password = 'Admin@Akas12345';

try {
  echo "Connected OK.<br>";

  // Show which DB this script is using
  $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
  echo "DB in use: <b>" . htmlspecialchars((string)$dbName) . "</b><br><br>";

  $hash = password_hash($password, PASSWORD_DEFAULT);

 $stmt = $pdo->prepare("
  INSERT INTO accounts (role, clinic_id, name, email, password_hash)
  VALUES ('super_admin', NULL, 'AKAS SUPER ADMIN', ?, ?)
  ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    role = 'super_admin',
    clinic_id = NULL,
    name = 'AKAS SUPER ADMIN'
");
$stmt->execute([$email, $hash]);


  echo "✅ Inserted. Last ID: " . (int)$pdo->lastInsertId() . "<br>";

  $row = $pdo->prepare("SELECT id, role, email, name FROM accounts WHERE email = ? LIMIT 1");
  $row->execute([$email]);
  $data = $row->fetch(PDO::FETCH_ASSOC);

  echo "<pre>";
  var_dump($data);
  echo "</pre>";

  echo "<br>Login:<br>Email: {$email}<br>Password: {$password}<br>";
  echo "<b>DELETE THIS FILE NOW:</b> /dev/seed_superadmin.php";

} catch (Throwable $e) {
  echo "❌ ERROR: <pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
