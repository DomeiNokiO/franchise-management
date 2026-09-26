<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $routeName = request()->route()?->getName() ?? 'dashboard';
        $user = auth()->user();
        $activeBranchId = $user ? $user->activeBranchId() : null;
        $branches = $user ? $user->branches()->where('is_active', true)->orderBy('name')->get() : collect();
        $pageTitle = match (true) {
            $routeName === 'dashboard' => 'Dashboard',
            str_starts_with($routeName, 'sales.create') => 'Transaksi Kasir',
            str_starts_with($routeName, 'sales.') => 'Penjualan',
            str_starts_with($routeName, 'purchase-orders.') => 'Purchase Order',
            str_starts_with($routeName, 'ingredients.') => 'Bahan Baku',
            str_starts_with($routeName, 'products.') => 'Produk & Resep',
            str_starts_with($routeName, 'finance.') => 'Keuangan Cabang',
            default => 'Franchise Management',
        };
    @endphp
    <title>@yield('title', $pageTitle) · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --ink: #0f172a; --ink-2: #475569; --ink-3: #94a3b8;
            --line: #e2e8f0; --bg: #f1f5f9; --surface: #ffffff;
            --brand: #2563eb; --brand-ink: #1d4ed8; --brand-soft: #eff6ff;
            --ok: #16a34a; --ok-soft: #f0fdf4;
            --warn: #d97706; --warn-soft: #fffbeb;
            --bad: #dc2626; --bad-soft: #fef2f2;
            --radius: 14px;
            --shadow: 0 1px 2px rgba(15,23,42,.05), 0 8px 24px -12px rgba(15,23,42,.12);
        }
        * { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--ink); font-size: .925rem; min-width: 320px; }
        a { color: var(--brand-ink); text-decoration: none; }
        a:hover { color: var(--brand); }
        h1,h2,h3,h4,h5 { font-weight: 700; letter-spacing: -.02em; }

        /* ===== Sidebar ===== */
        .sidebar { position: fixed; inset: 0 auto 0 0; width: 264px; background: #0f172a; color: #cbd5e1; z-index: 1045; display: flex; flex-direction: column; transition: transform .22s ease; }
        .sidebar .brand { display: flex; align-items: center; gap: .7rem; padding: 1.15rem 1.25rem; min-height: 68px; border-bottom: 1px solid rgba(148,163,184,.15); }
        .sidebar .brand .logo { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6, #6366f1); display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 1.05rem; flex: none; }
        .sidebar .brand b { color: #fff; font-size: .98rem; letter-spacing: -.01em; line-height: 1.15; }
        .sidebar .brand small { display: block; color: #64748b; font-size: .68rem; font-weight: 500; }
        .sidebar nav { padding: .9rem .75rem 1rem; overflow-y: auto; flex: 1; }
        .sidebar .nav-label { font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .09em; color: #475569; padding: .85rem .6rem .3rem; }
        .sidebar .nav-link { display: flex; align-items: center; gap: .7rem; color: #94a3b8; border-radius: 10px; padding: .62rem .7rem; font-weight: 500; font-size: .875rem; margin-bottom: 2px; }
        .sidebar .nav-link i { width: 19px; text-align: center; font-size: .9rem; }
        .sidebar .nav-link:hover { background: rgba(148,163,184,.12); color: #e2e8f0; }
        .sidebar .nav-link.active { background: var(--brand); color: #fff; box-shadow: 0 4px 14px -4px rgba(37,99,235,.6); }
        .sidebar .sidebar-foot { padding: .9rem 1.25rem; border-top: 1px solid rgba(148,163,184,.15); }
        .sidebar .sidebar-foot .who { display: flex; align-items: center; gap: .65rem; }
        .sidebar .sidebar-foot .avatar { width: 36px; height: 36px; border-radius: 50%; background: #1e293b; color: #e2e8f0; display: grid; place-items: center; font-weight: 700; font-size: .8rem; flex: none; }
        .sidebar .sidebar-foot b { display: block; color: #e2e8f0; font-size: .8rem; line-height: 1.2; }
        .sidebar .sidebar-foot small { color: #64748b; font-size: .68rem; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.55); z-index: 1040; }

        /* ===== Topbar ===== */
        .main { margin-left: 264px; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { position: sticky; top: 0; z-index: 1030; background: rgba(255,255,255,.86); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); min-height: 68px; display: flex; align-items: center; gap: .75rem; padding: 0 1.5rem; }
        .topbar .menu-btn { display: none; border: 0; background: none; font-size: 1.25rem; color: var(--ink); padding: .4rem; }
        .topbar .crumb b { font-size: 1.02rem; display: block; line-height: 1.2; }
        .topbar .crumb small { color: var(--ink-3); font-size: .74rem; }
        .branch-picker { margin-left: auto; display: flex; align-items: center; gap: .6rem; }
        .branch-picker label { font-size: .72rem; color: var(--ink-3); font-weight: 600; white-space: nowrap; }
        .branch-picker select { border: 1px solid var(--line); border-radius: 10px; padding: .45rem 2rem .45rem .8rem; font-size: .82rem; font-weight: 600; color: var(--ink); background: #fff; max-width: 220px; }

        .content { padding: 1.5rem; flex: 1; width: 100%; max-width: 1440px; margin: 0 auto; }

        /* ===== Kartu & elemen ===== */
        .card { border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); background: var(--surface); }
        .card .card-header { background: #fff; border-bottom: 1px solid var(--line); border-radius: var(--radius) var(--radius) 0 0; padding: .9rem 1.25rem; font-weight: 600; font-size: .9rem; display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
        .card .card-header .h-sub { font-weight: 500; color: var(--ink-3); font-size: .78rem; }
        .card .card-body { padding: 1.25rem; }
        .card .card-footer { background: #f8fafc; border-top: 1px solid var(--line); border-radius: 0 0 var(--radius) var(--radius); padding: .8rem 1.25rem; }

        .stat { border-radius: var(--radius); }
        .stat .stat-ico { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 1.05rem; flex: none; }
        .stat .stat-num { font-size: 1.45rem; font-weight: 800; letter-spacing: -.03em; line-height: 1.2; }
        .stat .stat-lbl { color: var(--ink-3); font-size: .78rem; font-weight: 600; }

        .btn { border-radius: 10px; font-weight: 600; font-size: .855rem; padding: .5rem 1rem; }
        .btn-primary { background: var(--brand); border-color: var(--brand); }
        .btn-primary:hover, .btn-primary:focus { background: var(--brand-ink); border-color: var(--brand-ink); }
        .btn-outline-secondary { color: var(--ink-2); border-color: var(--line); }
        .btn-outline-secondary:hover { background: var(--bg); color: var(--ink); }
        .btn-sm { padding: .35rem .75rem; font-size: .8rem; border-radius: 8px; }

        .form-control, .form-select { border-color: var(--line); border-radius: 10px; font-size: .875rem; }
        .form-control:focus, .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 .2rem rgba(37,99,235,.12); }
        .form-label { font-size: .78rem; font-weight: 600; color: var(--ink-2); margin-bottom: .3rem; }

        .badge { font-weight: 600; font-size: .72rem; border-radius: 999px; padding: .35em .8em; }
        .badge-soft { background: var(--brand-soft); color: var(--brand-ink); }

        .table { font-size: .855rem; }
        .table thead th { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ink-3); background: #f8fafc; border-bottom: 1px solid var(--line); white-space: nowrap; padding: .7rem 1rem; }
        .table tbody td { padding: .7rem 1rem; border-color: #eef2f7; vertical-align: middle; }
        .table tbody tr:hover { background: #f8fafc; }

        .alert { border: 0; border-radius: 12px; font-size: .875rem; }

        /* ===== DataTables ===== */
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { font-size: .8rem; color: var(--ink-2); }
        .dataTables_wrapper .dataTables_filter input { border: 1px solid var(--line); border-radius: 10px; padding: .45rem .8rem; font-size: .82rem; margin-left: .5rem; }
        .dataTables_wrapper .dataTables_filter input:focus { border-color: var(--brand); outline: none; box-shadow: 0 0 0 .2rem rgba(37,99,235,.12); }
        .dataTables_wrapper .dataTables_length select { border: 1px solid var(--line); border-radius: 8px; padding: .3rem .5rem; font-size: .8rem; }
        .dataTables_wrapper .dataTables_paginate .pagination { border: 0; margin: .75rem 0 0; }
        .dataTables_wrapper .dataTables_paginate .pagination .page-link { border: 1px solid var(--line); color: var(--ink-2); font-size: .8rem; }
        .dataTables_wrapper .dataTables_paginate .pagination .page-item.active .page-link { background: var(--brand); border-color: var(--brand); color: #fff; }
        .dataTables_wrapper .dataTables_paginate .pagination .page-item:first-child .page-link { border-radius: 8px 0 0 8px; }
        .dataTables_wrapper .dataTables_paginate .pagination .page-item:last-child .page-link { border-radius: 0 8px 8px 0; }
        table.dataTable.empty td { text-align: center; color: var(--ink-3); padding: 2.5rem 1rem; }

        /* ===== Modal ===== */
        .modal-content { border: 0; border-radius: 16px; box-shadow: 0 24px 64px -16px rgba(15,23,42,.35); }
        .modal-header { border-bottom: 1px solid var(--line); padding: 1rem 1.25rem; }
        .modal-footer { border-top: 1px solid var(--line); padding: .9rem 1.25rem; }
        .modal-title { font-size: 1rem; font-weight: 700; }
        @media (max-width: 575.98px) {
            .modal { --bs-modal-margin: 0; }
            .modal-content { border-radius: 0; min-height: 100vh; }
            .modal-dialog-centered { margin-top: 0; margin-bottom: 0; }
        }

        /* ===== POS ===== */
        .pos-products { max-height: calc(100vh - 320px); overflow-y: auto; padding: .5rem; }
        .pos-product { background: #fff; border: 1.5px solid var(--line); border-radius: 12px; padding: .8rem; cursor: pointer; transition: all .12s ease; height: 100%; }
        .pos-product:hover { border-color: var(--brand); box-shadow: 0 4px 14px -6px rgba(37,99,235,.35); transform: translateY(-1px); }
        .pos-product b { font-size: .83rem; display: block; line-height: 1.25; min-height: 2.1em; }
        .pos-product .price { color: var(--brand-ink); font-weight: 700; font-size: .85rem; margin-top: .35rem; display: block; }
        .cart-item { border-bottom: 1px dashed var(--line); padding: .6rem 0; }
        .cart-item:last-child { border-bottom: 0; }
        .total-big { font-size: 1.6rem; font-weight: 800; letter-spacing: -.03em; }

        /* ===== Misc ===== */
        .page-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .page-head h1 { font-size: 1.35rem; margin: 0; }
        .page-head p { margin: .2rem 0 0; color: var(--ink-3); font-size: .85rem; }
        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--ink-3); }
        .empty-state i { font-size: 2.2rem; margin-bottom: .75rem; display: block; color: #cbd5e1; }
        .kbd { background: #f1f5f9; border: 1px solid var(--line); border-radius: 6px; padding: .1em .45em; font-size: .75em; font-family: ui-monospace, monospace; }
        .timeline { position: relative; padding-left: 1.5rem; }
        .timeline::before { content: ''; position: absolute; left: 6px; top: 6px; bottom: 6px; width: 2px; background: var(--line); }
        .timeline .t-item { position: relative; padding-bottom: 1rem; }
        .timeline .t-item::before { content: ''; position: absolute; left: -1.5rem; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: var(--brand); border: 2.5px solid #fff; box-shadow: 0 0 0 1.5px var(--brand); }
        .timeline .t-item.done::before { background: var(--ok); box-shadow: 0 0 0 1.5px var(--ok); }
        .timeline .t-item.pending::before { background: #cbd5e1; box-shadow: 0 0 0 1.5px #cbd5e1; }

        @media print {
            .sidebar, .topbar, .no-print { display: none !important; }
            .main { margin: 0; }
            .content { padding: 0; max-width: none; }
            body { background: #fff; }
            .card { border: 0; box-shadow: none; }
        }

        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            body.sidebar-open .sidebar { transform: translateX(0); }
            body.sidebar-open .sidebar-overlay { display: block; }
            .main { margin-left: 0; }
            .topbar { padding: 0 1rem; }
            .topbar .menu-btn { display: block; }
            .content { padding: 1rem; }
            .branch-picker label { display: none; }
            .branch-picker select { max-width: 150px; }
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="logo"><i class="fa-solid fa-store"></i></div>
        <div><b>{{ config('app.name') }}</b><small>Franchise Management</small></div>
    </div>
    @auth
    <nav>
        @php($current = request()->route()?->getName())
        <div class="nav-label">Utama</div>
        <a href="{{ route('dashboard') }}" class="nav-link {{ $current === 'dashboard' ? 'active' : '' }}"><i class="fa-solid fa-gauge-high"></i>Dashboard</a>
        <div class="nav-label">Operasional</div>
        <a href="{{ route('sales.create') }}" class="nav-link {{ str_starts_with($current ?? '', 'sales.create') ? 'active' : '' }}"><i class="fa-solid fa-cash-register"></i>Kasir</a>
        <a href="{{ route('sales.index') }}" class="nav-link {{ str_starts_with($current ?? '', 'sales.') && $current !== 'sales.create' ? 'active' : '' }}"><i class="fa-solid fa-receipt"></i>Penjualan</a>
        <a href="{{ route('purchase-orders.index') }}" class="nav-link {{ str_starts_with($current ?? '', 'purchase-orders.') ? 'active' : '' }}"><i class="fa-solid fa-truck-ramp-box"></i>Purchase Order</a>
        @unless(auth()->user()->isCentralOwner())
        <a href="{{ route('finance.index') }}" class="nav-link {{ str_starts_with($current ?? '', 'finance.') ? 'active' : '' }}"><i class="fa-solid fa-wallet"></i>Keuangan</a>
        @endunless
        <div class="nav-label">Pusat</div>
        <a href="{{ route('ingredients.index') }}" class="nav-link {{ str_starts_with($current ?? '', 'ingredients.') ? 'active' : '' }}"><i class="fa-solid fa-boxes-stacked"></i>Bahan Baku</a>
        <a href="{{ route('products.index') }}" class="nav-link {{ str_starts_with($current ?? '', 'products.') ? 'active' : '' }}"><i class="fa-solid fa-tags"></i>Produk &amp; Resep</a>
    </nav>
    <div class="sidebar-foot">
        <div class="who">
            <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <div class="flex-1" style="min-width:0">
                <b class="text-truncate">{{ auth()->user()->name }}</b>
                <small>{{ auth()->user()->roleLabel() }}</small>
            </div>
            <form method="post" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm text-secondary p-2" title="Keluar" style="color:#64748b"><i class="fa-solid fa-right-from-bracket"></i></button>
            </form>
        </div>
    </div>
    @endauth
</aside>

<div class="main">
    <header class="topbar">
        <button class="menu-btn" id="sidebarToggle" type="button" aria-label="Buka menu"><i class="fa-solid fa-bars"></i></button>
        <div class="crumb">
            <b>@yield('heading', $pageTitle)</b>
            <small>{{ now()->format('d F Y') }}</small>
        </div>
        @auth
        @if(auth()->user()->branches()->count() > 1)
        <div class="branch-picker">
            <label for="branchSwitcher">Cabang</label>
            <form method="post" action="{{ route('active-branch.store') }}" class="m-0">
                @csrf
                <select name="branch_id" id="branchSwitcher" class="form-select" onchange="this.form.submit()">
                    @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branch->id === $activeBranchId)>{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>
            </form>
        </div>
        @endif
        @endauth
    </header>

    <main class="content">
        @if(session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <i class="fa-solid fa-circle-check"></i><span>{{ session('success') }}</span>
        </div>
        @endif
        @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Periksa input Anda</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endif
        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script>
(() => {
    const t = document.getElementById('sidebarToggle');
    const o = document.getElementById('sidebarOverlay');
    const close = () => document.body.classList.remove('sidebar-open');
    if (t) t.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
    if (o) o.addEventListener('click', close);
    document.querySelectorAll('.sidebar a').forEach(a => a.addEventListener('click', close));
})();
</script>
<script>
    // Utilitas global
    window.App = {
        csrf: document.querySelector('meta[name="csrf-token"]')?.content,
        fmtRupiah(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID'); },
        flash(msg, type = 'success') {
            const wrap = document.querySelector('.content');
            if (!wrap) return;
            const el = document.createElement('div');
            el.className = 'alert alert-' + type + ' d-flex align-items-center gap-2';
            el.innerHTML = '<i class="fa-solid ' + (type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation') + '"></i><span></span>';
            el.querySelector('span').textContent = msg;
            wrap.prepend(el);
            setTimeout(() => el.remove(), 4500);
        },
        // Init DataTable serverside — panggil: App.dt('tabelId', {url: '...'})
        dt(tableId, opts) {
            return $('#' + tableId).DataTable({
                processing: true,
                serverSide: true,
                ajax: { url: opts.url, headers: { 'X-CSRF-TOKEN': App.csrf } },
                columns: opts.columns,
                language: {
                    url: '',
                    "emptyTable": "Tidak ada data",
                    "info": "Menampilkan _START_ s/d _END_ dari _TOTAL_",
                    "infoEmpty": "Tidak ada data",
                    "infoFiltered": "(diterangkan dari _MAX_)",
                    "lengthMenu": "_MENU_ / hal",
                    "loadingRecords": "Memuat…",
                    "processing": "Memproses…",
                    "search": "Cari:",
                    "zeroRecords": "Tidak ada yang cocok",
                    "paginate": { "first": "⟪", "last": "⟫", "next": "›", "previous": "‹" }
                },
                order: opts.order ?? [],
                pageLength: opts.pageLength ?? 10,
                lengthMenu: [5, 10, 25, 50, 100],
                dom: '<"row g-2 mb-1 align-items-center"><"col-sm-12 col-md-auto"l><"col-sm-12 col-md" f>>rt<"row g-2"<"col-sm-12 col-md-auto"i><"col-sm-12 col-md text-end"p>>'
            });
        }
    };
</script>
@stack('scripts')
</body>
</html>
