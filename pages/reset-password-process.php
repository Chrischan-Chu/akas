<?php
declare(strict_types=1);

$baseUrl = "";

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $baseUrl . '/pages/forgot-password.php');
  exit;
}

function redirect_to(string $to): void {
  header('Location: ' . $to);
  exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$email = preg_replace('/\s+/', '', $email);
$token = (string)($_POST['token'] ?? '');

$password = (string)($_POST['password'] ?? '');
$confirm  = (string)($_POST['confirm_password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '') {
  flash_set('error', 'Invalid reset request.');
  redirect_to($baseUrl . '/pages/forgot-password.php');
}

// Password rules (match signup-process.php)
$minLen = strlen($password) >= 8;
$hasUpper = preg_match('/[A-Z]/', $password) === 1;
$hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;

if (!($minLen && $hasUpper && $hasSpecial)) {
  flash_set('error', 'Password must be 8+ chars, with 1 uppercase and 1 special character.');
  redirect_to($baseUrl . '/pages/reset-password.php?email=' . urlencode($email) . '&token=' . urlencode($token));
}

if ($password !== $confirm) {
  flash_set('error', 'Passwords do not match.');
  redirect_to($baseUrl . '/pages/reset-password.php?email=' . urlencode($email) . '&token=' . urlencode($token));
}

$pdo = db();
$hash = hash('sha256', $token);

$stmt = $pdo->prepare('
  SELECT id, auth_provider, email_verified_at, password_reset_expires_at
  FROM accounts
  WHERE email = ? AND password_reset_token_hash = ?
  LIMIT 1
');
$stmt->execute([$email, $hash]);
$acc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$acc) {
  flash_set('error', 'This reset link is invalid or has already been used.');
  redirect_to($baseUrl . '/pages/forgot-password.php?email=' . urlencode($email));
}

if ((string)$acc['auth_provider'] !== 'local') {
  flash_set('error', 'This account uses Google sign-in. Please use Google login.');
  redirect_to($baseUrl . '/pages/login.php?email=' . urlencode($email));
}

if (empty($acc['email_verified_at'])) {
  flash_set('error', 'Please verify your email first.');
  redirect_to($baseUrl . '/pages/resend-verification.php?email=' . urlencode($email));
}

$exp = (string)($acc['password_reset_expires_at'] ?? '');
if ($exp === '' || strtotime($exp) < time()) {
  // Expired: clear token
  $clr = $pdo->prepare('UPDATE accounts SET password_reset_token_hash = NULL, password_reset_expires_at = NULL WHERE id = ? LIMIT 1');
  $clr->execute([(int)$acc['id']]);

  flash_set('error', 'This reset link has expired. Please request a new one.');
  redirect_to($baseUrl . '/pages/forgot-password.php?email=' . urlencode($email));
}

$newHash = password_hash($password, PASSWORD_DEFAULT);

$upd = $pdo->prepare('
  UPDATE accounts
  SET password_hash = ?, password_reset_token_hash = NULL, password_reset_expires_at = NULL
  WHERE id = ?
  LIMIT 1
');
$upd->execute([$newHash, (int)$acc['id']]);

flash_set('success', 'Password updated. You can now log in.');
redirect_to($baseUrl . '/pages/login.php?email=' . urlencode($email));
