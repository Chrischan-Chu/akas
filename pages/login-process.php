<?php
declare(strict_types=1);

$baseUrl = '';
require_once __DIR__ . '/../includes/auth.php';

function redirect_to(string $to): void {
  header('Location: ' . $to);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_to($baseUrl . '/pages/login.php');
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$email = preg_replace('/\s+/', '', $email);
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '') {
  flash_set('error', 'Invalid email or password.');
  redirect_to($baseUrl . '/pages/login.php');
}

if (!preg_match('/^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/', $email)) {
  flash_set('error', 'Enter a valid email (ex: name@gmail.com).');
  redirect_to($baseUrl . '/pages/login.php');
}

$pdo = db();
$stmt = $pdo->prepare('
  SELECT id, role, name, email, password_hash, clinic_id, auth_provider, email_verified_at
  FROM accounts
  WHERE email = ?
  LIMIT 1
');
$stmt->execute([$email]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || !password_verify($password, (string)$row['password_hash'])) {
  flash_set('error', 'Invalid email or password.');
  redirect_to($baseUrl . '/pages/login.php');
}

// ✅ Require email verification for MANUAL accounts only
if ((string)($row['auth_provider'] ?? 'local') === 'local' && empty($row['email_verified_at'])) {
  flash_set('error', 'Please verify your email before logging in. You can resend the verification email below.');
  redirect_to($baseUrl . '/pages/login.php?email=' . urlencode($email) . '&need_verify=1');
}

auth_set(
  (int)$row['id'],
  (string)$row['role'],
  (string)$row['name'],
  (string)$row['email'],
  isset($row['clinic_id']) ? (int)$row['clinic_id'] : null
);

// CLINIC ADMIN routing
if ((string)$row['role'] === 'clinic_admin') {
  $cid = (int)($row['clinic_id'] ?? 0);

  // Incomplete clinic signup (Google admin)
  if ($cid <= 0) {
    redirect_to($baseUrl . '/pages/signup-admin.php?step=2&locked=1');
  }

  // Approved? send to dashboard; else show pending page
  $st = $pdo->prepare('SELECT approval_status FROM clinics WHERE id = ? LIMIT 1');
  $st->execute([$cid]);
  $status = (string)($st->fetchColumn() ?? 'PENDING');

  if ($status === 'APPROVED') {
    redirect_to($baseUrl . '/admin/dashboard.php');
  }
  redirect_to($baseUrl . '/admin/pending.php');
}

// USER routing
redirect_to($baseUrl . '/index.php#home');
