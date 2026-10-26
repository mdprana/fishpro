@extends('layouts.app')
@section('title','Group Produk')
@section('breadcrumb')<li class="breadcrumb-item active">Group Produk</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-grid me-2"></i>Group Produk</h4>
        <p class="text-muted mb-0 small">Kelola group → tipe produk output</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
        <i class="bi bi-plus-lg me-1"></i>Tambah Group Produk
    </button>
</div>

@foreach($groups as $group)
<div class="form-card mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-2 d-flex align-items-center justify-content-center fw-bold text-white"
                 style="width:42px;height:42px;background:{{ $group->color }};font-size:.85rem">
                {{ strtoupper(substr($group->name,0,2)) }}
            </div>
            <div>
                <div class="fw-bold fs-6">{{ $group->name }}</div>
                <div class="text-muted small">
                    {{ $group->productTypes->count() }} tipe produk
                    @if(!$group->is_active)<span class="badge bg-secondary ms-1">Nonaktif</span>@endif
                </div>
            </div>
        </div>
        <div class="d-flex gap-1">
            <button class="btn btn-sm btn-outline-secondary"
                    onclick="openEditGroup({{ $group->id }},'{{ addslashes($group->name) }}','{{ $group->color }}',{{ $group->is_active?1:0 }})">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-success"
                    onclick="openAddType({{ $group->id }},'{{ addslashes($group->name) }}','{{ $group->color }}')">
                <i class="bi bi-plus-lg"></i> Tipe Produk
            </button>
        </div>
    </div>

    @if($group->productTypes->count() > 0)
    <div class="row g-2">
        @foreach($group->productTypes as $pt)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="p-2 rounded-3 d-flex align-items-center justify-content-between"
                 style="background:{{ $pt->effective_color }}15;border:1px solid {{ $pt->effective_color }}33">
                <div>
                    <div class="fw-semibold" style="color:{{ $pt->effective_color }};font-size:.85rem">{{ $pt->name }}</div>
                    <div class="text-muted" style="font-size:.68rem">
                        PO: {{ $pt->po_items_count }} · Output: {{ $pt->stage_outputs_count }} · Box: {{ $pt->packing_boxes_count }}
                    </div>
                    @if(!$pt->is_active)<span class="badge bg-secondary" style="font-size:.62rem">Nonaktif</span>@endif
                </div>
                <button class="btn btn-xs p-0 border-0 ms-1" style="color:#94a3b8"
                        onclick="openEditType({{ $pt->id }},{{ $pt->product_group_id }},'{{ addslashes($pt->name) }}','{{ $pt->color ?? '' }}',{{ $pt->is_active?1:0 }})">
                    <i class="bi bi-pencil-fill" style="font-size:.75rem"></i>
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-2 text-muted small">
        Belum ada tipe produk. Klik <strong>+ Tipe Produk</strong>.
    </div>
    @endif
</div>
@endforeach

@if($groups->count() === 0)
<div class="text-center py-5 text-muted form-card">
    <i class="bi bi-grid fs-2 d-block mb-2"></i>
    Belum ada group produk. Tambahkan terlebih dahulu.
</div>
@endif

{{-- Modal Tambah Group --}}
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('master.product-groups.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Group Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nama Group <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   placeholder="Ikan, Ayam, Seafood, Frozen Food…" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Warna <span class="text-danger">*</span></label>
                            <input type="color" name="color" class="form-control form-control-color" value="#3b82f6" required>
                        </div>
                        <div class="col-12">
                            <div id="addGroupPreview" class="p-2 rounded-2 text-center fw-semibold" style="background:#3b82f622;color:#3b82f6;border:1px solid #3b82f644">
                                Preview Nama Group
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

{{-- Modal Edit Group --}}
<div class="modal fade" id="editGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editGroupForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit Group Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nama Group</label>
                            <input type="text" name="name" id="egName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Warna</label>
                            <input type="color" name="color" id="egColor" class="form-control form-control-color">
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

{{-- Modal Tambah Tipe Produk --}}
<div class="modal fade" id="addTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('master.product-types.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Tipe Produk ke <span id="addTypeGroupName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="product_group_id" id="addTypeGroupId">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   placeholder="BE STEAK, YF LOIN, Dada Ayam…" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Warna Custom <span class="text-muted small">(opsional, kosong = pakai warna grup)</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="color" id="addTypeColor" class="form-control form-control-color">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="document.getElementById('addTypeColor').value=''; document.getElementById('addTypeColorCheck').textContent='Pakai warna grup'">
                                    Reset ke warna grup
                                </button>
                            </div>
                            <span id="addTypeColorCheck" class="text-muted small">Warna custom aktif</span>
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

{{-- Modal Edit Tipe Produk --}}
<div class="modal fade" id="editTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editTypeForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit Tipe Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="etName" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Pindah ke Group</label>
                            <select name="product_group_id" id="etGroup" class="form-select">
                                @foreach($groups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Warna Custom <span class="text-muted small">(opsional)</span></label>
                            <input type="color" name="color" id="etColor" class="form-control form-control-color">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="etActive" value="1">
                                <label class="form-check-label" for="etActive">Aktif</label>
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
@endsection

@push('scripts')
<script>
function openEditGroup(id, name, color, active) {
    document.getElementById('editGroupForm').action = `/master/product-groups/${id}`;
    document.getElementById('egName').value   = name;
    document.getElementById('egColor').value  = color;
    document.getElementById('egActive').checked = active === 1;
    new bootstrap.Modal(document.getElementById('editGroupModal')).show();
}

function openAddType(groupId, groupName, groupColor) {
    document.getElementById('addTypeGroupId').value = groupId;
    document.getElementById('addTypeGroupName').textContent = groupName;
    document.getElementById('addTypeColor').value = groupColor;
    new bootstrap.Modal(document.getElementById('addTypeModal')).show();
}

function openEditType(id, groupId, name, color, active) {
    document.getElementById('editTypeForm').action = `/master/product-types/${id}`;
    document.getElementById('etName').value  = name;
    document.getElementById('etGroup').value = groupId;
    document.getElementById('etColor').value = color || '#6b7280';
    document.getElementById('etActive').checked = active === 1;
    new bootstrap.Modal(document.getElementById('editTypeModal')).show();
}

// Color preview
document.querySelector('[name="color"]')?.addEventListener('input', function() {
    const prev = document.getElementById('addGroupPreview');
    if (prev) {
        prev.style.background = this.value + '22';
        prev.style.color = this.value;
        prev.style.border = '1px solid ' + this.value + '44';
    }
});
</script>
@endpush
