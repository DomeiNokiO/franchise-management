<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_authenticated_owner_can_render_core_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('full-owner');
        $branch = Branch::create(['name' => 'Pusat', 'code' => 'PUSAT', 'is_active' => true]);
        $user->branches()->attach($branch);
        $this->actingAs($user);

        $this->get('/dashboard')->assertOk()->assertSee('Dashboard');
        $this->get('/products')->assertOk()->assertSee('Produk');
        $this->get('/ingredients')->assertOk()->assertSee('Bahan');
        $this->get('/purchase-orders')->assertOk()->assertSee('Purchase Order');
        $this->get('/sales')->assertOk()->assertSee('Penjualan');
    }
}
