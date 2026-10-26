@extends('layouts.app')
@section('title','Bahan Baku')
@section('breadcrumb')<li class="breadcrumb-item active">Bahan Baku</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-box-arrow-in-down me-2"></i>Bahan Baku</h4>
        <p class="text-muted mb-0 small">Stok & riwayat penerimaan</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('receiving.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Terima Bahan</a>
    </div>
</div>

{{-- Stok per Subgroup --}}
@if($stocks->count() > 0)
<div class="form-card mb-4">
    <h6 class="fw-bold mb-3">🏪 Stok Saat Ini</h6>
    @php $grouped = $stocks->groupBy(fn($s) => $s->subgroup->group->name ?? 'Lainnya'); @endphp
    @foreach($grouped as $groupName => $groupStocks)
    <div class="mb-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing:.05em;font-size:.7rem">
            {{ $groupName }}
        </div>
        <div class="row g-2">
            @foreach($groupStocks as $s)
            @php
                $stock = (float)$s->current_stock_kg;
                $alert = $stock < 10 ? 'danger' : ($stock < 50 ? 'warning' : 'success');
                $color = $s->subgroup->group->color ?? '#6b7280';
            @endphp
            <div class="col-6 col-md-4 col-xl-3">
                <div class="p-2 rounded-3 border-start border-3 border-{{ $alert }} bg-{{ $alert }} bg-opacity-5">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="color-dot" style="background:{{ $color }}"></span>
                        <span class="fw-semibold small">{{ $s->subgroup->name }}</span>
                    </div>
                   <div class="fw-bold text-dark" style="font-size:1.3rem">
    {{ number_format($stock,1) }}
    <span class="text-secondary small fw-normal"> kg</span>
</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- CSV Import --}}
<div class="form-card mb-4">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h6 class="fw-bold mb-1"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Import CSV</h6>
            <small class="text-muted">Kolom: po_number, subgroup, supplier (kode/nama), weight_kg, container_type, container_count, split_per_container, received_date, doc_number</small>
        </div>
        <button class="btn btn-outline-success btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#csvForm">
            <i class="bi bi-upload me-1"></i>Upload
        </button>
    </div>
    <div class="collapse mt-3" id="csvForm">
        <form action="{{ route('receiving.import') }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2">
            @csrf
            <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv,.txt" required>
            <button type="submit" class="btn btn-success btn-sm flex-shrink-0">Import</button>
        </form>
    </div>
</div>

{{-- Link ke Riwayat --}}
<div class="form-card">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h6 class="fw-bold mb-1"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Penerimaan & Pemakaian</h6>
            <small class="text-muted">Lihat seluruh riwayat bahan baku masuk dan keluar di halaman Riwayat</small>
        </div>
        <a href="{{ route('riwayat.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-right-circle me-1"></i>Lihat Riwayat
        </a>
    </div>
</div>
@endsection
