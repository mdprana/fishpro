@extends('layouts.app')
@section('title','Edit PO')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('po.index') }}">Purchase Order</a></li>
    <li class="breadcrumb-item"><a href="{{ route('po.show',$po) }}">{{ $po->po_number }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-12 col-lg-9">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('po.show',$po) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h4 class="fw-bold mb-0">Edit PO: {{ $po->po_number }}</h4>
    </div>

    <form action="{{ route('po.update',$po) }}" method="POST">
        @csrf @method('PUT')

        <div class="form-card mb-3">
            <h6 class="fw-bold text-primary mb-3">Informasi PO</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nomor PO</label>
                    <input type="text" class="form-control bg-light" value="{{ $po->po_number }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nama Buyer <span class="text-danger">*</span></label>
                    <input type="text" name="buyer_name" class="form-control @error('buyer_name') is-invalid @enderror"
                           value="{{ old('buyer_name',$po->buyer_name) }}" required>
                    @error('buyer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach(['draft','active','in_progress','completed','cancelled'] as $s)
                        <option value="{{ $s }}" {{ old('status',$po->status)===$s?'selected':'' }}>
                            {{ ucfirst(str_replace('_',' ',$s)) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Order <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" class="form-control"
                           value="{{ old('order_date',$po->order_date->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Deadline <span class="text-danger">*</span></label>
                    <input type="date" name="deadline" class="form-control @error('deadline') is-invalid @enderror"
                           value="{{ old('deadline',$po->deadline->format('Y-m-d')) }}" required>
                    @error('deadline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" value="{{ old('notes',$po->notes) }}">
                </div>
            </div>
        </div>

        <div class="form-card mb-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h6 class="fw-bold mb-0"><i class="bi bi-grid me-2 text-success"></i>Item Produk</h6>
                    <small class="text-muted">Pilih Group Produk → Tipe Produk. Kalkulasi 3-arah otomatis.</small>
                </div>
                <button type="button" class="btn btn-success btn-sm" id="addItem">
                    <i class="bi bi-plus-lg me-1"></i>Tambah
                </button>
            </div>
            <div id="itemsContainer">
                @foreach($po->items as $idx => $item)
                <div class="item-row mb-3 p-3 rounded-3 border" data-idx="{{ $idx }}" style="position:relative">
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            style="position:absolute;top:.5rem;right:.5rem;padding:.15rem .4rem"
                            onclick="removeItem(this)"><i class="bi bi-x-lg"></i></button>

                    {{-- Tanpa id ini, server tidak tahu baris mana yang lama dan
                         terpaksa membuat ulang semua item (yang dulu ikut menghapus packing). --}}
                    <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $item->id }}">

                    <div class="mb-2">
                        <span class="badge fw-semibold dyn-text" id="itemBadge{{ $idx }}"
                              style="background:{{ $item->productType->effective_color }}22;--c:{{ $item->productType->effective_color }};border:1px solid {{ $item->productType->effective_color }}44;font-size:.72rem">
                            {{ $item->productType->group_name }} → {{ $item->productType->name }}
                        </span>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <label class="form-label" style="font-size:.78rem">Group Produk</label>
                            <select class="form-select form-select-sm item-pg-sel"
                                    onchange="onGroupChange(this, '{{ $idx }}')">
                                <option value="">-- Pilih Group --</option>
                                @foreach($productsByGroup as $gId => $g)
                                <option value="{{ $gId }}"
                                    {{ $item->productType->product_group_id == $gId ? 'selected' : '' }}>
                                    {{ $g['name'] }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7" id="ptWrap{{ $idx }}">
                            <label class="form-label" style="font-size:.78rem">Tipe Produk <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-1" id="ptBtns{{ $idx }}">
                                {{-- Pre-render tipe produk dari group yang sudah dipilih --}}
                                @php $curGroup = $productsByGroup[$item->productType->product_group_id] ?? null; @endphp
                                @if($curGroup)
                                @foreach($curGroup['products'] as $pt)
                                <input type="radio" class="btn-check" name="pt_radio_{{ $idx }}"
                                       id="ptr{{ $idx }}_{{ $pt['id'] }}" value="{{ $pt['id'] }}"
                                       {{ $item->product_type_id == $pt['id'] ? 'checked' : '' }}>
                                <label class="btn btn-sm product-btn dyn-text" for="ptr{{ $idx }}_{{ $pt['id'] }}"
                                       style="background:{{ $pt['color'] }}18;border:1px solid {{ $pt['color'] }}55;--c:{{ $pt['color'] }};font-size:.73rem;padding:.2rem .5rem">
                                    {{ $pt['name'] }}
                                </label>
                                @endforeach
                                @endif
                            </div>
                            <input type="hidden" name="items[{{ $idx }}][product_type_id]"
                                   id="ptVal{{ $idx }}" value="{{ $item->product_type_id }}" required>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:.78rem">Target Berat (kg) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="items[{{ $idx }}][target_weight_kg]"
                                       class="form-control item-weight"
                                       value="{{ $item->target_weight_kg }}" step="0.01" min="0.01" required>
                                <span class="input-group-text">kg</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:.78rem">Target Box <span class="text-danger">*</span></label>
                            <input type="number" name="items[{{ $idx }}][target_box]"
                                   class="form-control form-control-sm item-box"
                                   value="{{ $item->target_box }}" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:.78rem">Kg/Box</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="items[{{ $idx }}][weight_per_box_kg]"
                                       class="form-control item-wpb"
                                       value="{{ $item->effective_weight_per_box > 0 ? number_format($item->effective_weight_per_box,2,'.','') : '' }}"
                                       step="0.01" min="0.01" placeholder="auto">
                                <span class="input-group-text">kg</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:.78rem">Catatan</label>
                            <input type="text" name="items[{{ $idx }}][notes]"
                                   class="form-control form-control-sm" value="{{ $item->notes }}">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Simpan</button>
            <a href="{{ route('po.show',$po) }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
</div>
</div>

<template id="itemTpl">
    <div class="item-row mb-3 p-3 rounded-3 border" data-idx="__IDX__" style="position:relative">
        <button type="button" class="btn btn-sm btn-outline-danger"
                style="position:absolute;top:.5rem;right:.5rem;padding:.15rem .4rem"
                onclick="removeItem(this)"><i class="bi bi-x-lg"></i></button>
        <div class="mb-2">
            <span class="badge bg-secondary" id="itemBadge__IDX__" style="font-size:.72rem">Produk Baru</span>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-5">
                <label class="form-label" style="font-size:.78rem">Group Produk</label>
                <select class="form-select form-select-sm item-pg-sel" onchange="onGroupChange(this,'__IDX__')">
                    <option value="">-- Pilih Group --</option>
                    @foreach($productsByGroup as $gId => $g)
                    <option value="{{ $gId }}">{{ $g['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-7" id="ptWrap__IDX__" style="display:none">
                <label class="form-label" style="font-size:.78rem">Tipe Produk <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-1" id="ptBtns__IDX__"></div>
                <input type="hidden" name="items[__IDX__][product_type_id]" id="ptVal__IDX__" required>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem">Target Berat (kg) <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                    <input type="number" name="items[__IDX__][target_weight_kg]"
                           class="form-control item-weight" step="0.01" min="0.01" required>
                    <span class="input-group-text">kg</span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem">Target Box <span class="text-danger">*</span></label>
                <input type="number" name="items[__IDX__][target_box]"
                       class="form-control form-control-sm item-box" min="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem">Kg/Box</label>
                <div class="input-group input-group-sm">
                    <input type="number" name="items[__IDX__][weight_per_box_kg]"
                           class="form-control item-wpb" step="0.01" min="0.01" placeholder="auto">
                    <span class="input-group-text">kg</span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.78rem">Catatan</label>
                <input type="text" name="items[__IDX__][notes]" class="form-control form-control-sm">
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
const prodByGroup = @json($productsByGroup);
let itemIdx = {{ $po->items->count() }};

// Bind existing rows
document.querySelectorAll('.item-row').forEach(row => bindRowEvents(row));

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
        inp.addEventListener('change', () => {
            hidden.value = pt.id;
            updateBadge(idx, pt.name, pt.color);
        });
        const lbl = document.createElement('label');
        lbl.htmlFor = `ptr${idx}_${pt.id}`;
        lbl.className = 'btn btn-sm product-btn dyn-text';
        lbl.style.cssText = `background:${pt.color}18;border:1px solid ${pt.color}55;--c:${pt.color};font-size:.73rem;padding:.2rem .5rem`;
        lbl.textContent = pt.name;
        btns.appendChild(inp); btns.appendChild(lbl);
    });
}

// Bind radio dari existing rows
document.querySelectorAll('.item-row').forEach(row => {
    const idx = row.dataset.idx;
    row.querySelectorAll(`input[name="pt_radio_${idx}"]`).forEach(r => {
        r.addEventListener('change', function() {
            document.getElementById('ptVal' + idx).value = this.value;
        });
    });
});

function updateBadge(idx, name, color) {
    const b = document.getElementById('itemBadge' + idx);
    if (b) { b.textContent = name; b.style.cssText = `background:${color}22;--c:${color};border:1px solid ${color}44;font-size:.72rem`; }
}

function bindRowEvents(el) {
    const tw  = el.querySelector('.item-weight');
    const tb  = el.querySelector('.item-box');
    const wpb = el.querySelector('.item-wpb');
    tw?.addEventListener('input',  () => { if(tw.value&&tb.value) wpb.value=(parseFloat(tw.value)/parseInt(tb.value)).toFixed(2); });
    tb?.addEventListener('input',  () => { if(tw.value&&tb.value) wpb.value=(parseFloat(tw.value)/parseInt(tb.value)).toFixed(2); });
    wpb?.addEventListener('input', () => {
        const w=parseFloat(tw?.value)||0,b=parseInt(tb?.value)||0,p=parseFloat(wpb.value)||0;
        if(w>0&&p>0&&!tb.value) tb.value=Math.ceil(w/p);
        else if(b>0&&p>0&&!tw.value) tw.value=(b*p).toFixed(2);
    });
}

function addItem() {
    const tpl  = document.getElementById('itemTpl').innerHTML;
    const html = tpl.replaceAll('__IDX__', itemIdx).replaceAll('__NUM__', document.querySelectorAll('.item-row').length+1);
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    const el = wrap.firstElementChild;
    document.getElementById('itemsContainer').appendChild(el);
    bindRowEvents(el);
    itemIdx++;
}

function removeItem(btn) {
    const row = btn.closest('.item-row');
    const isExisting = !!row.querySelector('input[name$="[id]"]');
    if (isExisting && !confirm('Hapus item ini dari PO? Item yang sudah punya data packing akan ditolak saat disimpan.')) return;
    row.remove();
}
document.getElementById('addItem').addEventListener('click', addItem);
</script>
@endpush
