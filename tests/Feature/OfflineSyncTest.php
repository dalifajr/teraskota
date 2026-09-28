<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Menu $sampleMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->cashier = User::where('username', 'kasir')->first();
        $this->sampleMenu = Menu::where('status', true)->first();
    }

    public function test_guest_cannot_access_pos_bootstrap_or_sync(): void
    {
        $this->getJson('/pos/bootstrap')->assertStatus(401);
        $this->postJson('/pos/sync', [])->assertStatus(401);
    }

    public function test_cashier_can_bootstrap_offline_master_data(): void
    {
        $response = $this->actingAs($this->cashier)->getJson('/pos/bootstrap');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'cashier' => ['id', 'name', 'role'],
                'settings' => ['global_profit_percentage'],
                'categories',
                'menus',
                'server_time',
            ])
            ->assertJson([
                'success' => true,
                'cashier' => [
                    'id' => $this->cashier->id,
                    'name' => $this->cashier->name,
                ],
            ]);

        $this->assertNotEmpty($response->json('categories'));
        $this->assertNotEmpty($response->json('menus'));
    }

    public function test_cashier_can_sync_single_offline_transaction(): void
    {
        $syncId = (string) Str::uuid();
        $qty = 2;
        $expectedTotal = (float) $this->sampleMenu->price * $qty;

        $payload = [
            'sync_id' => $syncId,
            'source_device_id' => 'DEV-TEST-01',
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_time' => now()->format('H:i:s'),
            'payment_method' => 'tunai',
            'cash_tendered' => $expectedTotal + 10000,
            'change_returned' => 10000,
            'customer_name' => 'Budi Santoso',
            'notes' => 'Catatan pesanan meja 5',
            'items' => [
                [
                    'menu_id' => $this->sampleMenu->id,
                    'quantity' => $qty,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson('/pos/sync', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'sync_id' => $syncId,
                'status' => 'synced',
            ]);

        $this->assertDatabaseHas('transactions', [
            'sync_id' => $syncId,
            'source_device_id' => 'DEV-TEST-01',
            'payment_method' => 'tunai',
            'customer_name' => 'Budi Santoso',
            'total_quantity' => $qty,
            'total_sales' => $expectedTotal,
            'created_by' => $this->cashier->id,
        ]);
    }

    public function test_sync_endpoint_is_idempotent(): void
    {
        $syncId = (string) Str::uuid();
        $payload = [
            'sync_id' => $syncId,
            'source_device_id' => 'DEV-TEST-01',
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_time' => now()->format('H:i:s'),
            'payment_method' => 'tunai',
            'cash_tendered' => 50000,
            'items' => [
                [
                    'menu_id' => $this->sampleMenu->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // First submission
        $firstResponse = $this->actingAs($this->cashier)->postJson('/pos/sync', $payload);
        $firstResponse->assertStatus(200);
        $firstNumber = $firstResponse->json('transaction_number');

        // Duplicate submission with identical sync_id (e.g. network retry)
        $secondResponse = $this->actingAs($this->cashier)->postJson('/pos/sync', $payload);
        $secondResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'sync_id' => $syncId,
                'transaction_number' => $firstNumber,
            ]);

        // Ensure database ONLY contains 1 transaction with this sync_id
        $this->assertEquals(1, Transaction::where('sync_id', $syncId)->count());
    }

    public function test_cashier_can_sync_batch_offline_transactions(): void
    {
        $syncId1 = (string) Str::uuid();
        $syncId2 = (string) Str::uuid();

        $batchPayload = [
            'transactions' => [
                [
                    'sync_id' => $syncId1,
                    'transaction_date' => now()->format('Y-m-d'),
                    'transaction_time' => '10:00:00',
                    'payment_method' => 'tunai',
                    'cash_tendered' => 30000,
                    'items' => [
                        ['menu_id' => $this->sampleMenu->id, 'quantity' => 1],
                    ],
                ],
                [
                    'sync_id' => $syncId2,
                    'transaction_date' => now()->format('Y-m-d'),
                    'transaction_time' => '10:15:00',
                    'payment_method' => 'tunai',
                    'cash_tendered' => 60000,
                    'items' => [
                        ['menu_id' => $this->sampleMenu->id, 'quantity' => 2],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson('/pos/sync', $batchPayload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'synced_count' => 2,
                'total_count' => 2,
            ]);

        $this->assertEquals(1, Transaction::where('sync_id', $syncId1)->count());
        $this->assertEquals(1, Transaction::where('sync_id', $syncId2)->count());
    }

    public function test_sync_fails_gracefully_with_invalid_menu(): void
    {
        $syncId = (string) Str::uuid();
        $invalidPayload = [
            'sync_id' => $syncId,
            'payment_method' => 'tunai',
            'items' => [
                ['menu_id' => 999999, 'quantity' => 1], // non-existent menu
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson('/pos/sync', $invalidPayload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertEquals(0, Transaction::where('sync_id', $syncId)->count());
    }
}
