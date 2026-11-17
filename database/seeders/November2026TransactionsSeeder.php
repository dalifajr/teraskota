<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class November2026TransactionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlPath = database_path('seeders/sql/tambahan_transaksi_november_2026_terbaru_fixed.sql');

        if (!File::exists($sqlPath)) {
            $this->command?->error("File SQL tidak ditemukan di {$sqlPath}");
            return;
        }

        $sql = File::get($sqlPath);

        // Eksekusi SQL migrasi data transaksi
        DB::unprepared($sql);

        // Pastikan final_amount terisi sama dengan total_sales jika NULL
        DB::table('transactions')
            ->whereNull('final_amount')
            ->update(['final_amount' => DB::raw('total_sales')]);

        $count = DB::table('transactions')
            ->where('transaction_date', '>=', '2026-11-01')
            ->where('transaction_date', '<', '2026-12-01')
            ->whereNull('deleted_at')
            ->count();

        $this->command?->info("Seeding transaksi November 2026 selesai! Total transaksi November: {$count}");
    }
}
