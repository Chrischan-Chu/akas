<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

function akas_h(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function akas_format_appt_date(string $date): string {
  if (trim($date) === '') return '';
  $ts = strtotime($date);
  return $ts ? date('M d, Y', $ts) : $date;
}

function akas_format_appt_time(string $time): string {
  if (trim($time) === '') return '';
  $ts = strtotime($time);
  return $ts ? date('g:i A', $ts) : $time;
}

function akas_email_layout(string $headline, string $intro, array $rows, string $preTableMessage = '', string $clinicLine = ''): string {
  $rowHtml = '';
  foreach ($rows as $label => $value) {
    $value = trim((string)$value);
    if ($value === '') continue;
    $rowHtml .= '<tr>'
      . '<td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;width:160px;font-weight:700;color:#0f172a;">' . akas_h((string)$label) . '</td>'
      . '<td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#334155;">' . akas_h($value) . '</td>'
      . '</tr>';
  }

  $preTableHtml = $preTableMessage !== ''
    ? '<p style="margin:0 0 18px;color:#475569;line-height:1.6;">' . nl2br(akas_h($preTableMessage)) . '</p>'
    : '';

  $clinicHtml = $clinicLine !== ''
    ? '<p style="margin:18px 0 0;color:#475569;line-height:1.6;font-weight:600;">' . nl2br(akas_h($clinicLine)) . '</p>'
    : '';

  return '<!doctype html><html><body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
    . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">'
    . '<div style="padding:18px 24px;background:#0ea5e9;color:#ffffff;font-size:20px;font-weight:700;">AKAS Appointment System</div>'
    . '<div style="padding:24px;">'
    . '<h2 style="margin:0 0 12px;font-size:22px;">' . akas_h($headline) . '</h2>'
    . '<p style="margin:0 0 18px;color:#475569;line-height:1.6;">' . nl2br(akas_h($intro)) . '</p>'
    . $preTableHtml
    . '<table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">' . $rowHtml . '</table>'
    . $clinicHtml
    . '<p style="margin:24px 0 0;color:#64748b;font-size:13px;line-height:1.6;">This is an automated notification from AKAS Appointment System. Please do not reply to this email.</p>'
    . '</div></div></body></html>';
}

function akas_send_booking_emails(array $a): void {
  $patientEmail = trim((string)($a['user_email'] ?? ''));
  $doctorEmail  = trim((string)($a['doctor_email'] ?? ''));
  $patientName  = (string)($a['user_name'] ?? 'Patient');
  $doctorName   = (string)($a['doctor_name'] ?? 'Doctor');
  $clinicName   = (string)($a['clinic_name'] ?? 'Clinic');
  $date         = akas_format_appt_date((string)($a['APT_Date'] ?? ''));
  $time         = akas_format_appt_time((string)($a['APT_Time'] ?? ''));

  if ($patientEmail !== '') {
    akas_send_mail(
      $patientEmail,
      $patientName,
      'Appointment Confirmed – AKAS',
      akas_email_layout(
        'Appointment Confirmed',
        "Dear {$patientName},

Your appointment at {$clinicName} with Dr. {$doctorName} has been successfully confirmed.",
        [
          'Date'   => $date,
          'Time'   => $time,
          'Clinic' => $clinicName,
          'Doctor' => 'Dr. ' . $doctorName,
        ],
        "Kindly arrive at your scheduled time.",
        $clinicName
      ),
      '',
      '',
      [
        'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
        'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($a['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
        'event_type'     => 'booking',
        'recipient_type' => 'user',
      ]
    );
  }

  if ($doctorEmail !== '') {
    akas_send_mail(
      $doctorEmail,
      'Dr. ' . $doctorName,
      'New Appointment Scheduled – AKAS',
      akas_email_layout(
        'New Appointment Scheduled',
        "Dear Dr. {$doctorName},

A new appointment has been scheduled at {$clinicName}.",
        [
          'Patient' => $patientName,
          'Date'    => $date,
          'Time'    => $time,
          'Clinic'  => $clinicName,
        ],
        '',
        $clinicName
      ),
      '',
      '',
      [
        'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
        'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($a['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
        'event_type'     => 'booking',
        'recipient_type' => 'doctor',
      ]
    );
  }
}

function akas_send_reschedule_emails(array $a, string $oldDate = '', string $oldTime = ''): void {
  $userEmail   = trim((string)($a['user_email'] ?? ''));
  $userName    = (string)($a['user_name'] ?? 'Patient');
  $doctorEmail = trim((string)($a['doctor_email'] ?? ''));
  $doctorName  = (string)($a['doctor_name'] ?? 'Doctor');
  $clinicName  = (string)($a['clinic_name'] ?? 'Clinic');

  $newDate    = akas_format_appt_date((string)($a['APT_Date'] ?? $a['date'] ?? ''));
  $newTime    = akas_format_appt_time((string)($a['APT_Time'] ?? $a['time'] ?? ''));
  $oldDateFmt = akas_format_appt_date($oldDate);
  $oldTimeFmt = akas_format_appt_time($oldTime);

  if ($userEmail !== '') {
    $rows = [];
    if ($oldDateFmt !== '' || $oldTimeFmt !== '') {
      $rows['Previous Schedule'] = trim($oldDateFmt . ($oldTimeFmt !== '' ? ' at ' . $oldTimeFmt : ''));
    }

    $rows += [
      'New Date' => $newDate,
      'New Time' => $newTime,
      'Clinic'   => $clinicName,
      'Doctor'   => 'Dr. ' . $doctorName,
    ];

    akas_send_mail(
      $userEmail,
      $userName,
      'Appointment Rescheduled – AKAS',
      akas_email_layout(
        'Appointment Rescheduled',
        "Dear {$userName},\n\nYour appointment at {$clinicName} with Dr. {$doctorName} has been rescheduled.",
        $rows,
        "Please review your updated schedule.",
        $clinicName
      ),
      '',
      '',
      [
        'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
        'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($a['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
        'event_type'     => 'reschedule',
        'recipient_type' => 'user',
      ]
    );
  }

  if ($doctorEmail !== '') {
    $rows = [
      'Patient' => $userName,
    ];

    if ($oldDateFmt !== '' || $oldTimeFmt !== '') {
      $rows['Previous Schedule'] = trim($oldDateFmt . ($oldTimeFmt !== '' ? ' at ' . $oldTimeFmt : ''));
    }

    $rows += [
      'New Date' => $newDate,
      'New Time' => $newTime,
      'Clinic'   => $clinicName,
    ];

    akas_send_mail(
      $doctorEmail,
      'Dr. ' . $doctorName,
      'Appointment Rescheduled – AKAS',
      akas_email_layout(
        'Appointment Rescheduled',
        "Dear Dr. {$doctorName},\n\nAn appointment has been rescheduled.",
        $rows,
        '',
        $clinicName
      ),
      '',
      '',
      [
        'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
        'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($a['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
        'event_type'     => 'reschedule',
        'recipient_type' => 'doctor',
      ]
    );
  }
}

function akas_send_reschedule_request_emails(array $a, string $oldDate = '', string $oldTime = '', string $reason = ''): void {
  $patientEmail = trim((string)($a['user_email'] ?? ''));
  $patientName  = (string)($a['user_name'] ?? 'Patient');
  $doctorName   = (string)($a['doctor_name'] ?? 'Doctor');
  $clinicName   = (string)($a['clinic_name'] ?? 'Clinic');

  $newDate    = akas_format_appt_date((string)($a['APT_Date'] ?? $a['date'] ?? ''));
  $newTime    = akas_format_appt_time((string)($a['APT_Time'] ?? $a['time'] ?? ''));
  $oldDateFmt = akas_format_appt_date($oldDate);
  $oldTimeFmt = akas_format_appt_time($oldTime);

  if ($patientEmail === '') return;

  $rows = [];
  if ($oldDateFmt !== '' || $oldTimeFmt !== '') {
    $rows['Previous Schedule'] = trim($oldDateFmt . ($oldTimeFmt !== '' ? ' at ' . $oldTimeFmt : ''));
  }

  $rows += [
    'New Date' => $newDate,
    'New Time' => $newTime,
    'Clinic'   => $clinicName,
    'Doctor'   => 'Dr. ' . $doctorName,
    'Reason'   => $reason !== '' ? $reason : 'No reason provided.',
  ];

  akas_send_mail(
    $patientEmail,
    $patientName,
    'Appointment Schedule Change Request – AKAS',
    akas_email_layout(
      'Appointment Schedule Change Request',
      "Dear {$patientName},\n\nYour appointment at {$clinicName} with Dr. {$doctorName} has been moved by the clinic and is waiting for your response.",
      $rows,
      "Please log in to your AKAS account to accept or decline this rescheduled appointment.\n\nIf you accept it, your appointment will be confirmed on the new date and time.",
      $clinicName
    ),
    '',
    '',
    [
      'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
      'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
      'user_id'        => (int)($a['APT_UserID'] ?? 0),
      'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
      'event_type'     => 'reschedule_request',
      'recipient_type' => 'user',
    ]
  );
}

function akas_send_reschedule_accepted_emails(array $a, string $oldDate = '', string $oldTime = ''): void {
  $patientEmail = trim((string)($a['user_email'] ?? ''));
  $doctorEmail  = trim((string)($a['doctor_email'] ?? ''));
  $patientName  = (string)($a['user_name'] ?? 'Patient');
  $doctorName   = (string)($a['doctor_name'] ?? 'Doctor');
  $clinicName   = (string)($a['clinic_name'] ?? 'Clinic');

  $newDate    = akas_format_appt_date((string)($a['APT_Date'] ?? $a['date'] ?? ''));
  $newTime    = akas_format_appt_time((string)($a['APT_Time'] ?? $a['time'] ?? ''));
  $oldDateFmt = akas_format_appt_date($oldDate);
  $oldTimeFmt = akas_format_appt_time($oldTime);

  if ($patientEmail !== '') {
    $rows = [];
    if ($oldDateFmt !== '' || $oldTimeFmt !== '') {
      $rows['Previous Schedule'] = trim($oldDateFmt . ($oldTimeFmt !== '' ? ' at ' . $oldTimeFmt : ''));
    }

    $rows += [
      'Confirmed Date' => $newDate,
      'Confirmed Time' => $newTime,
      'Clinic'         => $clinicName,
      'Doctor'         => 'Dr. ' . $doctorName,
    ];

    akas_send_mail(
      $patientEmail,
      $patientName,
      'Appointment Reschedule Confirmed – AKAS',
      akas_email_layout(
        'Appointment Reschedule Confirmed',
        "Dear {$patientName},\n\nYour rescheduled appointment at {$clinicName} with Dr. {$doctorName} is now confirmed.",
        $rows,
        "Please arrive at your scheduled time.",
        $clinicName
      ),
      '',
      '',
      [
        'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
        'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($a['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
        'event_type'     => 'reschedule_accept',
        'recipient_type' => 'user',
      ]
    );
  }

  if ($doctorEmail !== '') {
    $rows = [
      'Patient' => $patientName,
    ];

    if ($oldDateFmt !== '' || $oldTimeFmt !== '') {
      $rows['Previous Schedule'] = trim($oldDateFmt . ($oldTimeFmt !== '' ? ' at ' . $oldTimeFmt : ''));
    }

    $rows += [
      'Confirmed Date' => $newDate,
      'Confirmed Time' => $newTime,
      'Clinic'         => $clinicName,
    ];

    akas_send_mail(
      $doctorEmail,
      'Dr. ' . $doctorName,
      'Appointment Reschedule Confirmed – AKAS',
      akas_email_layout(
        'Appointment Reschedule Confirmed',
        "Dear Dr. {$doctorName},\n\nThe patient has accepted the rescheduled appointment. The appointment is now confirmed.",
        $rows,
        '',
        $clinicName
      ),
      '',
      '',
      [
        'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
        'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($a['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
        'event_type'     => 'reschedule_accept',
        'recipient_type' => 'doctor',
      ]
    );
  }
}

function akas_send_reschedule_declined_emails(array $a, string $oldDate = '', string $oldTime = ''): void {
  $patientEmail = trim((string)($a['user_email'] ?? ''));
  $doctorEmail  = trim((string)($a['doctor_email'] ?? ''));
  $patientName  = (string)($a['user_name'] ?? 'Patient');
  $doctorName   = (string)($a['doctor_name'] ?? 'Doctor');
  $clinicName   = (string)($a['clinic_name'] ?? 'Clinic');

  $newDate    = akas_format_appt_date((string)($a['APT_Date'] ?? $a['date'] ?? ''));
  $newTime    = akas_format_appt_time((string)($a['APT_Time'] ?? $a['time'] ?? ''));
  $oldDateFmt = akas_format_appt_date($oldDate);
  $oldTimeFmt = akas_format_appt_time($oldTime);

  if ($patientEmail !== '') {
    $rows = [];
    if ($oldDateFmt !== '' || $oldTimeFmt !== '') {
      $rows['Previous Schedule'] = trim($oldDateFmt . ($oldTimeFmt !== '' ? ' at ' . $oldTimeFmt : ''));
    }

    $rows += [
      'Requested Date' => $newDate,
      'Requested Time' => $newTime,
      'Clinic'         => $clinicName,
      'Doctor'         => 'Dr. ' . $doctorName,
      'Final Status'   => 'Cancelled',
    ];

    akas_send_mail(
      $patientEmail,
      $patientName,
      'Reschedule Declined – Appointment Cancelled – AKAS',
      akas_email_layout(
        'Appointment Cancelled',
        "Dear {$patientName},\n\nYou declined the clinic's reschedule request. Your appointment at {$clinicName} with Dr. {$doctorName} has now been cancelled.",
        $rows,
        "You may schedule a new appointment at your convenience.",
        $clinicName
      ),
      '',
      '',
      [
        'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
        'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($a['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
        'event_type'     => 'reschedule_decline',
        'recipient_type' => 'user',
      ]
    );
  }

  if ($doctorEmail !== '') {
    $rows = [
      'Patient' => $patientName,
    ];

    if ($oldDateFmt !== '' || $oldTimeFmt !== '') {
      $rows['Previous Schedule'] = trim($oldDateFmt . ($oldTimeFmt !== '' ? ' at ' . $oldTimeFmt : ''));
    }

    $rows += [
      'Requested Date' => $newDate,
      'Requested Time' => $newTime,
      'Clinic'         => $clinicName,
      'Final Status'   => 'Cancelled',
    ];

    akas_send_mail(
      $doctorEmail,
      'Dr. ' . $doctorName,
      'Reschedule Declined – Appointment Cancelled – AKAS',
      akas_email_layout(
        'Appointment Cancelled',
        "Dear Dr. {$doctorName},\n\nThe patient declined the clinic's reschedule request. The appointment has now been cancelled.",
        $rows,
        '',
        $clinicName
      ),
      '',
      '',
      [
        'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
        'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($a['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
        'event_type'     => 'reschedule_decline',
        'recipient_type' => 'doctor',
      ]
    );
  }
}

function akas_send_cancel_emails(array $a, string $cancelledBy = 'user'): void {
  $patientEmail = trim((string)($a['user_email'] ?? ''));
  $doctorEmail  = trim((string)($a['doctor_email'] ?? ''));
  $patientName  = (string)($a['user_name'] ?? 'Patient');
  $doctorName   = (string)($a['doctor_name'] ?? 'Doctor');
  $clinicName   = (string)($a['clinic_name'] ?? 'Clinic');
  $date         = akas_format_appt_date((string)($a['APT_Date'] ?? $a['date'] ?? ''));
  $time         = akas_format_appt_time((string)($a['APT_Time'] ?? $a['time'] ?? ''));

  $cancelledLabel = $cancelledBy === 'clinic' ? 'the clinic' : 'the patient';
  $eventType = $cancelledBy === 'clinic' ? 'cancel_by_clinic' : 'cancel_by_user';

  if ($patientEmail !== '') {
    $intro = $cancelledBy === 'clinic'
      ? "Dear {$patientName},\n\nYour appointment at {$clinicName} with Dr. {$doctorName} has been cancelled by the clinic."
      : "Dear {$patientName},\n\nYour appointment at {$clinicName} with Dr. {$doctorName} has been successfully cancelled.";

    akas_send_mail(
      $patientEmail,
      $patientName,
      'Appointment Cancelled – AKAS',
      akas_email_layout(
        'Appointment Cancelled',
        $intro,
        [
          'Date'         => $date,
          'Time'         => $time,
          'Clinic'       => $clinicName,
          'Doctor'       => 'Dr. ' . $doctorName,
          'Cancelled By' => ucfirst($cancelledLabel),
        ],
        "You may schedule a new appointment at your convenience.",
        $clinicName
      ),
      '',
      '',
      [
        'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
        'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($a['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
        'event_type'     => $eventType,
        'recipient_type' => 'user',
      ]
    );
  }

  if ($doctorEmail !== '') {
    $intro = $cancelledBy === 'clinic'
      ? "Dear Dr. {$doctorName},\n\nThe appointment at {$clinicName} has been cancelled by the clinic."
      : "Dear Dr. {$doctorName},\n\nThe appointment at {$clinicName} has been cancelled by the patient.";

    akas_send_mail(
      $doctorEmail,
      'Dr. ' . $doctorName,
      'Appointment Cancelled – AKAS',
      akas_email_layout(
        'Appointment Cancelled',
        $intro,
        [
          'Patient'      => $patientName,
          'Date'         => $date,
          'Time'         => $time,
          'Clinic'       => $clinicName,
          'Cancelled By' => ucfirst($cancelledLabel),
        ],
        '',
        $clinicName
      ),
      '',
      '',
      [
        'appointment_id' => (int)($a['APT_AppointmentID'] ?? 0),
        'clinic_id'      => (int)($a['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($a['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($a['APT_DoctorID'] ?? 0),
        'event_type'     => $eventType,
        'recipient_type' => 'doctor',
      ]
    );
  }
}