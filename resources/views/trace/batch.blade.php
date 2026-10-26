@extends('layouts.app')
@section('title','Telusur Batch')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('trace.index') }}">Telusur</a></li>
    <li class="breadcrumb-item active">{{ $batch->short_code }}</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-diagram-3 me-2"></i>Telusur Batch</h4>
        <p class="text-muted mb-0 small">Dari mana batch ini berasal, dan mengalir ke mana isinya.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('trace.index') }}" class="btn btn-outline-secondary"><i class="bi bi-upc-scan me-1"></i>Scan Lagi</a>
        <a href="{{ route('batch.show', $batch) }}" class="btn btn-outline-primary">Detail &amp; Ledger</a>
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
    </div>
</div>

<div class="form-card mb-3">
    <div class="d-flex gap-3 flex-wrap align-items-start">
        <div class="qr" data-qr="{{ $batch->qr_url }}" data-size="120"></div>
        <div class="flex-grow-1" style="min-width:240px">
            <div class="fw-bold mb-2" style="font-family:ui-monospace,monospace">{{ $batch->batch_number }}</div>
            <div class="row g-2" style="font-size:.82rem">
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Kode Supplier</div>
                    <div class="fw-bold">{{ $batch->rootBatch?->supplier?->code ?? $batch->supplier?->code ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Supplier</div>
                    <div>{{ $batch->rootBatch?->supplier?->name ?? $batch->supplier?->name ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Tgl Bongkar</div>
                    <div>{{ optional($batch->rootBatch?->effective_date)->format('d/m/Y') ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Tahap Saat Ini</div>
                    <div>{{ $batch->stage_label }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Berat Awal</div>
                    <div>{{ number_format((float)$batch->initial_weight_kg, 3) }} kg</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Sisa</div>
                    <div class="fw-semibold text-{{ $batch->status_color }}">{{ number_format((float)$batch->current_weight_kg, 3) }} kg</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Nomor PO</div>
                    <div>{{ $batch->purchaseOrder?->po_number ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Bahan / Produk</div>
                    <div>{{ $batch->subgroup?->name ?? $batch->productType?->name ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="form-card h-100">
            <h6 class="fw-bold mb-3"><i class="bi bi-arrow-up-circle me-2"></i>Asal (ke atas)</h6>
            @php
                $rantai = [];
                $node = $batch;
                while ($node) { $rantai[] = $node; $node = $node->parent; }
                $rantai = array_reverse($rantai);
            @endphp
            <div class="d-flex flex-column gap-2">
                @foreach($rantai as $i => $n)
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill dyn-text" style="background:{{ $n->stage?->color ?? '#64748b' }}22;--c:{{ $n->stage?->color ?? '#64748b' }};min-width:26px">{{ $i + 1 }}</span>
                    <div class="flex-grow-1 p-2 rounded-3 border {{ $n->id === $batch->id ? 'border-primary bg-primary-subtle' : '' }}">
                        <div style="font-family:ui-monospace,monospace;font-size:.72rem">{{ $n->batch_number }}</div>
                        <div class="text-muted" style="font-size:.7rem">
                            {{ $n->stage_label }} · {{ $n->effective_date?->format('d/m/Y') }} ·
                            {{ number_format((float)$n->initial_weight_kg, 2) }} kg
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @if($batch->is_multi_source)
            <div class="alert alert-warning py-2 px-3 small mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Lot ini hasil gabungan beberapa batch induk. Rantai di atas hanya menampilkan
                induk utama; rincian kontribusi tiap batch ada di laporan penelusuran.
            </div>
            @endif
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="form-card h-100">
            <h6 class="fw-bold mb-3"><i class="bi bi-arrow-down-circle me-2"></i>Mengalir Ke (ke bawah)</h6>
            @if($batch->children->count())
            <div class="d-flex flex-column gap-2">
                @foreach($batch->children as $child)
                <a href="{{ route('trace.batch', $child->qr_code) }}"
                   class="d-block p-2 rounded-3 border text-decoration-none">
                    <div class="text-dark" style="font-family:ui-monospace,monospace;font-size:.72rem">{{ $child->batch_number }}</div>
                    <div class="text-muted" style="font-size:.7rem">
                        {{ $child->stage_label }} · {{ $child->effective_date?->format('d/m/Y') }} ·
                        {{ number_format((float)$child->initial_weight_kg, 2) }} kg
                    </div>
                </a>
                @endforeach
            </div>
            @else
            <p class="text-muted small mb-0">
                Batch ini belum diproses lebih lanjut.
                @if($batch->is_available)
                    Sisanya {{ number_format((float)$batch->current_weight_kg, 2) }} kg masih tersedia.
                @endif
            </p>
            @endif
        </div>
    </div>
</div>

@include('partials._qr')
@endsection

@push('styles')
<style>
@media print{
    #sidebar,.topbar,.no-print,.breadcrumb{display:none !important;}
    #main{margin-left:0 !important;}
    .page-content{padding:0 !important;}
    body{background:#fff;}
}
</style>
@endpush
