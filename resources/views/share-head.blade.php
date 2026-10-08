@php
    // The built, versioned stylesheet when there is one; the plain path otherwise (e.g. before the assets are built)
    try { $bootstrapCss = mix('/css/bootstrap.css'); } catch (\Throwable $e) { $bootstrapCss = '/css/bootstrap.css'; }
@endphp
<link rel="stylesheet" href="{{ $bootstrapCss }}">
<style>
    main table { font-size: .925rem; }
    @media print { tr { break-inside: avoid; } }
</style>
{{-- Picks the theme before the page paints: the saved choice, else the system preference (and follows it until a choice is made) --}}
<script nonce="{{ $nonce }}">
(function () {
    var root = document.documentElement, KEY = 'share-theme', saved = null;
    var MOON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278"/></svg>';
    var SUN = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0m0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13m8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5M3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8m10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0m-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0m9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707M4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708"/></svg>';
    try { saved = localStorage.getItem(KEY); } catch (e) { /* storage can be blocked */ }
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    function systemTheme() { return media && media.matches ? 'dark' : 'light'; }
    function apply(theme) {
        root.setAttribute('data-bs-theme', theme);
        var button = document.getElementById('theme-toggle');
        // The icon shows what a click switches to: a sun while dark, a moon while light
        if (button) { button.innerHTML = theme === 'dark' ? SUN : MOON; button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false'); }
    }
    apply(saved === 'light' || saved === 'dark' ? saved : systemTheme());
    document.addEventListener('DOMContentLoaded', function () {
        apply(root.getAttribute('data-bs-theme'));
        var button = document.getElementById('theme-toggle');
        if (!button) { return; }
        button.addEventListener('click', function () {
            var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            apply(next);
            try { localStorage.setItem(KEY, next); } catch (e) { /* remembering is optional */ }
        });
    });
    // Paper is white, so print in the light theme (light text would be unreadable) and put the theme back afterwards
    var beforePrint = null;
    window.addEventListener('beforeprint', function () { beforePrint = root.getAttribute('data-bs-theme'); apply('light'); });
    window.addEventListener('afterprint', function () { if (beforePrint) { apply(beforePrint); beforePrint = null; } });
    if (media && media.addEventListener) {
        media.addEventListener('change', function () {
            var choice = null;
            try { choice = localStorage.getItem(KEY); } catch (e) { /* ignore */ }
            if (choice !== 'light' && choice !== 'dark') { apply(systemTheme()); }
        });
    }
})();
</script>
