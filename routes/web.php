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

// Android Payment Listener Webhook Routes (Exempted from CSRF)
Route::post('/listener/payment', [PaymentListenerController::class, 'payment']);
Route::post('/listener/test-connection', [PaymentListenerController::class, 'testConnection']);

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
