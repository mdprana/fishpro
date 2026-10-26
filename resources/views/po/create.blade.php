@extends('layouts.app')
@section('title','Buat PO')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('po.index') }}">Purchase Order</a></li>
    <li class="breadcrumb-item active">Buat PO Baru</li>
@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-12 col-lg-9">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('po.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h4 class="fw-bold mb-0">📋 Buat Purchase Order Baru</h4>
            <p class="text-muted mb-0 small">Pilih Group → Tipe Produk. Target box ↔ berat/box saling hitung otomatis.</p>
        </div>
    </div>

    <form action="{{ route('po.store') }}" method="POST">
        @csrf

        {{-- Info PO --}}
        <div class="form-card mb-3">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-file-text me-2"></i>Informasi PO</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nomor PO <span class="text-danger">*</span></label>
                    <input type="text" name="po_number"
                           class="form-control @error('po_number') is-invalid @enderror"
                           value="{{ old('po_number') }}" placeholder="PO-2024-001" required>
                    @error('po_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nama Buyer <span class="text-danger">*</span></label>
                    <input type="text" name="buyer_name"
                           class="form-control @error('buyer_name') is-invalid @enderror"
                           value="{{ old('buyer_name') }}" placeholder="PT / CV / Nama" required>
                    @error('buyer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Deadline <span class="text-danger">*</span></label>
                    <input type="date" name="deadline"
                           class="form-control @error('deadline') is-invalid @enderror"
                           value="{{ old('deadline') }}" required>
                    @error('deadline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Order <span class="text-danger">*</span></label>
                    <input type="date" name="order_date"
                           class="form-control @error('order_date') is-invalid @enderror"
                           value="{{ old('order_date', today()->format('Y-m-d')) }}" required>
                    @error('order_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control"
                           value="{{ old('notes') }}" placeholder="Opsional">
                </div>
            </div>
        </div>

        {{-- Item Produk --}}
        <div class="form-card mb-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h6 class="fw-bold mb-0"><i class="bi bi-grid me-2 text-success"></i>Item Produk</h6>
                    <small class="text-muted">Isi dua dari tiga: Berat, Box, atau Kg/Box. Yang ketiga otomatis terhitung</small>
                </div>
                <button type="button" class="btn btn-success btn-sm" id="addItem">
                    <i class="bi bi-plus-lg me-1"></i>Tambah Produk
                </button>
            </div>

            <div id="itemsContainer"></div>
            @error('items')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        {{-- Summary --}}
        <div class="form-card mb-4 bg-light" id="summaryCard" style="display:none">
            <h6 class="fw-bold mb-2" style="font-size:.88rem">📊 Ringkasan</h6>
            <div class="row g-2 text-center">
                <div class="col-4"><div class="p-2 bg-white rounded-2">
                    <div class="text-muted small">Produk</div>
                    <div class="fw-bold fs-5" id="sumProducts">0</div>
                </div></div>
                <div class="col-4"><div class="p-2 bg-white rounded-2">
                    <div class="text-muted small">Total Box</div>
                    <div class="fw-bold fs-5 text-warning" id="sumBox">0</div>
                </div></div>
                <div class="col-4"><div class="p-2 bg-white rounded-2">
                    <div class="text-muted small">Total Berat</div>
                    <div class="fw-bold fs-5 text-primary" id="sumWeight">0 kg</div>
                </div></div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Simpan PO</button>
            <a href="{{ route('po.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
</div>
</div>

{{-- Template item row --}}
<template id="itemTpl">
    <div class="item-row mb-3 p-3 rounded-3 border" data-idx="__IDX__" style="position:relative">
        <button type="button" class="btn btn-sm btn-outline-danger"
                style="position:absolute;top:.5rem;right:.5rem;padding:.15rem .4rem"
                onclick="removeItem(this)"><i class="bi bi-x-lg"></i></button>

        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-secondary" id="itemBadge__IDX__" style="font-size:.72rem">Produk #__NUM__</span>
        </div>

        {{-- Step 1: Pilih Group Produk --}}
        <div class="row g-2 mb-2">
            <div class="col-md-5">
                <label class="form-label" style="font-size:.78rem">Group Produk <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm item-pg-sel" data-idx="__IDX__"
                        onchange="onGroupChange(this, '__IDX__')">
                    <option value="">-- Pilih Group --</option>
                    @foreach($productsByGroup as $gId => $g)
                    <option value="{{ $gId }}" data-color="{{ $g['color'] }}">{{ $g['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-7" id="ptWrap__IDX__" style="display:none">
                <label class="form-label" style="font-size:.78rem">Tipe Produk <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-1" id="ptBtns__IDX__"></div>
                <input type="hidden" name="items[__IDX__][product_type_id]" id="ptVal__IDX__" required>
            </div>
        </div>

        {{-- 3-way kalkulasi --}}
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem">Target Berat (kg) <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                    <input type="number" name="items[__IDX__][target_weight_kg]"
                           class="form-control item-weight" step="0.01" min="0.01" placeholder="0" required>
                    <span class="input-group-text">kg</span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem">Target Box <span class="text-danger">*</span></label>
                <input type="number" name="items[__IDX__][target_box]"
                       class="form-control form-control-sm item-box" min="1" placeholder="0" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem">Kg/Box <span class="text-muted">(auto)</span></label>
                <div class="input-group input-group-sm">
                    <input type="number" name="items[__IDX__][weight_per_box_kg]"
                           class="form-control item-wpb" step="0.01" min="0.01" placeholder="auto">
                    <span class="input-group-text">kg</span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem">Catatan</label>
                <input type="text" name="items[__IDX__][notes]"
                       class="form-control form-control-sm" placeholder="Opsional">
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
// Group & produk data dari server (flat)
const prodByGroup = @json($productsByGroup);
let itemIdx = 0;

function onGroupChange(sel, idx) {
    const gId   = sel.value;
    const g     = prodByGroup[gId];
    const wrap  = document.getElementById('ptWrap' + idx);
    const btns  = document.getElementById('ptBtns' + idx);
    const hidden = document.getElementById('ptVal' + idx);
    btns.innerHTML = '';
    hidden.value = '';

    if (!g) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';

    g.products.forEach(pt => {
        const inp = document.createElement('input');
        inp.type = 'radio'; inp.className = 'btn-check';
        inp.name = `pt_radio_${idx}`; inp.id = `ptr${idx}_${pt.id}`;
        inp.addEventListener('change', function() {
            hidden.value = pt.id;
            updateItemBadge(idx, pt.name, pt.color);
            updateSummary();
        });

        const lbl = document.createElement('label');
        lbl.htmlFor = `ptr${idx}_${pt.id}`;
        lbl.className = 'btn btn-sm product-btn';
        lbl.style.cssText = `background:${pt.color}18;border:1px solid ${pt.color}55;color:${pt.color};font-size:.73rem;padding:.2rem .5rem`;
        lbl.textContent = pt.name;

        btns.appendChild(inp);
        btns.appendChild(lbl);
    });
}

function updateItemBadge(idx, name, color) {
    const badge = document.getElementById('itemBadge' + idx);
    if (badge) {
        badge.textContent = name;
        badge.style.cssText = `background:${color}22;color:${color};border:1px solid ${color}44;font-size:.72rem`;
    }
}

function addItem() {
    const tpl  = document.getElementById('itemTpl').innerHTML;
    const num  = document.querySelectorAll('.item-row').length + 1;
    const html = tpl.replaceAll('__IDX__', itemIdx).replaceAll('__NUM__', num);
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    const el = wrap.firstElementChild;
    document.getElementById('itemsContainer').appendChild(el);

    const tw  = el.querySelector('.item-weight');
    const tb  = el.querySelector('.item-box');
    const wpb = el.querySelector('.item-wpb');

    // 3-way auto calc
    tw.addEventListener('input',  () => { if (tb.value)  wpb.value = (parseFloat(tw.value)/parseInt(tb.value)).toFixed(2); updateSummary(); });
    tb.addEventListener('input',  () => { if (tw.value)  wpb.value = (parseFloat(tw.value)/parseInt(tb.value)).toFixed(2); updateSummary(); });
    wpb.addEventListener('input', () => {
        const w = parseFloat(tw.value)||0, b = parseInt(tb.value)||0, p = parseFloat(wpb.value)||0;
        if (w > 0 && p > 0 && !tb.value) { tb.value = Math.ceil(w/p); }
        else if (b > 0 && p > 0 && !tw.value) { tw.value = (b*p).toFixed(2); }
        else if (w > 0 && b > 0) {} // berat & box diisi, wpb sudah auto
        updateSummary();
    });

    itemIdx++;
    updateSummary();
}

function removeItem(btn) {
    btn.closest('.item-row').remove();
    updateSummary();
}

function updateSummary() {
    const rows = document.querySelectorAll('.item-row');
    let totBox = 0, totWeight = 0;
    rows.forEach(r => {
        totBox    += parseInt(r.querySelector('.item-box')?.value)    || 0;
        totWeight += parseFloat(r.querySelector('.item-weight')?.value) || 0;
    });
    document.getElementById('sumProducts').textContent = rows.length;
    document.getElementById('sumBox').textContent      = totBox;
    document.getElementById('sumWeight').textContent   = totWeight.toFixed(1) + ' kg';
    document.getElementById('summaryCard').style.display = rows.length > 0 ? 'block' : 'none';
}

document.getElementById('addItem').addEventListener('click', addItem);
addItem(); // Auto 1 row
</script>
@endpush
