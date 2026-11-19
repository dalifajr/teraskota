<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ensure default founding tenant (Teras Kota) exists
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'teras-kota'],
            [
                'name' => 'Teras Kota Berlian Makmur',
                'business_type' => 'Kedai Minuman & Kuliner',
                'address' => 'Berlian Makmur',
                'receipt_footer' => 'Terima Kasih Atas Kunjungan Anda',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
                'is_lifetime' => true,
            ]
        );

        // Ensure founding tenant has an active lifetime Pro subscription
        $proPlan = Plan::where('slug', 'pro')->first();
        if ($proPlan && !$tenant->activeSubscription) {
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $proPlan->id,
                'billing_cycle' => 'lifetime',
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => null,
                'grace_ends_at' => null,
                'notes' => 'Tenant Pendiri (VIP Lifetime)',
            ]);
        }

        TenantContext::runForTenant($tenant, function () {
            $this->call([
                AdminSeeder::class,
                CategorySeeder::class,
                MenuSeeder::class,
                SettingSeeder::class,
            ]);
        });
    }
}
