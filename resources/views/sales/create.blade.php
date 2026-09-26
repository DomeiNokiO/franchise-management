@extends('layouts.app')

@section('heading', 'Kasir')

@section('content')
@php
    $rows = [];
    foreach (old('items', []) as $item) {
        $rows[] = ['product_id' => (int) ($item['product_id'] ?? 0), 'quantity' => (float) ($item['quantity'] ?? 1)];
    }
@endphp

<div class="row g-3">
    <!-- ===== Kiri: pilih produk ===== -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-tags me-1"></i>Produk
                <span class="h-sub ms-auto">{{ $products->count() }} tersedia</span>
            </div>
            <div class="pos-products">
                <div class="row g-2" id="productGrid">
                    @foreach($products as $product)
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="pos-product" data-id="{{ $product->id }}" data-price="{{ $product->selling_price }}" data-name="{{ $product->name }}">
                            <b>{{ $product->name }}</b>
                            <span class="price">{{ App\Support\Money::id($product->selling_price) }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Kanan: keranjang & pembayaran ===== -->
    <div class="col-lg-5">
        <form method="post" action="{{ route('sales.store') }}" id="posForm">
            @csrf
            <div class="card">
                <div class="card-header"><i class="fa-solid fa-basket-shopping me-1"></i>Keranjang
                    <span class="h-sub ms-auto" id="cartCount">0 item</span></div>
                <div class="card-body" id="cartList">
                    <div class="empty-state py-4"><i class="fa-solid fa-basket-shopping"></i>Keranjang kosong.<br><small>Klik produk di kiri untuk menambah.</small></div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Cabang transaksi</label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">Pilih cabang…</option>
                                @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($branch->id === $branchId)>{{ $branch->name }} ({{ $branch->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Diskon (Rp)</label>
                            <input type="number" name="discount" id="discount" min="0" step="0.01" class="form-control" value="{{ old('discount', 0) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Diterima (Rp)</label>
                            <input type="number" name="paid_amount" id="paid_amount" min="0" step="0.01" class="form-control" value="{{ old('paid_amount') }}" placeholder="0">
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small text-secondary">Subtotal</div>
                        <div class="fw-semibold" id="subtotal">Rp 0</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small text-secondary">Diskon</div>
                        <div class="fw-semibold text-danger" id="discountView">− Rp 0</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pb-2 border-bottom">
                        <div class="fw-semibold">Total</div>
                        <div class="total-big" id="totalView">Rp 0</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2" id="changeRow" style="display:none">
                        <div class="small text-secondary">Kembali</div>
                        <div class="fw-bold text-success" id="changeView">Rp 0</div>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill" id="posSubmit"><i class="fa-solid fa-check me-1"></i>Simpan Transaksi</button>
                    <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const priceMap = @json($products->pluck('selling_price', 'id'));
    const nameMap = @json($products->pluck('name', 'id'));
    let idx = 0;

    const cartList = document.getElementById('cartList');
    const emptyHtml = '<div class="empty-state py-4"><i class="fa-solid fa-basket-shopping"></i>Keranjang kosong.<br><small>Klik produk di kiri untuk menambah.</small></div>';

    function addRow(pid, qty = 1) {
        const i = idx++;
        const name = nameMap[pid] || 'Produk';
        cartList.insertAdjacentHTML('beforeend', `
            <div class="cart-item d-flex align-items-center gap-2" data-idx="${i}">
                <div class="flex-grow-1" style="min-width:0">
                    <div class="fw-semibold text-truncate">${name}</div>
                    <div class="small text-secondary line-view">…</div>
                </div>
                <div class="input-group input-group-sm" style="width:112px">
                    <button class="btn btn-outline-secondary" type="button" data-act="dec">−</button>
                    <input class="form-control text-center qty" name="items[${i}][quantity]" value="${qty}" min="0.001" step="0.001" inputmode="decimal">
                    <button class="btn btn-outline-secondary" type="button" data-act="inc">+</button>
                </div>
                <input type="hidden" name="items[${i}][product_id]" class="pid" value="${pid}">
                <button class="btn btn-sm text-danger p-2" type="button" data-act="del" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
            </div>`);
        calc();
    }

    // Isi ulang keranjang dari old input (flashback validasi)
    @foreach($rows as $r)
        addRow({{ $r['product_id'] }}, {{ $r['quantity'] }});
    @endforeach

    document.querySelectorAll('.pos-product').forEach(card => {
        card.addEventListener('click', () => addRow(card.dataset.id, 1));
    });

    cartList.addEventListener('click', e => {
        const btn = e.target.closest('button[data-act]');
        if (!btn) return;
        const row = btn.closest('.cart-item');
        const qtyInput = row.querySelector('.qty');
        const q = parseFloat(qtyInput.value) || 0;
        if (btn.dataset.act === 'inc') qtyInput.value = (q + 1).toString();
        if (btn.dataset.act === 'dec') qtyInput.value = Math.max(1, q - 1).toString();
        if (btn.dataset.act === 'del') row.remove();
        if (!cartList.querySelector('.cart-item')) cartList.innerHTML = emptyHtml;
        calc();
    });
    cartList.addEventListener('input', e => {
        if (e.target.classList.contains('qty')) calc();
    });

    const discountInput = document.getElementById('discount');
    const paidInput = document.getElementById('paid_amount');

    function fmt(v) { return 'Rp ' + Math.round(v).toLocaleString('id-ID'); }

    function calc() {
        let subtotal = 0, count = 0;
        cartList.querySelectorAll('.cart-item').forEach(row => {
            const pid = row.querySelector('.pid').value;
            const qty = parseFloat(row.querySelector('.qty').value) || 0;
            const price = Number(priceMap[pid] || 0);
            const line = price * qty;
            subtotal += line;
            count += qty;
            row.querySelector('.line-view').textContent = `${qty} × ${fmt(price)} = ${fmt(line)}`;
        });
        const discount = Math.min(parseFloat(discountInput.value) || 0, subtotal);
        const total = Math.max(0, subtotal - discount);
        document.getElementById('subtotal').textContent = fmt(subtotal);
        document.getElementById('discountView').textContent = '− ' + fmt(discount);
        document.getElementById('totalView').textContent = fmt(total);
        document.getElementById('cartCount').textContent = count + ' item';
        const paid = parseFloat(paidInput.value) || 0;
        const changeRow = document.getElementById('changeRow');
        if (paid >= total && count > 0) {
            changeRow.style.display = 'flex';
            document.getElementById('changeView').textContent = fmt(paid - total);
        } else {
            changeRow.style.display = 'none';
        }
        return total;
    }
    discountInput.addEventListener('input', calc);
    paidInput.addEventListener('input', calc);

    document.getElementById('posForm').addEventListener('submit', e => {
        const total = calc();
        const branch = document.querySelector('select[name=branch_id]').value;
        if (!cartList.querySelector('.cart-item')) { e.preventDefault(); App.flash('Pilih minimal satu produk.', 'danger'); return; }
        if (!branch) { e.preventDefault(); App.flash('Pilih cabang transaksi.', 'danger'); return; }
        if ((parseFloat(paidInput.value) || 0) < total) { e.preventDefault(); App.flash('Pembayaran kurang dari total.', 'danger'); return; }
    });

    calc();
})();
</script>
@endpush
@endsection
