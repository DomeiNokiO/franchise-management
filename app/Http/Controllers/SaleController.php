<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $sales = Sale::query()->with(['branch', 'cashier'])->when(!$user->isCentralOwner(), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))->latest('sold_at')->paginate(25);
        return view('sales.index', compact('sales'));
    }

    public function create(): View
    {
        $user = request()->user();
        $branches = $user->branches()->where('is_active', true)->orderBy('name')->get();
        $products = Product::query()->where('is_active', true)->with('recipes.ingredient')->orderBy('name')->get();
        return view('sales.create', compact('branches', 'products'));
    }

    public function store(StoreSaleRequest $request, SaleService $service): RedirectResponse
    {
        $sale = $service->create($request->user(), (int) $request->integer('branch_id'), $request->validated('items'), (float) ($request->validated('discount') ?? 0), (float) $request->validated('paid_amount'));
        return redirect()->route('sales.show', $sale)->with('success', "Transaksi {$sale->receipt_number} berhasil disimpan.");
    }

    public function show(Sale $sale): View
    {
        abort_unless(request()->user()->isCentralOwner() || request()->user()->branches()->whereKey($sale->branch_id)->exists(), 403);
        return view('sales.show', ['sale' => $sale->load('items.product', 'branch', 'cashier')]);
    }
}
