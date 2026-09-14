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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_method')->default('tunai')->after('estimated_cost');
            $table->decimal('cash_tendered', 15, 2)->nullable()->after('payment_method');
            $table->decimal('change_returned', 15, 2)->nullable()->after('cash_tendered');
            $table->string('customer_name')->nullable()->after('change_returned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'cash_tendered',
                'change_returned',
                'customer_name',
            ]);
        });
    }
};
