<?php

function convertToPHTime($datetime) {
    $utc = new DateTime($datetime, new DateTimeZone('UTC'));
    $utc->setTimezone(new DateTimeZone('Asia/Manila'));
    return $utc->format('Y-m-d h:i A');
}