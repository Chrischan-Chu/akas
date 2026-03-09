<footer class="bg-white border-b-4 border-red-600 font-sans">
  <div class="max-w-6xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">

      <!-- LEFT: Logo + Tagline -->
      <div>
        <img
          src="<?php echo $baseUrl; ?>/assets/img/akas-logo.png"
          alt="AKAS Logo"
          class="h-12 w-auto"
        >

        <p class="text-xs font-semibold text-[#FF9239] mt-2">
          Angeles Kilink Appointment System
        </p>
      </div>

      <!-- MIDDLE: About -->
      <div>
        <div class="mt-2">
          <h4 class="text-lg font-extrabold text-[#40B7FF]">About AKAS</h4>
          <p class="mt-2 text-xs leading-6 text-slate-600">
            We built AKAS to make the appointment process simpler and more organized. With AKAS, patients can request or book an appointment online without needing to line up or call repeatedly. Clinics also get an easier way to manage schedules, track requests, and keep basic patient details in one place. It is meant to help clinics work faster and help patients access healthcare with less hassle.
          </p>
        </div>
      </div>

      <!-- RIGHT: Contact -->
      <div class="md:border-l md:border-gray-200 md:pl-8">
        <div class="text-xs text-gray-800 space-y-2">
          <div class="flex gap-2">
            <span class="font-extrabold">Call :</span>
            <span>+63 961 0978 082</span>
          </div>

          <div class="flex gap-2">
            <span class="font-extrabold">Email:</span>
            <span>akas.appointment.system@gmail.com</span>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="text-center text-xs text-gray-500 py-3">
    © <?php echo date('Y'); ?> AKAS. All Rights Reserved.
  </div>
</footer>

<script src="<?php echo $baseUrl; ?>/assets/js/global.js?v=<?php echo time(); ?>"></script>