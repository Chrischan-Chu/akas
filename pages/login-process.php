<?php
declare(strict_types=1);

$baseUrl = '/AKAS';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $baseUrl . '/pages/login.php');
  exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('error', 'Please enter your email and password.');
  header('Location: ' . $baseUrl . '/pages/login.php');
  exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, role, name, email, password_hash, clinic_id FROM accounts WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$row = $stmt->fetch();

if (!$row || !password_verify($password, (string)$row['password_hash'])) {
  flash_set('error', 'Invalid email or password.');
  header('Location: ' . $baseUrl . '/pages/login.php');
  exit;
}

auth_set(
  (int)$row['id'],
  (string)$row['role'],
  (string)$row['name'],
  (string)$row['email'],
  isset($row['clinic_id']) ? (int)$row['clinic_id'] : null
);

if ((string)$row['role'] === 'clinic_admin') {
  header('Location: ' . $baseUrl . '/admin/dashboard.php');
  exit;
}

header('Location: ' . $baseUrl . '/index.php#home');
exit;
