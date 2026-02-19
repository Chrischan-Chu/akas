<?php
declare(strict_types=1);

$baseUrl = '';
require_once __DIR__ . '/includes/auth.php';

auth_logout();

header('Location: ' . $baseUrl . '/index.php#home');
exit;
