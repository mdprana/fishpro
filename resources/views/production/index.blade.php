@extends('layouts.app')
@section('title','Proses Produksi')
@section('breadcrumb')<li class="breadcrumb-item active">Proses Produksi</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-gear-wide-connected me-2"></i>Proses Produksi</h4>
        <p class="text-muted mb-0 small">Seluruh sesi dari semua tahap. Pencatatannya dilakukan di menu tiap tahap.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @foreach($stages as $st)
        <a href="{{ route('stage.create', $st->code) }}" class="btn btn-sm"
           style="background:{{ $st->color }}18;border:1px solid {{ $st->color }}55;color:{{ $st->color }}">
            <i class="bi {{ $st->icon }} me-1"></i>{{ \Illuminate\Support\Str::of($st->name)->before(' (') }}
        </a>
        @endforeach
    </div>
</div>

<div class="row g-2 mb-3">
    @foreach([
        ['Sesi Hari Ini', number_format($today['sesi']), 'bi-list-check', '#64748b'],
        ['Bahan Masuk',   number_format($today['input_kg'], 2) . ' kg', 'bi-box-arrow-in-down', '#2563eb'],
        ['Hasil',         number_format($today['output_kg'], 2) . ' kg', 'bi-box-arrow-up', '#16a34a'],
        ['Limbah',        number_format($today['waste_kg'], 2) . ' kg', 'bi-trash3', '#dc2626'],
    ] as [$label, $value, $icon, $color])
    <div class="col-6 col-lg-3">
        <div class="form-card h-100">
            <div class="d-flex align-items-center gap-2">
                <i class="bi {{ $icon }} fs-4" style="color:{{ $color }}"></i>
                <div>
                    <div class="text-muted" style="font-size:.7rem">{{ $label }}</div>
                    <div class="fw-bold">{{ $value }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="form-card">
    <form method="GET" class="row g-2 mb-3" style="max-width:520px">
        <div class="col-5">
            <select name="stage" class="form-select form-select-sm">
                <option value="">Semua Tahap</option>
                @foreach($stages as $st)
                <option value="{{ $st->code }}" @selected(request('stage') === $st->code)>{{ $st->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-5">
            <select name="po_id" class="form-select form-select-sm">
                <option value="">Semua PO</option>
                @foreach($pos as $po)
                <option value="{{ $po->id }}" @selected(request('po_id') == $po->id)>{{ $po->po_number }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-2">
            <button class="btn btn-sm btn-outline-primary w-100">Filter</button>
        </div>
    </form>

    @if($records->count())
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Kode Sesi</th><th>Tahap</th><th>Tanggal</th><th>PO</th><th>Operator</th>
                    <th class="text-end">Masuk</th><th class="text-end">Hasil</th>
                    <th class="text-end">Limbah</th><th class="text-center">Yield</th><th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $r)
                <tr class="{{ $r->is_voided ? 'opacity-50' : '' }}">
                    <td>
                        <a href="{{ route('stage.show', $r) }}" class="fw-semibold text-decoration-none"
                           style="font-family:ui-monospace,monospace;font-size:.76rem">{{ $r->record_code }}</a>
                    </td>
                    <td>
                        <span class="badge" style="background:{{ $r->stage->color }}22;color:{{ $r->stage->color }};font-size:.68rem">
                            {{ $r->stage->name }}
                        </span>
                    </td>
                    <td class="small">{{ $r->process_date->format('d/m/Y') }}</td>
                    <td class="small">{{ $r->purchaseOrder?->po_number }}</td>
                    <td class="small text-muted">{{ $r->operator_name ?: '-' }}</td>
                    <td class="text-end">{{ number_format((float)$r->total_input_kg, 2) }}</td>
                    <td class="text-end text-success">{{ number_format((float)$r->total_output_kg, 2) }}</td>
                    <td class="text-end text-danger">{{ number_format((float)$r->total_waste_kg, 2) }}</td>
                    <td class="text-center fw-bold">{{ number_format((float)$r->yield_pct, 1) }}%</td>
                    <td class="text-center">
                        @if($r->is_voided)
                            <span class="badge bg-danger-subtle text-danger">Dibatalkan</span>
                        @else
                            <span class="badge bg-success-subtle text-success">Selesai</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $records->links() }}</div>
    @else
    <div class="text-center py-5 text-muted">
        <i class="bi bi-gear-wide-connected fs-2 d-block mb-2"></i>
        Belum ada sesi produksi.
    </div>
    @endif
</div>
@endsection
