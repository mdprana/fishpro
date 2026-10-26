<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    @include('layouts._theme_init')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FishPro</title>
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
        <h1 class="text-heading-page">Masuk ke akun</h1>
        <p class="text-body-regular auth-sub">Kelola produksi, packing, dan telusur batch.</p>

        @if(session('status'))
        <div class="alert alert-success mb-3" role="alert">{{ session('status') }}</div>
        @endif
        @if($errors->any())
        <div class="alert alert-danger mb-3" role="alert">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="auth-field">
                <label for="email" class="text-body-medium">Email<span class="auth-req" aria-hidden="true">*</span></label>
                <div class="auth-input">
                    <x-icon name="mail" />
                    <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}"
                           placeholder="admin@fishpro.com" autocomplete="email" required aria-required="true" autofocus>
                </div>
            </div>
            <div class="auth-field">
                <label for="password" class="text-body-medium">Password<span class="auth-req" aria-hidden="true">*</span></label>
                <div class="auth-input">
                    <x-icon name="lock" />
                    <input type="password" id="password" name="password" class="form-control"
                           autocomplete="current-password" required aria-required="true">
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-4">
                <label class="auth-check" for="remember">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember"> Ingat saya
                </label>
                @if(Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="auth-link text-body-regular">Lupa password?</a>
                @endif
            </div>
            <button type="submit" class="btn btn-primary auth-submit">Masuk</button>
        </form>

        <div class="auth-helper text-body-regular">
            Belum punya akun? <a href="{{ route('register') }}" class="auth-link">Daftar</a>
        </div>
    </div>

    <p class="text-body-small auth-demo">Demo: <strong>admin@fishpro.com</strong> / <strong>password</strong></p>
</main>
</body>
</html>
