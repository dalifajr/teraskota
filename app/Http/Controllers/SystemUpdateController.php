<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class SystemUpdateController extends Controller
{
    /**
     * Path to application root directory.
     */
    protected function getAppPath(): string
    {
        return base_path();
    }

    /**
     * Helper to run safe shell commands in the application root directory.
     */
    protected function runCommand(string $command): array
    {
        $cwd = $this->getAppPath();
        $fullCommand = "cd /d \"{$cwd}\" && {$command} 2>&1";
        $output = shell_exec($fullCommand);

        return [
            'command' => $command,
            'output' => trim($output ?? ''),
        ];
    }

    /**
     * Display the System Update dashboard.
     */
    public function index()
    {
        // Git Information
        $gitVersion = $this->runCommand('git --version')['output'];
        $currentBranch = $this->runCommand('git branch --show-current')['output'];
        $remoteUrl = $this->runCommand('git config --get remote.origin.url')['output'];
        
        // Latest Local Commit
        $lastCommitHash = $this->runCommand('git log -1 --format="%h"')['output'];
        $lastCommitMsg = $this->runCommand('git log -1 --format="%s"')['output'];
        $lastCommitDate = $this->runCommand('git log -1 --format="%cd" --date=relative')['output'];
        $lastCommitAuthor = $this->runCommand('git log -1 --format="%an"')['output'];

        // Environment Details
        $phpVersion = PHP_VERSION;
        $laravelVersion = app()->version();

        return view('settings.update', compact(
            'gitVersion',
            'currentBranch',
            'remoteUrl',
            'lastCommitHash',
            'lastCommitMsg',
            'lastCommitDate',
            'lastCommitAuthor',
            'phpVersion',
            'laravelVersion'
        ));
    }

    /**
     * Check for updates from GitHub remote repository.
     */
    public function check()
    {
        try {
            // 1. Fetch remote changes
            $fetchRes = $this->runCommand('git fetch origin main');

            // 2. Compare local vs remote
            $localHash = $this->runCommand('git rev-parse HEAD')['output'];
            $remoteHash = $this->runCommand('git rev-parse origin/main')['output'];

            if (empty($remoteHash) || str_contains($remoteHash, 'fatal:')) {
                return back()->with('error', 'Gagal menghubungi remote repositori: ' . $remoteHash);
            }

            if ($localHash === $remoteHash) {
                return back()->with('success', 'Sistem Anda sudah menggunakan versi TERBARU (Commit: ' . substr($localHash, 0, 7) . ').');
            }

            // Count commits behind
            $behindCount = $this->runCommand('git rev-list HEAD..origin/main --count')['output'];
            $newCommits = $this->runCommand('git log HEAD..origin/main --oneline -n 5')['output'];

            return back()->with('update_available', [
                'count' => $behindCount,
                'commits' => $newCommits,
                'remote_hash' => substr($remoteHash, 0, 7),
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Error saat memeriksa pembaruan: ' . $e->getMessage());
        }
    }

    /**
     * Execute the 5-stage safe update pipeline.
     */
    public function execute(Request $request)
    {
        $log = [];
        $log[] = '=== MEMULAI PROSES PEMBARUAN SISTEM TERAS KOTA ===';
        $log[] = 'Waktu: ' . Carbon::now()->toDateTimeString();

        try {
            // TAHAP 1: Mode Pemeliharaan
            $log[] = "\n[Tahap 1/5] Mengaktifkan Mode Pemeliharaan (Maintenance Mode)...";
            Artisan::call('down', ['--retry' => 60]);
            $log[] = 'Status: Sistem sementara dialihkan ke mode pemeliharaan.';

            // TAHAP 2: Git Pull
            $log[] = "\n[Tahap 2/5] Menarik kode pembaruan dari GitHub (git pull origin main)...";
            $pullRes = $this->runCommand('git pull origin main');
            $log[] = $pullRes['output'];

            // TAHAP 3: Database Migration
            $log[] = "\n[Tahap 3/5] Menjalankan migrasi basis data (php artisan migrate --force)...";
            Artisan::call('migrate', ['--force' => true]);
            $log[] = Artisan::output();

            // TAHAP 4: Clear & Optimize Cache
            $log[] = "\n[Tahap 4/5] Membersihkan cache bytecode, view, route, dan config...";
            Artisan::call('optimize:clear');
            $log[] = Artisan::output();

            // TAHAP 5: Resume Application
            $log[] = "\n[Tahap 5/5] Mengaktifkan kembali sistem (php artisan up)...";
            Artisan::call('up');
            $log[] = 'Status: Aplikasi kembali AKTIF dan siap digunakan!';

            $log[] = "\n=== PEMBARUAN SISTEM BERHASIL DILAKUKAN ===";

            return back()->with('update_log', implode("\n", $log))
                         ->with('success', 'Pembaruan sistem berhasil diselesaikan!');
        } catch (\Exception $e) {
            // Ensure system comes back up if failed
            Artisan::call('up');
            $log[] = "\n[ERROR]: " . $e->getMessage();
            $log[] = 'Aplikasi telah diaktifkan kembali dari mode pemeliharaan.';

            return back()->with('update_log', implode("\n", $log))
                         ->with('error', 'Pembaruan sistem gagal: ' . $e->getMessage());
        }
    }

    /**
     * Clear application caches manually.
     */
    public function clearCache()
    {
        Artisan::call('optimize:clear');
        return back()->with('success', 'Semua cache (konfigurasi, rute, view terkompilasi) berhasil dibersihkan!');
    }
}
