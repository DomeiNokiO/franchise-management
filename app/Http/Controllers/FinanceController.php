<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashShift;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request, FinanceService $finance): View
    {
        abort_if($request->user()->isCentralOwner() || $request->user()->hasRole('karyawan-mitra'), 403);
        $branches = $request->user()->branches()->where('is_active', true)->get();
        $branchId = (int) ($request->integer('branch_id') ?: $branches->first()?->id);
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $report = $branchId ? $finance->report($request->user(), $branchId, $from, $to) : null;
        $openShifts = CashShift::whereIn('branch_id', $branches->pluck('id'))->where('status', 'open')->with(['branch', 'opener'])->get();
        $closedShifts = CashShift::whereIn('branch_id', $branches->pluck('id'))->where('status', 'closed')
            ->with(['branch', 'opener', 'closer'])->orderByDesc('closed_at')->take(10)->get();
        return view('finance.index', compact('branches', 'branchId', 'from', 'to', 'report', 'openShifts', 'closedShifts'));
    }

    public function open(Request $request, FinanceService $finance): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id'], 'opening_balance' => ['required', 'numeric', 'min:0']]);
        $finance->openShift($request->user(), (int) $data['branch_id'], (float) $data['opening_balance']);
        return back()->with('success', 'Shift berhasil dibuka.');
    }

    public function close(Request $request, CashShift $cashShift, FinanceService $finance): RedirectResponse
    {
        $data = $request->validate(['closing_balance' => ['required', 'numeric', 'min:0'], 'closing_note' => ['nullable', 'string', 'max:2000']]);
        $finance->closeShift($request->user(), $cashShift, (float) $data['closing_balance'], $data['closing_note'] ?? null);
        return back()->with('success', 'Shift berhasil ditutup.');
    }

    public function transaction(Request $request, FinanceService $finance): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id'], 'type' => ['required', 'in:income,expense'], 'category' => ['required', 'string', 'max:80'], 'amount' => ['required', 'numeric', 'gt:0'], 'description' => ['nullable', 'string', 'max:255']]);
        $finance->record($request->user(), (int) $data['branch_id'], $data['type'], $data['category'], (float) $data['amount'], $data['description'] ?? null);
        return back()->with('success', 'Transaksi kas berhasil dicatat.');
    }
}
