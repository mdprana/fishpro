@extends('layouts.app')
@section('title','Detail PO: '.$po->po_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('po.index') }}">Purchase Order</a></li>
    <li class="breadcrumb-item active">{{ $po->po_number }}</li>
@endsection

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('po.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
            <h4 class="fw-bold mb-0">{{ $po->po_number }}</h4>
            <span class="badge rounded-pill bg-{{ match($po->status){'active'=>'primary','in_progress'=>'warning','completed'=>'success',default=>'secondary'} }}">
                {{ ucfirst(str_replace('_',' ',$po->status)) }}
            </span>
        </div>
        <div class="text-muted"><i class="bi bi-building me-1"></i>{{ $po->buyer_name }}</div>
        <div class="text-muted small mt-1">
            Order: {{ $po->order_date->format('d M Y') }} →
            Deadline: <strong class="{{ $po->deadline->isPast()&&$po->status!=='completed'?'text-danger':'' }}">{{ $po->deadline->format('d M Y') }}</strong>
            @php $dl = (int)now()->diffInDays($po->deadline, false); @endphp
            @if($po->status !== 'completed')
                @if($dl > 0)<span class="badge bg-success ms-1">{{ $dl }} hari lagi</span>
                @elseif($dl === 0)<span class="badge bg-warning ms-1">Hari ini!</span>
                @else<span class="badge bg-danger ms-1">Terlambat {{ abs($dl) }}h</span>@endif
            @endif
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('receiving.create') }}?po_id={{ $po->id }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-in-down me-1"></i>Terima Bahan</a>
        @foreach(\App\Models\ProcessStage::active()->ordered()->get() as $navStage)
        <a href="{{ route('stage.create', $navStage->code) }}?po_id={{ $po->id }}" class="btn btn-outline-warning btn-sm">
            <i class="bi {{ $navStage->icon }} me-1"></i>{{ \Illuminate\Support\Str::of($navStage->name)->before(' (') }}
        </a>
        @endforeach
        <a href="{{ route('packing.create') }}?po_id={{ $po->id }}" class="btn btn-outline-success btn-sm"><i class="bi bi-box-seam me-1"></i>Input Packing</a>
        <a href="{{ route('packing.boxes', ['po_id' => $po->id]) }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-list-ul me-1"></i>Rekap Box</a>
        <a href="{{ route('po.edit',$po) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
    </div>
</div>

{{-- Timeline proses: tanggal bongkar, potong, trimming, steak, packing --}}
<div class="form-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h6 class="fw-bold mb-0">Timeline Proses</h6>
        <div class="text-end">
            <span class="text-muted" style="font-size:.7rem">Recovery</span>
            @php $rec = $po->recovery_pct; @endphp
            <span class="fw-bold ms-1 text-{{ $rec >= 45 ? 'success' : ($rec > 0 ? 'warning' : 'secondary') }}">
                {{ number_format($rec, 1) }}%
            </span>
            <span class="text-muted" style="font-size:.7rem">
                ({{ number_format($po->total_packed_kg, 1) }} / {{ number_format($po->total_received_kg, 1) }} kg)
            </span>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        @foreach($timeline as $i => $step)
        <div class="flex-grow-1 p-2 rounded-3 border text-center"
             style="min-width:130px;{{ $step['first'] ? 'border-color:'.$step['color'].'55;background:'.$step['color'].'0d' : 'opacity:.55' }}">
            <div class="mb-1">
                <i class="bi {{ $step['icon'] }}" style="color:{{ $step['color'] }}"></i>
                <span class="fw-semibold ms-1" style="font-size:.76rem">{{ \Illuminate\Support\Str::of($step['name'])->before(' (') }}</span>
            </div>
            @if($step['first'])
                <div class="fw-bold" style="font-size:.78rem">
                    {{ \Illuminate\Support\Carbon::parse($step['first'])->format('d/m/y') }}
                    @if($step['last'] && $step['last'] != $step['first'])
                        <span class="text-muted">- {{ \Illuminate\Support\Carbon::parse($step['last'])->format('d/m') }}</span>
                    @endif
                </div>
                <div class="text-muted" style="font-size:.66rem">
                    {{ $step['count'] }} {{ $step['code'] === 'packing' ? 'box' : ($step['code'] === 'receiving' ? 'terima' : 'sesi') }}
                    · {{ number_format($step['kg'], 1) }} kg
                </div>
                @if($step['yield'] !== null)
                    <span class="badge bg-{{ $step['yield'] >= 85 ? 'success' : ($step['yield'] >= 70 ? 'warning' : 'danger') }}-subtle
                                 text-{{ $step['yield'] >= 85 ? 'success' : ($step['yield'] >= 70 ? 'warning' : 'danger') }} mt-1"
                          style="font-size:.62rem">Yield {{ $step['yield'] }}%</span>
                @endif
            @else
                <div class="text-muted" style="font-size:.74rem">Belum ada</div>
            @endif
        </div>
        @if($i < count($timeline) - 1)
        <div class="d-none d-lg-flex align-items-center text-muted"><i class="bi bi-chevron-right"></i></div>
        @endif
        @endforeach
    </div>
</div>

{{-- Progress per produk --}}
<div class="form-card mb-4">
    <h6 class="fw-bold mb-3">Progress per Produk</h6>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" style="font-size:.83rem">
            <thead>
                <tr>
                    <th>Produk</th><th>Target</th>
                    <th style="min-width:130px">Proses</th>
                    <th style="min-width:130px">Packing</th>
                    <th>Stok Siap Pack</th><th>Sisa Box</th>
                </tr>
            </thead>
            <tbody>
                @foreach($po->items as $item)
                @php
                    $typeId      = $item->product_type_id;
                    $targetKg    = (float)$item->target_weight_kg;
                    $targetBox   = (int)$item->target_box;
                    // Hasil produksi produk ini = lot yang pernah terbit untuknya,
                    // diambil dari $semiFinished agar tidak query per baris.
                    $sfRow       = $semiFinished->firstWhere('product_type_id',$typeId);
                    $processedKg = $sfRow ? (float)$sfRow['output_kg'] : 0.0;
                    $packedBox   = (int)$item->packed_box;
                    $packedKg    = (float)$item->packed_kg;
                    $processPct  = $targetKg > 0 ? round(($processedKg/$targetKg)*100,1) : 0;
                    $packPct     = $targetBox > 0 ? round(($packedBox/$targetBox)*100,1) : 0;
                    $available   = $sfRow ? $sfRow['available'] : 0;
                    $color       = $item->productType->effective_color;
                @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-1" style="width:5px;height:30px;background:{{ $color }}"></div>
                            <div>
                                <div class="fw-semibold" style="color:{{ $color }}">{{ $item->productType->name }}</div>
                                <div class="text-muted" style="font-size:.68rem">
                                    {{ $item->productType->group_name }} · {{ number_format($item->effective_weight_per_box,2) }} kg/box
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $targetBox }} box</div>
                        <div class="text-muted" style="font-size:.7rem">{{ number_format($targetKg,1) }} kg</div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <div class="flex-grow-1">
                                <div class="progress" style="height:7px">
                                    <div class="progress-bar" style="width:{{ min($processPct,100) }}%;background:{{ $color }}"></div>
                                </div>
                            </div>
                            <span class="fw-bold" style="min-width:34px;font-size:.76rem;color:{{ $color }}">{{ $processPct }}%</span>
                        </div>
                        <div class="text-muted" style="font-size:.68rem">{{ number_format($processedKg,1) }}/{{ number_format($targetKg,1) }} kg</div>
                        @if($processPct < 100)
                        <span class="badge bg-danger-subtle">↓{{ number_format(100-$processPct,1) }}%</span>
                        @else
                        <span class="badge bg-success-subtle">Selesai</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <div class="flex-grow-1">
                                <div class="progress" style="height:7px">
                                    <div class="progress-bar bg-success" style="width:{{ min($packPct,100) }}%"></div>
                                </div>
                            </div>
                            <span class="fw-bold text-{{ $packPct>=100?'success':($packPct>=50?'warning':'danger') }}" style="min-width:34px;font-size:.76rem">{{ $packPct }}%</span>
                        </div>
                        <div class="text-muted" style="font-size:.68rem">{{ $packedBox }}/{{ $targetBox }} box · {{ number_format($packedKg,1) }} kg</div>
                        @if($packPct < 100)
                        <span class="badge bg-warning-subtle">↓{{ number_format(100-$packPct,1) }}%</span>
                        @else
                        <span class="badge bg-success-subtle">Selesai</span>
                        @endif
                    </td>
                    <td>
                        <span class="fw-bold text-{{ $available>0?'success':($available==0&&$processedKg>0?'warning':'muted') }}">
                            {{ number_format($available,1) }} kg
                        </span>
                    </td>
                    <td class="fw-bold text-{{ $item->remaining_box>0?'warning':'success' }}">
                        {{ $item->remaining_box }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Surplus --}}
@if($surpluses->count() > 0)
<div class="form-card mb-4" style="border-left:3px solid #fbbf24">
    <h6 class="fw-bold mb-3">Stok Sisa / Surplus dari PO ini</h6>
    <div class="row g-2">
        @foreach($surpluses as $s)
        <div class="col-6 col-md-3">
            <div class="p-3 rounded-3" style="background:var(--amber-50)">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="color-dot" style="background:{{ $s->productType->effective_color }}"></span>
                    <span class="fw-semibold small">{{ $s->productType->name }}</span>
                </div>
                <div class="fw-bold" style="font-size:1.2rem">{{ $s->weight_kg }} kg</div>
                @if($s->box_count > 0)<div class="text-muted small">{{ $s->box_count }} box</div>@endif
                <div class="d-flex justify-content-between mt-1">
                    <span class="badge" style="background:#FBF0DA;color:#A66A08;font-size:.64rem">{{ $s->getTypeLabel() }}</span>
                    <span class="text-muted" style="font-size:.67rem">{{ $s->surplus_code }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Tabs --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSession">
        <i class="bi bi-gear me-1"></i>Sesi Proses <span class="badge bg-secondary ms-1">{{ $po->stageRecords->count() }}</span>
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPack">
        <i class="bi bi-box-seam me-1"></i>Packing <span class="badge bg-secondary ms-1">{{ $po->packingBoxes->where('status','!=','voided')->count() }}</span>
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabReceiving">
        <i class="bi bi-box-arrow-in-down me-1"></i>Bahan Baku <span class="badge bg-secondary ms-1">{{ $po->receivings->count() }}</span>
    </button></li>
</ul>

<div class="tab-content">
    {{-- Sesi Proses --}}
    <div class="tab-pane fade show active" id="tabSession">
        <div class="form-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="fw-bold mb-0">Riwayat Sesi Proses</h6>
                <a href="{{ route('production.analisa', ['po_id' => $po->id]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-graph-up-arrow me-1"></i>Analisa PO
                </a>
            </div>
            @forelse($po->stageRecords->sortBy('process_date') as $s)
            <div class="p-3 mb-2 rounded-3 border {{ $s->is_voided ? 'opacity-50' : '' }}">
                <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                    <div>
                        <a href="{{ route('stage.show', $s) }}" class="fw-semibold text-decoration-none"
                           style="font-family:ui-monospace,monospace">{{ $s->record_code }}</a>
                        <span class="badge ms-1" style="background:{{ $s->stage->color }}22;color:{{ $s->stage->color }};font-size:.7rem">
                            {{ $s->stage->name }}
                        </span>
                        <span class="text-muted small ms-2">{{ $s->process_date->format('d M Y') }}</span>
                        @if($s->operator_name)
                            <span class="badge bg-light text-dark ms-1" style="font-size:.7rem">{{ $s->operator_name }}</span>
                        @endif
                        @if($s->is_voided)
                            <span class="badge bg-danger ms-1" style="font-size:.66rem">Dibatalkan</span>
                        @endif
                        <div class="text-muted mt-1" style="font-size:.72rem">
                            Masuk {{ number_format((float)$s->total_input_kg, 2) }} kg ·
                            Limbah {{ number_format((float)$s->total_waste_kg, 2) }} kg
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-success small">{{ number_format((float)$s->total_output_kg, 2) }} kg</div>
                        @php $y = (float) $s->yield_pct; @endphp
                        <span class="badge bg-{{ $y>=85?'success':($y>=70?'warning':'danger') }}-subtle
                                     text-{{ $y>=85?'success':($y>=70?'warning':'danger') }}" style="font-size:.68rem">
                            Yield {{ number_format($y, 1) }}%
                        </span>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-4 text-muted small">
                Belum ada sesi proses. Mulai dari tombol tahap di atas.
            </div>
            @endforelse
        </div>
    </div>

    {{-- Packing --}}
    <div class="tab-pane fade" id="tabPack">
        <div class="form-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="fw-bold mb-0">Box Hasil Packing</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('packing.boxes', ['po_id' => $po->id]) }}" class="btn btn-sm btn-outline-secondary">Rekap Box</a>
                    <a href="{{ route('packing.create') }}?po_id={{ $po->id }}" class="btn btn-sm btn-success">+ Packing</a>
                </div>
            </div>

            {{-- Ringkas per produk; rincian tiap box ada di halaman Rekap Box. --}}
            @php $boxes = $po->packingBoxes->where('status','!=','voided'); @endphp
            @forelse($boxes->groupBy('product_type_id') as $ptId => $group)
            @php $pt = $group->first()->productType; @endphp
            <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-light rounded-3">
                <div>
                    <span class="badge rounded-pill" style="background:{{ $pt->effective_color }}22;color:{{ $pt->effective_color }};border:1px solid {{ $pt->effective_color }}44">
                        {{ $pt->name }}
                    </span>
                    <div class="small fw-semibold mt-1">
                        {{ $group->count() }} box · {{ number_format($group->sum('net_weight_kg'), 2) }} kg
                        @if($group->where('is_mixed_batch', true)->count())
                            <span class="badge bg-warning-subtle text-warning ms-1" style="font-size:.64rem">
                                {{ $group->where('is_mixed_batch', true)->count() }} box campuran
                            </span>
                        @endif
                    </div>
                    <div class="text-muted" style="font-size:.7rem">
                        {{ $group->min('packing_date')?->format('d M Y') }}
                        @if($group->min('packing_date') != $group->max('packing_date'))
                            - {{ $group->max('packing_date')?->format('d M Y') }}
                        @endif
                    </div>
                </div>
                <a href="{{ route('packing.boxes', ['po_id' => $po->id, 'product_id' => $ptId]) }}"
                   class="btn btn-sm btn-outline-primary">Lihat Box</a>
            </div>
            @empty
            <div class="text-center py-4 text-muted small">Belum ada box packing</div>
            @endforelse
        </div>
    </div>

    {{-- Receiving --}}
    <div class="tab-pane fade" id="tabReceiving">
        <div class="form-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Bahan Baku untuk PO ini</h6>
                <a href="{{ route('receiving.create') }}?po_id={{ $po->id }}" class="btn btn-sm btn-primary">+ Terima</a>
            </div>
            @forelse($po->receivings as $r)
            <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-light rounded-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="color-dot" style="background:{{ $r->subgroup->group->color ?? '#6b7280' }}"></span>
                        <span class="fw-semibold small">{{ $r->subgroup->name }}</span>
                        <span class="text-muted" style="font-size:.7rem">{{ $r->subgroup->group->name ?? '' }}</span>
                        <span class="badge bg-secondary-subtle text-secondary" style="font-size:.68rem">{{ $r->container_type }} ×{{ $r->container_count }}</span>
                    </div>
                    <div class="text-muted" style="font-size:.76rem">
                        {{ $r->weight_kg }} kg
                        @if($r->supplier_id) · {{ $r->supplier_label }}@endif
                        @if($r->batch_code) · {{ $r->batch_code }}@endif
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">{{ $r->received_date->format('d M Y') }}</div>
                    <form method="POST" action="{{ route('receiving.destroy',$r) }}" onsubmit="return confirm('Hapus? Stok disesuaikan.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger mt-1"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
            @empty
            <div class="text-center py-4 text-muted small">Belum ada penerimaan bahan baku</div>
            @endforelse
        </div>

        {{-- Batch induk: dari supplier mana saja bahan PO ini berasal, dan sisanya berapa --}}
        <div class="form-card mt-3">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="fw-bold mb-0">Batch Bahan Baku</h6>
                @if($batches->count())
                <a href="{{ route('batch.labels', ['ids' => $batches->pluck('id')->implode(',')]) }}"
                   class="btn btn-sm btn-outline-primary"><i class="bi bi-printer me-1"></i>Cetak Semua Label</a>
                @endif
            </div>

            @if($batches->count())
            <div class="table-responsive">
                <table class="table table-custom table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nomor Batch</th><th>Supplier</th><th>Bahan</th>
                            <th class="text-end">Masuk</th><th class="text-end">Sisa</th>
                            <th>Tgl Bongkar</th><th class="text-center">Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batches as $b)
                        <tr>
                            <td>
                                <a href="{{ route('batch.show', $b) }}"
                                   style="font-family:ui-monospace,monospace;font-size:.72rem">{{ $b->batch_number }}</a>
                            </td>
                            <td class="small">
                                <span class="badge bg-dark-subtle text-dark">{{ $b->supplier?->code ?? '-' }}</span>
                                <span class="text-muted" style="font-size:.68rem">{{ $b->supplier?->name }}</span>
                            </td>
                            <td class="small">{{ $b->subgroup?->name }}</td>
                            <td class="text-end">{{ number_format((float)$b->initial_weight_kg, 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float)$b->current_weight_kg, 2) }}</td>
                            <td class="small">{{ $b->effective_date?->format('d/m/Y') }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $b->status_color }}-subtle text-{{ $b->status_color }}"
                                      style="font-size:.64rem">{{ $b->status_label }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('batch.label', $b) }}" class="btn btn-sm btn-outline-secondary" title="Cetak label">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-muted small mb-0">Batch terbit otomatis saat bahan baku diterima.</p>
            @endif
        </div>
    </div>
</div>
@endsection
