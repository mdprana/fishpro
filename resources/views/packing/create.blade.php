@extends('layouts.app')
@section('title','Input Packing')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('packing.index') }}">Packing</a></li>
    <li class="breadcrumb-item active">Input</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-box-seam me-2"></i>Input Packing</h4>
        <p class="text-muted mb-0 small">Setiap box mendapat QR sendiri sehingga asal bahan bakunya bisa ditelusuri.</p>
    </div>
    <a href="{{ route('packing.boxes') }}" class="btn btn-outline-secondary">Daftar Box</a>
</div>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="form-card mb-3">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Nomor PO <span class="text-danger">*</span></label>
            <select id="poSelect" class="form-select">
                <option value="">-- Pilih PO --</option>
                @foreach($activePOs as $po)
                <option value="{{ $po->id }}" @selected($selectedPO?->id == $po->id)>
                    {{ $po->po_number }} - {{ $po->buyer_name }}
                </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Produk <span class="text-danger">*</span></label>
            <select id="productSelect" class="form-select" @disabled(!$selectedPO)>
                <option value="">-- Pilih Produk --</option>
                @foreach($products as $p)
                <option value="{{ $p['product_type_id'] }}"
                        data-wpb="{{ $p['weight_per_box'] }}"
                        data-available="{{ $p['available_kg'] }}">
                    {{ $p['name'] }}, tersedia {{ number_format($p['available_kg'], 2) }} kg
                </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Tanggal Packing <span class="text-danger">*</span></label>
            <input type="date" id="packingDate" class="form-control" value="{{ today()->format('Y-m-d') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">Operator</label>
            <input type="text" id="operatorName" class="form-control">
        </div>
    </div>
</div>

@if($selectedPO && $products->count())
<div class="row g-2 mb-3">
    @foreach($products as $p)
    <div class="col-6 col-lg-3">
        <div class="form-card h-100" style="border-left:4px solid {{ $p['color'] }}">
            <div class="fw-semibold" style="color:{{ $p['color'] }}">{{ $p['name'] }}</div>
            <div class="text-muted" style="font-size:.7rem">Siap dipacking</div>
            <div class="fw-bold fs-5">{{ number_format($p['available_kg'], 2) }} kg</div>
            <div class="text-muted" style="font-size:.7rem">
                Box: {{ $p['packed_box'] }} / {{ $p['target_box'] }} · sisa {{ $p['remaining_box'] }}
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

<div class="form-card mb-3">
    <h6 class="fw-bold mb-2">Lot Sumber</h6>
    <p class="text-muted small mb-3">
        Pilih lot hasil tahap akhir yang akan dipacking. Bila lebih dari satu lot dipilih,
        isi tiap box menjadi campuran secara proporsional, dan itu tetap tercatat per box.
    </p>

    <div id="lotList" class="mb-2">
        <div class="text-muted small text-center py-3">Pilih PO dan produk terlebih dahulu.</div>
    </div>

    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
        <div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="fifoBtn" disabled>
                <i class="bi bi-magic me-1"></i>Ambil Otomatis (FIFO)
            </button>
        </div>
        <div class="text-end">
            <div class="text-muted" style="font-size:.7rem">Total diambil</div>
            <div class="fw-bold fs-5" id="totalAllocated">0,000 kg</div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabBulk" type="button">
            <i class="bi bi-stack me-1"></i>Bulk Generate
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPerBox" type="button">
            <i class="bi bi-speedometer me-1"></i>Timbang per Box
        </button>
    </li>
</ul>

<div class="tab-content">
    {{-- ══ Mode bulk ══ --}}
    <div class="tab-pane fade show active" id="tabBulk">
        <form method="POST" action="{{ route('packing.run.store') }}" id="bulkForm" class="form-card">
            @csrf
            <input type="hidden" name="purchase_order_id" id="bulkPo">
            <input type="hidden" name="product_type_id"  id="bulkProduct">
            <input type="hidden" name="packing_date"     id="bulkDate">
            <input type="hidden" name="operator_name"    id="bulkOperator">
            <div id="bulkAllocation"></div>

            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Jumlah Box <span class="text-danger">*</span></label>
                    <input type="number" name="box_count" id="boxCount" class="form-control" min="1" max="2000" value="10" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Berat per Box (kg) <span class="text-danger">*</span></label>
                    <input type="number" name="weight_per_box_kg" id="weightPerBox" class="form-control" step="0.001" min="0.001" required>
                </div>
                <div class="col-md-3">
                    <div class="p-2 rounded-3 bg-light text-center">
                        <div class="text-muted" style="font-size:.7rem">Total dibutuhkan</div>
                        <div class="fw-bold" id="bulkNeeded">0,000 kg</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100" id="bulkSubmit" disabled>
                        <i class="bi bi-plus-square me-1"></i>Generate Box
                    </button>
                </div>
                <div class="col-12">
                    <div id="bulkWarning" class="alert alert-warning py-2 px-3 small mb-0" style="display:none"></div>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" maxlength="500">
                </div>
            </div>
        </form>
    </div>

    {{-- ══ Mode timbang per box ══ --}}
    <div class="tab-pane fade" id="tabPerBox">
        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="form-card">
                    <h6 class="fw-bold mb-2">Timbang Satu per Satu</h6>
                    <p class="text-muted small mb-3">
                        Masukkan berat lalu tekan Enter. Box langsung terbit beserta QR-nya,
                        dan kursor kembali ke kolom berat untuk box berikutnya.
                    </p>
                    <div class="input-group input-group-lg">
                        <input type="number" id="singleWeight" class="form-control" step="0.001" min="0.001"
                               placeholder="Berat box" autocomplete="off">
                        <span class="input-group-text">kg</span>
                        <button class="btn btn-primary" id="singleSubmit" disabled>Terbitkan</button>
                    </div>
                    <div id="singleFeedback" class="small mt-2"></div>
                </div>
            </div>
            <div class="col-12 col-lg-7">
                <div class="form-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0">Box Sesi Ini (<span id="sessionCount">0</span>)</h6>
                        <button class="btn btn-sm btn-outline-primary" id="printSession" disabled>
                            <i class="bi bi-printer me-1"></i>Cetak Label
                        </button>
                    </div>
                    <div id="sessionBoxes" class="d-flex flex-column gap-2">
                        <div class="text-muted small text-center py-3">Belum ada box pada sesi ini.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const LOTS_URL  = @json(route('api.packing.lots'));
const WEIGH_URL = @json(route('packing.weigh'));
const CSRF      = @json(csrf_token());

let lots = [];                 // lot tersedia untuk produk terpilih
const sessionBoxIds = [];      // box hasil mode timbang-per-box

const fmt = n => n.toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

const poSelect      = document.getElementById('poSelect');
const productSelect = document.getElementById('productSelect');

// Ganti PO memuat ulang halaman supaya panel stok produk ikut menyesuaikan.
poSelect.addEventListener('change', function () {
    window.location = '{{ route('packing.create') }}' + (this.value ? ('?po_id=' + this.value) : '');
});

productSelect.addEventListener('change', async function () {
    const wpb = this.options[this.selectedIndex]?.dataset.wpb;
    if (wpb && wpb > 0) document.getElementById('weightPerBox').value = parseFloat(wpb).toFixed(3);
    await loadLots();
    recalc();
});

async function loadLots() {
    const poId      = poSelect.value;
    const productId = productSelect.value;
    const wrap      = document.getElementById('lotList');

    if (!poId || !productId) {
        wrap.innerHTML = '<div class="text-muted small text-center py-3">Pilih PO dan produk terlebih dahulu.</div>';
        lots = [];
        return;
    }

    wrap.innerHTML = '<div class="text-muted small text-center py-3">Memuat…</div>';

    try {
        const res = await fetch(`${LOTS_URL}?po_id=${poId}&product_id=${productId}`, { headers: { 'Accept': 'application/json' } });
        lots = await res.json();
    } catch (e) {
        wrap.innerHTML = '<div class="text-danger small text-center py-3">Gagal memuat lot.</div>';
        return;
    }

    if (!lots.length) {
        wrap.innerHTML = '<div class="text-muted small text-center py-3">' +
            'Belum ada lot siap packing. Selesaikan tahap terakhir produksi terlebih dahulu.</div>';
        document.getElementById('fifoBtn').disabled = true;
        return;
    }

    wrap.innerHTML = '';
    lots.forEach(lot => {
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-2 p-2 mb-1 rounded-3 border';
        row.innerHTML = `
            <input type="checkbox" class="form-check-input lot-check" data-id="${lot.id}">
            <div class="flex-grow-1" style="min-width:0">
                <div style="font-family:ui-monospace,monospace;font-size:.72rem">${lot.batch_number}</div>
                <div class="text-muted" style="font-size:.68rem">
                    ${lot.supplier_code ?? '-'} · ${lot.effective_date ?? ''} · sisa ${lot.available_kg} kg
                    ${lot.is_mixed ? '<span class="badge bg-warning-subtle text-warning ms-1" style="font-size:.6rem">gabungan</span>' : ''}
                </div>
            </div>
            <div class="input-group input-group-sm" style="max-width:150px">
                <input type="number" class="form-control lot-kg" data-id="${lot.id}"
                       step="0.001" min="0" max="${lot.available_kg}" value="0" disabled>
                <span class="input-group-text">kg</span>
            </div>`;
        wrap.appendChild(row);
    });

    wrap.querySelectorAll('.lot-check').forEach(chk => chk.addEventListener('change', function () {
        const kg = wrap.querySelector(`.lot-kg[data-id="${this.dataset.id}"]`);
        kg.disabled = !this.checked;
        if (this.checked) {
            const lot = lots.find(l => l.id == this.dataset.id);
            if (parseFloat(kg.value || 0) === 0) kg.value = lot.available_kg;
        } else {
            kg.value = 0;
        }
        recalc();
    }));
    wrap.querySelectorAll('.lot-kg').forEach(el => el.addEventListener('input', recalc));

    document.getElementById('fifoBtn').disabled = false;
}

// FIFO: ambil dari lot tertua dulu sampai kebutuhan terpenuhi.
document.getElementById('fifoBtn').addEventListener('click', () => {
    let butuh = (parseInt(document.getElementById('boxCount').value || 0)) *
                (parseFloat(document.getElementById('weightPerBox').value || 0));
    if (butuh <= 0) { alert('Isi jumlah box dan berat per box terlebih dahulu.'); return; }

    document.querySelectorAll('.lot-check').forEach(chk => {
        const kgEl = document.querySelector(`.lot-kg[data-id="${chk.dataset.id}"]`);
        const lot  = lots.find(l => l.id == chk.dataset.id);
        const ambil = Math.min(butuh, lot.available_kg);

        chk.checked   = ambil > 0;
        kgEl.disabled = ambil <= 0;
        kgEl.value    = ambil > 0 ? ambil.toFixed(3) : 0;
        butuh = Math.max(0, +(butuh - ambil).toFixed(3));
    });
    recalc();
});

function allocation() {
    return Array.from(document.querySelectorAll('.lot-check:checked')).map(chk => ({
        batch_id: parseInt(chk.dataset.id),
        kg: parseFloat(document.querySelector(`.lot-kg[data-id="${chk.dataset.id}"]`).value || 0),
    })).filter(a => a.kg > 0);
}

function recalc() {
    const alloc = allocation();
    const total = alloc.reduce((s, a) => s + a.kg, 0);
    document.getElementById('totalAllocated').textContent = fmt(total) + ' kg';

    const boxes = parseInt(document.getElementById('boxCount').value || 0);
    const wpb   = parseFloat(document.getElementById('weightPerBox').value || 0);
    const butuh = boxes * wpb;
    document.getElementById('bulkNeeded').textContent = fmt(butuh) + ' kg';

    const warn = document.getElementById('bulkWarning');
    const siap = alloc.length > 0 && butuh > 0 && butuh <= total + 0.001 && productSelect.value;

    if (alloc.length && butuh > total + 0.001) {
        warn.style.display = '';
        warn.innerHTML = `Butuh <strong>${fmt(butuh)} kg</strong> tetapi lot terpilih hanya menyediakan
                          <strong>${fmt(total)} kg</strong>. Tambah lot atau kurangi jumlah box.`;
    } else {
        warn.style.display = 'none';
    }

    document.getElementById('bulkSubmit').disabled  = !siap;
    document.getElementById('singleSubmit').disabled = !(alloc.length && productSelect.value);
}

document.getElementById('boxCount').addEventListener('input', recalc);
document.getElementById('weightPerBox').addEventListener('input', recalc);

// Alokasi baru disusun menjadi field form saat submit, supaya baris yang
// tidak tercentang tidak ikut terkirim.
document.getElementById('bulkForm').addEventListener('submit', function (e) {
    const alloc = allocation();
    if (!alloc.length) { e.preventDefault(); alert('Pilih minimal satu lot sumber.'); return; }

    document.getElementById('bulkPo').value       = poSelect.value;
    document.getElementById('bulkProduct').value  = productSelect.value;
    document.getElementById('bulkDate').value     = document.getElementById('packingDate').value;
    document.getElementById('bulkOperator').value = document.getElementById('operatorName').value;

    const wrap = document.getElementById('bulkAllocation');
    wrap.innerHTML = '';
    alloc.forEach((a, i) => {
        wrap.insertAdjacentHTML('beforeend',
            `<input type="hidden" name="allocation[${i}][batch_id]" value="${a.batch_id}">
             <input type="hidden" name="allocation[${i}][kg]" value="${a.kg}">`);
    });
});

// ── Mode timbang per box ──────────────────────────────────────
async function terbitkanBox() {
    const berat = parseFloat(document.getElementById('singleWeight').value || 0);
    const fb    = document.getElementById('singleFeedback');

    if (berat <= 0) { fb.className = 'small mt-2 text-danger'; fb.textContent = 'Isi berat box.'; return; }

    const alloc = allocation();
    if (!alloc.length) { fb.className = 'small mt-2 text-danger'; fb.textContent = 'Pilih lot sumber terlebih dahulu.'; return; }

    fb.className = 'small mt-2 text-muted';
    fb.textContent = 'Menerbitkan box…';

    try {
        const res = await fetch(WEIGH_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({
                purchase_order_id: poSelect.value,
                product_type_id:   productSelect.value,
                net_weight_kg:     berat,
                packing_date:      document.getElementById('packingDate').value,
                operator_name:     document.getElementById('operatorName').value,
                allocation:        alloc,
            }),
        });

        const data = await res.json();
        if (!res.ok) {
            fb.className = 'small mt-2 text-danger';
            fb.textContent = data.message || 'Gagal menerbitkan box.';
            return;
        }

        tambahBoxSesi(data);
        fb.className = 'small mt-2 text-success';
        fb.textContent = `${data.box_code} terbit (${data.net_kg} kg).`;

        // Lot berkurang, jadi daftar dimuat ulang agar sisanya benar.
        await loadLots();
        recalc();
    } catch (e) {
        fb.className = 'small mt-2 text-danger';
        fb.textContent = 'Gagal menghubungi server.';
    }

    const input = document.getElementById('singleWeight');
    input.value = '';
    input.focus();
}

document.getElementById('singleSubmit').addEventListener('click', terbitkanBox);
document.getElementById('singleWeight').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); terbitkanBox(); }
});

function tambahBoxSesi(box) {
    sessionBoxIds.push(box.id);

    const wrap = document.getElementById('sessionBoxes');
    if (sessionBoxIds.length === 1) wrap.innerHTML = '';

    const row = document.createElement('div');
    row.className = 'd-flex align-items-center gap-2 p-2 rounded-3 border';
    row.innerHTML = `
        <div class="qr" data-qr="${box.qr_url}" data-size="54"></div>
        <div class="flex-grow-1">
            <div style="font-family:ui-monospace,monospace;font-size:.72rem">${box.box_code}</div>
            <div class="text-muted" style="font-size:.68rem">
                ${box.net_kg} kg ${box.is_mixed ? '· campuran' : ''}
            </div>
        </div>
        <a href="${box.label_url}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a>`;
    wrap.prepend(row);

    if (window.renderQrCodes) window.renderQrCodes();

    document.getElementById('sessionCount').textContent = sessionBoxIds.length;
    document.getElementById('printSession').disabled = false;
}

document.getElementById('printSession').addEventListener('click', () => {
    if (sessionBoxIds.length) {
        window.open('{{ route('packing.labels') }}?ids=' + sessionBoxIds.join(','), '_blank');
    }
});

// ── Awal ──────────────────────────────────────────────────────
@if($selectedPO)
loadLots().then(recalc);
@endif
</script>
@endpush

@include('partials._qr')
