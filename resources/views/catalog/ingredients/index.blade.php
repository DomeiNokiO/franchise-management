@extends('layouts.app')

@section('heading', 'Bahan Baku')

@section('content')
<div class="page-toolbar">
    <div><div class="subtitle">Kelola bahan, harga pokok, dan batas reorder.</div></div>
    @if(auth()->user()->isCentralOwner())
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ingredientModal"><i class="fa-solid fa-plus me-1"></i>Tambah bahan</button>
    @endif
</div>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center"><div class="fw-semibold">Daftar bahan</div><span class="text-secondary small">{{ $ingredients->total() }} item</span></div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>SKU</th><th>Nama</th><th>Satuan</th><th class="text-end">Harga pokok</th><th class="text-end">ROP</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
    @forelse($ingredients as $ingredient)
    <tr><td class="font-monospace small">{{ $ingredient->sku }}</td><td class="fw-semibold">{{ $ingredient->name }}</td><td>{{ $ingredient->unit }}</td><td class="text-end">Rp {{ number_format($ingredient->cost, 0, ',', '.') }}</td><td class="text-end">{{ $ingredient->reorder_point }}</td><td><span class="badge rounded-pill text-bg-{{ $ingredient->is_active ? 'success' : 'secondary' }}">{{ $ingredient->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="text-end">@if(auth()->user()->isCentralOwner())<a class="btn btn-sm btn-light" href="{{ route('ingredients.edit', $ingredient) }}" title="Edit"><i class="fa-solid fa-pen"></i></a>@endif</td></tr>
    @empty<tr><td colspan="7" class="text-center py-5"><i class="fa-solid fa-box-open fs-2 text-secondary mb-2"></i><div class="fw-semibold">Belum ada bahan baku</div><div class="text-secondary small">Tambahkan bahan pertama untuk mulai mengelola stok.</div></td></tr>@endforelse
    </tbody></table></div>
    @if($ingredients->hasPages())<div class="card-footer">{{ $ingredients->links() }}</div>@endif
</div>
@if(auth()->user()->isCentralOwner())
<div class="modal fade" id="ingredientModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="{{ route('ingredients.store') }}">@csrf<div class="modal-header"><h2 class="modal-title fs-5">Tambah bahan baku</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Nama</label><input name="name" class="form-control" required></div><div class="row g-3"><div class="col-md-6"><label class="form-label">SKU</label><input name="sku" class="form-control" required></div><div class="col-md-6"><label class="form-label">Satuan</label><input name="unit" class="form-control" placeholder="kg, gram, pcs" required></div><div class="col-md-6"><label class="form-label">Harga pokok</label><input name="cost" type="number" min="0" step="0.01" class="form-control" required></div><div class="col-md-6"><label class="form-label">Reorder point</label><input name="reorder_point" type="number" min="0" step="0.001" class="form-control" required></div></div><div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ingredientActive" checked><label class="form-check-label" for="ingredientActive">Aktif</label></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>Simpan bahan</button></div></form></div></div></div>
@endif
@endsection
