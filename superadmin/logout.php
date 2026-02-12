<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$baseUrl = '/AKAS';

auth_logout();

header('Location: ' . $baseUrl . '/superadmin/login.php');
exit;
