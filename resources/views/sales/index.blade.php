@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <h1>Penjualan</h1>
        <p>Riwayat transaksi POS di cabang Anda ({{ auth()->user()->isCentralOwner() ? 'seluruh cabang' : 'akses Anda' }}).</p>
    </div>
    <a href="{{ route('sales.create') }}" class="btn btn-primary"><i class="fa-solid fa-cash-register me-1"></i>Transaksi Baru</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table id="salesTable" class="table">
            <thead>
                <tr>
                    <th>Struk</th>
                    <th>Cabang</th>
                    <th>Kasir</th>
                    <th>Waktu</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
App.dt('salesTable', {
    url: @json(route('sales.data')),
    order: [[3, 'desc']],
    pageLength: 10,
    columns: [
        { data: 'receipt_number', render: d => `<span class="font-monospace" style="font-size:.8rem">${d}</span>` },
        { data: 'branch' },
        { data: 'cashier' },
        { data: 'sold_at' },
        { data: 'total', className: 'text-end fw-semibold' },
        {
            data: 'url', orderable: false, className: 'text-end',
            render: d => `<a href="${d}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eye me-1"></i>Detail</a>`
        }
    ]
});
</script>
@endpush
@endsection
