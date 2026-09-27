/*
 * Cinematheque Centre Davao — progressive enhancement only.
 * Every page works without this file: forms submit normally, seats are plain
 * checkboxes, confirmations fall back to the browser's confirm().
 */
(function () {
    'use strict';

    var doc = document.documentElement;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* Sticky header turns solid once the page scrolls (as on fdcp.ph). */
    var header = document.querySelector('.site-header');
    if (header) {
        var onScroll = function () { header.classList.toggle('is-solid', window.scrollY > 24); };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* Mobile nav + staff sidebar toggles. */
    document.querySelectorAll('[data-toggle-class]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cls = btn.getAttribute('data-toggle-class');
            var open = !document.body.classList.contains(cls);
            document.body.classList.toggle(cls, open);
            btn.setAttribute('aria-expanded', String(open));
        });
    });
    document.querySelectorAll('.scrim').forEach(function (s) {
        s.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });
    });

    /* Reveal-on-scroll: sections and cards only, short slide-up. */
    var revealables = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && !reduceMotion) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
        revealables.forEach(function (el, i) {
            if (el.hasAttribute('data-stagger')) el.style.setProperty('--reveal-delay', Math.min(i % 6, 5) * 40 + 'ms');
            io.observe(el);
        });
    } else {
        revealables.forEach(function (el) { el.classList.add('is-visible'); });
    }

    /* Dismissible alerts. */
    document.querySelectorAll('[data-dismiss]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = btn.closest('.alert');
            if (!box) return;
            box.style.transition = 'opacity 150ms ease, transform 150ms ease';
            box.style.opacity = '0'; box.style.transform = 'translateY(-6px)';
            setTimeout(function () { box.remove(); }, reduceMotion ? 0 : 160);
        });
    });

    /* Confirmation modal for destructive actions: <form data-confirm="..."> */
    var modal = document.getElementById('confirm-modal');
    var pendingForm = null;
    function closeModal() {
        if (!modal || !modal.open) return;
        modal.classList.add('is-closing');
        setTimeout(function () { modal.classList.remove('is-closing'); modal.close(); }, reduceMotion ? 0 : 150);
    }
    if (modal && typeof modal.showModal === 'function') {
        modal.querySelector('[data-modal-cancel]').addEventListener('click', function () { pendingForm = null; closeModal(); });
        modal.querySelector('[data-modal-confirm]').addEventListener('click', function () {
            var f = pendingForm; pendingForm = null; closeModal();
            if (f) { f.dataset.confirmed = '1'; f.requestSubmit ? f.requestSubmit() : f.submit(); }
        });
        modal.addEventListener('cancel', function (e) { e.preventDefault(); pendingForm = null; closeModal(); });
    }

    /* Forms: confirmation, then loading state + top progress bar (also stops double-submits). */
    var progress = document.querySelector('.page-progress');
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var message = form.getAttribute('data-confirm');
        if (message && form.dataset.confirmed !== '1') {
            e.preventDefault();
            if (modal && typeof modal.showModal === 'function') {
                pendingForm = form;
                modal.querySelector('[data-modal-message]').textContent = message;
                var danger = form.getAttribute('data-confirm-label');
                modal.querySelector('[data-modal-confirm]').textContent = danger || 'Confirm';
                modal.showModal();
            } else if (window.confirm(message)) {
                form.dataset.confirmed = '1'; form.submit();
            }
            return;
        }
        if (form.hasAttribute('data-no-loading')) return;
        var btn = form.querySelector('button[type=submit]:not([name]), button:not([type]):not([name])');
        if (btn) { btn.classList.add('is-loading'); btn.setAttribute('aria-busy', 'true'); }
        if (progress && (form.method || 'get').toLowerCase() === 'post') progress.classList.add('is-active');
    });
    /* Coming back via the Back button restores the page — clear stale loading states. */
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); b.removeAttribute('aria-busy'); });
        document.querySelectorAll('form[data-confirmed]').forEach(function (f) { delete f.dataset.confirmed; });
        if (progress) progress.classList.remove('is-active');
    });

    /* Seat picker: live count, running total and the per-booking limit. */
    var picker = document.querySelector('[data-seat-picker]');
    if (picker) {
        var max = parseInt(picker.getAttribute('data-max'), 10) || 10;
        var price = parseFloat(picker.getAttribute('data-price') || '0');
        var all = function (sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); };
        var countEls = all('[data-seat-count]'), listEls = all('[data-seat-list]'), totalEls = all('[data-seat-total]'), submits = all('[data-seat-submit]');
        var boxes = Array.prototype.slice.call(picker.querySelectorAll('input[type=checkbox]'));
        var initiallyDisabled = boxes.filter(function (b) { return b.disabled; });

        var update = function () {
            var chosen = boxes.filter(function (b) { return b.checked; });
            var n = chosen.length;
            var total = price > 0 ? '₱' + (price * n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'Free';
            countEls.forEach(function (el) { el.textContent = n === 1 ? '1 seat' : n + ' seats'; });
            listEls.forEach(function (el) { el.textContent = n ? chosen.map(function (b) { return b.getAttribute('data-label'); }).join(', ') : 'None selected yet'; });
            totalEls.forEach(function (el) { el.textContent = total; });
            submits.forEach(function (b) { b.disabled = n === 0; });
            all('[data-seat-hint]').forEach(function (h) { h.hidden = n > 0; });
            boxes.forEach(function (b) {
                if (initiallyDisabled.indexOf(b) !== -1) return;
                b.disabled = !b.checked && n >= max;
            });
        };
        boxes.forEach(function (b) { b.addEventListener('change', update); });
        update();
    }

    /* Proof / QR upload: show the chosen image before submitting. */
    document.querySelectorAll('[data-preview]').forEach(function (input) {
        var target = document.getElementById(input.getAttribute('data-preview'));
        var zone = input.closest('.file-drop');
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (!target || !f || !/^image\//.test(f.type)) return;
            target.src = URL.createObjectURL(f);
            target.hidden = false;
            target.classList.add('fade-swap');
        });
        if (zone) {
            ['dragenter', 'dragover'].forEach(function (t) { zone.addEventListener(t, function () { zone.classList.add('is-dragover'); }); });
            ['dragleave', 'drop'].forEach(function (t) { zone.addEventListener(t, function () { zone.classList.remove('is-dragover'); }); });
        }
    });

    doc.classList.add('js-ready');
})();
