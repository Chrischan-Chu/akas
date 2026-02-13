<?php
declare(strict_types=1);

require_once __DIR__ . '/google_config.php';

/**
 * Verify a Google ID token WITHOUT composer.
 * Uses Google's tokeninfo endpoint.
 *
 * Returns decoded payload array if valid; otherwise null.
 */
function google_verify_id_token(string $idToken): ?array {
  $idToken = trim($idToken);
  if ($idToken === '') return null;

  $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
  ]);

  $resp = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($resp === false || $code !== 200) return null;

  $data = json_decode((string)$resp, true);
  if (!is_array($data)) return null;

  if (($data['aud'] ?? '') !== GOOGLE_CLIENT_ID) return null;
  if (($data['email_verified'] ?? '') !== 'true') return null;

  if (empty($data['sub']) || empty($data['email'])) return null;

  return $data;
}

function google_make_dummy_password_hash(): string {
  $rand = bin2hex(random_bytes(24));
  return password_hash($rand, PASSWORD_DEFAULT);
}
