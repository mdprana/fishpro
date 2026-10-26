@extends('layouts.app')
@section('title','Yield & Recovery')
@section('breadcrumb')<li class="breadcrumb-item active">Yield &amp; Recovery</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-speedometer2 me-2"></i>Yield &amp; Recovery</h4>
        <p class="text-muted mb-0 small">
            Recovery = Total Berat Packing ÷ Total Berat Bahan Baku × 100%
        </p>
    </div>
    <form method="GET" class="d-flex gap-2 align-items-end">
        <div>
            <label class="form-label" style="font-size:.72rem">Dari</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
        </div>
        <div>
            <label class="form-label" style="font-size:.72rem">Sampai</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
        </div>
        <button class="btn btn-sm btn-primary">Terapkan</button>
        <a href="{{ route('report.yield') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
    </form>
</div>

{{-- KPI --}}
@php
    $rec = $summary['recovery_pct'];
    $recColor = $rec >= 45 ? 'success' : ($rec >= 30 ? 'warning' : 'danger');
    $recBg = match ($recColor) {
        'success' => '#E2F4E6',
        'warning' => '#FBF0DA',
        default   => '#FDE8E5',
    };
@endphp
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3">
        <div class="form-card h-100">
            <div class="text-muted" style="font-size:.7rem">Total Bahan Baku</div>
            <div class="fw-bold fs-4">{{ number_format($summary['bahan_kg'], 2) }} <small class="text-muted">kg</small></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="form-card h-100">
            <div class="text-muted" style="font-size:.7rem">Total Packing</div>
            <div class="fw-bold fs-4 text-success">{{ number_format($summary['packing_kg'], 2) }} <small class="text-muted">kg</small></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="form-card h-100" style="background:{{ $recBg }}">
            <div class="text-muted" style="font-size:.7rem">Recovery</div>
            <div class="fw-bold text-{{ $recColor }}" style="font-size:2rem">{{ number_format($rec, 1) }}%</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="form-card h-100">
            <div class="text-muted" style="font-size:.7rem">Total Susut</div>
            <div class="fw-bold fs-4 text-danger">{{ number_format($summary['susut_kg'], 2) }} <small class="text-muted">kg</small></div>
        </div>
    </div>
</div>

{{-- Alert toleransi --}}
<div class="form-card mb-3 {{ $alerts->count() ? 'border-danger' : '' }}">
    <h6 class="fw-bold mb-1">
        Alert Penyusutan Melebihi Toleransi
        @if($alerts->count())<span class="badge bg-danger ms-1">{{ $alerts->count() }}</span>@endif
    </h6>
    @if($alerts->count())
    <p class="text-muted small mb-3">
        Dinilai dengan standar yang berlaku pada tanggal produksinya, bukan standar hari ini.
    </p>
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead>
                <tr><th>Sesi</th><th>Tahap</th><th>Tanggal</th><th>PO</th><th>Bahan</th><th>Operator</th>
                    <th class="text-end">Yield</th><th class="text-end">Standar</th><th class="text-end">Selisih</th><th></th></tr>
            </thead>
            <tbody>
                @foreach($alerts as $a)
                <tr class="{{ $a['level'] === 'below' ? 'table-danger' : 'table-warning' }}">
                    <td style="font-family:ui-monospace,monospace;font-size:.72rem">{{ $a['record']->record_code }}</td>
                    <td class="small">{{ $a['record']->stage->name }}</td>
                    <td class="small">{{ $a['record']->process_date->format('d/m/y') }}</td>
                    <td class="small">{{ $a['record']->purchaseOrder?->po_number }}</td>
                    <td class="small">{{ $a['subgroup'] ?? '-' }}</td>
                    <td class="small text-muted">{{ $a['record']->operator_name ?: '-' }}</td>
                    <td class="text-end fw-bold">{{ number_format((float)$a['record']->yield_pct, 1) }}%</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format((float)$a['standard']->min_yield_pct, 2), '0'), '.') }}%</td>
                    <td class="text-end text-danger">
                        -{{ number_format($a['selisih_pct'], 1) }}%
                        <div class="text-muted" style="font-size:.64rem">≈ {{ number_format($a['selisih_kg'], 1) }} kg</div>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('stage.show', $a['record']) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="text-center py-4 text-success small">
        <i class="bi bi-check-circle fs-4 d-block mb-1"></i>
        Tidak ada sesi yang penyusutannya melebihi toleransi pada periode ini.
    </div>
    @endif
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="form-card h-100">
            <h6 class="fw-bold mb-3">Hasil vs Limbah per Tahap</h6>
            <canvas id="stageChart" height="180"></canvas>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="form-card h-100">
            <h6 class="fw-bold mb-3">Tren Recovery Harian (30 hari)</h6>
            <canvas id="trendChart" height="180"></canvas>
        </div>
    </div>
</div>

{{-- Yield per tahap --}}
<div class="form-card mb-3">
    <h6 class="fw-bold mb-3">Yield per Tahap</h6>
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr><th>Tahap</th><th class="text-center">Sesi</th><th class="text-end">Masuk</th>
                    <th class="text-end">Hasil</th><th class="text-end">Limbah</th><th class="text-end">Yield</th></tr>
            </thead>
            <tbody>
                @foreach($perStage as $s)
                <tr>
                    <td>
                        <i class="bi {{ $s['stage']->icon }} me-1" style="color:{{ $s['stage']->color }}"></i>
                        {{ $s['stage']->name }}
                    </td>
                    <td class="text-center">{{ $s['sesi'] }}</td>
                    <td class="text-end">{{ number_format($s['input_kg'], 2) }}</td>
                    <td class="text-end text-success">{{ number_format($s['output_kg'], 2) }}</td>
                    <td class="text-end text-danger">{{ number_format($s['waste_kg'], 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($s['yield_pct'], 1) }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Yield per bahan per tahap --}}
<div class="form-card mb-3">
    <h6 class="fw-bold mb-3">Yield per Bahan per Tahap</h6>
    @if($perSubgroup->count())
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead>
                <tr><th>Bahan</th><th>Tahap</th><th class="text-center">Sesi</th>
                    <th class="text-end">Masuk</th><th class="text-end">Hasil</th>
                    <th class="text-end">Yield</th><th class="text-end">Standar Min</th><th class="text-center">Status</th></tr>
            </thead>
            <tbody>
                @foreach($perSubgroup as $r)
                @php
                    $rowClass = match ($r['level']) {
                        'below' => 'table-danger',
                        'warn'  => 'table-warning',
                        default => '',
                    };
                @endphp
                <tr class="{{ $rowClass }}">
                    <td class="small fw-semibold">{{ $r['subgroup'] }}</td>
                    <td class="small">{{ $r['stage'] }}</td>
                    <td class="text-center">{{ $r['sesi'] }}</td>
                    <td class="text-end">{{ number_format($r['input_kg'], 2) }}</td>
                    <td class="text-end">{{ number_format($r['output_kg'], 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($r['yield_pct'], 1) }}%</td>
                    <td class="text-end">
                        {{ $r['standard'] ? rtrim(rtrim(number_format((float)$r['standard']->min_yield_pct, 2), '0'), '.') . '%' : '-' }}
                    </td>
                    <td class="text-center">
                        @switch($r['level'])
                            @case('below')<span class="badge bg-danger">Di bawah standar</span>@break
                            @case('warn')<span class="badge bg-warning text-dark">Perhatian</span>@break
                            @case('ok')<span class="badge bg-success">Aman</span>@break
                            @default<span class="text-muted small">Belum ada standar</span>
                        @endswitch
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="text-center py-4 text-muted small">Belum ada sesi produksi pada periode ini.</div>
    @endif
</div>

{{-- Recovery per PO --}}
<div class="form-card">
    <h6 class="fw-bold mb-3">Recovery per PO</h6>
    @if($perPo->count())
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle mb-0">
            <thead>
                <tr><th>PO</th><th>Buyer</th><th class="text-end">Bahan Baku</th>
                    <th class="text-end">Packing</th><th class="text-end">Susut</th><th class="text-end">Recovery</th><th></th></tr>
            </thead>
            <tbody>
                @foreach($perPo as $r)
                <tr>
                    <td class="fw-semibold">{{ $r['po']->po_number }}</td>
                    <td class="small text-muted">{{ $r['po']->buyer_name }}</td>
                    <td class="text-end">{{ number_format($r['bahan_kg'], 2) }}</td>
                    <td class="text-end text-success">{{ number_format($r['packing_kg'], 2) }}</td>
                    <td class="text-end text-danger">{{ number_format($r['susut_kg'], 2) }}</td>
                    <td class="text-end fw-bold text-{{ $r['recovery_pct'] >= 45 ? 'success' : ($r['recovery_pct'] >= 30 ? 'warning' : 'danger') }}">
                        {{ number_format($r['recovery_pct'], 1) }}%
                    </td>
                    <td class="text-end">
                        <a href="{{ route('po.show', $r['po']) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="text-center py-4 text-muted small">Belum ada PO dengan bahan baku pada periode ini.</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
new Chart(document.getElementById('stageChart'), {
    type: 'bar',
    data: {
        labels: @json($stageChart['labels']),
        datasets: [
            { label: 'Hasil (kg)',  data: @json($stageChart['output']), backgroundColor: '#147A3A' },
            { label: 'Limbah (kg)', data: @json($stageChart['waste']),  backgroundColor: '#B42318' },
        ],
    },
    options: {
        responsive: true,
        // Bertumpuk supaya tinggi batang = total bahan yang masuk tahap itu.
        scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, grid: { color: '#EFF1F3' } } },
        plugins: { legend: { position: 'bottom' } },
    },
});

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: @json($trend['labels']),
        datasets: [{
            label: 'Recovery harian (%)',
            data: @json($trend['values']),
            borderColor: '#17191F',
            backgroundColor: 'rgba(23,25,31,.08)',
            fill: true,
            tension: .3,
            spanGaps: true,   // hari tanpa penerimaan tidak memutus garis
        }],
    },
    options: {
        responsive: true,
        scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#EFF1F3' }, ticks: { callback: v => v + '%' } } },
        plugins: { legend: { display: false } },
    },
});
</script>
@endpush
