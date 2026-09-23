@extends('layouts.app')

@section('heading', 'Produk dan Resep')

@section('content')
<div class="page-toolbar"><div><div class="subtitle">Katalog produk, harga jual, dan komposisi resep.</div></div>@if(auth()->user()->isCentralOwner())<a class="btn btn-primary" href="{{ route('products.create') }}"><i class="fa-solid fa-plus me-1"></i>Tambah produk</a>@endif</div>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center"><div class="fw-semibold">Daftar produk</div><span class="text-secondary small">{{ $products->total() }} item</span></div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>SKU</th><th>Produk</th><th class="text-end">Harga jual</th><th>Resep</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
    @forelse($products as $product)
    <tr><td class="font-monospace small">{{ $product->sku }}</td><td class="fw-semibold">{{ $product->name }}</td><td class="text-end">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</td><td class="small text-secondary">@forelse($product->recipes as $recipe){{ $recipe->ingredient?->name ?? 'Bahan dihapus' }} ({{ $recipe->quantity }} {{ $recipe->ingredient?->unit ?? '' }})@if(!$loop->last), @endif @empty Belum ada resep @endforelse</td><td><span class="badge rounded-pill text-bg-{{ $product->is_active ? 'success' : 'secondary' }}">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td class="text-end">@if(auth()->user()->isCentralOwner())<a class="btn btn-sm btn-light" href="{{ route('products.edit', $product) }}" title="Edit"><i class="fa-solid fa-pen"></i></a>@endif</td></tr>
    @empty<tr><td colspan="6" class="text-center py-5"><i class="fa-solid fa-tags fs-2 text-secondary mb-2"></i><div class="fw-semibold">Belum ada produk</div><div class="text-secondary small">Tambahkan produk dan resep untuk mengaktifkan POS.</div></td></tr>@endforelse
    </tbody></table></div>
    @if($products->hasPages())<div class="card-footer">{{ $products->links() }}</div>@endif
</div>
@endsection
