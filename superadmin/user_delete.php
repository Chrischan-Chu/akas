<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
$pdo = db();

$userId = (int)($_POST['user_id'] ?? 0);

if ($userId <= 0) {
  flash_set('error', 'Invalid user.');
  header('Location: ' . $baseUrl . '/superadmin/users.php');
  exit;
}

/* prevent deleting super admin */
$stmt = $pdo->prepare("SELECT role FROM accounts WHERE id=:id");
$stmt->execute([':id' => $userId]);
$role = $stmt->fetchColumn();

if ($role !== 'user') {
  flash_set('error', 'You can only delete normal users.');
  header('Location: ' . $baseUrl . '/superadmin/users.php');
  exit;
}

$del = $pdo->prepare("DELETE FROM accounts WHERE id=:id AND role='user'");
$del->execute([':id' => $userId]);

flash_set('success', 'User deleted.');
header('Location: ' . $baseUrl . '/superadmin/users.php');
exit;
