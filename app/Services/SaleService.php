<?php

namespace App\Services;

use App\Models\BranchStock;
use App\Models\CashTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    /**
     * @param array<int, array{product_id:int, quantity:numeric}> $items
     */
    public function create(User $cashier, int $branchId, array $items, float $discount, float $paidAmount): Sale
    {
        $this->assertBranchAccess($cashier, $branchId);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Minimal satu produk harus dipilih.']);
        }
        if ($discount < 0 || $paidAmount < 0) {
            throw ValidationException::withMessages(['amount' => 'Nilai pembayaran dan diskon tidak boleh negatif.']);
        }

        return DB::transaction(function () use ($cashier, $branchId, $items, $discount, $paidAmount): Sale {
            $productIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id)->unique();
            $products = Product::query()->with('recipes.ingredient')->whereIn('id', $productIds)->where('is_active', true)->get()->keyBy('id');
            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages(['items' => 'Ada produk tidak aktif atau tidak ditemukan.']);
            }

            $subtotal = 0.0;
            $requirements = [];
            $normalizedItems = [];
            foreach ($items as $item) {
                $product = $products->get((int) $item['product_id']);
                $quantity = (float) $item['quantity'];
                if ($quantity <= 0) {
                    throw ValidationException::withMessages(['items' => 'Jumlah produk harus lebih besar dari nol.']);
                }
                $lineTotal = (float) $product->selling_price * $quantity;
                $subtotal += $lineTotal;
                $normalizedItems[] = compact('product', 'quantity', 'lineTotal');
                foreach ($product->recipes as $recipe) {
                    $requirements[$recipe->ingredient_id] = ($requirements[$recipe->ingredient_id] ?? 0) + ((float) $recipe->quantity * $quantity);
                }
            }

            $total = $subtotal - $discount;
            if ($total < 0 || $paidAmount < $total) {
                throw ValidationException::withMessages(['paid_amount' => 'Pembayaran kurang dari total transaksi.']);
            }

            $stocks = BranchStock::query()->where('branch_id', $branchId)->whereIn('ingredient_id', array_keys($requirements))->lockForUpdate()->get()->keyBy('ingredient_id');
            foreach ($requirements as $ingredientId => $required) {
                $stock = $stocks->get($ingredientId);
                if (!$stock || (float) $stock->quantity < $required) {
                    $name = $stock?->ingredient?->name ?? "bahan #$ingredientId";
                    throw ValidationException::withMessages(['items' => "Stok $name tidak mencukupi."]);
                }
            }

            $sale = Sale::create([
                'branch_id' => $branchId, 'cashier_id' => $cashier->id,
                'receipt_number' => 'POS-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3))),
                'status' => 'completed', 'subtotal' => $subtotal, 'discount' => $discount,
                'total' => $total, 'paid_amount' => $paidAmount, 'change_amount' => $paidAmount - $total,
                'sold_at' => now(),
            ]);
            foreach ($normalizedItems as $item) {
                $sale->items()->create(['product_id' => $item['product']->id, 'quantity' => $item['quantity'], 'unit_price' => $item['product']->selling_price, 'line_total' => $item['lineTotal']]);
            }
            foreach ($requirements as $ingredientId => $required) {
                $stock = $stocks->get($ingredientId);
                $stock->quantity -= $required;
                $stock->save();
                StockMovement::create(['branch_id' => $branchId, 'ingredient_id' => $ingredientId, 'user_id' => $cashier->id, 'quantity' => -$required, 'balance_after' => $stock->quantity, 'reason' => 'pos_sale', 'reference_type' => Sale::class, 'reference_id' => $sale->id]);
            }
            CashTransaction::create(['branch_id' => $branchId, 'user_id' => $cashier->id, 'type' => 'income', 'category' => 'penjualan_pos', 'amount' => $total, 'description' => "Penjualan {$sale->receipt_number}", 'occurred_at' => now()]);
            return $sale->load('items.product');
        });
    }

    private function assertBranchAccess(User $user, int $branchId): void
    {
        if (!$user->branches()->whereKey($branchId)->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'Anda tidak memiliki akses ke cabang ini.']);
        }
    }
}
