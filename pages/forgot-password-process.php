<?php
declare(strict_types=1);

$baseUrl = "";

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email_verification.php'; 
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

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('error', 'Please enter a valid email.');
  redirect_to($baseUrl . '/pages/forgot-password.php');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name, auth_provider, email_verified_at FROM accounts WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$acc = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Prevent email enumeration: always show a generic success for unknown emails
if (!$acc) {
  flash_set('success', 'If an account exists for that email, we sent a password reset link.');
  redirect_to($baseUrl . '/pages/forgot-password.php?email=' . urlencode($email));
}

// Google accounts don’t have local passwords
if ((string)$acc['auth_provider'] !== 'local') {
  flash_set('success', 'This account uses Google sign-in. Please use the Google login button.');
  redirect_to($baseUrl . '/pages/login.php?email=' . urlencode($email));
}

// ✅ Only verified accounts can reset password
if (empty($acc['email_verified_at'])) {
  flash_set('error', 'Please verify your email first before resetting your password.');
  redirect_to($baseUrl . '/pages/resend-verification.php?email=' . urlencode($email));
}

// Create token (email raw token) + store only hash
$token = bin2hex(random_bytes(32));
$hash  = hash('sha256', $token);
$exp   = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');

$upd = $pdo->prepare('UPDATE accounts SET password_reset_token_hash = ?, password_reset_expires_at = ? WHERE id = ? LIMIT 1');
$upd->execute([$hash, $exp, (int)$acc['id']]);

$absBase = akas_abs_base_url($baseUrl);
$resetUrl = $absBase . '/pages/reset-password.php?email=' . urlencode($email) . '&token=' . urlencode($token);

$safeName = htmlspecialchars((string)($acc['name'] ?? 'there'), ENT_QUOTES, 'UTF-8');
$safeUrl  = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

// Simple branded email (no templates folder required)
$html = '
<div style="font-family:Arial, sans-serif; line-height:1.5; color:#111;">
  <div style="max-width:560px;margin:0 auto;border:1px solid #eee;border-radius:18px;overflow:hidden;">
    <div style="background:#111;padding:14px 18px;">
      <img src="' . htmlspecialchars($absBase . '/assets/img/akas-logo.png', ENT_QUOTES, 'UTF-8') . '" alt="AKAS" style="height:34px;display:block;" />
    </div>
    <div style="padding:22px 18px;background:#fff;">
      <h2 style="margin:0 0 10px 0;">Reset your password</h2>
      <p style="margin:0 0 14px 0;">Hi ' . $safeName . ',</p>
      <p style="margin:0 0 16px 0;">We received a request to reset your AKAS password.</p>
      <p style="margin:0 0 18px 0;">
        <a href="' . $safeUrl . '" style="display:inline-block;padding:10px 14px;background:#111;color:#fff;text-decoration:none;border-radius:12px;">Reset Password</a>
      </p>
      <p style="margin:0 0 6px 0;font-size:12px;color:#444;">If the button doesn\'t work, copy-paste this link:</p>
      <p style="margin:0 0 0 0;font-size:12px;color:#444;word-break:break-all;">' . $safeUrl . '</p>
      <p style="margin:14px 0 0 0;font-size:12px;color:#666;">This link expires in 60 minutes.</p>
    </div>
  </div>
</div>';

$sent = akas_send_mail($email, (string)($acc['name'] ?? ''), 'Reset your AKAS password', $html);

flash_set('success', $sent
  ? 'If an account exists for that email, we sent a password reset link.'
  : 'Could not send email. Please configure SMTP in includes/smtp_config.php.'
);

redirect_to($baseUrl . '/pages/forgot-password.php?email=' . urlencode($email));
