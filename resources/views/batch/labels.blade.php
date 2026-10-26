@extends('layouts.app')
@section('title','Cetak Label Batch')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('batch.index') }}">Batch &amp; Lot</a></li>
    <li class="breadcrumb-item active">Cetak Label</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 no-print flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-upc-scan me-2"></i>Label Batch</h4>
        <p class="text-muted mb-0 small">
            {{ $batches->count() }} label siap cetak. Tempelkan pada palet/keranjang yang sesuai.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('batch.index') }}" class="btn btn-outline-secondary">Kembali</a>
        <button class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Cetak
        </button>
    </div>
</div>

<div class="alert alert-info py-2 px-3 small no-print">
    <i class="bi bi-info-circle me-1"></i>
    QR berisi alamat halaman telusur, sehingga kamera HP bawaan pun langsung membukanya.
</div>

<div class="label-grid">
    @foreach($batches as $b)
    <div class="label-sheet">
        <div class="label-head">
            <span class="label-brand">FishPro</span>
            <span class="label-stage">{{ $b->stage_label }}</span>
        </div>

        <div class="label-body">
            <div class="qr" data-qr="{{ $b->qr_url }}" data-size="118"></div>

            <div class="label-info">
                <div class="label-number">{{ $b->batch_number }}</div>
                <table class="label-table">
                    <tr><td>Supplier</td><td><strong>{{ $b->supplier?->code ?? '-' }}</strong> {{ $b->supplier?->name }}</td></tr>
                    <tr><td>Bahan</td><td>{{ $b->subgroup?->name ?? ($b->productType?->name ?? '-') }}</td></tr>
                    <tr><td>Berat</td><td><strong>{{ rtrim(rtrim(number_format((float)$b->initial_weight_kg, 3, ',', '.'), '0'), ',') }} kg</strong></td></tr>
                    <tr><td>Wadah</td><td>{{ $b->container_type ?? '-' }}</td></tr>
                    <tr><td>Tgl Bongkar</td><td>{{ $b->effective_date?->format('d/m/Y') }}</td></tr>
                    <tr><td>No. PO</td><td>{{ $b->purchaseOrder?->po_number ?? '-' }}</td></tr>
                </table>
            </div>
        </div>

        <div class="label-foot">{{ $b->qr_code }}</div>
    </div>
    @endforeach
</div>

@include('partials._qr')
@endsection

@push('styles')
<style>
.label-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;}
.label-sheet{border:1.5px dashed #94a3b8;border-radius:6px;padding:10px;background:#fff;
             page-break-inside:avoid;break-inside:avoid;}
.label-head{display:flex;justify-content:space-between;align-items:center;
            border-bottom:1.5px solid #1a3a5c;padding-bottom:4px;margin-bottom:8px;}
.label-brand{font-weight:800;font-size:.9rem;color:#1a3a5c;}
.label-stage{font-size:.66rem;background:#1a3a5c;color:#fff;padding:2px 7px;border-radius:99px;}
.label-body{display:flex;gap:10px;align-items:flex-start;}
.label-info{flex:1;min-width:0;}
.label-number{font-family:ui-monospace,'Consolas',monospace;font-weight:700;font-size:.78rem;
              word-break:break-all;line-height:1.25;margin-bottom:5px;color:#0f172a;}
.label-table{width:100%;font-size:.68rem;border-collapse:collapse;}
.label-table td{padding:1px 0;vertical-align:top;}
.label-table td:first-child{color:#64748b;width:64px;white-space:nowrap;}
.label-foot{margin-top:6px;padding-top:4px;border-top:1px dotted #cbd5e1;text-align:center;
            font-family:ui-monospace,'Consolas',monospace;font-size:.82rem;letter-spacing:.16em;font-weight:700;}

@media (max-width:576px){ .label-grid{grid-template-columns:1fr;} }

@media print{
    /* Sisakan hanya lembar label; sisanya milik aplikasi, bukan milik kertas. */
    #sidebar,.topbar,.no-print,.alert,.breadcrumb{display:none !important;}
    #main{margin-left:0 !important;}
    .page-content{padding:0 !important;}
    body{background:#fff;}
    .label-grid{grid-template-columns:repeat(2,1fr);gap:6px;}
    .label-sheet{border:1px solid #333;}
    @page{margin:8mm;}
}
</style>
@endpush
