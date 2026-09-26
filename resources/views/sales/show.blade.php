@extends('layouts.app')

@section('content')
<div class="page-head no-print">
    <div>
        <h1>Detail Penjualan</h1>
        <p>Struk <span class="font-monospace">{{ $sale->receipt_number }}</span></p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="fa-solid fa-print me-1"></i>Cetak</button>
        <a href="{{ route('sales.index') }}" class="btn btn-primary"><i class="fa-solid fa-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-receipt me-1"></i>Item Penjualan</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Produk</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                    @foreach($sale->items as $item)
                        <tr>
                            <td>{{ $item->product->name }}</td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td class="text-end">{{ App\Support\Money::id($item->unit_price) }}</td>
                            <td class="text-end fw-semibold">{{ App\Support\Money::id($item->line_total) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between"><span class="text-secondary">Subtotal</span><b>{{ App\Support\Money::id($sale->subtotal) }}</b></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Diskon</span><b class="text-danger">− {{ App\Support\Money::id($sale->discount) }}</b></div>
                <div class="d-flex justify-content-between fs-5"><span>Total</span><b>{{ App\Support\Money::id($sale->total) }}</b></div>
                <div class="d-flex justify-content-between text-success"><span class="text-secondary">Dibayar</span><b>{{ App\Support\Money::id($sale->paid_amount) }}</b></div>
                @if($sale->change_amount > 0)
                <div class="d-flex justify-content-between"><span class="text-secondary">Kembali</span><b>{{ App\Support\Money::id($sale->change_amount) }}</b></div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-circle-info me-1"></i>Info Transaksi</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="text-secondary small">Cabang</div>
                        <b>{{ $sale->branch->name }} <span class="text-secondary font-monospace small">({{ $sale->branch->code }})</span></b>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-secondary small">Kasir</div>
                        <b>{{ $sale->cashier->name }}</b>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-secondary small">Waktu</div>
                        <b>{{ $sale->sold_at->format('d M Y H:i') }}</b>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-secondary small">Status</div>
                        <span class="badge text-bg-success">{{ strtoupper($sale->status) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
