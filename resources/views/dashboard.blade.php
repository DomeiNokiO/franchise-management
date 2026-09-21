{% extends 'layouts.app' %}
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-4">Dashboard</h1>
    <div class="row g-3">
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Cabang dalam akses</div><div class="fs-2 fw-bold">{{ $branchCount }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-warning"><div class="card-body"><div class="text-muted">Stok perlu reorder</div><div class="fs-2 fw-bold text-warning">{{ $lowStocks->count() }}</div></div></div></div>
    </div>
    <div class="card mt-4"><div class="card-header">Peringatan stok</div><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Cabang</th><th>Bahan</th><th>Stok</th><th>ROP</th></tr></thead><tbody>@forelse($lowStocks as $stock)<tr><td>{{ $stock->branch->name }}</td><td>{{ $stock->ingredient->name }}</td><td>{{ $stock->quantity }} {{ $stock->ingredient->unit }}</td><td>{{ $stock->ingredient->reorder_point }}</td></tr>@empty<tr><td colspan="4" class="text-center py-4">Semua stok aman.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@endsection
