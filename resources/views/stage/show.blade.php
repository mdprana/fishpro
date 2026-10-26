@extends('layouts.app')
@section('title','Detail Sesi')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('stage.index', $record->stage->code) }}">{{ $record->stage->name }}</a></li>
    <li class="breadcrumb-item active">{{ $record->record_code }}</li>
@endsection

@section('content')
@if(session('yield_alerts'))
    @foreach(session('yield_alerts') as $alert)
    <div class="alert alert-{{ $alert['level'] === 'below' ? 'danger' : 'warning' }}">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $alert['message'] }}
    </div>
    @endforeach
@endif

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1" style="font-family:ui-monospace,monospace">{{ $record->record_code }}</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="badge" style="background:{{ $record->stage->color }}22;color:{{ $record->stage->color }}">
                {{ $record->stage->name }}
            </span>
            <span class="text-muted small">{{ $record->process_date->format('d M Y') }}</span>
            @if($record->is_voided)
                <span class="badge bg-danger-subtle text-danger">Dibatalkan</span>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2">
        @php $lots = $record->outputs->whereNotNull('output_batch_id')->pluck('output_batch_id')->implode(','); @endphp
        @if($lots && !$record->is_voided)
        <a href="{{ route('batch.labels', ['ids' => $lots]) }}" class="btn btn-primary">
            <i class="bi bi-printer me-1"></i>Cetak Label Lot Hasil
        </a>
        @endif
        @if(!$record->is_voided)
        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#voidModal">
            <i class="bi bi-x-circle me-1"></i>Batalkan
        </button>
        @endif
    </div>
</div>

@if($record->is_voided)
<div class="alert alert-danger">
    <strong>Sesi dibatalkan</strong> oleh {{ $record->voider?->name ?? '-' }}
    pada {{ $record->voided_at?->format('d/m/Y H:i') }}. Alasan: {{ $record->void_reason }}
    <div class="small mt-1">Bahan sudah dikembalikan ke batch asal; seluruh langkahnya tetap tercatat di ledger.</div>
</div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="form-card mb-3">
            <h6 class="fw-bold mb-3">Ringkasan</h6>
            <table class="table table-sm mb-0" style="font-size:.85rem">
                <tr><td class="text-muted">Nomor PO</td><td class="text-end">
                    <a href="{{ route('po.show', $record->purchaseOrder) }}">{{ $record->purchaseOrder->po_number }}</a>
                </td></tr>
                <tr><td class="text-muted">Operator</td><td class="text-end">{{ $record->operator_name ?: '-' }}</td></tr>
                <tr><td class="text-muted">Jam</td><td class="text-end">
                    {{ $record->start_time ? substr($record->start_time,0,5) : '-' }} -
                    {{ $record->end_time ? substr($record->end_time,0,5) : '-' }}
                </td></tr>
                <tr><td class="text-muted">Shift / Line</td><td class="text-end">{{ $record->shift ?: '-' }} / {{ $record->line_name ?: '-' }}</td></tr>
                <tr class="border-top"><td class="text-muted">Bahan masuk</td><td class="text-end fw-semibold">{{ number_format((float)$record->total_input_kg, 3) }} kg</td></tr>
                <tr><td class="text-muted">Hasil utama</td><td class="text-end fw-semibold text-success">{{ number_format($record->main_output_kg, 3) }} kg</td></tr>
                <tr><td class="text-muted">Sampingan</td><td class="text-end">{{ number_format($record->byproduct_kg, 3) }} kg</td></tr>
                <tr><td class="text-muted">Limbah</td><td class="text-end text-danger">{{ number_format((float)$record->total_waste_kg, 3) }} kg</td></tr>
                <tr><td class="text-muted">Tak tercatat</td>
                    <td class="text-end {{ abs($record->unaccounted_kg) > 0.01 ? 'text-warning fw-semibold' : 'text-muted' }}">
                        {{ number_format($record->unaccounted_kg, 3) }} kg
                    </td></tr>
                <tr><td class="text-muted">Dicatat oleh</td><td class="text-end">{{ $record->creator?->name ?? '-' }}</td></tr>
            </table>
        </div>

        @php
            $level = $evaluation['level'] ?? 'none';
            $bg    = ['below' => '#FDE8E5', 'warn' => '#FBF0DA', 'ok' => '#E2F4E6'][$level] ?? '#F5F7F9';
            $color = ['below' => 'danger', 'warn' => 'warning', 'ok' => 'success'][$level] ?? 'secondary';
        @endphp
        <div class="form-card text-center" style="background:{{ $bg }}">
            <div class="text-muted" style="font-size:.72rem">Yield Aktual</div>
            <div class="fw-bold text-{{ $color }}" style="font-size:2.4rem">{{ number_format((float)$record->yield_pct, 1) }}%</div>
            @if($standard)
                <div class="small text-muted">
                    Standar minimal {{ rtrim(rtrim(number_format((float)$standard->min_yield_pct, 2), '0'), '.') }}%
                    @if($standard->target_yield_pct)
                        · target {{ rtrim(rtrim(number_format((float)$standard->target_yield_pct, 2), '0'), '.') }}%
                    @endif
                </div>
                @if(!empty($evaluation['message']))
                <div class="alert alert-{{ $color }} py-2 px-3 small mt-2 mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $evaluation['message'] }}
                </div>
                @endif
            @else
                <div class="small text-muted">Standar belum diatur untuk bahan ini.</div>
            @endif
            <div class="text-muted mt-2" style="font-size:.68rem">Susut {{ number_format($record->waste_pct, 1) }}% dari bahan masuk</div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="form-card mb-3">
            <h6 class="fw-bold mb-3">A. Bahan Masuk</h6>
            <div class="table-responsive">
                <table class="table table-custom table-sm align-middle mb-0">
                    <thead><tr><th>Batch</th><th>Supplier</th><th>Bahan</th><th class="text-end">Dipakai</th></tr></thead>
                    <tbody>
                        @foreach($record->inputs as $in)
                        <tr>
                            <td>
                                <a href="{{ route('batch.show', $in->batch) }}" style="font-family:ui-monospace,monospace;font-size:.72rem">
                                    {{ $in->batch->batch_number }}
                                </a>
                            </td>
                            <td class="small">{{ $in->batch->supplier?->code ?? '-' }}</td>
                            <td class="small">{{ $in->batch->subgroup?->name ?? '-' }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float)$in->input_weight_kg, 3) }} kg</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-card mb-3">
            <h6 class="fw-bold mb-3">B. Hasil &amp; Limbah</h6>
            <div class="table-responsive">
                <table class="table table-custom table-sm align-middle mb-0">
                    <thead><tr><th>Jenis</th><th>Keterangan</th><th>Lot Hasil</th><th class="text-end">Berat</th></tr></thead>
                    <tbody>
                        @foreach($record->outputs as $out)
                        <tr>
                            <td>
                                <span class="badge {{ match($out->output_type) {
                                    'utama' => 'bg-success-subtle text-success',
                                    'sampingan' => 'bg-secondary-subtle text-secondary',
                                    default => 'bg-danger-subtle text-danger',
                                } }}" style="font-size:.66rem">{{ ucfirst($out->output_type) }}</span>
                            </td>
                            <td class="small">{{ $out->label }}</td>
                            <td>
                                @if($out->outputBatch)
                                <a href="{{ route('batch.show', $out->outputBatch) }}" style="font-family:ui-monospace,monospace;font-size:.7rem">
                                    {{ $out->outputBatch->short_code }}
                                </a>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">{{ number_format((float)$out->output_weight_kg, 3) }} kg</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($record->links->count())
        <div class="form-card">
            <h6 class="fw-bold mb-1">C. Asal Tiap Hasil</h6>
            <p class="text-muted small mb-3">
                Berapa kg dari tiap batch masuk menyumbang ke tiap lot hasil.
                Inilah yang membuat satu box packing nanti bisa menyebut semua asal-usulnya.
            </p>
            <div class="table-responsive">
                <table class="table table-custom table-sm align-middle mb-0">
                    <thead><tr><th>Lot Hasil</th><th>Berasal Dari</th><th class="text-end">Kontribusi</th><th class="text-end">%</th><th>Metode</th></tr></thead>
                    <tbody>
                        @foreach($record->links as $link)
                        <tr>
                            <td style="font-family:ui-monospace,monospace;font-size:.7rem">{{ $link->outputBatch?->short_code ?? '-' }}</td>
                            <td style="font-family:ui-monospace,monospace;font-size:.7rem">{{ $link->sourceBatch->short_code }}</td>
                            <td class="text-end">{{ number_format((float)$link->contributed_kg, 3) }} kg</td>
                            <td class="text-end">{{ number_format((float)$link->contribution_pct, 1) }}%</td>
                            <td class="small text-muted">{{ $link->allocation_method === 'manual' ? 'Manual' : 'Proporsional' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($record->notes)
        <div class="form-card mt-3">
            <h6 class="fw-bold mb-2">Catatan</h6>
            <p class="mb-0 small">{{ $record->notes }}</p>
        </div>
        @endif
    </div>
</div>

@if(!$record->is_voided)
<div class="modal fade" id="voidModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('stage.void', $record) }}">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Batalkan Sesi {{ $record->record_code }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Bahan akan dikembalikan ke batch asal dan lot hasil sesi ini dibatalkan.
                    Tidak ada data yang dihapus, semuanya dicatat sebagai pembatalan di ledger.
                </p>
                <label class="form-label">Alasan pembatalan <span class="text-danger">*</span></label>
                <textarea name="void_reason" rows="3" class="form-control" required maxlength="500"></textarea>
                @error('void_reason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-danger">Batalkan Sesi</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
