<?php
declare(strict_types=1);

$baseUrl = '';
require_once __DIR__ . '/../includes/auth.php';

function redirect_to(string $to): void {
  header('Location: ' . $to);
  exit;
}

$email = strtolower(trim((string)($_GET['email'] ?? '')));
$token = (string)($_GET['token'] ?? '');

if ($email === '' || $token === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('error', 'Invalid verification link.');
  redirect_to($baseUrl . '/pages/login.php');
}

$hash = hash('sha256', $token);

$pdo = db();
$stmt = $pdo->prepare('
  SELECT id, role, name, auth_provider, email_verified_at, email_verify_expires_at
  FROM accounts
  WHERE email = ? AND email_verify_token_hash = ?
  LIMIT 1
');
$stmt->execute([$email, $hash]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  flash_set('error', 'Invalid or already used verification link.');
  redirect_to($baseUrl . '/pages/login.php');
}

// Already verified (or token used)
if (!empty($row['email_verified_at'])) {
  flash_set('success', 'Your email is already verified. You can log in.');
  redirect_to($baseUrl . '/pages/login.php');
}

$expires = (string)($row['email_verify_expires_at'] ?? '');
if ($expires === '' || strtotime($expires) < time()) {
  flash_set('error', 'Verification link expired. Please resend verification.');
  redirect_to($baseUrl . '/pages/resend-verification.php?email=' . urlencode($email));
}

// Mark verified + clear token
$upd = $pdo->prepare('
  UPDATE accounts
  SET email_verified_at = NOW(),
      email_verify_token_hash = NULL,
      email_verify_expires_at = NULL
  WHERE id = ?
  LIMIT 1
');
$upd->execute([(int)$row['id']]);

// Message depends on role
if ((string)$row['role'] === 'clinic_admin') {
  flash_set('success', 'Email verified! You may now log in. If your clinic is still pending approval, you will be sent to the Pending page after login.');
  redirect_to($baseUrl . '/pages/login.php?email=' . urlencode($email));
}

flash_set('success', 'Email verified! You can now log in.');
redirect_to($baseUrl . '/pages/login.php?email=' . urlencode($email));
