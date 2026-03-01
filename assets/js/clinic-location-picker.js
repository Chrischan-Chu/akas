/* assets/js/clinic-location-picker.js
 * Clinic Details pinning behavior:
 * - Address optional
 * - If Address is empty: lock map + disable "Use My Current Location"
 * - If Address is filled: enable map; pin required on submit
 * - Keeps pin within Angeles City bounds (rough bbox)
 */
(function () {
  const addressEl = document.getElementById('akasClinicAddress');
  const mapEl = document.getElementById('akasClinicMap');
  const overlayEl = document.getElementById('akasClinicMapOverlay');
  const errorEl = document.getElementById('akasClinicMapError');
  const useBtn = document.getElementById('akasUseMyLocationBtn');
  const latEl = document.getElementById('akasClinicLat');
  const lngEl = document.getElementById('akasClinicLng');

  if (!addressEl || !mapEl || !overlayEl || !useBtn || !latEl || !lngEl) return;

  // Find the nearest form (Clinic Details form)
  const formEl = addressEl.closest('form');
  if (!formEl) return;

  // If Leaflet isn't present, show an error and stop.
  if (!window.L) {
    overlayEl.classList.remove('hidden');
    overlayEl.textContent = 'Map library failed to load. Please refresh the page.';
    return;
  }

  const ANGELES_BOUNDS = {
    minLat: 15.06, maxLat: 15.22,
    minLng: 120.50, maxLng: 120.70,
  };

  const maxBounds = L.latLngBounds(
    L.latLng(ANGELES_BOUNDS.minLat, ANGELES_BOUNDS.minLng),
    L.latLng(ANGELES_BOUNDS.maxLat, ANGELES_BOUNDS.maxLng)
  );

  function isInsideBounds(lat, lng) {
    return (
      Number.isFinite(lat) &&
      Number.isFinite(lng) &&
      lat >= ANGELES_BOUNDS.minLat && lat <= ANGELES_BOUNDS.maxLat &&
      lng >= ANGELES_BOUNDS.minLng && lng <= ANGELES_BOUNDS.maxLng
    );
  }

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.classList.remove('hidden');
  }

  function clearError() {
    errorEl.textContent = '';
    errorEl.classList.add('hidden');
  }

  // Initialize map
  const defaultCenter = [15.1450, 120.5936]; // Angeles City center-ish
  const map = L.map(mapEl, { zoomControl: true, attributionControl: true })
    .setView(defaultCenter, 13);

  map.setMaxBounds(maxBounds);
  map.on('drag', function () { map.panInsideBounds(maxBounds, { animate: false }); });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  let marker = null;
  let accuracyCircle = null;

  function setMarker(lat, lng, { open = false } = {}) {
    if (!isInsideBounds(lat, lng)) {
      showError('Pinned location must be within Angeles City, Pampanga.');
      return false;
    }

    clearError();

    // Remove old items
    if (marker) map.removeLayer(marker);
    if (accuracyCircle) map.removeLayer(accuracyCircle);

    marker = L.marker([lat, lng], { draggable: true }).addTo(map);

    marker.on('dragend', function () {
      const pos = marker.getLatLng();
      if (!isInsideBounds(pos.lat, pos.lng)) {
        // snap back inside bounds
        const snappedLat = Math.min(Math.max(pos.lat, ANGELES_BOUNDS.minLat), ANGELES_BOUNDS.maxLat);
        const snappedLng = Math.min(Math.max(pos.lng, ANGELES_BOUNDS.minLng), ANGELES_BOUNDS.maxLng);
        marker.setLatLng([snappedLat, snappedLng]);
        latEl.value = String(snappedLat);
        lngEl.value = String(snappedLng);
        showError('Pinned location must be within Angeles City, Pampanga.');
        return;
      }
      latEl.value = String(pos.lat);
      lngEl.value = String(pos.lng);
      clearError();
    });

    latEl.value = String(lat);
    lngEl.value = String(lng);

    map.setView([lat, lng], Math.max(map.getZoom(), 16), { animate: true });
    if (open && marker.openPopup) marker.openPopup();
    return true;
  }

  function lockMap(locked) {
    if (locked) {
      overlayEl.classList.remove('hidden');
      overlayEl.style.display = 'flex';
      useBtn.disabled = true;

      map.dragging.disable();
      map.scrollWheelZoom.disable();
      map.doubleClickZoom.disable();
      map.boxZoom.disable();
      map.keyboard.disable();
      if (map.tap) map.tap.disable();
      map.touchZoom.disable();
    } else {
      overlayEl.classList.add('hidden');
      overlayEl.style.display = 'none';
      useBtn.disabled = false;

      map.dragging.enable();
      map.scrollWheelZoom.enable();
      map.doubleClickZoom.enable();
      map.boxZoom.enable();
      map.keyboard.enable();
      if (map.tap) map.tap.enable();
      map.touchZoom.enable();
    }
  }

  function clearPin() {
    if (marker) map.removeLayer(marker);
    if (accuracyCircle) map.removeLayer(accuracyCircle);
    marker = null;
    accuracyCircle = null;
    latEl.value = '';
    lngEl.value = '';
  }

  // Click-to-pin
  map.on('click', function (e) {
    if (addressEl.value.trim() === '') return; // locked
    setMarker(e.latlng.lat, e.latlng.lng);
  });

  // Enable/disable map based on address
  function syncLockState() {
    const hasAddress = addressEl.value.trim() !== '';
    if (!hasAddress) {
      clearError();
      clearPin();
      lockMap(true);
      // Reset view
      map.setView(defaultCenter, 13, { animate: false });
    } else {
      lockMap(false);
    }
  }

  addressEl.addEventListener('input', function () {
    syncLockState();
  });

  // Use My Current Location
  useBtn.addEventListener('click', function () {
    if (useBtn.disabled) return;

    if (!navigator.geolocation) {
      showError('Geolocation is not supported by this browser.');
      return;
    }

    clearError();
    const originalText = useBtn.textContent;
    useBtn.textContent = 'Detecting…';
    useBtn.disabled = true;

    navigator.geolocation.getCurrentPosition(
      function (pos) {
        const lat = Number(pos.coords.latitude);
        const lng = Number(pos.coords.longitude);
        const accuracy = Number(pos.coords.accuracy);

        // Place marker even if slightly outside; but enforce bounds and show msg
        if (!setMarker(lat, lng)) {
          // If outside, still restore button
          useBtn.textContent = originalText;
          useBtn.disabled = false;
          return;
        }

        // Accuracy circle (best effort)
        if (Number.isFinite(accuracy) && accuracy > 0) {
          accuracyCircle = L.circle([lat, lng], { radius: accuracy }).addTo(map);
        }

        useBtn.textContent = originalText;
        useBtn.disabled = false;
      },
      function (err) {
        let msg = 'Unable to retrieve your location.';
        if (err && err.code === 1) msg = 'Location permission denied. Please allow location access and try again.';
        if (err && err.code === 2) msg = 'Location unavailable. Please try again or pin manually on the map.';
        if (err && err.code === 3) msg = 'Location request timed out. Please try again.';
        showError(msg);

        useBtn.textContent = originalText;
        useBtn.disabled = false;
      },
      {
        enableHighAccuracy: true,
        timeout: 12000,
        maximumAge: 0
      }
    );
  });

  // Submit validation: if address is provided, pin is required.
  formEl.addEventListener('submit', function (ev) {
    const hasAddress = addressEl.value.trim() !== '';
    const lat = Number(latEl.value);
    const lng = Number(lngEl.value);

    if (hasAddress && (!Number.isFinite(lat) || !Number.isFinite(lng))) {
      ev.preventDefault();
      showError('Please pin the clinic location on the map before saving.');
      // Scroll into view
      mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return false;
    }
    return true;
  });

  // Init: if we already have stored lat/lng and address, show pin and unlock.
  (function init() {
    const addr = addressEl.value.trim();
    const lat = Number(latEl.value);
    const lng = Number(lngEl.value);

    if (addr !== '') {
      lockMap(false);
      if (Number.isFinite(lat) && Number.isFinite(lng) && isInsideBounds(lat, lng)) {
        setMarker(lat, lng);
      } else {
        // no pin yet -> stay unlocked but empty
        clearPin();
      }
    } else {
      lockMap(true);
      clearPin();
    }
  })();
})();