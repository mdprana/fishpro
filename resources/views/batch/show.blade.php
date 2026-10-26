@extends('layouts.app')
@section('title','Detail Batch')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('batch.index') }}">Batch &amp; Lot</a></li>
    <li class="breadcrumb-item active">{{ $batch->short_code }}</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1" style="font-family:ui-monospace,monospace;font-size:1.05rem">{{ $batch->batch_number }}</h4>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge" style="background:{{ $batch->stage?->color ?? '#64748b' }}22;color:{{ $batch->stage?->color ?? '#475569' }}">
                {{ $batch->stage_label }}
            </span>
            <span class="badge bg-{{ $batch->status_color }}-subtle text-{{ $batch->status_color }}">{{ $batch->status_label }}</span>
            @if($batch->is_multi_source)<span class="badge bg-warning-subtle text-warning">Lot gabungan</span>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('trace.batch', $batch->qr_code) }}" class="btn btn-outline-primary">
            <i class="bi bi-diagram-3 me-1"></i>Telusur
        </a>
        <a href="{{ route('batch.label', $batch) }}" class="btn btn-primary">
            <i class="bi bi-printer me-1"></i>Cetak Label
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="form-card text-center mb-3">
            <div class="qr d-inline-block" data-qr="{{ $batch->qr_url }}" data-size="160"></div>
            <div class="mt-2 fw-bold" style="font-family:ui-monospace,monospace;letter-spacing:.18em">{{ $batch->qr_code }}</div>
            <div class="text-muted" style="font-size:.7rem">Scan untuk membuka halaman telusur</div>
        </div>

        <div class="form-card">
            <h6 class="fw-bold mb-3">Informasi</h6>
            <table class="table table-sm mb-0" style="font-size:.82rem">
                <tr><td class="text-muted">Berat awal</td><td class="text-end fw-semibold">{{ number_format((float)$batch->initial_weight_kg, 3) }} kg</td></tr>
                <tr><td class="text-muted">Sisa</td><td class="text-end fw-semibold text-{{ $batch->status_color }}">{{ number_format((float)$batch->current_weight_kg, 3) }} kg</td></tr>
                <tr><td class="text-muted">Terpakai</td><td class="text-end">{{ number_format($batch->consumed_kg, 3) }} kg</td></tr>
                <tr><td class="text-muted">Supplier</td><td class="text-end">{{ $batch->supplier?->display_name ?? '-' }}</td></tr>
                <tr><td class="text-muted">Bahan / Produk</td><td class="text-end">{{ $batch->subgroup?->name ?? $batch->productType?->name ?? '-' }}</td></tr>
                <tr><td class="text-muted">Wadah</td><td class="text-end">{{ $batch->container_type ?? '-' }}</td></tr>
                <tr><td class="text-muted">Tanggal tahap</td><td class="text-end">{{ $batch->effective_date?->format('d/m/Y') }}</td></tr>
                <tr><td class="text-muted">Nomor PO</td><td class="text-end">
                    @if($batch->purchaseOrder)
                        <a href="{{ route('po.show', $batch->purchaseOrder) }}">{{ $batch->purchaseOrder->po_number }}</a>
                    @else - @endif
                </td></tr>
                @if($batch->parent)
                <tr><td class="text-muted">Lot induk</td><td class="text-end">
                    <a href="{{ route('batch.show', $batch->parent) }}" style="font-family:ui-monospace,monospace;font-size:.72rem">{{ $batch->parent->short_code }}</a>
                </td></tr>
                @endif
                <tr><td class="text-muted">Dibuat oleh</td><td class="text-end">{{ $batch->creator?->name ?? '-' }}</td></tr>
            </table>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="form-card mb-3">
            <h6 class="fw-bold mb-1"><i class="bi bi-list-columns-reverse me-2"></i>Riwayat Pergerakan (Ledger)</h6>
            <p class="text-muted small mb-3">
                Catatan permanen. Koreksi ditulis sebagai baris baru, sehingga tidak ada baris yang pernah diubah atau dihapus.
            </p>
            <div class="table-responsive">
                <table class="table table-custom table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Jenis</th>
                            <th class="text-end">Perubahan</th>
                            <th class="text-end">Saldo</th>
                            <th>Keterangan</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batch->movements as $m)
                        <tr>
                            <td class="small text-muted">{{ $m->occurred_at?->format('d/m/y H:i') }}</td>
                            <td><span class="badge bg-light text-dark" style="font-size:.66rem">{{ $m->type_label }}</span></td>
                            <td class="text-end fw-semibold {{ $m->direction === 'in' ? 'text-success' : 'text-danger' }}">
                                {{ $m->direction === 'in' ? '+' : '-' }}{{ number_format((float)$m->weight_kg, 3) }}
                            </td>
                            <td class="text-end">{{ number_format((float)$m->balance_after, 3) }}</td>
                            <td class="small text-muted">{{ $m->note ?? '-' }}</td>
                            <td class="small text-muted">{{ $m->creator?->name ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-card">
            <h6 class="fw-bold mb-3"><i class="bi bi-diagram-3 me-2"></i>Lot Turunan</h6>
            @if($batch->children->count())
            <div class="row g-2">
                @foreach($batch->children as $child)
                <div class="col-12 col-md-6">
                    <a href="{{ route('batch.show', $child) }}"
                       class="d-block p-2 rounded-3 border text-decoration-none">
                        <div class="fw-semibold text-dark" style="font-family:ui-monospace,monospace;font-size:.72rem">{{ $child->short_code }}</div>
                        <div class="text-muted" style="font-size:.7rem">
                            {{ $child->stage_label }} ·
                            {{ number_format((float)$child->current_weight_kg, 2) }} / {{ number_format((float)$child->initial_weight_kg, 2) }} kg
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-muted small mb-0">Belum ada lot turunan dari batch ini.</p>
            @endif
        </div>
    </div>
</div>

@include('partials._qr')
@endsection
