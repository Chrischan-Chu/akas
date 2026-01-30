
<?php
$clinics = [
  ["id" => 1, "name" => "AKAS Health Clinic – Angeles City"],
  ["id" => 2, "name" => "St. Maria Clinic – San Fernando"],
  ["id" => 3, "name" => "CityCare Clinic – Mabalacat"],
];
$success = isset($_GET['sent']);
?>


<section id="contact" class="scroll-mt-24">
  <section class="py-6 text-center" style="background:var(--secondary)">
    <div class="max-w-6xl mx-auto px-4">
      <h1 class="text-3xl tracking-widest font-light text-black/80">CONTACT</h1>
      <p class="mt-2 text-black/70">
        Send feedback to a clinic or reach out to the AKAS developers.
      </p>
    </div>
  </section>
  <section class="py-10 px-4">
    <div class="max-w-6xl mx-auto">
      <?php if($success): ?>
        <div class="mb-6 rounded-2xl bg-white p-4 border border-green-200">
          <p class="text-green-700 font-semibold">
            <i class="bi bi-check-circle-fill"></i> Message sent successfully (mock).
          </p>
          <p class="text-sm text-gray-600 mt-1">
            Replace this with your real submit handler later.
          </p>
        </div>
      <?php endif; ?>
      <div class="relative">
        <div class="bg-white rounded-2xl shadow-sm p-6 md:p-8">
          <form id="panelClinic" class="grid grid-cols-1 lg:grid-cols-2 gap-6" method="POST" action="#">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Choose Clinic</label>
                <select name="clinic_id"
                        class="w-full rounded-xl px-4 py-3 text-white"
                        style="background:var(--primary)">
                  <?php foreach($clinics as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-2">
                  Your message will be sent to the selected clinic.
                </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
              <input type="text" name="name"
                     class="w-full rounded-xl px-4 py-3 text-white placeholder-white/70"
                     style="background:var(--primary)" placeholder="Juan Dela Cruz" required>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input type="email" name="email"
                     class="w-full rounded-xl px-4 py-3 text-white placeholder-white/70"
                     style="background:var(--primary)" placeholder="name@email.com" required>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
              <input type="text" name="subject"
                     class="w-full rounded-xl px-4 py-3 text-white placeholder-white/70"
                     style="background:var(--primary)" placeholder="Feedback / Complaint / Suggestion" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category"
                        class="w-full rounded-xl px-4 py-3 text-white"
                        style="background:var(--primary)">
                  <option>Feedback</option>
                  <option>Complaint</option>
                  <option>Suggestion</option>
                  <option>Appointment Concern</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                <select name="priority"
                        class="w-full rounded-xl px-4 py-3 text-white"
                        style="background:var(--primary)">
                  <option>Normal</option>
                  <option>Urgent</option>
                </select>
              </div>
            </div>
          </div>

          <div class="flex flex-col">
            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
            <textarea name="message" rows="10"
                      class="w-full flex-1 rounded-2xl px-4 py-3 text-white placeholder-white/70"
                      style="background:var(--primary)"
                      placeholder="Write your message to the clinic here..." required></textarea>

            <div class="mt-4 flex flex-col sm:flex-row gap-3">
              <button type="submit"
                      class="rounded-xl py-3 px-6 font-semibold text-gray-800 hover:opacity-95 transition"
                      style="background:var(--accent)">
                Send to Clinic
              </button>

              <button type="reset"
                      class="rounded-xl py-3 px-6 font-semibold text-white bg-gray-800 hover:bg-gray-900 transition">
                Clear
              </button>
            </div>

            <p class="mt-3 text-xs text-gray-500">
              Note: This is UI only for now. Connect it to your backend/API later.
            </p>
          </div>
        </form>

        <!-- DEVELOPER FORM
        <form id="panelDev" class="hidden grid grid-cols-1 lg:grid-cols-2 gap-6" method="POST" action="#">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
              <input type="text" name="dev_name"
                     class="w-full rounded-xl px-4 py-3 text-white placeholder-white/70"
                     style="background:var(--primary)" placeholder="Your full name" required>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input type="email" name="dev_email"
                     class="w-full rounded-xl px-4 py-3 text-white placeholder-white/70"
                     style="background:var(--primary)" placeholder="name@email.com" required>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Topic</label>
              <select name="dev_topic"
                      class="w-full rounded-xl px-4 py-3 text-white"
                      style="background:var(--primary)">
                <option>Bug Report</option>
                <option>Feature Request</option>
                <option>Account/Login Issue</option>
                <option>Other</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Reference (optional)</label>
              <input type="text" name="dev_reference"
                     class="w-full rounded-xl px-4 py-3 text-white placeholder-white/70"
                     style="background:var(--primary)"
                     placeholder="e.g., Appointment ID / Clinic Name / Screenshot filename">
            </div>
          </div>

          <div class="flex flex-col">
            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
            <textarea name="dev_message" rows="10"
                      class="w-full flex-1 rounded-2xl px-4 py-3 text-white placeholder-white/70"
                      style="background:var(--primary)"
                      placeholder="Tell us what happened, and how we can help..." required></textarea>

            <div class="mt-4 flex flex-col sm:flex-row gap-3">
              <button type="submit"
                      class="rounded-xl py-3 px-6 font-semibold text-gray-800 hover:opacity-95 transition"
                      style="background:var(--accent)">
                Send to Developers
              </button>

              <button type="reset"
                      class="rounded-xl py-3 px-6 font-semibold text-white bg-gray-800 hover:bg-gray-900 transition">
                Clear
              </button>
            </div>

            <p class="mt-3 text-xs text-gray-500">
              Tip: For bugs, include steps to reproduce + device/browser if possible.
            </p>
          </div>
        </form> -->

      </div>
    </div>

  </div>
</section>

<!-- FOOTER CONTACT SECTION (like your other pages) -->
<section class="py-16 px-4 text-white" style="background:var(--primary)">
  <div class="max-w-6xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div>
        <iframe
          src="https://maps.google.com/maps?q=Angeles%20City&t=&z=13&ie=UTF8&iwloc=&output=embed"
          class="w-full rounded-2xl"
          height="220"
          style="border:0">
        </iframe>
      </div>

      <div>
        <h5 class="text-lg font-semibold mb-3">AKAS</h5>
        <p class="mb-1">Email: info@akas.com</p>
        <p>Phone: +63 900 000 0000</p>
        <p class="mt-3 text-sm text-white/90">Mon–Fri (9AM–5PM)</p>
      </div>

      <div>
        <h5 class="text-lg font-semibold mb-3">Follow Us</h5>
        <div class="flex gap-3 text-2xl">
          <i class="bi bi-facebook cursor-pointer transition-colors hover:text-orange-300"></i>
          <i class="bi bi-instagram cursor-pointer transition-colors hover:text-orange-300"></i>
          <i class="bi bi-linkedin cursor-pointer transition-colors hover:text-orange-300"></i>
          <i class="bi bi-messenger cursor-pointer transition-colors hover:text-orange-300"></i>
        </div>
      </div>
    </div>
  </div>
</section>


</section>
