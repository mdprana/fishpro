@extends('layouts.app')
@section('title','Rekap Bahan Baku')
@section('breadcrumb')<li class="breadcrumb-item active">Rekap Bahan Baku</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-clipboard-data me-2"></i>Rekap Bahan Baku</h4>
        <p class="text-muted mb-0 small">Dari supplier dan batch mana saja bahan berasal, dan berapa yang akhirnya menjadi box.</p>
    </div>
    <a href="{{ route('report.raw-material.csv', request()->query()) }}" class="btn btn-outline-primary">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<div class="form-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Dari Tanggal</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Sampai</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Nomor PO</label>
            <select name="po_id" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach($pos as $po)
                <option value="{{ $po->id }}" @selected(request('po_id') == $po->id)>{{ $po->po_number }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Supplier</label>
            <select name="supplier_id" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach($suppliers as $s)
                <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->code }} - {{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="font-size:.75rem">Bahan</label>
            <select name="subgroup_id" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach($groups as $g)
                <optgroup label="{{ $g->name }}">
                    @foreach($g->subgroups as $sub)
                    <option value="{{ $sub->id }}" @selected(request('subgroup_id') == $sub->id)>{{ $sub->name }}</option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            <a href="{{ route('report.raw-material') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

{{-- 1. Per Supplier --}}
<div class="form-card mb-3">
    <h6 class="fw-bold mb-1">1. Per Supplier</h6>
    <p class="text-muted small mb-3">Recovery di sini = kg yang sampai menjadi box, dibagi kg yang diterima dari supplier itu.</p>
    @if($perSupplier->count())
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Kode</th><th>Supplier</th>
                    <th class="text-center">Penerimaan</th><th class="text-center">Batch</th>
                    <th class="text-end">Masuk</th><th class="text-end">Terpakai</th><th class="text-end">Sisa</th>
                    <th class="text-end">Terpacking</th><th class="text-center">Box</th><th class="text-end">Recovery</th>
                </tr>
            </thead>
            <tbody>
                @foreach($perSupplier as $r)
                <tr>
                    <td><span class="badge bg-dark-subtle text-dark fw-bold">{{ $r['code'] }}</span></td>
                    <td class="small fw-semibold">{{ $r['name'] }}</td>
                    <td class="text-center">{{ $r['penerimaan'] }}</td>
                    <td class="text-center">{{ $r['batch'] }}</td>
                    <td class="text-end fw-semibold">{{ number_format($r['masuk_kg'], 2) }}</td>
                    <td class="text-end text-muted">{{ number_format($r['terpakai_kg'], 2) }}</td>
                    <td class="text-end">{{ number_format($r['sisa_kg'], 2) }}</td>
                    <td class="text-end text-success">{{ number_format($r['packing_kg'], 2) }}</td>
                    <td class="text-center">{{ $r['box'] }}</td>
                    <td class="text-end fw-bold text-{{ $r['recovery_pct'] >= 45 ? 'success' : ($r['recovery_pct'] > 0 ? 'warning' : 'secondary') }}">
                        {{ number_format($r['recovery_pct'], 1) }}%
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold border-top">
                    <td colspan="4">Total</td>
                    <td class="text-end">{{ number_format($perSupplier->sum('masuk_kg'), 2) }}</td>
                    <td class="text-end">{{ number_format($perSupplier->sum('terpakai_kg'), 2) }}</td>
                    <td class="text-end">{{ number_format($perSupplier->sum('sisa_kg'), 2) }}</td>
                    <td class="text-end">{{ number_format($perSupplier->sum('packing_kg'), 2) }}</td>
                    <td class="text-center">{{ $perSupplier->sum('box') }}</td>
                    <td class="text-end">
                        @php $tm = $perSupplier->sum('masuk_kg'); @endphp
                        {{ $tm > 0 ? number_format(($perSupplier->sum('packing_kg') / $tm) * 100, 1) : '0,0' }}%
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
    <div class="text-center py-4 text-muted small">Tidak ada data pada filter ini.</div>
    @endif
</div>

{{-- 2. Per Batch Induk --}}
<div class="form-card mb-3">
    <h6 class="fw-bold mb-1">2. Per Batch Induk</h6>
    <p class="text-muted small mb-3">Tiap baris adalah satu unit fisik bahan baku yang masuk pabrik.</p>
    @if($perBatch->count())
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Batch</th><th>Supplier</th><th>Bahan</th><th>Tgl Bongkar</th>
                    <th class="text-end">Masuk</th><th class="text-end">Terpakai</th><th class="text-end">Sisa</th>
                    <th class="text-center">Status</th><th class="text-end">Terpacking</th><th class="text-center">Box</th><th>PO</th>
                </tr>
            </thead>
            <tbody>
                @foreach($perBatch as $r)
                <tr>
                    <td>
                        <a href="{{ route('trace.batch', $r['qr_code']) }}"
                           style="font-family:ui-monospace,monospace;font-size:.7rem">{{ $r['batch_number'] }}</a>
                    </td>
                    <td class="small">{{ $r['supplier_code'] }}</td>
                    <td class="small">{{ $r['subgroup'] }}</td>
                    <td class="small">{{ $r['tgl_bongkar'] ? \Illuminate\Support\Carbon::parse($r['tgl_bongkar'])->format('d/m/y') : '-' }}</td>
                    <td class="text-end">{{ number_format($r['masuk_kg'], 2) }}</td>
                    <td class="text-end text-muted">{{ number_format($r['terpakai_kg'], 2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($r['sisa_kg'], 2) }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $r['status_color'] }}-subtle text-{{ $r['status_color'] }}" style="font-size:.62rem">
                            {{ $r['status'] }}
                        </span>
                    </td>
                    <td class="text-end text-success">{{ number_format($r['packing_kg'], 2) }}</td>
                    <td class="text-center">{{ $r['box'] }}</td>
                    <td class="small">{{ $r['po_number'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="text-center py-4 text-muted small">Tidak ada batch pada filter ini.</div>
    @endif
</div>

{{-- 3. Per Jenis Bahan --}}
<div class="form-card">
    <h6 class="fw-bold mb-3">3. Per Jenis Bahan</h6>
    @if($perSubgroup->count())
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead>
                <tr><th>Bahan</th><th>Group</th><th class="text-center">Batch</th>
                    <th class="text-end">Masuk</th><th class="text-end">Terpakai</th>
                    <th class="text-end">Sisa</th><th class="text-end">Terpacking</th></tr>
            </thead>
            <tbody>
                @foreach($perSubgroup as $r)
                <tr>
                    <td class="small fw-semibold">{{ $r['subgroup'] }}</td>
                    <td class="small text-muted">{{ $r['group'] }}</td>
                    <td class="text-center">{{ $r['batch'] }}</td>
                    <td class="text-end">{{ number_format($r['masuk_kg'], 2) }}</td>
                    <td class="text-end text-muted">{{ number_format($r['terpakai_kg'], 2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($r['sisa_kg'], 2) }}</td>
                    <td class="text-end text-success">{{ number_format($r['packing_kg'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="text-center py-4 text-muted small">Tidak ada data pada filter ini.</div>
    @endif
</div>
@endsection
