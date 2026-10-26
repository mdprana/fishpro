@unless(request()->routeIs('batch.labels', 'batch.label', 'packing.labels', 'production.analisa.pdf'))
<button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Ganti tema" aria-pressed="false" title="Ganti tema"><x-icon name="moon" class="theme-moon" size="18" /><x-icon name="sun" class="theme-sun" size="18" /></button>
@endunless
