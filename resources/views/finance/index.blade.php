@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <h1>Keuangan Cabang</h1>
        <p>Shift kasir, transaksi kas, dan ringkasan laporan. Khusus Owner Mitra.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" id="btnOpenShift"><i class="fa-solid fa-power-off me-1"></i>Buka Shift</button>
        <button class="btn btn-primary" id="btnTransaksi"><i class="fa-solid fa-plus me-1"></i>Catat Kas</button>
    </div>
</div>

<!-- ===== Filter laporan ===== -->
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Cabang</label>
                <select name="branch_id" class="form-select">
                    @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branch->id === $branchId)>{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Dari tanggal</label>
                <input type="date" name="from" class="form-control" value="{{ $from }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sampai tanggal</label>
                <input type="date" name="to" class="form-control" value="{{ $to }}">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Tampilkan Laporan</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Kartu ringkasan ===== -->
@php
    $stats = $report ? [
        ['Omzet POS', $report['sales_total'], 'fa-solid fa-sack-dollar', 'var(--brand-soft)', 'var(--brand-ink)', $report['sales_count'].' transaksi'],
        ['Kas masuk', $report['cash_income'], 'fa-solid fa-arrow-down', 'var(--ok-soft)', 'var(--ok)', 'seluruh sumber'],
        ['Biaya / kas keluar', $report['cash_expense'], 'fa-solid fa-arrow-up', 'var(--bad-soft)', 'var(--bad)', 'berdasarkan pengeluaran'],
    ] : [];
@endphp
@if($report)
<div class="row g-3 mb-3">
    @foreach($stats as [$label, $value, $icon, $bg, $color, $sub])
    <div class="col-sm-6 col-lg-4">
        <div class="card stat h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-ico" style="background:{{ $bg }};color:{{ $color }}"><i class="{{ $icon }}"></i></div>
            <div class="flex-grow-1" style="min-width:0">
                <div class="stat-num text-truncate">{{ App\Support\Money::id($value) }}</div>
                <div class="stat-lbl">{{ $label }} <span class="text-secondary">· {{ $sub }}</span></div>
            </div>
        </div></div>
    </div>
    @endforeach
</div>
@endif

<div class="row g-3">
    <!-- ===== Shift ===== -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-clock me-1"></i>Shift Kasir</div>
            <div class="card-body p-0">
                @forelse($openShifts as $shift)
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center gap-2" style="background:var(--ok-soft)">
                    <div>
                        <b>{{ $shift->branch->name }}</b>
                        <div class="small text-secondary">Dibuka {{ $shift->opened_at->diffForHumans() }} oleh {{ $shift->opener?->name }} · saldo awal {{ App\Support\Money::id($shift->opening_balance) }}</div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger" data-close-shift="{{ $shift->id }}" data-branch="{{ $shift->branch->name }}">Tutup</button>
                </div>
                @empty
                <div class="empty-state py-4"><i class="fa-solid fa-moon"></i>Tidak ada shift terbuka.<br><small>Buka shift agar POS dapat mencatat penjualan.</small></div>
                @endforelse

                @if($closedShifts->count())
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Cabang</th><th>Selesai</th><th class="text-end">Fisik</th><th class="text-end">Selisih</th></tr></thead>
                        <tbody>
                        @foreach($closedShifts as $shift)
                        <tr>
                            <td class="text-nowrap">{{ $shift->branch->name }}</td>
                            <td class="text-nowrap">{{ $shift->closed_at->format('d/m H:i') }}</td>
                            <td class="text-end">{{ App\Support\Money::id($shift->closing_balance) }}</td>
                            <td class="text-end">
                                @php($v = (float) $shift->variance)
                                <span class="badge {{ abs($v) < 0.01 ? 'text-bg-success' : 'text-bg-danger' }}">{{ $v > 0 ? '+' : '' }}{{ App\Support\Money::id($v) }}</span>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- ===== Pengeluaran ===== -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-arrow-up me-1"></i>Pengeluaran Periode
                <span class="h-sub ms-auto">{{ now()->createFromFormat('Y-m-d', $from)->translatedFormat('d M Y') }} – {{ now()->createFromFormat('Y-m-d', $to)->translatedFormat('d M Y') }}</span></div>
            <div class="table-responsive" style="max-height:420px;overflow-y:auto">
                <table class="table mb-0">
                    <thead class="sticky-top"><tr><th>Waktu</th><th>Kategori</th><th>Keterangan</th><th class="text-end">Nominal</th></tr></thead>
                    <tbody>
                    @forelse($report['expenses'] ?? [] as $expense)
                        <tr>
                            <td class="text-nowrap">{{ $expense->occurred_at->format('d/m H:i') }}</td>
                            <td><span class="badge badge-soft">{{ $expense->category }}</span></td>
                            <td>{{ $expense->description }}</td>
                            <td class="text-end fw-semibold text-danger">{{ App\Support\Money::id($expense->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state"><i class="fa-solid fa-folder-open"></i>Tidak ada pengeluaran pada periode ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal: buka shift ===== -->
<div class="modal fade" id="openShiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('finance.shifts.open') }}">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title">Buka shift kasir</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cabang</label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">Pilih cabang…</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Saldo awal kas (Rp)</label>
                        <input type="number" name="opening_balance" min="0" step="0.01" class="form-control" required placeholder="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-power-off me-1"></i>Buka Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Modal: tutup shift ===== -->
<div class="modal fade" id="closeShiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="closeShiftForm">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title">Tutup shift <span id="closeBranchName" class="text-secondary"></span></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Saldo fisik akhir (Rp)</label>
                        <input type="number" name="closing_balance" id="closing_balance" min="0" step="0.01" class="form-control" required placeholder="0">
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea name="closing_note" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                    <div class="alert alert-info small mb-0"><i class="fa-solid fa-circle-info me-1"></i>Selisih = saldo fisik − saldo sistem (saldo awal + kas masuk − kas keluar).</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-lock me-1"></i>Tutup Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Modal: catat kas ===== -->
<div class="modal fade" id="transaksiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('finance.transactions.store') }}">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title">Catat transaksi kas</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cabang</label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">Pilih cabang…</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Jenis</label>
                            <select name="type" class="form-select" required>
                                <option value="expense">Pengeluaran</option>
                                <option value="income">Pemasukan</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Kategori</label>
                            <input name="category" class="form-control" maxlength="80" required placeholder="sewa, listrik, gaji…">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nominal (Rp)</label>
                            <input type="number" name="amount" min="0.01" step="0.01" class="form-control" required placeholder="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Keterangan (opsional)</label>
                            <input name="description" class="form-control" maxlength="255" placeholder="Detail pengeluaran…">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Simpan Transaksi</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const closeShiftRoutes = (id) => @json(route('finance.shifts.close', ':id')).replace(':id', id);
    const closeForm = document.getElementById('closeShiftForm');
    const closeModal = new bootstrap.Modal('#closeShiftModal');

    document.getElementById('btnOpenShift').addEventListener('click', () => new bootstrap.Modal('#openShiftModal').show());
    document.getElementById('btnTransaksi').addEventListener('click', () => new bootstrap.Modal('#transaksiModal').show());

    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-close-shift]');
        if (!btn) return;
        closeForm.action = closeShiftRoutes(btn.dataset.closeShift);
        document.getElementById('closeBranchName').textContent = btn.dataset.branch;
        closeModal.show();
    });
})();
</script>
@endpush
@endsection
