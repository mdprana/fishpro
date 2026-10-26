@props(['name', 'size' => 16])
@php
$paths = match ($name) {
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'production' => '<path d="M3 21V10l6 4V10l6 4V4h4a2 2 0 0 1 2 2v15H3Z"/><path d="M7 17h2M12 17h2M17 17h1"/>',
    'report' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-6"/>',
    'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3"/>',
    'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
    'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
    'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
    'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'fish' => '<path d="M3 12c3-5 9-6 13-3l5-3v12l-5-3c-4 3-10 2-13-3Z"/><circle cx="9" cy="11" r=".5" fill="currentColor"/>',
    default => '<circle cx="12" cy="12" r="9"/>',
};
@endphp
<svg {{ $attributes->merge(['width' => $size, 'height' => $size, 'aria-hidden' => 'true']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false">{!! $paths !!}</svg>
