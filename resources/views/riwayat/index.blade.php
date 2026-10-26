@extends('layouts.app')
@section('title','Riwayat')
@section('breadcrumb')<li class="breadcrumb-item active">Riwayat</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-clock-history me-2"></i>Riwayat</h4>
        <p class="text-muted mb-0 small">Penerimaan bahan baku, pemakaiannya di produksi, dan ledger pergerakan batch.</p>
    </div>
</div>

<ul class="nav nav-tabs mb-4" id="riwayatTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="masuk-tab" data-bs-toggle="tab"
                data-bs-target="#tab-masuk" type="button" role="tab">
            <i class="bi bi-box-arrow-in-down me-1 text-success"></i>
            Penerimaan (Masuk)
            <span class="badge bg-success ms-1">{{ $receivings->total() }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="keluar-tab" data-bs-toggle="tab"
                data-bs-target="#tab-keluar" type="button" role="tab">
            <i class="bi bi-box-arrow-up me-1 text-danger"></i>
            Pemakaian (Keluar)
            <span class="badge bg-danger ms-1">{{ $keluars->total() }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="ledger-tab" data-bs-toggle="tab"
                data-bs-target="#tab-ledger" type="button" role="tab">
            <i class="bi bi-list-columns-reverse me-1 text-primary"></i>
            Ledger Batch
            <span class="badge bg-primary ms-1">{{ $movements->total() }}</span>
        </button>
    </li>
</ul>

<div class="tab-content" id="riwayatTabContent">

    {{-- ══ PENERIMAAN MASUK ══ --}}
    <div class="tab-pane fade show active" id="tab-masuk" role="tabpanel">
        <div class="form-card">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-box-arrow-in-down me-1 text-success"></i>Penerimaan Bahan Baku
                </h6>
                <div class="d-flex gap-2">
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="masuk">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                        <button class="btn btn-sm btn-outline-primary">Filter</button>
                    </form>
                    <a href="{{ route('receiving.create') }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Terima
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Tanggal</th><th>Bahan Baku</th><th>Wadah</th>
                            <th class="text-end">Berat (kg)</th><th>PO</th><th>Supplier</th><th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($receivings as $r)
                        <tr>
                            <td class="text-nowrap small">{{ $r->received_date->format('d M Y') }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="color-dot" style="background:{{ $r->subgroup->group->color ?? '#6b7280' }}"></span>
                                    <div>
                                        <div class="fw-semibold small">{{ $r->subgroup->name }}</div>
                                        <div class="text-muted" style="font-size:.7rem">{{ $r->subgroup->group->name ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="small text-muted">{{ $r->container_type }} ×{{ $r->container_count }}</td>
                            <td class="text-end fw-bold text-success">+{{ number_format($r->weight_kg, 2) }} kg</td>
                            <td class="small">
                                @if($r->purchaseOrder)
                                    <a href="{{ route('po.show', $r->purchase_order_id) }}" class="text-decoration-none">
                                        {{ $r->purchaseOrder->po_number }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $r->supplier_label }}</td>
                            <td class="text-muted small">{{ $r->creator?->name ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada penerimaan
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($receivings->count())
            <div class="d-flex align-items-center justify-content-between mt-2 px-1">
                <div class="small text-muted">
                    Total halaman ini:
                    <strong class="text-success">+{{ number_format($receivings->sum('weight_kg'), 2) }} kg</strong>
                </div>
                <div>{{ $receivings->appends(request()->except('page_in'))->links() }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- ══ PEMAKAIAN KELUAR ══ --}}
    <div class="tab-pane fade" id="tab-keluar" role="tabpanel">
        <div class="form-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-box-arrow-up me-1 text-danger"></i>Pemakaian Bahan Baku ke Produksi
                </h6>
                <a href="{{ route('production.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-gear me-1"></i>Lihat Sesi Produksi
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-custom table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Tanggal</th><th>Sesi Tahap</th><th>Batch</th><th>Bahan Baku</th>
                            <th class="text-end">Berat (kg)</th><th>PO</th><th>Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($keluars as $inp)
                        <tr>
                            <td class="text-nowrap small">{{ optional($inp->record)->process_date?->format('d M Y') ?? '-' }}</td>
                            <td>
                                @if($inp->record)
                                    <a href="{{ route('stage.show', $inp->record) }}"
                                       class="fw-semibold text-decoration-none small"
                                       style="font-family:ui-monospace,monospace">{{ $inp->record->record_code }}</a>
                                    <div class="text-muted" style="font-size:.7rem">{{ $inp->record->stage?->name }}</div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('batch.show', $inp->batch_id) }}"
                                   style="font-family:ui-monospace,monospace;font-size:.7rem">{{ $inp->batch?->short_code }}</a>
                                <div class="text-muted" style="font-size:.66rem">{{ $inp->batch?->supplier?->code }}</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="color-dot" style="background:{{ $inp->batch?->subgroup?->group?->color ?? '#6b7280' }}"></span>
                                    <div>
                                        <div class="fw-semibold small">{{ $inp->batch?->subgroup?->name ?? '-' }}</div>
                                        <div class="text-muted" style="font-size:.7rem">{{ $inp->batch?->subgroup?->group?->name ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end fw-bold text-danger">-{{ number_format($inp->input_weight_kg, 2) }} kg</td>
                            <td class="small">
                                @if($inp->record?->purchaseOrder)
                                    <a href="{{ route('po.show', $inp->record->purchase_order_id) }}" class="text-decoration-none">
                                        {{ $inp->record->purchaseOrder->po_number }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $inp->record?->operator_name ?: '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada riwayat pemakaian
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($keluars->count())
            <div class="d-flex align-items-center justify-content-between mt-2 px-1">
                <div class="small text-muted">
                    Total halaman ini:
                    <strong class="text-danger">-{{ number_format($keluars->sum('input_weight_kg'), 2) }} kg</strong>
                </div>
                <div>{{ $keluars->appends(request()->except('page_out'))->links() }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- ══ LEDGER BATCH ══ --}}
    {{-- Jejak audit permanen: tidak ada baris yang pernah diubah atau dihapus. --}}
    <div class="tab-pane fade" id="tab-ledger" role="tabpanel">
        <div class="form-card">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-list-columns-reverse me-1 text-primary"></i>Ledger Pergerakan Batch
                    </h6>
                    <small class="text-muted">Koreksi ditulis sebagai baris baru, bukan menimpa yang lama.</small>
                </div>
                <form method="GET">
                    <input type="hidden" name="tab" value="ledger">
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua jenis</option>
                        @foreach([
                            'receipt'=>'Penerimaan','stage_consume'=>'Dipakai proses','stage_produce'=>'Hasil proses',
                            'waste'=>'Limbah','pack'=>'Masuk packing','adjustment'=>'Koreksi','reversal'=>'Pembatalan',
                        ] as $k => $v)
                        <option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-custom table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Waktu</th><th>Batch</th><th>Jenis</th>
                            <th class="text-end">Perubahan</th><th class="text-end">Saldo</th>
                            <th>Keterangan</th><th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $m)
                        <tr>
                            <td class="small text-muted text-nowrap">{{ $m->occurred_at?->format('d/m/y H:i') }}</td>
                            <td>
                                <a href="{{ route('batch.show', $m->batch_id) }}"
                                   style="font-family:ui-monospace,monospace;font-size:.7rem">{{ $m->batch?->short_code }}</a>
                                <div class="text-muted" style="font-size:.64rem">{{ $m->batch?->supplier?->code }}</div>
                            </td>
                            <td><span class="badge bg-light text-dark" style="font-size:.64rem">{{ $m->type_label }}</span></td>
                            <td class="text-end fw-semibold {{ $m->direction === 'in' ? 'text-success' : 'text-danger' }}">
                                {{ $m->direction === 'in' ? '+' : '-' }}{{ number_format((float)$m->weight_kg, 3) }}
                            </td>
                            <td class="text-end">{{ number_format((float)$m->balance_after, 3) }}</td>
                            <td class="small text-muted">{{ $m->note ?? '-' }}</td>
                            <td class="small text-muted">{{ $m->creator?->name ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada pergerakan batch.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $movements->appends(request()->except('page_ledger'))->links() }}</div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// Pertahankan tab aktif saat pagination / filter.
const tabFromUrl = new URLSearchParams(window.location.search).get('tab');
if (tabFromUrl) {
    const target = document.getElementById(tabFromUrl + '-tab');
    if (target) new bootstrap.Tab(target).show();
}

document.querySelectorAll('#riwayatTab .nav-link').forEach(btn => {
    btn.addEventListener('shown.bs.tab', e => {
        const url = new URL(window.location);
        url.searchParams.set('tab', e.target.id.replace('-tab', ''));
        history.replaceState(null, '', url);
    });
});
</script>
@endpush
