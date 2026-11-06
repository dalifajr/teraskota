<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class PackageInfinityFreeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:package-infinityfree {--force : Overwrite existing distribution packages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Package application files into core.zip and htdocs.zip optimized for InfinityFree shared hosting';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('===========================================================');
        $this->info('  TERAS KOTA - INFINITYFREE PACKAGE GENERATOR             ');
        $this->info('===========================================================');

        if (!class_exists('ZipArchive')) {
            $this->error('Ekstensi PHP ZipArchive tidak ditemukan pada sistem ini.');
            return Command::FAILURE;
        }

        // 1. Verify pre-built frontend assets exist
        $manifestPath = public_path('build/manifest.json');
        if (!file_exists($manifestPath)) {
            $this->warn('Peringatan: Pre-built aset Vite (public/build/manifest.json) belum ditemukan.');
            $this->line('Jalankan "npm run build" terlebih dahulu jika ingin menyertakan CSS/JS terkompilasi.');
        }

        $baseDir = base_path();
        $distDir = base_path('dist' . DIRECTORY_SEPARATOR . 'infinityfree');

        if (!is_dir($distDir)) {
            mkdir($distDir, 0755, true);
        }

        $coreZipPath = $distDir . DIRECTORY_SEPARATOR . 'core.zip';
        $htdocsZipPath = $distDir . DIRECTORY_SEPARATOR . 'htdocs.zip';
        $envExamplePath = $distDir . DIRECTORY_SEPARATOR . '.env.infinityfree.example';

        if (file_exists($coreZipPath) && !$this->option('force')) {
            if (!$this->confirm('File paket core.zip sudah ada. Apakah ingin menimpa (overwrite)?', true)) {
                $this->info('Operasi dibatalkan.');
                return Command::SUCCESS;
            }
        }

        // 2. Generate .env.infinityfree.example
        $this->info('[1/3] Menyiapkan template konfigurasi .env untuk InfinityFree...');
        $appKey = config('app.key') ?: 'base64:' . base64_encode(random_bytes(32));
        $envContent = <<<ENV
APP_NAME="Teras Kota"
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=https://nama-domain-anda.infinityfreeapp.com

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID

# Kredensial Database InfinityFree (Dapat dilihat di Control Panel InfinityFree)
DB_CONNECTION=mysql
DB_HOST=sql201.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_42684462_db_teras_kota
DB_USERNAME=if0_42684462
DB_PASSWORD=MASUKKAN_PASSWORD_AKUN_INFINITYFREE_ANDA

SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Path override untuk folder public di InfinityFree
LARAVEL_PUBLIC_PATH=../htdocs
ENV;
        file_put_contents($envExamplePath, $envContent);

        // 3. Build htdocs.zip
        $this->info('[2/3] Mengompres paket htdocs.zip (isi folder public)...');
        if (file_exists($htdocsZipPath)) {
            @unlink($htdocsZipPath);
        }

        $zipHtdocs = new ZipArchive();
        if ($zipHtdocs->open($htdocsZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Gagal membuat arsip {$htdocsZipPath}");
            return Command::FAILURE;
        }

        $publicDir = public_path();
        $this->addDirectoryToZip($zipHtdocs, $publicDir, '', ['index.php']);

        // Tambahkan custom index.php khusus untuk struktur InfinityFree (/htdocs -> ../core)
        $customIndexPhp = <<<PHP
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Cek mode pemeliharaan dari folder core
if (file_exists(\$maintenance = __DIR__.'/../core/storage/framework/maintenance.php')) {
    require \$maintenance;
}

// Muat autoloader Composer dari folder core
require __DIR__.'/../core/vendor/autoload.php';

// Inisialisasi aplikasi Laravel dari folder core
/** @var Application \$app */
\$app = require_once __DIR__.'/../core/bootstrap/app.php';

\$app->handleRequest(Request::capture());
PHP;
        $zipHtdocs->addFromString('index.php', $customIndexPhp);
        $zipHtdocs->close();

        // 4. Build core.zip
        $this->info('[3/3] Mengompres paket core.zip (backend aplikasi, vendor, & library)...');
        if (file_exists($coreZipPath)) {
            @unlink($coreZipPath);
        }

        $zipCore = new ZipArchive();
        if ($zipCore->open($coreZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Gagal membuat arsip {$coreZipPath}");
            return Command::FAILURE;
        }

        // Masukkan file-file single root
        $rootFiles = ['artisan', 'composer.json'];
        foreach ($rootFiles as $rf) {
            $fPath = $baseDir . DIRECTORY_SEPARATOR . $rf;
            if (file_exists($fPath)) {
                $zipCore->addFile($fPath, $rf);
            }
        }
        $zipCore->addFromString('.env.example', $envContent);

        // Masukkan direktori-direktori penting
        $folders = ['app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'vendor'];
        foreach ($folders as $folder) {
            $dirPath = $baseDir . DIRECTORY_SEPARATOR . $folder;
            if (is_dir($dirPath)) {
                $this->addDirectoryToZip($zipCore, $dirPath, $folder);
            }
        }

        // Buat struktur direktori storage yang bersih
        $storageDirs = [
            'storage/app/public',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
        ];
        foreach ($storageDirs as $sDir) {
            $zipCore->addEmptyDir($sDir);
            $zipCore->addFromString($sDir . '/.gitignore', "*\n!.gitignore\n");
        }

        $zipCore->close();

        $coreSizeMb = round(filesize($coreZipPath) / (1024 * 1024), 2);
        $htdocsSizeMb = round(filesize($htdocsZipPath) / (1024 * 1024), 2);

        $this->newLine();
        $this->info('===========================================================');
        $this->info('  PAKET DEPLOYMENT INFINITYFREE BERHASIL DIBUAT!           ');
        $this->info('===========================================================');
        $this->line("Direktori Paket : {$distDir}");
        $this->line("1. core.zip     : {$coreSizeMb} MB (Upload & Ekstrak di folder /core di luar htdocs)");
        $this->line("2. htdocs.zip   : {$htdocsSizeMb} MB (Upload & Ekstrak di dalam folder /htdocs)");
        $this->line("3. Template .env: {$envExamplePath}");
        $this->newLine();

        return Command::SUCCESS;
    }

    /**
     * Recursively add a directory to a zip archive.
     */
    protected function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPrefix = '', array $excludeFiles = []): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($dir) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);
            $zipPath = $zipPrefix !== '' ? $zipPrefix . '/' . $relativePath : $relativePath;

            if (in_array(basename($filePath), $excludeFiles, true)) {
                continue;
            }

            // Skip unwanted system files
            if (str_contains($zipPath, '.git/') || str_contains($zipPath, 'node_modules/')) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($zipPath);
            } elseif ($file->isFile()) {
                $zip->addFile($filePath, $zipPath);
            }
        }
    }
}

