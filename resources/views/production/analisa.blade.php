@extends('layouts.app')
@section('title','Analisa PO')
@section('breadcrumb')<li class="breadcrumb-item active">Analisa PO</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow me-2"></i>Analisa PO</h4>
        <p class="text-muted mb-0 small">Dari bahan baku yang masuk sampai box yang keluar, untuk satu PO.</p>
    </div>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <select name="po_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
                <option value="">-- Pilih PO --</option>
                @foreach($pos as $p)
                <option value="{{ $p->id }}" @selected($po?->id == $p->id)>{{ $p->po_number }} - {{ $p->buyer_name }}</option>
                @endforeach
            </select>
        </form>
        @if($po)
        <a href="{{ route('production.analisa.export', $po) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-download me-1"></i>CSV
        </a>
        <a href="{{ route('production.analisa.pdf', $po) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Cetak
        </a>
        @endif
    </div>
</div>

@if(!$po)
<div class="form-card text-center py-5 text-muted">
    <i class="bi bi-graph-up-arrow fs-2 d-block mb-2"></i>
    Pilih PO untuk melihat analisanya.
</div>
@else

{{-- Recovery --}}
@php $r = $analisa['recovery']; $rc = $r['recovery_pct'] >= 45 ? 'success' : ($r['recovery_pct'] >= 30 ? 'warning' : 'danger'); @endphp
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3">
        <div class="form-card h-100">
            <div class="text-muted" style="font-size:.7rem">Nomor PO</div>
            <div class="fw-bold">{{ $po->po_number }}</div>
            <div class="text-muted" style="font-size:.7rem">{{ $po->buyer_name }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="form-card h-100">
            <div class="text-muted" style="font-size:.7rem">Bahan Baku Masuk</div>
            <div class="fw-bold fs-5">{{ number_format($r['bahan_kg'], 2) }} kg</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="form-card h-100">
            <div class="text-muted" style="font-size:.7rem">Hasil Packing</div>
            <div class="fw-bold fs-5 text-success">{{ number_format($r['packing_kg'], 2) }} kg</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="form-card h-100">
            <div class="text-muted" style="font-size:.7rem">Recovery</div>
            <div class="fw-bold text-{{ $rc }}" style="font-size:1.8rem">{{ number_format($r['recovery_pct'], 1) }}%</div>
            <div class="text-muted" style="font-size:.68rem">Susut {{ number_format($r['susut_kg'], 2) }} kg</div>
        </div>
    </div>
</div>

{{-- Rekap bahan baku: dari mana saja bahannya --}}
<div class="form-card mb-3">
    <h6 class="fw-bold mb-1">1. Rekap Bahan Baku: Dari Mana Saja</h6>
    <p class="text-muted small mb-3">Asal bahan PO ini, dirinci per supplier dan per batch yang masuk.</p>

    <div class="table-responsive mb-3">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead><tr><th>Kode</th><th>Supplier</th><th class="text-center">Batch</th>
                <th class="text-end">Masuk</th><th class="text-end">Terpakai</th><th class="text-end">Sisa</th></tr></thead>
            <tbody>
                @forelse($analisa['bahanPerSupplier'] as $row)
                <tr>
                    <td><span class="badge bg-dark-subtle text-dark fw-bold">{{ $row['code'] }}</span></td>
                    <td class="small">{{ $row['name'] }}</td>
                    <td class="text-center">{{ $row['batch'] }}</td>
                    <td class="text-end fw-semibold">{{ number_format($row['masuk_kg'], 2) }}</td>
                    <td class="text-end text-muted">{{ number_format($row['terpakai_kg'], 2) }}</td>
                    <td class="text-end">{{ number_format($row['sisa_kg'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada penerimaan untuk PO ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(count($analisa['bahanPerBatch']))
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead><tr><th>Batch</th><th>Supplier</th><th>Bahan</th><th>Tgl Bongkar</th>
                <th class="text-end">Masuk</th><th class="text-end">Terpakai</th><th class="text-end">Sisa</th><th class="text-center">Status</th></tr></thead>
            <tbody>
                @foreach($analisa['bahanPerBatch'] as $row)
                <tr>
                    <td><a href="{{ route('trace.batch', $row['qr_code']) }}" style="font-family:ui-monospace,monospace;font-size:.7rem">{{ $row['batch_number'] }}</a></td>
                    <td class="small">{{ $row['supplier_code'] }}</td>
                    <td class="small">{{ $row['subgroup'] }}</td>
                    <td class="small">{{ $row['tgl_bongkar'] ? \Illuminate\Support\Carbon::parse($row['tgl_bongkar'])->format('d/m/y') : '-' }}</td>
                    <td class="text-end">{{ number_format($row['masuk_kg'], 2) }}</td>
                    <td class="text-end text-muted">{{ number_format($row['terpakai_kg'], 2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($row['sisa_kg'], 2) }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $row['status_color'] }}-subtle text-{{ $row['status_color'] }}" style="font-size:.62rem">{{ $row['status'] }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- Ringkasan per tahap --}}
<div class="form-card mb-3">
    <h6 class="fw-bold mb-3">2. Ringkasan per Tahap</h6>
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead><tr><th>Tahap</th><th class="text-center">Sesi</th><th class="text-end">Masuk</th>
                <th class="text-end">Hasil</th><th class="text-end">Limbah</th>
                <th class="text-end">Yield</th><th class="text-end">Standar</th><th class="text-center">Status</th></tr></thead>
            <tbody>
                @forelse($analisa['perTahap'] as $row)
                @php
                    $below = $row['standard_min'] !== null && $row['yield_pct'] < $row['standard_min'];
                @endphp
                <tr class="{{ $below ? 'table-danger' : '' }}">
                    <td><i class="bi {{ $row['icon'] }} me-1" style="color:{{ $row['color'] }}"></i>{{ $row['stage'] }}</td>
                    <td class="text-center">{{ $row['sesi'] }}</td>
                    <td class="text-end">{{ number_format($row['input_kg'], 2) }}</td>
                    <td class="text-end text-success">{{ number_format($row['output_kg'], 2) }}</td>
                    <td class="text-end text-danger">{{ number_format($row['waste_kg'], 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($row['yield_pct'], 1) }}%</td>
                    <td class="text-end">{{ $row['standard_min'] !== null ? number_format($row['standard_min'], 1) . '%' : '-' }}</td>
                    <td class="text-center">
                        @if($below)<span class="badge bg-danger">Di bawah standar</span>
                        @elseif($row['standard_min'] !== null)<span class="badge bg-success">Aman</span>
                        @else<span class="text-muted small">-</span>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-3">Belum ada sesi produksi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Limbah --}}
@if(count($analisa['limbah']))
<div class="form-card mb-3">
    <h6 class="fw-bold mb-3">3. Limbah per Kategori</h6>
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead><tr><th>Tahap</th><th>Kategori</th><th class="text-end">Berat</th></tr></thead>
            <tbody>
                @foreach($analisa['limbah'] as $row)
                <tr>
                    <td class="small">{{ $row['stage'] }}</td>
                    <td class="small">{{ $row['kategori'] }}</td>
                    <td class="text-end text-danger fw-semibold">{{ number_format($row['kg'], 2) }} kg</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Estimasi vs aktual --}}
<div class="form-card mb-3">
    <h6 class="fw-bold mb-1">4. Estimasi Yield vs Aktual</h6>
    <p class="text-muted small mb-3">Dihitung per pasangan bahan-produk, memakai jejak kontribusi tiap batch.</p>
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead><tr><th>Bahan</th><th>Produk</th><th class="text-end">Bahan Masuk</th>
                <th class="text-end">Est. Yield</th><th class="text-end">Est.</th>
                <th class="text-end">Aktual</th><th class="text-end">Yield Aktual</th><th class="text-end">Selisih</th></tr></thead>
            <tbody>
                @forelse($analisa['estimasiVsAktual'] as $row)
                <tr>
                    <td class="small">{{ $row['bahan'] }}</td>
                    <td><span class="badge" style="background:{{ $row['color'] }}22;color:{{ $row['color'] }};font-size:.68rem">{{ $row['produk'] }}</span></td>
                    <td class="text-end">{{ number_format($row['input_kg'], 2) }}</td>
                    <td class="text-end">{{ $row['est_pct'] !== null ? number_format($row['est_pct'], 1) . '%' : '-' }}</td>
                    <td class="text-end text-muted">{{ $row['est_kg'] !== null ? number_format($row['est_kg'], 2) : '-' }}</td>
                    <td class="text-end fw-semibold">{{ number_format($row['aktual_kg'], 2) }}</td>
                    <td class="text-end">{{ number_format($row['yield_aktual'], 1) }}%</td>
                    <td class="text-end {{ ($row['selisih_kg'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                        {{ $row['selisih_kg'] !== null ? number_format($row['selisih_kg'], 2) : '-' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-3">Belum ada hasil produksi ber-tipe produk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Target vs packing --}}
<div class="form-card mb-3">
    <h6 class="fw-bold mb-3">5. Target PO vs Packing</h6>
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead><tr><th>Produk</th><th class="text-end">Target kg</th><th class="text-center">Target Box</th>
                <th class="text-end">Packing kg</th><th class="text-center">Box</th>
                <th class="text-end">Selisih kg</th><th class="text-center">Status</th></tr></thead>
            <tbody>
                @foreach($analisa['targetVsPacking'] as $row)
                <tr>
                    <td><span class="badge" style="background:{{ $row['color'] }}22;color:{{ $row['color'] }};font-size:.68rem">{{ $row['produk'] }}</span></td>
                    <td class="text-end">{{ number_format($row['target_kg'], 2) }}</td>
                    <td class="text-center">{{ $row['target_box'] }}</td>
                    <td class="text-end text-success">{{ number_format($row['packing_kg'], 2) }}</td>
                    <td class="text-center">{{ $row['packing_box'] }}</td>
                    <td class="text-end {{ $row['selisih_kg'] < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($row['selisih_kg'], 2) }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $row['status'] === 'Terpenuhi' ? 'success' : 'warning' }}">{{ $row['status'] }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Sisa lot --}}
@if(count($analisa['sisaLot']))
<div class="form-card">
    <h6 class="fw-bold mb-3">6. Lot Siap Packing yang Belum Terpakai</h6>
    <div class="row g-2">
        @foreach($analisa['sisaLot'] as $row)
        <div class="col-6 col-lg-3">
            <div class="p-2 rounded-3 border" style="border-color:{{ $row['color'] }}55 !important">
                <div class="fw-semibold" style="color:{{ $row['color'] }}">{{ $row['produk'] }}</div>
                <div class="fw-bold">{{ number_format($row['sisa_kg'], 2) }} kg</div>
                <div class="text-muted" style="font-size:.68rem">{{ $row['lot'] }} lot</div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

@endif
@endsection
