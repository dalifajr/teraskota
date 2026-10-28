<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class MigrateLegacyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teraskota:migrate-data 
                            {file? : Path file SQL dump (default: if0_42684462_db_teras_kota.sql)}
                            {--fresh : Hapus data lama di tabel bisnis sebelum import}
                            {--force : Jalankan tanpa konfirmasi interaktif di mode production}
                            {--generate-sql : Hasilkan juga file database/sql/data_only_teraskota.sql}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrasi data operasional (menus, categories, transactions, users, settings) dari file dump SQL ke skema sistem saat ini';

    /**
     * Tabel bisnis yang diekstrak datanya.
     *
     * @var array
     */
    protected array $targetTables = [
        'users',
        'categories',
        'menus',
        'settings',
        'transactions',
        'transaction_details',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file') ?: base_path('if0_42684462_db_teras_kota.sql');

        if (!File::exists($filePath)) {
            $this->error("File SQL tidak ditemukan pada path: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("Memulai ekstraksi data dari: {$filePath}");
        $fileSizeMb = round(filesize($filePath) / 1024 / 1024, 2);
        $this->line("Ukuran file: {$fileSizeMb} MB");

        // 1. Ekstrak INSERT statements
        $statements = $this->extractInsertStatements($filePath);

        if (empty($statements)) {
            $this->error("Tidak ditemukan statement INSERT INTO yang cocok untuk tabel target.");
            return Command::FAILURE;
        }

        $this->info("Ditemukan " . count($statements) . " blok INSERT INTO untuk data bisnis.");

        // Jika opsi --generate-sql aktif, buat file SQL data-only (dengan error handling aman)
        if ($this->option('generate-sql')) {
            $this->generateDataOnlySql($statements);
        }

        // 2. Konfirmasi eksekusi jika di production
        if (app()->environment('production') && !$this->option('force')) {
            if (!$this->confirm('Aplikasi berjalan di mode PRODUCTION. Lanjutkan proses migrasi data?', true)) {
                $this->warn("Operasi dibatalkan oleh user.");
                return Command::SUCCESS;
            }
        }

        $this->newLine();
        $this->info("Menjalankan migrasi data ke database...");

        DB::disableQueryLog();

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

            // Truncate tabel jika opsi fresh dipilih (default direkomendasikan agar tidak bentrok ID)
            $this->warn("Membersihkan data lama pada tabel target agar tidak ada konflik ID/Unique Key...");
            
            // Truncate listener logs jika ada relasi
            if (DB::getSchemaBuilder()->hasTable('payment_listener_logs')) {
                DB::table('payment_listener_logs')->truncate();
            }

            foreach (array_reverse($this->targetTables) as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }

            // Eksekusi tiap blok INSERT
            $bar = $this->output->createProgressBar(count($statements));
            $bar->start();

            foreach ($statements as $stmt) {
                DB::unprepared($stmt);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // 3. Post-migration data patching
            $this->info("Melakukan penyesuaian data pasca-migrasi:");

            // A. Pastikan user admin memiliki role = 'admin'
            $adminUpdated = DB::table('users')
                ->where('username', 'admin')
                ->update(['role' => 'admin']);
            $this->line(" - Hak akses user admin diset ke: 'admin' (Rows: {$adminUpdated})");

            // B. Pastikan user kasir tersedia untuk POS
            $kasirExists = DB::table('users')->where('username', 'kasir')->exists();
            if (!$kasirExists) {
                User::create([
                    'name' => 'Kasir POS Teras Kota',
                    'username' => 'kasir',
                    'email' => 'kasir@teraskota.local',
                    'password' => Hash::make('kasir123'),
                    'role' => 'kasir',
                ]);
                $this->line(" - Dibuat user kasir default: username 'kasir', password 'kasir123'");
            }

            // C. Rapikan kolom transaksi lama (final_amount, status, payment_method, sync_status)
            $txAmountUpdated = DB::table('transactions')
                ->whereNull('final_amount')
                ->update(['final_amount' => DB::raw('total_sales')]);
            $this->line(" - Menyamakan final_amount = total_sales pada transaksi lama (Rows: {$txAmountUpdated})");

            DB::table('transactions')
                ->whereNull('status')
                ->orWhere('status', '')
                ->update(['status' => 'paid']);

            DB::table('transactions')
                ->whereNull('payment_method')
                ->orWhere('payment_method', '')
                ->update(['payment_method' => 'tunai']);

            DB::table('transactions')
                ->whereNull('sync_status')
                ->orWhere('sync_status', '')
                ->update(['sync_status' => 'synced']);

            // D. Bersihkan cache & session tabel bawaan jika ada
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->truncate();
            }
            if (DB::getSchemaBuilder()->hasTable('cache')) {
                DB::table('cache')->truncate();
            }
            $this->line(" - Membersihkan tabel cache dan sessions lama");

            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            $this->error("Terjadi error saat migrasi data: " . $e->getMessage());
            return Command::FAILURE;
        }

        // 4. Tampilkan Ringkasan Hasil Migrasi
        $this->newLine();
        $this->info("=== RINGKASAN DATA BERHASIL DIMIGRASIKAN ===");

        $tableData = [];
        foreach ($this->targetTables as $table) {
            $count = DB::getSchemaBuilder()->hasTable($table) ? DB::table($table)->count() : 0;
            $tableData[] = [$table, number_format($count)];
        }

        $this->table(['Tabel', 'Jumlah Record Saat Ini'], $tableData);
        $this->info("Migrasi data selesai dengan sukses!");

        return Command::SUCCESS;
    }

    /**
     * Ekstrak blok INSERT INTO untuk tabel target secara streaming.
     */
    protected function extractInsertStatements(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        $statements = [];
        $currentStmt = '';
        $capturing = false;

        $targetPattern = '/^INSERT\s+INTO\s+`(' . implode('|', $this->targetTables) . ')`/i';

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            if (!$capturing) {
                if (preg_match($targetPattern, $trimmed)) {
                    $capturing = true;
                    $currentStmt = $line;
                }
            } else {
                $currentStmt .= $line;
            }

            if ($capturing && str_ends_with($trimmed, ';')) {
                $statements[] = $currentStmt;
                $currentStmt = '';
                $capturing = false;
            }
        }

        fclose($handle);
        return $statements;
    }

    /**
     * Buat file SQL data-only murni yang siap pakai.
     */
    protected function generateDataOnlySql(array $statements): void
    {
        try {
            $sqlDir = base_path('database/sql');
            if (!File::isDirectory($sqlDir)) {
                File::makeDirectory($sqlDir, 0755, true);
            }

            $outputPath = $sqlDir . '/data_only_teraskota.sql';

            $header = "-- ========================================================\n";
            $header .= "-- Teras Kota — Data-Only Migration Dump\n";
            $header .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
            $header .= "-- Berisi HANYA data operasional tanpa merusak skema tabel\n";
            $header .= "-- ========================================================\n\n";
            $header .= "SET FOREIGN_KEY_CHECKS = 0;\n";
            $header .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

            // Truncate commands
            $header .= "-- 1. Membersihkan data lama agar ID tidak bentrok\n";
            foreach (array_reverse($this->targetTables) as $table) {
                $header .= "TRUNCATE TABLE `{$table}`;\n";
            }
            $header .= "\n-- 2. Memasukkan data asli dari InfinityFree\n";

            $body = implode("\n", $statements) . "\n\n";

            $footer = "-- 3. Penyesuaian Kolom Sistem Baru (Role & QRIS)\n";
            $footer .= "UPDATE `users` SET `role` = 'admin' WHERE `username` = 'admin';\n";
            $footer .= "UPDATE `transactions` SET `final_amount` = `total_sales` WHERE `final_amount` IS NULL;\n";
            $footer .= "UPDATE `transactions` SET `status` = 'paid' WHERE `status` IS NULL OR `status` = '';\n";
            $footer .= "UPDATE `transactions` SET `payment_method` = 'tunai' WHERE `payment_method` IS NULL OR `payment_method` = '';\n";
            $footer .= "UPDATE `transactions` SET `sync_status` = 'synced' WHERE `sync_status` IS NULL OR `sync_status` = '';\n\n";
            $footer .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            File::put($outputPath, $header . $body . $footer);
            $this->info("File SQL data-only tersimpan di: {$outputPath}");
        } catch (\Throwable $e) {
            $this->warn("Skip pembuatan file SQL data-only (permission file/direktori terbatas): " . $e->getMessage());
        }
    }
}
