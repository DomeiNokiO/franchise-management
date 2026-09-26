@extends('layouts.app')

@section('content')
<div class="page-head">
    <div>
        <h1>Purchase Order</h1>
        <p>Alur: <span class="badge badge-soft">pending</span> → <span class="badge badge-soft">approved</span> → <span class="badge badge-soft">shipped</span> → <span class="badge badge-soft">received</span> — {{ auth()->user()->isCentralOwner() ? 'seluruh cabang' : 'cabang Anda' }}.</p>
    </div>
    @if(auth()->user()->isBranchOwner())
    <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Buat PO</a>
    @endif
</div>

<div class="card">
    <div class="table-responsive">
        <table id="poTable" class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cabang</th>
                    <th>Pembuat</th>
                    <th>Status</th>
                    <th class="text-end">Item</th>
                    <th class="text-end">Estimasi</th>
                    <th>Dibuat</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
const statusBadge = s => {
    const map = {
        pending:  ['text-bg-warning', 'PENDING'],
        approved: ['text-bg-info', 'APPROVED'],
        shipped:  ['text-bg-primary', 'SHIPPED'],
        received: ['text-bg-success', 'RECEIVED'],
        rejected: ['text-bg-danger', 'REJECTED']
    };
    const [cls, label] = map[s] || ['text-bg-secondary', s.toUpperCase()];
    return `<span class="badge ${cls}">${label}</span>`;
};
App.dt('poTable', {
    url: @json(route('purchase-orders.data')),
    order: [[6, 'desc']],
    columns: [
        { data: 'id', render: d => `#${d}` },
        { data: 'branch' },
        { data: 'creator' },
        { data: 'status', orderable: false, render: statusBadge },
        { data: 'items_count', className: 'text-end' },
        { data: 'total', className: 'text-end' },
        { data: 'ordered_at' },
        {
            data: 'url', orderable: false, className: 'text-end',
            render: d => `<a href="${d}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eye me-1"></i>Detail</a>`
        }
    ]
});
</script>
@endpush
@endsection
