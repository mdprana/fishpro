@extends('layouts.app')
@section('title','Terima Bahan Baku')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('receiving.index') }}">Bahan Baku</a></li>
    <li class="breadcrumb-item active">Terima</li>
@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-12 col-lg-8">
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('receiving.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-box-arrow-in-down me-2 text-primary"></i>Terima Bahan Baku</h4>
            <p class="text-muted small mb-0">Stok otomatis bertambah. Estimasi yield tampil berdasarkan subgroup.</p>
        </div>
    </div>

    <form action="{{ route('receiving.store') }}" method="POST">
        @csrf

        <div class="form-card mb-3">
            <h6 class="fw-bold text-primary mb-3">Pilih Bahan Baku</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Group Bahan Baku <span class="text-danger">*</span></label>
                    <select id="groupSel" class="form-select">
                        <option value="">-- Pilih Group --</option>
                        @foreach($groups as $g)
                        <option value="{{ $g->id }}" data-color="{{ $g->color }}">{{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6" id="subgroupWrap" style="display:none">
                    <label class="form-label">Subgroup Bahan Baku <span class="text-danger">*</span></label>
                    <select name="material_subgroup_id" id="subgroupSel"
                            class="form-select @error('material_subgroup_id') is-invalid @enderror" required>
                        <option value="">-- Pilih Subgroup --</option>
                    </select>
                    @error('material_subgroup_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12" id="stockRow" style="display:none">
                    <div class="p-2 rounded-2 bg-light d-flex align-items-center gap-3" style="font-size:.8rem">
                        <span class="text-muted">Stok saat ini:</span>
                        <span class="fw-bold text-primary" id="stockVal">-</span>
                        <span class="text-muted ms-2">Total yield dikonfigurasi:</span>
                        <span class="fw-bold" id="totalPctVal">-</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Estimasi yield dari subgroup --}}
        <div id="yieldEstBox" class="form-card mb-3" style="display:none;border-left:3px solid #16a34a">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-percent text-success"></i>
                <span class="fw-semibold small">Estimasi Hasil Produk dari Subgroup ini</span>
            </div>
            <div id="yieldEstRows" class="d-flex flex-wrap gap-1 mb-1"></div>
        </div>

        <div class="form-card mb-3">
            <h6 class="fw-bold mb-3">Detail Penerimaan</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nomor PO <span class="text-danger">*</span></label>
                    <select name="purchase_order_id" class="form-select @error('purchase_order_id') is-invalid @enderror" required>
                        <option value="">-- Pilih PO --</option>
                        @foreach($activePOs as $po)
                        <option value="{{ $po->id }}" {{ old('purchase_order_id', request('po_id'))==$po->id?'selected':'' }}>
                            {{ $po->po_number }} - {{ $po->buyer_name }}
                        </option>
                        @endforeach
                    </select>
                    @error('purchase_order_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" style="font-size:.7rem">Barang masuk selalu terhubung ke satu PO.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Berat (kg) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="weight_kg" id="weightKg"
                               class="form-control @error('weight_kg') is-invalid @enderror"
                               value="{{ old('weight_kg') }}" step="0.01" min="0.01" required>
                        <span class="input-group-text">kg</span>
                    </div>
                    @error('weight_kg')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Terima <span class="text-danger">*</span></label>
                    <input type="date" name="received_date"
                           class="form-control @error('received_date') is-invalid @enderror"
                           value="{{ old('received_date', today()->format('Y-m-d')) }}" required>
                    @error('received_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-5">
                    <label class="form-label">Jenis Wadah <span class="text-danger">*</span></label>
                    <input type="text" name="container_type" id="cType"
                           class="form-control @error('container_type') is-invalid @enderror"
                           list="cList" value="{{ old('container_type','Box Polos') }}"
                           placeholder="Tulis atau pilih…" required autocomplete="off">
                    <datalist id="cList">
                        @foreach($containerSuggests as $cs)
                        <option value="{{ $cs }}">
                        @endforeach
                    </datalist>
                    @error('container_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Jumlah Wadah <span class="text-danger">*</span></label>
                    <input type="number" name="container_count" id="cCount"
                           class="form-control" value="{{ old('container_count',1) }}" min="1" required>
                    <div id="avgWadah" class="text-muted small mt-1"></div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">No. Surat Jalan</label>
                    <input type="text" name="doc_number" class="form-control"
                           value="{{ old('doc_number') }}" placeholder="Opsional">
                </div>

                <div class="col-md-3">
                    <label class="form-label">No. Kendaraan</label>
                    <input type="text" name="vehicle_number" class="form-control"
                           value="{{ old('vehicle_number') }}" placeholder="Opsional">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jam Bongkar</label>
                    <div class="input-group">
                        <input type="time" name="unload_start" class="form-control" value="{{ old('unload_start') }}">
                        <span class="input-group-text">-</span>
                        <input type="time" name="unload_end" class="form-control" value="{{ old('unload_end') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Suhu (°C)</label>
                    <input type="number" name="temperature_c" class="form-control"
                           value="{{ old('temperature_c') }}" step="0.1" min="-50" max="50" placeholder="Opsional">
                </div>

                <div class="col-12">
                    <div class="p-3 rounded-3 border bg-light">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="split_per_container" value="1"
                                   id="splitPerContainer" {{ old('split_per_container') ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="splitPerContainer">
                                Buat label QR terpisah untuk tiap wadah
                            </label>
                        </div>
                        <div class="text-muted small mt-1" id="splitHint">
                            Aktifkan bila tiap keranjang/palet akan bergerak sendiri-sendiri di lantai produksi.
                            Bila tidak, seluruh kiriman memakai satu batch induk.
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="d-flex flex-wrap gap-1">
                        @foreach(['Box Polos','Keranjang','Karung','Drum','Styrofoam Box','Peti Kayu'] as $w)
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2"
                                style="font-size:.74rem"
                                onclick="document.getElementById('cType').value='{{ $w }}'">
                            {{ $w }}
                        </button>
                        @endforeach
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                        <option value="">-- Pilih Supplier --</option>
                        @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" {{ old('supplier_id') == $sup->id ? 'selected' : '' }}>
                            {{ $sup->display_name }}
                        </option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" style="font-size:.7rem">
                        Kode supplier menjadi bagian nomor batch.
                        <a href="{{ route('master.suppliers') }}" target="_blank">Kelola supplier</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control"
                           value="{{ old('notes') }}" placeholder="Opsional">
                </div>
            </div>
        </div>

        {{-- Estimasi output nyata berdasarkan berat x yield --}}
        <div id="estOutputBox" class="form-card mb-4 bg-light" style="display:none">
            <h6 class="fw-bold mb-2">📊 Estimasi Output dari Penerimaan Ini</h6>
            <div class="row g-2" id="estOutputRows"></div>
            <p class="text-muted small mb-0 mt-2">*Berdasarkan estimasi yield di master data</p>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-lg me-1"></i>Simpan
            </button>
            <a href="{{ route('receiving.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
</div>
</div>
@endsection


@push('scripts')
<script>

const groupData = @json($groupData);

let currentYields = [];

const groupSel = document.getElementById('groupSel');
const subSel = document.getElementById('subgroupSel');
const subWrap = document.getElementById('subgroupWrap');
const weightEl = document.getElementById('weightKg');
const cCountEl = document.getElementById('cCount');

groupSel.addEventListener('change', function () {

    const selectedId = parseInt(this.value);
    const g = groupData.find(item => item.id == selectedId);

    subSel.innerHTML = '<option value="">-- Pilih Subgroup --</option>';

    document.getElementById('stockRow').style.display = 'none';
    document.getElementById('yieldEstBox').style.display = 'none';
    document.getElementById('estOutputBox').style.display = 'none';

    currentYields = [];

    if (!g) {
        subWrap.style.display = 'none';
        return;
    }

    subWrap.style.display = 'block';

    g.subgroups.forEach(function (s) {

        const opt = document.createElement('option');

        opt.value = s.id;
        opt.textContent = s.name;
        opt.dataset.stock = s.stock;
        opt.dataset.totalPct = s.total_pct;
        opt.dataset.yields = JSON.stringify(s.yields);

        subSel.appendChild(opt);
    });
});

subSel.addEventListener('change', function () {

    const opt = this.options[this.selectedIndex];

    if (!opt.value) {

        document.getElementById('stockRow').style.display = 'none';
        document.getElementById('yieldEstBox').style.display = 'none';
        document.getElementById('estOutputBox').style.display = 'none';

        currentYields = [];
        return;
    }

    const stock = parseFloat(opt.dataset.stock || 0);
    const totalPct = parseFloat(opt.dataset.totalPct || 0);

    currentYields = JSON.parse(opt.dataset.yields || '[]');

    document.getElementById('stockVal').textContent =
        stock.toFixed(1) + ' kg';

    document.getElementById('totalPctVal').textContent =
        totalPct + '%';

    document.getElementById('totalPctVal').className =
        totalPct == 100
            ? 'fw-bold text-success'
            : 'fw-bold text-warning';

    document.getElementById('stockRow').style.display = 'block';

    renderYieldBadges();
    updateEstOutput();
});

function renderYieldBadges()
{
    const yieldBox = document.getElementById('yieldEstBox');
    const yieldRows = document.getElementById('yieldEstRows');

    yieldRows.innerHTML = '';

    if (currentYields.length === 0) {
        yieldBox.style.display = 'none';
        return;
    }

    currentYields.forEach(function (y) {

        const span = document.createElement('span');

        span.className = 'badge rounded-pill';

        span.style.cssText =
            'background:' + y.color + '22;' +
            'color:' + y.color + ';' +
            'border:1px solid ' + y.color + '44;' +
            'font-size:.75rem';

        span.textContent =
            y.product + ': ' + y.percentage + '%';

        yieldRows.appendChild(span);
    });

    yieldBox.style.display = 'block';
}

function updateEstOutput()
{
    const wt = parseFloat(weightEl.value || 0);

    if (wt <= 0 || currentYields.length === 0) {

        document.getElementById('estOutputBox').style.display = 'none';
        return;
    }

    const rowsEl = document.getElementById('estOutputRows');

    rowsEl.innerHTML = '';

    currentYields.forEach(function (y) {

        const kg = (
            wt * parseFloat(y.percentage) / 100
        ).toFixed(2);

        const div = document.createElement('div');

        div.className = 'col-6 col-md-3';

        div.innerHTML =
            '<div class="p-2 rounded-2 text-center" ' +
            'style="background:' + y.color + '18;border:1px solid ' + y.color + '33">' +
            '<div style="font-size:.72rem;color:' + y.color + ';font-weight:600">' +
            y.product +
            '</div>' +
            '<div class="fw-bold" style="color:' + y.color + ';font-size:1.15rem">' +
            kg +
            '</div>' +
            '<div class="text-muted" style="font-size:.68rem">' +
            'kg (' + y.percentage + '%)' +
            '</div>' +
            '</div>';

        rowsEl.appendChild(div);
    });

    document.getElementById('estOutputBox').style.display = 'block';
}

function updateAvg()
{
    const w = parseFloat(weightEl.value || 0);
    const c = parseInt(cCountEl.value || 0);

    document.getElementById('avgWadah').textContent =
        (w > 0 && c > 0)
            ? 'Rata-rata: ' + (w / c).toFixed(2) + ' kg/wadah'
            : '';

    updateSplitHint();
}

// Beri tahu operator berapa label yang akan tercetak sebelum ia menyimpan,
// supaya tidak kaget mendapat 30 lembar label.
function updateSplitHint()
{
    const split = document.getElementById('splitPerContainer');
    const hint  = document.getElementById('splitHint');
    if (!split || !hint) return;

    const w = parseFloat(weightEl.value || 0);
    const c = parseInt(cCountEl.value || 0);

    if (split.checked && c > 1 && w > 0) {
        hint.innerHTML = '<strong>' + c + ' lot</strong> akan dibuat, masing-masing ±'
            + (w / c).toFixed(2) + ' kg, dengan <strong>' + c + ' label QR</strong>.';
    } else if (split.checked && c <= 1) {
        hint.textContent = 'Jumlah wadah masih 1, jadi tetap terbit satu batch induk saja.';
    } else {
        hint.textContent = 'Aktifkan bila tiap keranjang/palet akan bergerak sendiri-sendiri di lantai produksi. '
            + 'Bila tidak, seluruh kiriman memakai satu batch induk.';
    }
}

document.getElementById('splitPerContainer')?.addEventListener('change', updateSplitHint);

weightEl.addEventListener('input', function () {
    updateEstOutput();
    updateAvg();
});

cCountEl.addEventListener('input', updateAvg);

</script>
@endpush
