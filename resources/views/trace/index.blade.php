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
    <div class="alert alert-danger py-2 px-3 small">{{ $message }}</div>
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
    document.getElementById('resolveCode').value = code;
    document.getElementById('resolveForm').submit();
};

// Riwayat scan hanya kenyamanan per-perangkat, jadi cukup di localStorage.
// Dibungkus try/catch karena mode privat / site data diblokir bisa melempar.
function remember(code) {
    try {
        const list = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]')
            .filter(c => c !== code);
        list.unshift(code);
        localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, 8)));
    } catch (e) { /* riwayat bukan data penting, abaikan saja */ }
}

function renderRecent() {
    let list = [];
    try { list = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch (e) { return; }
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
