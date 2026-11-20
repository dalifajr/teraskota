<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_listener_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_listener_logs', 'is_platform_billing')) {
                $table->boolean('is_platform_billing')->default(false)->after('tenant_id')->index();
            }
            if (!Schema::hasColumn('payment_listener_logs', 'matched_invoice_id')) {
                $table->foreignId('matched_invoice_id')
                    ->nullable()
                    ->after('matched_transaction_id')
                    ->constrained('subscription_invoices')
                    ->nullOnDelete();
            }
        });

        $now = now();
        $defaults = [
            ['key' => 'announcement_enabled', 'value' => '0'],
            ['key' => 'announcement_type', 'value' => 'info'],
            ['key' => 'announcement_message', 'value' => ''],
        ];

        foreach ($defaults as $row) {
            $exists = DB::table('platform_settings')->where('key', $row['key'])->exists();
            if (!$exists) {
                DB::table('platform_settings')->insert(array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::table('payment_listener_logs', function (Blueprint $table) {
            if (Schema::hasColumn('payment_listener_logs', 'matched_invoice_id')) {
                $table->dropConstrainedForeignId('matched_invoice_id');
            }
            if (Schema::hasColumn('payment_listener_logs', 'is_platform_billing')) {
                $table->dropColumn('is_platform_billing');
            }
        });
    }
};
