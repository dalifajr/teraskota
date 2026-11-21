<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaasMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_landing_page_displays_platform_brand_and_pricing_plans(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('KasirLoka')
            ->assertSee('Starter')
            ->assertSee('Pro')
            ->assertSee('39.000')
            ->assertSee('79.000');
    }

    public function test_new_merchant_can_self_register_with_14_day_trial_and_sample_menus(): void
    {
        $response = $this->post('/register', [
            'store_name' => 'Kedai Kopi Senja',
            'business_type' => 'Kedai Minuman / Kopi',
            'phone' => '081234567890',
            'address' => 'Jl. Malioboro No. 5',
            'owner_name' => 'Andi Wijaya',
            'username' => 'kopisenja',
            'email' => 'andi@kopisenja.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'seed_sample' => '1',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $tenant = Tenant::where('name', 'Kedai Kopi Senja')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('trial', $tenant->subscriptionState());
        $this->assertTrue($tenant->canOperate());

        $owner = User::where('username', 'kopisenja')->first();
        $this->assertEquals($tenant->id, $owner->tenant_id);
        $this->assertEquals('admin', $owner->role);

        // Verify sample menus created strictly under this tenant (4 smart items for Kedai Minuman / Kopi)
        $this->assertEquals(
            4,
            Menu::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count()
        );
    }

    public function test_strict_tenant_data_isolation_across_two_stores(): void
    {
        $subService = app(SubscriptionService::class);

        $storeA = $subService->provisionTenant(
            ['name' => 'Warung Alfa', 'business_type' => 'Warung Makan'],
            ['name' => 'Owner Alfa', 'username' => 'alfa_admin', 'email' => 'alfa@test.id', 'password' => 'secret123'],
            true
        );

        $storeB = $subService->provisionTenant(
            ['name' => 'Kedai Beta', 'business_type' => 'Kedai Minuman'],
            ['name' => 'Owner Beta', 'username' => 'beta_admin', 'email' => 'beta@test.id', 'password' => 'secret123'],
            true
        );

        $menuA = Menu::withoutGlobalScope('tenant')->where('tenant_id', $storeA['tenant']->id)->first();
        $menuB = Menu::withoutGlobalScope('tenant')->where('tenant_id', $storeB['tenant']->id)->first();

        // Both stores can legally share identical menu codes (MNM-001) thanks to composite unique (tenant_id, code)
        $this->assertEquals('MNM-001', $menuA->code);
        $this->assertEquals('MNM-001', $menuB->code);
        $this->assertNotEquals($menuA->id, $menuB->id);

        // Store A creates a transaction & expense
        $this->actingAs($storeA['user'])->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menuA->id, 'quantity' => 2]],
            'payment_method' => 'tunai',
            'cash_tendered' => 50000,
            'customer_name' => 'Pelanggan Rahasia Alfa',
        ])->assertStatus(200);

        $trxA = Transaction::withoutGlobalScope('tenant')->where('tenant_id', $storeA['tenant']->id)->first();
        $this->assertNotNull($trxA);

        // Store B MUST NOT see Store A's transaction or menu or user
        $this->actingAs($storeB['user'])->get('/transactions/' . $trxA->id)->assertStatus(404);
        $this->actingAs($storeB['user'])->get('/pos/receipt/' . $trxA->id)->assertStatus(404);
        $this->actingAs($storeB['user'])->get('/menus/' . $menuA->id . '/edit')->assertStatus(404);
        $this->actingAs($storeB['user'])->delete('/users/' . $storeA['user']->id)->assertStatus(403);

        // Store B MUST NOT checkout or offline-sync using Store A's menu_id
        $this->actingAs($storeB['user'])->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menuA->id, 'quantity' => 1]],
            'payment_method' => 'tunai',
            'cash_tendered' => 20000,
        ])->assertStatus(422);

        $this->actingAs($storeB['user'])->postJson('/pos/sync', [
            'sync_id' => (string) Str::uuid(),
            'payment_method' => 'tunai',
            'cash_tendered' => 20000,
            'items' => [['menu_id' => $menuA->id, 'quantity' => 1]],
        ])->assertStatus(422);

        // Store B's dashboard/transactions list must never leak Store A customer name
        $this->actingAs($storeB['user'])->get('/transactions')
            ->assertStatus(200)
            ->assertDontSee('Pelanggan Rahasia Alfa');
    }

    public function test_tenant_aware_qris_webhook_only_matches_target_store(): void
    {
        $subService = app(SubscriptionService::class);

        $storeA = $subService->provisionTenant(
            ['name' => 'Toko QRIS Satu'],
            ['name' => 'Admin Satu', 'username' => 'qris_satu', 'email' => 'satu@qris.id', 'password' => 'secret123'],
            true
        );
        $storeB = $subService->provisionTenant(
            ['name' => 'Toko QRIS Dua'],
            ['name' => 'Admin Dua', 'username' => 'qris_dua', 'email' => 'dua@qris.id', 'password' => 'secret123'],
            true
        );

        Setting::setValue('qris_secret', 'secret-toko-satu-123', $storeA['tenant']->id);
        Setting::setValue('qris_secret', 'secret-toko-dua-456', $storeB['tenant']->id);

        // Create identical pending QRIS amount (Rp15.123) in BOTH stores
        $trxA = Transaction::create([
            'tenant_id' => $storeA['tenant']->id,
            'transaction_number' => 'TRX-20261119-0001',
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_time' => now()->format('H:i:s'),
            'total_quantity' => 1,
            'total_sales' => 15000,
            'total_profit' => 4500,
            'estimated_cost' => 10500,
            'payment_method' => 'qris',
            'status' => 'pending',
            'unique_code' => 123,
            'final_amount' => 15123,
            'qris_expired_at' => now()->addMinutes(15),
            'created_by' => $storeA['user']->id,
        ]);

        $trxB = Transaction::create([
            'tenant_id' => $storeB['tenant']->id,
            'transaction_number' => 'TRX-20261119-0001',
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_time' => now()->format('H:i:s'),
            'total_quantity' => 1,
            'total_sales' => 15000,
            'total_profit' => 4500,
            'estimated_cost' => 10500,
            'payment_method' => 'qris',
            'status' => 'pending',
            'unique_code' => 123,
            'final_amount' => 15123,
            'qris_expired_at' => now()->addMinutes(15),
            'created_by' => $storeB['user']->id,
        ]);

        // Send webhook to Store A's endpoint with Store A's secret
        $body = json_encode([
            'amount' => 15123,
            'source_app' => 'DANA',
            'reference' => 'REF-A-001',
        ]);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, 'secret-toko-satu-123');

        $res = $this->call('POST', '/listener/' . $storeA['tenant']->slug . '/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TIMESTAMP' => $ts,
            'HTTP_X_SIGNATURE' => $sig,
        ], $body);

        $res->assertStatus(200)->assertJson(['matched' => true, 'transaction_id' => $trxA->id]);

        // Store A is PAID, Store B MUST still be PENDING!
        $this->assertEquals('paid', $trxA->fresh()->status);
        $this->assertEquals('pending', $trxB->fresh()->status);
    }

    public function test_expired_subscription_enforces_readonly_and_locks_pos_while_billing_stays_open(): void
    {
        $subService = app(SubscriptionService::class);
        $store = $subService->provisionTenant(
            ['name' => 'Toko Habis Masa'],
            ['name' => 'Owner Expired', 'username' => 'exp_owner', 'email' => 'exp@test.id', 'password' => 'secret123'],
            true
        );

        $cashier = User::create([
            'tenant_id' => $store['tenant']->id,
            'name' => 'Kasir Expired',
            'username' => 'exp_kasir',
            'email' => 'exp_kasir@test.id',
            'password' => bcrypt('secret123'),
            'role' => 'kasir',
        ]);

        // Force subscription past ends_at & grace_ends_at
        $store['subscription']->update([
            'ends_at' => Carbon::now()->subDays(10),
            'grace_ends_at' => Carbon::now()->subDays(5),
        ]);

        $this->assertEquals('expired', $store['tenant']->fresh()->subscriptionState());

        // 1. Admin can still view dashboard (Read-Only) and billing page
        $this->actingAs($store['user'])->get('/dashboard')->assertStatus(200);
        $this->actingAs($store['user'])->get('/billing')->assertStatus(200);

        // 2. Admin is blocked from POS and write operations (redirected to /billing)
        $this->actingAs($store['user'])->get('/pos')->assertRedirect('/billing');
        $this->actingAs($store['user'])->post('/categories', ['name' => 'Kategori Baru'])->assertRedirect('/billing');

        // 3. Cashier sees locked screen (402) on POS
        $this->actingAs($cashier)->get('/pos')->assertStatus(402)->assertSee('Terminal Kasir Sementara Terkunci');
    }

    public function test_starter_plan_blocks_qris_pro_feature_and_enforces_user_limit(): void
    {
        $subService = app(SubscriptionService::class);
        $store = $subService->provisionTenant(
            ['name' => 'Toko Starter'],
            ['name' => 'Owner Starter', 'username' => 'starter_owner', 'email' => 'starter@test.id', 'password' => 'secret123'],
            true
        );

        $starterPlan = Plan::where('slug', 'starter')->firstOrFail();
        $subService->assignSubscriptionManually($store['tenant'], $starterPlan, 'monthly');

        // QRIS Settings & Dynamic QRIS generate are locked on Starter plan
        $this->actingAs($store['user'])->get('/settings/qris')->assertRedirect('/billing');

        $menu = Menu::withoutGlobalScope('tenant')->where('tenant_id', $store['tenant']->id)->first();
        $this->actingAs($store['user'])->postJson('/pos/qris/generate', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertStatus(403)->assertJson(['feature_locked' => true]);

        // Starter plan max_users = 2 (Owner already 1 -> adding 2nd user succeeds, 3rd user blocked)
        $this->actingAs($store['user'])->post('/users', [
            'name' => 'Kasir Satu',
            'username' => 'starter_kasir1',
            'email' => 'k1@starter.id',
            'password' => 'secret123',
            'role' => 'kasir',
        ])->assertRedirect('/users');

        $this->assertEquals(2, User::where('tenant_id', $store['tenant']->id)->count());

        $this->actingAs($store['user'])->post('/users', [
            'name' => 'Kasir Kedua (Over Limit)',
            'username' => 'starter_kasir2',
            'email' => 'k2@starter.id',
            'password' => 'secret123',
            'role' => 'kasir',
        ])->assertRedirect('/users')->assertSessionHas('error');

        $this->assertEquals(2, User::where('tenant_id', $store['tenant']->id)->count());
    }

    public function test_billing_invoice_creation_and_platform_android_listener_auto_activation(): void
    {
        $subService = app(SubscriptionService::class);
        $store = $subService->provisionTenant(
            ['name' => 'Kedai AutoPay'],
            ['name' => 'Owner AutoPay', 'username' => 'autopay_owner', 'email' => 'auto@pay.id', 'password' => 'secret123'],
            false
        );

        $proPlan = Plan::where('slug', 'pro')->firstOrFail();
        $platformSecret = 'platform-super-secret-999';
        PlatformSetting::setValue('platform_qris_secret', $platformSecret);
        PlatformSetting::setValue(
            'platform_qris_payload',
            '00020101021126590014ID.GO.GPN.WWW011893600999000000000002091234567890303UME5204549953033605802ID5909KASIRLOKA6007JAKARTA6304ABCD'
        );

        // Tenant requests Pro Monthly subscription via QRIS
        $this->actingAs($store['user'])->post('/billing/subscribe', [
            'plan_id' => $proPlan->id,
            'billing_cycle' => 'monthly',
            'payment_method' => 'qris',
        ])->assertRedirect('/billing');

        $invoice = SubscriptionInvoice::where('tenant_id', $store['tenant']->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('unpaid', $invoice->status);
        $this->assertNotEmpty($invoice->qris_payload);
        $this->assertGreaterThan(79000, (float) $invoice->final_amount);

        // Simulate Android Payment Listener sending payment notification to /listener/platform/payment
        $body = json_encode([
            'amount' => (float) $invoice->final_amount,
            'source_app' => 'GoPay Merchant',
            'reference' => 'PLAT-REF-777',
        ]);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, $platformSecret);

        $res = $this->call('POST', '/listener/platform/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TIMESTAMP' => $ts,
            'HTTP_X_SIGNATURE' => $sig,
        ], $body);

        $res->assertStatus(200)->assertJson([
            'status' => 'success',
            'matched' => true,
            'invoice_number' => $invoice->invoice_number,
        ]);

        // Invoice must now be PAID and Tenant must be ACTIVE on Pro plan!
        $this->assertEquals('paid', $invoice->fresh()->status);
        $freshTenant = $store['tenant']->fresh(['activeSubscription.plan']);
        $this->assertEquals('active', $freshTenant->subscriptionState());
        $this->assertEquals('pro', $freshTenant->currentPlan()->slug);
        $this->assertTrue($freshTenant->hasFeature('qris_dynamic'));
    }

    public function test_super_admin_panel_access_control_and_tenant_impersonation(): void
    {
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();
        $tenantAdmin = User::where('username', 'admin')->firstOrFail();
        $tenant = Tenant::where('slug', 'teras-kota')->firstOrFail();

        // Regular tenant admin CANNOT access /superadmin
        $this->actingAs($tenantAdmin)->get('/superadmin')->assertRedirect('/dashboard');

        // Super Admin can access dashboard, tenants, invoices, plans, settings
        $this->actingAs($superAdmin)->get('/superadmin')->assertStatus(200)->assertSee('Estimasi MRR');
        $this->actingAs($superAdmin)->get('/superadmin/tenants')->assertStatus(200)->assertSee($tenant->name);
        $this->actingAs($superAdmin)->get('/superadmin/invoices')->assertStatus(200);
        $this->actingAs($superAdmin)->get('/superadmin/plans')->assertStatus(200)->assertSee('Starter');
        $this->actingAs($superAdmin)->get('/superadmin/settings')->assertStatus(200)->assertSee('/listener/platform/payment');

        // Super Admin can impersonate tenant & view tenant dashboard, then stop impersonate
        $this->actingAs($superAdmin)->post('/superadmin/tenants/' . $tenant->id . '/impersonate')
            ->assertRedirect('/dashboard');

        $this->actingAs($superAdmin)->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Mode Support Super Admin')
            ->assertSee($tenant->name);

        $this->actingAs($superAdmin)->post('/superadmin/stop-impersonate')
            ->assertRedirect('/superadmin/tenants');
    }

    public function test_store_white_label_profile_updates_receipt_branding(): void
    {
        $subService = app(SubscriptionService::class);
        $store = $subService->provisionTenant(
            ['name' => 'Warung Original'],
            ['name' => 'Owner WL', 'username' => 'wl_owner', 'email' => 'wl@test.id', 'password' => 'secret123'],
            true
        );

        $this->actingAs($store['user'])->put('/settings/store', [
            'name' => 'Kopi Nusantara Express',
            'business_type' => 'Kedai Kopi',
            'address' => 'Jl. Sudirman Kav 99, Bandung',
            'phone' => '089988776655',
            'receipt_footer' => 'Sampai jumpa di seduhan berikutnya!',
            'timezone' => 'Asia/Jakarta',
        ])->assertRedirect('/settings/store');

        $menu = Menu::withoutGlobalScope('tenant')->where('tenant_id', $store['tenant']->id)->first();
        $checkout = $this->actingAs($store['user'])->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
            'payment_method' => 'tunai',
            'cash_tendered' => 20000,
        ])->assertStatus(200);

        $trxId = $checkout->json('transaction.id');
        $this->actingAs($store['user'])->get('/pos/receipt/' . $trxId)
            ->assertStatus(200)
            ->assertSee('Kopi Nusantara Express')
            ->assertSee('Jl. Sudirman Kav 99, Bandung')
            ->assertSee('Sampai jumpa di seduhan berikutnya!');
    }

    public function test_role_menu_segregation_and_super_admin_tenant_management(): void
    {
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();
        $tenantAdmin = User::where('username', 'admin')->firstOrFail();
        $cashier = User::where('username', 'kasir')->firstOrFail();
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();

        // 1. Super Admin sees PLATFORM HQ menu & Super Admin mobile nav, NOT Tenant POS/Menu links
        $saPage = $this->actingAs($superAdmin)->get('/superadmin');
        $saPage->assertStatus(200)
            ->assertSee('PLATFORM HQ')
            ->assertSee('Pusat Kontrol SaaS')
            ->assertSee('Update Sistem')
            ->assertDontSee('Buka Kasir POS')
            ->assertDontSee('Data Menu');

        // 2. Tenant Admin sees store operational menus, NOT Super Admin PLATFORM HQ or Update Sistem
        $taPage = $this->actingAs($tenantAdmin)->get('/dashboard');
        $taPage->assertStatus(200)
            ->assertSee('Buka Kasir POS')
            ->assertSee('Langganan &amp; Paket', false)
            ->assertSee('Profil Usaha &amp; Struk', false)
            ->assertDontSee('PLATFORM HQ')
            ->assertDontSee('Update Sistem');

        // 3. Cashier opening /profile sees clean cashier profile without Admin sidebar menus
        $kasirProfile = $this->actingAs($cashier)->get('/profile');
        $kasirProfile->assertStatus(200)
            ->assertSee('Profil Kasir')
            ->assertSee('Kembali ke Kasir POS')
            ->assertDontSee('Kelola Pengguna')
            ->assertDontSee('PLATFORM HQ');

        // 4. Guest or Tenant Admin cannot trigger /auto-migrate without Super Admin auth
        $this->get('/auto-migrate')->assertStatus(403);
        $this->actingAs($tenantAdmin)->get('/auto-migrate')->assertStatus(403);

        // 5. Super Admin can manually create a new tenant & reset owner password
        $this->actingAs($superAdmin)->post('/superadmin/tenants', [
            'store_name' => 'Bakso Malang Juara',
            'business_type' => 'Warung Makan',
            'owner_name' => 'Pak Hendra',
            'username' => 'baksomalang',
            'email' => 'hendra@bakso.id',
            'password' => 'awal123',
            'plan_id' => $proPlan->id,
            'billing_cycle' => 'monthly',
            'seed_sample' => '1',
        ])->assertRedirect();

        $newTenant = Tenant::where('name', 'Bakso Malang Juara')->first();
        $this->assertNotNull($newTenant);
        $this->assertEquals('active', $newTenant->subscriptionState());

        $this->actingAs($superAdmin)->post('/superadmin/tenants/' . $newTenant->id . '/reset-password', [
            'new_password' => 'barubanget99',
        ])->assertRedirect();

        $owner = User::where('username', 'baksomalang')->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('barubanget99', $owner->password));
    }

    public function test_smart_sample_catalog_by_business_type_and_1click_menu_toggle_and_whitelabel_csv(): void
    {
        $subService = app(SubscriptionService::class);
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();

        // 1. Bakery preset seeds Bakery-specific items
        $bakery = $subService->provisionTenant(
            ['name' => 'RotiHarum Bakery', 'business_type' => 'Bakery & Kue'],
            ['name' => 'Owner Bakery', 'username' => 'bakery_owner', 'email' => 'owner@bakery.id', 'password' => 'secret123'],
            true
        );

        $bakeryMenuNames = Menu::withoutGlobalScope('tenant')
            ->where('tenant_id', $bakery['tenant']->id)
            ->pluck('name')
            ->toArray();

        $this->assertContains('Croissant Butter', $bakeryMenuNames);
        $this->assertContains('Roti Bakar Cokelat Keju', $bakeryMenuNames);

        // 2. Retail preset seeds Retail-specific items
        $retail = $subService->provisionTenant(
            ['name' => 'Berkah Minimarket', 'business_type' => 'Toko Kelontong / Retail'],
            ['name' => 'Owner Retail', 'username' => 'retail_owner', 'email' => 'owner@retail.id', 'password' => 'secret123'],
            true
        );

        $retailMenuNames = Menu::withoutGlobalScope('tenant')
            ->where('tenant_id', $retail['tenant']->id)
            ->pluck('name')
            ->toArray();

        $this->assertContains('Mie Instan Goreng', $retailMenuNames);
        $this->assertContains('Air Mineral 600ml', $retailMenuNames);

        // 3. 1-Click Menu Status Toggle (Aktif <-> Habis) & Cross-Tenant Isolation
        $bakeryMenu = Menu::withoutGlobalScope('tenant')
            ->where('tenant_id', $bakery['tenant']->id)
            ->firstOrFail();

        $this->assertTrue((bool) $bakeryMenu->status);

        $this->actingAs($bakery['user'])
            ->patch('/menus/' . $bakeryMenu->id . '/toggle-status')
            ->assertRedirect();

        $this->assertFalse((bool) $bakeryMenu->fresh()->status);

        // Retail admin MUST NOT be able to toggle Bakery's menu (404 due to tenant scope)
        $this->actingAs($retail['user'])
            ->patch('/menus/' . $bakeryMenu->id . '/toggle-status')
            ->assertStatus(404);

        // 4. White-Label CSV Report Export uses tenant store name instead of hardcoded Teras Kota
        $csvResponse = $this->actingAs($bakery['user'])->get('/reports/export');
        $csvResponse->assertStatus(200);
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('LAPORAN PENJUALAN ROTIHARUM BAKERY', $csvContent);
        $this->assertStringNotContainsString('TERAS KOTA BERLIAN MAKMUR', $csvContent);

        // 5. Plan Quota Indicators on /menus & /users
        $this->actingAs($bakery['user'])->get('/menus')
            ->assertStatus(200)
            ->assertSee('Kuota Menu Paket Langganan')
            ->assertSee('Kelola Menu Produk');

        $this->actingAs($bakery['user'])->get('/users')
            ->assertStatus(200)
            ->assertSee('Kuota Akun Paket');

        // 6. Super Admin Tenant Status Filter (/superadmin/tenants?status=trial)
        $this->actingAs($superAdmin)->get('/superadmin/tenants?status=trial')
            ->assertStatus(200)
            ->assertSee('RotiHarum Bakery');
    }

    public function test_super_admin_upgrade_suite_analytics_tenant_crud_invoice_search_webhook_logs_and_broadcast(): void
    {
        $subService = app(SubscriptionService::class);
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();

        // Provision a tenant expiring in 3 days with a phone number
        $soonStore = $subService->provisionTenant(
            ['name' => 'Warung Segera Habis', 'business_type' => 'Warung Makan / Resto', 'phone' => '081234560000', 'address' => 'Jl. Kenanga 10'],
            ['name' => 'Owner Soon', 'username' => 'soon_owner', 'email' => 'soon@test.id', 'password' => 'secret123'],
            true
        );
        $soonStore['subscription']->update(['ends_at' => Carbon::now()->addDays(3)]);

        // Create an unpaid invoice with unique code
        $invoice = $subService->createInvoice($soonStore['tenant'], $proPlan, 'monthly', 'qris');

        // 1. Dashboard shows expiring watchlist, chart canvas, and sidebar unpaid badge
        $this->actingAs($superAdmin)->get('/superadmin')
            ->assertStatus(200)
            ->assertSee('saasGrowthChart')
            ->assertSee('Warung Segera Habis')
            ->assertSee('https://wa.me/6281234560000');

        // 2. Invoice search by 3-digit unique_code & CSV revenue export
        $this->actingAs($superAdmin)->get('/superadmin/invoices?search=' . $invoice->unique_code)
            ->assertStatus(200)
            ->assertSee($invoice->invoice_number);

        $csvRes = $this->actingAs($superAdmin)->get('/superadmin/invoices/export');
        $csvRes->assertStatus(200);
        $csvBody = $csvRes->streamedContent();
        $this->assertStringContainsString('LAPORAN TAGIHAN & REVENUE SAAS', $csvBody);
        $this->assertStringContainsString($invoice->invoice_number, $csvBody);

        // 3. Platform webhook logs persisted (unmatched & matched) and visible in /superadmin/settings
        $platformSecret = 'plat-log-secret-8888';
        PlatformSetting::setValue('platform_qris_secret', $platformSecret);

        $unmatchedBody = json_encode(['amount' => 12345, 'source_app' => 'BRI', 'reference' => 'UNM-001']);
        $ts1 = (string) time();
        $this->call('POST', '/listener/platform/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TIMESTAMP' => $ts1,
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $ts1 . '.' . $unmatchedBody, $platformSecret),
        ], $unmatchedBody)->assertStatus(200)->assertJson(['matched' => false]);

        $matchedBody = json_encode(['amount' => (float) $invoice->final_amount, 'source_app' => 'GoPay', 'reference' => 'MTC-001']);
        $ts2 = (string) time();
        $this->call('POST', '/listener/platform/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TIMESTAMP' => $ts2,
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $ts2 . '.' . $matchedBody, $platformSecret),
        ], $matchedBody)->assertStatus(200)->assertJson(['matched' => true]);

        $this->assertEquals(2, \App\Models\PaymentListenerLog::withoutGlobalScope('tenant')->where('is_platform_billing', true)->count());

        $this->actingAs($superAdmin)->get('/superadmin/settings')
            ->assertStatus(200)
            ->assertSee('Log Webhook Billing Terbaru')
            ->assertSee($invoice->invoice_number)
            ->assertSee('UNMATCHED');

        // 4. Broadcast Announcement Banner appears on Tenant Dashboard
        $this->actingAs($superAdmin)->put('/superadmin/settings', [
            'platform_name' => 'KasirLoka',
            'platform_tagline' => 'POS UMKM',
            'platform_qris_secret' => $platformSecret,
            'trial_days' => 14,
            'grace_days' => 3,
            'announcement_enabled' => '1',
            'announcement_type' => 'warning',
            'announcement_message' => 'Pemeliharaan server pukul 23:00 WIB.',
        ])->assertRedirect();

        $this->actingAs($soonStore['user'])->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Pemeliharaan server pukul 23:00 WIB.');

        // 5. Edit Tenant Info & Safe Delete Guard (Blocked when Active, Allowed after Suspend)
        $this->actingAs($superAdmin)->put('/superadmin/tenants/' . $soonStore['tenant']->id, [
            'name' => 'Warung Segera Habis Updated',
            'business_type' => 'Warung Makan / Resto',
            'phone' => '081299998888',
            'address' => 'Jl. Baru No. 99',
        ])->assertRedirect();

        $this->assertEquals('Warung Segera Habis Updated', $soonStore['tenant']->fresh()->name);

        // Cannot delete while active
        $this->actingAs($superAdmin)->delete('/superadmin/tenants/' . $soonStore['tenant']->id)
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertNotNull(Tenant::find($soonStore['tenant']->id));

        // Suspend first, then permanent delete succeeds
        $this->actingAs($superAdmin)->post('/superadmin/tenants/' . $soonStore['tenant']->id . '/toggle')->assertRedirect();
        $this->actingAs($superAdmin)->delete('/superadmin/tenants/' . $soonStore['tenant']->id)
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertNull(Tenant::withTrashed()->find($soonStore['tenant']->id));

        // 6. System Update Health Diagnostics loads cleanly
        $this->actingAs($superAdmin)->get('/superadmin/system-update')
            ->assertStatus(200)
            ->assertSee('Kesehatan Server')
            ->assertSee('TERHUBUNG');
    }

    public function test_tenant_admin_upgrade_suite_dashboard_reconciliation_exports_filters_and_billing(): void
    {
        $subService = app(SubscriptionService::class);

        $storeA = $subService->provisionTenant(
            ['name' => 'Kopi Harmoni', 'business_type' => 'Kedai Minuman / Kopi'],
            ['name' => 'Admin Harmoni', 'username' => 'harmoni_admin', 'email' => 'harmoni@test.id', 'password' => 'secret123'],
            true
        );
        $storeB = $subService->provisionTenant(
            ['name' => 'Warung Lain', 'business_type' => 'Warung Makan / Resto'],
            ['name' => 'Admin Lain', 'username' => 'lain_admin', 'email' => 'lain@test.id', 'password' => 'secret123'],
            true
        );

        $menuA = Menu::withoutGlobalScope('tenant')->where('tenant_id', $storeA['tenant']->id)->firstOrFail();
        $catA = Category::withoutGlobalScope('tenant')->where('tenant_id', $storeA['tenant']->id)->firstOrFail();

        // Create cash transaction + cash expense for Store A
        $this->actingAs($storeA['user'])->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menuA->id, 'quantity' => 2]],
            'payment_method' => 'tunai',
            'cash_tendered' => 50000,
            'customer_name' => 'Budi Santoso',
        ])->assertStatus(200);

        $this->actingAs($storeA['user'])->post('/expenses', [
            'expense_date' => now()->format('Y-m-d'),
            'category' => 'Bahan Baku',
            'description' => 'Beli Es Batu',
            'amount' => 5000,
            'payment_method' => 'tunai',
        ])->assertRedirect('/expenses');

        // 1. Dashboard shows Cash Drawer Estimate, Cashier Reconciliation, and WA Rekap
        $this->actingAs($storeA['user'])->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Estimasi Kas Fisik Laci')
            ->assertSee('Rekap Penjualan per Kasir')
            ->assertSee('Admin Harmoni')
            ->assertSee('Item / Produk Terjual')
            ->assertSee('Rekap WA');

        // 2. Transactions filter by payment_method & cashier_id + CSV export
        $this->actingAs($storeA['user'])->get('/transactions?payment_method=tunai&cashier_id=' . $storeA['user']->id)
            ->assertStatus(200)
            ->assertSee('Budi Santoso')
            ->assertSee('Total Nota (Sesuai Filter)');

        $txCsv = $this->actingAs($storeA['user'])->get('/transactions/export?payment_method=tunai');
        $txCsv->assertStatus(200);
        $txCsvBody = $txCsv->streamedContent();
        $this->assertStringContainsString('LOG TRANSAKSI KOPI HARMONI', $txCsvBody);
        $this->assertStringContainsString('Budi Santoso', $txCsvBody);

        // 3. Expenses filter by payment_method & CSV export
        $this->actingAs($storeA['user'])->get('/expenses?payment_method=tunai')
            ->assertStatus(200)
            ->assertSee('Dari Kas Tunai (Laci)')
            ->assertSee('Beli Es Batu');

        $expCsv = $this->actingAs($storeA['user'])->get('/expenses/export?payment_method=tunai');
        $expCsv->assertStatus(200);
        $expCsvBody = $expCsv->streamedContent();
        $this->assertStringContainsString('LAPORAN PENGELUARAN KAS KOPI HARMONI', $expCsvBody);
        $this->assertStringContainsString('Beli Es Batu', $expCsvBody);

        // 4. Menu status filter & 1-click category toggle + cross-tenant isolation
        $this->actingAs($storeA['user'])->patch('/menus/' . $menuA->id . '/toggle-status')->assertRedirect();
        $this->actingAs($storeA['user'])->get('/menus?status=inactive')
            ->assertStatus(200)
            ->assertSee($menuA->name);

        $this->assertTrue((bool) $catA->status);
        $this->actingAs($storeA['user'])->patch('/categories/' . $catA->id . '/toggle-status')->assertRedirect();
        $this->assertFalse((bool) $catA->fresh()->status);

        // Store B cannot toggle Store A's category (404)
        $this->actingAs($storeB['user'])->patch('/categories/' . $catA->id . '/toggle-status')->assertStatus(404);

        // 5. Reports 5-col grid + Store live preview + Profit live simulator
        $this->actingAs($storeA['user'])->get('/reports')
            ->assertStatus(200)
            ->assertSee('row-cols-xl-5')
            ->assertSee('Bagikan WA')
            ->assertSee('Omzet Tunai');

        $this->actingAs($storeA['user'])->get('/settings/store')
            ->assertStatus(200)
            ->assertSee('Live Preview')
            ->assertSee('previewStoreName');

        $this->actingAs($storeA['user'])->get('/settings/profit')
            ->assertStatus(200)
            ->assertSee('semua menu produk')
            ->assertSee('simProfitText');

        // 6. Billing transfer invoice loads copy button + auto-poll script even without QRIS
        $starterPlan = Plan::where('slug', 'starter')->firstOrFail();
        $subService->createInvoice($storeA['tenant'], $starterPlan, 'monthly', 'transfer');

        $this->actingAs($storeA['user'])->get('/billing')
            ->assertStatus(200)
            ->assertSee('js-billing-copy')
            ->assertSee('billing/invoice/');
    }

    public function test_cashier_pos_upgrade_suite_profile_link_hold_order_starter_qris_lock_and_summary_details(): void
    {
        $subService = app(SubscriptionService::class);
        $starterPlan = Plan::where('slug', 'starter')->firstOrFail();

        $store = $subService->provisionTenant(
            ['name' => 'Kedai Kasir Pro', 'business_type' => 'Kedai Minuman / Kopi'],
            ['name' => 'Owner Kasir', 'username' => 'owner_pos_test', 'email' => 'ownerpos@test.id', 'password' => 'secret123'],
            true
        );

        $cashier = User::create([
            'tenant_id' => $store['tenant']->id,
            'name' => 'Dina Kasir',
            'username' => 'dina_kasir',
            'email' => 'dina@test.id',
            'password' => bcrypt('secret123'),
            'role' => 'kasir',
        ]);

        // 1. Trial/Pro Cashier POS page has Profile link, Hold Order, Qty badges, clean data-category-id (no inline onclick)
        $posPage = $this->actingAs($cashier)->get('/pos');
        $posPage->assertStatus(200)
            ->assertSee(route('profile.edit'))
            ->assertSee('btnHoldOrder')
            ->assertSee('btnOpenHeldOrders')
            ->assertSee('pos-card-qty-badge')
            ->assertSee('data-category-id=')
            ->assertSee('btnShareShiftWa')
            ->assertDontSee('onclick="posAddToCart(');

        // 2. Cashier creates a transaction and /pos/summary-today returns details & cashier for reprint/WA
        $menu = Menu::withoutGlobalScope('tenant')->where('tenant_id', $store['tenant']->id)->firstOrFail();
        $this->actingAs($cashier)->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
            'payment_method' => 'tunai',
            'cash_tendered' => 50000,
        ])->assertStatus(200);

        $summaryRes = $this->actingAs($cashier)->getJson('/pos/summary-today');
        $summaryRes->assertStatus(200)
            ->assertJsonPath('total_transactions', 1)
            ->assertJsonStructure([
                'transactions' => [
                    ['id', 'transaction_number', 'details', 'cashier'],
                ],
            ]);

        // 3. Switching tenant to Starter plan locks QRIS radio visually with PRO badge in POS UI
        $subService->assignSubscriptionManually($store['tenant'], $starterPlan, 'monthly');
        $starterPos = $this->actingAs($cashier)->get('/pos');
        $starterPos->assertStatus(200)
            ->assertSee('PRO');
    }

    public function test_public_auth_profile_and_pwa_security_upgrades(): void
    {
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();
        $tenantAdmin = User::where('username', 'admin')->firstOrFail();
        $cashier = User::where('username', 'kasir')->firstOrFail();

        // 1. Dynamic trialDays & Support WhatsApp rendered on Landing Page & Login
        PlatformSetting::setValue('trial_days', '21');
        PlatformSetting::setValue('support_whatsapp', '081277778888');

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('Gratis Uji Coba 21 Hari')
            ->assertSee('https://wa.me/6281277778888');

        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('Daftar Gratis 21 Hari')
            ->assertSee('https://wa.me/6281277778888');

        // 2. Login brute-force rate limiting (5 failed attempts -> 6th blocked)
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'brute_target_user',
                'password' => 'wrongpass',
            ])->assertSessionHasErrors('username');
        }

        $throttled = $this->post('/login', [
            'username' => 'brute_target_user',
            'password' => 'wrongpass',
        ]);
        $throttled->assertSessionHasErrors('username');
        $this->assertStringContainsString('Terlalu banyak percobaan masuk gagal', session('errors')->first('username'));
        \Illuminate\Support\Facades\RateLimiter::clear('login:brute_target_user|127.0.0.1');

        // 3. Profile Cancel URL is role-aware (no broken redirect for Super Admin or Kasir)
        $this->actingAs($superAdmin)->get('/profile')
            ->assertStatus(200)
            ->assertSee('href="' . route('superadmin.dashboard') . '"', false);

        $this->actingAs($cashier)->get('/profile')
            ->assertStatus(200)
            ->assertSee('href="' . route('pos.index') . '"', false);

        $this->actingAs($tenantAdmin)->get('/profile')
            ->assertStatus(200)
            ->assertSee('href="' . route('dashboard') . '"', false);

        // 4. White-label PWA assets clean of Teras Kota & SW caches only /pos HTML
        $manifest = file_get_contents(public_path('manifest.webmanifest'));
        $offlineHtml = file_get_contents(public_path('offline.html'));
        $swJs = file_get_contents(public_path('service-worker.js'));

        $this->assertStringContainsString('KasirLoka POS', $manifest);
        $this->assertStringNotContainsString('Teras Kota', $manifest);
        $this->assertStringContainsString('KasirLoka POS', $offlineHtml);
        $this->assertStringNotContainsString('Teras Kota', $offlineHtml);
        $this->assertStringContainsString('kasirloka-pos-v3', $swJs);
        $this->assertStringContainsString("url.pathname === '/pos'", $swJs);
    }

    public function test_super_admin_round2_bugfixes_audit_trail_manual_invoice_new_plan_and_webhook_ping(): void
    {
        $subService = app(SubscriptionService::class);
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();
        $tenantAdmin = User::where('username', 'admin')->firstOrFail();
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();

        // Provision 2 tenants & create invoices for both (verifying eager-load fix without SQL global limit(5))
        $t1 = $subService->provisionTenant(
            ['name' => 'Tenant Audit Satu', 'business_type' => 'Kedai Minuman / Kopi'],
            ['name' => 'Owner T1', 'username' => 't1_owner', 'email' => 't1@test.id', 'password' => 'secret123'],
            false
        );
        $t2 = $subService->provisionTenant(
            ['name' => 'Tenant Audit Dua', 'business_type' => 'Warung Makan / Resto'],
            ['name' => 'Owner T2', 'username' => 't2_owner', 'email' => 't2@test.id', 'password' => 'secret123'],
            false
        );

        // 1. Super Admin creates manual invoices for both tenants via POST /superadmin/invoices
        $this->actingAs($superAdmin)->post('/superadmin/invoices', [
            'tenant_id' => $t1['tenant']->id,
            'plan_id' => $proPlan->id,
            'billing_cycle' => 'monthly',
            'payment_method' => 'transfer',
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($superAdmin)->post('/superadmin/invoices', [
            'tenant_id' => $t2['tenant']->id,
            'plan_id' => $proPlan->id,
            'billing_cycle' => 'yearly',
            'payment_method' => 'qris',
        ])->assertRedirect()->assertSessionHas('success');

        $inv1 = SubscriptionInvoice::where('tenant_id', $t1['tenant']->id)->latest('id')->firstOrFail();
        $inv2 = SubscriptionInvoice::where('tenant_id', $t2['tenant']->id)->latest('id')->firstOrFail();

        // Verify both tenants' invoices render in /superadmin/tenants (eager load bug fixed)
        $this->actingAs($superAdmin)->get('/superadmin/tenants')
            ->assertStatus(200)
            ->assertSee($inv1->invoice_number)
            ->assertSee($inv2->invoice_number);

        // 2. Overdue unpaid invoice automatically transitions to 'expired' when Super Admin opens /superadmin/invoices
        $inv1->update(['qris_expired_at' => Carbon::now()->subHours(2)]);
        $this->actingAs($superAdmin)->get('/superadmin/invoices')->assertStatus(200);
        $this->assertEquals('expired', $inv1->fresh()->status);

        // 3. Super Admin creates a brand new tier plan via POST /superadmin/plans
        $this->actingAs($superAdmin)->post('/superadmin/plans', [
            'name' => 'Enterprise Multi',
            'description' => 'Untuk jaringan outlet besar',
            'price_monthly' => 149000,
            'price_yearly' => 1490000,
            'max_users' => 50,
            'feature_qris_dynamic' => '1',
            'feature_offline_sync' => '1',
            'feature_expenses' => '1',
            'feature_pdf_export' => '1',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('plans', ['name' => 'Enterprise Multi', 'slug' => 'enterprise-multi']);

        // 4. Dashboard displays SaaS business metrics (Conversion Rate, ARPU, GMV)
        $this->actingAs($superAdmin)->get('/superadmin')
            ->assertStatus(200)
            ->assertSee('Trial Conversion Rate')
            ->assertSee('ARPU')
            ->assertSee('GMV POS Global');

        // 5. Live Webhook Test Ping endpoint returns success JSON
        $this->actingAs($superAdmin)->postJson('/superadmin/settings/test-webhook')
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        // 6. Audit Trail records actions & restricts access to Super Admin only
        $this->actingAs($tenantAdmin)->get('/superadmin/audit-logs')->assertRedirect('/dashboard');
        $this->actingAs($superAdmin)->get('/superadmin/audit-logs')
            ->assertStatus(200)
            ->assertSee($inv1->invoice_number)
            ->assertSee('Enterprise Multi');
    }

    public function test_ui_efficiency_components_render_across_superadmin_and_tenant_views(): void
    {
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();

        $subService = app(SubscriptionService::class);
        $store = $subService->provisionTenant(
            ['name' => 'Kopi Efisien', 'business_type' => 'Kedai Minuman / Kopi'],
            ['name' => 'Owner Efisien', 'username' => 'efisien_admin', 'email' => 'efisien@test.id', 'password' => 'secret123'],
            true
        );
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();

        // Ensure there is an unpaid invoice so Super Admin invoice hamburger menu renders
        $subService->createInvoice($store['tenant'], $proPlan, 'monthly', 'transfer');

        // Ensure there is at least 1 transaction & 1 expense via real endpoints so table action menus render
        $menu = Menu::withoutGlobalScope('tenant')->where('tenant_id', $store['tenant']->id)->firstOrFail();

        $this->actingAs($store['user'])->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
            'payment_method' => 'tunai',
            'cash_tendered' => 50000,
            'customer_name' => 'Pelanggan UI',
        ])->assertStatus(200);

        $this->actingAs($store['user'])->post('/expenses', [
            'expense_date' => now()->format('Y-m-d'),
            'category' => 'Bahan Baku',
            'description' => 'Beli Es Batu',
            'amount' => 12000,
            'payment_method' => 'tunai',
        ])->assertRedirect('/expenses');

        // Super Admin: compact metric strip, hamburger action menus, executive plan summary cards + edit modals
        $this->actingAs($superAdmin)->get('/superadmin')
            ->assertStatus(200)
            ->assertSee('kl-metric-strip', false);

        $this->actingAs($superAdmin)->get('/superadmin/tenants')
            ->assertStatus(200)
            ->assertSee('kl-action-dropdown', false)
            ->assertSee('kl-action-menu', false);

        $this->actingAs($superAdmin)->get('/superadmin/invoices')
            ->assertStatus(200)
            ->assertSee('kl-action-dropdown', false);

        $this->actingAs($superAdmin)->get('/superadmin/plans')
            ->assertStatus(200)
            ->assertSee('kl-plan-card', false)
            ->assertSee('editPlanModal' . $proPlan->id, false);

        // Tenant Admin: unified metric strip, collapsible filters, hamburger action menus, compact billing switcher
        $this->actingAs($store['user'])->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('kl-metric-strip', false)
            ->assertSee('kl-action-dropdown', false);

        $this->actingAs($store['user'])->get('/transactions')
            ->assertStatus(200)
            ->assertSee('kl-filter-toolbar', false)
            ->assertSee('trxAdvancedFilters', false)
            ->assertSee('kl-action-dropdown', false);

        $this->actingAs($store['user'])->get('/expenses')
            ->assertStatus(200)
            ->assertSee('kl-filter-toolbar', false)
            ->assertSee('expAdvancedFilters', false)
            ->assertSee('kl-action-dropdown', false);

        $this->actingAs($store['user'])->get('/menus')
            ->assertStatus(200)
            ->assertSee('kl-filter-toolbar', false)
            ->assertSee('kl-action-dropdown', false);

        $this->actingAs($store['user'])->get('/categories')
            ->assertStatus(200)
            ->assertSee('kl-action-dropdown', false);

        $this->actingAs($store['user'])->get('/users')
            ->assertStatus(200)
            ->assertSee('kl-action-dropdown', false);

        $this->actingAs($store['user'])->get('/reports')
            ->assertStatus(200)
            ->assertSee('kl-filter-toolbar', false)
            ->assertSee('Ekspor &amp; Bagikan', false);

        $this->actingAs($store['user'])->get('/billing')
            ->assertStatus(200)
            ->assertSee('kl-segmented', false)
            ->assertSee('kl-plan-card', false);
    }

    public function test_superadmin_promos_plan_delete_tenant_export_and_invoice_date_filters(): void
    {
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();
        $subService = app(SubscriptionService::class);
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();

        $store = $subService->provisionTenant(
            ['name' => 'Kedai Promo Uji', 'business_type' => 'Kedai Minuman / Kopi'],
            ['name' => 'Owner Promo', 'username' => 'promo_owner', 'email' => 'promo@test.id', 'password' => 'secret123'],
            true
        );

        // 1. Super Admin creates a 20% promo code (max discount Rp 15.000)
        $this->actingAs($superAdmin)->post('/superadmin/promos', [
            'code' => 'HEMAT20',
            'name' => 'Diskon 20% Launch',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'max_discount_amount' => 15000,
            'max_uses' => 5,
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $promo = \App\Models\PromoCode::where('code', 'HEMAT20')->firstOrFail();

        // 2. Tenant checks promo via AJAX and subscribes with promo code
        $this->actingAs($store['user'])->postJson('/billing/promo/check', [
            'code' => 'hemat20',
            'plan_id' => $proPlan->id,
            'billing_cycle' => 'monthly',
        ])->assertStatus(200)->assertJson(['valid' => true, 'code' => 'HEMAT20']);

        $this->actingAs($store['user'])->post('/billing/subscribe', [
            'plan_id' => $proPlan->id,
            'billing_cycle' => 'monthly',
            'payment_method' => 'transfer',
            'promo_code' => 'HEMAT20',
        ])->assertRedirect('/billing')->assertSessionHas('success');

        $invoice = SubscriptionInvoice::where('tenant_id', $store['tenant']->id)->latest('id')->firstOrFail();
        $this->assertEquals($promo->id, $invoice->promo_code_id);
        $this->assertEquals('HEMAT20', $invoice->promo_code_snapshot);
        $this->assertEquals(15000.0, (float) $invoice->discount_amount); // 20% of 79.000 = 15.800 capped at 15.000
        $this->assertEquals(64000.0, (float) $invoice->base_amount);
        $this->assertEquals(0, $promo->fresh()->used_count);

        // 3. Super Admin approves invoice -> used_count increments atomically
        $this->actingAs($superAdmin)->post('/superadmin/invoices/' . $invoice->id . '/approve')
            ->assertRedirect()->assertSessionHas('success');
        $this->assertEquals(1, $promo->fresh()->used_count);

        // 4. Super Admin filters invoices by date & sees promo badge + KPI strip
        $today = now()->format('Y-m-d');
        $this->actingAs($superAdmin)->get("/superadmin/invoices?start_date={$today}&end_date={$today}")
            ->assertStatus(200)
            ->assertSee('HEMAT20')
            ->assertSee('Total Revenue Lunas');

        // 5. Super Admin filters tenants by plan_id & exports CSV
        $this->actingAs($superAdmin)->get('/superadmin/tenants?plan_id=' . $proPlan->id)
            ->assertStatus(200)
            ->assertSee('Kedai Promo Uji');

        $csvResp = $this->actingAs($superAdmin)->get('/superadmin/tenants/export?plan_id=' . $proPlan->id);
        $csvResp->assertStatus(200);
        $this->assertStringContainsString('Kedai Promo Uji', $csvResp->streamedContent());

        // 6. Safe Plan Deletion: blocks Pro plan (in use), allows deleting brand-new unused plan
        $this->actingAs($superAdmin)->delete('/superadmin/plans/' . $proPlan->id)
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('plans', ['id' => $proPlan->id]);

        $unusedPlan = Plan::create([
            'name' => 'Sementara',
            'slug' => 'sementara-test',
            'price_monthly' => 25000,
            'price_yearly' => 250000,
            'is_active' => false,
            'sort_order' => 99,
            'features' => ['expenses' => false, 'pdf_export' => false],
        ]);
        $this->actingAs($superAdmin)->delete('/superadmin/plans/' . $unusedPlan->id)
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('plans', ['id' => $unusedPlan->id]);
    }

    public function test_tenant_admin_feature_gating_timezone_trash_restore_menu_csv_peak_hours_and_qris_ping(): void
    {
        $subService = app(SubscriptionService::class);
        $store = $subService->provisionTenant(
            ['name' => 'Warung Makassar', 'business_type' => 'Warung Makan / Resto', 'timezone' => 'Asia/Makassar'],
            ['name' => 'Owner Makassar', 'username' => 'mks_owner', 'email' => 'mks@test.id', 'password' => 'secret123'],
            true
        );

        // 1. Dynamic WITA timezone appears in navbar & dashboard
        $this->actingAs($store['user'])->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('WITA (UTC+8)')
            ->assertSee('Asia/Makassar');

        // 2. Soft-delete transaction -> appears in Trash tab -> Restore brings it back
        $menu = Menu::withoutGlobalScope('tenant')->where('tenant_id', $store['tenant']->id)->firstOrFail();
        $checkout = $this->actingAs($store['user'])->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
            'payment_method' => 'tunai',
            'cash_tendered' => 50000,
            'customer_name' => 'Andi Trash',
        ])->assertStatus(200);
        $txId = $checkout->json('transaction.id');

        $this->actingAs($store['user'])->delete('/transactions/' . $txId)
            ->assertRedirect('/transactions');
        $this->assertSoftDeleted('transactions', ['id' => $txId]);

        $this->actingAs($store['user'])->get('/transactions?trash=1')
            ->assertStatus(200)
            ->assertSee('Sampah Transaksi Terhapus')
            ->assertSee('Andi Trash');

        $this->actingAs($store['user'])->post('/transactions/' . $txId . '/restore')
            ->assertRedirect('/transactions?trash=1')
            ->assertSessionHas('success');
        $this->assertNotSoftDeleted('transactions', ['id' => $txId]);

        // 3. Menu 1-click duplicate, CSV export, CSV template, and bulk CSV import
        $this->actingAs($store['user'])->post('/menus/' . $menu->id . '/duplicate')
            ->assertRedirect();
        $this->assertDatabaseHas('menus', [
            'tenant_id' => $store['tenant']->id,
            'name' => $menu->name . ' (Salinan)',
        ]);

        $tplResp = $this->actingAs($store['user'])->get('/menus/template');
        $tplResp->assertStatus(200);
        $this->assertStringContainsString('MNM-101', $tplResp->streamedContent());

        $csvContent = "Kode,Nama Menu,Kategori,Harga Jual,Margin Khusus (%),Status\nIMP-001,Coto Makassar,Masakan Khas,35000,40,aktif\nIMP-002,Es Pisang Ijo,Dessert Khas,18000,,aktif\n";
        $csvFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('menus.csv', $csvContent);
        $this->actingAs($store['user'])->post('/menus/import', ['csv_file' => $csvFile])
            ->assertRedirect('/menus')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('menus', ['tenant_id' => $store['tenant']->id, 'code' => 'IMP-001', 'name' => 'Coto Makassar']);
        $this->assertDatabaseHas('categories', ['tenant_id' => $store['tenant']->id, 'name' => 'Masakan Khas']);

        $menuCsvResp = $this->actingAs($store['user'])->get('/menus/export');
        $menuCsvResp->assertStatus(200);
        $this->assertStringContainsString('Coto Makassar', $menuCsvResp->streamedContent());

        // 4. Reports Peak Hours chart & CSV includes Tunai/QRIS breakdown
        $this->actingAs($store['user'])->get('/reports')
            ->assertStatus(200)
            ->assertSee('Analisa Jam Ramai (Peak Hours)')
            ->assertSee('peakHoursChart', false);

        $reportCsv = $this->actingAs($store['user'])->get('/reports/export');
        $reportCsv->assertStatus(200);
        $this->assertStringContainsString('Omzet Tunai (Rp)', $reportCsv->streamedContent());

        // 5. Tenant QRIS Webhook Test Ping & Prune Logs
        $this->actingAs($store['user'])->postJson('/settings/qris/test-webhook')
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->actingAs($store['user'])->post('/settings/qris/prune-logs')
            ->assertRedirect('/settings/qris')
            ->assertSessionHas('success');

        // 6. Feature Gating Enforcement: Lite plan with expenses=false & pdf_export=false blocks /expenses & /reports/pdf
        $litePlan = Plan::create([
            'name' => 'Lite Hemat',
            'slug' => 'lite-hemat-test',
            'price_monthly' => 19000,
            'price_yearly' => 190000,
            'is_active' => true,
            'sort_order' => 10,
            'features' => ['offline_sync' => true, 'expenses' => false, 'pdf_export' => false, 'qris_dynamic' => false],
        ]);
        $subService->assignSubscriptionManually($store['tenant'], $litePlan, 'monthly');

        $this->actingAs($store['user'])->get('/expenses')
            ->assertRedirect('/billing')
            ->assertSessionHas('error');
        $this->actingAs($store['user'])->get('/reports/pdf')
            ->assertRedirect('/billing')
            ->assertSessionHas('error');
    }

    public function test_round2_webhook_auth_hardening_logo_fallback_audit_csv_log_clear_target_and_tx_resolve(): void
    {
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();
        $subService = app(SubscriptionService::class);

        $store = $subService->provisionTenant(
            ['name' => 'Kedai Aman Sejati', 'business_type' => 'Kedai Minuman / Kopi'],
            ['name' => 'Owner Aman', 'username' => 'aman_owner', 'email' => 'aman@test.id', 'password' => 'secret123'],
            true
        );

        // 1. Tenant::logoUrl() fallback points to assets/images/logo.png (never broken images/logo.png)
        $this->assertStringContainsString('assets/images/logo.png', $store['tenant']->logoUrl());

        // 2. Webhook test-connection endpoints MUST reject unauthenticated probes with 401 when secret is configured
        PlatformSetting::setValue('platform_qris_secret', 'supersecretplatform123');
        $this->postJson('/listener/platform/test-connection', [])->assertStatus(401);
        $this->postJson('/listener/platform/test-connection', ['secret' => 'supersecretplatform123'])->assertStatus(200);

        $tenantSecret = Setting::withoutGlobalScope('tenant')
            ->where('tenant_id', $store['tenant']->id)
            ->where('key', 'qris_secret')
            ->value('value');
        $this->postJson('/listener/' . $store['tenant']->slug . '/test-connection', [])->assertStatus(401);
        $this->postJson('/listener/' . $store['tenant']->slug . '/test-connection', ['secret' => $tenantSecret])->assertStatus(200);

        // 3. Manual backoffice transaction supports customer_name, payment_method, and discount
        $menu = Menu::withoutGlobalScope('tenant')->where('tenant_id', $store['tenant']->id)->firstOrFail();
        $this->actingAs($store['user'])->post('/transactions', [
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_time' => '11:30',
            'customer_name' => 'Pelanggan Backoffice',
            'payment_method' => 'qris',
            'discount_type' => 'nominal',
            'discount_value' => 2000,
            'notes' => 'Order manual WA',
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
        ])->assertRedirect();

        $manualTx = Transaction::withoutGlobalScope('tenant')
            ->where('tenant_id', $store['tenant']->id)
            ->where('customer_name', 'Pelanggan Backoffice')
            ->firstOrFail();
        $this->assertEquals('qris', $manualTx->payment_method);
        $this->assertEquals(2000.0, (float) $manualTx->discount_amount);

        // 4. Backoffice quick mark-paid & cancel on pending transactions
        $pendingTx = Transaction::withoutGlobalScope('tenant')->create([
            'tenant_id' => $store['tenant']->id,
            'transaction_number' => 'TRX-PEND-R2-1',
            'transaction_date' => now()->toDateString(),
            'transaction_time' => '12:00:00',
            'total_quantity' => 1,
            'total_sales' => 15000,
            'final_amount' => 15234,
            'total_profit' => 5000,
            'estimated_cost' => 10000,
            'payment_method' => 'qris',
            'status' => 'pending',
            'created_by' => $store['user']->id,
        ]);
        $this->actingAs($store['user'])->get('/transactions/' . $pendingTx->id)
            ->assertStatus(200)
            ->assertSee('Tandai Lunas');
        $this->actingAs($store['user'])->post('/transactions/' . $pendingTx->id . '/mark-paid')
            ->assertRedirect('/transactions/' . $pendingTx->id)
            ->assertSessionHas('success');
        $this->assertEquals('paid', $pendingTx->fresh()->status);

        $pendingTx2 = Transaction::withoutGlobalScope('tenant')->create([
            'tenant_id' => $store['tenant']->id,
            'transaction_number' => 'TRX-PEND-R2-2',
            'transaction_date' => now()->toDateString(),
            'transaction_time' => '12:05:00',
            'total_quantity' => 1,
            'total_sales' => 15000,
            'final_amount' => 15235,
            'total_profit' => 5000,
            'estimated_cost' => 10000,
            'payment_method' => 'qris',
            'status' => 'pending',
            'created_by' => $store['user']->id,
        ]);
        $this->actingAs($store['user'])->post('/transactions/' . $pendingTx2->id . '/cancel')
            ->assertRedirect('/transactions/' . $pendingTx2->id)
            ->assertSessionHas('success');
        $this->assertEquals('cancelled', $pendingTx2->fresh()->status);

        // 5. Monthly Sales Target setting & Dashboard progress bar
        $this->actingAs($store['user'])->put('/settings/profit', [
            'global_profit_percentage' => 35,
            'monthly_sales_target' => 1000000,
        ])->assertRedirect('/settings/profit')->assertSessionHas('success');

        $this->actingAs($store['user'])->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Target Omzet')
            ->assertSee('1.000.000');

        // 6. Super Admin: Top 5 Active Tenants on Dashboard, Clear Log + Audit Trail record, and Audit Trail CSV export
        $this->actingAs($superAdmin)->get('/superadmin')
            ->assertStatus(200)
            ->assertSee('Top 5 Usaha Paling Aktif Bulan Ini')
            ->assertSee('Kedai Aman Sejati');

        $this->actingAs($superAdmin)->post('/superadmin/system-update/clear-log')
            ->assertRedirect()
            ->assertSessionHas('success');

        $auditCsv = $this->actingAs($superAdmin)->get('/superadmin/audit-logs/export');
        $auditCsv->assertStatus(200);
        $this->assertStringContainsString('system_clear_log', $auditCsv->streamedContent());
    }
}
