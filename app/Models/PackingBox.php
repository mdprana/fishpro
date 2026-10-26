<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu box fisik hasil packing, dengan satu QR sendiri.
 *
 * Inilah titik akhir rantai telusur: dari QR sebuah box, seluruh asal-usul
 * bahan bakunya bisa dibaca kembali sampai ke tanggal bongkar dan supplier.
 */
class PackingBox extends Model
{
    use HasFactory;

    protected $fillable = [
        'box_code', 'qr_code', 'packing_run_id', 'purchase_order_id', 'po_item_id',
        'product_type_id', 'primary_batch_id', 'sequence_no',
        'net_weight_kg', 'gross_weight_kg', 'tare_kg', 'is_mixed_batch',
        'status', 'packing_date', 'weighed_at', 'label_printed_at',
        'operator_name', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'sequence_no'      => 'integer',
        'net_weight_kg'    => 'decimal:3',
        'gross_weight_kg'  => 'decimal:3',
        'tare_kg'          => 'decimal:3',
        'is_mixed_batch'   => 'boolean',
        'packing_date'     => 'date',
        'weighed_at'       => 'datetime',
        'label_printed_at' => 'datetime',
    ];

    public function run(): BelongsTo           { return $this->belongsTo(PackingRun::class, 'packing_run_id'); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function poItem(): BelongsTo        { return $this->belongsTo(PoItem::class); }
    public function productType(): BelongsTo   { return $this->belongsTo(ProductType::class); }
    public function primaryBatch(): BelongsTo  { return $this->belongsTo(ProductionBatch::class, 'primary_batch_id'); }
    public function creator(): BelongsTo       { return $this->belongsTo(User::class, 'created_by'); }

    public function sources(): HasMany
    {
        return $this->hasMany(PackingBoxSource::class)->orderByDesc('contributed_kg');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BatchMovement::class, 'packing_box_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', '<>', 'voided');
    }

    public function getQrUrlAttribute(): string
    {
        return route('trace.box', $this->qr_code);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'   => 'Draft',
            'packed'  => 'Dipacking',
            'stored'  => 'Disimpan',
            'shipped' => 'Dikirim',
            'voided'  => 'Dibatalkan',
            default   => $this->status,
        };
    }

    /**
     * Seluruh asal-usul isi box ini.
     *
     * Mengembalikan satu baris per batch induk, lengkap dengan kode supplier
     * dan tanggal tiap tahap, persis kolom yang diminta pada kartu telusur:
     * batch, kode supplier, tgl bongkar, tgl potong, tgl trimming,
     * tgl potong steak, tgl packing.
     *
     * Jangan dipanggil per baris pada daftar ratusan box; untuk rekap besar
     * pakai tabel packing_box_sources yang sudah didenormalisasi.
     *
     * @return array{
     *   box: array<string, mixed>,
     *   sources: array<int, array<string, mixed>>,
     *   is_mixed: bool,
     *   supplier_count: int,
     *   batch_count: int
     * }
     */
    public function traceSources(): array
    {
        $this->loadMissing([
            'productType', 'purchaseOrder',
            'sources.batch', 'sources.rootBatch.supplier', 'sources.rootBatch.subgroup',
        ]);

        // Tanggal tiap tahap ditelusuri sekali per lot akhir, lalu dipetakan
        // ke batch induk. Berat diambil dari baris tersimpan, bukan dihitung
        // ulang, supaya angka di kartu sama dengan yang dipakai laporan.
        $stagesPerLot = [];

        foreach ($this->sources->pluck('batch')->filter()->unique('id') as $lot) {
            foreach ($lot->tracePaths((float) $lot->initial_weight_kg) as $path) {
                $root  = $path['root'];
                $induk = $root->root_batch_id ?: $root->id;

                // Satu induk bisa melewati tanggal proses berbeda (dipotong di
                // dua hari). Jalur seperti itu sengaja tidak digabung agar
                // tanggalnya tetap jujur.
                $kunci = implode('|', [
                    $lot->id,
                    $induk,
                    optional(($path['stages'][ProcessStage::CUTTING]  ?? null)?->effective_date)->toDateString(),
                    optional(($path['stages'][ProcessStage::TRIMMING] ?? null)?->effective_date)->toDateString(),
                    optional(($path['stages'][ProcessStage::STEAK]    ?? null)?->effective_date)->toDateString(),
                ]);

                $stagesPerLot[$lot->id][$induk][$kunci] = $path['stages'];
            }
        }

        $net  = (float) $this->net_weight_kg;
        $rows = [];

        foreach ($this->sources as $source) {
            $lot   = $source->batch;
            $induk = $source->rootBatch ?? $lot;

            if (!$lot || !$induk) {
                continue;
            }

            $jalur = collect($stagesPerLot[$lot->id][$induk->id] ?? [])->values();

            // Bila induk melewati beberapa jalur tanggal, beratnya dibagi rata
            // ke tiap jalur; tanpa jejak lebih rinci itu pembagian paling wajar.
            $jumlahJalur = max(1, $jalur->count());
            $perJalur    = round((float) $source->contributed_kg / $jumlahJalur, 3);

            for ($i = 0; $i < $jumlahJalur; $i++) {
                $stages = $jalur->get($i, []);

                // Jalur terakhir menyerap sisa pembulatan.
                $kg = $i === $jumlahJalur - 1
                    ? round((float) $source->contributed_kg - $perJalur * ($jumlahJalur - 1), 3)
                    : $perJalur;

                $rows[] = [
                    'batch_number'      => $lot->batch_number,
                    'root_batch_number' => $induk->batch_number,
                    'root_batch_id'     => $induk->id,
                    'root_qr'           => $induk->qr_code,
                    'supplier_code'     => $induk->supplier?->code ?? '-',
                    'supplier_name'     => $induk->supplier?->name ?? '-',
                    'subgroup'          => $induk->subgroup?->name ?? '-',

                    // Tahap yang belum dilalui wajar bernilai null, misalnya
                    // box dibuat langsung dari hasil trimming tanpa potong steak.
                    'tgl_bongkar'  => $induk->effective_date,
                    'tgl_potong'   => ($stages[ProcessStage::CUTTING]  ?? null)?->effective_date,
                    'tgl_trimming' => ($stages[ProcessStage::TRIMMING] ?? null)?->effective_date,
                    'tgl_steak'    => ($stages[ProcessStage::STEAK]    ?? null)?->effective_date,
                    'tgl_packing'  => $this->packing_date,

                    'record_potong'   => ($stages[ProcessStage::CUTTING]  ?? null)?->stageRecord?->record_code,
                    'record_trimming' => ($stages[ProcessStage::TRIMMING] ?? null)?->stageRecord?->record_code,
                    'record_steak'    => ($stages[ProcessStage::STEAK]    ?? null)?->stageRecord?->record_code,

                    'contributed_kg'   => $kg,
                    'contribution_pct' => $net > 0 ? round(($kg / $net) * 100, 1) : 0.0,
                ];
            }
        }

        $sources = collect($rows)->sortByDesc('contributed_kg')->values()->all();

        return [
            'box' => [
                'code'         => $this->box_code,
                'qr'           => $this->qr_code,
                'product'      => $this->productType?->name,
                'net_kg'       => (float) $this->net_weight_kg,
                'packing_date' => $this->packing_date,
                'po_number'    => $this->purchaseOrder?->po_number,
                'buyer'        => $this->purchaseOrder?->buyer_name,
                'status'       => $this->status_label,
            ],
            'sources'        => $sources,
            // Dihitung dari batch induk yang berbeda, bukan jumlah baris:
            // satu induk bisa muncul beberapa kali karena tanggal proses berbeda.
            'is_mixed'       => collect($sources)->pluck('root_batch_id')->unique()->count() > 1,
            'supplier_count' => collect($sources)->pluck('supplier_code')->unique()->count(),
            'batch_count'    => collect($sources)->pluck('root_batch_id')->unique()->count(),
        ];
    }
}
