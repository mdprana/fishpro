<?php

namespace App\Http\Controllers;

use App\Models\MaterialGroup;
use App\Models\ProcessStage;
use App\Models\YieldStandard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Standar toleransi penyusutan per bahan per tahap.
 *
 * Perubahan tidak pernah menimpa: baris lama ditutup (effective_to) dan baris
 * baru disisipkan, supaya hasil produksi lama tetap dinilai dengan standar
 * yang berlaku saat itu.
 */
class YieldStandardController extends Controller
{
    public function index(): View
    {
        return view('master.yield_standards', [
            'stages'  => ProcessStage::active()->ordered()->get(),
            'groups'  => MaterialGroup::active()->with(['subgroups' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'aktif'   => YieldStandard::effectiveOn(today())
                            ->with(['subgroup.group', 'stage'])
                            ->orderBy('material_subgroup_id')->get(),
            'riwayat' => YieldStandard::whereNotNull('effective_to')
                            ->with(['subgroup', 'stage', 'creator'])
                            ->orderByDesc('effective_to')->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material_subgroup_id' => 'nullable|exists:material_subgroups,id',
            'process_stage_id'     => 'nullable|exists:process_stages,id',
            'min_yield_pct'        => 'required|numeric|min:0|max:100',
            'warn_yield_pct'       => 'nullable|numeric|min:0|max:100|gte:min_yield_pct',
            'target_yield_pct'     => 'nullable|numeric|min:0|max:100|gte:min_yield_pct',
            'effective_from'       => 'required|date',
            'notes'                => 'nullable|string|max:500',
        ], [
            'warn_yield_pct.gte'   => 'Ambang perhatian tidak boleh di bawah batas minimal.',
            'target_yield_pct.gte' => 'Target tidak boleh di bawah batas minimal.',
        ]);

        // Field nullable yang dikosongkan tidak muncul di hasil validate(),
        // jadi disamakan dulu agar pencocokan versi lama tidak meleset.
        $data += ['material_subgroup_id' => null, 'process_stage_id' => null];

        DB::transaction(function () use ($data) {
            $berlakuDari = \Illuminate\Support\Carbon::parse($data['effective_from']);

            // Tutup versi sebelumnya sehari sebelum versi baru mulai berlaku,
            // sehingga tidak ada dua standar yang berlaku pada hari yang sama.
            YieldStandard::query()
                ->where('scope', 'stage')
                ->where('material_subgroup_id', $data['material_subgroup_id'])
                ->where('process_stage_id', $data['process_stage_id'])
                ->whereNull('effective_to')
                ->update(['effective_to' => $berlakuDari->copy()->subDay()]);

            YieldStandard::create($data + [
                'scope'      => 'stage',
                'is_active'  => true,
                'created_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Standar yield baru berlaku. Versi sebelumnya tetap tersimpan sebagai riwayat.');
    }

    /** Hentikan sebuah standar tanpa menghapus jejaknya. */
    public function close(YieldStandard $standard): RedirectResponse
    {
        $standard->update(['effective_to' => today(), 'is_active' => false]);

        return back()->with('success', 'Standar dihentikan per hari ini dan tersimpan sebagai riwayat.');
    }
}
