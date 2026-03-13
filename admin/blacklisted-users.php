<?php
declare(strict_types=1);

$baseUrl = '';
require_once __DIR__ . '/../includes/auth.php';

if (!auth_is_logged_in() || auth_role() !== 'clinic_admin') {
  header('Location: ' . $baseUrl . '/pages/login.php');
  exit;
}

include __DIR__ . '/../includes/partials/head.php';
?>
<body
  class="bg-slate-50 min-h-screen flex flex-col"
  data-base-url="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES); ?>"
>
<?php include __DIR__ . '/../includes/partials/navbar.php'; ?>

<main class="flex-1">
<section class="max-w-7xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Blacklisted Users</h1>
      <p class="text-sm text-slate-600 mt-1">Manage patients who are blocked from booking appointments.</p>
    </div>

    <a
      href="<?php echo $baseUrl; ?>/admin/dashboard.php"
      class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold hover:bg-slate-50 text-white"
      style="background: var(--primary);"
    >
      Back to Dashboard
    </a>
  </div>

  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" >
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3" style="background: var(--primary);">
      <div class="font-bold text-slate-900" >Clinic Blacklist</div>
      <button
          id="reloadBlacklistBtn"
          type="button"
          class="px-4 py-2 rounded-xl text-white text-sm font-bold hover:opacity-90"
          style="background: var(--primary);"
        >
          Refresh
        </button>
    </div>

    <div id="blacklistMsg" class="hidden px-5 py-3 text-sm"></div>

    <div class="overflow-x-auto">
      <table class="w-full table-fixed text-sm border-collapse">
        <thead class="bg-slate-50">
          <tr class="text-left text-slate-600">
            <th class="px-5 py-3 font-bold">Name</th>
            <th class="px-5 py-3 font-bold">Email</th>
            <th class="px-5 py-3 font-bold">Phone</th>
            <th class="px-5 py-3 font-bold">Cancel Count</th>
            <th class="px-5 py-3 font-bold">Reason</th>
            <th class="px-5 py-3 font-bold">Blacklisted At</th>
            <th class="px-5 py-3 font-bold">Action</th>
          </tr>
        </thead>
        <tbody id="blacklistTableBody">
          <tr>
            <td colspan="7" class="px-5 py-6 text-slate-500">Loading...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<script>
(function () {
  const BASE_URL = document.body?.dataset?.baseUrl || "";
  const tableBody = document.getElementById("blacklistTableBody");
  const reloadBtn = document.getElementById("reloadBlacklistBtn");
  const msg = document.getElementById("blacklistMsg");

  function h(s) {
    return String(s ?? "").replace(/[&<>"']/g, (m) => ({
      "&":"&amp;",
      "<":"&lt;",
      ">":"&gt;",
      '"':"&quot;",
      "'":"&#039;"
    }[m]));
  }

  async function safeJson(res) {
    const txt = await res.text();
    try {
      return JSON.parse(txt);
    } catch {
      throw new Error("Server returned HTML instead of JSON.");
    }
  }

  function showMsg(text, type = "ok") {
    if (!msg) return;
    msg.classList.remove("hidden", "text-emerald-700", "bg-emerald-50", "border-emerald-200", "text-rose-700", "bg-rose-50", "border-rose-200");
    msg.classList.add("border");
    msg.textContent = text;

    if (type === "error") {
      msg.classList.add("text-rose-700", "bg-rose-50", "border-rose-200");
    } else {
      msg.classList.add("text-emerald-700", "bg-emerald-50", "border-emerald-200");
    }
  }

  async function loadBlacklistedUsers() {
    tableBody.innerHTML = `
      <tr>
        <td colspan="7" class="px-5 py-6 text-slate-500">Loading...</td>
      </tr>
    `;

    try {
      const res = await fetch(`${BASE_URL}/api/admin_blacklisted_users.php`, {
        credentials: "same-origin",
        cache: "no-store"
      });

      const data = await safeJson(res);
      if (!res.ok || !data.ok) throw new Error(data.message || "Failed to load blacklisted users");

      const items = Array.isArray(data.items) ? data.items : [];

      if (items.length === 0) {
        tableBody.innerHTML = `
          <tr>
            <td colspan="7" class="px-5 py-6 text-slate-500">No blacklisted users found.</td>
          </tr>
        `;
        return;
      }

      tableBody.innerHTML = items.map(item => `
          <tr class="border-t border-slate-200">
            <td class="px-5 py-4 font-semibold text-slate-900">${h(item.name)}</td>
            <td class="px-5 py-4 text-slate-700">${h(item.email)}</td>
            <td class="px-5 py-4 text-slate-700">${h(item.phone)}</td>
            <td class="px-5 py-4 text-slate-700">${h(item.cancel_count)}</td>
            <td class="px-5 py-4 text-slate-700">${h(item.blacklist_reason || "—")}</td>
            <td class="px-5 py-4 text-slate-700">${h(item.blacklisted_at || "—")}</td>
            <td class="px-5 py-4">
              <button
                type="button"
                data-user-id="${h(item.id)}"
                class="unblacklistBtn px-3 py-2 rounded-xl text-white text-xs font-bold hover:opacity-90"
                style="background:#22c55e;"
              >
                Unblacklist
              </button>
            </td>
          </tr>
        `).join("");

      tableBody.querySelectorAll(".unblacklistBtn").forEach(btn => {
        btn.addEventListener("click", async () => {
          const userId = parseInt(btn.getAttribute("data-user-id") || "0", 10);
          if (!userId) return;

          if (!confirm("Unblacklist this user?")) return;

          btn.disabled = true;

          try {
            const res = await fetch(`${BASE_URL}/api/admin_blacklist_user.php`, {
              method: "POST",
              credentials: "same-origin",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                user_id: userId,
                mode: "UNBLACKLIST"
              })
            });

            const data = await safeJson(res);
            if (!res.ok || !data.ok) throw new Error(data.message || "Unblacklist failed");

            showMsg("User has been unblacklisted.");
            await loadBlacklistedUsers();

          } catch (e) {
            showMsg(e.message || "Unblacklist failed", "error");
            btn.disabled = false;
          }
        });
      });

    } catch (e) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="7" class="px-5 py-6 text-rose-600">${h(e.message || "Failed to load blacklisted users.")}</td>
        </tr>
      `;
    }
  }

  reloadBtn?.addEventListener("click", loadBlacklistedUsers);

  loadBlacklistedUsers();
})();
</script>
</main>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>