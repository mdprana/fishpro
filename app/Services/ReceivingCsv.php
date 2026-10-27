<?php

namespace App\Services;

use App\Models\MaterialGroup;
use App\Models\MaterialSubgroup;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceivingCsv
{
    public const HEADERS = ['po_number', 'subgroup', 'supplier', 'weight_kg', 'container_type', 'container_count', 'split_per_container', 'received_date', 'doc_number'];
    public const MAX_BYTES = 1048576;
    public const MAX_ENVELOPE = 2097152;

    public static function rules(): array
    {
        return [
            'weight_kg' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'container_type' => 'required|string|max:100',
            'container_count' => ['required', 'regex:/^[1-9]\d{0,2}$/D', 'integer', 'between:1,100'],
            'split_per_container' => 'nullable|boolean',
            'received_date' => 'required|date_format:Y-m-d',
            'doc_number' => 'nullable|string|max:100',
        ];
    }

    public function parse(string $bytes, bool $lock = false): array
    {
        $rows = $errors = $tuples = $references = [];
        $errorCount = 0;
        $error = function (int $line, string $column, string $message) use (&$errors, &$errorCount) {
            $errorCount++;
            if (count($errors) < 100) {
                $errors[] = "Baris {$line}: kolom {$column} {$message}";
            }
        };
        $result = function () use (&$rows, &$errors, &$errorCount, &$tuples, &$references) {
            sort($tuples, SORT_STRING);
            return ['rows' => $rows, 'errors' => $errors, 'remaining' => max(0, $errorCount - 100),
                'digest' => hash('sha256', implode("\n", $tuples)),
                'references' => hash('sha256', json_encode($references, JSON_THROW_ON_ERROR))];
        };
        if (strlen($bytes) > self::MAX_BYTES || !preg_match('//u', $bytes) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $bytes)) {
            $error(1, 'CSV', 'ukuran/encoding tidak valid (maksimum 1 MiB UTF-8).');
            return $result();
        }
        $bytes = str_starts_with($bytes, "\xEF\xBB\xBF") ? substr($bytes, 3) : $bytes;
        $records = $this->records($bytes, $error);
        if (!$records) {
            $error(1, 'CSV', 'header wajib diisi.');
            return $result();
        }
        $headers = array_map('trim', $this->cells(array_shift($records)[1]));
        if (count(array_unique($headers)) !== count($headers) || array_diff($headers, self::HEADERS) || array_diff(array_slice(self::HEADERS, 0, 8), $headers)) {
            $error(1, 'header', 'duplikat, tidak dikenal, atau kolom wajib hilang.');
            return $result();
        }
        $masters = [];
        foreach ([MaterialGroup::class, MaterialSubgroup::class, Supplier::class, PurchaseOrder::class] as $class) {
            $query = $class::orderBy('id');
            $masters[$class] = ($lock ? $query->lockForUpdate() : $query)->get();
        }
        $seen = [];
        $lots = 0;
        foreach ($records as [$line, $record]) {
            $cells = $this->cells($record);
            if (count($cells) !== count($headers)) {
                $error($line, 'CSV', 'jumlah sel tidak sesuai header.');
                continue;
            }
            $data = array_combine($headers, array_map(fn ($cell) => trim((string) $cell), $cells));
            $before = $errorCount;
            foreach (['po_number', 'subgroup', 'supplier'] as $field) {
                if ($data[$field] === '' || mb_strlen($data[$field]) > 255 || preg_match('/^[=+@-]/', $data[$field])) {
                    $error($line, $field, 'identitas tidak valid.');
                }
            }
            $boolean = strtolower($data['split_per_container']);
            if (!in_array($boolean, ['0', '1', 'true', 'false'], true)) {
                $error($line, 'split_per_container', 'harus 0/1/true/false.');
            }
            $data['split_per_container'] = in_array($boolean, ['1', 'true'], true);
            foreach (Validator::make($data, self::rules())->errors()->messages() as $field => $messages) {
                $error($line, $field, 'nilai tidak valid.');
            }
            $sub = $this->resolve($masters[MaterialSubgroup::class], $data['subgroup']);
            $supplier = $this->resolve($masters[Supplier::class], $data['supplier'], true);
            $orders = $masters[PurchaseOrder::class]->filter(fn ($p) => $p->po_number === $data['po_number']);
            $po = $orders->count() === 1 ? $orders->first() : null;
            $group = $sub ? $masters[MaterialGroup::class]->firstWhere('id', $sub->material_group_id) : null;
            if (!$sub || !$sub->is_active || !$group?->is_active) {
                $error($line, 'subgroup', 'tidak ditemukan, ambigu, atau tidak aktif.');
            }
            if (!$supplier || !$supplier->is_active) {
                $error($line, 'supplier', 'tidak ditemukan, ambigu, atau tidak aktif.');
            }
            if (!$po || !in_array($po->status, ['active', 'in_progress'], true)) {
                $error($line, 'po_number', 'tidak ditemukan atau tidak aktif.');
            }
            if ($before !== $errorCount) {
                continue;
            }
            $normalized = [
                'purchase_order_id' => (int) $po->id, 'material_subgroup_id' => (int) $sub->id,
                'supplier_id' => (int) $supplier->id, 'weight_kg' => number_format((float) $data['weight_kg'], 2, '.', ''),
                'container_type' => $data['container_type'], 'container_count' => (int) $data['container_count'],
                'split_per_container' => $data['split_per_container'], 'received_date' => $data['received_date'],
                'doc_number' => ($data['doc_number'] ?? '') === '' ? null : $data['doc_number'],
            ];
            if ($normalized['split_per_container'] && (float) $normalized['weight_kg'] * 1000 < $normalized['container_count']) {
                $error($line, 'weight_kg', 'terlalu kecil untuk lot wadah minimum 0,001 kg.');
            }
            $tuple = json_encode($normalized, JSON_THROW_ON_ERROR);
            if (isset($seen[$tuple])) {
                $error($line, 'CSV', "duplikat dengan Baris {$seen[$tuple]}.");
            }
            $seen[$tuple] = $line;
            $tuples[] = $tuple;
            $lots += $normalized['split_per_container'] ? $normalized['container_count'] : 1;
            if ($lots > 1000) {
                $error($line, 'container_count', 'total lot melebihi 1.000.');
            }
            $references[] = [$sub->only(['id', 'code', 'name', 'is_active', 'material_group_id']), $group->only(['id', 'is_active']),
                $supplier->only(['id', 'code', 'name', 'is_active']), $po->only(['id', 'po_number', 'status'])];
            $rows[] = ['line' => $line, 'data' => $normalized, 'po' => $po->po_number, 'subgroup' => $sub->name, 'supplier' => $supplier->display_name];
        }
        if (!$records) {
            $error(2, 'CSV', 'minimal satu baris data.');
        }
        return $result();
    }

    private function resolve($models, string $value, bool $supplier = false)
    {
        $code = $supplier ? Supplier::normalizeCode($value) : $value;
        $matches = $models->filter(fn ($model) => $model->code === $code);
        if ($matches->isEmpty()) {
            $matches = $models->filter(fn ($model) => $model->name === $value);
        }
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function cells(string $record): array
    {
        $stream = fopen('php://memory', 'r+');
        try {
            fwrite($stream, $record);
            rewind($stream);
            return fgetcsv($stream, null, ',', '"', '') ?: [null];
        } finally {
            fclose($stream);
        }
    }

    private function records(string $bytes, callable $error): array
    {
        $records = [];
        $start = 0;
        $line = $startLine = 1;
        $state = 'start';
        $bad = false;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $c = $bytes[$i];
            if ($state === 'quoted') {
                if ($c === '"') {
                    if (($bytes[$i + 1] ?? '') === '"') {
                        $i++;
                    } else {
                        $state = 'closed';
                    }
                }
            } elseif ($c === "\r" || $c === "\n") {
                if ($c === "\r" && ($bytes[$i + 1] ?? '') === "\n") {
                    $i++;
                }
                $record = substr($bytes, $start, $i - $start + 1);
                if (strlen($record) > 16384 || $bad) {
                    $error($startLine, 'CSV', 'record terlalu besar atau kutip tidak valid.');
                }
                $records[] = [$startLine, $record];
                if (count($records) > 501) {
                    $error($startLine, 'CSV', 'maksimum 500 baris data.');
                    return array_slice($records, 0, 501);
                }
                $line++;
                $startLine = $line;
                $start = $i + 1;
                $state = 'start';
                $bad = false;
                continue;
            } elseif ($c === ',') {
                $state = 'start';
            } elseif ($c === '"' && $state === 'start') {
                $state = 'quoted';
            } else {
                if ($c === '"' || $state === 'closed') {
                    $bad = true;
                }
                $state = 'plain';
            }
            if ($c === "\n" || ($c === "\r" && ($bytes[$i + 1] ?? '') !== "\n")) {
                $line++;
            }
        }
        if ($start < $length) {
            if ($state === 'quoted' || $bad || $length - $start > 16384) {
                $error($startLine, 'CSV', 'record terlalu besar atau kutip tidak valid.');
            }
            $records[] = [$startLine, substr($bytes, $start)];
        }
        if (count($records) > 501) {
            $error($startLine, 'CSV', 'maksimum 500 baris data.');
        }
        return array_slice($records, 0, 501);
    }

    public function envelope(string $bytes, int $actor, array $preview): string
    {
        $envelope = Crypt::encryptString(json_encode(['actor' => $actor, 'nonce' => (string) Str::uuid(), 'issued' => now()->timestamp,
            'digest' => $preview['digest'], 'references' => $preview['references']], JSON_THROW_ON_ERROR)."\n".$bytes);
        if (strlen($envelope) > self::MAX_ENVELOPE) {
            throw ValidationException::withMessages(['csv_file' => 'CSV terlalu besar untuk konfirmasi.']);
        }
        return $envelope;
    }

    public function open(mixed $envelope, int $actor): array
    {
        try {
            if (!is_string($envelope) || strlen($envelope) > self::MAX_ENVELOPE) {
                throw new \RuntimeException();
            }
            $parts = explode("\n", Crypt::decryptString($envelope), 2);
            $meta = json_decode($parts[0], true, 8, JSON_THROW_ON_ERROR);
            if (count($parts) !== 2 || strlen($parts[1]) > self::MAX_BYTES || ($meta['actor'] ?? null) !== $actor
                || !is_int($meta['issued'] ?? null) || $meta['issued'] > now()->timestamp || $meta['issued'] < now()->timestamp - 1800
                || !Str::isUuid($meta['nonce'] ?? '') || !preg_match('/^[a-f0-9]{64}$/D', $meta['digest'] ?? '')
                || !preg_match('/^[a-f0-9]{64}$/D', $meta['references'] ?? '')) {
                throw new \RuntimeException();
            }
            return [$meta, $parts[1]];
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['csv_file' => 'Konfirmasi tidak valid/kedaluwarsa. Buat pratinjau baru.']);
        }
    }
}
