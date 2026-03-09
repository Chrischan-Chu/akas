<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

function json_out(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

try {
  $stmt = $pdo->prepare("
    SELECT
      a.id,
      a.name,
      a.email,
      a.phone,
      a.cancel_count,
      a.is_blacklisted,
      a.blacklisted_at,
      a.blacklist_reason
    FROM accounts a
    WHERE a.is_blacklisted = 1
      AND EXISTS (
        SELECT 1
        FROM appointments ap
        WHERE ap.APT_UserID = a.id
          AND ap.APT_ClinicID = :cid
      )
    ORDER BY a.blacklisted_at DESC, a.id DESC
  ");
  $stmt->execute([':cid' => $clinicId]);

  $rows = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $rows[] = [
      'id' => (int)($r['id'] ?? 0),
      'name' => (string)($r['name'] ?? ''),
      'email' => (string)($r['email'] ?? ''),
      'phone' => (string)($r['phone'] ?? ''),
      'cancel_count' => (int)($r['cancel_count'] ?? 0),
      'is_blacklisted' => (int)($r['is_blacklisted'] ?? 0),
      'blacklisted_at' => (string)($r['blacklisted_at'] ?? ''),
      'blacklist_reason' => (string)($r['blacklist_reason'] ?? ''),
    ];
  }

  json_out([
    'ok' => true,
    'items' => $rows
  ]);

} catch (Throwable $e) {
  json_out(['ok' => false, 'message' => 'Server error'], 500);
}