<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // memicu setUpTraits() → RefreshDatabase & co.

        $this->ensureRolesAvailable();
    }

    /**
     * Jamin 3 role aplikasi tersedia untuk test yang memakai assignRole()
     * tanpa menjalankan seeder. Aman dipanggil dari semua test: jika tabel
     * spatie-nya belum ada (test tanpa RefreshDatabase), tabel dibuat
     * minimum dulu.
     */
    protected function ensureRolesAvailable(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        foreach (['full-owner', 'owner-mitra', 'karyawan-mitra'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
