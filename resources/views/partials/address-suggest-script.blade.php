{{--
    Address suggestions while typing. Include once on a page, then mark an
    address box with data-address-suggest. Optional attributes on the box:

      data-latitude="#id"  data-longitude="#id"   hidden fields to fill with the map position
      data-city="#id"      data-state="#id"       fields to fill with the city and state
      data-fill="label"    what goes into the box itself: "label" (the whole address,
                           the default) or "address" (the street part only, for forms
                           that have their own city and state boxes)
      data-submit-on-pick                         send the form as soon as an address is chosen

    Choosing an address also fires an "address:picked" event on the box, with
    the place in event.detail, for pages that want to do more (move a map pin).
--}}
<script>
    (function () {
        const SUGGEST_URL = @json(route('location.suggest'));
        const MIN_LETTERS = {{ (int) config('curad.places.min_letters') }};
        const CREDITS = { geoapify: 'Geoapify', locationiq: 'LocationIQ', photon: 'Photon' };

        document.querySelectorAll('[data-address-suggest]').forEach((box, index) => {
            const find = selector => selector ? document.querySelector(selector) : null;
            const latitude = find(box.dataset.latitude);
            const longitude = find(box.dataset.longitude);
            const listId = 'address-suggestions-' + index;

            // The list hangs under the box, so the box gets a wrapper to hang it from.
            const wrap = document.createElement('div');
            wrap.className = 'relative';
            box.parentNode.insertBefore(wrap, box);
            wrap.appendChild(box);

            const list = document.createElement('div');
            list.id = listId;
            list.setAttribute('role', 'listbox');
            list.className = 'absolute inset-x-0 top-full z-[1200] mt-1.5 hidden max-h-80 overflow-y-auto rounded-xl border border-slate-200 bg-white text-left shadow-xl shadow-slate-900/10';
            wrap.appendChild(list);

            box.setAttribute('autocomplete', 'off');
            box.setAttribute('role', 'combobox');
            box.setAttribute('aria-autocomplete', 'list');
            box.setAttribute('aria-controls', listId);
            box.setAttribute('aria-expanded', 'false');

            let places = [];
            let active = -1;
            let timer = null;
            let asked = 0;          // only the answer to the latest question is shown
            let chosen = box.value;  // what the box held when an address was last chosen

            const close = () => { list.classList.add('hidden'); box.setAttribute('aria-expanded', 'false'); active = -1; };
            const open = () => { list.classList.remove('hidden'); box.setAttribute('aria-expanded', 'true'); };

            function note(icon, text) {
                list.replaceChildren();
                const line = document.createElement('p');
                line.className = 'flex items-start gap-2.5 px-3.5 py-3 text-sm leading-5 text-slate-500';
                line.innerHTML = `<i class="fas ${icon} mt-0.5 text-slate-400"></i>`;
                const words = document.createElement('span');
                words.textContent = text;
                line.appendChild(words);
                list.appendChild(line);
                open();
            }

            function highlight(next) {
                const options = [...list.querySelectorAll('[role="option"]')];
                if (!options.length) return;
                active = (next + options.length) % options.length;
                options.forEach((option, position) => {
                    option.classList.toggle('bg-emerald-50', position === active);
                    option.setAttribute('aria-selected', position === active ? 'true' : 'false');
                });
                options[active].scrollIntoView({ block: 'nearest' });
            }

            function pick(place) {
                box.value = box.dataset.fill === 'address' ? (place.address || place.title) : place.label;
                chosen = box.value;

                if (latitude && longitude && place.latitude !== null && place.longitude !== null) {
                    latitude.value = place.latitude;
                    longitude.value = place.longitude;
                }

                [['city', place.city], ['state', place.state]].forEach(([name, value]) => {
                    const field = find(box.dataset[name]);
                    if (field && value) field.value = value;
                });

                close();
                box.dispatchEvent(new Event('change', { bubbles: true }));
                box.dispatchEvent(new CustomEvent('address:picked', { bubbles: true, detail: place }));

                if ('submitOnPick' in box.dataset && box.form) box.form.requestSubmit ? box.form.requestSubmit() : box.form.submit();
            }

            function show(answer) {
                places = answer.places || [];
                list.replaceChildren();

                if (!places.length) {
                    note('fa-magnifying-glass', answer.provider
                        ? 'No address matches yet. Keep typing, or type the full address yourself.'
                        : 'Suggestions are not available right now. Type the full address yourself.');
                    return;
                }

                places.forEach((place, position) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.className = 'flex w-full items-start gap-3 border-b border-slate-100 px-3.5 py-2.5 text-left transition last:border-b-0 hover:bg-emerald-50';
                    option.innerHTML = '<span class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><i class="fas fa-location-dot text-sm"></i></span>';

                    const words = document.createElement('span');
                    words.className = 'min-w-0 flex-1';
                    const title = document.createElement('span');
                    title.className = 'block truncate text-sm font-semibold text-slate-900';
                    title.textContent = place.title;
                    const subtitle = document.createElement('span');
                    subtitle.className = 'block truncate text-xs text-slate-500';
                    subtitle.textContent = place.subtitle;
                    words.append(title, subtitle);
                    option.appendChild(words);

                    // mousedown, not click: the box must not lose focus (and close the list) first.
                    option.addEventListener('mousedown', event => { event.preventDefault(); pick(place); });
                    option.addEventListener('mousemove', () => { if (active !== position) highlight(position); });
                    list.appendChild(option);
                });

                const credit = document.createElement('p');
                credit.className = 'bg-slate-50 px-3.5 py-1.5 text-right text-[10px] text-slate-400';
                credit.textContent = 'Suggestions by ' + (CREDITS[answer.provider] || answer.provider) + ' · © OpenStreetMap';
                list.appendChild(credit);
                open();
            }

            function ask() {
                const text = box.value.trim();

                if (text.length < MIN_LETTERS || text === chosen.trim()) { close(); return; }

                const mine = ++asked;
                const near = latitude && longitude && latitude.value && longitude.value ? `&lat=${latitude.value}&lng=${longitude.value}` : '';

                note('fa-circle-notch fa-spin', 'Looking for addresses…');

                fetch(`${SUGGEST_URL}?q=${encodeURIComponent(text)}${near}`, { headers: { Accept: 'application/json' } })
                    .then(response => response.ok ? response.json() : { places: [], provider: null })
                    .then(answer => { if (mine === asked && document.activeElement === box) show(answer); })
                    .catch(() => { if (mine === asked) show({ places: [], provider: null }); });
            }

            // Wait for a pause in the typing before asking.
            box.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(ask, 350); });
            box.addEventListener('focus', () => { if (places.length && box.value.trim() !== chosen.trim()) open(); });
            box.addEventListener('blur', () => setTimeout(close, 150));

            box.addEventListener('keydown', event => {
                if (list.classList.contains('hidden')) return;

                if (event.key === 'ArrowDown') { event.preventDefault(); highlight(active + 1); }
                else if (event.key === 'ArrowUp') { event.preventDefault(); highlight(active - 1); }
                else if (event.key === 'Enter' && active >= 0) { event.preventDefault(); pick(places[active]); }
                else if (event.key === 'Escape') { close(); }
            });
        });
    })();
</script>
