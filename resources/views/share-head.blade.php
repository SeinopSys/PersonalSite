@php
    // The built, versioned stylesheet when there is one; the plain path otherwise (e.g. before the assets are built)
    try { $bootstrapCss = mix('/css/bootstrap.css'); } catch (\Throwable $e) { $bootstrapCss = '/css/bootstrap.css'; }
@endphp
<link rel="stylesheet" href="{{ $bootstrapCss }}">
<style>main table { font-size: .925rem; }</style>
{{-- Picks the theme before the page paints: the saved choice, else the system preference (and follows it until a choice is made) --}}
<script nonce="{{ $nonce }}">
(function () {
    var root = document.documentElement, KEY = 'share-theme', saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) { /* storage can be blocked */ }
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    function systemTheme() { return media && media.matches ? 'dark' : 'light'; }
    function apply(theme) {
        root.setAttribute('data-bs-theme', theme);
        var button = document.getElementById('theme-toggle');
        if (button) { button.textContent = theme === 'dark' ? '\u2600' : '\u263E'; button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false'); }
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
    if (media && media.addEventListener) {
        media.addEventListener('change', function () {
            var choice = null;
            try { choice = localStorage.getItem(KEY); } catch (e) { /* ignore */ }
            if (choice !== 'light' && choice !== 'dark') { apply(systemTheme()); }
        });
    }
})();
</script>
