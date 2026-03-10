<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function akas_auto_complete_appointments(): int
{
    $pdo = db();

    $stmt = $pdo->prepare("
        UPDATE appointments
        SET APT_Status = 'DONE'
        WHERE APT_Status = 'APPROVED'
          AND TIMESTAMP(APT_Date, APT_Time) <= (NOW() - INTERVAL 1 HOUR)
    ");
    $stmt->execute();

    return $stmt->rowCount();
}