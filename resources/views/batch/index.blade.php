@extends('layouts.app')
@section('title','Batch & Lot')
@section('breadcrumb')<li class="breadcrumb-item active">Batch &amp; Lot</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-box2 me-2"></i>Batch &amp; Lot</h4>
        <p class="text-muted mb-0 small">Setiap keranjang, palet, dan lot hasil proses beserta sisa isinya.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('trace.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-upc-scan me-1"></i>Telusur
        </a>
        <button class="btn btn-primary" id="printSelected" disabled>
            <i class="bi bi-printer me-1"></i>Cetak Label (<span id="selCount">0</span>)
        </button>
    </div>
</div>

<div class="form-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Nomor PO</label>
            <select name="po_id" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach($pos as $po)
                <option value="{{ $po->id }}" @selected(request('po_id') == $po->id)>{{ $po->po_number }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Supplier</label>
            <select name="supplier_id" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach($suppliers as $s)
                <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->code }} - {{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Bahan</label>
            <select name="subgroup_id" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach($groups as $g)
                <optgroup label="{{ $g->name }}">
                    @foreach($g->subgroups as $sub)
                    <option value="{{ $sub->id }}" @selected(request('subgroup_id') == $sub->id)>{{ $sub->name }}</option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Tahap</label>
            <select name="stage" class="form-select form-select-sm">
                <option value="">Semua</option>
                <option value="receiving" @selected(request('stage') === 'receiving')>Bahan Baku (Bongkar)</option>
                @foreach($stages as $st)
                <option value="{{ $st->code }}" @selected(request('stage') === $st->code)>{{ $st->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach(['available'=>'Tersedia','partial'=>'Terpakai sebagian','consumed'=>'Habis','packed'=>'Dipacking','voided'=>'Dibatalkan'] as $k=>$v)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Cari Batch</label>
            <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="Nomor / QR">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Dari Tanggal</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Sampai</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            <a href="{{ route('batch.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="form-card">
    @if($batches->count())
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:34px"><input type="checkbox" class="form-check-input" id="checkAll"></th>
                    <th>Nomor Batch</th>
                    <th>Tahap</th>
                    <th>Supplier</th>
                    <th>Isi</th>
                    <th class="text-end">Awal</th>
                    <th class="text-end">Sisa</th>
                    <th>Tanggal</th>
                    <th>PO</th>
                    <th class="text-center">Status</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($batches as $b)
                <tr class="{{ $b->status === 'voided' ? 'opacity-50' : '' }}">
                    <td><input type="checkbox" class="form-check-input batch-check" value="{{ $b->id }}"></td>
                    <td>
                        <a href="{{ route('batch.show', $b) }}" class="fw-semibold text-decoration-none"
                           style="font-family:ui-monospace,monospace;font-size:.76rem"
                           title="{{ $b->batch_number }}">{{ $b->batch_number }}</a>
                        @if($b->is_multi_source)
                            <span class="badge bg-warning-subtle text-warning ms-1" style="font-size:.6rem">gabungan</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge" style="background:{{ $b->stage?->color ?? '#64748b' }}22;color:{{ $b->stage?->color ?? '#475569' }};font-size:.68rem">
                            {{ $b->stage_label }}
                        </span>
                    </td>
                    <td class="small">{{ $b->supplier?->code ?? '-' }}</td>
                    <td class="small">{{ $b->subgroup?->name ?? $b->productType?->name ?? '-' }}</td>
                    <td class="text-end small text-muted">{{ number_format((float)$b->initial_weight_kg, 2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format((float)$b->current_weight_kg, 2) }}</td>
                    <td class="small">{{ $b->effective_date?->format('d/m/y') }}</td>
                    <td class="small">{{ $b->purchaseOrder?->po_number ?? '-' }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $b->status_color }}-subtle text-{{ $b->status_color }}" style="font-size:.66rem">
                            {{ $b->status_label }}
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('batch.label', $b) }}" class="btn btn-sm btn-outline-secondary" title="Cetak label">
                            <i class="bi bi-printer"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $batches->links() }}</div>
    @else
    <div class="text-center py-5 text-muted">
        <i class="bi bi-box2 fs-2 d-block mb-2"></i>
        Belum ada batch. Batch terbit otomatis saat bahan baku diterima.
        <div class="mt-3"><a href="{{ route('receiving.create') }}" class="btn btn-sm btn-primary">Terima Bahan</a></div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
const printBtn = document.getElementById('printSelected');
const counter  = document.getElementById('selCount');

function selected() {
    return Array.from(document.querySelectorAll('.batch-check:checked')).map(c => c.value);
}

function refresh() {
    const n = selected().length;
    counter.textContent = n;
    printBtn.disabled = n === 0;
}

document.getElementById('checkAll')?.addEventListener('change', function () {
    document.querySelectorAll('.batch-check').forEach(c => c.checked = this.checked);
    refresh();
});
document.querySelectorAll('.batch-check').forEach(c => c.addEventListener('change', refresh));

printBtn.addEventListener('click', () => {
    const ids = selected();
    if (ids.length) window.location = '{{ route('batch.labels') }}?ids=' + ids.join(',');
});

refresh();
</script>
@endpush
