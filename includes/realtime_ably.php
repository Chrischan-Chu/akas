<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Ably\AblyRest;

function ably_rest(): AblyRest {
  $key = getenv('ABLY_API_KEY') ?: '';
  if ($key === '') {
    throw new RuntimeException('Missing ABLY_API_KEY');
  }
  return new AblyRest(['key' => $key]);
}

function publish_slots_updated(int $clinicId, string $date): void {
  $ably = ably_rest();
  $channel = $ably->channels->get('clinic-' . $clinicId);
  $channel->publish('slots.updated', ['date' => $date]);
}
