<?php
$clinics = [
  ["id" => 1, "name" => "AKAS Health Clinic – Angeles City"],
  ["id" => 2, "name" => "St. Maria Clinic – San Fernando"],
  ["id" => 3, "name" => "CityCare Clinic – Mabalacat"],
];
$success = isset($_GET['sent']);
?>

<section id="contact" class="scroll-mt-24" style="background-color: white;">

 
  <section class="py-6 text-center" style="background:var(--primary)">
    <div class="max-w-6xl mx-auto px-4">
      <h1 class="text-3xl tracking-widest font-bold text-white">CONTACT</h1>
      <p class="mt-2 text-white">
        Send feedback to a clinic or reach out to the AKAS developers.
      </p>
    </div>
  </section>


 
  <section class="py-12 px-4 ">
    <div class="max-w-6xl mx-auto">

      <?php if($success): ?>
        <div class="mb-6 rounded-2xl bg-white p-4 border border-green-200 shadow-sm">
          <p class="text-green-700 font-semibold">
            ✅ Message sent successfully (mock)
          </p>
        </div>
      <?php endif; ?>


      <div class="rounded-3xl shadow-sm p-6 md:p-10 text-white" style="background-color: var(--secondary);">
        <form class="grid grid-cols-1 lg:grid-cols-2 gap-8">

       
          <div class="space-y-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Choose Clinic
              </label>

              <div class="relative">
                <select
                  name="clinic_id"
                  class="appearance-none w-full rounded-xl px-4 pr-12 py-3 text-white font-medium"
                  style="background-color: white; color: black;"
                  required
                >
                  <?php foreach($clinics as $c): ?>
                    <option value="<?php echo $c['id']; ?>">
                      <?php echo $c['name']; ?>
                    </option>
                  <?php endforeach; ?>
                </select>

      
                <div class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-black">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 9l6 6 6-6"/>
                  </svg>
                </div>
              </div>

              <p class="text-xs text-gray-500 mt-2">
                Your message will be sent to the selected clinic.
              </p>
            </div>
          
            <input type="text"
              placeholder="Your Name"
              class="w-full rounded-xl px-4 py-3 text-black placeholder-black"
              style="background-color: white;" required>


            <input type="email"
              placeholder="Email"
              class="w-full rounded-xl px-4 py-3 text-black placeholder-black"
              style="background-color: white;" required>


            <input type="text"
              placeholder="Subject"
              class="w-full rounded-xl px-4 py-3 text-black placeholder-black"
              style="background-color: white; " required>


            <div class="relative">
              <select
                name="category"
                class="appearance-none w-full rounded-xl px-4 pr-12 py-3 text-black font-medium
                       focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
                style="background-color: white;" required
              >
                <option>Feedback</option>
                <option>Complaint</option>
                <option>Suggestion</option>
                <option>Appointment Concern</option>
              </select>

              <div class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-black">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M6 9l6 6 6-6"/>
                </svg>
              </div>
            </div>

          </div>


          <div class="flex flex-col">

            <textarea
            name="message"
            rows="9"
            placeholder="Write your message here..."
            class="w-full flex-1 rounded-2xl px-4 py-3 text-black placeholder-black"
            style="background-color: white; color: black;"
            required></textarea>


            <div class="mt-5 flex gap-3">
              <button
                class="flex-1 rounded-xl py-3 font-semibold text-gray-900 hover:opacity-95 transition"
                style="background:var(--accent)">
                Clear
              </button>

              <button
                type="reset"
                class="flex-1 rounded-xl py-3 font-semibold text-white hover:bg-gray-900 transition"
                style="background-color: var(--primary);">
                Send
              </button>
              
            </div>

          </div>

        </form>
      </div>
    </div>
  </section>


  <section class="py-16 px-4 text-white" style="background:var(--primary)">
    <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">

      <iframe
        src="https://maps.google.com/maps?q=Angeles%20City&t=&z=13&ie=UTF8&iwloc=&output=embed"
        class="w-full rounded-2xl"
        height="220"></iframe>

      <div>
        <h5 class="text-lg font-semibold mb-3">AKAS</h5>
        <p>Email: info@akas.com</p>
        <p>Phone: +63 900 000 0000</p>
        <p class="text-sm mt-3">Mon–Fri (9AM–5PM)</p>
      </div>

      <div>
        <h5 class="text-lg font-semibold mb-3">Follow Us</h5>
        <div class="flex gap-3 text-2xl">
          <i class="bi bi-facebook hover:text-orange-300"></i>
          <i class="bi bi-instagram hover:text-orange-300"></i>
          <i class="bi bi-linkedin hover:text-orange-300"></i>
          <i class="bi bi-messenger hover:text-orange-300"></i>
        </div>
      </div>

    </div>
  </section>

</section>
