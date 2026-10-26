@extends('layouts.app')
@section('title','Standar Yield')
@section('breadcrumb')<li class="breadcrumb-item active">Standar Yield</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-speedometer2 me-2"></i>Standar Yield</h4>
        <p class="text-muted mb-0 small">
            Toleransi penyusutan per bahan per tahap. Perubahan tidak menimpa:
            versi lama ditutup dan tersimpan, agar laporan lama tetap dinilai dengan standar saat itu.
        </p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStandardModal">
        <i class="bi bi-plus-lg me-1"></i>Buat Versi Baru
    </button>
</div>

<div class="form-card mb-3">
    <h6 class="fw-bold mb-3">Berlaku Saat Ini</h6>
    @if($aktif->count())
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Bahan</th><th>Tahap</th>
                    <th class="text-end">Minimal</th><th class="text-end">Perhatian</th><th class="text-end">Target</th>
                    <th>Berlaku Sejak</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($aktif as $s)
                <tr>
                    <td class="small">
                        {{ $s->subgroup?->name ?? 'Semua bahan' }}
                        @if($s->subgroup?->group)<div class="text-muted" style="font-size:.68rem">{{ $s->subgroup->group->name }}</div>@endif
                    </td>
                    <td class="small">{{ $s->stage?->name ?? 'Semua tahap' }}</td>
                    <td class="text-end fw-bold text-danger">{{ rtrim(rtrim(number_format((float)$s->min_yield_pct,2),'0'),'.') }}%</td>
                    <td class="text-end text-warning">{{ $s->warn_yield_pct !== null ? rtrim(rtrim(number_format((float)$s->warn_yield_pct,2),'0'),'.').'%' : '-' }}</td>
                    <td class="text-end text-success">{{ $s->target_yield_pct !== null ? rtrim(rtrim(number_format((float)$s->target_yield_pct,2),'0'),'.').'%' : '-' }}</td>
                    <td class="small">{{ $s->effective_from->format('d/m/Y') }}</td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('master.yield-standards.close', $s) }}"
                              onsubmit="return confirm('Hentikan standar ini per hari ini?')">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" title="Hentikan">
                                <i class="bi bi-slash-circle"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="text-center py-4 text-muted small">
        Belum ada standar. Tanpa standar, sistem tidak bisa memberi alert saat penyusutan berlebih.
    </div>
    @endif
</div>

<div class="form-card">
    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Riwayat Versi</h6>
    @if($riwayat->count())
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead><tr><th>Bahan</th><th>Tahap</th><th class="text-end">Minimal</th><th>Berlaku</th><th>Dibuat Oleh</th></tr></thead>
            <tbody>
                @foreach($riwayat as $s)
                <tr class="text-muted">
                    <td class="small">{{ $s->subgroup?->name ?? 'Semua bahan' }}</td>
                    <td class="small">{{ $s->stage?->name ?? 'Semua tahap' }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format((float)$s->min_yield_pct,2),'0'),'.') }}%</td>
                    <td class="small">{{ $s->effective_from->format('d/m/y') }} - {{ $s->effective_to?->format('d/m/y') }}</td>
                    <td class="small">{{ $s->creator?->name ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <p class="text-muted small mb-0">Belum ada versi yang ditutup.</p>
    @endif
</div>

<div class="modal fade" id="addStandardModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('master.yield-standards.store') }}">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Standar Yield Versi Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Bahan</label>
                        <select name="material_subgroup_id" class="form-select">
                            <option value="">Semua bahan (umum)</option>
                            @foreach($groups as $g)
                            <optgroup label="{{ $g->name }}">
                                @foreach($g->subgroups as $sub)
                                <option value="{{ $sub->id }}" @selected(old('material_subgroup_id') == $sub->id)>{{ $sub->name }}</option>
                                @endforeach
                            </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tahap</label>
                        <select name="process_stage_id" class="form-select">
                            <option value="">Semua tahap</option>
                            @foreach($stages as $st)
                            <option value="{{ $st->id }}" @selected(old('process_stage_id') == $st->id)>{{ $st->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Minimal % <span class="text-danger">*</span></label>
                        <input type="number" name="min_yield_pct" class="form-control @error('min_yield_pct') is-invalid @enderror"
                               step="0.01" min="0" max="100" value="{{ old('min_yield_pct') }}" required>
                        @error('min_yield_pct')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" style="font-size:.68rem">Di bawah ini = alert</div>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Perhatian %</label>
                        <input type="number" name="warn_yield_pct" class="form-control @error('warn_yield_pct') is-invalid @enderror"
                               step="0.01" min="0" max="100" value="{{ old('warn_yield_pct') }}">
                        @error('warn_yield_pct')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-4">
                        <label class="form-label">Target %</label>
                        <input type="number" name="target_yield_pct" class="form-control @error('target_yield_pct') is-invalid @enderror"
                               step="0.01" min="0" max="100" value="{{ old('target_yield_pct') }}">
                        @error('target_yield_pct')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Berlaku Sejak <span class="text-danger">*</span></label>
                        <input type="date" name="effective_from" class="form-control"
                               value="{{ old('effective_from', today()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}"
                               placeholder="Mis. penyesuaian setelah ganti mesin">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary">Simpan Versi Baru</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
@if($errors->any())
new bootstrap.Modal(document.getElementById('addStandardModal')).show();
@endif
</script>
@endpush
