@extends('layouts.app')
@section('title','Purchase Order')
@section('breadcrumb')<li class="breadcrumb-item active">Purchase Order</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Purchase Order</h4>
        <p class="text-muted mb-0 small">Kelola semua order produksi</p>
    </div>
    <a href="{{ route('po.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Buat PO</a>
</div>

<div class="form-card">
    <div class="table-responsive">
        <table class="table table-custom table-hover align-middle">
            <thead><tr>
                <th>No. PO</th><th>Buyer</th><th>Produk</th>
                <th class="text-center">Bongkar</th>
                @foreach($stages as $st)
                <th class="text-center">{{ \Illuminate\Support\Str::of($st->name)->before(' (') }}</th>
                @endforeach
                <th class="text-center">Packing</th>
                <th>Total Box</th><th>Progress</th>
                <th>Deadline</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
                @forelse($pos as $po)
                @php
                    $totalBox  = $po->items->sum('target_box');
                    $packedBox = $po->items->sum(fn($i) => $i->packed_box);
                    $pct       = $totalBox > 0 ? round(($packedBox/$totalBox)*100,1) : 0;
                    $daysLeft  = $po->days_remaining_raw;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('po.show',$po) }}" class="fw-bold text-decoration-none">{{ $po->po_number }}</a>
                    </td>
                    <td class="small">{{ $po->buyer_name }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($po->items->take(4) as $item)
                            <span class="badge rounded-pill dyn-text"
                                  style="background:{{ $item->productType->effective_color }}22;--c:{{ $item->productType->effective_color }};border:1px solid {{ $item->productType->effective_color }}44;font-size:.67rem">
                                {{ $item->productType->name }}
                            </span>
                            @endforeach
                            @if($po->items_count > 4)
                            <span class="text-muted small">+{{ $po->items_count - 4 }}</span>
                            @endif
                        </div>
                    </td>
                    {{-- Tanggal tiap tahap. Nilainya berasal dari withStageDates()
                         sehingga tidak ada query tambahan per baris. --}}
                    @php
                        $kolomTanggal = [
                            ['first' => $po->first_receiving_date, 'last' => $po->last_receiving_date],
                        ];
                        foreach ($stages as $st) {
                            $kolomTanggal[] = [
                                'first' => $po->{"first_{$st->code}_date"},
                                'last'  => $po->{"last_{$st->code}_date"},
                            ];
                        }
                        $kolomTanggal[] = ['first' => $po->first_packing_date, 'last' => $po->last_packing_date];
                    @endphp
                    @foreach($kolomTanggal as $kol)
                    <td class="text-center text-nowrap" style="font-size:.72rem">
                        @if($kol['first'])
                            {{ \Illuminate\Support\Carbon::parse($kol['first'])->format('d/m/y') }}
                            @php
                                $hari = (int) \Illuminate\Support\Carbon::parse($kol['first'])->startOfDay()
                                        ->diffInDays(\Illuminate\Support\Carbon::parse($kol['last'])->startOfDay());
                            @endphp
                            @if($hari > 0)
                                <div class="text-muted" style="font-size:.62rem">+{{ $hari }} hari</div>
                            @endif
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    @endforeach

                    <td class="fw-bold">{{ $totalBox }}</td>
                    <td style="min-width:130px">
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <div class="progress" style="height:7px">
                                    <div class="progress-bar bg-{{ $pct>=100?'success':($pct>=50?'warning':'danger') }}"
                                         style="width:{{ min($pct,100) }}%"></div>
                                </div>
                            </div>
                            <small class="fw-bold text-{{ $pct>=100?'success':($pct>=50?'warning':'danger') }}" style="min-width:36px">{{ $pct }}%</small>
                        </div>
                        <small class="text-muted" style="font-size:.68rem">{{ $packedBox }}/{{ $totalBox }} box</small>
                    </td>
                    <td class="small text-nowrap">
                        <span class="{{ $daysLeft < 0 && $po->status !== 'completed' ? 'text-danger fw-semibold' : ($daysLeft <= 3 ? 'text-warning fw-semibold' : '') }}">
                            {{ $po->deadline->format('d M Y') }}
                        </span>
                        @if($po->status !== 'completed')
                        <div class="text-muted" style="font-size:.7rem">
                            @if($daysLeft > 0){{ $daysLeft }}h lagi
                            @elseif($daysLeft == 0)Hari ini
                            @else<span class="text-danger">Terlambat {{ abs($daysLeft) }}h</span>
                            @endif
                        </div>
                        @endif
                    </td>
                    <td>
                        <span class="badge rounded-pill bg-{{ match($po->status){'active'=>'primary','in_progress'=>'warning','completed'=>'success',default=>'secondary'} }}">
                            {{ ucfirst(str_replace('_',' ',$po->status)) }}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('po.show',$po) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('po.edit',$po) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('po.destroy',$po) }}"
                                  onsubmit="return confirm('Hapus PO {{ $po->po_number }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ 10 + $stages->count() }}" class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-x fs-2 d-block mb-2"></i>Belum ada PO
                    <div class="mt-2"><a href="{{ route('po.create') }}" class="btn btn-sm btn-primary">Buat PO Pertama</a></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $pos->links() }}</div>
</div>
@endsection
