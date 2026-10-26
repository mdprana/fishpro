@extends('layouts.app')
@section('title','Group Bahan Baku')
@section('breadcrumb')<li class="breadcrumb-item active">Group Bahan Baku</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-tags me-2"></i>Group Bahan Baku</h4>
        <p class="text-muted mb-0 small">Kelola group → subgroup → estimasi yield ke produk</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
        <i class="bi bi-plus-lg me-1"></i>Tambah Group
    </button>
</div>

@foreach($groups as $group)
<div class="form-card mb-4">
    {{-- Group Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-2 d-flex align-items-center justify-content-center fw-bold text-white"
                 style="width:42px;height:42px;background:{{ $group->color }};font-size:.85rem">
                {{ strtoupper(substr($group->name,0,2)) }}
            </div>
            <div>
                <div class="fw-bold fs-6">{{ $group->name }}</div>
                <div class="text-muted small">
                    Satuan: <strong>{{ $group->unit }}</strong>
                    · {{ $group->subgroups->count() }} subgroup
                    @if(!$group->is_active)<span class="badge bg-secondary ms-1">Nonaktif</span>@endif
                </div>
                @if($group->description)<div class="text-muted small">{{ $group->description }}</div>@endif
            </div>
        </div>
        <div class="d-flex gap-1">
            <button class="btn btn-sm btn-outline-secondary"
                    onclick="openEditGroup({{ $group->id }},'{{ addslashes($group->name) }}','{{ $group->unit }}','{{ $group->color }}','{{ addslashes($group->description??'') }}',{{ $group->is_active?1:0 }})">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-success"
                    onclick="openAddSubgroup({{ $group->id }},'{{ addslashes($group->name) }}')">
                <i class="bi bi-plus-lg"></i> Subgroup
            </button>
        </div>
    </div>

    {{-- Subgroups --}}
    @if($group->subgroups->count() > 0)
    <div class="row g-2">
        @foreach($group->subgroups as $sub)
        @php $stock = $sub->stock?->current_stock_kg ?? 0; @endphp
        <div class="col-12 col-md-6">
            <div class="p-3 rounded-3 border" style="border-color:{{ $group->color }}44">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div>
                        <div class="fw-semibold" style="color:{{ $group->color }}">{{ $sub->name }}</div>
                        <div class="small text-muted">
                            Stok: <strong class="text-{{ $stock<10?'danger':($stock<50?'warning':'success') }}">{{ number_format($stock,1) }} kg</strong>
                            @if(!$sub->is_active)<span class="badge bg-secondary ms-1" style="font-size:.65rem">Nonaktif</span>@endif
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size:.72rem"
                                data-bs-toggle="collapse" data-bs-target="#yield{{ $sub->id }}">
                            <i class="bi bi-percent"></i> Yield
                        </button>
                        <button class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size:.72rem"
                                onclick="openEditSub({{ $sub->id }},'{{ addslashes($sub->name) }}',{{ $sub->is_active?1:0 }})">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </div>
                </div>

                {{-- Estimasi Yield yang sudah dikonfigurasi --}}
                @if($sub->yieldEstimates->count() > 0)
                <div class="d-flex flex-wrap gap-1 mb-2">
                    @foreach($sub->yieldEstimates as $ye)
                    <span class="badge rounded-pill" style="background:{{ $ye->productType->effective_color }}22;color:{{ $ye->productType->effective_color }};border:1px solid {{ $ye->productType->effective_color }}44;font-size:.68rem">
                        {{ $ye->productType->name }}: {{ $ye->percentage }}%
                    </span>
                    @endforeach
                    @php $totalPct = $sub->yieldEstimates->sum('percentage'); @endphp
                    <span class="badge bg-success-subtle text-success" style="font-size:.68rem">
                        {{ $sub->yieldEstimates->count() }} produk dikonfigurasi
                    </span>
                </div>
                @else
                <div class="text-muted" style="font-size:.75rem;font-style:italic">Belum ada estimasi yield</div>
                @endif

                {{-- Form Estimasi Yield (collapse) --}}
                <div class="collapse mt-2" id="yield{{ $sub->id }}">
                    <div class="p-2 rounded-2 bg-light border">
                        <div class="fw-semibold small mb-2">
                            <i class="bi bi-percent me-1 text-primary"></i>
                            Estimasi Yield: {{ $sub->name }}
                        </div>
                        <form action="{{ route('master.subgroups.yield',$sub) }}" method="POST">
                            @csrf
                            <div id="yieldRows{{ $sub->id }}">
                                @foreach($sub->yieldEstimates as $i => $ye)
                                <div class="d-flex align-items-center gap-2 mb-2 yield-row">
                                    <select name="product_type_id[]" class="form-select form-select-sm flex-grow-1" required>
                                        <option value="">-- Pilih Produk --</option>
                                        @foreach($productTypes as $pt)
                                        <option value="{{ $pt->id }}"
                                            style="color:{{ $pt->effective_color }}"
                                            {{ $ye->product_type_id==$pt->id?'selected':'' }}>
                                            {{ $pt->group_name }} → {{ $pt->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="input-group input-group-sm" style="width:110px">
                                        <input type="number" name="percentage[]" class="form-control pct-input"
                                               value="{{ $ye->percentage }}" step="0.1" min="0" max="100" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                                            onclick="this.closest('.yield-row').remove(); updateTotal('{{ $sub->id }}')">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                                @endforeach
                            </div>

                            {{-- Total Indicator --}}
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <button type="button" class="btn btn-xs btn-outline-success py-0 px-2" style="font-size:.72rem"
                                        onclick="addYieldRow('{{ $sub->id }}')">
                                    <i class="bi bi-plus-lg me-1"></i>Tambah Produk
                                </button>
                                <span class="small text-muted">
                                    <i class="bi bi-info-circle me-1"></i>Setiap produk bebas 0-100%
                                </span>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-check-lg me-1"></i>Simpan Estimasi Yield
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-3 text-muted small">
        Belum ada subgroup. Klik <strong>+ Subgroup</strong> untuk menambahkan.
    </div>
    @endif
</div>
@endforeach

@if($groups->count() === 0)
<div class="text-center py-5 text-muted form-card">
    <i class="bi bi-tags fs-2 d-block mb-2"></i>
    Belum ada group bahan baku. Tambahkan terlebih dahulu.
</div>
@endif

{{-- Modal Tambah Group --}}
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('master.material-groups.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Group Bahan Baku</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nama Group <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   placeholder="Ayam Beku, YF LOIN, BE LOIN…" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Satuan <span class="text-danger">*</span></label>
                            <input type="text" name="unit" class="form-control" value="kg" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Warna</label>
                            <input type="color" name="color" class="form-control form-control-color" value="#3b82f6">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Opsional"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Edit Group --}}
<div class="modal fade" id="editGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editGroupForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit Group Bahan Baku</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nama Group <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="egName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Satuan</label>
                            <input type="text" name="unit" id="egUnit" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Warna</label>
                            <input type="color" name="color" id="egColor" class="form-control form-control-color">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" id="egDesc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="egActive" value="1">
                                <label class="form-check-label" for="egActive">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Tambah Subgroup --}}
<div class="modal fade" id="addSubModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('master.subgroups.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Subgroup ke <span id="addSubGroupName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="material_group_id" id="addSubGroupId">
                    <label class="form-label">Nama Subgroup <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           placeholder="Ayam Boiler, Ayam Kampung, YF LOIN EROPA WARNA…" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Edit Subgroup --}}
<div class="modal fade" id="editSubModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editSubForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit Subgroup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nama Subgroup <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="esName" class="form-control" required>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="esActive" value="1">
                        <label class="form-check-label" for="esActive">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Template baris yield
const productTypesJson = {!! json_encode(
    $productTypes->map(function ($pt) {
        return [
            'id'    => $pt->id,
            'name'  => $pt->name,
            'group' => $pt->group->name ?? '',
            'color' => $pt->effective_color,
        ];
    })->values()->toArray()
) !!};

function addYieldRow(subId) {
    const container = document.getElementById('yieldRows' + subId);
    const opts = productTypesJson.map(pt =>
        `<option value="${pt.id}" style="color:${pt.color}">${pt.group} → ${pt.name}</option>`
    ).join('');

    const div = document.createElement('div');
    div.className = 'yield-row d-flex align-items-center gap-2 mb-2';
    div.innerHTML = `
        <select name="product_type_id[]" class="form-select form-select-sm flex-grow-1" required>
            <option value="">-- Pilih Produk --</option>${opts}
        </select>
        <div class="input-group input-group-sm" style="width:110px">
            <input type="number" name="percentage[]" class="form-control pct-input"
                   placeholder="0" step="0.1" min="0" max="100" required>
            <span class="input-group-text">%</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                onclick="this.closest('.yield-row').remove(); updateTotal('${subId}')">
            <i class="bi bi-x"></i>
        </button>`;
    container.appendChild(div);
    div.querySelector('.pct-input').addEventListener('input', () => updateTotal(subId));
    updateTotal(subId);
}

function updateTotal(subId) {
    // Tidak ada validasi total - setiap produk bebas 0-100%
    // Fungsi ini dipertahankan agar tidak error pada onclick yang masih memanggil updateTotal
}

// Bind existing pct inputs
document.querySelectorAll('.pct-input').forEach(inp => {
    const subId = inp.closest('.collapse')?.id?.replace('yield','');
    if (subId) inp.addEventListener('input', () => updateTotal(subId));
});

function openEditGroup(id, name, unit, color, desc, active) {
    document.getElementById('editGroupForm').action = `/master/material-groups/${id}`;
    document.getElementById('egName').value  = name;
    document.getElementById('egUnit').value  = unit;
    document.getElementById('egColor').value = color;
    document.getElementById('egDesc').value  = desc;
    document.getElementById('egActive').checked = active === 1;
    new bootstrap.Modal(document.getElementById('editGroupModal')).show();
}

function openAddSubgroup(groupId, groupName) {
    document.getElementById('addSubGroupId').value   = groupId;
    document.getElementById('addSubGroupName').textContent = groupName;
    new bootstrap.Modal(document.getElementById('addSubModal')).show();
}

function openEditSub(id, name, active) {
    document.getElementById('editSubForm').action = `/master/subgroups/${id}`;
    document.getElementById('esName').value = name;
    document.getElementById('esActive').checked = active === 1;
    new bootstrap.Modal(document.getElementById('editSubModal')).show();
}
</script>
@endpush
