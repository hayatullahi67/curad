{{-- Behaviour for partials.location-picker. Include once per page, after the form(s). --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
    (function () {
        const lookupUrl = @json(route('location.lookup'));
        // Roughly the middle of Nigeria, shown until a position is chosen.
        const START = { lat: 9.082, lng: 8.6753, zoom: 5 };

        document.querySelectorAll('[data-location-picker]').forEach(picker => {
            const form = picker.closest('form');
            const latitude = picker.querySelector('[data-latitude]');
            const longitude = picker.querySelector('[data-longitude]');
            const status = picker.querySelector('[data-location-status]');
            const hint = picker.querySelector('[data-map-hint]');
            const mapBox = picker.querySelector('[data-map]');
            const useButton = picker.querySelector('[data-use-location]');
            const findButton = picker.querySelector('[data-find-address]');
            const googleCheck = picker.querySelector('[data-google-check]');
            const field = name => form.querySelector(`[data-place="${name}"]`);

            let map = null;
            let pin = null;
            let lookupTimer = null;

            function say(message, tone) {
                status.textContent = message;
                status.className = 'mt-0.5 text-xs leading-5 ' + ({ good: 'text-emerald-700', bad: 'text-rose-600', busy: 'text-slate-500' }[tone] || 'text-slate-500');
            }

            function busy(button, label) {
                button.dataset.label = button.dataset.label || button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> ' + label;
            }

            function ready(button) {
                button.disabled = false;
                if (button.dataset.label) button.innerHTML = button.dataset.label;
            }

            // The map is built the first time it is on screen: one inside a
            // closed window has no size yet and would draw blank.
            function build() {
                if (map || typeof L === 'undefined') return;

                const has = latitude.value !== '' && longitude.value !== '';
                const at = has ? [Number(latitude.value), Number(longitude.value)] : [START.lat, START.lng];

                map = L.map(mapBox, { scrollWheelZoom: false, attributionControl: true }).setView(at, has ? 17 : START.zoom);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);

                const icon = L.divIcon({
                    className: '',
                    html: '<span style="display:flex;width:36px;height:36px;align-items:center;justify-content:center;border-radius:9999px 9999px 9999px 0;transform:rotate(-45deg);background:#059669;box-shadow:0 6px 14px rgba(5,150,105,.45);border:3px solid #fff"><i class="fas fa-plus" style="transform:rotate(45deg);color:#fff;font-size:12px"></i></span>',
                    iconSize: [36, 36],
                    iconAnchor: [18, 36],
                });

                pin = L.marker(at, { draggable: true, icon, keyboard: true, title: 'Drag to the right building' });
                if (has) pin.addTo(map);

                pin.on('dragend', () => place(pin.getLatLng().lat, pin.getLatLng().lng, { fillAddress: true, move: false }));
                map.on('click', event => place(event.latlng.lat, event.latlng.lng, { fillAddress: true, move: false }));
            }

            new IntersectionObserver(entries => {
                if (entries.some(entry => entry.isIntersecting)) {
                    build();
                    setTimeout(() => map && map.invalidateSize(), 60);
                }
            }).observe(mapBox);

            // Puts the pin at a position and, if asked, looks up its address.
            function place(lat, lng, options) {
                build();
                latitude.value = Number(lat).toFixed(6);
                longitude.value = Number(lng).toFixed(6);
                hint.classList.add('hidden');

                if (googleCheck) {
                    googleCheck.href = `https://www.google.com/maps/search/?api=1&query=${latitude.value},${longitude.value}`;
                    googleCheck.classList.replace('hidden', 'inline-flex');
                }

                // A phone with GPS is sure to a few metres; a computer can be far off.
                const metres = Math.round(options.accuracy || 0);
                const sure = !metres ? '' : (metres > 100
                    ? ` Your device was only sure to about ${metres} m, so drag the pin onto the right building.`
                    : ` Your device was sure to about ${metres} m.`);

                if (map) {
                    pin.setLatLng([lat, lng]).addTo(map);
                    if (options.move) map.setView([lat, lng], 17);
                }

                if (!options.fillAddress) {
                    say('Pinned. Drag the pin if it is not on the right building.' + sure, 'good');
                    return;
                }

                say('Looking up the address for this spot…', 'busy');
                clearTimeout(lookupTimer);
                lookupTimer = setTimeout(() => {
                    fetch(`${lookupUrl}?lat=${latitude.value}&lng=${longitude.value}`, { headers: { Accept: 'application/json' } })
                        .then(response => response.ok ? response.json() : Promise.reject(response))
                        .then(found => {
                            fill(found);
                            say('Pinned, and the address was filled in for you. Check it and correct anything that is wrong.' + sure, 'good');
                        })
                        .catch(() => say('Pinned. We could not look up the address for this spot, so type it in yourself.' + sure, 'good'));
                }, 350);
            }

            function fill(found) {
                [['address', found.address], ['city', found.city], ['state', found.state]].forEach(([name, value]) => {
                    const input = field(name);
                    if (input && value) {
                        input.value = value;
                        input.classList.add('ring-2', 'ring-emerald-200');
                        setTimeout(() => input.classList.remove('ring-2', 'ring-emerald-200'), 1600);
                    }
                });
            }

            useButton.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    say('This browser cannot share a location. Use “Find the address” or tap the map instead.', 'bad');
                    return;
                }

                busy(useButton, 'Finding you…');
                say('Asking your device where you are…', 'busy');

                navigator.geolocation.getCurrentPosition(position => {
                    ready(useButton);
                    place(position.coords.latitude, position.coords.longitude, { fillAddress: true, move: true, accuracy: position.coords.accuracy });
                }, problem => {
                    ready(useButton);
                    say(problem.code === 1
                        ? 'Location is blocked for this site. Allow it in your browser, or use “Find the address”.'
                        : 'We could not read your location. Try again, or use “Find the address”.', 'bad');
                }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
            });

            findButton.addEventListener('click', () => {
                const typed = ['address', 'city', 'state'].map(name => field(name)?.value.trim()).filter(Boolean).join(', ');

                if (typed.length < 3) {
                    say('Type the street address first, then press “Find the address”.', 'bad');
                    field('address')?.focus();
                    return;
                }

                busy(findButton, 'Searching…');
                say('Looking for that address on the map…', 'busy');

                fetch(`${lookupUrl}?q=${encodeURIComponent(typed)}`, { headers: { Accept: 'application/json' } })
                    .then(response => response.json().then(body => ({ ok: response.ok, body })))
                    .then(({ ok, body }) => {
                        ready(findButton);
                        if (!ok) { say(body.message || 'We could not find that address.', 'bad'); return; }

                        place(body.latitude, body.longitude, { fillAddress: false, move: true });
                        if (!field('city')?.value && body.city) field('city').value = body.city;
                        if (!field('state')?.value && body.state) field('state').value = body.state;
                        say('Found it. Drag the pin onto the exact building if it is a little off.', 'good');
                    })
                    .catch(() => { ready(findButton); say('The address search is not reachable right now. Tap the map to drop the pin.', 'bad'); });
            });
        });
    })();
</script>
