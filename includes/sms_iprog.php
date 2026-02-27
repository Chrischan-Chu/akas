<?php
declare(strict_types=1);

/**
 * IPROG SMS helper
 * Docs: https://www.iprogsms.com/api/v1/documentation
 */

require_once __DIR__ . '/../config.php';

/**
 * Normalize PH phone numbers to "63" format.
 * Accepts (common PH formats):
 *  - 09xxxxxxxxx
 *  - 9xxxxxxxxx (stored by AKAS for doctors)
 *  - 63xxxxxxxxxx
 *  - +63xxxxxxxxxx
 */
function sms_normalize_phone(string $raw): string {
  $p = trim($raw);
  if ($p === '') return '';

  // keep digits and leading + only
  $p = preg_replace('/[^0-9+]/', '', $p) ?? '';
  if ($p === '') return '';

  // PHP 7/8 safe check for leading "+"
  if (isset($p[0]) && $p[0] === '+') {
    $p = substr($p, 1);
  }

  // 09xxxxxxxxx -> 63xxxxxxxxxx
  if (preg_match('/^0\d{10}$/', $p)) {
    return '63' . substr($p, 1);
  }

  // 9xxxxxxxxx -> 63xxxxxxxxxx (AKAS doctor numbers often stored like this)
  if (preg_match('/^9\d{9}$/', $p)) {
    return '63' . $p;
  }

  // 63xxxxxxxxxx
  if (preg_match('/^63\d{10}$/', $p)) {
    return $p;
  }

  // Already something else (leave as-is)
  return $p;
}

/**
 * Send SMS via IPROG SMS.
 * Returns: ['ok' => bool, 'status' => int, 'response' => mixed]
 */
function iprog_send_sms(string $phone, string $message, ?int $provider = null): array {
  $token = defined('IPROGSMS_API_TOKEN') ? (string)IPROGSMS_API_TOKEN : '';
  if ($token === '') {
    return ['ok' => false, 'status' => 0, 'response' => 'Missing IPROGSMS_API_TOKEN'];
  }

  $to = sms_normalize_phone($phone);
  if ($to === '') {
    return ['ok' => false, 'status' => 0, 'response' => 'Missing phone number'];
  }

  $msg = trim($message);
  if ($msg === '') {
    return ['ok' => false, 'status' => 0, 'response' => 'Missing message'];
  }

  $prov = $provider;
  if ($prov === null) {
    $prov = defined('IPROGSMS_SMS_PROVIDER') ? (int)IPROGSMS_SMS_PROVIDER : 0;
  }

  $base = 'https://www.iprogsms.com/api/v1/sms_messages';
  $qs = http_build_query([
    'api_token' => $token,
    'phone_number' => $to,
    'message' => $msg,
    'sms_provider' => $prov,
  ]);

  $url = $base . '?' . $qs;

  $ch = curl_init($url);
  if ($ch === false) {
    return ['ok' => false, 'status' => 0, 'response' => 'curl_init failed'];
  }

  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
  ]);

  $raw = curl_exec($ch);
  $err = curl_error($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($raw === false) {
    return ['ok' => false, 'status' => $http, 'response' => $err ?: 'curl_exec failed'];
  }

  $decoded = json_decode($raw, true);
  $resp = is_array($decoded) ? $decoded : $raw;

  // Docs show {status: 200, ...}
  $ok = ($http >= 200 && $http < 300) || ((int)($decoded['status'] ?? 0) === 200);

  return ['ok' => $ok, 'status' => $http, 'response' => $resp];
}
