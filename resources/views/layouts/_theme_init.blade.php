<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20viewBox=%220%200%2024%2024%22%3E%3Cstyle%3Esvg%7Bcolor:black%7D@media(prefers-color-scheme:dark)%7Bsvg%7Bcolor:white%7D%7D%3C/style%3E%3Cpath%20fill=%22currentColor%22%20d=%22M7%2012%20Q14%202%2021%2012%20Q14%2022%207%2012%20Z%20M7%2012%202%207%20v10%20Z%22/%3E%3C/svg%3E">
<script>
(function () {
    var root = document.documentElement;
    var theme = 'light';
    try { if (localStorage.getItem('fp-theme') === 'dark') theme = 'dark'; } catch (e) {}
    var fixedLight = {{ request()->routeIs('batch.labels', 'batch.label', 'packing.labels', 'production.analisa.pdf') ? "true" : "false" }};
    root.dataset.bsTheme = fixedLight ? 'light' : theme;
    function sync() {
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.setAttribute('aria-pressed', String(root.dataset.bsTheme === 'dark'));
        });
    }
    document.addEventListener('DOMContentLoaded', sync);
    document.addEventListener('click', function (event) {
        if (fixedLight || !event.target.closest('[data-theme-toggle]')) return;
        root.dataset.bsTheme = root.dataset.bsTheme === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('fp-theme', root.dataset.bsTheme); } catch (e) {}
        sync();
    });
})();
</script>
