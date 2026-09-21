<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashTransaction;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_deducts_recipe_stock_and_records_cash(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['name' => 'Cabang A', 'code' => 'A', 'is_active' => true]);
        $user->branches()->attach($branch);
        $ingredient = Ingredient::create(['name' => 'Bahan', 'sku' => 'B-1', 'unit' => 'gram', 'cost' => 100, 'reorder_point' => 1, 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 10, 'average_cost' => 100]);
        $product = Product::create(['name' => 'Produk', 'sku' => 'P-1', 'selling_price' => 5000, 'is_active' => true]);
        Recipe::create(['product_id' => $product->id, 'ingredient_id' => $ingredient->id, 'quantity' => 2]);

        $sale = app(SaleService::class)->create($user, $branch->id, [['product_id' => $product->id, 'quantity' => 3]], 0, 15000);

        $this->assertSame(15000.0, (float) $sale->total);
        $this->assertSame(4.0, (float) BranchStock::first()->quantity);
        $this->assertDatabaseHas('cash_transactions', ['branch_id' => $branch->id, 'type' => 'income', 'amount' => 15000]);
        $this->assertDatabaseHas('stock_movements', ['reason' => 'pos_sale', 'quantity' => -6]);
    }

    public function test_sale_is_rejected_when_recipe_stock_is_insufficient(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['name' => 'Cabang A', 'code' => 'A', 'is_active' => true]);
        $user->branches()->attach($branch);
        $ingredient = Ingredient::create(['name' => 'Bahan', 'sku' => 'B-2', 'unit' => 'gram', 'cost' => 100, 'reorder_point' => 1, 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 1, 'average_cost' => 100]);
        $product = Product::create(['name' => 'Produk', 'sku' => 'P-2', 'selling_price' => 5000, 'is_active' => true]);
        Recipe::create(['product_id' => $product->id, 'ingredient_id' => $ingredient->id, 'quantity' => 2]);

        $this->expectException(ValidationException::class);
        app(SaleService::class)->create($user, $branch->id, [['product_id' => $product->id, 'quantity' => 1]], 0, 5000);
        $this->assertDatabaseCount('sales', 0);
    }
}
