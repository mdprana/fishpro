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

{{-- Stok per Subgroup: semua subgroup aktif, stok kosong = 0 kg --}}
@if($subgroups->count() > 0)
<div class="form-card mb-4">
    <h6 class="fw-bold mb-3">Stok Saat Ini</h6>
    @foreach($subgroups->groupBy(fn($s) => $s->group->name) as $groupName => $groupSubs)
    <div class="mb-3">
        <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing:.05em;font-size:.7rem">
            {{ $groupName }}
        </div>
        <div class="row g-2">
            @foreach($groupSubs as $s)
            @php $reached = $s->minimum_reached; @endphp
            <div class="col-6 col-md-4 col-xl-3">
                <div class="p-2 rounded-3 border {{ $reached ? 'bg-warning-subtle' : '' }}">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="color-dot" style="background:{{ $s->group->color ?? '#6b7280' }}"></span>
                        <span class="fw-semibold small">{{ $s->name }}</span>
                    </div>
                    <div class="fw-bold text-dark" style="font-size:1.3rem">
                        {{ number_format($s->current_stock, 1) }}<span class="text-secondary small fw-normal"> kg</span>
                    </div>
                    <div class="small {{ $reached ? 'text-warning fw-semibold' : 'text-muted' }}">
                        @if($reached === null)
                            Minimum: Belum diatur
                        @else
                            Minimum: {{ number_format((float) $s->minimum_stock_kg, 2) }} kg
                            @if($reached)<div><i class="bi bi-exclamation-triangle me-1"></i>Stok minimum tercapai</div>@endif
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
@endif

@can('manage-operations')
<div class="form-card mb-4">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h6 class="fw-bold mb-1"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Import CSV</h6>
            <small class="text-muted">UTF-8, koma, maksimum 1 MiB / 500 baris / 100 wadah per baris / 1.000 lot. Tanggal Y-m-d; split 0/1/true/false. Semua baris harus valid; isi identik hanya boleh diimpor sekali.</small>
            <div><a href="{{ route('receiving.import.template') }}">Unduh header dan contoh CSV</a></div>
        </div>
        <button class="btn btn-outline-success btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#csvForm">
            <i class="bi bi-upload me-1"></i>Upload
        </button>
    </div>
    <div class="collapse mt-3" id="csvForm">
        <form action="{{ route('receiving.import') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
            @csrf
            <label for="csv_file">Berkas CSV penerimaan</label>
            <input id="csv_file" type="file" name="csv_file" class="form-control form-control-sm" accept=".csv,.txt" required>
            <button type="submit" class="btn btn-success btn-sm flex-shrink-0">Pratinjau</button>
        </form>
    </div>
</div>

@endcan

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
