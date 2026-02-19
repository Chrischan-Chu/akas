<?php
declare(strict_types=1);

/**
 * SMTP Configuration (Brevo recommended)
 *
 * ✅ Put your real credentials here.
 * - In production, store these as environment variables instead.
 */
return [
  'host' => 'smtp-relay.brevo.com',
  'port' => 587,
  'username' => 'a266b1001@smtp-brevo.com',
  'password' => 'paste_key_here',
  'from_email' => 'akas.appointment.system@gmail.com',
  'from_name' => 'AKAS Appointment System',
];
