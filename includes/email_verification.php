<?php
require_once __DIR__ . '/../config.php';
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

/**
 * Build absolute base URL like: http://localhost{BASE_URL}  or https://example.com{BASE_URL}
 */
function akas_abs_base_url(string $baseUrl = ''): string {
  if ($baseUrl === '' && defined('BASE_URL')) { $baseUrl = (string)BASE_URL; }
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $scheme = $https ? 'https' : 'http';
  $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');

  // If baseUrl already includes scheme, return as-is
  if (preg_match('#^https?://#i', $baseUrl)) return rtrim($baseUrl, '/');

  return $scheme . '://' . $host . rtrim($baseUrl, '/');
}

/**
 * Create a verify token for an account and store ONLY the hash in DB.
 * Returns the raw token (hex).
 */
function akas_create_email_verify_token(PDO $pdo, int $accountId, int $minutesValid = 30): string {
  $token = bin2hex(random_bytes(32));     // email this
  $hash  = hash('sha256', $token);        // store this
  $exp   = (new DateTime('+' . $minutesValid . ' minutes'))->format('Y-m-d H:i:s');

  $stmt = $pdo->prepare('
    UPDATE accounts
    SET email_verify_token_hash = ?, email_verify_expires_at = ?, email_verified_at = NULL
    WHERE id = ?
    LIMIT 1
  ');
  $stmt->execute([$hash, $exp, $accountId]);

  return $token;
}

/**
 * Send verification email (manual/local accounts only).
 */
function akas_send_verification_email(string $baseUrl, string $toEmail, string $toName, string $token): bool {
  $absBase = akas_abs_base_url($baseUrl);
  $url = $absBase . '/pages/verify-email.php?email=' . urlencode($toEmail) . '&token=' . urlencode($token);

  $safeName = htmlspecialchars($toName !== '' ? $toName : 'there', ENT_QUOTES, 'UTF-8');

  $html = '
  <div style="font-family:Arial, sans-serif; line-height:1.5; color:#111">
    <h2 style="margin:0 0 8px 0;">Verify your email</h2>
    <p style="margin:0 0 12px 0;">Hi ' . $safeName . ',</p>
    <p style="margin:0 0 12px 0;">Please verify your email to activate your AKAS account.</p>
    <p style="margin:0 0 16px 0;">
      <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:10px 14px;background:#111;color:#fff;text-decoration:none;border-radius:10px;">
        Verify Email
      </a>
    </p>
    <p style="margin:0 0 6px 0; font-size:12px; color:#444;">If the button doesn\'t work, copy-paste this link:</p>
    <p style="margin:0 0 0 0; font-size:12px; color:#444;">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</p>
    <p style="margin:14px 0 0 0; font-size:12px; color:#666;">This link expires in 30 minutes.</p>
  </div>';

  return akas_send_mail($toEmail, $toName, 'Verify your AKAS email', $html);
}
