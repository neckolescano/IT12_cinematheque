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
    document.querySelectorAll('[data-open-dialog]').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            var d = document.getElementById(trigger.getAttribute('data-open-dialog'));
            if (d && typeof d.showModal === 'function') { e.preventDefault(); openDialog(d); }
        });
    });
    document.querySelectorAll('dialog').forEach(function (d) {
        d.querySelectorAll('[data-close-dialog]').forEach(function (b) { b.addEventListener('click', function () { closeDialog(d); }); });
        d.addEventListener('cancel', function (e) { e.preventDefault(); closeDialog(d); });
        d.addEventListener('click', function (e) { if (e.target === d && d.classList.contains('drawer')) closeDialog(d); });
        if (d.hasAttribute('data-open-on-load')) openDialog(d);
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
                modal.querySelector('[data-modal-confirm]').textContent = form.getAttribute('data-confirm-label') || 'Confirm';
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
            var row = form.closest('tr[data-row]');
            if (row && r.data.row) {
                var tmp = document.createElement('tbody');
                tmp.innerHTML = r.data.row.trim();
                var fresh = tmp.firstElementChild;
                row.replaceWith(fresh);
                fresh.classList.add('is-flash');
                bindRow(fresh);
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
        list.querySelectorAll('tr[data-row]').forEach(function (tr) {
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
    document.querySelectorAll('tr[data-href]').forEach(bindRow);

    /* ---- Dismissible alerts / close popovers on outside click ------------- */
    document.querySelectorAll('[data-dismiss]').forEach(function (b) {
        b.addEventListener('click', function () { var a = b.closest('.alert'); if (a) a.remove(); });
    });
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details[open].note-pop, details[open].usermenu').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });

    /* ---- "/" focuses the global search ------------------------------------ */
    var globalSearch = document.getElementById('global-search');
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && globalSearch && !/input|textarea|select/i.test(document.activeElement.tagName)) {
            e.preventDefault(); globalSearch.focus();
        }
    });
})();
