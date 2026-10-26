<?php

namespace App\Http\Controllers;

use App\Models\BatchMovement;
use App\Models\BatchStageInput;
use App\Models\Receiving;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiwayatController extends Controller
{
    public function index(Request $request): View
    {
        // Masuk: penerimaan bahan baku
        $receivings = Receiving::with(['subgroup.group', 'purchaseOrder', 'supplier', 'creator'])
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('received_date', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('received_date', '<=', $request->date_to))
            ->orderByDesc('received_date')
            ->paginate(20, ['*'], 'page_in')->withQueryString();

        // Keluar: bahan yang dipakai tiap sesi tahap
        $keluars = BatchStageInput::with([
                'batch.subgroup.group', 'batch.supplier',
                'record.purchaseOrder', 'record.stage',
            ])
            ->whereHas('record', fn ($q) => $q->where('status', 'completed'))
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'page_out')->withQueryString();

        // Ledger mentah, jejak audit yang tidak pernah diubah.
        $movements = BatchMovement::with(['batch.subgroup', 'batch.supplier', 'creator'])
            ->when($request->filled('type'), fn ($q) => $q->where('movement_type', $request->type))
            ->orderByDesc('id')
            ->paginate(30, ['*'], 'page_ledger')->withQueryString();

        return view('riwayat.index', compact('receivings', 'keluars', 'movements'));
    }
}
