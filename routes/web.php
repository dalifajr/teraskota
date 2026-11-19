<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OfflineSyncController;
use App\Http\Controllers\PaymentListenerController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrisSettingController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StoreSettingController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SystemUpdateController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;

// =========================================================================
// 1. ANDROID PAYMENT LISTENER WEBHOOKS (CSRF Exempt)
// =========================================================================
// Platform SaaS Billing Auto-Verify Listener (Super Admin)
Route::post('/listener/platform/payment', [PaymentListenerController::class, 'platformPayment'])->name('listener.platform.payment');
Route::post('/listener/platform/test-connection', [PaymentListenerController::class, 'platformTestConnection'])->name('listener.platform.test');

// Legacy single-tenant fallback (Teras Kota #1)
Route::post('/listener/payment', [PaymentListenerController::class, 'payment'])->name('listener.legacy.payment');
Route::post('/listener/test-connection', [PaymentListenerController::class, 'testConnection'])->name('listener.legacy.test');

// Multi-Tenant Webhook Routes per store slug
Route::post('/listener/{tenantSlug}/payment', [PaymentListenerController::class, 'payment'])->name('listener.tenant.payment');
Route::post('/listener/{tenantSlug}/test-connection', [PaymentListenerController::class, 'testConnection'])->name('listener.tenant.test');

// =========================================================================
// 2. EMERGENCY / SHARED HOSTING WEB MIGRATION TRIGGER
// =========================================================================
Route::get('/auto-migrate', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        if (class_exists(\Database\Seeders\November2026TransactionsSeeder::class)) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => \Database\Seeders\November2026TransactionsSeeder::class,
                '--force' => true,
            ]);
            $output .= "\n" . \Illuminate\Support\Facades\Artisan::output();
        }

        \Illuminate\Support\Facades\Artisan::call('optimize:clear');

        return response('
            <div style="font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; padding: 40px; max-width: 600px; margin: 60px auto; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px; color: #166534; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center;">
                <div style="font-size: 48px; margin-bottom: 12px;">✓</div>
                <h2 style="margin: 0 0 10px 0; font-size: 24px; color: #15803d;">Migrasi Database Berhasil!</h2>
                <div style="text-align: left; background: #ffffff; padding: 14px; border-radius: 10px; border: 1px solid #dcfce7; font-size: 12px; font-family: monospace; overflow-x: auto; max-height: 220px; color: #374151; margin-bottom: 25px;">' . nl2br(htmlspecialchars($output ?: 'Basis data sudah berada pada versi terbaru.')) . '</div>
                <a href="/login" style="display: inline-block; background: #16a34a; color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 15px;">Masuk ke Halaman Login</a>
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

// =========================================================================
// 3. PUBLIC LANDING PAGE & AUTHENTICATION
// =========================================================================
Route::get('/', [LandingController::class, 'index'])->name('landing');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

// =========================================================================
// 4. AUTHENTICATED ROUTES
// =========================================================================
Route::middleware('auth')->group(function () {

    // Stop Super Admin Tenant Impersonation (Accessible while impersonating)
    Route::post('/superadmin/stop-impersonate', [SuperAdminController::class, 'stopImpersonate'])
        ->middleware('role:super_admin')
        ->name('superadmin.stop-impersonate');

    // Profile Settings (Accessible by all authenticated users)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // ---------------------------------------------------------------------
    // A. SUPER ADMIN PLATFORM CONTROL PANEL (KasirLoka Owner)
    // ---------------------------------------------------------------------
    Route::prefix('superadmin')
        ->name('superadmin.')
        ->middleware('role:super_admin')
        ->group(function () {
            Route::get('/', [SuperAdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/tenants', [SuperAdminController::class, 'tenants'])->name('tenants');
            Route::post('/tenants/{tenant}/subscription', [SuperAdminController::class, 'updateTenantSubscription'])->name('tenants.subscription');
            Route::post('/tenants/{tenant}/toggle', [SuperAdminController::class, 'toggleTenantStatus'])->name('tenants.toggle');
            Route::post('/tenants/{tenant}/impersonate', [SuperAdminController::class, 'impersonateTenant'])->name('tenants.impersonate');

            Route::get('/invoices', [SuperAdminController::class, 'invoices'])->name('invoices');
            Route::post('/invoices/{invoice}/approve', [SuperAdminController::class, 'approveInvoice'])->name('invoices.approve');
            Route::post('/invoices/{invoice}/cancel', [SuperAdminController::class, 'cancelInvoice'])->name('invoices.cancel');

            Route::get('/plans', [SuperAdminController::class, 'plans'])->name('plans');
            Route::put('/plans/{plan}', [SuperAdminController::class, 'updatePlan'])->name('plans.update');

            Route::get('/settings', [SuperAdminController::class, 'settings'])->name('settings');
            Route::put('/settings', [SuperAdminController::class, 'updateSettings'])->name('settings.update');

            // System Update via GitHub (Super Admin Only)
            Route::get('/system-update', [SystemUpdateController::class, 'index'])->name('update.index');
            Route::post('/system-update/check', [SystemUpdateController::class, 'check'])->name('update.check');
            Route::post('/system-update/execute', [SystemUpdateController::class, 'execute'])->name('update.execute');
            Route::post('/system-update/migrate', [SystemUpdateController::class, 'migrateDb'])->name('update.migrate');
            Route::post('/system-update/clear-cache', [SystemUpdateController::class, 'clearCache'])->name('update.clear-cache');
        });

    // ---------------------------------------------------------------------
    // B. DEDICATED POS CASHIER ROUTES (Requires Active/Trial/Grace Subscription)
    // ---------------------------------------------------------------------
    Route::middleware(['role:admin,kasir', 'subscription:active'])->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::post('/pos/qris/generate', [PosController::class, 'generateQris'])
            ->middleware('subscription:qris_dynamic')
            ->name('pos.qris.generate');
        Route::get('/pos/qris/check-status/{transaction}', [PosController::class, 'checkQrisStatus'])->name('pos.qris.check-status');
        Route::post('/pos/qris/mark-paid/{transaction}', [PosController::class, 'markQrisPaid'])->name('pos.qris.mark-paid');
        Route::post('/pos/qris/cancel/{transaction}', [PosController::class, 'cancelQris'])->name('pos.qris.cancel');
        Route::get('/pos/receipt/{transaction}', [PosController::class, 'receipt'])->name('pos.receipt');
        Route::get('/pos/summary-today', [PosController::class, 'summaryToday'])->name('pos.summary');
        Route::get('/pos/bootstrap', [OfflineSyncController::class, 'bootstrap'])->name('pos.bootstrap');
        Route::post('/pos/sync', [OfflineSyncController::class, 'sync'])->name('pos.sync');
    });

    // ---------------------------------------------------------------------
    // C. TENANT ADMINISTRATOR ROUTES
    // ---------------------------------------------------------------------
    Route::middleware('role:admin')->group(function () {
        // Billing & Subscription Management (Always accessible even when expired!)
        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
        Route::get('/billing/invoice/{invoice}/status', [BillingController::class, 'checkInvoiceStatus'])->name('billing.invoice.status');
        Route::post('/billing/invoice/{invoice}/cancel', [BillingController::class, 'cancelInvoice'])->name('billing.invoice.cancel');

        // Read-only when expired; writes blocked by CheckSubscription
        Route::middleware('subscription')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/notifications/unread-transactions', [DashboardController::class, 'unreadTransactions'])->name('notifications.unread');

            Route::resource('transactions', TransactionController::class);
            Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('menus', MenuController::class);
            Route::resource('categories', CategoryController::class);
            Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);

            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
            Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

            // Store Profile & White-Label Branding
            Route::get('/settings/store', [StoreSettingController::class, 'edit'])->name('settings.store.edit');
            Route::put('/settings/store', [StoreSettingController::class, 'update'])->name('settings.store.update');

            // Profit Margin Settings
            Route::get('/settings/profit', [SettingController::class, 'editProfit'])->name('settings.profit.edit');
            Route::put('/settings/profit', [SettingController::class, 'updateProfit'])->name('settings.profit.update');

            // QRIS & Android Webhook Settings (Pro & Trial feature)
            Route::middleware('subscription:qris_dynamic')->group(function () {
                Route::get('/settings/qris', [QrisSettingController::class, 'index'])->name('settings.qris.index');
                Route::put('/settings/qris', [QrisSettingController::class, 'update'])->name('settings.qris.update');
                Route::post('/settings/qris/regenerate-secret', [QrisSettingController::class, 'regenerateSecret'])->name('settings.qris.regenerate-secret');
                Route::post('/settings/qris/custom-secret', [QrisSettingController::class, 'updateCustomSecret'])->name('settings.qris.custom-secret');
            });

            // Legacy System Update routes kept for founding tenant / superadmin compatibility
            Route::get('/settings/update', [SystemUpdateController::class, 'index'])->name('settings.update.index');
            Route::post('/settings/update/check', [SystemUpdateController::class, 'check'])->name('settings.update.check');
            Route::post('/settings/update/execute', [SystemUpdateController::class, 'execute'])->name('settings.update.execute');
            Route::post('/settings/update/migrate', [SystemUpdateController::class, 'migrateDb'])->name('settings.update.migrate');
            Route::post('/settings/update/clear-cache', [SystemUpdateController::class, 'clearCache'])->name('settings.update.clear-cache');
        });
    });
});
