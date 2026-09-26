@extends('layouts.app')

@section('content')
@php
    $canEdit = auth()->user()->isCentralOwner();
@endphp

<div class="page-head">
    <div>
        <h1>Produk &amp; Resep</h1>
        <p>Katalog produk, harga jual, dan komposisi resep. {{ $canEdit ? 'Klik ikon pensil untuk edit.' : 'Mode baca.' }}</p>
    </div>
    @if($canEdit)
    <button class="btn btn-primary" id="btnAddProd"><i class="fa-solid fa-plus me-1"></i>Tambah Produk</button>
    @endif
</div>

<div class="card">
    <div class="table-responsive">
        <table id="prodTable" class="table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Produk</th>
                    <th class="text-end">Harga jual</th>
                    <th>Resep</th>
                    <th>Status</th>
                    @if($canEdit)<th class="text-end">Aksi</th>@endif
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@if($canEdit)
<div class="modal fade" id="prodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="prodForm">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title" id="prodModalTitle">Tambah produk</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body" id="prodModalBody">
                    <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
(() => {
    const canEdit = @json($canEdit);
    const routes = {
        data: @json(route('products.data')),
        store: @json(route('products.store')),
        form: (id) => @json(route('products.form')) + (id ? '/' + id : ''),
        update: (id) => @json(route('products.update', ':id')).replace(':id', id),
        destroy: (id) => @json(route('products.destroy', ':id')).replace(':id', id),
    };

    const columns = [
        { data: 'sku', render: d => `<span class="font-monospace" style="font-size:.8rem">${d}</span>` },
        { data: 'name', render: d => `<span class="fw-semibold">${d}</span>` },
        { data: 'selling_price', className: 'text-end fw-semibold' },
        { data: 'recipes', render: d => `<span class="text-secondary small">${d}</span>`, className: 'text-nowrap' },
        {
            data: 'is_active', orderable: false,
            render: b => b ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>'
        }
    ];
    if (canEdit) {
        columns.push({
            data: 'id', orderable: false, className: 'text-end',
            render: d => `
                <button class="btn btn-sm btn-outline-secondary me-1" data-edit="${d}" title="Edit"><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-sm btn-outline-danger" data-del="${d}" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>`
        });
    }

    const table = App.dt('prodTable', { url: routes.data, order: [[1, 'asc']], columns });

    if (!canEdit) return;

    const modal = new bootstrap.Modal('#prodModal');
    const body = document.getElementById('prodModalBody');
    const prodForm = document.getElementById('prodForm');

    document.getElementById('btnAddProd').addEventListener('click', () => openModal(null));

    async function openModal(id) {
        document.getElementById('prodModalTitle').textContent = id ? 'Edit produk & resep' : 'Tambah produk & resep';
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        prodForm.method = 'POST';
        prodForm.action = id ? routes.update(id) : routes.store;
        const oldMethod = prodForm.querySelector('input[name=_method]');
        if (oldMethod) oldMethod.remove();
        const res = await fetch(routes.form(id), { headers: { 'X-CSRF-TOKEN': App.csrf, 'X-Requested-With': 'fetch' } });
        if (!res.ok) { body.innerHTML = '<div class="alert alert-danger">Gagal memuat form. Hubungi admin.</div>'; return; }
        body.innerHTML = await res.text();
        if (id) {
            const m = document.createElement('input');
            m.type = 'hidden'; m.name = '_method'; m.value = 'PUT';
            prodForm.prepend(m);
        }
        modal.show();
    }

    table.table().on('click', 'button[data-edit]', e => openModal(e.currentTarget.dataset.edit));

    document.addEventListener('click', e => {
        const del = e.target.closest('button[data-del]');
        if (!del) return;
        if (!confirm('Hapus produk ini? Hanya bisa jika belum ada transaksi.')) return;
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = routes.destroy(del.dataset.del);
        f.innerHTML = `<input type="hidden" name="_token" value="${App.csrf}">`;
        document.body.append(f);
        f.submit();
    });
})();
</script>
@endpush
@endsection
