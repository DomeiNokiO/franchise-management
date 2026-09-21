<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashShift;
use App\Models\CashTransaction;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public function openShift(User $user, int $branchId, float $openingBalance): CashShift
    {
        $this->assertBranch($user, $branchId);
        if ($openingBalance < 0) throw ValidationException::withMessages(['opening_balance' => 'Saldo awal tidak boleh negatif.']);
        return DB::transaction(function () use ($user, $branchId, $openingBalance) {
            if (CashShift::where('branch_id', $branchId)->where('status', 'open')->lockForUpdate()->exists()) throw ValidationException::withMessages(['shift' => 'Masih ada shift terbuka di cabang ini.']);
            return CashShift::create(['branch_id' => $branchId, 'opened_by' => $user->id, 'status' => 'open', 'opening_balance' => $openingBalance, 'opened_at' => now()]);
        });
    }

    public function closeShift(User $user, CashShift $shift, float $closingBalance, ?string $note): CashShift
    {
        $this->assertBranch($user, $shift->branch_id);
        if ($closingBalance < 0) throw ValidationException::withMessages(['closing_balance' => 'Saldo akhir tidak boleh negatif.']);
        return DB::transaction(function () use ($user, $shift, $closingBalance, $note) {
            $shift = CashShift::with('transactions')->lockForUpdate()->findOrFail($shift->id);
            if ($shift->status !== 'open') throw ValidationException::withMessages(['shift' => 'Shift sudah ditutup.']);
            $income = $shift->transactions->where('type', 'income')->sum('amount');
            $expense = $shift->transactions->where('type', 'expense')->sum('amount');
            $expected = (float) $shift->opening_balance + (float) $income - (float) $expense;
            $shift->update(['status' => 'closed', 'closed_by' => $user->id, 'closing_balance' => $closingBalance, 'expected_balance' => $expected, 'variance' => $closingBalance - $expected, 'closed_at' => now(), 'closing_note' => $note]);
            return $shift;
        });
    }

    public function record(User $user, int $branchId, string $type, string $category, float $amount, ?string $description): CashTransaction
    {
        $this->assertBranch($user, $branchId);
        if ($amount <= 0) throw ValidationException::withMessages(['amount' => 'Nominal harus lebih besar dari nol.']);
        return DB::transaction(function () use ($user, $branchId, $type, $category, $amount, $description) {
            $shift = CashShift::where('branch_id', $branchId)->where('status', 'open')->lockForUpdate()->first();
            if (!$shift) throw ValidationException::withMessages(['shift' => 'Buka shift terlebih dahulu.']);
            return CashTransaction::create(['branch_id' => $branchId, 'user_id' => $user->id, 'cash_shift_id' => $shift->id, 'type' => $type, 'category' => $category, 'amount' => $amount, 'description' => $description, 'occurred_at' => now()]);
        });
    }

    public function report(User $user, int $branchId, string $from, string $to): array
    {
        if ($user->isCentralOwner() || !$user->hasRole('owner-mitra')) {
            throw ValidationException::withMessages(['access' => 'Laporan keuangan hanya untuk Owner Mitra.']);
        }
        $this->assertBranch($user, $branchId);
        $sales = Sale::where('branch_id', $branchId)->whereBetween('sold_at', [$from.' 00:00:00', $to.' 23:59:59'])->where('status', 'completed');
        $cash = CashTransaction::where('branch_id', $branchId)->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59']);
        return ['sales_total' => (float) $sales->sum('total'), 'sales_count' => $sales->count(), 'cash_income' => (float) (clone $cash)->where('type', 'income')->sum('amount'), 'cash_expense' => (float) (clone $cash)->where('type', 'expense')->sum('amount'), 'expenses' => (clone $cash)->where('type', 'expense')->orderByDesc('occurred_at')->get(), 'from' => $from, 'to' => $to];
    }

    private function assertBranch(User $user, int $branchId): void { if (!$user->branches()->whereKey($branchId)->exists()) throw ValidationException::withMessages(['branch_id' => 'Akses cabang tidak diizinkan.']); }
}
