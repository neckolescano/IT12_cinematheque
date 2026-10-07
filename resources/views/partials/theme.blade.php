{{-- Light theme is the default. Dark is opt-in via [data-theme-toggle] and remembered
     per browser (localStorage). Runs in <head> so the page never flashes the wrong theme. --}}
<script>
(function () {
    var root = document.documentElement, key = 'ccd-theme-v2'; // v2 (yellow/black/white): everyone starts in light again
    try { if (localStorage.getItem(key) === 'dark') root.setAttribute('data-theme', 'dark'); } catch (e) {}
    function sync() {
        var dark = root.getAttribute('data-theme') === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
            b.setAttribute('aria-pressed', String(dark));
            b.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
            b.title = dark ? 'Light mode' : 'Dark mode';
        });
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-theme-toggle]');
        if (!btn) return;
        var dark = root.getAttribute('data-theme') !== 'dark';
        if (dark) root.setAttribute('data-theme', 'dark'); else root.removeAttribute('data-theme');
        try { localStorage.setItem(key, dark ? 'dark' : 'light'); } catch (e) {}
        sync();
    });
    document.addEventListener('DOMContentLoaded', sync);
})();
</script>
