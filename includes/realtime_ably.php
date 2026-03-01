<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Ably\AblyRest;

/**
 * Returns an AblyRest client or null when ABLY_API_KEY isn't configured.
 * This keeps localhost setup smooth (realtime is optional).
 */
function ably_rest(): ?AblyRest {
  // Added your fallback key here so it works on Hostinger!
  $key = getenv('ABLY_API_KEY') ?: 'KtC7rw.-df6sw:fEo5tVYYnOpj_RrNA450TNLUaST_v6qYWplSC79SdmU';
  if ($key === '') return null;
  return new AblyRest(['key' => $key]);
}

function publish_slots_updated(int $clinicId, string $date): void {
  $ably = ably_rest();
  if (!$ably) return; // realtime disabled
  
  $channel = $ably->channels->get('clinic-' . $clinicId);
  
  // FIXED: Changed 'slots.updated' to 'slots-updated' to match your JavaScript!
  $channel->publish('slots-updated', ['date' => $date]);
}