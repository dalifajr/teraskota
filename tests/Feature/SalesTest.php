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

    /**
     * Test pending, expired, and cancelled QRIS transactions do not pollute financial reports, dashboard, or POS summary.
     */
    public function test_pending_and_expired_qris_transactions_are_excluded_from_financial_metrics(): void
    {
        $admin = User::where('username', 'admin')->first();
        $cashier = User::where('username', 'kasir')->first();
        $menu = Menu::where('name', 'Jasmine Tea')->first(); // Rp4.000

        // 1. Create a valid PAID transaction (5 x 4000 = Rp20.000)
        $this->actingAs($cashier)->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 5]],
            'payment_method' => 'tunai',
            'cash_tendered' => 20000,
        ])->assertStatus(200);

        // 2. Generate a PENDING QRIS transaction (3 x 4000 = Rp12.000)
        $qrisRes = $this->actingAs($cashier)->postJson('/pos/qris/generate', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 3]],
            'customer_name' => 'Pending Buyer',
        ]);
        $qrisRes->assertStatus(200);
        $pendingTxId = $qrisRes->json('transaction_id');

        // 3. Create an overdue PENDING QRIS transaction that should auto-expire (2 x 4000 = Rp8.000)
        $overdueRes = $this->actingAs($cashier)->postJson('/pos/qris/generate', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
            'customer_name' => 'Overdue Buyer',
        ]);
        $overdueTxId = $overdueRes->json('transaction_id');
        Transaction::where('id', $overdueTxId)->update([
            'qris_expired_at' => now()->subMinutes(15),
        ]);

        // Verify POS summary today only includes the paid transaction (Rp20.000, 1 transaction, 5 qty)
        $summaryRes = $this->actingAs($cashier)->getJson('/pos/summary-today');
        $summaryRes->assertStatus(200)
            ->assertJson([
                'total_transactions' => 1,
                'total_sales' => 20000,
                'total_qty' => 5,
            ]);

        // Verify overdue transaction was automatically marked as expired
        $this->assertDatabaseHas('transactions', [
            'id' => $overdueTxId,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $pendingTxId,
            'status' => 'pending',
        ]);

        // Verify Reports page only counts Rp20.000 and not Rp40.000
        $today = now()->format('Y-m-d');
        $reportRes = $this->actingAs($admin)->get("/reports?period=custom&start_date={$today}&end_date={$today}");
        $reportRes->assertStatus(200);
        $reportRes->assertSee('20.000');
        $reportRes->assertDontSee('40.000');
    }

    /**
     * Test cashier can cancel an active pending QRIS transaction.
     */
    public function test_cashier_can_cancel_pending_qris_transaction(): void
    {
        $cashier = User::where('username', 'kasir')->first();
        $menu = Menu::first();

        $qrisRes = $this->actingAs($cashier)->postJson('/pos/qris/generate', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
        ]);
        $qrisRes->assertStatus(200);
        $txId = $qrisRes->json('transaction_id');

        $cancelRes = $this->actingAs($cashier)->postJson("/pos/qris/cancel/{$txId}");
        $cancelRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'transaction_status' => 'cancelled',
            ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $txId,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Test cash checkout rejects insufficient cash_tendered.
     */
    public function test_cash_checkout_rejects_insufficient_cash_tendered(): void
    {
        $cashier = User::where('username', 'kasir')->first();
        $menu = Menu::where('name', 'Jasmine Tea')->first(); // Rp4.000

        $response = $this->actingAs($cashier)->postJson('/pos/checkout', [
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 3], // Total Rp12.000
            ],
            'payment_method' => 'tunai',
            'cash_tendered' => 5000, // Less than Rp12.000
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseMissing('transactions', [
            'total_sales' => 12000.00,
            'cash_tendered' => 5000.00,
        ]);
    }

    /**
     * Test updating a transaction reconciles cash_tendered, change_returned, and final_amount.
     */
    public function test_updating_transaction_reconciles_payment_fields(): void
    {
        $admin = User::where('username', 'admin')->first();
        $menu = Menu::where('name', 'Jasmine Tea')->first(); // Rp4.000

        // Create initial cash transaction (2 x 4000 = 8000, paid with 20000 -> change 12000)
        $checkoutRes = $this->actingAs($admin)->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
            'payment_method' => 'tunai',
            'cash_tendered' => 20000,
        ]);
        $txId = $checkoutRes->json('transaction.id');

        // Admin edits quantity to 4 (4 x 4000 = 16000 -> change should become 4000)
        $this->actingAs($admin)->put("/transactions/{$txId}", [
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_time' => '14:00',
            'notes' => 'Updated qty',
            'items' => [['menu_id' => $menu->id, 'quantity' => 4]],
        ])->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'id' => $txId,
            'total_sales' => 16000.00,
            'cash_tendered' => 20000.00,
            'change_returned' => 4000.00,
        ]);
    }

    /**
     * Test Admin can record, update, and delete operational expenses and verify Net Profit in Reports/Dashboard.
     */
    public function test_admin_can_crud_operational_expenses_and_calculate_net_profit(): void
    {
        $admin = User::where('username', 'admin')->first();
        $cashier = User::where('username', 'kasir')->first();
        $menu = Menu::where('name', 'Jasmine Tea')->first(); // Rp4.000, 30% profit -> Rp1.200 profit per cup
        $today = now()->format('Y-m-d');

        // 1. Create a paid sale: 10 cups = Rp40.000 sales, Rp12.000 gross profit, Rp28.000 cost
        $this->actingAs($cashier)->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 10]],
            'payment_method' => 'tunai',
            'cash_tendered' => 50000,
        ])->assertStatus(200);

        // 2. Admin records an operational expense of Rp5.000 (e.g., Es Batu)
        $storeRes = $this->actingAs($admin)->post('/expenses', [
            'expense_date' => $today,
            'category' => 'Bahan Baku & Es',
            'description' => 'Beli Es Batu Kristal',
            'amount' => 5000,
            'payment_method' => 'tunai',
            'notes' => 'Warung sebelah',
        ]);
        $storeRes->assertRedirect('/expenses');

        $expense = \App\Models\Expense::first();
        $this->assertNotNull($expense);
        $this->assertEquals(5000.00, (float) $expense->amount);

        // 3. Check Reports page: Gross Profit Rp12.000 - Expense Rp5.000 = Net Profit Rp7.000
        $reportRes = $this->actingAs($admin)->get("/reports?period=custom&start_date={$today}&end_date={$today}");
        $reportRes->assertStatus(200);
        $reportRes->assertSee('12.000'); // Gross profit
        $reportRes->assertSee('5.000');  // Operational expense
        $reportRes->assertSee('7.000');  // Net profit

        // 4. Update expense to Rp3.000 -> Net Profit becomes Rp9.000
        $this->actingAs($admin)->put("/expenses/{$expense->id}", [
            'expense_date' => $today,
            'category' => 'Bahan Baku & Es',
            'description' => 'Beli Es Batu Kristal Revisi',
            'amount' => 3000,
            'payment_method' => 'tunai',
        ])->assertRedirect('/expenses');

        $dashboardRes = $this->actingAs($admin)->get("/dashboard?period=today");
        $dashboardRes->assertStatus(200);
        $dashboardRes->assertSee('9.000'); // Net profit on dashboard

        // 5. Delete expense
        $this->actingAs($admin)->delete("/expenses/{$expense->id}")->assertRedirect('/expenses');
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    /**
     * Test POS checkout with nominal and percentage discount calculates accurate sales, profit, and receipt.
     */
    public function test_pos_checkout_with_discounts_and_qris_discount(): void
    {
        $cashier = User::where('username', 'kasir')->first();
        $menu = Menu::where('name', 'Jasmine Tea')->first(); // Rp4.000, 30% profit

        // 1. Nominal discount: 5 cups = Rp20.000 subtotal, Cost = Rp14.000, Orig Profit = Rp6.000
        // Discount Rp2.000 -> Net Sales = Rp18.000, Net Profit = Rp4.000
        $resNominal = $this->actingAs($cashier)->postJson('/pos/checkout', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 5]],
            'payment_method' => 'tunai',
            'discount_type' => 'nominal',
            'discount_value' => 2000,
            'cash_tendered' => 20000,
        ]);
        $resNominal->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('transactions', [
            'subtotal_amount' => 20000.00,
            'discount_type' => 'nominal',
            'discount_value' => 2000.00,
            'discount_amount' => 2000.00,
            'total_sales' => 18000.00,
            'total_profit' => 4000.00,
            'estimated_cost' => 14000.00,
            'cash_tendered' => 20000.00,
            'change_returned' => 2000.00,
        ]);

        // Verify receipt HTML contains Diskon row
        $this->assertStringContainsString('Diskon', $resNominal->json('receipt_html'));
        $this->assertStringContainsString('-Rp2.000', $resNominal->json('receipt_html'));

        // 2. Percentage discount on QRIS generation: 5 cups = Rp20.000, 10% discount = Rp2.000 -> base_amount = Rp18.000
        $resQris = $this->actingAs($cashier)->postJson('/pos/qris/generate', [
            'items' => [['menu_id' => $menu->id, 'quantity' => 5]],
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);
        $resQris->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'subtotal_amount' => 20000,
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'discount_amount' => 2000,
                'base_amount' => 18000,
            ]);
    }
}

