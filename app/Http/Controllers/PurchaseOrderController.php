<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        return view('purchase-orders.index');
    }

    /** Endpoint serverside DataTables untuk daftar PO. */
    public function data(): JsonResponse
    {
        $user = request()->user();

        return DataTables::process(
            PurchaseOrder::query()->with(['branch', 'creator', 'items'])->withCount('items'),
            ['ordered_at', 'created_at', 'id'],
            function ($query, string $search) use ($user) {
                if (!$user->isCentralOwner()) {
                    $query->whereIn('branch_id', $user->branches()->select('branches.id'));
                }
                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->whereHas('branch', fn ($b) => $b->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('creator', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                            ->orWhere('notes', 'like', "%{$search}%");
                    });
                }
                return $query;
            },
            function (PurchaseOrder $po) {
                $total = (float) $po->items->sum(fn ($i) => $i->quantity * $i->unit_cost);
                return [
                    'id' => $po->id,
                    'branch' => $po->branch?->name ?? '—',
                    'creator' => $po->creator?->name ?? '—',
                    'status' => $po->status,
                    'items_count' => $po->items_count,
                    'total' => 'Rp '.number_format($total, 0, ',', '.'),
                    'ordered_at' => $po->ordered_at?->format('d/m/Y H:i') ?? '—',
                    'url' => route('purchase-orders.show', $po),
                ];
            }
        );
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
        return view('purchase-orders.show', ['order' => $purchaseOrder->load('items.ingredient', 'branch', 'creator', 'approver')]);
    }

    public function approve(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $service->approve(request()->user(), $purchaseOrder);
        return back()->with('success', 'PO disetujui.');
    }

    public function reject(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $service->reject(request()->user(), $purchaseOrder);
        return back()->with('success', 'PO ditolak.');
    }

    public function ship(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $service->ship(request()->user(), $purchaseOrder);
        return back()->with('success', 'PO dikirim dan stok pusat berkurang.');
    }

    public function receive(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $service->receive(request()->user(), $purchaseOrder);
        return back()->with('success', 'PO diterima dan stok cabang bertambah.');
    }

    private function authorizeView(PurchaseOrder $po): void
    {
        abort_unless(request()->user()->isCentralOwner() || request()->user()->branches()->whereKey($po->branch_id)->exists(), 403);
    }
}
