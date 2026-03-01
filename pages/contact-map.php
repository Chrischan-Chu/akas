<?php
// pages/contact-map.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/geocode.php';

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

$bounds = [
  'sw' => ['lat' => 15.11, 'lng' => 120.52],
  'ne' => ['lat' => 15.19, 'lng' => 120.67],
];

// Load clinics that have an address, and (if available) coords.
$clinics = [];
try {
  $pdo = db();
  $hasGeoCols = akas_clinic_geo_columns_exist($pdo);
  $selectGeo = $hasGeoCols ? ", latitude, longitude" : "";

  $stmt = $pdo->prepare("
    SELECT id, clinic_name, address{$selectGeo}, approval_status
    FROM clinics
    WHERE approval_status = 'APPROVED'
      AND address IS NOT NULL AND TRIM(address) <> ''
    ORDER BY clinic_name ASC
  ");
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($rows as $r) {
    $lat = ($hasGeoCols && array_key_exists('latitude', $r) && $r['latitude'] !== null) ? (float)$r['latitude'] : null;
    $lng = ($hasGeoCols && array_key_exists('longitude', $r) && $r['longitude'] !== null) ? (float)$r['longitude'] : null;

    // If we have coords, only include clinics inside Angeles bounding box
    if ($lat !== null && $lng !== null) {
      if ($lat < $bounds['sw']['lat'] || $lat > $bounds['ne']['lat'] || $lng < $bounds['sw']['lng'] || $lng > $bounds['ne']['lng']) {
        continue;
      }
    }

    $clinics[] = [
      'id' => (int)$r['id'],
      'name' => (string)$r['clinic_name'],
      'address' => (string)($r['address'] ?? ''),
      'lat' => $lat,
      'lng' => $lng,
    ];
  }
} catch (Throwable $e) {
  $clinics = [];
}

// Default center: Angeles City, Pampanga
$payload = [
  'defaultCenter' => ['lat' => 15.1450, 'lng' => 120.5936],
  'defaultZoom' => 13,
  'bounds' => $bounds,
  'clinics' => $clinics,
];
?>

<section id="clinic-map" class="scroll-mt-24 py-14" style="background: linear-gradient(180deg, rgba(59,130,246,.08) 0%, rgba(255,255,255,0) 70%);">
  <div class="max-w-6xl mx-auto px-4">

    <div class="flex items-end justify-between flex-wrap gap-3">
      <div>
        <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900">Map</h2>
        <p class="text-slate-600 mt-1">Find an approved clinic in Angeles City, Pampanga and jump to its pinned location.</p>
      </div>
      <div class="text-xs font-semibold text-slate-600 px-3 py-2 rounded-full border border-slate-200 bg-white/80">
        <?php if (count($clinics) === 0): ?>
          No clinics pinned yet
        <?php else: ?>
          <?= (int)count($clinics); ?> clinic(s) available
        <?php endif; ?>
      </div>
    </div>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-5 gap-6">
      <!-- Controls / info -->
      <div class="lg:col-span-2">
        <div class="rounded-3xl bg-white shadow-sm border border-slate-200">
          <div class="p-6">
            <label class="block text-sm font-extrabold text-slate-800">Select clinic</label>
            <p class="text-sm text-slate-600 mt-1">Choose a clinic to center the map and open its address pin.</p>

            <!-- Hidden REAL select (kept for compatibility; JS will keep it empty behind the scenes) -->
            <select id="akasClinicSelect" class="hidden" aria-hidden="true" tabindex="-1">
              <option value="">Select a clinic…</option>
              <?php foreach ($clinics as $c): ?>
                <option value="<?= (int)$c['id']; ?>"><?= h((string)$c['name']); ?></option>
              <?php endforeach; ?>
            </select>

            <!-- Custom dropdown UI -->
            <div class="mt-4 relative">
              <button
                id="akasClinicSelectBtn"
                type="button"
                class="w-full text-left rounded-2xl px-4 pr-12 py-3 font-semibold border border-slate-300 bg-white focus:outline-none focus:ring-2"
                style="--tw-ring-color: rgba(59,130,246,.45);"
                aria-haspopup="listbox"
                aria-expanded="false"
              >
                <span id="akasClinicSelectBtnText">Select a clinic…</span>
              </button>

              <div class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                </svg>
              </div>

              <div
  id="akasClinicSelectMenu"
  class="hidden absolute z-30 mt-2 w-full rounded-2xl border border-slate-200 bg-white shadow-lg overflow-hidden"
  role="listbox"
>

                <div class="max-h-60 overflow-y-auto">
                  <?php foreach ($clinics as $c): ?>
                    <button
                      type="button"
                      class="w-full px-4 py-3 text-left text-sm font-semibold text-slate-800 hover:bg-slate-50"
                      data-id="<?= (int)$c['id']; ?>"
                      data-name="<?= h((string)$c['name']); ?>"
                    >
                      <?= h((string)$c['name']); ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <div class="mt-4 text-sm text-slate-600">
              <span class="font-semibold text-slate-800">Note:</span>
              The map is limited to clinics within Angeles City bounds.
            </div>
          </div>
        </div>
      </div>

      <!-- Map -->
      <div class="lg:col-span-3">
        <div class="rounded-3xl overflow-hidden border border-slate-200 bg-white shadow-sm">
          <div id="akasClinicMap" style="height: 460px;"></div>
        </div>
        <div class="mt-3 text-sm text-slate-600">
          <?php if (count($clinics) === 0): ?>
            No clinics with saved addresses in Angeles City yet.
          <?php else: ?>
            Showing <?= (int)count($clinics); ?> clinic(s) within Angeles City bounds.
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</section>

<script>
  window.AKAS_CLINICS_MAP = <?= json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>