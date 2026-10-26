<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    @include('layouts._theme_init')
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','FishPro') - Fish Processing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @include('layouts._theme')
    <style>
        .app-shell{display:flex;min-height:100vh}
        #sidebar{width:280px;min-width:280px;max-width:280px;position:sticky;top:0;height:100vh;background:var(--color-neutral-50);
            border-right:1px solid var(--color-neutral-200);display:flex;flex-direction:column;flex-shrink:0;z-index:50}
        .sb-top{padding:19px 18px 0;flex-shrink:0}
        .sb-brand{display:flex;align-items:center;gap:13px;height:48px;color:inherit;text-decoration:none}
        .sb-logo{width:48px;height:48px;border-radius:14px;background:var(--color-ink-900);color:var(--on-solid);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .sb-brand-name{color:var(--color-neutral-900)}
        .sb-brand-sub{color:var(--text-muted)}
        .sb-search{position:relative;margin-top:37px;height:38px}
        .sb-search input{width:100%;height:38px;border:1px solid transparent;border-radius:10px;background:var(--color-neutral-100);
            padding:0 42px 0 36px;font-size:14px;color:var(--color-neutral-900);outline:none}
        .sb-search input:focus{background:var(--color-neutral-0);border-color:var(--color-neutral-350)}
        .sb-search input::placeholder{color:var(--text-muted)}
        .sb-search .sb-search-icon{position:absolute;left:12px;top:11px;color:var(--text-muted);pointer-events:none}
        .sb-kbd{position:absolute;right:8px;top:9px;width:26px;height:20px;border-radius:6px;background:var(--color-neutral-0);color:var(--color-neutral-600);
            font-size:12px;line-height:20px;text-align:center;pointer-events:none;box-shadow:var(--shadow-raised)}
        .sb-nav{flex:1;min-height:0;overflow-y:auto;padding:21px 18px 12px}
        .sb-nav ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:4px}
        #sidebar [hidden]{display:none!important}
        .sb-item{display:flex;align-items:center;gap:8px;width:100%;height:38px;padding:0 12px;border:0;border-radius:10px;background:transparent;
            color:var(--color-neutral-600);text-decoration:none;font-size:14px;line-height:20px;letter-spacing:-0.1px;text-align:left;
            transition:background-color 120ms ease-out,color 120ms ease-out}
        .sb-item:hover{background:var(--color-neutral-100);color:var(--color-neutral-600)}
        .sb-item svg{flex-shrink:0;color:var(--color-neutral-600)}
        .sb-item.active{background:var(--color-neutral-0);box-shadow:var(--shadow-raised);color:var(--color-neutral-900)}
        .sb-item.active svg{color:var(--color-neutral-900)}
        .sb-item.open-parent{color:var(--color-neutral-900);font-weight:500}
        .sb-item.open-parent svg:first-child{color:var(--color-neutral-900)}
        .sb-label{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .sb-chevron{color:var(--text-muted)!important;transition:transform 150ms ease-out}
        .sb-group-btn[aria-expanded="true"] .sb-chevron{transform:rotate(180deg)}
        .sb-sub{margin-top:4px!important}
        .sb-sub .sb-item{height:34px;padding-left:36px}
        .sb-rail-link{display:none!important}
        .sb-count{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 5px;border-radius:999px;
            background:var(--color-neutral-0);border:1px solid var(--color-neutral-200);color:var(--color-neutral-900);line-height:1;flex-shrink:0}
        .sb-bottom{flex-shrink:0;padding:12px 18px 18px}
        .sb-usage-label{color:var(--text-muted)}
        .sb-usage-value{color:var(--color-neutral-900)}
        .sb-progress{height:6px;border-radius:999px;background:var(--color-neutral-200);overflow:hidden;margin:8px 0 12px}
        .sb-progress>span{display:block;height:100%;background:var(--color-ink-900);border-radius:999px}
        .sb-new{display:flex;align-items:center;justify-content:center;gap:8px;height:36px;width:100%;border:1px solid var(--color-neutral-200);
            border-radius:10px;background:var(--color-neutral-0);color:var(--color-neutral-900);font-size:14px;font-weight:500;text-decoration:none;
            transition:background-color 120ms ease-out,color 120ms ease-out}
        .sb-new:hover{background:var(--color-neutral-100);color:var(--color-neutral-900)}
        .sb-user{display:flex;align-items:center;gap:12px;margin-top:16px;padding-top:16px;border-top:1px solid var(--color-neutral-200)}
        .sb-avatar{position:relative;width:40px;height:40px;border-radius:50%;background:var(--color-neutral-200);color:var(--color-neutral-900);
            display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;flex-shrink:0}
        .sb-avatar::after{content:"";position:absolute;right:-2px;bottom:-2px;width:14px;height:14px;border-radius:50%;background:#07B93B;border:2px solid var(--color-neutral-0)}
        .sb-user-text{flex:1;min-width:0}
        .sb-user-name{color:var(--color-neutral-900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .sb-user-email{color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:0;border-radius:10px;background:transparent;
            color:var(--color-neutral-600);flex-shrink:0;transition:background-color 120ms ease-out,color 120ms ease-out;text-decoration:none}
        .icon-btn:hover{background:var(--color-neutral-100);color:var(--color-neutral-900)}

        main.app-main{flex:1;min-width:0;display:flex;flex-direction:column}
        .app-header{position:sticky;top:0;z-index:40;background:var(--color-neutral-0);height:67px;border-bottom:1px solid var(--color-neutral-200);
            display:flex;align-items:center;gap:12px;padding:0 18px}
        .app-header-title{min-width:0}
        .app-header-title h1{margin:0;color:var(--color-neutral-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .app-header .breadcrumb{margin:0;flex-wrap:nowrap;overflow:hidden;white-space:nowrap;font-size:12.5px;line-height:18px}
        .app-header .breadcrumb a{color:var(--text-muted);text-decoration:none}
        .app-header .breadcrumb a:hover{color:var(--color-neutral-900)}
        .app-header .breadcrumb-item,.app-header .breadcrumb-item.active{color:var(--text-muted)}
        .app-date{color:var(--text-muted);white-space:nowrap}
        #sbToggle{display:none}
        .page-content{padding:24px 18px;min-width:0}
        .page-content h4.fw-bold{font-weight:600;font-size:23.5px;line-height:30px;letter-spacing:-0.3px;color:var(--color-neutral-900)}
        .page-content h4.fw-bold + p,.page-content h4.fw-bold + .text-muted{color:var(--color-neutral-600)!important;font-size:14px}

        .item-row{background:var(--color-neutral-50);border:1px solid var(--color-neutral-200);border-radius:10px;padding:1rem;margin-bottom:.75rem;position:relative}
        .output-row{background:var(--color-neutral-0);border:1px solid var(--color-neutral-200);border-radius:10px;padding:.85rem;margin-bottom:.5rem;position:relative}
        .color-dot{width:9px;height:9px;border-radius:50%;display:inline-block;flex-shrink:0}
        .btn-check:checked+.product-btn{box-shadow:0 0 0 2px currentColor inset!important;font-weight:600}
        .product-btn{opacity:.85;cursor:pointer}
        .product-btn:hover{opacity:1}
        .sb-overlay{display:none}

        @media (min-width:768px) and (max-width:1023px){
            #sidebar.sidebar-collapsed{width:72px!important;min-width:72px!important;max-width:72px!important}
            #sidebar .sb-top{padding:19px 12px 0}
            #sidebar .sb-brand{justify-content:center}
            #sidebar .sb-brand-text,#sidebar .sb-search,#sidebar .sb-label,#sidebar .sb-chevron,#sidebar .sb-sub,
            #sidebar .sb-group-btn,#sidebar .sb-usage-text,#sidebar .sb-new-label,#sidebar .sb-user-text{display:none!important}
            #sidebar .sb-rail-link{display:flex!important}
            #sidebar .sb-nav{padding:21px 16px 12px}
            #sidebar .sb-item{width:40px;height:38px;padding:0;justify-content:center;position:relative}
            #sidebar .sb-count{position:absolute;top:6px;right:6px;min-width:8px;width:8px;height:8px;padding:0;font-size:0;
                background:var(--red-700);border:0}
            #sidebar .sb-bottom{padding:12px 16px 18px;display:flex;flex-direction:column;align-items:center}
            #sidebar .sb-new{width:40px;height:38px}
            #sidebar .sb-user{flex-direction:column;gap:8px;width:100%}
        }
        @media (max-width:767.98px){
            #sidebar{position:fixed;left:-280px;top:0;bottom:0;height:auto;visibility:hidden;
                transition:left 300ms ease-out,visibility 0s linear 300ms}
            #sidebar.active{left:0;visibility:visible;transition:left 300ms ease-out}
            .sb-overlay.show{display:block;position:fixed;inset:0;background:rgba(13,15,20,.4);z-index:45}
            #sbToggle{display:inline-flex}
            .app-date{display:none}
            .app-header{height:auto;min-height:67px;padding-top:12px;padding-bottom:12px}
            .app-header-title h1{white-space:normal;overflow:visible;overflow-wrap:anywhere}
            .app-header .breadcrumb{flex-wrap:wrap;overflow:visible;white-space:normal;overflow-wrap:anywhere}
            .app-header .breadcrumb-item:not(:first-child):not(:last-child){display:none}
            .app-header .breadcrumb-item:last-child{min-width:0;max-width:100%}
        }
    </style>
    @stack('styles')
</head>
<body>
@php
    $r = request();
    $navStages = \App\Models\ProcessStage::active()->ordered()->get();
    $inAnalisa = $r->routeIs('production.analisa*');
    $produksiOpen = $r->routeIs('po.*','receiving.*','batch.*','stage.*') || ($r->routeIs('production.*') && !$inAnalisa);
    $laporanOpen = $inAnalisa || $r->routeIs('report.yield','report.raw-material*','packing.*','trace.*','riwayat.*');
    $masterOpen = $r->routeIs('master.*');
    $nav = $navSidebar ?? ['overdue_po' => 0, 'packed_box' => 0, 'target_box' => 0];
    $usagePct = $nav['target_box'] > 0 ? min(100, round($nav['packed_box'] / $nav['target_box'] * 100)) : 0;
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($user->name ?? 'A')))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $groups = [
        ['id' => 'produksi', 'label' => 'Produksi', 'icon' => 'production', 'open' => $produksiOpen, 'items' => array_merge([
            ['Purchase Order', route('po.index'), $r->routeIs('po.*')],
            ['Bahan Baku', route('receiving.index'), $r->routeIs('receiving.*')],
            ['Batch & Lot', route('batch.index'), $r->routeIs('batch.*')],
        ], $navStages->map(fn ($s) => [$s->name, route('stage.index', $s->code), $r->routeIs('stage.*') && $r->route('stage')?->code === $s->code])->all(), [
            ['Proses Produksi', route('production.index'), $r->routeIs('production.*') && !$inAnalisa],
        ])],
        ['id' => 'laporan', 'label' => 'Laporan', 'icon' => 'report', 'open' => $laporanOpen, 'items' => [
            ['Analisa PO', route('production.analisa'), $inAnalisa],
            ['Yield & Recovery', route('report.yield'), $r->routeIs('report.yield')],
            ['Rekap Bahan Baku', route('report.raw-material'), $r->routeIs('report.raw-material*')],
            ['Packing', route('packing.index'), $r->routeIs('packing.*')],
            ['Telusur', route('trace.index'), $r->routeIs('trace.*')],
            ['Riwayat', route('riwayat.index'), $r->routeIs('riwayat.*')],
        ]],
        ['id' => 'master', 'label' => 'Master Data', 'icon' => 'database', 'open' => $masterOpen, 'items' => [
            ['Group Bahan Baku', route('master.material-groups'), $r->routeIs('master.material-groups')],
            ['Group Produk', route('master.product-groups'), $r->routeIs('master.product-groups')],
            ['Supplier', route('master.suppliers'), $r->routeIs('master.suppliers*')],
            ['Standar Yield', route('master.yield-standards'), $r->routeIs('master.yield-standards*')],
        ]],
    ];
@endphp
<div class="app-shell">
<aside id="sidebar" class="sidebar-collapsed" aria-label="Navigasi utama">
    <div class="sb-top">
        <a href="{{ route('dashboard') }}" class="sb-brand" aria-label="FishPro, ke Dashboard">
            <span class="sb-logo"><x-fish-mark size="24" /></span>
            <span class="sb-brand-text">
                <span class="d-block text-heading-brand sb-brand-name">FishPro</span>
                <span class="d-block text-body-regular sb-brand-sub">Fish Processing</span>
            </span>
        </a>
        <div class="sb-search">
            <x-icon name="search" class="sb-search-icon" />
            <input type="search" id="sbSearch" placeholder="Cari menu" aria-label="Cari menu" autocomplete="off">
            <span class="sb-kbd" aria-hidden="true">/</span>
        </div>
    </div>

    <nav class="sb-nav" id="sbNav">
        <ul>
            <li data-nav-item data-label="Dashboard">
                <a class="sb-item {{ $r->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Dashboard"
                   @if($r->routeIs('dashboard')) aria-current="page" @endif>
                    <x-icon name="dashboard" /><span class="sb-label">Dashboard</span>
                    @if($nav['overdue_po'] > 0)
                    <span class="sb-count text-label-score" aria-label="{{ $nav['overdue_po'] }} PO melewati deadline">{{ $nav['overdue_po'] }}</span>
                    @endif
                </a>
            </li>
            @foreach($groups as $g)
            <li data-nav-group>
                <button type="button" class="sb-item sb-group-btn {{ $g['open'] ? 'open-parent' : '' }}"
                        aria-expanded="{{ $g['open'] ? 'true' : 'false' }}" aria-controls="sb-{{ $g['id'] }}">
                    <x-icon :name="$g['icon']" /><span class="sb-label">{{ $g['label'] }}</span>
                    <x-icon name="chevron-down" class="sb-chevron" />
                </button>
                <a class="sb-item sb-rail-link {{ $g['open'] ? 'active' : '' }}" href="{{ $g['items'][0][1] }}"
                   title="{{ $g['label'] }}" aria-label="{{ $g['label'] }}">
                    <x-icon :name="$g['icon']" />
                </a>
                <ul class="sb-sub" id="sb-{{ $g['id'] }}" @unless($g['open']) hidden @endunless>
                    @foreach($g['items'] as [$label, $href, $active])
                    <li data-nav-item data-label="{{ $label }}">
                        <a class="sb-item {{ $active ? 'active' : '' }}" href="{{ $href }}" title="{{ $label }}"
                           @if($active) aria-current="page" @endif><span class="sb-label">{{ $label }}</span></a>
                    </li>
                    @endforeach
                </ul>
            </li>
            @endforeach
        </ul>
    </nav>

    <div class="sb-bottom">
        <div class="sb-usage-text">
            <div class="text-body-small sb-usage-label">Packing PO aktif</div>
            <div class="text-body-medium sb-usage-value">{{ number_format($nav['packed_box'], 0, ',', '.') }} dari {{ number_format($nav['target_box'], 0, ',', '.') }} box</div>
            <div class="sb-progress" role="progressbar" aria-label="Progress packing PO aktif"
                 aria-valuenow="{{ $usagePct }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $usagePct }}%"></span></div>
        </div>
        <a href="{{ route('po.create') }}" class="sb-new" title="Buat PO" aria-label="Buat PO">
            <x-icon name="plus" /><span class="sb-new-label">Buat PO</span>
        </a>
        <div class="sb-user">
            <div class="sb-avatar" aria-hidden="true">{{ $initials }}</div>
            <div class="sb-user-text">
                <div class="text-heading-brand sb-user-name">{{ $user->name ?? '' }}</div>
                <div class="text-body-regular sb-user-email">{{ $user->email ?? '' }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                <button type="submit" class="icon-btn" aria-label="Keluar" title="Keluar"><x-icon name="logout" /></button>
            </form>
        </div>
    </div>
</aside>
<div class="sb-overlay" id="sbOverlay"></div>

<main class="app-main">
    <header class="app-header">
        <button type="button" class="icon-btn" id="sbToggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
            <x-icon name="menu" size="20" />
        </button>
        <div class="app-header-title">
            <h1 class="text-heading-page">@yield('title','FishPro')</h1>
            @hasSection('breadcrumb')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                    @yield('breadcrumb')
                </ol>
            </nav>
            @endif
        </div>
        <div class="flex-grow-1"></div>
        <span class="text-body-regular app-date">{{ now()->translatedFormat('d M Y') }}</span>
        @include('layouts._theme_toggle')
        <a href="{{ route('dashboard') }}" class="icon-btn" aria-label="Notifikasi" title="Notifikasi"><x-icon name="bell" size="18" /></a>
    </header>

    <div class="page-content">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
        @endif
        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong class="fw-semibold">Perbaiki kesalahan:</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
        @endif

        @yield('content')
    </div>
</main>
</div>

<script>
(function () {
    var sb = document.getElementById('sidebar'), ov = document.getElementById('sbOverlay'),
        tg = document.getElementById('sbToggle'), q = document.getElementById('sbSearch');
    var mobile = window.matchMedia('(max-width: 767.98px)');

    function setDrawer(open) {
        sb.classList.toggle('active', open);
        ov.classList.toggle('show', open);
        tg.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) setTimeout(function () { q.focus(); }, 50); else tg.focus();
    }
    tg.addEventListener('click', function () { setDrawer(!sb.classList.contains('active')); });
    ov.addEventListener('click', function () { setDrawer(false); });
    mobile.addEventListener('change', function (e) { if (!e.matches && sb.classList.contains('active')) { sb.classList.remove('active'); ov.classList.remove('show'); tg.setAttribute('aria-expanded', 'false'); } });

    sb.querySelectorAll('.sb-group-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var open = btn.getAttribute('aria-expanded') !== 'true';
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.getElementById(btn.getAttribute('aria-controls')).hidden = !open;
        });
    });

    var groupState = null;
    function filter() {
        var term = q.value.trim().toLowerCase();
        var groups = sb.querySelectorAll('[data-nav-group]');
        if (term && !groupState) groupState = Array.prototype.map.call(groups, function (g) { return g.querySelector('.sb-sub').hidden; });
        sb.querySelectorAll('[data-nav-item]').forEach(function (li) {
            li.hidden = term !== '' && li.dataset.label.toLowerCase().indexOf(term) === -1;
        });
        groups.forEach(function (g, i) {
            var sub = g.querySelector('.sb-sub'), btn = g.querySelector('.sb-group-btn');
            if (term) {
                var any = sub.querySelector('[data-nav-item]:not([hidden])');
                g.hidden = !any;
                sub.hidden = !any;
                btn.setAttribute('aria-expanded', any ? 'true' : 'false');
            } else {
                g.hidden = false;
                if (groupState) { sub.hidden = groupState[i]; btn.setAttribute('aria-expanded', groupState[i] ? 'false' : 'true'); }
            }
        });
        if (!term) groupState = null;
    }
    q.addEventListener('input', filter);
    q.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && q.value) { e.stopPropagation(); q.value = ''; filter(); }
    });

    document.addEventListener('keydown', function (e) {
        var t = e.target, tag = t.tagName;
        var typing = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || t.isContentEditable;
        if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey && q.offsetParent !== null) {
            e.preventDefault(); q.focus();
        } else if (e.key === 'Escape' && sb.classList.contains('active')) {
            setDrawer(false);
        }
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
if (window.Chart) {
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#9BA0A6';
    Chart.defaults.borderColor = '#EFF1F3';
}
</script>
@stack('scripts')
</body>
</html>
