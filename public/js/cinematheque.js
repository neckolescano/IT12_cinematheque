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
    /* Success popup after booking: shown for a few seconds, then closes to reveal the receipt. */
    document.querySelectorAll('dialog[data-autoclose]').forEach(function (dlg) {
        if (typeof dlg.showModal !== 'function') { dlg.remove(); return; }
        var ms = parseInt(dlg.getAttribute('data-autoclose'), 10) || 3500;
        var timer;
        var close = function () {
            clearTimeout(timer);
            dlg.classList.add('is-closing');
            setTimeout(function () { dlg.close(); dlg.remove(); }, reduceMotion ? 0 : 150);
        };
        dlg.style.setProperty('--autoclose', ms + 'ms');
        dlg.showModal();
        timer = setTimeout(close, ms);
        dlg.querySelector('[data-modal-close]').addEventListener('click', close);
        dlg.addEventListener('cancel', function (e) { e.preventDefault(); close(); });
        dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });
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
        var order = []; // seats in the order they were picked: the first one is the primary booker

        var update = function () {
            var chosen = boxes.filter(function (b) { return b.checked; });
            var n = chosen.length;
            var total = price > 0 ? '₱' + (price * n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'Free';
            countEls.forEach(function (el) { el.textContent = n === 1 ? '1 seat' : n + ' seats'; });
            listEls.forEach(function (el) { el.textContent = n ? order.map(function (b) { return b.getAttribute('data-label'); }).join(', ') : '—'; });
            totalEls.forEach(function (el) { el.textContent = total; });
            submits.forEach(function (b) { b.disabled = n === 0; });
            all('[data-seat-hint]').forEach(function (h) { h.hidden = n > 0; });
            boxes.forEach(function (b) {
                if (initiallyDisabled.indexOf(b) !== -1) return;
                b.disabled = !b.checked && n >= max;
            });
        };
        boxes.forEach(function (b) {
            b.addEventListener('change', function () {
                order = order.filter(function (x) { return x !== b; });
                if (b.checked) order.push(b);
                update();
            });
        });
        // Submit the seats in picking order (checkboxes alone would submit in seat order).
        picker.addEventListener('submit', function () {
            order.forEach(function (b) {
                var h = document.createElement('input');
                h.type = 'hidden'; h.name = 'seats[]'; h.value = b.value;
                picker.appendChild(h);
                b.removeAttribute('name');
            });
        });
        update();
    }

    /* Details step, paid screenings: a PWD or Senior Citizen ID makes that seat's ticket 20% off.
       Recount regular / discounted tickets and the total as ID numbers are typed (the server prices it again). */
    var priced = document.querySelector('[data-price-summary]');
    var people = Array.prototype.slice.call(document.querySelectorAll('[data-person]'));
    if (priced && people.length) {
        var full = parseFloat(priced.getAttribute('data-price')) || 0;
        var off = parseFloat(priced.getAttribute('data-discount-price')) || 0;
        var peso = function (v) { return '₱' + v.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        var reprice = function () {
            var discounted = people.filter(function (p) {
                return Array.prototype.some.call(p.querySelectorAll('input[name$="[pwd_id_no]"], input[name$="[senior_card_no]"]'), function (i) { return i.value.trim() !== ''; });
            }).length;
            var regular = people.length - discounted;
            priced.querySelectorAll('[data-regular-count]').forEach(function (el) { el.textContent = regular; });
            priced.querySelectorAll('[data-discount-count]').forEach(function (el) { el.textContent = discounted; });
            priced.querySelectorAll('[data-discount-row]').forEach(function (el) { el.hidden = discounted === 0; });
            priced.querySelectorAll('[data-seat-total]').forEach(function (el) { el.textContent = peso(regular * full + discounted * off); });
        };
        people.forEach(function (p) { p.addEventListener('input', reprice); });
        reprice();
    }

    /* PWD ID numbers: 16 digits shown as RR-PPMM-BBB-NNNNNNN as they are typed. */
    document.querySelectorAll('[data-pwd-id]').forEach(function (input) {
        input.addEventListener('input', function () {
            var d = input.value.replace(/\D/g, '').slice(0, 16);
            var parts = [d.slice(0, 2), d.slice(2, 6), d.slice(6, 9), d.slice(9)].filter(function (p) { return p; });
            input.value = parts.join('-');
        });
    });

    /* Mobile numbers: the 10 digits after the fixed +63 prefix, shown as 9XX XXX XXXX. A pasted
       +63 / 0 prefix is dropped, so the country code never appears twice. */
    var formatMobile = function (value) {
        var v = value.replace(/\D/g, '');
        if (v.length > 10 && v.indexOf('63') === 0) v = v.slice(2);       // pasted +639XXXXXXXXX
        else if (v.length > 10 && v.indexOf('0') === 0) v = v.slice(1);   // pasted 09XXXXXXXXX
        v = v.slice(0, 10);
        return [v.slice(0, 3), v.slice(3, 6), v.slice(6)].filter(function (p) { return p; }).join(' ');
    };
    document.querySelectorAll('[data-digits]').forEach(function (input) {
        input.addEventListener('input', function () { input.value = formatMobile(input.value); });
    });

    /* Guests: "Same mobile and email as seat 1" copies (and keeps following) the booker's. */
    var primary = document.querySelector('[data-person][data-primary]');
    if (primary) {
        var src = function (k) { return primary.querySelector('[data-contact="' + k + '"]'); };
        document.querySelectorAll('[data-same-contact]').forEach(function (box) {
            var person = box.closest('[data-person]');
            var dst = function (k) { return person.querySelector('[data-contact="' + k + '"]'); };
            var copy = function () {
                ['mobile', 'email'].forEach(function (k) {
                    dst(k).readOnly = box.checked;
                    if (box.checked) dst(k).value = src(k).value;
                });
            };
            box.addEventListener('change', copy);
            ['mobile', 'email'].forEach(function (k) { src(k).addEventListener('input', function () { if (box.checked) copy(); }); });
        });
    }

    /* Copy the booking reference. */
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(btn.getAttribute('data-copy')).then(function () {
                btn.title = 'Copied'; btn.setAttribute('aria-label', 'Booking reference copied');
            });
        });
    });

    /* Bookings opened on this device (this browser only, like the app's "On this phone"). */
    var KEY = 'ccd-bookings';
    var readSaved = function () { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } };
    var ticket = document.querySelector('[data-save-booking]');
    if (ticket) {
        var entry = {};
        ['ref', 'title', 'date', 'start', 'url'].forEach(function (k) { entry[k] = ticket.getAttribute('data-' + k); });
        var saved = readSaved().filter(function (b) { return b.ref !== entry.ref; });
        saved.unshift(entry);
        try { localStorage.setItem(KEY, JSON.stringify(saved.slice(0, 12))); } catch (e) {}
    }
    var savedBox = document.querySelector('[data-saved-bookings]');
    if (savedBox) {
        var list = readSaved();
        if (list.length) {
            var holder = savedBox.querySelector('[data-saved-list]');
            list.forEach(function (b) {
                var a = document.createElement('a');
                a.className = 'mini-ticket'; a.href = b.url;
                var d = new Date(b.start + 'T00:00');
                a.innerHTML = '<span class="mini-ticket__stub"><span class="date-stub date-stub--sm"><span class="date-stub__m"></span><span class="date-stub__d"></span><span class="date-stub__w"></span></span></span>'
                    + '<span class="mini-ticket__body"><strong></strong><span class="muted small"></span><span class="ref small"></span></span>';
                a.querySelector('.date-stub__m').textContent = d.toLocaleDateString('en', { month: 'short' }).toUpperCase();
                a.querySelector('.date-stub__d').textContent = d.getDate();
                a.querySelector('.date-stub__w').textContent = d.toLocaleDateString('en', { weekday: 'short' }).toUpperCase();
                a.querySelector('strong').textContent = b.title;
                a.querySelector('.muted').textContent = b.date;
                a.querySelector('.ref').textContent = b.ref;
                holder.appendChild(a);
            });
            savedBox.hidden = false;
        }
    }

    /* ---- About: the mobile app's scroll-linked motion -------------------------------
       Driven by where things are on screen (not timers), so scrolling back reverses it.
       progress(el, from, to): 0 while the element's top is below `from` × viewport height,
       rising to 1 as it reaches `to` × viewport height. Reduced motion: everything settled. */
    var about = document.querySelector('[data-about]');
    if (about) {
        var settled = reduceMotion;
        var easeOut = function (x) { return 1 - Math.pow(1 - x, 3); };
        var clamp01 = function (x) { return Math.max(0, Math.min(1, x)); };
        var stage = function (p, a, b) { return easeOut(clamp01((p - a) / (b - a))); };
        var progress = function (top, vh, from, to) { return clamp01((vh * from - top) / (vh * from - vh * to)); };

        var reveals = Array.prototype.slice.call(about.querySelectorAll('[data-reveal]'));
        var rows = Array.prototype.slice.call(about.querySelectorAll('[data-tl-row]'));
        var timeline = about.querySelector('[data-timeline]');
        var ink = about.querySelector('[data-timeline-ink]');
        var map = about.querySelector('[data-map]');
        var dots = map ? Array.prototype.slice.call(map.querySelectorAll('[data-arrive]')) : [];
        var parallels = map ? Array.prototype.slice.call(map.querySelectorAll('.centres-map__parallel')) : [];
        var strip = about.querySelector('[data-era-strip]');
        var eraRows = rows.filter(function (r) { return r.hasAttribute('data-era'); });
        var endMark = about.querySelector('[data-timeline-end]');
        var names = []; try { names = JSON.parse(about.querySelector('[data-era-names]').textContent); } catch (e) {}
        var opening = document.querySelector('[data-opening]');
        var media = opening && opening.querySelector('[data-opening-media]');
        var title = opening && opening.querySelector('[data-opening-title]');
        var headerH = function () { var h = document.querySelector('.site-header'); return h ? h.offsetHeight : 64; };

        rows.forEach(function (r) {
            var node = r.querySelector('.tl-node');
            if (node) node.style.setProperty('--node-y', (r.getAttribute('data-node-y') || 20) + 'px');
        });

        // The rail ends at the last node (2022), not at the bottom of the last entry's text.
        var track = timeline && timeline.querySelector('.timeline__track');
        var sizeTrack = function () {
            if (!track || !rows.length) return;
            var last = rows[rows.length - 1];
            var end = last.offsetTop + parseFloat(last.getAttribute('data-node-y') || 20);
            track.style.setProperty('--track', Math.max(0, end - track.offsetTop) + 'px');
        };
        sizeTrack();
        window.addEventListener('resize', sizeTrack);
        window.addEventListener('load', sizeTrack);

        var frame = 0;
        var render = function () {
            frame = 0;
            var vh = window.innerHeight;
            if (settled) {
                reveals.forEach(function (el) { el.style.setProperty('--t', 1); });
                rows.forEach(function (r) { ['--s1', '--s2', '--s3', '--fill'].forEach(function (k) { r.style.setProperty(k, 1); }); });
                if (ink) ink.style.setProperty('--ink', '100%');
                return;
            }

            // Opening: the photo moves slower than the page and its crop tightens; the title drifts and fades.
            if (opening) {
                var oh = opening.offsetHeight;
                var off = Math.min(Math.max(window.scrollY, 0), oh);
                var away = off / oh;
                if (media) media.style.transform = 'translateY(' + (off * 0.45) + 'px) scale(' + (1 + 0.07 * away) + ')';
                if (title) {
                    title.style.transform = 'translateY(' + (off * 0.18) + 'px)';
                    title.style.opacity = clamp01(1 - away * 1.4);
                }
            }

            // Chapter pieces: fade and lift into place.
            reveals.forEach(function (el) {
                var top = el.getBoundingClientRect().top;
                el.style.setProperty('--t', stage(progress(top, vh, 0.96, 0.72), 0, 1));
            });

            // Timeline: the gold line reaches down to the reading line (62% of the viewport).
            var pen = vh * 0.62;
            if (timeline && ink) {
                var tt = timeline.getBoundingClientRect().top + 40;
                ink.style.setProperty('--ink', Math.max(0, pen - tt) + 'px');
            }
            rows.forEach(function (r) {
                var top = r.getBoundingClientRect().top;
                var nodeY = parseFloat(r.getAttribute('data-node-y') || 20);
                r.style.setProperty('--fill', clamp01((pen - top - nodeY + 6) / 22));
                if (r.hasAttribute('data-quick')) {
                    r.style.setProperty('--s1', stage(progress(top, vh, 0.9, 0.66), 0, 1));
                } else {
                    var p = progress(top, vh, 0.9, 0.5);       // year → name → text
                    r.style.setProperty('--s1', stage(p, 0, 0.45));
                    r.style.setProperty('--s2', stage(p, 0.2, 0.65));
                    r.style.setProperty('--s3', stage(p, 0.4, 0.9));
                }
            });

            // Pinned era: appears as the era's big year passes under the header; the next era pushes it out.
            if (strip && eraRows.length) {
                var line = headerH() + 48;
                var current = -1, currentTop = 0;
                eraRows.forEach(function (r, i) {
                    var t = r.getBoundingClientRect().top;
                    if (t + 72 < line) { current = i; currentTop = t; }
                });
                if (current < 0) {
                    strip.classList.remove('is-on');
                } else {
                    var next = current + 1 < eraRows.length ? eraRows[current + 1] : endMark;
                    var push = next ? Math.max(-48, Math.min(0, next.getBoundingClientRect().top - line)) : 0;
                    var appear = easeOut(clamp01((line - (currentTop + 72)) / 36));
                    var shift = Math.min(push, -48 * (1 - appear));
                    strip.style.top = headerH() + 'px';
                    strip.classList.toggle('is-on', shift > -48);
                    strip.querySelector('.era-strip__bar').style.setProperty('--shift', shift + 'px');
                    var era = names[current] || {};
                    var nameEl = strip.querySelector('[data-era-name]');
                    strip.querySelector('[data-era-years]').textContent = era.years || '';
                    nameEl.textContent = era.name || '';
                    if (nameEl.scrollWidth > nameEl.clientWidth) nameEl.textContent = era.short || era.name; // never cut a name mid-word
                }
            }

            // Map: parallels first, then the centres north to south, Davao last.
            if (map) {
                var mp = progress(map.getBoundingClientRect().top, vh, 0.95, 0.4);
                var grid = stage(mp, 0, 0.3);
                parallels.forEach(function (el) { el.style.setProperty('--grid', grid); });
                dots.forEach(function (d) {
                    var span = d.getAttribute('data-arrive').split(',');
                    d.style.setProperty('--a', stage(mp, parseFloat(span[0]), parseFloat(span[1])));
                });
            }
        };
        // One render per frame; if frames are paused (e.g. a background or embedded view), render anyway.
        var request = function () {
            if (frame) return;
            frame = requestAnimationFrame(render);
            setTimeout(function () { if (frame) { cancelAnimationFrame(frame); render(); } }, 100);
        };
        window.addEventListener('scroll', request, { passive: true });
        window.addEventListener('resize', request);
        render();
    }

    /* Home poster showcase: the date bar becomes tabs (arrow keys, remembered in the URL hash), and
       showtime chips open that film's schedule drawer with the chosen time highlighted.
       Without this, all three lists show in turn and chips link to the screening page. */
    document.querySelectorAll('[data-showcase]').forEach(function (showcase) {
        var tabs = Array.prototype.slice.call(showcase.querySelectorAll('[data-tab]'));
        var keepTab = showcase.querySelectorAll('[data-keep-tab]');
        var panelOf = function (tab) { return document.getElementById(tab.hash.slice(1)); };
        var select = function (tab, remember) {
            tabs.forEach(function (t) {
                var on = t === tab;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                if (panelOf(t)) panelOf(t).classList.toggle('is-active', on);
            });
            if (remember) {
                history.replaceState(null, '', tab.hash);
                keepTab.forEach(function (a) { a.hash = tab.hash; }); // Free/Paid filter stays on this tab
            }
        };
        var list = showcase.querySelector('[data-tablist]');
        if (list && tabs.length) {
            list.setAttribute('role', 'tablist');
            tabs.forEach(function (t, i) {
                t.setAttribute('role', 'tab');
                t.setAttribute('aria-controls', t.hash.slice(1));
                if (panelOf(t)) { panelOf(t).setAttribute('role', 'tabpanel'); panelOf(t).setAttribute('aria-labelledby', t.id); }
                t.addEventListener('click', function (e) { e.preventDefault(); select(t, true); });
                t.addEventListener('keydown', function (e) {
                    var to = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
                    if (to === undefined) return;
                    e.preventDefault();
                    var next = tabs[(to + tabs.length) % tabs.length];
                    select(next, true);
                    next.focus();
                });
            });
            var fromHash = tabs.filter(function (t) { return t.hash === location.hash; })[0];
            select(fromHash || tabs.filter(function (t) { return t.getAttribute('aria-selected') === 'true'; })[0] || tabs[0], !!fromHash);
        }
    });

    /* Trailer player, from anywhere on the page. Without JS the button is a plain link to YouTube/Vimeo. */
    var trailer = document.getElementById('trailer-modal');
    var trailerFrame = trailer && trailer.querySelector('[data-trailer-frame]');
    if (trailer) {
        trailer.querySelector('[data-trailer-close]').addEventListener('click', function () { trailer.close(); });
        trailer.addEventListener('click', function (e) { if (e.target === trailer) trailer.close(); });
        trailer.addEventListener('close', function () { trailerFrame.removeAttribute('src'); }); // stops playback
    }
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-trailer]');
        if (link && trailer && typeof trailer.showModal === 'function') {
            e.preventDefault();
            trailer.querySelector('[data-trailer-heading]').textContent = link.getAttribute('data-trailer-title') + ' · Trailer';
            trailerFrame.title = link.getAttribute('data-trailer-title') + ' trailer';
            trailerFrame.src = link.getAttribute('data-trailer') + '?autoplay=1&rel=0';
            trailer.showModal();
        }
    });

    /* Spotlight carousel, looping: clones of the last two slides go before the first and of the first two after the
       last, so a neighbour always peeks in on both sides; when scrolling settles on a clone it jumps (invisibly) to
       its real twin. The slide nearest the centre is current (dimmed neighbours, active dot). Dots, side arrows and
       autoplay scroll the track. Autoplay pauses on hover/focus, in a background tab, and with reduced motion; the
       active dot's fill (CSS, --spotlight-delay) shows the time left and resets while held. */
    document.querySelectorAll('[data-spotlight]').forEach(function (spot) {
        var track = spot.querySelector('[data-spotlight-track]');
        var real = Array.prototype.slice.call(track.querySelectorAll('[data-spotlight-slide]'));
        var dots = spot.querySelectorAll('[data-spotlight-dot]');
        var pauseBtn = spot.querySelector('[data-spotlight-pause]');
        var count = real.length;
        if (count > 1) {
            var clone = function (s) {
                var c = s.cloneNode(true);
                c.classList.add('is-clone');
                c.setAttribute('aria-hidden', 'true');
                c.removeAttribute('aria-roledescription');
                c.removeAttribute('aria-label');
                c.querySelectorAll('a, button').forEach(function (x) { x.tabIndex = -1; });
                return c;
            };
            var n = Math.min(2, count);
            real.slice(-n).forEach(function (s) { track.insertBefore(clone(s), real[0]); });
            real.slice(0, n).forEach(function (s) { track.appendChild(clone(s)); });
        }
        var all = Array.prototype.slice.call(track.querySelectorAll('[data-spotlight-slide]'));
        var indexOf = function (s) { return parseInt(s.getAttribute('data-spotlight-slide'), 10); };
        var leftFor = function (s) { return s.offsetLeft - (track.clientWidth - s.clientWidth) / 2; };
        var cur = real[0];
        var paused = reduceMotion;
        var held = false; // hover or focus inside
        var timer = null;
        var delay = 7000;
        spot.style.setProperty('--spotlight-delay', delay + 'ms');

        var nearest = function () {
            var mid = track.scrollLeft + track.clientWidth / 2;
            return all.reduce(function (best, s) {
                return Math.abs(s.offsetLeft + s.clientWidth / 2 - mid) < Math.abs(best.offsetLeft + best.clientWidth / 2 - mid) ? s : best;
            });
        };
        var mark = function (s) {
            cur = s;
            all.forEach(function (x) { x.classList.toggle('is-current', x === s); });
            dots.forEach(function (d, i) { if (i === indexOf(s)) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
        };
        var jump = function (left) { // instant, without snapping in between
            track.style.scrollSnapType = 'none';
            track.scrollLeft = left;
            void track.offsetWidth;
            track.style.scrollSnapType = '';
        };
        var go = function (s) {
            if (!s) return;
            track.scrollTo({ left: leftFor(s), behavior: reduceMotion ? 'auto' : 'smooth' });
        };
        var step = function (d) { go(all[all.indexOf(cur) + d]); };
        var schedule = function () {
            clearTimeout(timer);
            var run = !paused && !held && count > 1 && !document.hidden;
            spot.classList.remove('is-running');
            if (!run) return;
            void spot.offsetWidth; // restart the dot's fill
            spot.classList.add('is-running');
            timer = setTimeout(function () { step(1); }, delay);
        };
        var setPaused = function (p) {
            paused = p;
            spot.classList.toggle('is-paused', p);
            if (pauseBtn) pauseBtn.setAttribute('aria-label', p ? 'Play slideshow' : 'Pause slideshow');
            schedule();
        };
        var settle = function () {
            var s = nearest();
            if (s.classList.contains('is-clone')) {
                var twin = real[indexOf(s)];
                jump(track.scrollLeft + twin.offsetLeft - s.offsetLeft);
                s = twin;
            }
            mark(s);
            schedule();
        };

        var frame = 0, settleTimer = 0;
        var update = function () { frame = 0; var s = nearest(); if (s !== cur) mark(s); };
        track.addEventListener('scroll', function () {
            clearTimeout(settleTimer);
            settleTimer = setTimeout(settle, 160);
            if (frame) return;
            frame = requestAnimationFrame(update);
            setTimeout(function () { if (frame) { cancelAnimationFrame(frame); update(); } }, 100); // frames paused (background view)
        }, { passive: true });
        dots.forEach(function (d, i) { d.addEventListener('click', function () { go(real[i]); }); });
        spot.querySelectorAll('[data-spotlight-step]').forEach(function (b) {
            b.addEventListener('click', function () { step(parseInt(b.getAttribute('data-spotlight-step'), 10)); });
        });
        all.forEach(function (s) { s.addEventListener('click', function (e) { if (s !== cur && !e.target.closest('a, button')) go(s); }); }); // click a peeking neighbour
        if (pauseBtn) pauseBtn.addEventListener('click', function () { setPaused(!paused); });
        spot.addEventListener('mouseenter', function () { held = true; schedule(); });
        spot.addEventListener('mouseleave', function () { held = false; schedule(); });
        spot.addEventListener('focusin', function () { held = true; schedule(); });
        spot.addEventListener('focusout', function (e) { if (!spot.contains(e.relatedTarget)) { held = false; schedule(); } });
        document.addEventListener('visibilitychange', schedule);
        window.addEventListener('resize', function () { jump(leftFor(cur)); });

        jump(leftFor(real[0])); // start centred on the first film, the last one peeking in on the left
        mark(real[0]);
        setPaused(paused);
    });

    doc.classList.add('js-ready');
})();
