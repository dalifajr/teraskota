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
    protected $description = 'Package application files into an all-in-one zip optimized for InfinityFree /htdocs folder upload';

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

        $allInOneZipPath = $distDir . DIRECTORY_SEPARATOR . 'teraskota_infinityfree_htdocs.zip';
        $coreZipPath = $distDir . DIRECTORY_SEPARATOR . 'core.zip';
        $htdocsZipPath = $distDir . DIRECTORY_SEPARATOR . 'htdocs.zip';
        $envExamplePath = $distDir . DIRECTORY_SEPARATOR . '.env.infinityfree.example';

        if (file_exists($allInOneZipPath) && !$this->option('force')) {
            if (!$this->confirm('File paket teraskota_infinityfree_htdocs.zip sudah ada. Apakah ingin menimpa (overwrite)?', true)) {
                $this->info('Operasi dibatalkan.');
                return Command::SUCCESS;
            }
        }

        // 2. Generate .env configuration for InfinityFree
        $this->info('[1/3] Menyiapkan konfigurasi .env otomatis untuk InfinityFree...');
        $envContent = <<<ENV
APP_NAME="Teras Kota"
APP_ENV=production
APP_KEY=base64:2gobdG/VD+A9OsRU/gAqlZ316Naf0j/e4g9bHO5HKKs=
APP_DEBUG=true
APP_TIMEZONE=Asia/Jakarta
APP_URL=https://teraskota.rf.gd

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID

# Kredensial Database InfinityFree
DB_CONNECTION=mysql
DB_HOST=sql103.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_42907576_teraskota
DB_USERNAME=if0_42907576
DB_PASSWORD=Endpoint1140

SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Path override untuk folder public di InfinityFree jika core ada di dalam htdocs
LARAVEL_PUBLIC_PATH=..
ENV;
        file_put_contents($envExamplePath, $envContent);
        file_put_contents($distDir . DIRECTORY_SEPARATOR . '.env', $envContent);

        // Content of index.php for htdocs
        $htdocsIndexPhp = <<<PHP
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Cek mode pemeliharaan dari folder core
if (file_exists(\$maintenance = __DIR__.'/core/storage/framework/maintenance.php')) {
    require \$maintenance;
}

// Muat autoloader Composer dari folder core
require __DIR__.'/core/vendor/autoload.php';

// Inisialisasi aplikasi Laravel dari folder core
/** @var Application \$app */
\$app = require_once __DIR__.'/core/bootstrap/app.php';

\$app->handleRequest(Request::capture());
PHP;

        // Content of .htaccess for core/ folder protection
        $coreHtaccess = <<<HTACCESS
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
HTACCESS;

        // 3. Build ALL-IN-ONE PACKAGE (teraskota_infinityfree_htdocs.zip)
        $this->info('[2/3] Mengompres paket ALL-IN-ONE (teraskota_infinityfree_htdocs.zip)...');
        if (file_exists($allInOneZipPath)) {
            @unlink($allInOneZipPath);
        }

        $zipAll = new ZipArchive();
        if ($zipAll->open($allInOneZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Gagal membuat arsip {$allInOneZipPath}");
            return Command::FAILURE;
        }

        // A. Add public files directly to the root of zip
        $publicDir = public_path();
        $this->addDirectoryToZip($zipAll, $publicDir, '', ['index.php']);
        $zipAll->addFromString('index.php', $htdocsIndexPhp);

        // B. Add core folder files under "core/"
        $zipAll->addEmptyDir('core');
        $zipAll->addFromString('core/.htaccess', $coreHtaccess);
        $zipAll->addFromString('core/.env', $envContent);
        $zipAll->addFromString('core/.env.example', $envContent);

        $rootFiles = ['artisan', 'composer.json'];
        foreach ($rootFiles as $rf) {
            $fPath = $baseDir . DIRECTORY_SEPARATOR . $rf;
            if (file_exists($fPath)) {
                $zipAll->addFile($fPath, 'core/' . $rf);
            }
        }

        $folders = ['app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'vendor'];
        foreach ($folders as $folder) {
            $dirPath = $baseDir . DIRECTORY_SEPARATOR . $folder;
            if (is_dir($dirPath)) {
                $this->addDirectoryToZip($zipAll, $dirPath, 'core/' . $folder);
            }
        }

        $storageDirs = [
            'core/storage/app/public',
            'core/storage/framework/cache/data',
            'core/storage/framework/sessions',
            'core/storage/framework/views',
            'core/storage/logs',
            'core/storage/fonts',
            'core/public',
        ];
        foreach ($storageDirs as $sDir) {
            $zipAll->addEmptyDir($sDir);
            $zipAll->addFromString($sDir . '/.gitignore', "*\n!.gitignore\n");
        }

        $zipAll->close();

        // 4. Also keep legacy split core.zip & htdocs.zip
        $this->info('[3/3] Memperbarui paket split core.zip & htdocs.zip...');
        if (file_exists($coreZipPath)) @unlink($coreZipPath);
        if (file_exists($htdocsZipPath)) @unlink($htdocsZipPath);

        $zipCore = new ZipArchive();
        if ($zipCore->open($coreZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zipCore->addFromString('.htaccess', $coreHtaccess);
            $zipCore->addFromString('.env', $envContent);
            $zipCore->addFromString('.env.example', $envContent);
            foreach ($rootFiles as $rf) {
                $fPath = $baseDir . DIRECTORY_SEPARATOR . $rf;
                if (file_exists($fPath)) $zipCore->addFile($fPath, $rf);
            }
            foreach ($folders as $folder) {
                $dirPath = $baseDir . DIRECTORY_SEPARATOR . $folder;
                if (is_dir($dirPath)) $this->addDirectoryToZip($zipCore, $dirPath, $folder);
            }
            foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/fonts', 'public'] as $sDir) {
                $zipCore->addEmptyDir($sDir);
                $zipCore->addFromString($sDir . '/.gitignore', "*\n!.gitignore\n");
            }
            $zipCore->close();
        }

        $zipHtdocs = new ZipArchive();
        if ($zipHtdocs->open($htdocsZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $this->addDirectoryToZip($zipHtdocs, $publicDir, '', ['index.php']);
            $zipHtdocs->addFromString('index.php', $htdocsIndexPhp);
            $zipHtdocs->close();
        }

        $allSizeMb = round(filesize($allInOneZipPath) / (1024 * 1024), 2);

        $this->newLine();
        $this->info('===========================================================');
        $this->info('  PAKET ALL-IN-ONE INFINITYFREE BERHASIL DIBUAT!           ');
        $this->info('===========================================================');
        $this->line("Direktori Paket   : {$distDir}");
        $this->line("Paket Utama (HTDOCS): teraskota_infinityfree_htdocs.zip ({$allSizeMb} MB)");
        $this->line("Cara Pakai        : Upload LANGSUNG ke dalam folder 'htdocs' di File Manager lalu Extract.");
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
