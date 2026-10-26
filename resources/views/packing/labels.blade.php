@extends('layouts.app')
@section('title','Label Box')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('packing.boxes') }}">Daftar Box</a></li>
    <li class="breadcrumb-item active">Label</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 no-print flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-tag me-2"></i>Label Box</h4>
        <p class="text-muted mb-0 small">{{ $boxes->count() }} label. Satu box, satu QR.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('packing.boxes') }}" class="btn btn-outline-secondary">Kembali</a>
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
    </div>
</div>

<div class="box-label-grid">
    @foreach($boxes as $box)
    <div class="box-label">
        <div class="box-label-top">
            <span class="fw-bold">FishPro</span>
            <span class="box-seq">#{{ $box->sequence_no }}</span>
        </div>

        <div class="qr mx-auto my-1" data-qr="{{ $box->qr_url }}" data-size="96"></div>

        <div class="box-code">{{ $box->box_code }}</div>

        <table class="box-table">
            <tr><td>Produk</td><td><strong>{{ $box->productType?->name }}</strong></td></tr>
            <tr><td>Netto</td><td><strong>{{ rtrim(rtrim(number_format((float)$box->net_weight_kg, 3, ',', '.'), '0'), ',') }} kg</strong></td></tr>
            <tr><td>PO</td><td>{{ $box->purchaseOrder?->po_number }}</td></tr>
            <tr><td>Supplier</td><td>{{ $box->sources->pluck('supplier.code')->filter()->unique()->implode(', ') ?: '-' }}</td></tr>
            <tr><td>Packing</td><td>{{ $box->packing_date?->format('d/m/Y') }}</td></tr>
        </table>

        @if($box->is_mixed_batch)
        <div class="box-mixed">campuran {{ $box->sources->count() }} batch</div>
        @endif

        <div class="box-token">{{ $box->qr_code }}</div>
    </div>
    @endforeach
</div>

@include('partials._qr')
@endsection

@push('styles')
<style>
.box-label-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;}
.box-label{border:1.5px dashed #94a3b8;border-radius:5px;padding:7px;background:#fff;text-align:center;
           page-break-inside:avoid;break-inside:avoid;}
.box-label-top{display:flex;justify-content:space-between;align-items:center;
               border-bottom:1px solid #1a3a5c;padding-bottom:3px;font-size:.72rem;color:#1a3a5c;}
.box-seq{font-size:.66rem;color:#64748b;}
.box-code{font-family:ui-monospace,'Consolas',monospace;font-weight:700;font-size:.66rem;
          word-break:break-all;line-height:1.2;margin-bottom:4px;}
.box-table{width:100%;font-size:.63rem;border-collapse:collapse;text-align:left;}
.box-table td{padding:0;vertical-align:top;}
.box-table td:first-child{color:#64748b;width:52px;}
.box-mixed{margin-top:3px;font-size:.58rem;background:#fef3c7;color:#92400e;border-radius:99px;padding:1px 5px;display:inline-block;}
.box-token{margin-top:4px;padding-top:3px;border-top:1px dotted #cbd5e1;
           font-family:ui-monospace,'Consolas',monospace;font-size:.74rem;letter-spacing:.14em;font-weight:700;}

@media (max-width:768px){ .box-label-grid{grid-template-columns:repeat(2,1fr);} }
@media (max-width:480px){ .box-label-grid{grid-template-columns:1fr;} }

@media print{
    #sidebar,.topbar,.no-print,.breadcrumb{display:none !important;}
    #main{margin-left:0 !important;}
    .page-content{padding:0 !important;}
    body{background:#fff;}
    .box-label-grid{grid-template-columns:repeat(3,1fr);gap:4px;}
    .box-label{border:1px solid #333;}
    @page{margin:6mm;}
}
</style>
@endpush
