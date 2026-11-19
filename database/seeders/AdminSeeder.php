<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Super Admin (Platform Owner — KasirLoka)
        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'tenant_id' => null,
                'name' => 'Super Admin KasirLoka',
                'email' => 'superadmin@kasirloka.id',
                'password' => Hash::make('superadmin123'),
                'role' => 'super_admin',
            ]
        );

        $tenantId = TenantContext::id()
            ?? Tenant::where('slug', 'teras-kota')->value('id')
            ?? Tenant::orderBy('id')->value('id');

        // 2. Founding Tenant Admin & Cashier
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'tenant_id' => $tenantId,
                'name' => 'Admin Teras Kota Berlian Makmur',
                'email' => 'admin@teraskota.local',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['username' => 'kasir'],
            [
                'tenant_id' => $tenantId,
                'name' => 'Kasir POS Teras Kota',
                'email' => 'kasir@teraskota.local',
                'password' => Hash::make('kasir123'),
                'role' => 'kasir',
            ]
        );
    }
}
