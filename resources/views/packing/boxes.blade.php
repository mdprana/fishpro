@extends('layouts.app')
@section('title','Daftar Box')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('packing.index') }}">Packing</a></li>
    <li class="breadcrumb-item active">Daftar Box</li>
@endsection

@section('content')
@php
    // Warna chip batch dibuat deterministik dari id, supaya perbedaan batch
    // antar box langsung terlihat mata tanpa harus membaca nomornya.
    $palette = ['#2563eb','#16a34a','#f59e0b','#dc2626','#7c3aed','#0891b2','#db2777','#65a30d','#ea580c','#0d9488'];
    $chipColor = fn ($id) => $palette[$id % count($palette)];

    $aktif   = $boxes->getCollection()->where('status', '!=', 'voided');
    $batches = $aktif->flatMap(fn ($b) => $b->sources->pluck('root_batch_id'))->filter()->unique();
    $suppliers = $aktif->flatMap(fn ($b) => $b->sources->pluck('supplier_id'))->filter()->unique();
@endphp

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-box-seam me-2"></i>Daftar Box</h4>
        <p class="text-muted mb-0 small">Satu baris = satu box fisik dengan QR sendiri.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" id="printSelected" disabled>
            <i class="bi bi-printer me-1"></i>Cetak Label (<span id="selCount">0</span>)
        </button>
        <a href="{{ route('packing.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Input Packing</a>
    </div>
</div>

<div class="row g-2 mb-3">
    @foreach([
        ['Box (halaman ini)', $aktif->count(), 'bi-boxes', '#2563eb'],
        ['Total Berat', number_format($aktif->sum('net_weight_kg'), 2) . ' kg', 'bi-speedometer2', '#16a34a'],
        ['Batch Berbeda', $batches->count(), 'bi-diagram-3', '#f59e0b'],
        ['Supplier', $suppliers->count(), 'bi-truck', '#7c3aed'],
        ['Box Campuran', $aktif->where('is_mixed_batch', true)->count(), 'bi-shuffle', '#dc2626'],
    ] as [$label, $value, $icon, $color])
    <div class="col-6 col-lg">
        <div class="form-card h-100">
            <div class="d-flex align-items-center gap-2">
                <i class="bi {{ $icon }} fs-5" style="color:var(--color-neutral-400)"></i>
                <div>
                    <div class="text-body-regular">{{ $label }}</div>
                    <div class="text-heading-brand text-dark">{{ $value }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="form-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label" style="font-size:.75rem">Nomor PO</label>
            <select name="po_id" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach($pos as $po)
                <option value="{{ $po->id }}" @selected(request('po_id') == $po->id)>{{ $po->po_number }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" style="font-size:.75rem">Sesi Packing</label>
            <select name="run_id" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach($runs as $run)
                <option value="{{ $run->id }}" @selected(request('run_id') == $run->id)>{{ $run->run_code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" style="font-size:.75rem">Cari Kode Box</label>
            <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}">
        </div>
        <div class="col-6 col-md-3 d-flex gap-2 align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="mixed_only" value="1"
                       id="mixedOnly" @checked(request()->boolean('mixed_only'))>
                <label class="form-check-label small" for="mixedOnly">Hanya campuran</label>
            </div>
            <button class="btn btn-sm btn-primary">Filter</button>
            <a href="{{ route('packing.boxes') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="form-card">
    @if($boxes->count())
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:34px"><input type="checkbox" class="form-check-input" id="checkAll"></th>
                    <th>No</th>
                    <th>Kode Box</th>
                    <th>QR</th>
                    <th>Produk</th>
                    <th class="text-end">Berat</th>
                    <th>Batch Asal</th>
                    <th>Supplier</th>
                    <th>Tgl Packing</th>
                    <th class="text-center">Status</th>
                    <th style="width:70px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($boxes as $box)
                <tr class="{{ $box->status === 'voided' ? 'opacity-50' : '' }}">
                    <td><input type="checkbox" class="form-check-input box-check" value="{{ $box->id }}"></td>
                    <td class="text-muted small">{{ $box->sequence_no }}</td>
                    <td style="font-family:ui-monospace,monospace;font-size:.72rem">{{ $box->box_code }}</td>
                    <td><code style="font-size:.7rem">{{ $box->qr_code }}</code></td>
                    <td class="small">{{ $box->productType?->name }}</td>
                    <td class="text-end">
                        {{-- Berat bisa dikoreksi langsung; selisihnya tercatat di ledger. --}}
                        <input type="number" class="form-control form-control-sm text-end box-weight"
                               style="max-width:110px;display:inline-block"
                               data-id="{{ $box->id }}" data-original="{{ (float) $box->net_weight_kg }}"
                               value="{{ number_format((float)$box->net_weight_kg, 3, '.', '') }}"
                               step="0.001" min="0.001" @disabled($box->status === 'voided')>
                    </td>
                    <td>
                        @foreach($box->sources as $src)
                        <span class="badge me-1 mb-1 dyn-text" title="{{ $src->rootBatch?->batch_number }}"
                              style="background:{{ $chipColor($src->root_batch_id ?? $src->batch_id) }}22;
                                     --c:{{ $chipColor($src->root_batch_id ?? $src->batch_id) }};
                                     border:1px solid {{ $chipColor($src->root_batch_id ?? $src->batch_id) }}55;
                                     font-size:.62rem">
                            {{ \Illuminate\Support\Str::afterLast($src->rootBatch?->batch_number ?? '-', '-') }}
                            · {{ number_format((float)$src->contributed_kg, 2) }}kg
                        </span>
                        @endforeach
                        @if($box->is_mixed_batch)
                            <span class="badge bg-warning-subtle text-warning" style="font-size:.6rem">campuran</span>
                        @endif
                    </td>
                    <td class="small">{{ $box->sources->pluck('supplier.code')->filter()->unique()->implode(', ') ?: '-' }}</td>
                    <td class="small">{{ $box->packing_date?->format('d/m/y') }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $box->status === 'voided' ? 'danger' : 'success' }}-subtle text-{{ $box->status === 'voided' ? 'danger' : 'success' }}"
                              style="font-size:.64rem">{{ $box->status_label }}</span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('trace.box', $box->qr_code) }}" class="btn btn-sm btn-outline-primary" title="Telusur">
                            <i class="bi bi-diagram-3"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $boxes->links() }}</div>
    @else
    <div class="text-center py-5 text-muted">
        <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
        Belum ada box.
        <div class="mt-3"><a href="{{ route('packing.create') }}" class="btn btn-sm btn-primary">Input Packing</a></div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
const ADJUST_URL = @json(url('packing/box'));
const CSRF       = @json(csrf_token());

const printBtn = document.getElementById('printSelected');
const counter  = document.getElementById('selCount');

function selected() {
    return Array.from(document.querySelectorAll('.box-check:checked')).map(c => c.value);
}
function refresh() {
    const n = selected().length;
    counter.textContent = n;
    printBtn.disabled = n === 0;
}

document.getElementById('checkAll')?.addEventListener('change', function () {
    document.querySelectorAll('.box-check').forEach(c => c.checked = this.checked);
    refresh();
});
document.querySelectorAll('.box-check').forEach(c => c.addEventListener('change', refresh));

printBtn.addEventListener('click', () => {
    const ids = selected();
    if (ids.length) window.location = '{{ route('packing.labels') }}?ids=' + ids.join(',');
});

// Koreksi berat disimpan saat keluar dari kolom, bukan tiap ketukan.
document.querySelectorAll('.box-weight').forEach(input => {
    input.addEventListener('blur', async function () {
        const baru = parseFloat(this.value || 0);
        const lama = parseFloat(this.dataset.original);

        if (baru <= 0) { this.value = lama.toFixed(3); return; }
        if (Math.abs(baru - lama) < 0.001) return;

        this.classList.add('border-warning');

        try {
            const res = await fetch(`${ADJUST_URL}/${this.dataset.id}/adjust`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ net_weight_kg: baru }),
            });
            const data = await res.json();

            if (!res.ok) {
                alert(data.message || 'Gagal menyimpan berat.');
                this.value = lama.toFixed(3);
                this.classList.remove('border-warning');
                return;
            }

            this.dataset.original = data.net_kg;
            this.value = parseFloat(data.net_kg).toFixed(3);
            this.classList.remove('border-warning');
            this.classList.add('border-success');
            setTimeout(() => this.classList.remove('border-success'), 1200);
        } catch (e) {
            alert('Gagal menghubungi server.');
            this.value = lama.toFixed(3);
            this.classList.remove('border-warning');
        }
    });
});

refresh();
</script>
@endpush
