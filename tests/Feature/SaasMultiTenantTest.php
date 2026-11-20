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
}
