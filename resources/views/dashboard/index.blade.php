@extends('layouts.app')
@section('title','Dashboard')
@section('breadcrumb')<li class="breadcrumb-item active">Dashboard</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Dashboard Produksi</h4>
        <p class="text-body-regular mb-0">{{ now()->format('l, d M Y') }}</p>
    </div>
    <a href="{{ route('po.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Buat PO
    </a>
</div>

{{-- Recovery pabrik + alert penyusutan --}}
@php
    $rec      = $recovery['recovery_pct'];
    $recColor = $rec >= 45 ? 'success' : ($rec >= 30 ? 'warning' : 'danger');
@endphp
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-4">
        <a href="{{ route('report.yield') }}" class="text-decoration-none">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-body-regular">Recovery Pabrik</span>
                    <i class="bi bi-bullseye text-muted"></i>
                </div>
                <div class="text-kpi-value text-{{ $recColor }}">{{ number_format($rec, 1) }}%</div>
                <div class="text-body-small text-muted">
                    {{ number_format($recovery['packing_kg'], 1) }} kg packing ÷
                    {{ number_format($recovery['bahan_kg'], 1) }} kg bahan baku
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-lg-8">
        <div class="stat-card h-100 {{ $yieldAlerts->count() ? 'border-danger' : '' }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-body-regular">
                    Penyusutan Melebihi Toleransi
                    @if($yieldAlerts->count())<span class="badge bg-danger ms-1">{{ $yieldAlerts->count() }}</span>@endif
                </span>
                <a href="{{ route('report.yield') }}" class="text-body-regular">Lihat semua</a>
            </div>
            @forelse($yieldAlerts as $a)
            <a href="{{ route('stage.show', $a['record']) }}"
               class="d-flex justify-content-between align-items-center py-1 text-decoration-none border-bottom">
                <div>
                    <span class="fw-semibold" style="font-size:.78rem;font-family:ui-monospace,monospace">{{ $a['record']->record_code }}</span>
                    <span class="text-muted ms-1" style="font-size:.72rem">
                        {{ $a['record']->stage->name }} · {{ $a['record']->process_date->format('d/m/y') }}
                        @if($a['subgroup']) · {{ $a['subgroup'] }}@endif
                    </span>
                </div>
                <div class="text-end">
                    <span class="fw-bold text-danger" style="font-size:.8rem">{{ number_format((float)$a['record']->yield_pct, 1) }}%</span>
                    <span class="text-muted" style="font-size:.68rem">/ {{ rtrim(rtrim(number_format((float)$a['standard']->min_yield_pct, 2), '0'), '.') }}%</span>
                </div>
            </a>
            @empty
            <div class="text-success small py-2">
                <i class="bi bi-check-circle me-1"></i>Semua sesi produksi masih dalam batas toleransi.
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- STAT CARDS --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-body-regular">PO Aktif</span>
                <i class="bi bi-file-earmark-text text-muted"></i>
            </div>
            <div class="text-kpi-value text-dark">{{ $summary['total_active_po'] }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-body-regular">Output Hari Ini</span>
                <i class="bi bi-gear text-muted"></i>
            </div>
            <div class="text-kpi-value text-dark">{{ $summary['today_output_kg'] }}<span class="text-body-regular text-muted"> kg</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-body-regular">Total Box Packed</span>
                <i class="bi bi-box-seam text-muted"></i>
            </div>
            <div class="text-kpi-value text-dark">{{ number_format($summary['total_packed_box']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-body-regular">Alert Aktif</span>
                <i class="bi bi-exclamation-triangle text-muted"></i>
            </div>
            <div class="text-kpi-value text-dark">{{ $summary['total_alerts'] }}</div>
        </div>
    </div>
</div>

{{-- PROGRESS PER PO PER PRODUK --}}
@forelse($poTable as $po)
<div class="form-card mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <a href="{{ route('po.show',$po['id']) }}" class="fw-bold text-decoration-none" style="font-size:1rem">
                {{ $po['po_number'] }}
            </a>
            @if($po['is_overdue'])<span class="badge bg-danger ms-1">OVERDUE</span>@endif
            <span class="text-muted small ms-2">{{ $po['buyer_name'] }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            @php $dL = $po['days_left']; @endphp
            @if($dL > 0)<span class="badge bg-success-subtle text-success">{{ $dL }}h lagi</span>
            @elseif($dL === 0)<span class="badge bg-warning">Hari ini!</span>
            @else<span class="badge bg-danger">Terlambat {{ abs($dL) }}h</span>@endif
            <span class="text-muted small">{{ $po['deadline'] }}</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:160px">Produk</th>
                    <th>Target</th>
                    <th style="min-width:120px">Proses</th>
                    <th>Status Proses</th>
                    <th style="min-width:120px">Packing</th>
                    <th>Status Packing</th>
                    <th>Sisa Box</th>
                </tr>
            </thead>
            <tbody>
                @foreach($po['items'] as $item)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-1 flex-shrink-0" style="width:5px;height:32px;background:{{ $item['color'] }}"></div>
                            <div>
                                <div class="fw-semibold" style="line-height:1.2;color:{{ $item['color'] }}">{{ $item['name'] }}</div>
                                <div class="text-muted" style="font-size:.67rem">{{ $item['group_name'] }} · {{ number_format($item['wpb'],2) }} kg/box</div>
                            </div>
                        </div>
                    </td>
                    <td class="text-nowrap">
                        <div class="fw-semibold">{{ number_format($item['target_box']) }} box</div>
                        <div class="text-muted" style="font-size:.7rem">{{ number_format($item['target_kg'],1) }} kg</div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <div class="flex-grow-1">
                                <div class="progress" style="height:7px">
                                    <div class="progress-bar" style="width:{{ min($item['process_pct'],100) }}%;background:{{ $item['color'] }}"></div>
                                </div>
                            </div>
                            <span class="fw-bold" style="min-width:34px;font-size:.76rem;color:{{ $item['color'] }}">{{ $item['process_pct'] }}%</span>
                        </div>
                        <div class="text-muted" style="font-size:.67rem">{{ number_format($item['processed_kg'],1) }}/{{ number_format($item['target_kg'],1) }} kg</div>
                    </td>
                    <td>
                        @if($item['process_pct'] < 100)
                        <span class="badge bg-danger-subtle">↓{{ number_format(100-$item['process_pct'],1) }}%</span>
                        @else
                        <span class="badge bg-success-subtle">Selesai</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <div class="flex-grow-1">
                                <div class="progress" style="height:7px">
                                    <div class="progress-bar bg-success" style="width:{{ min($item['pack_pct'],100) }}%"></div>
                                </div>
                            </div>
                            <span class="fw-bold text-{{ $item['pack_alert'] }}" style="min-width:34px;font-size:.76rem">{{ $item['pack_pct'] }}%</span>
                        </div>
                        <div class="text-muted" style="font-size:.67rem">{{ $item['packed_box'] }}/{{ $item['target_box'] }} box</div>
                    </td>
                    <td>
                        @if($item['pack_pct'] < 100)
                        <span class="badge bg-warning-subtle">↓{{ number_format(100-$item['pack_pct'],1) }}%</span>
                        @else
                        <span class="badge bg-success-subtle">Selesai</span>
                        @endif
                    </td>
                    <td class="fw-bold text-{{ $item['remaining_box'] > 0 ? 'warning' : 'success' }}" style="font-size:.82rem">
                        {{ $item['remaining_box'] }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="text-center py-5 text-muted form-card mb-3">
    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada PO aktif
    <div class="mt-2"><a href="{{ route('po.create') }}" class="btn btn-sm btn-primary">Buat PO Pertama</a></div>
</div>
@endforelse

<div class="row g-3 mt-1">
    {{-- EARLY WARNING --}}
    <div class="col-12 col-xl-5">
        <div class="stat-card h-100">
            <h6 class="fw-bold mb-3">
                <i class="bi bi-bell me-2"></i>Early Warning
                @if($summary['total_alerts'] > 0)
                <span class="badge bg-danger ms-1">{{ $summary['total_alerts'] }}</span>
                @endif
            </h6>
            @forelse($summary['alerts'] as $alert)
            <div class="d-flex align-items-start gap-2 p-2 mb-2 rounded-2"
                 style="{{ $alert['type']==='danger' ? 'background:var(--red-50)' : 'background:var(--amber-50)' }}">
                <div class="rounded-1 flex-shrink-0 mt-1" style="width:4px;height:38px;background:{{ $alert['color'] }}"></div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between align-items-start gap-1">
                        <div>
                            <span class="fw-semibold" style="font-size:.8rem;color:{{ $alert['color'] }}">{{ $alert['product'] }}</span>
                            <span class="text-muted" style="font-size:.72rem"> · {{ $alert['po_number'] }}</span>
                        </div>
                        @if($alert['po_id'])
                        <a href="{{ route('po.show',$alert['po_id']) }}" class="btn btn-sm btn-outline-secondary">detail</a>
                        @endif
                    </div>
                    <div class="text-muted" style="font-size:.74rem">
                        <i class="bi {{ $alert['category']==='proses' ? 'bi-gear' : ($alert['category']==='packing' ? 'bi-box-seam' : 'bi-shop') }} me-1"></i>
                        {{ $alert['message'] }}
                    </div>
                    <div class="progress mt-1" style="height:4px">
                        <div class="progress-bar {{ $alert['type']==='danger'?'bg-danger':'bg-warning' }}"
                             style="width:{{ $alert['done_pct'] }}%"></div>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center text-muted py-4">
                <i class="bi bi-check-circle fs-2 text-success d-block mb-2"></i>
                Semua produksi on track.
            </div>
            @endforelse
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="row g-3">
            {{-- Stok Bahan Baku per Subgroup --}}
            <div class="col-12">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0" style="font-size:.88rem">Stok Bahan Baku</h6>
                        <a href="{{ route('receiving.create') }}" class="btn btn-sm btn-outline-primary">+ Terima</a>
                    </div>
                    @php $byGroup = collect($stockSummary)->groupBy('group'); @endphp
                    @if($byGroup->count() > 0)
                    @foreach($byGroup as $groupName => $items)
                    <div class="mb-2">
                        <div class="text-muted fw-semibold" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">{{ $groupName }}</div>
                        <div class="row g-1 mt-1">
                            @foreach($items as $s)
                            <div class="col-6">
                                <div class="p-2 rounded-2 bg-light d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-1 min-w-0">
                                        <span class="color-dot flex-shrink-0" style="background:{{ $s['color'] }}"></span>
                                        <span class="text-truncate fw-semibold" style="font-size:.76rem">{{ $s['subgroup'] }}</span>
                                    </div>
                                    <span class="fw-bold flex-shrink-0 ms-1 text-{{ $s['alert'] }}" style="font-size:.82rem">
                                        {{ number_format($s['current_stock_kg'],1) }}<span class="text-muted fw-normal" style="font-size:.65rem"> kg</span>
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                    @else
                    <div class="text-center text-muted small py-2">Belum ada stok</div>
                    @endif
                </div>
            </div>

            {{-- Output harian + Surplus --}}
            <div class="col-12 col-md-6">
                <div class="stat-card h-100">
                    <h6 class="fw-bold mb-2" style="font-size:.85rem">Output Hari Ini</h6>
                    @forelse($dailyStats as $ds)
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="color-dot" style="background:{{ $ds['color'] }}"></span>
                            <span style="font-size:.77rem;font-weight:600">{{ $ds['product'] }}</span>
                            <span class="text-muted" style="font-size:.67rem">{{ $ds['sessions'] }}×</span>
                        </div>
                        <span class="fw-bold" style="font-size:.8rem">{{ $ds['total_kg'] }} kg</span>
                    </div>
                    @empty
                    <div class="text-center text-muted py-3 small">Belum ada proses hari ini</div>
                    @endforelse
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="stat-card h-100">
                    <h6 class="fw-bold mb-2" style="font-size:.85rem">Stok Sisa PO</h6>
                    @forelse($surpluses as $s)
                    <div class="d-flex align-items-center justify-content-between mb-2 p-2 bg-light rounded-2">
                        <div>
                            <span class="badge rounded-pill" style="background:{{ $s['color'] }}22;color:{{ $s['color'] }};border:1px solid {{ $s['color'] }}44;font-size:.67rem">
                                {{ $s['product'] }}
                            </span>
                            <div class="text-muted" style="font-size:.67rem">{{ $s['code'] }} · {{ $s['po_number'] }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold" style="font-size:.8rem">{{ $s['weight_kg'] }} kg</div>
                            @if($s['box_count'] > 0)<div class="text-muted" style="font-size:.67rem">{{ $s['box_count'] }} box</div>@endif
                        </div>
                    </div>
                    @empty
                    <div class="text-center text-muted py-3 small">Tidak ada sisa/surplus</div>
                    @endforelse
                </div>
            </div>

            {{-- Chart 7 hari --}}
            <div class="col-12">
                <div class="stat-card">
                    <h6 class="fw-bold mb-2" style="font-size:.85rem">Output 7 Hari Terakhir</h6>
                    <canvas id="weeklyChart" height="85"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const w = @json($weeklyChart);
new Chart(document.getElementById('weeklyChart'), {
    type: 'bar',
    data: {
        labels: w.map(d => d.date),
        datasets: [{
            data: w.map(d => d.output_kg),
            backgroundColor: 'rgba(23,25,31,.85)',
            borderColor: '#17191F', borderWidth: 0, borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#EFF1F3' }, ticks: { color: '#9BA0A6', font: { family: 'Inter', size: 11 } } },
            x: { grid: { display: false }, ticks: { color: '#9BA0A6', font: { family: 'Inter', size: 11 } } }
        }
    }
});
</script>
@endpush
