<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $stocks = BranchStock::query()->with(['branch', 'ingredient'])
            ->when(!$user->isCentralOwner(), fn ($query) => $query->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->get();

        return view('dashboard', [
            'branchCount' => $user->isCentralOwner() ? Branch::count() : $user->branches()->count(),
            'lowStocks' => $stocks->filter(fn ($stock) => $stock->quantity <= $stock->ingredient->reorder_point),
        ]);
    }
}
