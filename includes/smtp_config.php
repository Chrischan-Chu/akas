<?php
declare(strict_types=1);

/**
 * SMTP Configuration
 * - Do NOT hardcode secrets here.
 * - Secrets must be defined in config.production.php
 */

require_once __DIR__ . '/../config.php';

return [
  'host' => 'smtp-relay.brevo.com',
  'port' => 587,
  'username' => 'a266b1001@smtp-brevo.com',
  'password' => 'paste_key_here',
  'from_email' => 'akas.appointment.system@gmail.com',
  'from_name' => 'AKAS Appointment System',
];
