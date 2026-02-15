<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

function json_out(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
}

if (auth_role() !== 'clinic_admin') {
  json_out(['ok' => false, 'message' => 'Forbidden'], 403);
}

$pdo = db();
$clinicId = (int)(auth_clinic_id() ?? 0);
if ($clinicId <= 0) {
  json_out(['ok' => false, 'message' => 'Clinic not linked.'], 400);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) $payload = [];

$apptId = (int)($payload['appointment_id'] ?? 0);
$action = strtoupper(trim((string)($payload['action'] ?? '')));

if ($apptId <= 0) json_out(['ok' => false, 'message' => 'Invalid appointment_id'], 422);
if (!in_array($action, ['CANCELLED','DONE'], true)) {
  json_out(['ok' => false, 'message' => 'Invalid action'], 422);
}

// Make sure appointment belongs to this clinic
$chk = $pdo->prepare("
  SELECT APT_Status
  FROM appointments
  WHERE APT_AppointmentID = :id AND APT_ClinicID = :cid
  LIMIT 1
");
$chk->execute([':id' => $apptId, ':cid' => $clinicId]);
$row = $chk->fetch(PDO::FETCH_ASSOC);

if (!$row) json_out(['ok' => false, 'message' => 'Appointment not found'], 404);

$cur = strtoupper((string)($row['APT_Status'] ?? ''));
if ($cur === 'DONE' || $cur === 'CANCELLED') {
  json_out(['ok' => false, 'message' => 'This appointment is already closed.'], 409);
}

$upd = $pdo->prepare("
  UPDATE appointments
  SET APT_Status = :st
  WHERE APT_AppointmentID = :id AND APT_ClinicID = :cid
  LIMIT 1
");
$upd->execute([':st' => $action, ':id' => $apptId, ':cid' => $clinicId]);

json_out(['ok' => true, 'status' => $action]);
