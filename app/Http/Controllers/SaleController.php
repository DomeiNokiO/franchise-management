<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(): View
    {
        return view('sales.index');
    }

    /** Endpoint serverside DataTables untuk daftar penjualan. */
    public function data(): JsonResponse
    {
        $user = request()->user();

        return DataTables::process(
            Sale::query()->with(['branch', 'cashier']),
            ['sold_at', 'total', 'id'],
            function ($query, string $search) use ($user) {
                if (!$user->isCentralOwner()) {
                    $query->whereIn('branch_id', $user->branches()->select('branches.id'));
                }
                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('receipt_number', 'like', "%{$search}%")
                            ->orWhereHas('branch', fn ($b) => $b->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('cashier', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                    });
                }
                return $query;
            },
            fn (Sale $sale) => [
                'receipt_number' => $sale->receipt_number,
                'branch' => $sale->branch?->name ?? '—',
                'cashier' => $sale->cashier?->name ?? '—',
                'sold_at' => $sale->sold_at->format('d/m/Y H:i'),
                'total' => 'Rp '.number_format((float) $sale->total, 0, ',', '.'),
                'url' => route('sales.show', $sale),
            ]
        );
    }

    public function create(): View
    {
        $user = request()->user();
        $branches = $user->branches()->where('is_active', true)->orderBy('name')->get();
        $products = Product::query()->where('is_active', true)->orderBy('name')->get();
        $branchId = $user->activeBranchId();

        return view('sales.create', compact('branches', 'products', 'branchId'));
    }

    public function store(StoreSaleRequest $request, SaleService $service): RedirectResponse
    {
        $sale = $service->create(
            $request->user(),
            (int) $request->integer('branch_id'),
            $request->validated('items'),
            (float) ($request->validated('discount') ?? 0),
            (float) $request->validated('paid_amount'),
        );
        return redirect()->route('sales.show', $sale)->with('success', "Transaksi {$sale->receipt_number} berhasil disimpan.");
    }

    public function show(Sale $sale): View
    {
        $user = request()->user();
        abort_unless($user->isCentralOwner() || $user->branches()->whereKey($sale->branch_id)->exists(), 403);

        $sale->load('items.product', 'branch', 'cashier');
        return view('sales.show', ['sale' => $sale]);
    }
}
