<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $orders = PurchaseOrder::with(['branch', 'creator'])->when(!$user->isCentralOwner(), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))->latest()->paginate(25);
        return view('purchase-orders.index', compact('orders'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->hasRole('owner-mitra'), 403);
        $branches = request()->user()->branches()->where('is_active', true)->get();
        $ingredients = Ingredient::where('is_active', true)->orderBy('name')->get();
        return view('purchase-orders.create', compact('branches', 'ingredients'));
    }

    public function store(StorePurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
    {
        $po = $service->create($request->user(), (int) $request->integer('branch_id'), $request->validated('items'), $request->validated('notes'));
        return redirect()->route('purchase-orders.show', $po)->with('success', 'PO berhasil dibuat dan menunggu persetujuan pusat.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorizeView($purchaseOrder);
        return view('purchase-orders.show', ['order' => $purchaseOrder->load('items.ingredient', 'branch', 'creator')]);
    }

    public function approve(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse { $service->approve(request()->user(), $purchaseOrder); return back()->with('success', 'PO disetujui.'); }
    public function ship(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse { $service->ship(request()->user(), $purchaseOrder); return back()->with('success', 'PO dikirim dan stok pusat berkurang.'); }
    public function receive(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse { $service->receive(request()->user(), $purchaseOrder); return back()->with('success', 'PO diterima dan stok cabang bertambah.'); }

    private function authorizeView(PurchaseOrder $po): void { abort_unless(request()->user()->isCentralOwner() || request()->user()->branches()->whereKey($po->branch_id)->exists(), 403); }
}
