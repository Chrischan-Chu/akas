<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';

use Ably\AblyRest;

// FIXED: The API key is now correctly assigned to the $key variable
$key = getenv('ABLY_API_KEY') ?: 'KtC7rw.-df6sw:fEo5tVYYnOpj_RrNA450TNLUaST_v6qYWplSC79SdmU';

if ($key === '') {
  http_response_code(500);
  echo json_encode(['error' => 'Ably API key is missing.']);
  exit;
}

try {
  $ably = new AblyRest(['key' => $key]);

  $tokenRequest = $ably->auth->createTokenRequest([
    'ttl' => 60 * 60 * 1000, // 1 hour
    'capability' => json_encode([
      '*' => ['subscribe'] // clients can only subscribe; server publishes
    ]),
  ]);

  // Ably expects the tokenRequest fields directly (not wrapped in ok:true)
  echo json_encode($tokenRequest);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Token creation failed: ' . $e->getMessage()]);
}