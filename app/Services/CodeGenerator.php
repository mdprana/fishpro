<?php

namespace App\Services;

use App\Models\DocumentCounter;
use App\Models\MaterialSubgroup;
use App\Models\ProcessStage;
use App\Models\ProductType;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pembentuk seluruh nomor dokumen & lot.
 *
 * Semua nomor urut diambil dari tabel `document_counters` dengan lockForUpdate,
 * bukan dari MAX(kolom)+1. Pola MAX menghasilkan nomor kembar begitu dua
 * operator menyimpan pada detik yang sama.
 */
class CodeGenerator
{
    /**
     * Alfabet token QR: base32 tanpa I, L, O, U.
     * Karakter mirip (1/I/L, 0/O) dihindari supaya kode masih bisa dibaca
     * dan diketik ulang manual saat label rusak atau kamera gagal.
     */
    private const QR_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const QR_LENGTH = 8;

    // ── Nomor urut ────────────────────────────────────────────────

    /**
     * Ambil nomor urut berikutnya untuk sebuah scope, aman dari race condition.
     */
    public function nextNumber(string $scope): int
    {
        return DB::transaction(function () use ($scope) {
            $counter = DocumentCounter::where('scope', $scope)->lockForUpdate()->first();

            if (!$counter) {
                try {
                    DocumentCounter::create(['scope' => $scope, 'last_number' => 0]);
                } catch (QueryException $e) {
                    // Proses lain menyisipkan scope yang sama lebih dulu. Tidak masalah,
                    // baris sudah ada dan akan dikunci di bawah.
                }
                $counter = DocumentCounter::where('scope', $scope)->lockForUpdate()->firstOrFail();
            }

            $counter->last_number++;
            $counter->save();

            return $counter->last_number;
        });
    }

    // ── Nomor batch / lot ─────────────────────────────────────────

    /**
     * Batch Number Induk (Parent Lot) dari penerimaan bahan baku.
     * Format: RM-{YYMMDD}-{SUPPLIER}-{SUBGROUP}-{001}
     * Contoh: RM-260918-NJY-YFW-001
     */
    public function parentLot(Supplier $supplier, MaterialSubgroup $subgroup, Carbon|string $date): string
    {
        $date  = $date instanceof Carbon ? $date : Carbon::parse($date);
        $stamp = $date->format('ymd');
        $supp  = Supplier::normalizeCode($supplier->code);
        $sub   = $this->subgroupCode($subgroup);

        $scope = "RM-{$stamp}-{$supp}-{$sub}";
        $seq   = $this->nextNumber($scope);

        return sprintf('%s-%03d', $scope, $seq);
    }

    /**
     * Lot per wadah fisik (palet / keranjang) di bawah satu parent lot.
     * Format: {PARENT}/P{01}
     */
    public function containerLot(string $parentBatchNumber, int $sequence): string
    {
        return sprintf('%s/P%02d', $parentBatchNumber, $sequence);
    }

    /**
     * Lot hasil sebuah tahap, menempel pada nomor lot induknya sehingga
     * rantai asal-usul terbaca langsung dari nomornya.
     * Format: {PARENT}-CT01 / -TR01 / -ST01
     */
    public function stageLot(string $parentBatchNumber, ProcessStage $stage, int $sequence): string
    {
        return sprintf('%s-%s%02d', $parentBatchNumber, strtoupper($stage->short_code), $sequence);
    }

    /**
     * Lot hasil penggabungan beberapa batch induk. Tidak ada satu induk
     * yang bisa dipakai sebagai awalan, jadi memakai penomoran sendiri.
     * Format: MIX-{YYMMDD}-{CT}-{001}
     */
    public function mixedLot(ProcessStage $stage, Carbon|string $date): string
    {
        $date  = $date instanceof Carbon ? $date : Carbon::parse($date);
        $stamp = $date->format('ymd');
        $short = strtoupper($stage->short_code);

        $scope = "MIX-{$stamp}-{$short}";
        $seq   = $this->nextNumber($scope);

        return sprintf('%s-%03d', $scope, $seq);
    }

    // ── Nomor dokumen ─────────────────────────────────────────────

    /**
     * Kode catatan tahap produksi. Format: CUT-260918-0007
     */
    public function stageRecord(ProcessStage $stage, Carbon|string $date): string
    {
        $date   = $date instanceof Carbon ? $date : Carbon::parse($date);
        $stamp  = $date->format('ymd');
        $prefix = strtoupper(substr($stage->code, 0, 3));

        $scope = "{$prefix}-{$stamp}";
        $seq   = $this->nextNumber($scope);

        return sprintf('%s-%04d', $scope, $seq);
    }

    /** Kode sesi packing. Format: PKR-260921-0003 */
    public function packingRun(Carbon|string $date): string
    {
        $date  = $date instanceof Carbon ? $date : Carbon::parse($date);
        $scope = 'PKR-' . $date->format('ymd');

        return sprintf('%s-%04d', $scope, $this->nextNumber($scope));
    }

    /**
     * Kode box packing. Format: BOX-PO2024001-YFSTK-0187
     * Nomor urut per (PO, produk) supaya rentang box satu produk berurutan rapi.
     */
    public function boxCode(PurchaseOrder $po, ProductType $productType): string
    {
        $poPart   = $this->slug($po->po_number, 14);
        $prodPart = $this->productCode($productType);

        $scope = "BOX-{$poPart}-{$prodPart}";

        return sprintf('%s-%04d', $scope, $this->nextNumber($scope));
    }

    // ── Token QR ──────────────────────────────────────────────────

    /**
     * Token QR acak yang dijamin belum dipakai di tabel tujuan.
     *
     * @param string $table  mis. 'production_batches'
     * @param string $column mis. 'qr_code'
     */
    public function qrToken(string $table, string $column = 'qr_code'): string
    {
        // 32^8 ≈ 1,1 triliun kemungkinan; loop hanya jaring pengaman.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $token = $this->randomToken();

            if (!DB::table($table)->where($column, $token)->exists()) {
                return $token;
            }
        }

        throw new \RuntimeException("Gagal membuat token QR unik untuk {$table}.{$column} setelah 20 percobaan.");
    }

    private function randomToken(): string
    {
        $max   = strlen(self::QR_ALPHABET) - 1;
        $token = '';

        for ($i = 0; $i < self::QR_LENGTH; $i++) {
            $token .= self::QR_ALPHABET[random_int(0, $max)];
        }

        return $token;
    }

    // ── Helper singkatan ──────────────────────────────────────────

    /** Kode subgroup untuk nomor batch: pakai kolom `code` bila ada, jika tidak turunkan dari nama. */
    private function subgroupCode(MaterialSubgroup $subgroup): string
    {
        return $this->slug($subgroup->code ?: $this->initials($subgroup->name), 6);
    }

    /** Kode produk untuk nomor box. */
    private function productCode(ProductType $productType): string
    {
        return $this->slug($productType->code ?: $this->initials($productType->name), 6);
    }

    /**
     * Ambil inisial tiap kata, mis. "YF LOIN EROPA WARNA" -> "YLEW".
     * Hanya dipakai bila master belum diisi kolom `code`; keunikan nomor
     * tetap dijamin oleh counter, bukan oleh singkatan ini.
     */
    private function initials(string $name): string
    {
        $words = preg_split('/[\s\-_]+/', strtoupper(trim($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) === 1) {
            return substr($words[0], 0, 4);
        }

        return implode('', array_map(fn ($w) => substr($w, 0, 1), $words));
    }

    private function slug(string $value, int $maxLength): string
    {
        $clean = preg_replace('/[^A-Z0-9]/', '', strtoupper($value));

        return substr($clean ?: 'NA', 0, $maxLength);
    }
}
