<?php

function sms_template($type, $data = [])
{
    $clinic  = $data['clinic_name'] ?? 'The Clinic'; // ✅ multi-tenant clinic name

    $patient = $data['patient_name'] ?? '';
    $doctor  = $data['doctor_name'] ?? '';
    $date    = $data['date'] ?? '';
    $time    = $data['time'] ?? '';

    $oldDate = $data['old_date'] ?? '';
    $oldTime = $data['old_time'] ?? '';

    $formattedTime    = $time ? date("g:i A", strtotime($time)) : '';
    $formattedOldTime = $oldTime ? date("g:i A", strtotime($oldTime)) : '';

    if ($type === 'booking_user') {

        return "Dear {$patient},\n\n"
        . "Your appointment at {$clinic} with Dr. {$doctor} has been successfully confirmed.\n\n"
        . "Date: {$date}\n"
        . "Time: {$formattedTime}\n\n"
        . "Kindly arrive at your scheduled time.\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    else if ($type === 'booking_doctor') {

        return "Dear Dr. {$doctor},\n\n"
        . "A new appointment has been scheduled at {$clinic}.\n\n"
        . "Patient: {$patient}\n"
        . "Date: {$date}\n"
        . "Time: {$formattedTime}\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    // ✅ 2-hour reminder to patient
    else if ($type === 'reminder_2h_user') {

        return "Dear {$patient},\n\n"
        . "This is a reminder from {$clinic} that you have an appointment with Dr. {$doctor} in 2 hours.\n\n"
        . "Date: {$date}\n"
        . "Time: {$formattedTime}\n\n"
        . "Please ensure you arrive on time.\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    // ✅ 2-hour reminder to doctor
    else if ($type === 'reminder_2h_doctor') {

        return "Dear Dr. {$doctor},\n\n"
        . "Reminder from {$clinic}: You have an appointment in 2 hours.\n\n"
        . "Patient: {$patient}\n"
        . "Date: {$date}\n"
        . "Time: {$formattedTime}\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    else if ($type === 'cancel_by_user_user') {

        return "Dear {$patient},\n\n"
        . "Your appointment at {$clinic} with Dr. {$doctor} on {$date} at {$formattedTime} has been successfully cancelled.\n\n"
        . "You may schedule a new appointment at your convenience.\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    else if ($type === 'cancel_by_user_doctor') {

        return "Dear Dr. {$doctor},\n\n"
        . "The appointment with {$patient} scheduled on {$date} at {$formattedTime} at {$clinic} has been cancelled by the patient.\n\n"
        . "No further action is required.\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    else if ($type === 'cancel_by_admin_user') {

        return "Dear {$patient},\n\n"
        . "We regret to inform you that your appointment at {$clinic} with Dr. {$doctor} on {$date} at {$formattedTime} has been cancelled by the clinic.\n\n"
        . "Please contact {$clinic} or rebook at your convenience.\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    else if ($type === 'cancel_by_admin_doctor') {

        return "Dear Dr. {$doctor},\n\n"
        . "The appointment with {$patient} scheduled on {$date} at {$formattedTime} at {$clinic} has been cancelled by the clinic.\n\n"
        . "No further action is required.\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    // ✅ appointment rescheduled to patient
    else if ($type === 'reschedule_user') {

        $oldPart = ($oldDate !== '' && $formattedOldTime !== '')
          ? "Previous Schedule: {$oldDate} at {$formattedOldTime}\n"
          : "";

        return "Dear {$patient},\n\n"
        . "Your appointment at {$clinic} with Dr. {$doctor} has been rescheduled.\n\n"
        . $oldPart
        . "New Date: {$date}\n"
        . "New Time: {$formattedTime}\n\n"
        . "If this new schedule does not work for you, please contact {$clinic} or you may cancel the appointment and book again.\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    // ✅ appointment rescheduled to doctor
    else if ($type === 'reschedule_doctor') {

        $oldPart = ($oldDate !== '' && $formattedOldTime !== '')
          ? "Previous Schedule: {$oldDate} at {$formattedOldTime}\n"
          : "";

        return "Dear Dr. {$doctor},\n\n"
        . "The appointment with {$patient} at {$clinic} has been rescheduled.\n\n"
        . $oldPart
        . "New Date: {$date}\n"
        . "New Time: {$formattedTime}\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    // ✅ appointment completed to patient
    else if ($type === 'done_user') {

        return "Dear {$patient},\n\n"
        . "Your appointment at {$clinic} with Dr. {$doctor} on {$date} at {$formattedTime} has been marked as completed.\n\n"
        . "Thank you for visiting {$clinic}.\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    // ✅ appointment completed to doctor
    else if ($type === 'done_doctor') {

        return "Dear Dr. {$doctor},\n\n"
        . "The appointment with {$patient} at {$clinic} on {$date} at {$formattedTime} has been marked as completed.\n\n"
        . "{$clinic}\n"
        . "This is an automated notification. Do not reply to this message.";
    }

    else {
        return "Dear User, this is an automated appointment notification from {$clinic}.";
    }
}