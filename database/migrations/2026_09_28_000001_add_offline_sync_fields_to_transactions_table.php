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
            $table->uuid('sync_id')->nullable()->unique()->after('id');
            $table->string('source_device_id', 100)->nullable()->after('customer_name');
            $table->string('sync_status', 30)->default('synced')->after('source_device_id');
            $table->timestamp('synced_at')->nullable()->after('sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'sync_id',
                'source_device_id',
                'sync_status',
                'synced_at',
            ]);
        });
    }
};
