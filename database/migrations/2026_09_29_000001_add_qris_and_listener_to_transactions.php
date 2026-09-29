<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add QRIS and status fields to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('status', 20)->default('paid')->after('payment_method')->index();
            $table->unsignedSmallInteger('unique_code')->nullable()->after('status');
            $table->decimal('final_amount', 15, 2)->nullable()->after('unique_code');
            $table->text('qris_payload')->nullable()->after('final_amount');
            $table->dateTime('qris_expired_at')->nullable()->after('qris_payload');
            $table->dateTime('paid_at')->nullable()->after('qris_expired_at');
            $table->string('payment_reference')->nullable()->after('paid_at');
            $table->string('payment_source_app')->nullable()->after('payment_reference');
            $table->dateTime('notified_at')->nullable()->after('payment_source_app')->index();
        });

        // 2. Create payment_listener_logs table for Android webhook audits
        Schema::create('payment_listener_logs', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('source_app')->nullable();
            $table->string('reference')->nullable();
            $table->text('raw_text')->nullable();
            $table->string('status', 30)->default('unmatched')->index();
            $table->foreignId('matched_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_listener_logs');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['notified_at']);
            $table->dropColumn([
                'status',
                'unique_code',
                'final_amount',
                'qris_payload',
                'qris_expired_at',
                'paid_at',
                'payment_reference',
                'payment_source_app',
                'notified_at',
            ]);
        });
    }
};
