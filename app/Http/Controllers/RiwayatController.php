<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\BatchMovement;
use App\Models\BatchStageInput;
use App\Models\Receiving;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RiwayatController extends Controller
{
    public function index(Request $request): View
    {
        // Gate dicek sebelum query audit apa pun, termasuk ?tab=audit langsung.
        $canAudit = Gate::allows('view-audit');
        if ($request->query('tab') === 'audit' && !$canAudit) {
            abort(403);
        }

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

        $audits = $canAudit ? $this->audits($request) : null;

        return view('riwayat.index', compact('receivings', 'keluars', 'movements', 'audits'));
    }

    private function audits(Request $request)
    {
        $f = $request->validate([
            'audit_from'   => 'nullable|date_format:Y-m-d',
            'audit_to'     => 'nullable|date_format:Y-m-d',
            'audit_actor'  => 'nullable|integer|min:1',
            'audit_entity' => ['nullable', Rule::in(array_values(AuditService::ENTITIES))],
            'audit_id'     => 'nullable|integer|min:1',
            'audit_action' => ['nullable', Rule::in(array_keys(AuditService::ACTIONS))],
        ]);

        // Rentang tanggal lokal diubah ke UTC karena recorded_at disimpan UTC.
        $tz = config('app.timezone');

        return AuditEvent::with('actor:id,name')
            ->when($f['audit_from'] ?? null, fn ($q, $d) => $q->where('recorded_at', '>=', Carbon::parse($d, $tz)->startOfDay()->utc()->format('Y-m-d H:i:s')))
            ->when($f['audit_to'] ?? null, fn ($q, $d) => $q->where('recorded_at', '<=', Carbon::parse($d, $tz)->endOfDay()->utc()->format('Y-m-d H:i:s')))
            ->when($f['audit_actor'] ?? null, fn ($q, $v) => $q->where('actor_id', $v))
            ->when($f['audit_entity'] ?? null, fn ($q, $v) => $q->where('entity_type', $v))
            ->when($f['audit_id'] ?? null, fn ($q, $v) => $q->where('entity_id', $v))
            ->when($f['audit_action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->orderByDesc('id')
            ->paginate(30, ['*'], 'page_audit')->withQueryString();
    }
}
