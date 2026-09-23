<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $routeName = request()->route()?->getName() ?? 'dashboard';
        $pageTitle = match (true) {
            $routeName === 'dashboard' => 'Dashboard',
            str_starts_with($routeName, 'sales.') => 'Penjualan',
            str_starts_with($routeName, 'purchase-orders.') => 'Purchase Order',
            str_starts_with($routeName, 'ingredients.') => 'Bahan Baku',
            str_starts_with($routeName, 'products.') => 'Produk dan Resep',
            str_starts_with($routeName, 'finance.') || $routeName === 'financials' => 'Keuangan Cabang',
            default => 'Franchise Management',
        };
    @endphp
    <title>@yield('title', $pageTitle) · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc4/dist/css/adminlte.min.css">
    <style>
        :root { --app-sidebar: #17202b; --app-accent: #0d6efd; }
        body { min-width: 320px; }
        .app-sidebar { background: var(--app-sidebar); }
        .app-sidebar .brand-link { border-bottom: 1px solid rgba(255,255,255,.1); }
        .app-sidebar .nav-link { color: rgba(255,255,255,.78); }
        .app-sidebar .nav-link:hover, .app-sidebar .nav-link.active { color: #fff; background: rgba(13,110,253,.85); }
        .app-sidebar .nav-icon { width: 1.4rem; text-align: center; margin-right: .55rem; }
        .content-wrapper { min-height: calc(100vh - 57px); }
        .content-header { padding: 1.25rem 1.5rem .5rem; }
        .content { padding: 0 1.5rem 1.5rem; }
        .card { border: 1px solid rgba(0,0,0,.08); box-shadow: 0 2px 8px rgba(20,30,40,.04); }
        @media (max-width: 991.98px) {
            .app-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 1050; width: 280px; transform: translateX(-100%); transition: transform .2s ease; }
            body.sidebar-open .app-sidebar { transform: translateX(0); }
            body.sidebar-open::after { content: ''; position: fixed; inset: 0; z-index: 1040; background: rgba(0,0,0,.45); }
            .content-header { padding: 1rem 1rem .5rem; }
            .content { padding: 0 1rem 1rem; }
        }
    </style>
</head>
<body class="layout-fixed">
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body border-bottom">
        <div class="container-fluid">
            <button class="btn btn-link nav-link d-lg-none" type="button" id="sidebarToggle" aria-label="Buka menu"><i class="fa-solid fa-bars"></i></button>
            <a href="{{ route('dashboard') }}" class="navbar-brand d-lg-none fw-semibold">{{ config('app.name') }}</a>
            <ul class="navbar-nav ms-auto align-items-center">
                @auth
                <li class="nav-item d-flex align-items-center me-2"><span class="text-secondary small d-none d-sm-inline">{{ auth()->user()->name }}</span></li>
                <li class="nav-item"><form method="post" action="{{ route('logout') }}" class="m-0">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit"><i class="fa-solid fa-right-from-bracket me-1"></i>Keluar</button></form></li>
                @endauth
            </ul>
        </div>
    </nav>
    <aside class="app-sidebar shadow" data-bs-theme="dark">
        <div class="sidebar-brand"><a href="{{ route('dashboard') }}" class="brand-link text-decoration-none"><span class="brand-text fw-light">{{ config('app.name') }}</span></a></div>
        <div class="sidebar-wrapper p-2">
            <nav>
                <ul class="nav nav-pills nav-sidebar flex-column gap-1" data-lte-toggle="treeview" role="menu">
                    @php($current = request()->route()?->getName())
                    <li class="nav-item"><a href="{{ route('dashboard') }}" class="nav-link {{ $current === 'dashboard' ? 'active' : '' }}"><i class="nav-icon fa-solid fa-gauge-high"></i><p>Dashboard</p></a></li>
                    <li class="nav-item"><a href="{{ route('sales.index') }}" class="nav-link {{ str_starts_with($current ?? '', 'sales.') ? 'active' : '' }}"><i class="nav-icon fa-solid fa-receipt"></i><p>Penjualan</p></a></li>
                    <li class="nav-item"><a href="{{ route('sales.create') }}" class="nav-link"><i class="nav-icon fa-solid fa-cash-register"></i><p>Kasir</p></a></li>
                    <li class="nav-item"><a href="{{ route('purchase-orders.index') }}" class="nav-link {{ str_starts_with($current ?? '', 'purchase-orders.') ? 'active' : '' }}"><i class="nav-icon fa-solid fa-truck-ramp-box"></i><p>Purchase Order</p></a></li>
                    <li class="nav-item"><a href="{{ route('ingredients.index') }}" class="nav-link {{ str_starts_with($current ?? '', 'ingredients.') ? 'active' : '' }}"><i class="nav-icon fa-solid fa-boxes-stacked"></i><p>Bahan Baku</p></a></li>
                    <li class="nav-item"><a href="{{ route('products.index') }}" class="nav-link {{ str_starts_with($current ?? '', 'products.') ? 'active' : '' }}"><i class="nav-icon fa-solid fa-tags"></i><p>Produk &amp; Resep</p></a></li>
                    @unless(auth()->user()->isCentralOwner())
                    <li class="nav-item"><a href="{{ route('finance.index') }}" class="nav-link {{ str_starts_with($current ?? '', 'finance.') ? 'active' : '' }}"><i class="nav-icon fa-solid fa-chart-line"></i><p>Keuangan</p></a></li>
                    @endunless
                </ul>
            </nav>
        </div>
    </aside>
    <main class="app-main">
        <div class="app-content-header"><div class="container-fluid content-header"><h1 class="h4 m-0">@yield('heading', 'Dashboard')</h1></div></div>
        <div class="app-content"><div class="container-fluid content">@yield('content')</div></div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc4/dist/js/adminlte.min.js"></script>
<script>
(() => { const toggle = document.getElementById('sidebarToggle'); if (!toggle) return; toggle.addEventListener('click', () => document.body.classList.toggle('sidebar-open')); document.addEventListener('click', event => { if (event.target.closest('.app-sidebar a') && window.innerWidth < 992) document.body.classList.remove('sidebar-open'); }); })();
</script>
</body>
</html>
