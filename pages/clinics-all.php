<?php

$appTitle  = "AKAS | All Clinics";
$baseUrl   = "/AKAS";
include __DIR__ . "/../includes/partials/head.php";


$tabs = [
  "all"       => "All",
  "general"   => "General",
  "dental"    => "Dental",
  "pediatric" => "Pediatric",
  "derma"     => "Derma",
];


$activeTab = $_GET["tab"] ?? "all";
if (!isset($tabs[$activeTab])) $activeTab = "all";
$q = trim($_GET["q"] ?? "");

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';


function clinic_category_from_specialty(string $specialty): string {
  $s = strtolower($specialty);
  if (str_contains($s, 'dental')) return 'dental';
  if (str_contains($s, 'pedia')) return 'pediatric';
  if (str_contains($s, 'derma')) return 'derma';
  return 'general';
}

$isSuperAdmin = auth_is_logged_in() && auth_role() === 'super_admin';

$sql = "SELECT
        c.id AS clinic_id,
        c.clinic_name, c.specialty, c.specialty_other, c.logo_path,
        c.description, c.address, c.contact, c.email, c.is_open, c.open_time, c.close_time
     FROM clinics c ";

if (!$isSuperAdmin) {
  $sql .= "WHERE c.approval_status = 'APPROVED' ";
}

$sql .= "ORDER BY c.clinic_name ASC";

$rows = db()->query($sql)->fetchAll();

$clinics = [];
foreach ($rows as $r) {
  $clinicName = trim((string)($r['clinic_name'] ?? ''));
  if ($clinicName === '') continue;

  $specialty = (string)($r['specialty'] ?? '');
  $specialtyOther = (string)($r['specialty_other'] ?? '');

  $displayType = ($specialty === 'Other' && $specialtyOther !== '') ? $specialtyOther : $specialty;
  $category  = clinic_category_from_specialty($specialty);

  $clinics[] = [
    'id'          => (int)$r['clinic_id'],
    'name'        => $clinicName,
    'specialty'   => $displayType,
    'category'    => $category,
    'logo_path'   => (string)($r['logo_path'] ?? ''),
    'description' => (string)($r['description'] ?? ''),
    'address'     => (string)($r['address'] ?? ''),
    'contact'     => (string)($r['contact'] ?? ''),
    'email'       => (string)($r['email'] ?? ''),
    'is_open'     => (int)($r['is_open'] ?? 1),
    'open_time'   => (string)($r['open_time'] ?? ''),
    'close_time'  => (string)($r['close_time'] ?? ''),
  ];
}


$qLower = strtolower($q);
$clinics = array_values(array_filter($clinics, function ($c) use ($activeTab, $qLower) {
  if ($activeTab !== 'all' && ($c['category'] ?? '') !== $activeTab) return false;
  if ($qLower !== '') {
    $hay = strtolower(($c['name'] ?? '') . ' ' . ($c['specialty'] ?? '') . ' ' . ($c['address'] ?? ''));
    if (strpos($hay, $qLower) === false) return false;
  }
  return true;
}));
?>

<body class="min-h-screen flex flex-col bg-gradient-to-br from-white to-[var(--secondary)]/40 overflow-x-hidden">

<?php include __DIR__ . "/../includes/partials/navbar.php"; ?>

<main class="flex-1 px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
  <div class="max-w-6xl mx-auto">


    <section class="rounded-3xl p-6 sm:p-8 bg-white/70 border-white/60 shadow-sm">
      <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
          <p class="text-[var(--secondary)] font-semibold tracking-wide uppercase text-xs">
            Browse
          </p>
          <h1 class="text-2xl sm:text-3xl font-extrabold text-[var(--primary)] leading-tight">
            Clinics
          </h1>
          <p class="text-slate-600 text-sm mt-1">
            Find the right clinic and book an appointment.
          </p>
        </div>

        <a href="<?php echo $baseUrl; ?>/index.php#clinics"
           class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-full font-semibold text-white transition
                  hover:opacity-95 active:scale-[0.99] w-full sm:w-auto"
           style="background: var(--primary);">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Back to Home
        </a>
      </div>

 
      <div class="mt-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

    
        <div class="flex-shrink-0">
          <div class="inline-flex gap-2 p-1 rounded-full bg-white border border-slate-200 shadow-sm">

            <?php foreach ($tabs as $key => $label): ?>
              <?php
                $isActive = ($key === $activeTab);
                $tabUrl = $baseUrl . "/pages/clinics-all.php?tab=" . urlencode($key) . "&q=" . urlencode($q);
              ?>
              <a href="<?php echo htmlspecialchars($tabUrl); ?>"
                class="px-5 py-2 rounded-full text-sm font-semibold transition whitespace-nowrap
                        <?php echo $isActive ? 'text-white' : 'text-slate-700 hover:text-slate-900'; ?>"
                style="<?php echo $isActive ? 'background: var(--primary);' : ''; ?>">
                <?php echo htmlspecialchars($label); ?>
              </a>
            <?php endforeach; ?>

          </div>
        </div>


        <form class="flex items-center gap-3 w-full lg:w-[420px]" method="get" action="">
          <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">

          <label class="relative flex-1">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
              🔍
            </span>

            <input
              id="clinicSearch"
              type="text"
              name="q"
              value="<?php echo htmlspecialchars($q); ?>"
              placeholder="Search clinic…"
              class="w-full pl-10 pr-4 h-11 rounded-full bg-white border border-slate-200
                    text-sm focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/60"
            >
          </label>

          <button type="submit"
                  class="h-11 px-6 rounded-full font-semibold text-white"
                  style="background: var(--primary);">
            Search
          </button>
        </form>

      </div>

                                  
    <section class="mt-8">
      <div id="clinicGrid" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($clinics as $c): ?>
          <?php
  
            $returnUrl = $baseUrl . "/pages/clinics-all.php?tab=" . urlencode($activeTab) . "&q=" . urlencode($q);
          ?>
          <a
            class="clinic-card group block rounded-3xl"
            data-category="<?php echo htmlspecialchars($c["category"]); ?>"
            data-name="<?php echo htmlspecialchars(strtolower($c["name"])); ?>"
            data-specialty="<?php echo htmlspecialchars(strtolower($c["specialty"])); ?>"
            data-address="<?php echo htmlspecialchars(strtolower($c["address"])); ?>"
            href="<?php echo $baseUrl; ?>/pages/clinic-profile.php?id=<?php echo urlencode($c["id"]); ?>&return=<?php echo urlencode($returnUrl); ?>"
          >
            <article class="h-full min-h-[320px] rounded-3xl bg-white shadow-sm border border-slate-100 overflow-hidden transition
                group-hover:shadow-sm group-hover:-translate-y-1">
              <div class="h-40 flex items-center justify-between px-6"
                   style="background: linear-gradient(90deg, rgba(64,183,255,.18), rgba(144,213,255,.30));">
                <div class="flex items-center gap-3">
                  <div class="h-28 w-28 rounded-2xl bg-white/85 flex items-center justify-center border border-white/60 shadow-sm">
                    <img
                      src="<?php echo htmlspecialchars($c['logo_path'] ?: 'https://cdn-icons-png.flaticon.com/512/2967/2967350.png'); ?>"
                      class="w-20 h-20"
                      alt="Clinic"
                      loading="lazy"
                    >
                  </div>
                  <div class="leading-tight min-w-0">
                    <h2 class="font-extrabold text-slate-900 truncate">
                      <?php echo htmlspecialchars($c["name"]); ?>
                    </h2>
                    <p class="text-xs font-semibold truncate" style="color: rgba(11,56,105,.75);">
                      <?php echo htmlspecialchars($c["specialty"]); ?>
                    </p>
                  </div>
                </div>

                <span class="text-xs font-bold px-3 py-1 rounded-full"
                      style="background: rgba(255,190,138,.30); color: rgba(11,56,105,.85);">
                  View
                </span>
              </div>

              <div class="p-6 min-w-0">
                <p class="text-sm text-slate-600 leading-relaxed whitespace-normal break-words"
                   style="overflow-wrap:anywhere; word-break:break-word;">
                  Address: <span class="font-semibold"><?php echo htmlspecialchars($c["address"] ?: '—'); ?></span>
                </p>

                <p class="text-xs text-slate-500 mt-2 whitespace-normal break-words"
                   style="overflow-wrap:anywhere; word-break:break-word;">
                  Contact:
                  <span class="font-semibold text-slate-700">
                    <?php
                      $phone = preg_replace('/\D/', '', (string)($c['contact'] ?? ''));

                      echo ($phone && strlen($phone) === 10)
                        ? '+63 ' . htmlspecialchars($phone)
                        : '—';
                    ?>
                  </span>

                </p>

                <?php if (!empty($c['email'])): ?>
                  <p class="text-xs text-slate-500 mt-1 whitespace-normal break-words"
                     style="overflow-wrap:anywhere; word-break:break-word;">
                    Email: <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($c['email']); ?></span>
                  </p>
                <?php endif; ?>

                <div class="mt-5 flex items-center justify-between">
                  <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500">
                    <span class="h-2 w-2 rounded-full" style="background: var(--primary);"></span>
                    <?php if ((int)$c['is_open'] === 1): ?>
                      Open<?php if ($c['open_time'] && $c['close_time']): ?> • <?php echo htmlspecialchars(substr($c['open_time'],0,5)); ?>–<?php echo htmlspecialchars(substr($c['close_time'],0,5)); ?><?php endif; ?>
                    <?php else: ?>
                      Closed
                    <?php endif; ?>
                  </span>

                  <span class="inline-flex items-center gap-2 text-sm font-bold" style="color: var(--primary);">
                    Details
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  </span>
                </div>
              </div>
            </article>
          </a>
        <?php endforeach; ?>

      </div>

      
      <div id="emptyState" class="hidden mt-10 text-center">
        <div class="mx-auto max-w-md rounded-3xl bg-white/70 border border-white/60 p-8 shadow-sm">
          <h3 class="text-lg font-extrabold text-slate-900">No clinics found</h3>
          <p class="text-sm text-slate-600 mt-2">
            Try a different keyword or switch tabs.
          </p>
        </div>
      </div>
    </section>

  </div>
</main>

<?php include __DIR__ . "/../includes/partials/footer.php"; ?>

<script>

(function () {
  const input = document.getElementById("clinicSearch");
  const cards = Array.from(document.querySelectorAll(".clinic-card"));
  const empty = document.getElementById("emptyState");

  const url = new URL(window.location.href);
  const activeTab = url.searchParams.get("tab") || "all";

  function applyFilter() {
    const q = (input.value || "").trim().toLowerCase();
    let visible = 0;

    cards.forEach(card => {
      const category = card.dataset.category || "";
      const name = card.dataset.name || "";
      const specialty = card.dataset.specialty || "";
      const address = card.dataset.address || "";

      const tabOk = (activeTab === "all") || (category === activeTab);
      const qOk = (q === "") || name.includes(q) || specialty.includes(q) || address.includes(q);

      const show = tabOk && qOk;
      card.classList.toggle("hidden", !show);
      if (show) visible++;
    });

    empty.classList.toggle("hidden", visible !== 0);
  }

  input.addEventListener("input", applyFilter);
  applyFilter();
})();
</script>

<script src="<?php echo $baseUrl; ?>/assets/js/global.js" defer></script>
</body>
</html>
