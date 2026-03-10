<?php
 declare(strict_types=1);
 
 header("Content-Type: text/html; charset=UTF-8");
 
 $baseUrl  = '';
 require_once __DIR__ . '/../includes/db.php';
 
 $pdo = db();
 
 $doctorId = (int)($_GET['id'] ?? 0);
 $clinicId = (int)($_GET['clinic_id'] ?? 0);
 
 function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
 function to12(?string $t): string {
   $t = trim((string)$t);
   if ($t === '') return '';
   $ts = strtotime($t);
   if ($ts === false) return $t;
   return date('g:i A', $ts);
 }
 
 function schedule_to_lines(?string $raw): array {
   $raw = trim((string)$raw);
   if ($raw === '') return [];
 
   $data = json_decode($raw, true);
   if (!is_array($data)) {
     return array_values(array_filter(array_map('trim', preg_split('/
|
|
/', $raw))));
   }
 
   if (isset($data['days'], $data['start'], $data['end']) && is_array($data['days'])) {
     $numToLabel = [0=>'Sun',1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat'];
     $days = array_values(array_unique(array_map('intval', $data['days'])));
     sort($days);
 
     $labels = [];
     foreach ($days as $d) {
       if (isset($numToLabel[$d])) $labels[] = $numToLabel[$d];
     }
 
     $line = ($labels ? implode(', ', $labels) : 'Selected days') . ': ' . to12((string)$data['start']) . ' – ' . to12((string)$data['end']);
     $mins = (int)($data['slot_mins'] ?? 0);
     if ($mins > 0) $line .= " ({$mins} mins per slot)";
     return [$line];
   }
 
   $order = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
   $lines = [];
   foreach ($order as $day) {
     if (!isset($data[$day]) || !is_array($data[$day]) || empty($data[$day]['enabled'])) continue;
     $start = trim((string)($data[$day]['start'] ?? ''));
     $end = trim((string)($data[$day]['end'] ?? ''));
     if ($start === '' || $end === '') continue;
 
     $line = $day . ': ' . to12($start) . ' – ' . to12($end);
     $mins = (int)($data[$day]['slot_mins'] ?? 0);
     if ($mins > 0) $line .= " ({$mins} mins per slot)";
     $lines[] = $line;
   }
 
   return $lines;
 }
 
 if ($doctorId <= 0 || $clinicId <= 0) {
   echo '<div style="background:#fff;border:1px solid #dbe3ef;border-radius:24px;padding:24px;color:#334155;">Doctor not found.</div>';
   exit;
 }
 
 $stmt = $pdo->prepare('
   SELECT d.id, d.name, d.about, d.availability, d.image_path,
          d.birthdate, d.specialization, d.prc_no, d.schedule, d.email, d.contact_number,
          c.clinic_name, c.specialty, c.specialty_other
   FROM clinic_doctors d
   JOIN clinics c ON c.id = d.clinic_id
   WHERE d.id = ?
     AND d.clinic_id = ?
   LIMIT 1
 ');
 $stmt->execute([$doctorId, $clinicId]);
 $doctor = $stmt->fetch();
 
 if (!$doctor) {
   echo '<div style="background:#fff;border:1px solid #dbe3ef;border-radius:24px;padding:24px;color:#334155;">Doctor not found.</div>';
   exit;
 }
 
 $img = trim((string)($doctor['image_path'] ?? ''));
 if ($img === '') {
   $img = $baseUrl . '/assets/img/doctor1.png';
 }
 
 $clinicName = trim((string)($doctor['clinic_name'] ?? 'Clinic'));
 $spec = trim((string)($doctor['specialty'] ?? ''));
 $specOther = trim((string)($doctor['specialty_other'] ?? ''));
 $specialty = ($spec === 'Other' && $specOther !== '') ? $specOther : $spec;
 
 $doctorSpecialization = trim((string)($doctor['specialization'] ?? ''));
 if ($doctorSpecialization === '') {
   $doctorSpecialization = $specialty !== '' ? $specialty : 'Doctor';
 }
 
 $about = trim((string)($doctor['about'] ?? ''));
 $scheduleLines = schedule_to_lines((string)($doctor['schedule'] ?? ''));
 if (empty($scheduleLines)) {
   $scheduleLines = schedule_to_lines((string)($doctor['availability'] ?? ''));
 }
 
 $prc = trim((string)($doctor['prc_no'] ?? ''));
 $email = trim((string)($doctor['email'] ?? ''));
 $contact = trim((string)($doctor['contact_number'] ?? ''));
 $birthdate = trim((string)($doctor['birthdate'] ?? ''));
 ?>
 <style>
 .akas-doctor-modal-layout{display:grid;grid-template-columns:320px minmax(0,1fr);gap:24px;align-items:start}
 .akas-left-card{position:relative;background:linear-gradient(145deg,#37abf4 0%,#7bc9fb 55%,#99dcff 100%);color:#fff;border-radius:30px;padding:30px;border:1px solid rgba(255,255,255,.28);box-shadow:0 18px 40px rgba(32,121,191,.20), inset 0 1px 0 rgba(255,255,255,.18)}
 .akas-left-card::before{content:"";position:absolute;inset:0;border-radius:inherit;background:linear-gradient(180deg,rgba(255,255,255,.12),rgba(255,255,255,0) 42%);pointer-events:none}
 .akas-panel{background:rgba(255,255,255,.96);border:1px solid #dbe3ef;border-radius:28px;overflow:hidden;box-shadow:0 14px 34px rgba(15,23,42,.06)}
 .akas-box{background:linear-gradient(180deg,#fbfdff 0%,#f5f9ff 100%);border:1px solid #d7e3f2;border-radius:22px;padding:20px;box-shadow:inset 0 1px 0 rgba(255,255,255,.75)}
 .akas-label{font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;color:#8ca0b8}
 .akas-value{margin-top:10px;font-size:16px;font-weight:800;color:#0f172a;line-height:1.5;word-break:break-word}
 .akas-value-light{margin-top:10px;font-size:15px;font-weight:700;color:#fff;line-height:1.65;word-break:break-word}
 .akas-grid-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
 .akas-stack{display:flex;flex-direction:column;gap:22px}
 .akas-chip{display:inline-flex;align-items:center;justify-content:center;padding:9px 18px;border-radius:999px;background:rgba(255,255,255,.96);color:#0b6cad;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;box-shadow:0 10px 24px rgba(10,108,173,.18)}
 .akas-clinic-pill{display:inline-block;margin-top:16px;padding:13px 18px;border-radius:999px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.22);font-weight:700;line-height:1.5;word-break:break-word;box-shadow:inset 0 1px 0 rgba(255,255,255,.12)}
 .akas-schedule-line{padding:15px 18px;border-top:1px solid #dbe3ef;font-size:15px;font-weight:700;color:#334155;background:#fff}
 .akas-schedule-line:nth-child(even){background:#fbfdff}
 .akas-schedule-line:first-child{border-top:none}
 @media (max-width: 980px){
   .akas-doctor-modal-layout{grid-template-columns:1fr}
 }
 @media (max-width: 640px){
   .akas-grid-two{grid-template-columns:1fr}
   .akas-left-card{padding:22px}
 }
 </style>
 
 <div class="akas-doctor-modal-layout">
   <aside class="akas-left-card">
     <div style="display:flex;flex-direction:column;align-items:center;text-align:center;">
       <div style="width:126px;height:126px;border-radius:999px;overflow:hidden;background:rgba(255,255,255,.22);border:4px solid rgba(255,255,255,.28);box-shadow:0 14px 30px rgba(15,23,42,.14), inset 0 1px 0 rgba(255,255,255,.2);position:relative;z-index:1;">
         <img src="<?php echo h($img); ?>" alt="Doctor" style="width:100%;height:100%;object-fit:cover;display:block;" loading="lazy" decoding="async">
       </div>
 
       <h2 style="margin:20px 0 0;font-size:24px;line-height:1.18;font-weight:900;letter-spacing:-0.02em;text-shadow:0 2px 10px rgba(15,23,42,.08);word-break:break-word;"><?php echo h((string)$doctor['name']); ?></h2>
       <p style="margin:10px 0 0;font-size:16px;font-weight:800;letter-spacing:.01em;opacity:.96;word-break:break-word;"><?php echo h($doctorSpecialization); ?></p>
       <div style="margin-top:16px;"><span class="akas-chip">Doctor</span></div>
       <div class="akas-clinic-pill"><?php echo h($clinicName); ?></div>
     </div>
   </aside>
 
   <section class="akas-stack">
     <div class="akas-panel" style="padding:30px;">
       <h3 style="margin:0;font-size:23px;line-height:1.2;font-weight:900;letter-spacing:-0.02em;color:#0f172a;">Doctor Details</h3>
       <p style="margin:8px 0 0;font-size:15px;color:#64748b;">Professional information and contact details.</p>
 
       <div class="akas-grid-two" style="margin-top:20px;">
         <div class="akas-box">
           <div class="akas-label">PRC Number</div>
           <div class="akas-value"><?php echo h($prc !== '' ? $prc : '—'); ?></div>
         </div>
         <div class="akas-box">
           <div class="akas-label">Birthdate</div>
           <div class="akas-value"><?php echo h($birthdate !== '' ? $birthdate : '—'); ?></div>
         </div>
         <div class="akas-box">
           <div class="akas-label">Contact Number</div>
           <div class="akas-value"><?php echo h($contact !== '' ? $contact : '—'); ?></div>
         </div>
         <div class="akas-box">
           <div class="akas-label">Email Address</div>
           <div class="akas-value"><?php echo h($email !== '' ? $email : '—'); ?></div>
         </div>
       </div>
     </div>
 
     <div class="akas-panel">
       <div style="padding:26px 30px;border-bottom:1px solid #dbe3ef;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%);">
         <div>
           <h3 style="margin:0;font-size:23px;line-height:1.2;font-weight:900;letter-spacing:-0.02em;color:#0f172a;">Availability Schedule</h3>
           <p style="margin:8px 0 0;font-size:16px;color:#64748b;">Simple schedule view</p>
         </div>
         <span style="display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border-radius:999px;background:#eff6ff;color:#0369a1;font-size:12px;font-weight:900;letter-spacing:.04em;box-shadow:inset 0 1px 0 rgba(255,255,255,.8);">Clinic hours</span>
       </div>
 
       <?php if (empty($scheduleLines)): ?>
         <div style="padding:20px 28px;font-size:15px;color:#64748b;background:#fff;">No availability set yet.</div>
       <?php else: ?>
         <div>
           <?php foreach ($scheduleLines as $line): ?>
             <div class="akas-schedule-line"><?php echo h($line); ?></div>
           <?php endforeach; ?>
         </div>
       <?php endif; ?>
     </div>
 
     <?php if ($about !== ''): ?>
       <div class="akas-panel" style="padding:30px;">
         <h3 style="margin:0;font-size:23px;line-height:1.2;font-weight:900;letter-spacing:-0.02em;color:#0f172a;">About</h3>
         <p style="margin:16px 0 0;font-size:15px;line-height:1.8;color:#475569;word-break:break-word;"><?php echo nl2br(h($about)); ?></p>
       </div>
     <?php endif; ?>
   </section>
 </div>
 