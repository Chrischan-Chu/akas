<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();

$isSuperAdmin = auth_is_logged_in() && auth_role() === 'super_admin';


$sql = "SELECT id,
               clinic_name, specialty, specialty_other, logo_path,
               description, address
        FROM clinics ";

if (!$isSuperAdmin) {
  $sql .= "WHERE approval_status = 'APPROVED' ";
}

$sql .= "ORDER BY updated_at DESC LIMIT 3";

$stmt = $pdo->query($sql);
$clinics = $stmt->fetchAll() ?: [];

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>

<section id="clinics" class="scroll-mt-24">
  <section class="px-4 py-14" style="background-color: var(--secondary);">

    <div class="max-w-6xl mx-auto">

      <div class="flex items-center justify-between gap-4 mb-8">
        <h2 class="text-3xl md:text-4xl font-extrabold text-white">
          Clinics
        </h2>

        <a href="/AKAS/pages/clinics-all.php"
           class="px-6 h-11 flex items-center rounded-full font-semibold text-white shadow-sm hover:opacity-95 transition"
           style="background-color: var(--primary);">
          View All Clinics
        </a>
      </div>


      <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-3 gap-6 px-8 lg:px-0">

        <?php foreach ($clinics as $c): ?>

          <a href="/AKAS/pages/clinic-profile.php?id=<?php echo urlencode((string)$c['id']); ?>"
             class="group block bg-white rounded-3xl shadow-sm hover:shadow-lg transition overflow-hidden">

            <div class="p-8 min-h-[210px] flex flex-col justify-between">

           
              <div class="flex items-center gap-5">

            
                <div class="h-20 w-20 rounded-3xl flex items-center justify-center shadow-sm overflow-hidden"
                     style="background: #40b7ff26;">
                  <?php if (!empty($c['logo_path'])): ?>
                    <img src="<?php echo h((string)$c['logo_path']); ?>" alt="Clinic Logo" class="h-full w-full object-cover" />
                  <?php else: ?>
                    <img src="https://cdn-icons-png.flaticon.com/512/2967/2967350.png" alt="Clinic" class="h-12 w-12" />
                  <?php endif; ?>
                </div>

                <div class="min-w-0">
                  <h5 class="text-xl font-extrabold text-[var(--primary)] truncate">
                    <?php echo h((string)($c['clinic_name'] ?? 'Clinic')); ?>
                  </h5>

                  <p class="text-slate-600 text-sm truncate">
                    <?php
                      $spec = (string)($c['specialty'] ?? '');
                      $specOther = (string)($c['specialty_other'] ?? '');
                      $display = ($spec === 'Other' && $specOther !== '') ? $specOther : $spec;
                    ?>
                    <?php echo h($display); ?>
                  </p>

                  <p class="text-slate-500 text-xs truncate mt-0.5">
                    <?php echo !empty($c['address']) ? h((string)$c['address']) : '—'; ?>
                  </p>
                </div>

              </div>


     
              <p class="text-slate-600 mt-5 text-base leading-relaxed line-clamp-3">
                <?php echo !empty($c['description']) ? h((string)$c['description']) : 'No clinic description yet.'; ?>
              </p>


              
              <div class="mt-6 text-sm font-bold group-hover:translate-x-1 transition"
                   style="color: var(--secondary);">
                View profile →
              </div>

            </div>

          </a>

        <?php endforeach; ?>

      </div>

    </div>
  </section>
</section>
