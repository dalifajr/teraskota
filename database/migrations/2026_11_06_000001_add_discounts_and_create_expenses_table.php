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
        // 1. Add discount columns to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('subtotal_amount', 15, 2)->default(0)->after('total_quantity');
            $table->string('discount_type', 20)->nullable()->after('subtotal_amount'); // 'nominal' or 'percentage'
            $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_value');
        });

        // 2. Create operational expenses table
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 30)->unique();
            $table->date('expense_date')->index();
            $table->string('category', 60)->index();
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 20)->default('tunai');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_amount',
                'discount_type',
                'discount_value',
                'discount_amount',
            ]);
        });
    }
};

