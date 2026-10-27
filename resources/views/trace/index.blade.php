@extends('layouts.app')
@section('title','Telusur')
@section('breadcrumb')<li class="breadcrumb-item active">Telusur</li>@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-12 col-lg-7">
    <div class="text-center mb-4">
        <h4 class="fw-bold mb-1"><i class="bi bi-upc-scan me-2"></i>Telusur Asal Bahan Baku</h4>
        <p class="text-muted mb-0 small">
            Scan QR pada label batch atau box packing untuk melihat asal-usulnya:
            supplier, tanggal bongkar, potong, trimming, steak, hingga packing.
        </p>
    </div>

    @error('code')
    <div class="alert alert-danger py-2 px-3 small" role="alert">{{ $message }}</div>
    @enderror

    @include('partials._scanner', [
        'scannerTitle' => 'Scan Kode Batch atau Box',
        'scannerHint'  => 'Kode akan dikenali otomatis, baik itu nomor batch, token QR, maupun kode box.',
    ])

    <div class="form-card">
        <h6 class="fw-bold mb-2"><i class="bi bi-clock-history me-2"></i>Terakhir Ditelusuri</h6>
        <div id="recentList" class="d-flex flex-wrap gap-2">
            <span class="text-muted small">Belum ada riwayat di perangkat ini.</span>
        </div>
    </div>
</div>
</div>

<form id="resolveForm" method="POST" action="{{ route('trace.resolve') }}" class="d-none">
    @csrf
    <input type="hidden" name="code" id="resolveCode">
</form>
@endsection

@push('scripts')
<script>
const RECENT_KEY = 'fishpro.trace.recent';

window.onScanCode = function (code) {
    window.scanFeedback('Mencari ' + code + '…', 'muted');
    remember(code);
    if (window.scannerStop) window.scannerStop();
    document.getElementById('resolveCode').value = code;
    document.getElementById('resolveForm').submit();
};

function loadRecent() {
    try {
        const raw = localStorage.getItem(RECENT_KEY) || '[]';
        if (raw.length > 32768) return [];
        const list = JSON.parse(raw);
        if (!Array.isArray(list) || list.length > 8
            || !list.every(c => typeof c === 'string' && c.length > 0 && c.length <= 512 && !/[\x00-\x1f\x7f]/.test(c))) return [];
        return list;
    } catch (e) { return []; }
}

function remember(code) {
    try {
        const list = loadRecent().filter(c => c !== code);
        list.unshift(code);
        localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, 8)));
    } catch (e) { /* riwayat bukan data penting, abaikan saja */ }
}

function renderRecent() {
    const list = loadRecent();
    if (!list.length) return;

    const wrap = document.getElementById('recentList');
    wrap.innerHTML = '';
    list.forEach(code => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary';
        btn.style.fontFamily = 'ui-monospace,monospace';
        btn.style.fontSize = '.74rem';
        btn.textContent = code;
        btn.addEventListener('click', () => window.onScanCode(code));
        wrap.appendChild(btn);
    });
}

renderRecent();
</script>
@endpush
