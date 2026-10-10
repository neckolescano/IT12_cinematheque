/*
 * Staff admin enhancements. Every action still works without JavaScript
 * (plain forms and links); this only removes page reloads and clicks.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;

    /* ---- Toasts ---------------------------------------------------------- */
    var stack = document.createElement('div');
    stack.className = 'toast-stack';
    stack.setAttribute('role', 'status');
    stack.setAttribute('aria-live', 'polite');
    document.body.appendChild(stack);
    function toast(message, isError) {
        var t = document.createElement('div');
        t.className = 'toast' + (isError ? ' toast--error' : '');
        t.textContent = message;
        stack.appendChild(t);
        setTimeout(function () { t.remove(); }, 3500);
    }

    /* ---- Sidebar (mobile) ------------------------------------------------ */
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (b) {
        b.addEventListener('click', function () {
            var open = document.body.classList.toggle('sidebar-open');
            b.setAttribute('aria-expanded', String(open));
        });
    });
    document.querySelectorAll('.scrim').forEach(function (s) {
        s.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });
    });

    /* ---- Dialogs: drawers + confirm modal -------------------------------- */
    function openDialog(d) { if (d && typeof d.showModal === 'function' && !d.open) d.showModal(); }
    function closeDialog(d) {
        if (!d || !d.open) return;
        d.classList.add('is-closing');
        setTimeout(function () { d.classList.remove('is-closing'); d.close(); }, reduceMotion ? 0 : 140);
    }
    // Delegated, so triggers inside checklist rows re-rendered by AJAX keep working.
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-open-dialog]');
        if (!trigger) return;
        var d = document.getElementById(trigger.getAttribute('data-open-dialog'));
        if (d && typeof d.showModal === 'function') {
            e.preventDefault();
            openDialog(d);
        }
    });

    var modal = document.getElementById('confirm-modal');
    var pending = null;
    if (modal) {
        modal.querySelector('[data-modal-cancel]').addEventListener('click', function () { pending = null; closeDialog(modal); });
        modal.querySelector('[data-modal-confirm]').addEventListener('click', function () {
            var f = pending; pending = null; closeDialog(modal);
            if (f) { f.dataset.confirmed = '1'; f.requestSubmit ? f.requestSubmit() : f.submit(); }
        });
    }

    /* ---- Form submit: confirm → ajax or loading state --------------------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var message = form.getAttribute('data-confirm');
        if (message && form.dataset.confirmed !== '1') {
            e.preventDefault();
            if (modal && typeof modal.showModal === 'function') {
                pending = form;
                modal.querySelector('[data-modal-message]').textContent = message;
                var go = modal.querySelector('[data-modal-confirm]');
                go.textContent = form.getAttribute('data-confirm-label') || 'Confirm';
                go.className = 'btn ' + (form.hasAttribute('data-confirm-danger') ? 'btn--danger-solid' : 'btn--primary');
                openDialog(modal);
            } else if (window.confirm(message)) {
                form.dataset.confirmed = '1'; form.submit();
            }
            return;
        }
        delete form.dataset.confirmed;

        if (form.hasAttribute('data-ajax') && window.fetch) {
            e.preventDefault();
            ajaxSubmit(form);
            return;
        }
        var btn = form.querySelector('button[type=submit], button:not([type])');
        if (btn && !form.hasAttribute('data-no-loading')) btn.classList.add('is-loading');
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); });
    });

    function ajaxSubmit(form) {
        var btn = form.querySelector('button[type=submit], button:not([type])');
        if (btn) btn.classList.add('is-loading');
        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) { return { ok: res.ok, data: data }; });
        }).then(function (r) {
            if (!r.ok) {
                if (btn) btn.classList.remove('is-loading');
                toast(r.data.message || 'That action is no longer allowed. Refreshing…', true);
                setTimeout(function () { window.location.reload(); }, 1400);
                return;
            }
            var party = form.closest('tbody[data-party]') || document.querySelector('tbody[data-party="' + (form.id || '').replace('admit-', '') + '"]');
            if (party && r.data.party) {
                var tmp = document.createElement('table');
                tmp.innerHTML = r.data.party.trim();
                var fresh = tmp.querySelector('tbody');
                if (party.classList.contains('is-open') && !fresh.classList.contains('is-muted')) fresh.classList.add('is-open');
                party.replaceWith(fresh);
                fresh.classList.add('is-flash');
                bindParty(fresh);
            }
            if (typeof r.data.admitted === 'number') {
                document.querySelectorAll('[data-admitted-count]').forEach(function (el) { el.textContent = r.data.admitted; });
                document.querySelectorAll('[data-admitted-bar]').forEach(function (el) {
                    var total = parseInt(el.getAttribute('data-total'), 10) || 0;
                    el.style.width = (total ? Math.min(100, r.data.admitted / total * 100) : 0) + '%';
                });
            }
            applyFilter();
            if (r.data.message) toast(r.data.message);
        }).catch(function () {
            if (btn) btn.classList.remove('is-loading');
            toast('Network problem — please try again.', true);
        });
    }

    /* ---- Attendance parties: expand to see attendees; "Admit party" = expand with everyone
            ticked + focus the confirm button; the confirm button counts the ticks. ------------ */
    function syncParty(p) {
        var open = p.classList.contains('is-open');
        var t = p.querySelector('[data-party-toggle]');
        if (t) t.setAttribute('aria-expanded', String(open));
        var btn = p.querySelector('[data-party-submit]');
        if (btn) {
            var n = p.querySelectorAll('.party__pick:checked').length;
            btn.textContent = n ? 'Admit ' + n : 'Admit';
            btn.disabled = n === 0;
        }
    }
    function bindParty(p) {
        var t = p.querySelector('[data-party-toggle]');
        if (t) t.addEventListener('click', function () { p.classList.toggle('is-open'); syncParty(p); });
        var admit = p.querySelector('[data-party-admit]');
        if (admit) admit.addEventListener('click', function () {
            p.querySelectorAll('.party__pick').forEach(function (c) { c.checked = true; });
            p.classList.add('is-open'); syncParty(p);
            var go = p.querySelector('[data-party-submit]');
            if (go) go.focus();
        });
        p.addEventListener('change', function (e) { if (e.target.classList.contains('party__pick')) syncParty(p); });
        syncParty(p);
    }
    document.querySelectorAll('tbody[data-party]').forEach(bindParty);

    /* ---- Checklist search + filter ---------------------------------------- */
    var list = document.querySelector('[data-checklist]');
    var searchInput = document.querySelector('[data-filter-input]');
    var filterButtons = document.querySelectorAll('[data-filter]');
    var activeFilter = 'all';
    function applyFilter() {
        if (!list) return;
        var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
        var counts = {};
        var visible = 0;
        list.querySelectorAll('tbody[data-party]').forEach(function (tr) {
            var states = (tr.getAttribute('data-state') || '').split(' ');
            states.concat('all').forEach(function (s) { counts[s] = (counts[s] || 0) + 1; });
            var match = (activeFilter === 'all' || states.indexOf(activeFilter) !== -1)
                && (!q || (tr.getAttribute('data-search') || '').indexOf(q) !== -1);
            tr.hidden = !match;
            if (match) visible++;
        });
        filterButtons.forEach(function (b) {
            var n = b.querySelector('.n');
            if (n) n.textContent = counts[b.getAttribute('data-filter')] || 0;
        });
        var none = document.querySelector('[data-no-match]');
        if (none) none.hidden = visible !== 0;
    }
    filterButtons.forEach(function (b) {
        b.addEventListener('click', function () {
            activeFilter = b.getAttribute('data-filter');
            filterButtons.forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
            applyFilter();
        });
    });
    if (searchInput) {
        searchInput.addEventListener('input', applyFilter);
        searchInput.addEventListener('keydown', function (e) { if (e.key === 'Escape') { searchInput.value = ''; applyFilter(); } });
    }
    applyFilter();

    /* ---- Clickable table rows --------------------------------------------- */
    function bindRow(tr) {
        if (!tr.hasAttribute('data-href')) return;
        tr.classList.add('row-link');
        tr.addEventListener('click', function (e) {
            if (e.target.closest('a, button, input, select, textarea, form, details, label')) return;
            window.location = tr.getAttribute('data-href');
        });
    }
    document.querySelectorAll('tr[data-href], li[data-href]').forEach(bindRow);

    /* ---- Dismissible alerts / close popovers on outside click ------------- */
    document.querySelectorAll('[data-dismiss]').forEach(function (b) {
        b.addEventListener('click', function () { var a = b.closest('.alert'); if (a) a.remove(); });
    });
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details[open].note-pop, details[open].usermenu, details[open].more').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });

    /* ---- Poster drop zone: the file input covers the box, so click and drop both work natively;
            this adds the drag highlight and an instant preview. ---------------------------------- */
    document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
        var input = zone.querySelector('input[type=file]');
        var img = zone.querySelector('.dropzone__preview');
        var prompt = zone.querySelector('.dropzone__prompt');
        ['dragenter', 'dragover'].forEach(function (t) { zone.addEventListener(t, function () { zone.classList.add('is-over'); }); });
        ['dragleave', 'drop'].forEach(function (t) { zone.addEventListener(t, function () { zone.classList.remove('is-over'); }); });
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { toast('Use a JPG, PNG or WebP image.', true); input.value = ''; return; }
            if (file.size > 2 * 1024 * 1024) { toast('That image is larger than 2 MB.', true); input.value = ''; return; }
            img.src = URL.createObjectURL(file);
            img.hidden = false;
            if (prompt) prompt.hidden = true;
            var remove = zone.parentNode.querySelector('input[name=remove_poster]');
            if (remove) remove.checked = false;
        });
    });

    /* ---- Genre "Other": the text box follows the chip (unticking clears it, ticking restores it) ---- */
    document.querySelectorAll('[data-genre-field]').forEach(function (f) {
        var toggle = f.querySelector('[data-genre-other-toggle]');
        var boxEl = f.querySelector('[data-genre-other-box]');
        var input = f.querySelector('[data-genre-other]');
        var kept = '';
        function sync(focus) {
            boxEl.hidden = !toggle.checked;
            if (toggle.checked) { if (!input.value) input.value = kept; if (focus) input.focus(); }
            else { kept = input.value; input.value = ''; }
        }
        toggle.addEventListener('change', function () { sync(true); });
        sync(false);
    });

    /* ---- Screening form: every screening has a film (a shorts block is a film record); a known film title fills in its details;
            end time = start + runtime; the price field only shows for paid screenings. ----- */
    document.querySelectorAll('[data-screening-form]').forEach(function (box) {
        var catalog = [];
        try { catalog = JSON.parse(box.getAttribute('data-catalog') || '[]'); } catch (e) { catalog = []; }
        var filmFields = box.querySelector('[data-film-fields]');
        var titleInput = box.querySelector('[data-film-title]');
        var knownHint = box.querySelector('[data-known-hint]');
        var start = box.querySelector('[data-start-input]');
        var end = box.querySelector('[data-end-input]');
        var endHint = box.querySelector('[data-end-hint]');
        var priceField = box.querySelector('[data-price-field]');
        var field = function (name) { return box.querySelector('[data-film="' + name + '"]'); };
        var autoEnd = null;

        function isFilm() { var f = box.querySelector('[data-kind-input][value=film]'); return !f || f.checked; }
        function syncKind() {
            var film = isFilm();
            if (!box.querySelector('[data-kind-input]')) { filmFields.hidden = false; titleInput.required = true; return; }
            filmFields.hidden = !film;
            titleInput.required = film;
            box.querySelector('[data-title-label-film]').hidden = !film;
            box.querySelector('[data-title-label-programme]').hidden = film;
        }
        function known() {
            var t = (titleInput.value || '').trim().toLowerCase();
            for (var i = 0; i < catalog.length; i++) if (catalog[i].title.toLowerCase() === t) return catalog[i];
            return null;
        }
        // Fill only empty fields, so anything staff already typed is kept.
        function fillFromCatalog() {
            var m = known();
            knownHint.hidden = !m;
            if (!m) return;
            var program = box.querySelector('[data-program-input]');
            if (program && !program.value && m.programs && m.programs.length === 1) program.value = m.programs[0];
            [['runtime', m.runtime], ['rating', m.rating], ['year', m.year], ['directors', m.directors], ['actors', m.actors]].forEach(function (pair) {
                var el = field(pair[0]);
                if (el && !el.value && pair[1]) el.value = pair[1];
            });
            var boxes = box.querySelectorAll('[data-film-genre]');
            var anyChecked = Array.prototype.some.call(boxes, function (b) { return b.checked; });
            if (!anyChecked) {
                var listed = [];
                boxes.forEach(function (b) { listed.push(b.value); b.checked = (m.genres || []).indexOf(b.value) !== -1; });
                var custom = (m.genres || []).filter(function (g) { return listed.indexOf(g) === -1; });
                var otherToggle = box.querySelector('[data-genre-other-toggle]');
                var otherInput = box.querySelector('[data-genre-other]');
                if (custom.length && otherToggle && otherInput && !otherInput.value) {
                    otherToggle.checked = true; otherInput.value = custom.join(', ');
                    otherToggle.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
            fillEnd();
        }
        function fillEnd() {
            var runtime = parseInt(field('runtime').value, 10);
            if (!(runtime > 0 && isFilm() && /^\d{2}:\d{2}$/.test(start.value) && (!end.value || end.value === autoEnd))) {
                endHint.hidden = !(end.value && end.value === autoEnd);
                return;
            }
            var parts = start.value.split(':');
            var mins = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10) + runtime;
            if (mins < 24 * 60) {
                end.value = autoEnd = String(Math.floor(mins / 60)).padStart(2, '0') + ':' + String(mins % 60).padStart(2, '0');
            }
            endHint.hidden = end.value !== autoEnd;
        }
        function syncPrice() {
            var paid = box.querySelector('[data-type-input][value=paid]');
            priceField.hidden = !(paid && paid.checked);
        }

        box.querySelectorAll('[data-kind-input]').forEach(function (r) { r.addEventListener('change', function () { syncKind(); fillEnd(); }); });
        titleInput.addEventListener('change', fillFromCatalog);
        titleInput.addEventListener('input', function () { knownHint.hidden = !known(); });
        field('runtime').addEventListener('input', fillEnd);
        start.addEventListener('change', fillEnd);
        box.querySelectorAll('[data-type-input]').forEach(function (r) { r.addEventListener('change', syncPrice); });
        syncKind(); syncPrice(); knownHint.hidden = !known();
    });

    /* ---- New/Edit screening: the summary panel mirrors the form as it is filled in ---- */
    var summary = document.querySelector('[data-screening-summary]');
    if (summary) {
        var form = summary.closest('form');
        var q = function (sel) { return form.querySelector(sel); };
        var put = function (key, text) { summary.querySelector('[data-sum="' + key + '"]').textContent = text || '—'; };
        var fmtTime = function (v) {
            if (!/^\d{2}:\d{2}/.test(v || '')) return '';
            var h = parseInt(v.slice(0, 2), 10), m = v.slice(3, 5);
            return ((h % 12) || 12) + ':' + m + (h < 12 ? ' AM' : ' PM');
        };
        var refresh = function () {
            var title = (q('[data-event-title]').value || '').trim() || (q('[data-film-title]').value || '').trim();
            put('title', title);
            var program = q('[data-program-input]');
            put('program', program ? program.value.trim() : '');
            var d = q('[data-date-input]').value;
            put('date', d ? new Date(d + 'T00:00').toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) : '');
            var s = fmtTime(q('[data-start-input]').value), e = fmtTime(q('[data-end-input]').value);
            put('time', s ? s + (e ? ' – ' + e : '') : '');
            var paid = q('[data-type-input][value=paid]').checked, price = q('[data-price-input]').value;
            put('admission', paid ? (price ? '₱' + Number(price).toLocaleString() + ' per seat' : 'Paid') : 'Free');
        };
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        refresh();
    }

    /* ---- Program tags: Enter or a comma turns the typed text into a chip (hidden programs[] input);
            × removes it. Duplicates (ignoring case) are skipped. ------------------------------------- */
    document.querySelectorAll('[data-tags]').forEach(function (box) {
        var input = box.querySelector('[data-tag-input]');
        var add = function () {
            var name = input.value.replace(/\s+/g, ' ').replace(/,/g, '').trim();
            if (!name) return;
            var key = name.toLowerCase();
            if (!box.querySelector('[data-tag="' + CSS.escape(key) + '"]')) {
                var chip = document.createElement('span');
                chip.className = 'tag-chip';
                chip.setAttribute('data-tag', key);
                chip.appendChild(document.createTextNode(name));
                var hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = 'programs[]'; hidden.value = name;
                var x = document.createElement('button');
                x.type = 'button'; x.textContent = '×'; x.setAttribute('data-tag-remove', ''); x.setAttribute('aria-label', 'Remove ' + name);
                chip.appendChild(hidden); chip.appendChild(x);
                input.parentNode.insertBefore(chip, input);
            }
            input.value = '';
            input.placeholder = 'Add another';
        };
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(); }
            if (e.key === 'Backspace' && !input.value) {
                var chips = box.querySelectorAll('.tag-chip');
                if (chips.length) chips[chips.length - 1].remove();
            }
        });
        input.addEventListener('change', add); // a suggestion picked from the list, or leaving the box
        box.addEventListener('click', function (e) {
            if (e.target.hasAttribute('data-tag-remove')) { e.target.closest('.tag-chip').remove(); input.focus(); }
        });
    });

    /* ---- Batch scheduling: "Repeat" shows "Until"; "Add showtime" adds a date + time row. ---------- */
    document.querySelectorAll('[data-repeat]').forEach(function (sel) {
        var until = sel.form.querySelector('[data-repeat-until]');
        var sync = function () { if (until) until.hidden = sel.value === 'none'; };
        sel.addEventListener('change', sync);
        sync();
    });
    document.querySelectorAll('[data-more-showtimes]').forEach(function (box) {
        var rows = box.querySelector('[data-more-rows]');
        var next = rows.children.length;
        box.querySelector('[data-more-add]').addEventListener('click', function () {
            var row = rows.firstElementChild.cloneNode(true);
            row.querySelectorAll('input').forEach(function (input) {
                input.value = '';
                input.name = input.name.replace(/more\[\d+\]/, 'more[' + next + ']');
                input.id = input.id.replace(/_more_\d+_/, '_more_' + next + '_');
            });
            row.querySelectorAll('label').forEach(function (l) { l.htmlFor = l.htmlFor.replace(/_more_\d+_/, '_more_' + next + '_'); });
            next++;
            rows.appendChild(row);
            row.querySelector('input').focus();
        });
        rows.addEventListener('click', function (e) {
            if (!e.target.hasAttribute('data-more-remove')) return;
            var row = e.target.closest('[data-more-row]');
            if (rows.children.length > 1) row.remove();
            else row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        });
    });

    /* ---- Door check-in: reload when the window opens or closes, so the Admit buttons switch on (or off)
            by themselves. The delay comes from the server clock, not this device's. ------------------ */
    var checkin = document.querySelector('[data-reload-in]');
    if (checkin) {
        setTimeout(function () { location.reload(); }, (parseInt(checkin.getAttribute('data-reload-in'), 10) + 1) * 1000);
    }

    /* ---- Filters that apply as soon as they change (no "Apply" button) ------ */
    document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.form.requestSubmit ? sel.form.requestSubmit() : sel.form.submit(); });
    });

    /* ---- "/" focuses the global search in the top bar ---------------------- */
    document.addEventListener('keydown', function (e) {
        var target = document.getElementById('global-search');
        if (e.key === '/' && target && !/input|textarea|select/i.test(document.activeElement.tagName)) {
            e.preventDefault(); target.focus(); target.select();
        }
    });
})();
