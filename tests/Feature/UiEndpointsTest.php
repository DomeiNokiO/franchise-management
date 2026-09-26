<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenants(): array
    {
        $central = Branch::create(['name' => 'Pusat', 'code' => 'PUSAT', 'is_active' => true]);
        $cabangA = Branch::create(['name' => 'Cabang A', 'code' => 'A', 'is_active' => true]);
        $cabangB = Branch::create(['name' => 'Cabang B', 'code' => 'B', 'is_active' => true]);
        return [$central, $cabangA, $cabangB];
    }

    public function test_sales_data_returns_datatables_json_for_branch_user(): void
    {
        [, $a, $b] = $this->setupTenants();
        $user = User::factory()->create();
        $user->assignRole('owner-mitra');
        $user->branches()->attach([$a]);

        $product = Product::create(['name' => 'Nasi', 'sku' => 'P1', 'selling_price' => 15000, 'is_active' => true]);
        \App\Models\Sale::create([
            'branch_id' => $a->id, 'cashier_id' => $user->id, 'receipt_number' => 'POS-A-1',
            'status' => 'completed', 'subtotal' => 15000, 'discount' => 0,
            'total' => 15000, 'paid_amount' => 15000, 'change_amount' => 0, 'sold_at' => now(),
        ]);
        \App\Models\Sale::create([
            'branch_id' => $b->id, 'cashier_id' => $user->id, 'receipt_number' => 'POS-B-1',
            'status' => 'completed', 'subtotal' => 99999, 'discount' => 0,
            'total' => 99999, 'paid_amount' => 99999, 'change_amount' => 0, 'sold_at' => now(),
        ]);

        $res = $this->actingAs($user)->getJson(route('sales.data'));
        $res->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        $this->assertSame(1, $res->json('recordsFiltered'));
        $this->assertSame('POS-A-1', $res->json('data.0.receipt_number'));
    }

    public function test_sales_data_search_filters(): void
    {
        [, $a] = $this->setupTenants();
        $user = User::factory()->create();
        $user->assignRole('owner-mitra');
        $user->branches()->attach([$a]);
        $user2 = User::factory()->create();

        \App\Models\Sale::create([
            'branch_id' => $a->id, 'cashier_id' => $user2->id, 'receipt_number' => 'POS-KASIR-ALPHA',
            'status' => 'completed', 'subtotal' => 5000, 'discount' => 0,
            'total' => 5000, 'paid_amount' => 5000, 'change_amount' => 0, 'sold_at' => now(),
        ]);

        $res = $this->actingAs($user)->getJson(route('sales.data') . '?search[value]=KASIR');
        $res->assertOk();
        $this->assertSame(1, $res->json('recordsFiltered'));
    }

    public function test_store_po_from_foreign_branch_is_blocked(): void
    {
        [, $a, $b] = $this->setupTenants();
        $user = User::factory()->create();
        $user->assignRole('owner-mitra');
        $user->branches()->attach([$a]);
        $ingredient = Ingredient::create(['name' => 'Tepung', 'sku' => 'T1', 'unit' => 'kg', 'cost' => 10000, 'reorder_point' => 0, 'is_active' => true]);

        // Mencoba membuat PO untuk cabang B (bukan miliknya)
        $res = $this->actingAs($user)->post(route('purchase-orders.store'), [
            'branch_id' => $b->id,
            'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 2]],
        ]);

        $res->assertForbidden();
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_store_sale_from_foreign_branch_is_blocked(): void
    {
        [, $a, $b] = $this->setupTenants();
        $user = User::factory()->create();
        $user->assignRole('owner-mitra');
        $user->branches()->attach([$a]);
        $product = Product::create(['name' => 'Nasi', 'sku' => 'P1', 'selling_price' => 10000, 'is_active' => true]);

        $res = $this->actingAs($user)->post(route('sales.store'), [
            'branch_id' => $b->id,
            'paid_amount' => 10000,
            'discount' => 0,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        // Service melempar ValidationException saat tidak ada akses → 422, bukan 201
        $this->assertNotSame(201, $res->status());
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_full_owner_cannot_open_branch_finance(): void
    {
        $this->setupTenants();
        $owner = User::factory()->create();
        $owner->assignRole('full-owner');

        $this->actingAs($owner)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($owner)->post(route('finance.shifts.open'), [
            'branch_id' => 1, 'opening_balance' => 100000,
        ])->assertForbidden();
    }

    public function test_po_reject_flow_and_cannot_receive_rejected(): void
    {
        $this->setupTenants();
        $central = Branch::where('code', 'PUSAT')->first();
        $cabang = Branch::where('code', 'A')->first();
        $owner = User::factory()->create();
        $owner->assignRole('full-owner');
        $mitra = User::factory()->create();
        $mitra->assignRole('owner-mitra');
        $mitra->branches()->attach([$cabang]);
        $ingredient = Ingredient::create(['name' => 'Gula', 'sku' => 'G1', 'unit' => 'kg', 'cost' => 15000, 'reorder_point' => 0, 'is_active' => true]);
        BranchStock::create(['branch_id' => $central->id, 'ingredient_id' => $ingredient->id, 'quantity' => 10, 'average_cost' => 15000]);

        $service = app(\App\Services\PurchaseOrderService::class);
        $order = $service->create($mitra, $cabang->id, [['ingredient_id' => $ingredient->id, 'quantity' => 2]]);
        $service->reject($owner, $order->fresh());
        $this->assertSame('rejected', $order->fresh()->status);

        // PO rejected tidak boleh diterima
        try {
            $service->receive($mitra, $order->fresh());
            $this->fail('PO rejected seharusnya tidak bisa diterima');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertDatabaseCount('branch_stocks', 1); // stok cabang tidak bertambah
        }
    }

    public function test_active_branch_switch_updates_session_and_blocks_outsider(): void
    {
        [, $a, $b] = $this->setupTenants();
        $user = User::factory()->create();
        $user->assignRole('owner-mitra');
        $user->branches()->attach([$a]);

        // Branch B bukan miliknya → ditolak
        $this->actingAs($user)->post(route('active-branch.store'), ['branch_id' => $b->id])->assertForbidden();

        // Branch A boleh → tersimpan di session
        $this->actingAs($user)->post(route('active-branch.store'), ['branch_id' => $a->id])->assertRedirect();
        $this->assertSame($a->id, session('active_branch_id'));
    }

    public function test_ingredient_form_fragment_requires_full_owner(): void
    {
        $this->setupTenants();
        $mitra = User::factory()->create();
        $mitra->assignRole('owner-mitra');

        $this->actingAs($mitra)->get(route('ingredients.form'))->assertForbidden();
    }
}
