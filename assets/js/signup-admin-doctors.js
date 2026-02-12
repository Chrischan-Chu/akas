(function(){
  const modal = document.getElementById('doctorModal');
  const openBtn = document.getElementById('openDoctorModal');
  const closeBtn = document.getElementById('closeDoctorModal');
  const cancelBtn = document.getElementById('cancelDoctor');
  const saveBtn = document.getElementById('saveDoctor');

  const listEl = document.getElementById('doctorsList');
  const jsonEl = document.getElementById('doctorsJson');

  const iName = document.getElementById('docFullName');
  const iBirth = document.getElementById('docBirthdate');
  const iSpec = document.getElementById('docSpecialization');
  const iPrc = document.getElementById('docPrc');
  const iSched = document.getElementById('docSchedule');
  const iEmail = document.getElementById('docEmail');
  const iPhone = document.getElementById('docPhone');

  let doctors = [];
  let editingIndex = -1; // ✅ NEW: -1 = add mode, >=0 = edit mode


  function lockScroll(locked){
    document.documentElement.style.overflow = locked ? 'hidden' : '';
    document.body.style.overflow = locked ? 'hidden' : '';
  }

  function showModal(){
  modal?.classList.add('show');
  lockScroll(true);

  // clear old custom validity
  [iName,iBirth,iSpec,iPrc,iSched,iEmail,iPhone].forEach(el => el && el.setCustomValidity(''));

  // ✅ NEW: change button label depending on mode
  if (saveBtn) saveBtn.textContent = (editingIndex >= 0) ? 'Save Changes' : 'Add Doctor';

  iName?.focus();
}


  function hideModal(){
    modal?.classList.remove('show');
    lockScroll(false);
  }

  function resetModalInputs(){
    if (iName) iName.value = '';
    if (iBirth) iBirth.value = '';
    if (iSpec) iSpec.value = '';
    if (iPrc) iPrc.value = '';
    if (iSched) iSched.value = '';
    if (iEmail) iEmail.value = '';
    if (iPhone) iPhone.value = '';
  }

  function fillModalInputs(d){
  if (!d) return;
  if (iName)  iName.value  = d.full_name || '';
  if (iBirth) iBirth.value = d.birthdate || '';
  if (iSpec)  iSpec.value  = d.specialization || '';
  if (iPrc)   iPrc.value   = d.prc || '';
  if (iSched) iSched.value = d.schedule || '';
  if (iEmail) iEmail.value = d.email || '';
  if (iPhone) iPhone.value = d.contact_number || '';
}


  function setError(el, msg){
    if (!el) return;
    el.setCustomValidity(msg);
    el.reportValidity();
  }

  function validateDoctor(){
    const name = (iName?.value || '').trim();
    const birth = (iBirth?.value || '').trim();
    const spec = (iSpec?.value || '').trim();
    const prc = (iPrc?.value || '').trim();
    const sched = (iSched?.value || '').trim();
    const email = (iEmail?.value || '').trim().toLowerCase();
    const phone = (iPhone?.value || '').trim();

    // Clear previous custom errors
    [iName,iBirth,iSpec,iPrc,iSched,iEmail,iPhone].forEach(el => el && el.setCustomValidity(''));

    if (name === '') return setError(iName, 'Full name is required.'), null;
    if (name.length > 190) return setError(iName, 'Full name is too long.'), null;
    if (!/^[A-Za-z\s.'-]+$/.test(name)) return setError(iName, 'Full name contains invalid characters.'), null;

    if (birth === '') return setError(iBirth, 'Birthdate is required.'), null;
    const d = new Date(birth + 'T00:00:00');
    if (Number.isNaN(d.getTime())) return setError(iBirth, 'Enter a valid birthdate.'), null;
    const today = new Date();
    today.setHours(0,0,0,0);
    if (d > today) return setError(iBirth, 'Birthdate cannot be in the future.'), null;
    // basic age check (18+)
    const age = today.getFullYear() - d.getFullYear() - ((today.getMonth() < d.getMonth() || (today.getMonth() === d.getMonth() && today.getDate() < d.getDate())) ? 1 : 0);
    if (age < 18) return setError(iBirth, 'Doctor must be at least 18 years old.'), null;

    if (spec === '') return setError(iSpec, 'Specialization is required.'), null;
    if (spec.length > 120) return setError(iSpec, 'Specialization is too long.'), null;
    if (!/^[A-Za-z0-9\s.,'()-]+$/.test(spec)) return setError(iSpec, 'Specialization contains invalid characters.'), null;

    if (prc === '') return setError(iPrc, 'PRC is required.'), null;
    if (prc.length > 50) return setError(iPrc, 'PRC is too long.'), null;
    if (!/^[A-Za-z0-9\-]+$/.test(prc)) return setError(iPrc, 'PRC contains invalid characters.'), null;

    if (email === '') return setError(iEmail, 'Email is required.'), null;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return setError(iEmail, 'Enter a valid email.'), null;
    if (email.length > 190) return setError(iEmail, 'Email is too long.'), null;

    const digits = phone.replace(/\D+/g,'');
    if (!/^9\d{9}$/.test(digits)) return setError(iPhone, 'Enter a valid PH mobile number (ex: 9123456789).'), null;

    if (sched === '') return setError(iSched, 'Schedule is required.'), null;
    if (sched.length > 300) return setError(iSched, 'Schedule is too long (max 300 characters).'), null;

    return { full_name: name, birthdate: birth, specialization: spec, prc, schedule: sched, email, contact_number: digits };
  }

  function syncHidden(){
    if (jsonEl) jsonEl.value = JSON.stringify(doctors);
  }

  function renderList(){
  if (!listEl) return;
  if (!doctors.length) {
    listEl.innerHTML = '<p class="text-sm text-slate-600">No doctors added yet.</p>';
    return;
  }

  listEl.innerHTML = doctors.map((d, idx) => {
    const safe = (s) => String(s).replace(/[&<>"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    return `
      <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 flex items-start justify-between gap-3">
        <div>
          <p class="font-bold text-slate-800">${safe(d.full_name)}</p>
          <p class="text-xs text-slate-600">${safe(d.specialization)} • PRC: ${safe(d.prc)}</p>
          <p class="text-xs text-slate-600">${safe(d.schedule)}</p>
        </div>

        <div class="shrink-0 flex items-center gap-2">
          <button type="button"
                  data-edit="${idx}"
                  class="px-3 py-1.5 rounded-lg text-sm font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50">
            Edit
          </button>

          <button type="button"
                  data-remove="${idx}"
                  class="px-3 py-1.5 rounded-lg text-sm font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50">
            Remove
          </button>
        </div>
      </div>
    `;
  }).join('');
}


  openBtn?.addEventListener('click', () => {
  editingIndex = -1;          // ✅ NEW: add mode
  resetModalInputs();
  showModal();
});

  closeBtn?.addEventListener('click', hideModal);
  cancelBtn?.addEventListener('click', hideModal);

  // prevent closing by clicking backdrop (close only via buttons)
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) {
      e.stopPropagation();
    }
  });

saveBtn?.addEventListener('click', () => {
  const doc = validateDoctor();
  if (!doc) return;

  if (editingIndex >= 0 && editingIndex < doctors.length) {
    doctors[editingIndex] = doc;     // ✅ EDIT mode
  } else {
    doctors.push(doc);              // ✅ ADD mode
  }

  editingIndex = -1;
  syncHidden();
  renderList();
  hideModal();
});


listEl?.addEventListener('click', (e) => {
  const removeBtn = e.target?.closest('button[data-remove]');
  const editBtn = e.target?.closest('button[data-edit]');

  if (removeBtn) {
    const idx = parseInt(removeBtn.getAttribute('data-remove') || '-1', 10);
    if (!Number.isFinite(idx) || idx < 0 || idx >= doctors.length) return;

    doctors.splice(idx, 1);

    // ✅ if you deleted the one being edited, exit edit mode
    if (editingIndex === idx) editingIndex = -1;
    if (editingIndex > idx) editingIndex -= 1;

    syncHidden();
    renderList();
    return;
  }

  if (editBtn) {
    const idx = parseInt(editBtn.getAttribute('data-edit') || '-1', 10);
    if (!Number.isFinite(idx) || idx < 0 || idx >= doctors.length) return;

    editingIndex = idx;          // ✅ set edit mode
    resetModalInputs();
    fillModalInputs(doctors[idx]);
    showModal();
    return;
  }
});


  // init
  try {
    doctors = JSON.parse(jsonEl?.value || '[]') || [];
  } catch { doctors = []; }
  syncHidden();
  renderList();
})();