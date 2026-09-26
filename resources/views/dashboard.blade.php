@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <h1>Selamat datang, {{ auth()->user()->name }} 👋</h1>
        <p>{{ $isCentral ? 'Pantau seluruh cabang, stok, dan alur pembelian dari sini.' : 'Ringkasan operasional cabang Anda hari ini.' }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('sales.create') }}" class="btn btn-primary"><i class="fa-solid fa-cash-register me-1"></i>Buka Kasir</a>
        @if(auth()->user()->isBranchOwner())
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-plus me-1"></i>Buat PO</a>
        @endif
        @unless($isCentral)
        <a href="{{ route('finance.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-wallet me-1"></i>Keuangan</a>
        @endunless
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-ico" style="background:var(--brand-soft);color:var(--brand-ink)"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="flex-grow-1">
                <div class="stat-num">{{ App\Support\Money::id($salesTotal) }}</div>
                <div class="stat-lbl">Omzet 7 hari</div>
            </div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-ico" style="background:var(--ok-soft);color:var(--ok)"><i class="fa-solid fa-receipt"></i></div>
            <div class="flex-grow-1">
                <div class="stat-num">{{ number_format($salesCount) }}</div>
                <div class="stat-lbl">Transaksi (7 hari)</div>
            </div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-ico" style="background:var(--warn-soft);color:var(--warn)"><i class="fa-solid fa-truck-ramp-box"></i></div>
            <div class="flex-grow-1">
                <div class="stat-num">{{ $pendingPOs }}</div>
                <div class="stat-lbl">PO menunggu approval</div>
            </div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-ico" style="background:var(--bad-soft);color:var(--bad)"><i class="fa-solid fa-box-open"></i></div>
            <div class="flex-grow-1">
                <div class="stat-num">{{ $lowStocks->count() }}</div>
                <div class="stat-lbl">Stok di bawah reorder</div>
            </div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Peringatan Stok
                <span class="h-sub ms-auto">stok ≤ reorder point</span></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Cabang</th><th>Bahan</th><th class="text-end">Stok</th><th class="text-end">Reorder</th><th class="text-end">Status</th></tr></thead>
                    <tbody>
                    @forelse($lowStocks as $stock)
                        <tr>
                            <td class="text-nowrap">{{ $stock->branch->name }}</td>
                            <td>{{ $stock->ingredient->name }}</td>
                            <td class="text-end fw-semibold">{{ $stock->quantity }} {{ $stock->ingredient->unit }}</td>
                            <td class="text-end text-secondary">{{ $stock->ingredient->reorder_point }}</td>
                            <td class="text-end"><span class="badge text-bg-danger">Kritis</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state"><i class="fa-solid fa-circle-check"></i>Semua stok aman.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        @unless($isCentral)
        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-clock me-1"></i>Shift Kasir</div>
            <div class="card-body">
                @if($activeShift)
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge text-bg-success mb-1">TERBUKA</span>
                            <div class="small text-secondary mt-1">Sejak {{ $activeShift->opened_at->diffForHumans() }} · saldo awal {{ App\Support\Money::id($activeShift->opening_balance) }}</div>
                        </div>
                        <a href="{{ route('finance.index') }}" class="btn btn-sm btn-primary">Kelola</a>
                    </div>
                @else
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <span class="text-secondary small"><i class="fa-solid fa-circle-info me-1"></i>Tidak ada shift aktif. Buka shift dulu agar POS dapat mencatat.</span>
                        <a href="{{ route('finance.index') }}" class="btn btn-sm btn-outline-secondary">Buka</a>
                    </div>
                @endif
            </div>
        </div>
        @endunless

        <div class="card">
            <div class="card-header"><i class="fa-solid fa-chart-column me-1"></i>Stok Teratas</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Cabang</th><th>Bahan</th><th class="text-end">Stok</th></tr></thead>
                    <tbody>
                    @forelse($stocks->sortByDesc('quantity')->take(6) as $stock)
                        <tr>
                            <td class="text-nowrap">{{ $stock->branch->name }}</td>
                            <td>{{ $stock->ingredient->name }}</td>
                            <td class="text-end fw-semibold">{{ $stock->quantity }} {{ $stock->ingredient->unit }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-state"><i class="fa-solid fa-boxes-stacked"></i>Belum ada stok terdaftar.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
