<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/appointment_auto_complete.php';

header('Content-Type: application/json; charset=utf-8');

$token = (string)($_GET['token'] ?? '');
$expected = (string)(getenv('CRON_TOKEN') ?: '');

if ($expected !== '' && !hash_equals($expected, $token)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Forbidden'
    ]);
    exit;
}

try {
    $updated = akas_auto_complete_appointments();

    echo json_encode([
        'ok' => true,
        'updated' => $updated,
        'message' => 'Auto-complete executed successfully.'
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Auto-complete failed.',
        'details' => $e->getMessage()
    ]);
    exit;
}