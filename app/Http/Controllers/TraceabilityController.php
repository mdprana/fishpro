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
    public function resolve(Request $request): RedirectResponse|JsonResponse
    {
        $raw  = $request->input('code');
        $code = is_string($raw) ? trim($raw) : null;

        if ($code === '') {
            return $this->resolveError($request, 'Masukkan atau scan kode terlebih dahulu.');
        }
        if ($code === null || !mb_check_encoding($code, 'UTF-8') || mb_strlen($code) > 512
            || preg_match('/[\x00-\x1F\x7F]/', $code)) {
            return $this->resolveError($request, 'Kode tidak valid.');
        }

        $box = $batch = null;

        if (preg_match('#^[a-z][a-z0-9+.\-]*:|^[/\\\\]{2}#i', $code)) {
            $origin = rtrim((string) config('app.url'), '/');
            if (!preg_match('#^(.*?)/trace/(batch|box)/([0-9A-HJKMNP-TV-Z]{8})$#', $code, $m)
                || strcasecmp($m[1], $origin) !== 0) {
                return $this->resolveError($request, 'Alamat QR bukan dari aplikasi ini atau formatnya tidak dikenal.');
            }
            $m[2] === 'box'
                ? $box   = PackingBox::where('qr_code', $m[3])->first()
                : $batch = ProductionBatch::where('qr_code', $m[3])->first();
        } else {
            // Nomor batch sendiri memuat garis miring (…-001/P03): cocokkan persis.
            $box = PackingBox::where('qr_code', $code)->orWhere('box_code', $code)->first();
            $batch = $box ? null : ProductionBatch::where('qr_code', $code)->orWhere('batch_number', $code)->first();
        }

        if ($box || $batch) {
            $type = $box ? 'box' : 'batch';
            $qr   = ($box ?? $batch)->qr_code;
            $url  = rtrim((string) config('app.url'), '/').route("trace.{$type}", $qr, false);

            return $request->expectsJson()
                ? response()->json(['type' => $type, 'qr_code' => $qr, 'url' => $url])
                : redirect($url);
        }

        return $this->resolveError($request, "Kode \"{$code}\" tidak ditemukan sebagai batch maupun box.", $code);
    }

    private function resolveError(Request $request, string $message, ?string $code = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['code' => [$message]]], 422);
        }

        return back()->withInput($code === null ? [] : ['code' => $code])->withErrors(['code' => $message]);
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
