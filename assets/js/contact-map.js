/* assets/js/contact-map.js
   - Custom dropdown ALWAYS shows (ported to <body> so it can't be clipped)
   - Map centers selected clinic pin reliably
   - Selecting the SAME clinic again ALWAYS re-centers (even after drag/zoom)
   - UI shows the selected clinic name on the custom dropdown button
   - Hidden <select> stays empty behind-the-scenes
*/

(function () {
  function onReady(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  // Sometimes this script can load before the inline payload is defined.
  // Wait briefly for window.AKAS_CLINICS_MAP to appear.
  function waitForPayload(maxWaitMs = 2000) {
    return new Promise((resolve) => {
      const start = Date.now();
      (function tick() {
        if (window.AKAS_CLINICS_MAP && Array.isArray(window.AKAS_CLINICS_MAP.clinics)) {
          resolve(window.AKAS_CLINICS_MAP);
          return;
        }
        if (Date.now() - start >= maxWaitMs) {
          resolve(null);
          return;
        }
        setTimeout(tick, 50);
      })();
    });
  }

  onReady(async () => {
    const mapEl = document.getElementById('akasClinicMap');

    // Hidden legacy select (kept for compatibility)
    const selectEl = document.getElementById('akasClinicSelect');

    // Custom dropdown UI
    const btn = document.getElementById('akasClinicSelectBtn');
    const btnText = document.getElementById('akasClinicSelectBtnText');
    const menu = document.getElementById('akasClinicSelectMenu');

    // If the section isn't on this page, do nothing.
    if (!btn || !btnText || !menu) return;

    // =========================
    // Dropdown: PORTAL TO BODY
    // =========================
    // Save original place so we can restore it when closing.
    const menuHome = menu.parentElement;
    const menuNextSibling = menu.nextSibling;

    function positionMenuFixed() {
      const r = btn.getBoundingClientRect();
      menu.style.position = 'fixed';
      menu.style.left = r.left + 'px';
      menu.style.top = (r.bottom + 8) + 'px';
      menu.style.width = r.width + 'px';
      menu.style.zIndex = '99999';
    }

    function openMenu() {
      // move menu to <body> so it can't be clipped by any parent
      if (menu.parentElement !== document.body) {
        document.body.appendChild(menu);
      }

      positionMenuFixed();
      menu.classList.remove('hidden');
      btn.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
      menu.classList.add('hidden');
      btn.setAttribute('aria-expanded', 'false');

      // restore to original DOM location
      if (menu.parentElement === document.body && menuHome) {
        if (menuNextSibling) menuHome.insertBefore(menu, menuNextSibling);
        else menuHome.appendChild(menu);
      }

      // clear inline styles
      menu.style.position = '';
      menu.style.left = '';
      menu.style.top = '';
      menu.style.width = '';
      menu.style.zIndex = '';
    }

    function toggleMenu() {
      if (menu.classList.contains('hidden')) openMenu();
      else closeMenu();
    }

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleMenu();
    });

    // Click outside closes
    document.addEventListener('click', (e) => {
      if (!menu.contains(e.target) && !btn.contains(e.target)) closeMenu();
    });

    // Esc closes
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeMenu();
    });

    // Keep aligned while open
    window.addEventListener(
      'scroll',
      () => {
        if (!menu.classList.contains('hidden')) positionMenuFixed();
      },
      true
    );
    window.addEventListener('resize', () => {
      if (!menu.classList.contains('hidden')) positionMenuFixed();
    });

    // Payload (clinics list + bounds + center)
    const data = await waitForPayload();
    if (!data) {
      // Dropdown still opens (items are rendered by PHP).
      // Selecting just updates UI text.
      menu.addEventListener('click', (e) => {
        const item = e.target.closest('button[data-id]');
        if (!item) return;

        const id = item.getAttribute('data-id') || '';
        closeMenu();

        const name = item.getAttribute('data-name') || item.textContent || 'Clinic';
        btnText.textContent = name.trim();
        if (selectEl) {
          selectEl.value = '';
          try { selectEl.selectedIndex = 0; } catch (_) {}
        }
      });
      return;
    }

    // Build a quick lookup so we can show names even if something else fails
    const clinicById = new Map();
    data.clinics.forEach((c) => clinicById.set(String(c.id), c));

    // --- Map init (optional) ---
    const leafletReady = !!window.L && !!mapEl;
    let map = null;
    let markersById = null;
    let pendingById = null;
    let angBounds = null;

    function invalidateSoon() {
      if (!map) return;
      try { map.invalidateSize(true); } catch (_) {}
    }

    function escapeHtml(s) {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function popupHtml(c) {
      const name = escapeHtml(c.name || 'Clinic');
      const addr = escapeHtml(c.address || '');
      return `<div style="min-width:180px">
        <div style="font-weight:700;margin-bottom:4px">${name}</div>
        <div style="font-size:12px;opacity:.85;white-space:pre-wrap">${addr}</div>
      </div>`;
    }

    function insideAngeles(lat, lng) {
      if (!angBounds) return true;
      return angBounds.contains(L.latLng(lat, lng));
    }

    function ensureMarker(c) {
      if (!map || !markersById) return null;

      const id = String(c.id);
      const existing = markersById.get(id);
      if (existing) return existing;

      const lat = Number(c.lat);
      const lng = Number(c.lng);
      if (!isFinite(lat) || !isFinite(lng)) return null;
      if (!insideAngeles(lat, lng)) return null;

      const m = L.marker([lat, lng]).addTo(map).bindPopup(popupHtml(c), { autoPan: false });
      markersById.set(id, m);
      return m;
    }

    function centerOnMarker(marker) {
      if (!map) return;
      invalidateSoon();
      map.stop();
      map.closePopup();

      const ll = marker.getLatLng();
      const targetZoom = 16;

      map.setView(ll, targetZoom, { animate: false });
      setTimeout(() => {
        try { marker.openPopup(); } catch (_) {}
      }, 80);
    }

    async function geocodeClinicIfNeeded(clinic) {
      if (!map || !pendingById) return null;

      const id = String(clinic.id);
      if (pendingById.get(id)) return null;
      pendingById.set(id, true);

      try {
        if (!clinic.address) return null;

        // Cached in localStorage (best effort)
        try {
          const cached = localStorage.getItem(`akas_geo_${clinic.id}`);
          if (cached) {
            const j = JSON.parse(cached);
            const latC = Number(j?.lat);
            const lngC = Number(j?.lng);
            if (isFinite(latC) && isFinite(lngC) && insideAngeles(latC, lngC)) {
              clinic.lat = latC;
              clinic.lng = lngC;
              return ensureMarker(clinic);
            }
          }
        } catch (_) {}

        const q = `${clinic.address}, Angeles City, Pampanga, Philippines`;
        const params = new URLSearchParams({
          q,
          format: 'json',
          limit: '1',
          countrycodes: 'ph',
        });

        // Bound results to Angeles box (if provided)
        const bounds = data.bounds || null;
        if (bounds?.sw && bounds?.ne) {
          const viewbox = `${bounds.sw.lng},${bounds.sw.lat},${bounds.ne.lng},${bounds.ne.lat}`;
          params.set('viewbox', viewbox);
          params.set('bounded', '1');
        }

        const url = 'https://nominatim.openstreetmap.org/search?' + params.toString();
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) return null;

        const json = await res.json();
        if (!Array.isArray(json) || !json[0]) return null;

        const lat = Number(json[0].lat);
        const lng = Number(json[0].lon);
        if (!isFinite(lat) || !isFinite(lng)) return null;
        if (!insideAngeles(lat, lng)) return null;

        clinic.lat = lat;
        clinic.lng = lng;

        try { localStorage.setItem(`akas_geo_${clinic.id}`, JSON.stringify({ lat, lng })); } catch (_) {}
        return ensureMarker(clinic);
      } finally {
        pendingById.set(id, false);
      }
    }

    if (leafletReady) {
      // Angeles bounds used ONLY for filtering & geocode bounding
      const bounds = data.bounds || null;
      const sw = bounds?.sw ? [Number(bounds.sw.lat), Number(bounds.sw.lng)] : null;
      const ne = bounds?.ne ? [Number(bounds.ne.lat), Number(bounds.ne.lng)] : null;

      angBounds =
        (sw && ne && isFinite(sw[0]) && isFinite(sw[1]) && isFinite(ne[0]) && isFinite(ne[1]))
          ? L.latLngBounds(L.latLng(sw[0], sw[1]), L.latLng(ne[0], ne[1]))
          : null;

      // Map init
      const defaultCenter = data.defaultCenter || { lat: 15.145, lng: 120.5936 };
      const defaultZoom = data.defaultZoom || 13;

      map = L.map(mapEl, {
        zoomControl: true,
        attributionControl: true,
        scrollWheelZoom: false,
      }).setView([defaultCenter.lat, defaultCenter.lng], defaultZoom);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
      }).addTo(map);

      markersById = new Map();
      pendingById = new Map();

      setTimeout(invalidateSoon, 50);
      setTimeout(invalidateSoon, 250);
      window.addEventListener('resize', () => setTimeout(invalidateSoon, 100));
      window.addEventListener('orientationchange', () => setTimeout(invalidateSoon, 100));

      // Build markers from server-provided coords
      data.clinics.forEach((c) => {
        if (c.lat != null && c.lng != null) {
          const lat = Number(c.lat);
          const lng = Number(c.lng);
          if (isFinite(lat) && isFinite(lng)) {
            c.lat = lat;
            c.lng = lng;
            ensureMarker(c);
          }
        }
      });

      // Initial view
      const allMarkers = Array.from(markersById.values());
      if (allMarkers.length > 0) {
        const group = L.featureGroup(allMarkers);
        map.fitBounds(group.getBounds().pad(0.2));
      } else if (angBounds) {
        map.fitBounds(angBounds.pad(0.05));
      }
    }

    async function pickClinicById(id) {
      const clinic = clinicById.get(String(id));
      if (!clinic) return;

      // UI: show the chosen clinic name on the button
      btnText.textContent = clinic.name || 'Clinic';

      // Behind the scenes: keep the real select EMPTY
      if (selectEl) {
        selectEl.value = '';
        try { selectEl.selectedIndex = 0; } catch (_) {}
      }

      // If map isn't ready, just stop after updating UI.
      if (!map || !leafletReady) return;

      // Always center, even if selecting same clinic again
      let marker = (markersById && markersById.get(String(clinic.id))) || ensureMarker(clinic);
      if (marker) {
        centerOnMarker(marker);
        return;
      }

      marker = await geocodeClinicIfNeeded(clinic);
      if (marker) {
        centerOnMarker(marker);
        return;
      }

      alert(
        'This clinic does not have a valid pinned location yet. Please ask the clinic to set a pin in Clinic Details.'
      );
    }

    menu.addEventListener('click', async (e) => {
      const item = e.target.closest('button[data-id]');
      if (!item) return;

      const id = item.getAttribute('data-id') || '';
      closeMenu();


      await pickClinicById(id);
    });
  });
})();