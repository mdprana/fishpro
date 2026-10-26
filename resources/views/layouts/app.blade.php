<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','FishPro') - Fish Processing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--sw:250px;--navy:#1a3a5c;--accent:#0dcaf0;}
        body{background:#f0f4f8;font-family:'Segoe UI',sans-serif;}
        #sidebar{position:fixed;top:0;left:0;height:100vh;width:var(--sw);
            background:var(--navy);z-index:1040;overflow-y:auto;transition:transform .25s;display:flex;flex-direction:column;}
        #sidebar .brand{padding:1.2rem 1rem;border-bottom:1px solid rgba(255,255,255,.1);
            color:#fff;font-weight:800;font-size:1.15rem;flex-shrink:0;}
        #sidebar .brand span{color:var(--accent);}
        .nav-section{font-size:.65rem;font-weight:700;text-transform:uppercase;
            letter-spacing:.1em;color:rgba(255,255,255,.3);padding:.7rem 1.25rem .15rem;margin-top:.2rem;}
        #sidebar .nav-link{color:rgba(255,255,255,.7);padding:.52rem 1.1rem;border-radius:7px;
            margin:1px 8px;display:flex;align-items:center;gap:.55rem;font-size:.86rem;transition:all .15s;}
        #sidebar .nav-link:hover,#sidebar .nav-link.active{color:#fff;background:rgba(255,255,255,.15);}
        #sidebar .nav-link .bi{width:18px;text-align:center;font-size:.95rem;flex-shrink:0;}
        #main{margin-left:var(--sw);min-height:100vh;}
        .topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:.65rem 1.5rem;
            display:flex;align-items:center;justify-content:space-between;
            position:sticky;top:0;z-index:100;box-shadow:0 1px 3px rgba(0,0,0,.04);}
        .page-content{padding:1.5rem;}
        .stat-card{background:#fff;border-radius:12px;padding:1.25rem 1.4rem;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.04);}
        .form-card{background:#fff;border-radius:12px;padding:1.75rem;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.04);}
        .form-label{font-size:.875rem;font-weight:600;color:#374151;margin-bottom:.3rem;}
        .form-control:focus,.form-select:focus{border-color:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.1);}
        .table-custom thead th{background:#f8fafc;font-size:.76rem;font-weight:700;text-transform:uppercase;
            letter-spacing:.04em;color:#64748b;border-bottom:2px solid #e2e8f0;}
        .progress{border-radius:8px;overflow:hidden;}
        .item-row{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1rem;margin-bottom:.75rem;position:relative;}
        .output-row{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.85rem;margin-bottom:.5rem;position:relative;}
        .color-dot{width:9px;height:9px;border-radius:50%;display:inline-block;flex-shrink:0;}
        .btn-check:checked+.product-btn{box-shadow:0 0 0 2px currentColor inset!important;font-weight:700;}
        .product-btn{opacity:.85;cursor:pointer;}
        .product-btn:hover{opacity:1;}
        @media(max-width:768px){#sidebar{transform:translateX(-100%);}#sidebar.open{transform:translateX(0);}#main{margin-left:0;}}
    </style>
    @stack('styles')
</head>
<body>

<nav id="sidebar">
    <div class="brand">🐟 Fish<span>Pro</span></div>
    <div class="flex-grow-1 overflow-auto">
    <ul class="nav flex-column pt-1 pb-4">
        <li><div class="nav-section">Overview</div></li>
        <li><a class="nav-link {{ request()->routeIs('dashboard') ?'active':'' }}" href="{{ route('dashboard') }}">
            <i class="bi bi-speedometer2"></i> Dashboard</a></li>

        <li><div class="nav-section">Produksi</div></li>
        <li><a class="nav-link {{ request()->routeIs('po.*') ?'active':'' }}" href="{{ route('po.index') }}">
            <i class="bi bi-file-earmark-text"></i> Purchase Order</a></li>
        <li><a class="nav-link {{ request()->routeIs('receiving.*') ?'active':'' }}" href="{{ route('receiving.index') }}">
            <i class="bi bi-box-arrow-in-down"></i> Bahan Baku</a></li>
        <li><a class="nav-link {{ request()->routeIs('batch.*') ?'active':'' }}" href="{{ route('batch.index') }}">
            <i class="bi bi-box2"></i> Batch &amp; Lot</a></li>

        {{-- Tahapan diambil dari master process_stages, bukan di-hardcode,
             supaya tahap baru langsung muncul di menu tanpa ubah layout. --}}
        @foreach(\App\Models\ProcessStage::active()->ordered()->get() as $navStage)
        <li><a class="nav-link {{ request()->routeIs('stage.*') && request()->route('stage')?->code === $navStage->code ?'active':'' }}"
               href="{{ route('stage.index', $navStage->code) }}">
            <i class="bi {{ $navStage->icon }}"></i> {{ $navStage->name }}</a></li>
        @endforeach
        {{-- 'Analisa' juga cocok dengan pola production.*, jadi dikecualikan
             agar tidak ada dua menu menyala bersamaan. --}}
        <li><a class="nav-link {{ request()->routeIs('production.*') && !request()->routeIs('production.analisa*') ?'active':'' }}" href="{{ route('production.index') }}">
            <i class="bi bi-gear-wide-connected"></i> Proses Produksi</a></li>
        <li><div class="nav-section">Laporan</div></li>
        <li><a class="nav-link {{ request()->routeIs('production.analisa*') ?'active':'' }}" href="{{ route('production.analisa') }}">
            <i class="bi bi-graph-up-arrow"></i> Analisa PO
        </a></li>
        <li><a class="nav-link {{ request()->routeIs('report.yield') ?'active':'' }}" href="{{ route('report.yield') }}">
            <i class="bi bi-speedometer2"></i> Yield &amp; Recovery</a></li>
        <li><a class="nav-link {{ request()->routeIs('report.raw-material*') ?'active':'' }}" href="{{ route('report.raw-material') }}">
            <i class="bi bi-clipboard-data"></i> Rekap Bahan Baku</a></li>
        <li><a class="nav-link {{ request()->routeIs('packing.*') ?'active':'' }}" href="{{ route('packing.index') }}">
            <i class="bi bi-box-seam"></i> Packing</a></li>
        <li><a class="nav-link {{ request()->routeIs('trace.*') ?'active':'' }}" href="{{ route('trace.index') }}">
            <i class="bi bi-upc-scan"></i> Telusur</a></li>
        <li><a class="nav-link {{ request()->routeIs('riwayat.*') ?'active':'' }}" href="{{ route('riwayat.index') }}">
            <i class="bi bi-clock-history"></i> Riwayat</a></li>

        <li><div class="nav-section">Master Data</div></li>
        <li><a class="nav-link {{ request()->routeIs('master.material-groups') ?'active':'' }}" href="{{ route('master.material-groups') }}">
            <i class="bi bi-tags"></i> Group Bahan Baku</a></li>
        <li><a class="nav-link {{ request()->routeIs('master.product-groups') ?'active':'' }}" href="{{ route('master.product-groups') }}">
            <i class="bi bi-grid"></i> Group Produk</a></li>
        <li><a class="nav-link {{ request()->routeIs('master.suppliers*') ?'active':'' }}" href="{{ route('master.suppliers') }}">
            <i class="bi bi-truck"></i> Supplier</a></li>
        <li><a class="nav-link {{ request()->routeIs('master.yield-standards*') ?'active':'' }}" href="{{ route('master.yield-standards') }}">
            <i class="bi bi-speedometer2"></i> Standar Yield</a></li>

        <li><div class="nav-section">Akun</div></li>
        <li>
            <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                <button type="submit" class="nav-link btn btn-link w-100 text-start border-0 p-0" style="background:none">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </form>
        </li>
    </ul>
    </div>
</nav>

<div id="main">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-md-none"
                onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="bi bi-list fs-5"></i>
            </button>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Home</a></li>
                    @yield('breadcrumb')
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small d-none d-md-block">{{ now()->format('d M Y') }}</span>
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                 style="width:34px;height:34px;font-size:.82rem">
                {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
            </div>
        </div>
    </div>

    <div class="page-content">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-check-circle-fill flex-shrink-0"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        @endif
        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3">
            <i class="bi bi-x-circle-fill me-2"></i>
            <strong>Perbaiki kesalahan:</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @yield('content')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
@stack('scripts')
</body>
</html>
