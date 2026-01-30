
<section id="clinics" class="scroll-mt-24">
  <section class="px-4 py-10">
    <div class="max-w-6xl mx-auto">
      <div class="flex items-center justify-between gap-4 mb-6">
        <h2 class="text-2xl md:text-3xl font-bold text-white">Clinics</h2>
        <a href="/AKAS/pages/clinics-all.php"
           class="px-5 py-2 rounded-full font-semibold text-white transition-all duration-300"
           style="background-color: var(--primary);">
          View All Clinics
        </a>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <a href="/AKAS/pages/clinic-profile.php?id=<?php echo urlencode($i); ?>"
             class="block bg-white rounded-2xl shadow-sm overflow-hidden hover:shadow-md transition">
            <div class="p-6">
              <div class="flex items-center gap-4">
                <div class="h-14 w-14 rounded-2xl flex items-center justify-center"
                     style="background: rgba(64, 183, 255, .12);">
                  <img
                    src="https://cdn-icons-png.flaticon.com/512/2967/2967350.png"
                    alt="Clinic"
                    class="h-9 w-9"
                  >
                </div>
                <div class="min-w-0">
                  <h5 class="text-lg font-semibold truncate">Clinic Name <?php echo $i; ?></h5>
                  <p class="text-gray-600 text-sm truncate">Medical Specialty • Barangay</p>
                </div>
              </div>
              <p class="text-gray-700 mt-4 text-sm leading-relaxed line-clamp-3">
                Short clinic description goes here. This can be fetched from the database.
              </p>
              <div class="mt-5 text-sm font-semibold" style="color: var(--primary);">
                View profile →
              </div>
            </div>
          </a>
        <?php endfor; ?>
      </div>
    </div>
  </section>
</section>
