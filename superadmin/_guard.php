<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$baseUrl = '/AKAS';
auth_require_role('super_admin', $baseUrl, '/superadmin/login.php');


// Persist session and always test if still logged in.