@extends('layouts.app')
@section('title','Telusur Box')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('trace.index') }}">Telusur</a></li>
    <li class="breadcrumb-item active">{{ $box->box_code }}</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-box-seam me-2"></i>Kartu Telusur Box</h4>
        <p class="text-muted mb-0 small">Asal-usul seluruh isi box ini, dari packing kembali ke bongkar.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('trace.index') }}" class="btn btn-outline-secondary"><i class="bi bi-upc-scan me-1"></i>Scan Lagi</a>
        <a href="{{ route('packing.labels', ['ids' => $box->id]) }}" class="btn btn-outline-primary">
            <i class="bi bi-tag me-1"></i>Label Box
        </a>
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak Kartu</button>
    </div>
</div>

<div class="form-card mb-3">
    <div class="d-flex gap-3 flex-wrap align-items-start">
        <div class="qr" data-qr="{{ $box->qr_url }}" data-size="130"></div>
        <div class="flex-grow-1" style="min-width:250px">
            <div class="fw-bold mb-2" style="font-family:ui-monospace,monospace;font-size:1rem">{{ $box->box_code }}</div>
            <div class="row g-2" style="font-size:.85rem">
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Produk</div>
                    <div class="fw-semibold">{{ $trace['box']['product'] ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Berat Netto</div>
                    <div class="fw-bold">{{ number_format($trace['box']['net_kg'], 3, ',', '.') }} kg</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Nomor PO</div>
                    <div>{{ $trace['box']['po_number'] ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Buyer</div>
                    <div>{{ $trace['box']['buyer'] ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Tgl Packing</div>
                    <div>{{ optional($trace['box']['packing_date'])->format('d/m/Y') ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted" style="font-size:.7rem">Status</div>
                    <div>{{ $trace['box']['status'] }}</div>
                </div>
                <div class="col-12 col-md-6">
                    @if($trace['is_mixed'])
                    <span class="badge bg-warning-subtle text-warning">
                        <i class="bi bi-diagram-2 me-1"></i>
                        Box campuran: {{ $trace['batch_count'] }} batch, {{ $trace['supplier_count'] }} supplier
                    </span>
                    @else
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-check-circle me-1"></i>Batch tunggal
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="form-card mb-3">
    <h6 class="fw-bold mb-1">Asal Bahan Baku</h6>
    <p class="text-muted small mb-3">
        Setiap baris adalah satu batch induk yang isinya masuk ke box ini, beserta tanggal tiap tahap yang dilaluinya.
    </p>

    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:34px">#</th>
                    <th>Batch</th>
                    <th>Kode Supplier</th>
                    <th>Tgl Bongkar</th>
                    <th>Tgl Potong</th>
                    <th>Tgl Trimming</th>
                    <th>Tgl Potong Steak</th>
                    <th>Tgl Packing</th>
                    <th class="text-end">Kontribusi</th>
                    <th class="text-end">%</th>
                </tr>
            </thead>
            <tbody>
                @forelse($trace['sources'] as $i => $src)
                <tr>
                    <td class="text-muted">{{ $i + 1 }}</td>
                    <td>
                        <a href="{{ route('trace.batch', $src['root_qr']) }}"
                           style="font-family:ui-monospace,monospace;font-size:.72rem"
                           title="{{ $src['root_batch_number'] }}">{{ $src['root_batch_number'] }}</a>
                        <div class="text-muted" style="font-size:.66rem">{{ $src['subgroup'] }}</div>
                    </td>
                    <td>
                        <span class="badge bg-dark-subtle text-dark fw-bold">{{ $src['supplier_code'] }}</span>
                        <div class="text-muted" style="font-size:.66rem">{{ $src['supplier_name'] }}</div>
                    </td>
                    <td class="small">{{ optional($src['tgl_bongkar'])->format('d/m/Y') ?? '-' }}</td>
                    <td class="small">
                        {{ optional($src['tgl_potong'])->format('d/m/Y') ?? '-' }}
                        @if($src['record_potong'])<div class="text-muted" style="font-size:.62rem">{{ $src['record_potong'] }}</div>@endif
                    </td>
                    <td class="small">
                        {{ optional($src['tgl_trimming'])->format('d/m/Y') ?? '-' }}
                        @if($src['record_trimming'])<div class="text-muted" style="font-size:.62rem">{{ $src['record_trimming'] }}</div>@endif
                    </td>
                    <td class="small">
                        {{ optional($src['tgl_steak'])->format('d/m/Y') ?? '-' }}
                        @if($src['record_steak'])<div class="text-muted" style="font-size:.62rem">{{ $src['record_steak'] }}</div>@endif
                    </td>
                    <td class="small">{{ optional($src['tgl_packing'])->format('d/m/Y') ?? '-' }}</td>
                    <td class="text-end fw-semibold">{{ number_format($src['contributed_kg'], 3, ',', '.') }} kg</td>
                    <td class="text-end">{{ number_format($src['contribution_pct'], 1, ',', '.') }}%</td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        Komposisi box ini belum tercatat.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="form-card no-print">
    <h6 class="fw-bold mb-3">Lot Langsung Sebelum Packing</h6>
    <div class="row g-2">
        @foreach($box->sources as $source)
        <div class="col-12 col-md-6">
            <a href="{{ route('trace.batch', $source->batch->qr_code) }}"
               class="d-block p-2 rounded-3 border text-decoration-none">
                <div class="text-dark" style="font-family:ui-monospace,monospace;font-size:.72rem">
                    {{ $source->batch->batch_number }}
                </div>
                <div class="text-muted" style="font-size:.7rem">
                    {{ number_format((float)$source->contributed_kg, 3) }} kg
                    ({{ number_format((float)$source->contribution_pct, 1) }}%)
                    · {{ $source->supplier?->code ?? '-' }}
                </div>
            </a>
        </div>
        @endforeach
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
    .table{font-size:.72rem;}
}
</style>
@endpush
