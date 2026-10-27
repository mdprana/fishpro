@extends('layouts.app')
@section('title', $stage->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('stage.index', $stage->code) }}">{{ $stage->name }}</a></li>
    <li class="breadcrumb-item active">Input</li>
@endsection

@section('content')
<form method="POST" action="{{ route('stage.store', $stage->code) }}" id="stageForm">
@csrf

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="bi {{ $stage->icon }} me-2" style="color:{{ $stage->color }}"></i>{{ $stage->name }}
        </h4>
        <p class="text-muted mb-0 small">
            @if($previousStage)
                Bahan masuk berasal dari hasil tahap <strong>{{ $previousStage->name }}</strong>.
            @else
                Bahan masuk berasal dari batch bahan baku hasil penerimaan.
            @endif
        </p>
    </div>
    <a href="{{ route('stage.index', $stage->code) }}" class="btn btn-outline-secondary">Kembali</a>
</div>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

<div class="form-card mb-3">
    <h6 class="fw-bold mb-3">Informasi Sesi</h6>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Nomor PO <span class="text-danger">*</span></label>
            <select name="purchase_order_id" id="poSelect" class="form-select" required>
                <option value="">-- Pilih PO --</option>
                @foreach($activePOs as $po)
                <option value="{{ $po->id }}" @selected(old('purchase_order_id', $selectedPO?->id) == $po->id)>
                    {{ $po->po_number }} - {{ $po->buyer_name }}
                </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
            <input type="date" name="process_date" class="form-control"
                   value="{{ old('process_date', today()->format('Y-m-d')) }}" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Jam</label>
            <div class="input-group">
                <input type="time" name="start_time" class="form-control" value="{{ old('start_time') }}">
                <span class="input-group-text">-</span>
                <input type="time" name="end_time" class="form-control" value="{{ old('end_time') }}">
            </div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Operator</label>
            <input type="text" name="operator_name" class="form-control" value="{{ old('operator_name') }}">
        </div>
        <div class="col-md-1">
            <label class="form-label">Shift</label>
            <input type="text" name="shift" class="form-control" value="{{ old('shift') }}" maxlength="20">
        </div>
        <div class="col-md-1">
            <label class="form-label">Line</label>
            <input type="text" name="line_name" class="form-control" value="{{ old('line_name') }}" maxlength="50">
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- ══ KIRI: BAHAN MASUK ══ --}}
    <div class="col-12 col-lg-6">
        @include('partials._scanner', [
            'scannerTitle' => 'Scan Batch Masuk',
            'scannerHint'  => 'Scan QR pada label lot, atau pilih dari daftar di bawah.',
        ])

        <div class="form-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold mb-0">A. Bahan Masuk</h6>
                <div class="d-flex gap-2">
                    <select id="batchPicker" class="form-select form-select-sm" style="max-width:260px">
                        <option value="">-- Pilih batch tersedia --</option>
                    </select>
                </div>
            </div>

            <div id="inputRows"></div>

            <div id="noInput" class="text-center py-4 text-muted small">
                Belum ada bahan. Scan QR atau pilih dari daftar.
            </div>

            <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
                <span class="fw-semibold">Total Masuk</span>
                <span class="fw-bold fs-5" id="totalInput">0,000 kg</span>
            </div>
        </div>
    </div>

    {{-- ══ KANAN: HASIL ══ --}}
    <div class="col-12 col-lg-6">
        <div class="form-card mb-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold mb-0">B. Hasil</h6>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-success" onclick="addOutput('utama')">
                        <i class="bi bi-plus-lg"></i> Utama
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="addOutput('sampingan')">
                        <i class="bi bi-plus-lg"></i> Sampingan
                    </button>
                </div>
            </div>
            <div id="outputRows"></div>
            <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
                <span class="fw-semibold">Total Hasil</span>
                <span class="fw-bold" id="totalOutput">0,000 kg</span>
            </div>
        </div>

        <div class="form-card mb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="fw-bold mb-0">
                    C. Limbah
                    @if($stage->code === 'trimming')<span class="text-danger">*</span>@endif
                </h6>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="addWaste()">
                    <i class="bi bi-plus-lg"></i> Tambah
                </button>
            </div>
            @if($stage->code === 'trimming')
            <p class="text-muted small mb-2">
                Tahap ini wajib mencatat berat limbah (darah, duri, kulit) agar efisiensi bisa dievaluasi.
            </p>
            @endif
            <div id="wasteRows"></div>
            <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
                <span class="fw-semibold">Total Limbah</span>
                <span class="fw-bold text-danger" id="totalWaste">0,000 kg</span>
            </div>
        </div>

        {{-- Panel yield langsung di layar, supaya operator tahu sebelum menyimpan --}}
        <div class="form-card" id="yieldPanel">
            <h6 class="fw-bold mb-3"><i class="bi bi-speedometer2 me-2"></i>Yield Sesi Ini</h6>
            <div class="row g-2 text-center mb-3">
                <div class="col-3">
                    <div class="text-muted" style="font-size:.68rem">Masuk</div>
                    <div class="fw-bold" id="ySumInput">0</div>
                </div>
                <div class="col-3">
                    <div class="text-muted" style="font-size:.68rem">Hasil Utama</div>
                    <div class="fw-bold text-success" id="ySumMain">0</div>
                </div>
                <div class="col-3">
                    <div class="text-muted" style="font-size:.68rem">Limbah</div>
                    <div class="fw-bold text-danger" id="ySumWaste">0</div>
                </div>
                <div class="col-3">
                    <div class="text-muted" style="font-size:.68rem">Tak Tercatat</div>
                    <div class="fw-bold" id="ySumUnacc">0</div>
                </div>
            </div>

            <div class="text-center py-2 rounded-3" id="yieldBox" style="background:var(--color-neutral-50)">
                <div class="text-muted" style="font-size:.7rem">Yield Aktual</div>
                <div class="fw-bold" style="font-size:2rem" id="yieldValue">-</div>
                <div class="small" id="yieldStandardText">Standar belum diatur untuk bahan ini.</div>
            </div>

            <div class="alert alert-danger py-2 px-3 small mt-3 mb-0" id="yieldAlert" style="display:none"></div>
        </div>
    </div>
</div>

<div class="form-card mt-3">
    <label class="form-label">Catatan Sesi <span id="notesRequired" class="text-danger" style="display:none">*</span></label>
    <textarea name="notes" id="notesField" rows="2" class="form-control"
              placeholder="Opsional">{{ old('notes') }}</textarea>
    <div class="form-text" id="notesHint" style="display:none">
        Yield di bawah standar. Jelaskan penyebabnya agar bisa dievaluasi.
    </div>
</div>

<div class="d-flex gap-2 mt-3">
    <button type="submit" class="btn btn-primary px-4" id="submitBtn">
        <i class="bi bi-check-lg me-1"></i>Simpan Sesi
    </button>
    <a href="{{ route('stage.index', $stage->code) }}" class="btn btn-outline-secondary">Batal</a>
</div>
</form>

{{-- ══ Template baris ══ --}}
<template id="tplInput">
    <div class="input-row p-2 mb-2 rounded-3 border" data-batch-id="">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="flex-grow-1" style="min-width:0">
                <div class="fw-semibold batch-number" style="font-family:ui-monospace,monospace;font-size:.72rem"></div>
                <div class="text-muted batch-meta" style="font-size:.68rem"></div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 remove-row">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="input-group input-group-sm mt-2">
            <span class="input-group-text">Ambil</span>
            <input type="number" class="form-control input-kg" step="0.001" min="0.001" required>
            <span class="input-group-text">kg</span>
            <button type="button" class="btn btn-outline-secondary take-all">Semua</button>
        </div>
        <div class="small mt-1 input-feedback"></div>
    </div>
</template>

<template id="tplOutput">
    <div class="output-row p-2 mb-2 rounded-3 border">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge output-badge"></span>
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 remove-row">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="row g-2">
            <div class="col-6 product-wrap">
                <select class="form-select form-select-sm output-product">
                    <option value="">-- Pilih Produk --</option>
                    @foreach($productGroups as $pg)
                    <optgroup label="{{ $pg->name }}">
                        @foreach($pg->productTypes as $pt)
                        <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-6">
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control output-kg" step="0.001" min="0.001" placeholder="Berat" required>
                    <span class="input-group-text">kg</span>
                </div>
            </div>
            <div class="col-5">
                <input type="text" class="form-control form-control-sm output-container" placeholder="Wadah">
            </div>
            <div class="col-3">
                <input type="number" class="form-control form-control-sm output-count" min="1" value="1" placeholder="Jml">
            </div>
            <div class="col-4">
                <input type="text" class="form-control form-control-sm output-notes" placeholder="Catatan">
            </div>
        </div>
    </div>
</template>

<template id="tplWaste">
    <div class="waste-row p-2 mb-2 rounded-3 border border-danger-subtle">
        <div class="row g-2 align-items-center">
            <div class="col-5">
                <select class="form-select form-select-sm waste-category" required>
                    @foreach($wasteCategories as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-4">
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control waste-kg" step="0.001" min="0.001" placeholder="Berat" required>
                    <span class="input-group-text">kg</span>
                </div>
            </div>
            <div class="col-2">
                <input type="text" class="form-control form-control-sm waste-notes" placeholder="Ket.">
            </div>
            <div class="col-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 remove-row">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
const STAGE_CODE      = @json($stage->code);
const REQUIRES_PRODUCT = @json((bool) $stage->requires_product_type);
const STANDARDS       = @json($standards);
const INPUTS_URL      = @json(route('api.stage.inputs', $stage->code));
const LOOKUP_URL      = @json(url('api/batch/lookup'));
const RESOLVE_URL     = @json(route('trace.resolve'));

const state = { inputs: new Map() };

const fmt = n => n.toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

// ── Bahan masuk ───────────────────────────────────────────────
async function refreshPicker() {
    const poId   = document.getElementById('poSelect').value;
    const picker = document.getElementById('batchPicker');
    picker.innerHTML = '<option value="">-- Pilih batch tersedia --</option>';
    if (!poId) return;

    try {
        const res = await fetch(`${INPUTS_URL}?po_id=${poId}`, { headers: { 'Accept': 'application/json' } });
        const rows = await res.json();

        if (!rows.length) {
            picker.innerHTML = '<option value="">Tidak ada batch siap untuk tahap ini</option>';
            return;
        }
        rows.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = `${b.batch_number} - ${b.available_kg} kg`;
            opt.dataset.payload = JSON.stringify(b);
            picker.appendChild(opt);
        });
    } catch (e) {
        picker.innerHTML = '<option value="">Gagal memuat daftar batch</option>';
    }
}

function addInput(batch) {
    if (state.inputs.has(batch.id)) {
        window.scanFeedback(`${batch.batch_number} sudah ada di daftar.`, 'warning');
        return;
    }
    if (!batch.available_kg || batch.available_kg <= 0) {
        window.scanFeedback(`${batch.batch_number} sudah habis.`, 'danger');
        return;
    }

    const node = document.getElementById('tplInput').content.cloneNode(true);
    const row  = node.querySelector('.input-row');
    row.dataset.batchId = batch.id;
    row.querySelector('.batch-number').textContent = batch.batch_number;
    row.querySelector('.batch-meta').textContent =
        [batch.supplier_code, batch.subgroup || batch.product, batch.stage_label, `sisa ${batch.available_kg} kg`]
            .filter(Boolean).join(' · ');

    const kg = row.querySelector('.input-kg');
    kg.max   = batch.available_kg;
    kg.value = batch.available_kg;

    kg.addEventListener('input', () => { validateInput(row, batch); recalc(); });
    row.querySelector('.take-all').addEventListener('click', () => {
        kg.value = batch.available_kg; validateInput(row, batch); recalc();
    });
    row.querySelector('.remove-row').addEventListener('click', () => {
        state.inputs.delete(batch.id); row.remove(); recalc();
    });

    document.getElementById('inputRows').appendChild(node);
    state.inputs.set(batch.id, batch);
    window.scanFeedback(`${batch.batch_number} ditambahkan.`, 'success');
    recalc();
}

function validateInput(row, batch) {
    const kgEl = row.querySelector('.input-kg');
    const kg   = parseFloat(kgEl.value || 0);
    const fb   = row.querySelector('.input-feedback');

    if (kg > batch.available_kg + 0.001) {
        kgEl.classList.add('is-invalid');
        fb.className = 'small mt-1 input-feedback text-danger';
        fb.textContent = `Melebihi sisa batch (${batch.available_kg} kg).`;
        return false;
    }
    kgEl.classList.remove('is-invalid');
    fb.className = 'small mt-1 input-feedback text-muted';
    fb.textContent = `Sisa setelah diambil: ${(batch.available_kg - kg).toFixed(3)} kg`;
    return true;
}

window.onScanCode = async function (code) {
    try {
        let token = code;
        if (code.includes('/') || code.includes(':') || code.includes('\\')) {
            const r = await fetch(RESOLVE_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ code }),
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok || data.type !== 'batch') {
                window.scanFeedback(r.ok ? `Kode ${code} adalah box, bukan batch.` : (data.message || `Kode ${code} tidak dikenali.`), 'danger');
                return;
            }
            token = data.qr_code;
        }
        const res = await fetch(`${LOOKUP_URL}/${encodeURIComponent(token)}`, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) { window.scanFeedback(`Kode ${code} tidak dikenali.`, 'danger'); return; }
        addInput(await res.json());
    } catch (e) {
        window.scanFeedback('Gagal menghubungi server.', 'danger');
    }
};

document.getElementById('batchPicker').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (opt?.dataset.payload) addInput(JSON.parse(opt.dataset.payload));
    this.value = '';
});
document.getElementById('poSelect').addEventListener('change', refreshPicker);

// ── Hasil & limbah ────────────────────────────────────────────
function addOutput(type) {
    const node = document.getElementById('tplOutput').content.cloneNode(true);
    const row  = node.querySelector('.output-row');
    row.dataset.type = type;

    const badge = row.querySelector('.output-badge');
    badge.textContent = type === 'utama' ? 'Hasil Utama' : 'Sampingan';
    badge.className = 'badge ' + (type === 'utama' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary');

    // Tahap Potong belum memilah produk jual. Hasilnya masih ikan gelondong
    // tanpa kepala & isi perut, jadi pemilihan produk disembunyikan.
    if (!REQUIRES_PRODUCT && type === 'utama') {
        row.querySelector('.product-wrap').style.display = 'none';
    } else {
        row.querySelector('.output-product').required = true;
    }

    row.querySelector('.output-kg').addEventListener('input', recalc);
    row.querySelector('.remove-row').addEventListener('click', () => { row.remove(); recalc(); });

    document.getElementById('outputRows').appendChild(node);
    recalc();
}

function addWaste() {
    const node = document.getElementById('tplWaste').content.cloneNode(true);
    const row  = node.querySelector('.waste-row');
    row.querySelector('.waste-kg').addEventListener('input', recalc);
    row.querySelector('.remove-row').addEventListener('click', () => { row.remove(); recalc(); });
    document.getElementById('wasteRows').appendChild(node);
    recalc();
}

// ── Hitung yield langsung ─────────────────────────────────────
function recalc() {
    let totalIn = 0, totalOut = 0, mainOut = 0, totalWaste = 0;

    document.querySelectorAll('.input-row').forEach(r => totalIn += parseFloat(r.querySelector('.input-kg').value || 0));
    document.querySelectorAll('.output-row').forEach(r => {
        const v = parseFloat(r.querySelector('.output-kg').value || 0);
        totalOut += v;
        if (r.dataset.type === 'utama') mainOut += v;
    });
    document.querySelectorAll('.waste-row').forEach(r => totalWaste += parseFloat(r.querySelector('.waste-kg').value || 0));

    document.getElementById('noInput').style.display = state.inputs.size ? 'none' : '';
    document.getElementById('totalInput').textContent  = fmt(totalIn) + ' kg';
    document.getElementById('totalOutput').textContent = fmt(totalOut) + ' kg';
    document.getElementById('totalWaste').textContent  = fmt(totalWaste) + ' kg';

    const unaccounted = totalIn - totalOut - totalWaste;
    document.getElementById('ySumInput').textContent = fmt(totalIn);
    document.getElementById('ySumMain').textContent  = fmt(mainOut);
    document.getElementById('ySumWaste').textContent = fmt(totalWaste);

    const unaccEl = document.getElementById('ySumUnacc');
    unaccEl.textContent = fmt(unaccounted);
    // Selisih yang tidak tercatat ditampilkan apa adanya, sebab biasanya berarti
    // ada penimbangan yang terlewat, bukan sesuatu yang layak disembunyikan.
    unaccEl.className = 'fw-bold ' + (Math.abs(unaccounted) > 0.01 ? 'text-warning' : 'text-muted');

    updateYield(totalIn, mainOut);
}

function activeStandard() {
    // Standar paling spesifik: per subgroup bahan yang sedang diproses,
    // jatuh ke standar umum bila belum diatur khusus.
    for (const b of state.inputs.values()) {
        if (b.subgroup_id && STANDARDS[String(b.subgroup_id)]) return STANDARDS[String(b.subgroup_id)];
    }
    return STANDARDS['default'] ?? null;
}

function updateYield(totalIn, mainOut) {
    const box   = document.getElementById('yieldBox');
    const value = document.getElementById('yieldValue');
    const text  = document.getElementById('yieldStandardText');
    const alert = document.getElementById('yieldAlert');
    const notesReq  = document.getElementById('notesRequired');
    const notesHint = document.getElementById('notesHint');

    if (totalIn <= 0) {
        value.textContent = '-';
        box.style.background = 'var(--color-neutral-50)';
        alert.style.display = 'none';
        notesReq.style.display = notesHint.style.display = 'none';
        document.getElementById('notesField').required = false;
        return;
    }

    const pct = (mainOut / totalIn) * 100;
    value.textContent = pct.toFixed(1) + '%';

    const std = activeStandard();
    if (!std) {
        text.textContent = 'Standar belum diatur untuk bahan ini.';
        box.style.background = 'var(--color-neutral-50)';
        value.className = 'fw-bold';
        alert.style.display = 'none';
        notesReq.style.display = notesHint.style.display = 'none';
        document.getElementById('notesField').required = false;
        return;
    }

    text.textContent = `Standar minimal ${std.min}%` + (std.target ? ` · target ${std.target}%` : '');

    let bg = 'var(--green-50)', color = 'text-success', below = false;
    if (pct < std.min) {
        bg = 'var(--red-50)'; color = 'text-danger'; below = true;
    } else if (std.warn !== null && pct < std.warn) {
        bg = 'var(--amber-50)'; color = 'text-warning';
    }

    box.style.background = bg;
    value.className = 'fw-bold ' + color;
    value.style.fontSize = '2rem';

    if (below) {
        alert.style.display = '';
        alert.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>Penyusutan melebihi toleransi pabrik.</strong>
            Standar minimal ${std.min}%, aktual ${pct.toFixed(1)}%.
            Sesi tetap bisa disimpan, tetapi catatan wajib diisi.`;
        // Sengaja tidak memblokir penyimpanan: yang terjadi di lantai produksi
        // harus tetap tercatat apa adanya, hanya wajib dijelaskan.
        notesReq.style.display = notesHint.style.display = '';
        document.getElementById('notesField').required = true;
    } else {
        alert.style.display = 'none';
        notesReq.style.display = notesHint.style.display = 'none';
        document.getElementById('notesField').required = false;
    }
}

// ── Susun nama field saat submit ──────────────────────────────
document.getElementById('stageForm').addEventListener('submit', function (e) {
    if (!state.inputs.size) {
        e.preventDefault();
        alert('Tambahkan minimal satu batch bahan masuk.');
        return;
    }
    if (!document.querySelectorAll('.output-row').length) {
        e.preventDefault();
        alert('Tambahkan minimal satu baris hasil.');
        return;
    }
    if (STAGE_CODE === 'trimming' && !document.querySelectorAll('.waste-row').length) {
        e.preventDefault();
        alert('Tahap Trimming wajib mencatat limbah minimal satu baris.');
        return;
    }

    document.querySelectorAll('.input-row').forEach((r, i) => {
        r.querySelector('.input-kg').name = `inputs[${i}][input_weight_kg]`;
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = `inputs[${i}][batch_id]`; hidden.value = r.dataset.batchId;
        r.appendChild(hidden);
    });

    document.querySelectorAll('.output-row').forEach((r, i) => {
        r.querySelector('.output-kg').name        = `outputs[${i}][output_weight_kg]`;
        r.querySelector('.output-product').name   = `outputs[${i}][product_type_id]`;
        r.querySelector('.output-container').name = `outputs[${i}][container_type]`;
        r.querySelector('.output-count').name     = `outputs[${i}][container_count]`;
        r.querySelector('.output-notes').name     = `outputs[${i}][notes]`;
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = `outputs[${i}][output_type]`; hidden.value = r.dataset.type;
        r.appendChild(hidden);
    });

    document.querySelectorAll('.waste-row').forEach((r, i) => {
        r.querySelector('.waste-category').name = `wastes[${i}][waste_category]`;
        r.querySelector('.waste-kg').name       = `wastes[${i}][output_weight_kg]`;
        r.querySelector('.waste-notes').name    = `wastes[${i}][notes]`;
    });
});

// ── Awal ──────────────────────────────────────────────────────
addOutput('utama');
@if($stage->code === 'trimming') addWaste(); @endif
refreshPicker();
recalc();
</script>
@endpush
