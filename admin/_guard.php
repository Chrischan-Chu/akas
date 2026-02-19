<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$baseUrl = '';
auth_require_role('clinic_admin', $baseUrl);

$clinicId = (int)auth_clinic_id();
if ($clinicId <= 0) {
  // Incomplete clinic signup (Google admin)
  header('Location: ' . $baseUrl . '/pages/signup-admin.php?step=2&locked=1');
  exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT approval_status FROM clinics WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $clinicId]);
$status = (string)($stmt->fetchColumn() ?? 'PENDING');

if ($status !== 'APPROVED') {
  header('Location: ' . $baseUrl . '/admin/pending.php');
  exit;
}
