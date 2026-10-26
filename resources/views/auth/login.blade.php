<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FishPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg,#1a3a5c 0%,#0d6efd 100%); min-height: 100vh;
            display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI',sans-serif; }
        .login-card { background: #fff; border-radius: 16px; padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,.2); width: 100%; max-width: 420px; }
        .brand { font-size: 1.6rem; font-weight: 800; color: #1a3a5c; }
        .brand span { color: #0d6efd; }
        .form-label { font-size: .875rem; font-weight: 600; color: #374151; }
        .form-control:focus { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.1); }
        .btn-login { background: linear-gradient(135deg,#1a3a5c,#0d6efd);
            border: none; padding: .75rem; font-weight: 600; letter-spacing: .02em; }
        .btn-login:hover { opacity: .92; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <div class="brand mb-1">🐟 Fish<span>Pro</span></div>
        <p class="text-muted small">Fish Processing Management System</p>
    </div>

    @if(session('status'))
    <div class="alert alert-success small mb-3">{{ session('status') }}</div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger small mb-3">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control"
                   value="{{ old('email') }}" placeholder="admin@fishpro.com" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
                   placeholder="••••••••" required>
        </div>
        <div class="mb-4 d-flex align-items-center justify-content-between">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label small" for="remember">Ingat saya</label>
            </div>
            @if(Route::has('password.request'))
            <a href="{{ route('password.request') }}" class="small text-primary text-decoration-none">Lupa password?</a>
            @endif
        </div>
        <button type="submit" class="btn btn-login btn-primary w-100 text-white">
            <i class="bi bi-box-arrow-in-right me-2"></i> Masuk
        </button>
    </form>

    <div class="text-center mt-3">
        <span class="text-muted small">Belum punya akun? </span>
        <a href="{{ route('register') }}" class="small text-primary text-decoration-none fw-semibold">Daftar</a>
    </div>

    <hr class="my-3">
    <div class="text-center">
        <small class="text-muted">Demo: <strong>admin@fishpro.com</strong> / <strong>password</strong></small>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
