<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Run seeders for default database states
        $this->seed();
    }

    /**
     * Test admin login and authentication limits.
     */
    public function test_admin_can_login_with_correct_credentials(): void
    {
        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_cashier_can_login_and_redirects_to_pos(): void
    {
        $response = $this->post('/login', [
            'username' => 'kasir',
            'password' => 'kasir123',
        ]);

        $response->assertRedirect('/pos');
        $this->assertAuthenticated();
    }

    public function test_cashier_can_access_pos_and_cannot_access_admin_dashboard(): void
    {
        $cashier = User::where('username', 'kasir')->first();

        // Cashier can access POS
        $posResponse = $this->actingAs($cashier)->get('/pos');
        $posResponse->assertStatus(200);
        $posResponse->assertSee('Terminal Kasir');

        // Cashier is forbidden from admin dashboard & reports
        $dashboardResponse = $this->actingAs($cashier)->get('/dashboard');
        $dashboardResponse->assertRedirect('/pos');

        $reportsResponse = $this->actingAs($cashier)->get('/reports');
        $reportsResponse->assertRedirect('/pos');
    }

    public function test_guest_can_view_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Teras Kota');
        $response->assertSee('Masuk');
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get('/dashboard');
        
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * Test adding menu and validation.
     */
    public function test_admin_can_create_menu_with_validation(): void
    {
        $admin = User::where('username', 'admin')->first();
        $category = Category::first();

        // Validate creation fails without code/name
        $response = $this->actingAs($admin)
            ->post('/menus', [
                'price' => 5000,
                'category_id' => $category->id,
            ]);

        $response->assertSessionHasErrors(['code', 'name']);

        // Create successfully
        $response = $this->actingAs($admin)
            ->post('/menus', [
                'code' => 'TEA-999',
                'name' => 'Test Green Tea',
                'price' => 12000,
                'category_id' => $category->id,
                'use_global_profit' => 1,
            ]);

        $response->assertRedirect('/menus');
        $this->assertDatabaseHas('menus', [
            'code' => 'TEA-999',
            'name' => 'Test Green Tea',
            'price' => 12000.00,
        ]);
    }

    /**
     * Test POS Transaction creation and math logic.
     */
    public function test_transaction_calculates_correct_totals_with_global_profit(): void
    {
        $admin = User::where('username', 'admin')->first();
        
        // Let's retrieve a menu. Jasmine Tea is seeded by default at Rp4.000. Global profit is 30%.
        $menu = Menu::where('name', 'Jasmine Tea')->first();
        $this->assertEquals(4000.00, $menu->price);
        $this->assertTrue($menu->use_global_profit);

        $response = $this->actingAs($admin)
            ->post('/transactions', [
                'transaction_date' => '2026-07-29',
                'transaction_time' => '13:00',
                'notes' => 'Test POS Transaction',
                'items' => [
                    [
                        'menu_id' => $menu->id,
                        'quantity' => 5, // Total Sales = 5 * 4000 = 20000
                    ]
                ]
            ]);

        $response->assertRedirect();
        
        // Assert transaction header calculations
        // Profit 30% of 20000 = 6000. Cost = 20000 - 6000 = 14000.
        $this->assertDatabaseHas('transactions', [
            'total_quantity' => 5,
            'total_sales' => 20000.00,
            'total_profit' => 6000.00,
            'estimated_cost' => 14000.00,
            'notes' => 'Test POS Transaction',
        ]);

        // Assert transaction detail calculations
        $this->assertDatabaseHas('transaction_details', [
            'menu_name_snapshot' => 'Jasmine Tea',
            'price_snapshot' => 4000.00,
            'profit_percentage_snapshot' => 30.00,
            'quantity' => 5,
            'subtotal' => 20000.00,
            'profit_amount' => 6000.00,
            'estimated_cost' => 14000.00,
        ]);
    }

    public function test_transaction_calculates_correct_totals_with_custom_profit(): void
    {
        $admin = User::where('username', 'admin')->first();
        $category = Category::first();

        // Create menu with custom 40% profit
        $menu = Menu::create([
            'category_id' => $category->id,
            'code' => 'CUST-001',
            'name' => 'Custom Profit Drink',
            'price' => 10000,
            'profit_percentage' => 40,
            'use_global_profit' => false,
            'status' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post('/transactions', [
                'transaction_date' => '2026-07-29',
                'transaction_time' => '13:00',
                'items' => [
                    [
                        'menu_id' => $menu->id,
                        'quantity' => 3, // Total Sales = 3 * 10000 = 30000
                    ]
                ]
            ]);

        // Profit 40% of 30000 = 12000. Cost = 30000 - 12000 = 18000.
        $this->assertDatabaseHas('transactions', [
            'total_quantity' => 3,
            'total_sales' => 30000.00,
            'total_profit' => 12000.00,
            'estimated_cost' => 18000.00,
        ]);

        $this->assertDatabaseHas('transaction_details', [
            'menu_name_snapshot' => 'Custom Profit Drink',
            'price_snapshot' => 10000.00,
            'profit_percentage_snapshot' => 40.00,
            'quantity' => 3,
            'subtotal' => 30000.00,
            'profit_amount' => 12000.00,
            'estimated_cost' => 18000.00,
        ]);
    }

    /**
     * Test menu changes does not affect old snapshots.
     */
    public function test_price_change_does_not_affect_old_transactions(): void
    {
        $admin = User::where('username', 'admin')->first();
        $menu = Menu::where('name', 'Jasmine Tea')->first();

        // 1. Make a purchase at Rp4.000
        $response = $this->actingAs($admin)
            ->post('/transactions', [
                'transaction_date' => '2026-07-29',
                'transaction_time' => '10:00',
                'items' => [
                    [
                        'menu_id' => $menu->id,
                        'quantity' => 2, // Total = 8000
                    ]
                ]
            ]);

        $this->assertDatabaseHas('transactions', [
            'total_sales' => 8000.00,
        ]);

        // 2. Change the menu price from Rp4.000 to Rp6.000
        $menu->update(['price' => 6000]);

        // 3. Verify database still contains old transaction with price Rp4.000
        $this->assertDatabaseHas('transaction_details', [
            'menu_name_snapshot' => 'Jasmine Tea',
            'price_snapshot' => 4000.00,
            'subtotal' => 8000.00,
        ]);

        // 4. Create new transaction after update, should use the new price Rp6.000
        $response2 = $this->actingAs($admin)
            ->post('/transactions', [
                'transaction_date' => '2026-07-29',
                'transaction_time' => '11:00',
                'items' => [
                    [
                        'menu_id' => $menu->id,
                        'quantity' => 2, // Total = 12000
                    ]
                ]
            ]);

        $this->assertDatabaseHas('transactions', [
            'total_sales' => 12000.00,
        ]);

        $this->assertDatabaseHas('transaction_details', [
            'menu_name_snapshot' => 'Jasmine Tea',
            'price_snapshot' => 6000.00,
            'subtotal' => 12000.00,
        ]);
    }

    /**
     * Test filtering reports.
     */
    public function test_reports_filter_by_date(): void
    {
        $admin = User::where('username', 'admin')->first();
        $menu = Menu::where('name', 'Jasmine Tea')->first();

        // Transaction 1 (Yesterday)
        $t1 = Transaction::create([
            'transaction_number' => 'TRX-20260728-0001',
            'transaction_date' => '2026-07-28',
            'transaction_time' => '12:00:00',
            'total_quantity' => 10,
            'total_sales' => 40000.00,
            'total_profit' => 12000.00,
            'estimated_cost' => 28000.00,
            'created_by' => $admin->id,
        ]);
        \App\Models\TransactionDetail::create([
            'transaction_id' => $t1->id,
            'menu_id' => $menu->id,
            'menu_name_snapshot' => $menu->name,
            'category_name_snapshot' => $menu->category->name,
            'price_snapshot' => $menu->price,
            'profit_percentage_snapshot' => 30.00,
            'quantity' => 10,
            'subtotal' => 40000.00,
            'profit_amount' => 12000.00,
            'estimated_cost' => 28000.00,
        ]);

        // Transaction 2 (Today)
        $t2 = Transaction::create([
            'transaction_number' => 'TRX-20260729-0001',
            'transaction_date' => '2026-07-29',
            'transaction_time' => '12:00:00',
            'total_quantity' => 5,
            'total_sales' => 20000.00,
            'total_profit' => 6000.00,
            'estimated_cost' => 14000.00,
            'created_by' => $admin->id,
        ]);
        \App\Models\TransactionDetail::create([
            'transaction_id' => $t2->id,
            'menu_id' => $menu->id,
            'menu_name_snapshot' => $menu->name,
            'category_name_snapshot' => $menu->category->name,
            'price_snapshot' => $menu->price,
            'profit_percentage_snapshot' => 30.00,
            'quantity' => 5,
            'subtotal' => 20000.00,
            'profit_amount' => 6000.00,
            'estimated_cost' => 14000.00,
        ]);

        // Access report index filtered to 'today' (2026-07-29)
        $response = $this->actingAs($admin)
            ->get('/reports?period=custom&start_date=2026-07-29&end_date=2026-07-29');

        $response->assertStatus(200);
        $response->assertSee('20.000'); // Today's sales
        $response->assertDontSee('40.000'); // Yesterday's sales
    }

    /**
     * Test POS Cashier checkout with cash payment and change calculation.
     */
    public function test_cashier_can_checkout_pos_transaction(): void
    {
        $cashier = User::where('username', 'kasir')->first();
        $menu = Menu::where('name', 'Jasmine Tea')->first(); // Rp4.000

        $response = $this->actingAs($cashier)
            ->postJson('/pos/checkout', [
                'items' => [
                    [
                        'menu_id' => $menu->id,
                        'quantity' => 3, // Total = 12.000
                    ]
                ],
                'payment_method' => 'tunai',
                'cash_tendered' => 20000,
                'customer_name' => 'Budi Sudarsono',
                'notes' => 'Less sugar',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify database has transaction with POS data
        $this->assertDatabaseHas('transactions', [
            'total_quantity' => 3,
            'total_sales' => 12000.00,
            'payment_method' => 'tunai',
            'cash_tendered' => 20000.00,
            'change_returned' => 8000.00,
            'customer_name' => 'Budi Sudarsono',
            'created_by' => $cashier->id,
        ]);
    }

    /**
     * Test POS receipt rendering.
     */
    public function test_pos_receipt_can_be_rendered(): void
    {
        $cashier = User::where('username', 'kasir')->first();
        $menu = Menu::first();
        $trx = Transaction::create([
            'transaction_number' => 'TRX-TEST-0001',
            'transaction_date' => '2026-09-14',
            'transaction_time' => '10:00:00',
            'total_quantity' => 1,
            'total_sales' => 4000.00,
            'total_profit' => 1200.00,
            'estimated_cost' => 2800.00,
            'payment_method' => 'tunai',
            'cash_tendered' => 5000.00,
            'change_returned' => 1000.00,
            'customer_name' => 'Test Customer',
            'created_by' => $cashier->id,
        ]);
        \App\Models\TransactionDetail::create([
            'transaction_id' => $trx->id,
            'menu_id' => $menu->id,
            'menu_name_snapshot' => $menu->name,
            'category_name_snapshot' => $menu->category->name,
            'price_snapshot' => $menu->price,
            'profit_percentage_snapshot' => 30.00,
            'quantity' => 1,
            'subtotal' => 4000.00,
            'profit_amount' => 1200.00,
            'estimated_cost' => 2800.00,
        ]);

        $response = $this->actingAs($cashier)->get('/pos/receipt/' . $trx->id);
        $response->assertStatus(200);
        $response->assertSee('Teras Kota');
        $response->assertSee($trx->transaction_number);
        // Ensure no global universal selector is polluting fonts
        $response->assertDontSee("* {\n            margin: 0;\n            padding: 0;\n            box-sizing: border-box;\n            font-family: 'Courier New'", false);
        $response->assertSee('.receipt-card,', false);
    }

    /**
     * Test POS checkout returns receipt HTML without duplicate buttons when called via AJAX.
     */
    public function test_pos_checkout_returns_receipt_html_without_duplicate_buttons_in_ajax(): void
    {
        $cashier = User::where('username', 'kasir')->first();
        $menu = Menu::first();

        $response = $this->actingAs($cashier)
            ->postJson('/pos/checkout', [
                'items' => [
                    [
                        'menu_id' => $menu->id,
                        'quantity' => 2,
                    ]
                ],
                'payment_method' => 'tunai',
                'cash_tendered' => 50000,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $receiptHtml = $response->json('receipt_html');
        // Ensure duplicate button block is NOT rendered in AJAX/modal mode
        $this->assertStringNotContainsString('class="btn-print"', $receiptHtml);
        $this->assertStringNotContainsString('class="btn-close-receipt"', $receiptHtml);
        // Ensure receipt card is present
        $this->assertStringContainsString('id="printableReceipt"', $receiptHtml);
    }

    /**
     * Test Admin can manage users.
     */
    public function test_admin_can_manage_users(): void
    {
        $admin = User::where('username', 'admin')->first();

        // Admin can view user list
        $response = $this->actingAs($admin)->get('/users');
        $response->assertStatus(200);
        $response->assertSee('Daftar Akun Pengguna');

        // Admin can create a new cashier
        $createResponse = $this->actingAs($admin)->post('/users', [
            'name' => 'Kasir Sore Baru',
            'username' => 'kasirsore',
            'email' => 'sore@teraskota.local',
            'password' => 'secret123',
            'role' => 'kasir',
        ]);

        $createResponse->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'username' => 'kasirsore',
            'role' => 'kasir',
        ]);
    }

    /**
     * Test System Update page loads gracefully.
     */
    public function test_system_update_page_loads_gracefully(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get('/settings/update');
        $response->assertStatus(200);
        $response->assertSee('Sinkronisasi & Pembaruan Sistem', false);
        $response->assertSee('Migrasi DB');
    }

    /**
     * Test Admin can run database migration via web endpoint (safe for shared hosting like InfinityFree).
     */
    public function test_admin_can_run_database_migration_via_web(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->post('/settings/update/migrate');
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}

