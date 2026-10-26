<?php

namespace App\Http\Controllers;

use App\Models\PackingBox;
use App\Models\ProductionBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TraceabilityController extends Controller
{
    public function index(): View
    {
        return view('trace.index');
    }

    /**
     * Terima kode apa pun yang diketik atau di-scan operator: token QR, nomor
     * batch, atau kode box. Lalu arahkan ke halaman telusur yang tepat.
     */
    public function resolve(Request $request): RedirectResponse
    {
        $code = trim((string) $request->input('code'));

        if ($code === '') {
            return back()->withErrors(['code' => 'Masukkan atau scan kode terlebih dahulu.']);
        }

        // Nomor batch sendiri memuat garis miring (…-001/P03), jadi pemotongan
        // URL hanya boleh dilakukan bila kode memang sebuah alamat lengkap
        // hasil scan kamera HP.
        if (preg_match('#^https?://#i', $code)) {
            $code = trim(substr($code, strrpos($code, '/') + 1));
        }

        $box = PackingBox::where('qr_code', $code)->orWhere('box_code', $code)->first();
        if ($box) {
            return redirect()->route('trace.box', $box->qr_code);
        }

        $batch = ProductionBatch::where('qr_code', $code)->orWhere('batch_number', $code)->first();
        if ($batch) {
            return redirect()->route('trace.batch', $batch->qr_code);
        }

        return back()->withInput()->withErrors([
            'code' => "Kode \"{$code}\" tidak ditemukan sebagai batch maupun box.",
        ]);
    }

    /**
     * Kartu telusur sebuah box: dari hasil packing kembali ke asal bahan baku.
     */
    public function box(string $qr): View
    {
        $box = PackingBox::with([
            'productType.group', 'purchaseOrder', 'run', 'creator',
            'sources.batch', 'sources.supplier', 'sources.rootBatch.supplier',
        ])->where('qr_code', $qr)->orWhere('box_code', $qr)->firstOrFail();

        return view('trace.box', [
            'box'   => $box,
            'trace' => $box->traceSources(),
        ]);
    }

    /**
     * Telusur maju: batch ini mengalir ke mana saja.
     */
    public function batch(string $qr): View
    {
        $batch = ProductionBatch::with([
            'supplier', 'subgroup.group', 'productType', 'stage',
            'purchaseOrder', 'receiving', 'rootBatch.supplier', 'parent',
            'children.stage', 'children.productType',
            'movements.creator',
        ])->where('qr_code', $qr)->firstOrFail();

        return view('trace.batch', compact('batch'));
    }

    public function apiTrace(string $qr): JsonResponse
    {
        $batch = ProductionBatch::with(['supplier', 'subgroup', 'stage', 'rootBatch.supplier'])
            ->where('qr_code', $qr)->first();

        if (!$batch) {
            return response()->json(['message' => 'Kode tidak ditemukan.'], 404);
        }

        return response()->json([
            'type'          => 'batch',
            'batch_number'  => $batch->batch_number,
            'qr_code'       => $batch->qr_code,
            'stage'         => $batch->stage_label,
            'supplier_code' => $batch->rootBatch?->supplier?->code ?? $batch->supplier?->code,
            'tgl_bongkar'   => $batch->rootBatch?->effective_date?->format('Y-m-d'),
            'available_kg'  => round((float) $batch->current_weight_kg, 3),
            'url'           => route('trace.batch', $batch->qr_code),
        ]);
    }
}
