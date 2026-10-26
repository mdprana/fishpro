{{--
    Field supplier yang dipakai bersama oleh modal Tambah dan Edit.
    $prefix ('add'|'edit') hanya membedakan id elemen agar JS edit bisa mengisinya.
--}}
@php $isAdd = ($prefix ?? 'add') === 'add'; @endphp

<div class="row g-3">
    <div class="col-4">
        <label class="form-label">Kode <span class="text-danger">*</span></label>
        <input type="text" name="code" id="{{ $prefix }}_code" maxlength="10" required
               class="form-control text-uppercase fw-bold @if($isAdd) @error('code') is-invalid @enderror @endif"
               value="{{ $isAdd ? old('code') : '' }}" placeholder="NJY">
        @if($isAdd)@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
        <div class="form-text" style="font-size:.7rem">Masuk ke nomor batch</div>
    </div>
    <div class="col-8">
        <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
        <input type="text" name="name" id="{{ $prefix }}_name" maxlength="200" required
               class="form-control @if($isAdd) @error('name') is-invalid @enderror @endif"
               value="{{ $isAdd ? old('name') : '' }}" placeholder="UD Nelayan Jaya">
        @if($isAdd)@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
    </div>

    <div class="col-md-6">
        <label class="form-label">Kontak Person</label>
        <input type="text" name="contact_person" id="{{ $prefix }}_contact_person" maxlength="150"
               class="form-control" value="{{ $isAdd ? old('contact_person') : '' }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Telepon</label>
        <input type="text" name="phone" id="{{ $prefix }}_phone" maxlength="50"
               class="form-control" value="{{ $isAdd ? old('phone') : '' }}">
    </div>

    <div class="col-md-7">
        <label class="form-label">Email</label>
        <input type="email" name="email" id="{{ $prefix }}_email" maxlength="150"
               class="form-control @if($isAdd) @error('email') is-invalid @enderror @endif"
               value="{{ $isAdd ? old('email') : '' }}">
        @if($isAdd)@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
    </div>
    <div class="col-md-5">
        <label class="form-label">Kota</label>
        <input type="text" name="city" id="{{ $prefix }}_city" maxlength="100"
               class="form-control" value="{{ $isAdd ? old('city') : '' }}">
    </div>

    <div class="col-12">
        <label class="form-label">Alamat</label>
        <textarea name="address" id="{{ $prefix }}_address" rows="2" maxlength="500"
                  class="form-control">{{ $isAdd ? old('address') : '' }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Catatan</label>
        <input type="text" name="notes" id="{{ $prefix }}_notes" maxlength="500"
               class="form-control" value="{{ $isAdd ? old('notes') : '' }}">
    </div>
</div>
