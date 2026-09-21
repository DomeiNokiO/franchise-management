<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['full-owner', 'owner-mitra', 'karyawan-mitra'];
        foreach ($roles as $role) Role::findOrCreate($role, 'web');

        CompanySetting::firstOrCreate([], ['brand_name' => env('BRAND_NAME', 'Franchise QTELA'), 'currency' => 'IDR']);
        $branch = Branch::firstOrCreate(['code' => 'PUSAT'], ['name' => 'Pusat', 'is_active' => true]);

        $email = env('SEED_OWNER_EMAIL');
        $password = env('SEED_OWNER_PASSWORD');
        if ($email && $password) {
            $owner = User::firstOrCreate(['email' => $email], [
                'name' => env('SEED_OWNER_NAME', 'Full Owner'),
                'password' => Hash::make($password),
            ]);
            $owner->syncRoles(['full-owner']);
            $owner->branches()->syncWithoutDetaching([$branch->id]);
        }
    }
}
