@extends('layouts.app')

@section('content')
@php
    $u = auth()->user();
    $po = $order;
    $total = (float) $po->items->sum(fn ($i) => $i->quantity * $i->unit_cost);
    $steps = [
        'pending' => ['label' => 'Menunggu approval', 'at' => $po->ordered_at, 'done' => $po->status !== 'pending', 'who' => $po->creator?->name],
        'approved' => ['label' => 'Disetujui pusat', 'at' => $po->approved_at, 'done' => in_array($po->status, ['approved', 'shipped', 'received']), 'who' => $po->approver?->name],
        'shipped' => ['label' => 'Dikirim (stok pusat berkurang)', 'at' => null, 'done' => in_array($po->status, ['shipped', 'received']), 'who' => null],
        'received' => ['label' => 'Diterima cabang (stok bertambah)', 'at' => $po->received_at, 'done' => $po->status === 'received', 'who' => null],
    ];
    $currentIdx = ['pending' => 0, 'approved' => 1, 'shipped' => 2, 'received' => 3, 'rejected' => 1][$po->status] ?? 0;
@endphp

<div class="page-head">
    <div>
        <h1>PO #{{ $po->id }}</h1>
        <p>Permintaan {{ $po->branch->name }} oleh {{ $po->creator->name }}</p>
    </div>
    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Kembali</a>
</div>

@if($po->status === 'rejected')
<div class="alert alert-danger"><i class="fa-solid fa-ban me-1"></i>PO ini <b>ditolak</b> oleh pusat. Permintaan tidak akan diproses.</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-boxes-stacked me-1"></i>Item PO</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Bahan</th><th>Satuan</th><th class="text-end">Jumlah</th><th class="text-end">Harga pusat</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                    @foreach($po->items as $item)
                        <tr>
                            <td>{{ $item->ingredient->name }}</td>
                            <td>{{ $item->ingredient->unit }}</td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td class="text-end">{{ App\Support\Money::id($item->unit_cost) }}</td>
                            <td class="text-end fw-semibold">{{ App\Support\Money::id($item->quantity * $item->unit_cost) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th colspan="4" class="text-end">Total estimasi</th><th class="text-end">{{ App\Support\Money::id($total) }}</th></tr>
                    </tfoot>
                </table>
            </div>
            @if($po->notes)
            <div class="card-footer"><span class="text-secondary small">Catatan:</span> {{ $po->notes }}</div>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-list-check me-1"></i>Status &amp; Aksi</div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge text-bg-{{ ['pending' => 'warning', 'approved' => 'info', 'shipped' => 'primary', 'received' => 'success', 'rejected' => 'danger'][$po->status] }}">{{ strtoupper($po->status) }}</span>
                </div>
                <div class="timeline mb-4">
                    @foreach($steps as $i => $step)
                    <div class="t-item {{ $step['done'] ? 'done' : ($i === $currentIdx && $po->status !== 'rejected' ? '' : 'pending') }}">
                        <div class="fw-semibold">{{ $step['label'] }}</div>
                        <div class="small text-secondary">
                            @if($step['at']){{ $step['at']->format('d M Y H:i') }} @if($step['who'])· {{ $step['who'] }} @endif
                            @else belum @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="d-flex flex-column gap-2">
                    @if($u->isCentralOwner() && $po->status === 'pending')
                        <form method="post" action="{{ route('purchase-orders.approve', $po) }}" class="d-flex gap-2">
                            @csrf
                            <button class="btn btn-primary flex-fill" onclick="return confirm('Setujui PO #{{ $po->id }}?')"><i class="fa-solid fa-check me-1"></i>Setujui</button>
                        </form>
                        <form method="post" action="{{ route('purchase-orders.reject', $po) }}" class="d-flex gap-2">
                            @csrf
                            <button class="btn btn-outline-danger flex-fill" onclick="return confirm('Tolak PO #{{ $po->id }}?')"><i class="fa-solid fa-xmark me-1"></i>Tolak</button>
                        </form>
                    @elseif($u->isCentralOwner() && $po->status === 'approved')
                        <form method="post" action="{{ route('purchase-orders.ship', $po) }}">
                            @csrf
                            <button class="btn btn-primary w-100" onclick="return confirm('Kirim PO #{{ $po->id }}? Stok pusat akan dikurangi.')"><i class="fa-solid fa-truck me-1"></i>Kirim Barang</button>
                        </form>
                    @elseif(!$u->isCentralOwner() && $po->status === 'shipped')
                        <form method="post" action="{{ route('purchase-orders.receive', $po) }}">
                            @csrf
                            <button class="btn btn-success w-100" onclick="return confirm('Tandai PO #{{ $po->id }} diterima? Stok cabang akan ditambah.')"><i class="fa-solid fa-box-open me-1"></i>Terima Barang</button>
                        </form>
                    @else
                        <p class="text-secondary small mb-0"><i class="fa-solid fa-circle-info me-1"></i>
                            @if($po->status === 'received')
                            PO selesai diproses.
                            @else
                            Tidak ada aksi untuk role Anda saat ini.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
