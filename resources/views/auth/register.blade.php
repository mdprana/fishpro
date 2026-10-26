<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - FishPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg,#1a3a5c 0%,#0d6efd 100%); min-height:100vh;
            display:flex; align-items:center; justify-content:center; font-family:'Segoe UI',sans-serif; }
        .card-box { background:#fff; border-radius:16px; padding:2.5rem;
            box-shadow:0 20px 60px rgba(0,0,0,.2); width:100%; max-width:440px; }
        .brand { font-size:1.5rem; font-weight:800; color:#1a3a5c; }
        .brand span { color:#0d6efd; }
        .form-label { font-size:.875rem; font-weight:600; color:#374151; }
        .form-control:focus { border-color:#0d6efd; box-shadow:0 0 0 3px rgba(13,110,253,.1); }
    </style>
</head>
<body>
<div class="card-box">
    <div class="text-center mb-4">
        <div class="brand mb-1">🐟 Fish<span>Pro</span></div>
        <p class="text-muted small">Buat akun baru</p>
    </div>

    @if($errors->any())
    <div class="alert alert-danger small mb-3">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-4">
            <label class="form-label">Konfirmasi Password</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Daftar</button>
    </form>
    <div class="text-center mt-3">
        <span class="text-muted small">Sudah punya akun? </span>
        <a href="{{ route('login') }}" class="small text-primary text-decoration-none fw-semibold">Masuk</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
