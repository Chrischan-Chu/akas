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

    // These are defined in config.production.php
    'username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
    'password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',

    'from_email' => 'akas.appointment.system@gmail.com',
    'from_name'  => 'AKAS Appointment System',
];
