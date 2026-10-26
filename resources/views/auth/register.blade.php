<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    @include('layouts._theme_init')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - FishPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    @include('auth._styles')
</head>
<body>
@include('layouts._theme_toggle')
<main class="auth-stack">
    <div class="auth-brand">
        <span class="auth-logo"><x-fish-mark size="24" /></span>
        <span class="text-heading-brand">FishPro</span>
    </div>

    <div class="auth-card">
        <h1 class="text-heading-page">Buat akun</h1>
        <p class="text-body-regular auth-sub">Kelola produksi, packing, dan telusur batch.</p>

        @if($errors->any())
        <div class="alert alert-danger mb-3" role="alert">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf
            @foreach([
                ['name', 'text', 'Nama Lengkap', 'user', 'name', old('name')],
                ['email', 'email', 'Email', 'mail', 'email', old('email')],
                ['password', 'password', 'Password', 'lock', 'new-password', null],
                ['password_confirmation', 'password', 'Konfirmasi Password', 'lock', 'new-password', null],
            ] as [$field, $type, $label, $icon, $ac, $value])
            <div class="auth-field">
                <label for="{{ $field }}" class="text-body-medium">{{ $label }}<span class="auth-req" aria-hidden="true">*</span></label>
                <div class="auth-input">
                    <x-icon :name="$icon" />
                    <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" class="form-control"
                           @if($value !== null) value="{{ $value }}" @endif
                           autocomplete="{{ $ac }}" required aria-required="true" @if($loop->first) autofocus @endif>
                </div>
            </div>
            @endforeach
            <button type="submit" class="btn btn-primary auth-submit mt-2">Daftar</button>
        </form>

        <div class="auth-helper text-body-regular">
            Sudah punya akun? <a href="{{ route('login') }}" class="auth-link">Masuk</a>
        </div>
    </div>
</main>
</body>
</html>
