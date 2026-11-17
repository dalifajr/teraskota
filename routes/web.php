<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SystemUpdateController;
use App\Http\Controllers\PaymentListenerController;
use App\Http\Controllers\QrisSettingController;
use App\Http\Controllers\ExpenseController;

// Android Payment Listener Webhook Routes (Exempted from CSRF)
Route::post('/listener/payment', [PaymentListenerController::class, 'payment']);
Route::post('/listener/test-connection', [PaymentListenerController::class, 'testConnection']);

// Emergency / Shared Hosting Web Migration Trigger
Route::get('/auto-migrate', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        // Jalankan Seeder Transaksi November jika ada
        if (class_exists(\Database\Seeders\November2026TransactionsSeeder::class)) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => \Database\Seeders\November2026TransactionsSeeder::class,
                '--force' => true
            ]);
            $output .= "\n" . \Illuminate\Support\Facades\Artisan::output();
        }

        \Illuminate\Support\Facades\Artisan::call('optimize:clear');

        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'role')) {
            \Illuminate\Support\Facades\DB::table('users')->where('username', 'admin')->update(['role' => 'admin']);
        }

        return response('
            <div style="font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; padding: 40px; max-width: 600px; margin: 60px auto; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px; color: #166534; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center;">
                <div style="font-size: 48px; margin-bottom: 12px;">✓</div>
                <h2 style="margin: 0 0 10px 0; font-size: 24px; color: #15803d;">Migrasi & Seeding Database Berhasil!</h2>
                <p style="font-size: 15px; line-height: 1.5; color: #166534; margin-bottom: 20px;">Seluruh tabel, kolom sistem, dan transaksi November 2026 telah disinkronkan.</p>
                <div style="text-align: left; background: #ffffff; padding: 14px; border-radius: 10px; border: 1px solid #dcfce7; font-size: 12px; font-family: monospace; overflow-x: auto; max-height: 200px; color: #374151; margin-bottom: 25px;">' . nl2br(htmlspecialchars($output ?: 'Basis data sudah berada pada versi terbaru.')) . '</div>
                <a href="/login" style="display: inline-block; background: #16a34a; color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 15px; box-shadow: 0 4px 12px rgba(22,163,74,0.3);">Masuk ke Halaman Login</a>
            </div>
        ');
    } catch (\Throwable $e) {
        return response('
            <div style="font-family: sans-serif; padding: 40px; max-width: 600px; margin: 60px auto; background: #fef2f2; border: 1px solid #fecaca; border-radius: 16px; color: #991b1b; text-align: center;">
                <h2 style="margin-top:0;">Gagal Menjalankan Migrasi Otomatis</h2>
                <p style="text-align: left; background: white; padding: 15px; border-radius: 8px; border: 1px solid #fee2e2; font-family: monospace; font-size: 13px;">' . htmlspecialchars($e->getMessage()) . '</p>
            </div>
        ', 500);
    }
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    
    // Redirect root based on role
    Route::get('/', function () {
        return Auth::user()->isKasir()
            ? redirect()->route('pos.index')
            : redirect()->route('dashboard');
    });

    // Dedicated POS Cashier Routes (Accessible by both Kasir and Admin)
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/qris/generate', [PosController::class, 'generateQris'])->name('pos.qris.generate');
    Route::get('/pos/qris/check-status/{transaction}', [PosController::class, 'checkQrisStatus'])->name('pos.qris.check-status');
    Route::post('/pos/qris/mark-paid/{transaction}', [PosController::class, 'markQrisPaid'])->name('pos.qris.mark-paid');
    Route::post('/pos/qris/cancel/{transaction}', [PosController::class, 'cancelQris'])->name('pos.qris.cancel');
    Route::get('/pos/receipt/{transaction}', [PosController::class, 'receipt'])->name('pos.receipt');
    Route::get('/pos/summary-today', [PosController::class, 'summaryToday'])->name('pos.summary');
    Route::get('/pos/bootstrap', [\App\Http\Controllers\OfflineSyncController::class, 'bootstrap'])->name('pos.bootstrap');
    Route::post('/pos/sync', [\App\Http\Controllers\OfflineSyncController::class, 'sync'])->name('pos.sync');

    // Profile Settings (Accessible by both Kasir and Admin)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Administrator Only Routes
    Route::middleware('role:admin')->group(function () {
        
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Master Resources
        Route::resource('transactions', TransactionController::class);
        Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('menus', MenuController::class);
        Route::resource('categories', CategoryController::class);

        // Reports
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

        // Settings (Profit)
        Route::get('/settings/profit', [SettingController::class, 'editProfit'])->name('settings.profit.edit');
        Route::put('/settings/profit', [SettingController::class, 'updateProfit'])->name('settings.profit.update');

        // Settings (QRIS & Webhook)
        Route::get('/settings/qris', [QrisSettingController::class, 'index'])->name('settings.qris.index');
        Route::put('/settings/qris', [QrisSettingController::class, 'update'])->name('settings.qris.update');
        Route::post('/settings/qris/regenerate-secret', [QrisSettingController::class, 'regenerateSecret'])->name('settings.qris.regenerate-secret');
        Route::post('/settings/qris/custom-secret', [QrisSettingController::class, 'updateCustomSecret'])->name('settings.qris.custom-secret');

        // Admin Browser Desktop Notifications Polling
        Route::get('/notifications/unread-transactions', [DashboardController::class, 'unreadTransactions'])->name('notifications.unread');

        // Settings (System Update via GitHub)
        Route::get('/settings/update', [SystemUpdateController::class, 'index'])->name('settings.update.index');
        Route::post('/settings/update/check', [SystemUpdateController::class, 'check'])->name('settings.update.check');
        Route::post('/settings/update/execute', [SystemUpdateController::class, 'execute'])->name('settings.update.execute');
        Route::post('/settings/update/migrate', [SystemUpdateController::class, 'migrateDb'])->name('settings.update.migrate');
        Route::post('/settings/update/clear-cache', [SystemUpdateController::class, 'clearCache'])->name('settings.update.clear-cache');

        // User Management
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});
