<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_mitra_can_open_and_close_shift_with_variance(): void
    {
        $user = User::factory()->create(); $user->assignRole('owner-mitra');
        $branch = Branch::create(['name' => 'Cabang Finance', 'code' => 'FIN', 'is_active' => true]); $user->branches()->attach($branch);
        $service = app(FinanceService::class);
        $shift = $service->openShift($user, $branch->id, 100000);
        $closed = $service->closeShift($user, $shift, 95000, 'Selisih kas kecil');
        $this->assertSame('closed', $closed->status); $this->assertSame(-5000.0, (float) $closed->variance);
    }

    public function test_central_owner_cannot_request_financial_report(): void
    {
        $user = User::factory()->create(); $user->assignRole('full-owner');
        $this->expectException(ValidationException::class);
        app(FinanceService::class)->report($user, 1, '2026-01-01', '2026-01-31');
    }
}
