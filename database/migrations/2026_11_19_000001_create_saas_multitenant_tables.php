<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Platform Settings (Super Admin config: QRIS platform, bank transfer, support WA)
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // 2. Subscription Plans
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 60)->unique();
            $table->string('description', 255)->nullable();
            $table->decimal('price_monthly', 15, 2)->default(0);
            $table->decimal('price_yearly', 15, 2)->default(0);
            $table->unsignedInteger('max_users')->nullable(); // null = unlimited
            $table->unsignedInteger('max_menus')->nullable(); // null = unlimited
            $table->unsignedInteger('max_monthly_transactions')->nullable(); // null = unlimited
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. Tenants (Stores / Businesses)
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('business_type', 60)->default('F&B / Kedai');
            $table->string('logo_path', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('receipt_footer', 255)->default('Terima kasih atas kunjungan Anda!');
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_lifetime')->default(false);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Tenant Subscriptions
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('billing_cycle', 20)->default('trial'); // trial, monthly, yearly, lifetime
            $table->string('status', 20)->default('trial')->index(); // trial, active, past_due, expired, cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('grace_ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Subscription Invoices (Billing & Auto-Verify QRIS / Transfer)
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->string('billing_cycle', 20)->default('monthly'); // monthly, yearly
            $table->decimal('base_amount', 15, 2);
            $table->unsignedSmallInteger('unique_code')->default(0);
            $table->decimal('final_amount', 15, 2)->index();
            $table->string('payment_method', 30)->default('qris'); // qris, transfer, manual
            $table->text('qris_payload')->nullable();
            $table->timestamp('qris_expired_at')->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->string('payment_source_app', 80)->nullable();
            $table->string('status', 20)->default('unpaid')->index(); // unpaid, paid, expired, cancelled
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. Update users table: expand role to varchar(30) & attach tenant_id
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('kasir')->change();
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
        });

        // 7. Attach tenant_id to all 7 business data tables & replace global uniques with tenant-scoped uniques
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->dropUnique(['code']);
            $table->unique(['tenant_id', 'code'], 'menus_tenant_code_unique');
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->dropUnique(['key']);
            $table->unique(['tenant_id', 'key'], 'settings_tenant_key_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->dropUnique(['transaction_number']);
            $table->unique(['tenant_id', 'transaction_number'], 'transactions_tenant_number_unique');
            $table->dropUnique(['sync_id']);
            $table->unique(['tenant_id', 'sync_id'], 'transactions_tenant_sync_id_unique');
            $table->index(['tenant_id', 'transaction_date']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'transaction_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->dropUnique(['expense_number']);
            $table->unique(['tenant_id', 'expense_number'], 'expenses_tenant_number_unique');
            $table->index(['tenant_id', 'expense_date']);
        });

        Schema::table('payment_listener_logs', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->dropUnique(['idempotency_key']);
            $table->unique(['tenant_id', 'idempotency_key'], 'listener_logs_tenant_idemp_unique');
            $table->index(['tenant_id', 'status']);
        });

        // 8. Seed default Plans and migrate any existing single-tenant production data into Tenant #1 (Teras Kota)
        $now = now();

        $trialPlanId = DB::table('plans')->insertGetId([
            'name' => 'Free Trial',
            'slug' => 'trial',
            'description' => 'Coba semua fitur lengkap selama 14 hari tanpa komitmen.',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'max_users' => 2,
            'max_menus' => 30,
            'max_monthly_transactions' => null,
            'features' => json_encode([
                'offline_sync' => true,
                'expenses' => true,
                'pdf_export' => true,
                'qris_dynamic' => true,
                'custom_branding' => true,
            ]),
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('plans')->insert([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Cocok untuk booth, warung, dan kedai rintisan harian.',
            'price_monthly' => 39000,
            'price_yearly' => 390000,
            'max_users' => 2,
            'max_menus' => null,
            'max_monthly_transactions' => null,
            'features' => json_encode([
                'offline_sync' => true,
                'expenses' => true,
                'pdf_export' => true,
                'qris_dynamic' => false,
                'custom_branding' => true,
            ]),
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $proPlanId = DB::table('plans')->insertGetId([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Paket lengkap dengan QRIS Dinamis Auto-Verify & tim kasir besar.',
            'price_monthly' => 79000,
            'price_yearly' => 790000,
            'max_users' => 10,
            'max_menus' => null,
            'max_monthly_transactions' => null,
            'features' => json_encode([
                'offline_sync' => true,
                'expenses' => true,
                'pdf_export' => true,
                'qris_dynamic' => true,
                'custom_branding' => true,
            ]),
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Seed default platform settings
        $defaultPlatformSettings = [
            ['key' => 'platform_name', 'value' => config('platform.name', 'KasirLoka')],
            ['key' => 'platform_tagline', 'value' => config('platform.tagline', 'Aplikasi Kasir POS & Keuangan UMKM Anti-Offline')],
            ['key' => 'support_whatsapp', 'value' => ''],
            ['key' => 'bank_name', 'value' => 'BCA / Mandiri / BRI'],
            ['key' => 'bank_account_number', 'value' => ''],
            ['key' => 'bank_account_name', 'value' => ''],
            ['key' => 'platform_qris_payload', 'value' => ''],
            ['key' => 'platform_qris_secret', 'value' => bin2hex(random_bytes(16))],
            ['key' => 'trial_days', 'value' => '14'],
            ['key' => 'grace_days', 'value' => '3'],
        ];

        foreach ($defaultPlatformSettings as $ps) {
            DB::table('platform_settings')->insert(array_merge($ps, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Backfill existing data (e.g., Teras Kota live production database) into Tenant #1
        $hasExistingData = DB::table('users')->exists()
            || DB::table('categories')->exists()
            || DB::table('menus')->exists()
            || DB::table('transactions')->exists();

        if ($hasExistingData) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => 'Teras Kota Berlian Makmur',
                'slug' => 'teras-kota',
                'business_type' => 'Kedai Minuman & Kuliner',
                'address' => 'Berlian Makmur',
                'receipt_footer' => 'Terima Kasih Atas Kunjungan Anda',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
                'is_lifetime' => true,
                'trial_ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('subscriptions')->insert([
                'tenant_id' => $tenantId,
                'plan_id' => $proPlanId,
                'billing_cycle' => 'lifetime',
                'status' => 'active',
                'starts_at' => $now,
                'ends_at' => null,
                'grace_ends_at' => null,
                'notes' => 'Tenant Pendiri (VIP Lifetime)',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('users')->where('role', '!=', 'super_admin')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            DB::table('categories')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            DB::table('menus')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            DB::table('settings')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            DB::table('transactions')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            DB::table('transaction_details')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            DB::table('expenses')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            DB::table('payment_listener_logs')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_listener_logs', function (Blueprint $table) {
            $table->dropUnique('listener_logs_tenant_idemp_unique');
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('idempotency_key');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_tenant_number_unique');
            $table->dropIndex(['tenant_id', 'expense_date']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('expense_number');
        });

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'transaction_id']);
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_tenant_number_unique');
            $table->dropUnique('transactions_tenant_sync_id_unique');
            $table->dropIndex(['tenant_id', 'transaction_date']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('transaction_number');
            $table->unique('sync_id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique('settings_tenant_key_unique');
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('key');
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->dropUnique('menus_tenant_code_unique');
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('code');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('platform_settings');
    }
};
