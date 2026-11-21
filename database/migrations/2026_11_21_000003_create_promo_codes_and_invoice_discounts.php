<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('promo_codes')) {
            Schema::create('promo_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 120);
                $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
                $table->decimal('discount_value', 12, 2);
                $table->decimal('max_discount_amount', 12, 2)->nullable();
                $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('subscription_invoices')) {
            Schema::table('subscription_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('subscription_invoices', 'promo_code_id')) {
                    $table->foreignId('promo_code_id')->nullable()->after('plan_id')->constrained('promo_codes')->nullOnDelete();
                }
                if (!Schema::hasColumn('subscription_invoices', 'promo_code_snapshot')) {
                    $table->string('promo_code_snapshot', 50)->nullable()->after('promo_code_id');
                }
                if (!Schema::hasColumn('subscription_invoices', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->default(0)->after('base_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscription_invoices')) {
            Schema::table('subscription_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('subscription_invoices', 'promo_code_id')) {
                    $table->dropConstrainedForeignId('promo_code_id');
                }
                if (Schema::hasColumn('subscription_invoices', 'promo_code_snapshot')) {
                    $table->dropColumn('promo_code_snapshot');
                }
                if (Schema::hasColumn('subscription_invoices', 'discount_amount')) {
                    $table->dropColumn('discount_amount');
                }
            });
        }

        Schema::dropIfExists('promo_codes');
    }
};
