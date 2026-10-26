@props(['size' => 24])
<svg {{ $attributes->merge(['width' => $size, 'height' => $size, 'aria-hidden' => 'true']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
    <path d="M7 12 Q14 2 21 12 Q14 22 7 12 Z M7 12 2 7 v10 Z" />
    <circle cx="17" cy="11" r=".7" />
</svg>
