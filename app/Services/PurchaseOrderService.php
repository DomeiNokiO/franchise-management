<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function create(User $user, int $branchId, array $items, ?string $notes = null): PurchaseOrder
    {
        $this->assertRole($user, 'owner-mitra');
        $this->assertBranchMember($user, $branchId);
        if (!$items) throw ValidationException::withMessages(['items' => 'Minimal satu bahan harus dipilih.']);

        return DB::transaction(function () use ($user, $branchId, $items, $notes) {
            $ids = collect($items)->pluck('ingredient_id')->map(fn ($id) => (int) $id)->unique();
            $ingredients = Ingredient::whereIn('id', $ids)->where('is_active', true)->get()->keyBy('id');
            if ($ingredients->count() !== $ids->count()) throw ValidationException::withMessages(['items' => 'Ada bahan tidak aktif atau tidak ditemukan.']);
            $po = PurchaseOrder::create(['branch_id' => $branchId, 'created_by' => $user->id, 'status' => 'pending', 'ordered_at' => now(), 'notes' => $notes]);
            foreach ($items as $item) {
                $quantity = (float) $item['quantity'];
                if ($quantity <= 0) throw ValidationException::withMessages(['items' => 'Jumlah harus lebih besar dari nol.']);
                $ingredient = $ingredients->get((int) $item['ingredient_id']);
                $po->items()->create(['ingredient_id' => $ingredient->id, 'quantity' => $quantity, 'unit_cost' => $ingredient->cost]);
            }
            return $po->load('items.ingredient', 'branch');
        });
    }

    public function approve(User $user, PurchaseOrder $po): PurchaseOrder
    {
        $this->assertRole($user, 'full-owner');
        $this->assertStatus($po, 'pending');
        return DB::transaction(function () use ($user, $po) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($po->id);
            $this->assertStatus($po, 'pending');
            $po->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            return $po->fresh('items.ingredient', 'branch');
        });
    }

    public function ship(User $user, PurchaseOrder $po): PurchaseOrder
    {
        $this->assertRole($user, 'full-owner');
        $this->assertStatus($po, 'approved');
        return DB::transaction(function () use ($user, $po) {
            $po = PurchaseOrder::with('items.ingredient')->lockForUpdate()->findOrFail($po->id);
            $this->assertStatus($po, 'approved');
            $central = Branch::where('code', 'PUSAT')->firstOrFail();
            foreach ($po->items as $item) {
                $stock = BranchStock::where(['branch_id' => $central->id, 'ingredient_id' => $item->ingredient_id])->lockForUpdate()->first();
                if (!$stock || (float) $stock->quantity < (float) $item->quantity) throw ValidationException::withMessages(['stock' => "Stok pusat {$item->ingredient->name} tidak mencukupi."]);
                $stock->decrement('quantity', $item->quantity);
                $stock->refresh();
                StockMovement::create(['branch_id' => $central->id, 'ingredient_id' => $item->ingredient_id, 'user_id' => $user->id, 'quantity' => -$item->quantity, 'balance_after' => $stock->quantity, 'reason' => 'po_shipment', 'reference_type' => PurchaseOrder::class, 'reference_id' => $po->id]);
            }
            $po->update(['status' => 'shipped']);
            return $po->fresh('items.ingredient', 'branch');
        });
    }

    public function receive(User $user, PurchaseOrder $po): PurchaseOrder
    {
        $this->assertStatus($po, 'shipped');
        $this->assertBranchMember($user, $po->branch_id);
        return DB::transaction(function () use ($user, $po) {
            $po = PurchaseOrder::with('items.ingredient')->lockForUpdate()->findOrFail($po->id);
            $this->assertStatus($po, 'shipped');
            foreach ($po->items as $item) {
                $stock = BranchStock::firstOrCreate(['branch_id' => $po->branch_id, 'ingredient_id' => $item->ingredient_id], ['quantity' => 0, 'average_cost' => $item->unit_cost]);
                $stock = BranchStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
                $old = (float) $stock->quantity;
                $stock->quantity = $old + (float) $item->quantity;
                $stock->average_cost = $item->unit_cost;
                $stock->save();
                StockMovement::create(['branch_id' => $po->branch_id, 'ingredient_id' => $item->ingredient_id, 'user_id' => $user->id, 'quantity' => $item->quantity, 'balance_after' => $stock->quantity, 'reason' => 'po_receipt', 'reference_type' => PurchaseOrder::class, 'reference_id' => $po->id]);
            }
            $po->update(['status' => 'received', 'received_at' => now()]);
            return $po->fresh('items.ingredient', 'branch');
        });
    }

    private function assertRole(User $user, string $role): void { if (!$user->hasRole($role)) throw ValidationException::withMessages(['access' => 'Role tidak diizinkan untuk aksi ini.']); }
    private function assertBranchMember(User $user, int $branchId): void { if (!$user->branches()->whereKey($branchId)->exists()) throw ValidationException::withMessages(['branch_id' => 'Akses cabang tidak diizinkan.']); }
    private function assertStatus(PurchaseOrder $po, string $status): void { if ($po->status !== $status) throw ValidationException::withMessages(['status' => "PO harus berstatus $status."]); }
}
