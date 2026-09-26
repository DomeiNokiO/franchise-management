@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <h1>Buat Purchase Order</h1>
        <p>Minta bahan baku dari gudang pusat. PO akan menunggu persetujuan Full Owner.</p>
    </div>
    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Kembali</a>
</div>

<form method="post" action="{{ route('purchase-orders.store') }}">
    @csrf
    <div class="card">
        <div class="card-header"><i class="fa-solid fa-truck-ramp-box me-1"></i>Detail Permintaan</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Cabang tujuan</label>
                    <select name="branch_id" class="form-select" required>
                        <option value="">Pilih cabang…</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Catatan (opsional)</label>
                    <input name="notes" class="form-control" maxlength="2000" placeholder="Misal: restok bahan mingguan" value="{{ old('notes') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><i class="fa-solid fa-boxes-stacked me-1"></i>Bahan yang Diminta
            <button type="button" class="btn btn-sm btn-primary ms-auto" id="addRow"><i class="fa-solid fa-plus me-1"></i>Tambah baris</button></div>
        <div class="card-body" id="itemsWrap">
            <p class="text-secondary small mb-2">Pilih bahan dan isi jumlah yang dibutuhkan.</p>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Kirim PO</button>
        </div>
    </div>
</form>

@php
    $ingredientsJson = $ingredients->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => $i->unit, 'cost' => (float) $i->cost])->values();
@endphp
@push('scripts')
<script>
(() => {
    const list = @json($ingredientsJson);
    const wrap = document.getElementById('itemsWrap');
    const optionHtml = list.map(i => `<option value="${i.id}" data-unit="${i.unit}" data-cost="${i.cost}">${i.name} (${i.unit})</option>`).join('');
    let idx = 0;

    function addRow(data = null) {
        const i = idx++;
        wrap.insertAdjacentHTML('beforeend', `
            <div class="row g-2 mb-2 po-row align-items-center">
                <div class="col-12 col-md-6">
                    <select name="items[${i}][ingredient_id]" class="form-select ing" required>
                        <option value="">Pilih bahan…</option>${optionHtml}
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <input name="items[${i}][quantity]" class="form-control qty" type="number" min="0.001" step="0.001" placeholder="Jumlah" required>
                </div>
                <div class="col-5 col-md-2 text-secondary small unit-view">—</div>
                <div class="col-1 d-grid"><button type="button" class="btn btn-outline-danger btn-sm del" title="Hapus"><i class="fa-solid fa-trash-can"></i></button></div>
            </div>`);
        const row = wrap.lastElementChild;
        const sel = row.querySelector('.ing');
        const upd = () => {
            const o = sel.selectedOptions[0];
            row.querySelector('.unit-view').textContent = o && o.value ? '× ' + (o.dataset.cost ? (o.dataset.cost / 1).toLocaleString('id-ID') + ' /' : '') + o.dataset.unit : '—';
        };
        sel.addEventListener('change', upd);
        if (data) { sel.value = data.ingredient_id; row.querySelector('.qty').value = data.quantity; }
        upd();
    }

    wrap.addEventListener('click', e => {
        if (e.target.closest('.del')) e.target.closest('.po-row').remove();
    });
    document.getElementById('addRow').addEventListener('click', () => addRow());

    // restore old input
    @foreach((old('items') ?? []) as $item)
        if ($item['ingredient_id']) addRow({ ingredient_id: {{ (int) $item['ingredient_id'] }}, quantity: {{ (float) $item['quantity'] }} });
    @endforeach
    if (!wrap.querySelector('.po-row')) addRow();

    document.querySelector('form').addEventListener('submit', e => {
        if (!wrap.querySelector('.po-row')) { e.preventDefault(); App.flash('Tambahkan minimal satu bahan.', 'danger'); }
    });
})();
</script>
@endpush
@endsection
