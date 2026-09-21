<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_po_can_be_approved_shipped_and_received(): void
    {
        $owner = User::factory()->create(); $owner->assignRole('full-owner');
        $mitra = User::factory()->create(); $mitra->assignRole('owner-mitra');
        $central = Branch::create(['name' => 'Pusat', 'code' => 'PUSAT', 'is_active' => true]);
        $branch = Branch::create(['name' => 'Cabang A', 'code' => 'A', 'is_active' => true]);
        $mitra->branches()->attach($branch); $ingredient = Ingredient::create(['name' => 'Tepung', 'sku' => 'T-1', 'unit' => 'kg', 'cost' => 10000, 'reorder_point' => 2, 'is_active' => true]);
        BranchStock::create(['branch_id' => $central->id, 'ingredient_id' => $ingredient->id, 'quantity' => 20, 'average_cost' => 10000]);
        $service = app(PurchaseOrderService::class);
        $po = $service->create($mitra, $branch->id, [['ingredient_id' => $ingredient->id, 'quantity' => 5]]);
        $this->assertSame('pending', $po->status);
        $service->approve($owner, $po); $service->ship($owner, $po->fresh()); $service->receive($mitra, $po->fresh());
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'status' => 'received']);
        $this->assertSame(15.0, (float) BranchStock::where('branch_id', $central->id)->first()->quantity);
        $this->assertSame(5.0, (float) BranchStock::where('branch_id', $branch->id)->first()->quantity);
    }

    public function test_mitra_cannot_approve_a_purchase_order(): void
    {
        $mitra = User::factory()->create(); $mitra->assignRole('owner-mitra');
        $this->expectException(ValidationException::class);
        app(PurchaseOrderService::class)->approve($mitra, new PurchaseOrder(['status' => 'pending']));
    }
}
