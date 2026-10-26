@extends('layouts.app')
@section('title','Packing')
@section('breadcrumb')<li class="breadcrumb-item active">Packing</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-box-seam me-2"></i>Packing</h4>
        <p class="text-muted mb-0 small">Sesi packing beserta jumlah box yang dihasilkan.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('packing.boxes') }}" class="btn btn-outline-secondary">Daftar Box</a>
        <a href="{{ route('packing.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Input Packing</a>
    </div>
</div>

<div class="form-card">
    @if($runs->count())
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Kode Sesi</th><th>Tanggal</th><th>PO</th><th>Produk</th>
                    <th>Mode</th><th class="text-end">Box</th><th class="text-end">Total Berat</th>
                    <th>Operator</th><th style="width:120px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($runs as $run)
                <tr>
                    <td style="font-family:ui-monospace,monospace;font-size:.76rem">{{ $run->run_code }}</td>
                    <td class="small">{{ $run->packing_date->format('d/m/Y') }}</td>
                    <td class="small">{{ $run->purchaseOrder?->po_number }}</td>
                    <td>
                        <span class="badge" style="background:{{ $run->productType?->effective_color }}22;color:{{ $run->productType?->effective_color }};font-size:.68rem">
                            {{ $run->productType?->name }}
                        </span>
                    </td>
                    <td class="small text-muted">{{ $run->mode === 'bulk' ? 'Bulk' : 'Timbang per box' }}</td>
                    <td class="text-end fw-semibold">{{ $run->box_count }}</td>
                    <td class="text-end">{{ number_format((float)($run->total_kg ?? 0), 2) }} kg</td>
                    <td class="small text-muted">{{ $run->operator_name ?: '-' }}</td>
                    <td class="text-end">
                        <a href="{{ route('packing.boxes', ['run_id' => $run->id]) }}" class="btn btn-sm btn-outline-secondary" title="Lihat box">
                            <i class="bi bi-list-ul"></i>
                        </a>
                        <a href="{{ route('packing.labels', ['run_id' => $run->id]) }}" class="btn btn-sm btn-outline-primary" title="Cetak label">
                            <i class="bi bi-printer"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $runs->links() }}</div>
    @else
    <div class="text-center py-5 text-muted">
        <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
        Belum ada sesi packing.
        <div class="mt-3"><a href="{{ route('packing.create') }}" class="btn btn-sm btn-primary">Input Packing</a></div>
    </div>
    @endif
</div>
@endsection
