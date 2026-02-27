<?php

function sms_template($type, $data = [])
{
    $patient = $data['patient_name'] ?? '';
    $doctor  = $data['doctor_name'] ?? '';
    $date    = $data['date'] ?? '';
    $time    = $data['time'] ?? '';

    $formattedTime = $time ? date("g:i A", strtotime($time)) : '';

    if ($type === 'booking_user') {

        return "Dear {$patient},\n\n"
        . "Your appointment with Dr. {$doctor} has been successfully confirmed.\n\n"
        . "Date: {$date}\n"
        . "Time: {$formattedTime}\n\n"
        . "Kindly arrive at your scheduled time.\n\n"
        . "This is an automated notification. Do not reply on this message.";
    }

    else if ($type === 'booking_doctor') {

        return "Dear Dr. {$doctor},\n\n"
        . "A new appointment has been scheduled.\n\n"
        . "Patient: {$patient}\n"
        . "Date: {$date}\n"
        . "Time: {$formattedTime}\n\n"
        . "This is an automated notification. Do not reply on this message.";
    }

    else if ($type === 'cancel_by_user_user') {

        return "Dear {$patient},\n\n"
        . "Your appointment with Dr. {$doctor} on {$date} at {$formattedTime} has been successfully cancelled.\n\n"
        . "You may schedule a new appointment at your convenience.\n\n"
        . "This is an automated notification. Do not reply on this message.";
    }

    else if ($type === 'cancel_by_user_doctor') {

        return "Dear Dr. {$doctor},\n\n"
        . "The appointment with {$patient} scheduled on {$date} at {$formattedTime} has been cancelled by the patient.\n\n"
        . "No further action is required.\n\n"
        . "This is an automated notification. Do not reply on this message.";
    }

    else if ($type === 'cancel_by_admin_user') {

        return "Dear {$patient},\n\n"
        . "We regret to inform you that your appointment with Dr. {$doctor} on {$date} at {$formattedTime} has been cancelled by the clinic.\n\n"
        . "Please contact the clinic or rebook at your convenience.\n\n"
        . "This is an automated notification. Do not reply on this message.";
    }

    else if ($type === 'cancel_by_admin_doctor') {

        return "Dear Dr. {$doctor},\n\n"
        . "The appointment with {$patient} scheduled on {$date} at {$formattedTime} has been cancelled by the clinic.\n\n"
        . "No further action is required.\n\n"
        . "This is an automated notification. Do not reply on this message.";
    }

    else {
        return "Dear User, this is an automated appointment notification.";
    }
}