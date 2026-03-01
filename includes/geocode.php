<?php
// includes/geocode.php
declare(strict_types=1);

/**
 * Lightweight server-side geocoding using OpenStreetMap Nominatim.
 * - Used when a clinic saves/updates an address in Admin → Clinic Details.
 * - Stores lat/lng into DB if columns exist.
 */

function akas_clinic_geo_columns_exist(PDO $pdo): bool {
  try {
    // Shared hosting friendly (avoids information_schema permission issues)
    $stmt = $pdo->query("SHOW COLUMNS FROM clinics LIKE 'latitude'");
    $hasLat = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("SHOW COLUMNS FROM clinics LIKE 'longitude'");
    $hasLng = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    return $hasLat && $hasLng;
  } catch (Throwable $e) {
    return false;
  }
}

function akas_geocode_address(string $address): ?array {
  $address = trim($address);
  if ($address === '') return null;

  // Force Angeles City, Pampanga, Philippines context
  $q = $address;
  if (stripos($q, 'Angeles') === false) {
    $q .= ', Angeles City, Pampanga';
  }
  if (stripos($q, 'Philippines') === false && stripos($q, 'PH') === false) {
    $q .= ', Philippines';
  }

  // Angeles City bounding box (lng,lat,lng,lat)
  $swLat = 15.0800; $swLng = 120.5200;
  $neLat = 15.2100; $neLng = 120.6800;
  $viewbox = $swLng . ',' . $swLat . ',' . $neLng . ',' . $neLat;

  // Nominatim usage policy expects a valid User-Agent
  $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q' => $q,
    'format' => 'json',
    'limit' => 1,
    'countrycodes' => 'ph',
    'viewbox' => $viewbox,
    'bounded' => 1,
  ]);

  $raw = null;
  $code = 0;

  // Prefer cURL, fallback to file_get_contents for shared hosting
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    if ($ch) {
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_HTTPHEADER => [
          'Accept: application/json',
          'User-Agent: AKAS/1.0 (contact-map; server-geocode)',
        ],
      ]);
      $raw = curl_exec($ch);
      $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
    }
  } else {
    $ctx = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'header' => "Accept: application/json\r\n" .
                    "User-Agent: AKAS/1.0 (contact-map; server-geocode)\r\n",
      ]
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    $code = $raw !== false ? 200 : 0;
  }

  if (!$raw || $code < 200 || $code >= 300) {
    error_log('[AKAS geocode] Request failed. HTTP=' . $code . ' url=' . $url);
    return null;
  }

  $json = json_decode($raw, true);
  if (!is_array($json) || !isset($json[0])) {
    error_log('[AKAS geocode] No result for address: ' . $q);
    return null;
  }

  $lat = isset($json[0]['lat']) ? (float)$json[0]['lat'] : null;
  $lng = isset($json[0]['lon']) ? (float)$json[0]['lon'] : null;
  if ($lat === null || $lng === null) {
    error_log('[AKAS geocode] Bad coords for address: ' . $q);
    return null;
  }

  // Final safety: ensure inside Angeles bounding box
  if ($lat < $swLat || $lat > $neLat || $lng < $swLng || $lng > $neLng) {
    error_log('[AKAS geocode] Result outside Angeles bounds. address=' . $q . ' lat=' . $lat . ' lng=' . $lng);
    return null;
  }

  return ['lat' => $lat, 'lng' => $lng];
}
