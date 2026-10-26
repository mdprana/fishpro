@extends('layouts.app')
@section('title','Supplier')
@section('breadcrumb')<li class="breadcrumb-item active">Supplier</li>@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-truck me-2"></i>Supplier</h4>
        <p class="text-muted mb-0 small">
            Kode supplier dipakai langsung di nomor batch bahan baku,
            contoh <code>RM-260918-<strong>NJY</strong>-YFW-001</code>
        </p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
        <i class="bi bi-plus-lg me-1"></i>Tambah Supplier
    </button>
</div>

<div class="form-card">
    <form method="GET" class="mb-3" style="max-width:340px">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control" placeholder="Cari kode atau nama…"
                   value="{{ request('q') }}">
            @if(request('q'))
                <a href="{{ route('master.suppliers') }}" class="btn btn-outline-secondary">Reset</a>
            @endif
            <button class="btn btn-outline-primary">Cari</button>
        </div>
    </form>

    @if($suppliers->count())
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:90px">Kode</th>
                    <th>Nama</th>
                    <th>Kontak</th>
                    <th>Kota</th>
                    <th class="text-center" style="width:110px">Penerimaan</th>
                    <th class="text-center" style="width:100px">Status</th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($suppliers as $s)
                @php
                    $payload = $s->only([
                        'id','code','name','contact_person','phone',
                        'email','address','city','notes','is_active',
                    ]);
                @endphp
                <tr class="{{ $s->is_active ? '' : 'opacity-50' }}">
                    <td><span class="badge bg-dark-subtle text-dark fw-bold">{{ $s->code }}</span></td>
                    <td class="fw-semibold">{{ $s->name }}</td>
                    <td class="small text-muted">
                        {{ $s->contact_person ?: '-' }}
                        @if($s->phone)<br><span style="font-size:.72rem">{{ $s->phone }}</span>@endif
                    </td>
                    <td class="small text-muted">{{ $s->city ?: '-' }}</td>
                    <td class="text-center">{{ $s->receivings_count }}</td>
                    <td class="text-center">
                        @if($s->is_active)
                            <span class="badge bg-success-subtle text-success">Aktif</span>
                        @else
                            <span class="badge bg-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary"
                                onclick='openEditSupplier(@json($payload))'>
                            <i class="bi bi-pencil"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="text-center py-5 text-muted">
        <i class="bi bi-truck fs-2 d-block mb-2"></i>
        {{ request('q') ? 'Tidak ada supplier yang cocok.' : 'Belum ada supplier. Tambahkan terlebih dahulu.' }}
    </div>
    @endif
</div>

{{-- Modal Tambah --}}
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('master.suppliers.store') }}">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Supplier</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @include('master._supplier_fields', ['prefix' => 'add'])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Edit --}}
<div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" id="editSupplierForm">
            @csrf @method('PUT')
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Edit Supplier</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @include('master._supplier_fields', ['prefix' => 'edit'])
                <div class="form-check mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editIsActive">
                    <label class="form-check-label small" for="editIsActive">Supplier aktif</label>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-danger" id="deactivateBtn">
                    <i class="bi bi-slash-circle me-1"></i>Nonaktifkan
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<form id="deactivateForm" method="POST" class="d-none">@csrf @method('DELETE')</form>
@endsection

@push('scripts')
<script>
const editModal = new bootstrap.Modal(document.getElementById('editSupplierModal'));

function openEditSupplier(s) {
    document.getElementById('editSupplierForm').action = `{{ url('master/suppliers') }}/${s.id}`;
    document.getElementById('deactivateForm').action   = `{{ url('master/suppliers') }}/${s.id}`;

    ['code','name','contact_person','phone','email','address','city','notes'].forEach(f => {
        const el = document.getElementById('edit_' + f);
        if (el) el.value = s[f] ?? '';
    });
    document.getElementById('editIsActive').checked = !!s.is_active;

    editModal.show();
}

document.getElementById('deactivateBtn').addEventListener('click', () => {
    if (confirm('Nonaktifkan supplier ini? Data penerimaan lama tetap tersimpan.')) {
        document.getElementById('deactivateForm').submit();
    }
});

// Kode supplier masuk ke nomor batch, jadi paksa huruf besar tanpa spasi sejak diketik.
document.querySelectorAll('input[name="code"]').forEach(input => {
    input.addEventListener('input', () => {
        input.value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
});

// Buka kembali modal yang gagal validasi supaya isian user tidak hilang.
@if($errors->any() && old('_method') !== 'PUT')
new bootstrap.Modal(document.getElementById('addSupplierModal')).show();
@endif
</script>
@endpush
