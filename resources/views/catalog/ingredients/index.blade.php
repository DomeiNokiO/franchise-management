@extends('layouts.app')

@section('content')
@php
    $canEdit = auth()->user()->isCentralOwner();
@endphp

<div class="page-head">
    <div>
        <h1>Bahan Baku</h1>
        <p>Kelola bahan, harga pokok, dan batas reorder. {{ $canEdit ? 'Klik ikon pensil untuk edit.' : 'Mode baca.' }}</p>
    </div>
    @if($canEdit)
    <button class="btn btn-primary" id="btnAddIng"><i class="fa-solid fa-plus me-1"></i>Tambah Bahan</button>
    @endif
</div>

<div class="card">
    <div class="table-responsive">
        <table id="ingTable" class="table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama</th>
                    <th>Satuan</th>
                    <th class="text-end">Harga pokok</th>
                    <th class="text-end">Reorder</th>
                    <th>Status</th>
                    @if($canEdit)<th class="text-end">Aksi</th>@endif
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@if($canEdit)
<div class="modal fade" id="ingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="ingForm">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title" id="ingModalTitle">Tambah bahan baku</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body" id="ingModalBody">
                    <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Simpan</button>
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
        data: @json(route('ingredients.data')),
        store: @json(route('ingredients.store')),
        form: (id) => @json(route('ingredients.form')) + (id ? '/' + id : ''),
        update: (id) => @json(route('ingredients.update', ':id')).replace(':id', id),
        destroy: (id) => @json(route('ingredients.destroy', ':id')).replace(':id', id),
    };

    const columns = [
        { data: 'sku', render: d => `<span class="font-monospace" style="font-size:.8rem">${d}</span>` },
        { data: 'name', render: d => `<span class="fw-semibold">${d}</span>` },
        { data: 'unit' },
        { data: 'cost', className: 'text-end' },
        { data: 'reorder_point', className: 'text-end' },
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

    const table = App.dt('ingTable', { url: routes.data, order: [[1, 'asc']], columns });

    if (!canEdit) return;

    const modal = new bootstrap.Modal('#ingModal');
    const body = document.getElementById('ingModalBody');
    const ingForm = document.getElementById('ingForm');
    let editingId = null;

    document.getElementById('btnAddIng').addEventListener('click', () => openModal(null));

    async function openModal(id) {
        editingId = id;
        document.getElementById('ingModalTitle').textContent = id ? 'Edit bahan baku' : 'Tambah bahan baku';
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        ingForm.method = 'POST';
        ingForm.action = id ? routes.update(id) : routes.store;
        const oldMethod = ingForm.querySelector('input[name=_method]');
        if (oldMethod) oldMethod.remove();
        const res = await fetch(routes.form(id), { headers: { 'X-CSRF-TOKEN': App.csrf, 'X-Requested-With': 'fetch' } });
        if (!res.ok) { body.innerHTML = '<div class="alert alert-danger">Gagal memuat form. Hubungi admin.</div>'; return; }
        body.innerHTML = await res.text();
        if (id) {
            const m = document.createElement('input');
            m.type = 'hidden'; m.name = '_method'; m.value = 'PUT';
            ingForm.prepend(m);
        }
        modal.show();
    }

    table.table().on('click', 'button[data-edit]', e => openModal(e.currentTarget.dataset.edit));

    document.addEventListener('click', e => {
        const del = e.target.closest('button[data-del]');
        if (!del) return;
        if (!confirm('Hapus bahan ini? Hanya bisa jika belum dipakai dan stoknya nol.')) return;
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
