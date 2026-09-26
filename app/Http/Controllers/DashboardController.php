<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashShift;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $isCentral = $user->isCentralOwner();

        // --- Stok ---
        $stocks = BranchStock::query()->with(['branch', 'ingredient'])
            ->when(!$isCentral, fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->where('quantity', '>', 0)
            ->get();

        $lowStocks = $stocks->filter(fn ($s) => $s->quantity <= $s->ingredient->reorder_point)
            ->sortBy(fn ($s) => $s->quantity / max(0.001, (float) $s->ingredient->reorder_point));

        // --- Statistik ringkas (7 hari terakhir) ---
        $since = now()->subDays(7)->startOfDay();

        $salesQuery = Sale::where('status', 'completed')->where('sold_at', '>=', $since)
            ->when(!$isCentral, fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')));

        $salesCount = (clone $salesQuery)->count();
        $salesTotal = (float) (clone $salesQuery)->sum('total');

        $poQuery = PurchaseOrder::query()->when(!$isCentral, fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')));
        $pendingPOs = (clone $poQuery)->where('status', 'pending')->count();
        $shippedPOs = (clone $poQuery)->where('status', 'shipped')->count();

        // --- Shift aktif (untuk pengguna cabang) ---
        $activeShift = null;
        if (!$isCentral) {
            $branchId = $user->activeBranchId();
            $activeShift = $branchId
                ? CashShift::where('branch_id', $branchId)->where('status', 'open')->first()
                : null;
        }

        $branches = $isCentral
            ? Branch::where('is_active', true)->orderBy('name')->get()
            : $user->branches()->where('is_active', true)->orderBy('name')->get();

        $activeBranchId = $user->activeBranchId();
        $activeBranch = $activeBranchId ? $branches->firstWhere('id', $activeBranchId) : null;

        return view('dashboard', compact(
            'isCentral', 'stocks', 'lowStocks', 'salesCount', 'salesTotal',
            'pendingPOs', 'shippedPOs', 'activeShift', 'branches', 'activeBranchId', 'activeBranch'
        ));
    }
}
