<?php

namespace App\Http\Controllers;

use App\Models\PaymentListenerLog;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\PromoCode;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\QrisService;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    protected SubscriptionService $subscriptionService;
    protected QrisService $qrisService;

    public function __construct(SubscriptionService $subscriptionService, QrisService $qrisService)
    {
        $this->subscriptionService = $subscriptionService;
        $this->qrisService = $qrisService;
    }

    public function dashboard()
    {
        SubscriptionInvoice::expireOverdue();

        // Always ensure we are in global platform context on superadmin routes
        $tenants = Tenant::with(['activeSubscription.plan'])
            ->withCount(['users', 'menus', 'transactions'])
            ->latest()
            ->get();

        $totalTenants = $tenants->count();
        $activeTenants = $tenants->filter(fn($t) => in_array($t->subscriptionState(), ['active', 'lifetime'], true))->count();
        $paidActiveTenants = $tenants->filter(fn($t) => !$t->is_lifetime && $t->subscriptionState() === 'active')->count();
        $nonLifetimeTenants = $tenants->filter(fn($t) => !$t->is_lifetime)->count();
        $trialTenants = $tenants->filter(fn($t) => $t->subscriptionState() === 'trial')->count();
        $graceTenants = $tenants->filter(fn($t) => $t->subscriptionState() === 'grace')->count();
        $expiredTenants = $tenants->filter(fn($t) => in_array($t->subscriptionState(), ['expired', 'suspended'], true))->count();

        // Watchlist: tenants expiring in <= 7 days or currently in grace period (prime conversion/retention leads)
        $expiringSoonTenants = $tenants->filter(function ($t) {
            $state = $t->subscriptionState();
            if ($state === 'grace') {
                return true;
            }
            if (in_array($state, ['active', 'trial'], true)) {
                $days = $t->daysRemaining();
                return $days !== null && $days <= 7;
            }
            return false;
        })->sortBy(fn($t) => $t->daysRemaining() ?? 999)->take(8)->values();

        // Estimate Monthly Recurring Revenue (MRR) from active paid non-lifetime subscriptions
        $mrr = 0.0;
        foreach ($tenants as $t) {
            if ($t->is_lifetime || $t->subscriptionState() !== 'active') {
                continue;
            }
            $sub = $t->activeSubscription;
            if ($sub && $sub->plan) {
                $mrr += $sub->billing_cycle === 'yearly'
                    ? ((float) $sub->plan->price_yearly / 12)
                    : (float) $sub->plan->price_monthly;
            }
        }

        // Advanced SaaS Health Metrics
        $conversionRatePct = $nonLifetimeTenants > 0
            ? round(($paidActiveTenants / $nonLifetimeTenants) * 100, 1)
            : 0.0;
        $arpu = $paidActiveTenants > 0 ? round($mrr / $paidActiveTenants, 0) : 0.0;
        $platformGmvThisMonth = (float) Transaction::withoutGlobalScope('tenant')
            ->paid()
            ->whereYear('transaction_date', Carbon::now()->year)
            ->whereMonth('transaction_date', Carbon::now()->month)
            ->sum('total_sales');

        $revenueThisMonth = (float) SubscriptionInvoice::where('status', 'paid')
            ->whereYear('paid_at', Carbon::now()->year)
            ->whereMonth('paid_at', Carbon::now()->month)
            ->sum('base_amount');

        $revenueAllTime = (float) SubscriptionInvoice::where('status', 'paid')->sum('base_amount');
        $pendingInvoicesCount = SubscriptionInvoice::where('status', 'unpaid')->count();
        $totalPlatformTransactions = Transaction::withoutGlobalScope('tenant')->paid()->count();

        // 6-Month SaaS Revenue & New Tenant Acquisition Trend for Chart.js
        $monthlyTrend = [
            'labels' => [],
            'revenues' => [],
            'new_tenants' => [],
        ];
        for ($i = 5; $i >= 0; $i--) {
            $m = Carbon::now()->startOfMonth()->subMonths($i);
            $monthlyTrend['labels'][] = $m->translatedFormat('M Y');
            $monthlyTrend['revenues'][] = (float) SubscriptionInvoice::where('status', 'paid')
                ->whereYear('paid_at', $m->year)
                ->whereMonth('paid_at', $m->month)
                ->sum('base_amount');
            $monthlyTrend['new_tenants'][] = Tenant::whereYear('created_at', $m->year)
                ->whereMonth('created_at', $m->month)
                ->count();
        }

        $recentTenants = $tenants->take(8);
        $pendingInvoices = SubscriptionInvoice::with(['tenant', 'plan'])
            ->where('status', 'unpaid')
            ->latest()
            ->take(8)
            ->get();

        // Top 5 Most Active Tenants This Month (by paid POS transaction count & GMV)
        $monthlyTxByTenant = Transaction::withoutGlobalScope('tenant')
            ->paid()
            ->whereYear('transaction_date', Carbon::now()->year)
            ->whereMonth('transaction_date', Carbon::now()->month)
            ->select(
                'tenant_id',
                DB::raw('COUNT(*) as month_tx_count'),
                DB::raw('SUM(total_sales) as month_gmv')
            )
            ->groupBy('tenant_id')
            ->orderByDesc('month_gmv')
            ->take(5)
            ->get();

        $tenantsKeyed = $tenants->keyBy('id');
        $topActiveTenants = $monthlyTxByTenant->map(function ($row) use ($tenantsKeyed) {
            $t = $tenantsKeyed->get($row->tenant_id);
            if (!$t) {
                return null;
            }
            return (object) [
                'tenant' => $t,
                'tx_count' => (int) $row->month_tx_count,
                'gmv' => (float) $row->month_gmv,
            ];
        })->filter()->values();

        return view('superadmin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'paidActiveTenants',
            'trialTenants',
            'graceTenants',
            'expiredTenants',
            'expiringSoonTenants',
            'topActiveTenants',
            'monthlyTrend',
            'mrr',
            'conversionRatePct',
            'arpu',
            'platformGmvThisMonth',
            'revenueThisMonth',
            'revenueAllTime',
            'pendingInvoicesCount',
            'totalPlatformTransactions',
            'recentTenants',
            'pendingInvoices'
        ));
    }

    public function tenants(Request $request)
    {
        $query = $this->buildTenantQuery($request);
        $tenants = $query->latest()->paginate(15)->withQueryString();
        $plans = Plan::orderBy('sort_order')->get();

        return view('superadmin.tenants', compact('tenants', 'plans'));
    }

    public function exportTenants(Request $request)
    {
        $tenants = $this->buildTenantQuery($request)->latest()->get();
        $platformName = strtoupper(PlatformSetting::platformName());
        $filename = 'daftar-usaha-tenant-' . Carbon::now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($tenants, $platformName) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ["DATABASE USAHA (TENANT) {$platformName}"]);
            fputcsv($file, ['Tanggal Export', Carbon::now()->translatedFormat('d F Y H:i')]);
            fputcsv($file, ['Total Usaha', $tenants->count()]);
            fputcsv($file, []);
            fputcsv($file, ['Nama Usaha', 'Slug', 'Jenis Usaha', 'Pemilik', 'Email', 'WhatsApp', 'Paket', 'Status', 'Berakhir', 'Jml User', 'Jml Menu', 'Jml Transaksi', 'Terdaftar']);

            foreach ($tenants as $t) {
                $owner = $t->users->firstWhere('role', 'admin') ?? $t->users->first();
                fputcsv($file, [
                    $t->name,
                    $t->slug,
                    $t->business_type,
                    $owner?->name ?? '-',
                    $owner?->email ?? '-',
                    $t->phone ?? '-',
                    $t->is_lifetime ? 'VIP Lifetime (Pro)' : ($t->currentPlan()?->name ?? '-'),
                    strtoupper($t->subscriptionState()),
                    $t->is_lifetime ? 'Selamanya' : ($t->activeSubscription?->ends_at?->format('Y-m-d') ?? '-'),
                    (int) $t->users_count,
                    (int) $t->menus_count,
                    (int) $t->transactions_count,
                    $t->created_at?->format('Y-m-d H:i') ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function buildTenantQuery(Request $request)
    {
        // Note: Do NOT use ->limit(5) inside eager-load closure (SQL applies global LIMIT across all parents!).
        // Slice ->take(5) per tenant in the view instead so every tenant accurately sees its own latest 5 invoices.
        $query = Tenant::with([
            'activeSubscription.plan',
            'users' => fn($q) => $q->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")->orderBy('id'),
            'invoices' => fn($q) => $q->with('plan')->latest('id'),
        ])->withCount(['users', 'menus', 'transactions']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('slug', 'like', "%{$s}%")
                  ->orWhere('business_type', 'like', "%{$s}%");
            });
        }

        if ($request->filled('plan_id')) {
            $planId = (int) $request->plan_id;
            $query->whereHas('activeSubscription', function ($sq) use ($planId) {
                $sq->where('plan_id', $planId);
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;
            $now = Carbon::now();
            if ($status === 'active') {
                $query->where('is_active', true)->where(function ($q) use ($now) {
                    $q->where('is_lifetime', true)
                      ->orWhereHas('activeSubscription', function ($sq) use ($now) {
                          $sq->where('status', 'active')
                             ->where(function ($eq) use ($now) {
                                 $eq->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                             });
                      });
                });
            } elseif ($status === 'trial') {
                $query->where('is_active', true)
                      ->where('is_lifetime', false)
                      ->whereHas('activeSubscription', function ($sq) use ($now) {
                          $sq->where('status', 'trial')
                             ->where(function ($eq) use ($now) {
                                 $eq->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                             });
                      });
            } elseif ($status === 'grace') {
                $query->where('is_active', true)
                      ->where('is_lifetime', false)
                      ->whereHas('activeSubscription', function ($sq) use ($now) {
                          $sq->whereNotNull('ends_at')
                             ->where('ends_at', '<', $now)
                             ->whereNotNull('grace_ends_at')
                             ->where('grace_ends_at', '>=', $now);
                      });
            } elseif ($status === 'expired') {
                $query->where(function ($q) use ($now) {
                    $q->where('is_active', false)
                      ->orWhere(function ($eq) use ($now) {
                          $eq->where('is_lifetime', false)
                             ->where(function ($subQ) use ($now) {
                                 $subQ->whereDoesntHave('activeSubscription')
                                      ->orWhereHas('activeSubscription', function ($sq) use ($now) {
                                          $sq->whereNotNull('ends_at')
                                             ->where('ends_at', '<', $now)
                                             ->where(function ($gq) use ($now) {
                                                 $gq->whereNull('grace_ends_at')
                                                    ->orWhere('grace_ends_at', '<', $now);
                                             });
                                      });
                             });
                      });
                });
            }
        }

        return $query;
    }

    public function storeTenant(Request $request)
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:trial,monthly,yearly,lifetime'],
            'seed_sample' => ['nullable', 'boolean'],
        ]);

        $provisioned = $this->subscriptionService->provisionTenant(
            [
                'name' => $validated['store_name'],
                'business_type' => $validated['business_type'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
            ],
            [
                'name' => $validated['owner_name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ],
            $request->boolean('seed_sample', true)
        );

        $plan = Plan::findOrFail($validated['plan_id']);
        if ($validated['billing_cycle'] !== 'trial' || $plan->slug !== 'trial') {
            $this->subscriptionService->assignSubscriptionManually(
                $provisioned['tenant'],
                $plan,
                $validated['billing_cycle'],
                null,
                'Dibuat manual oleh Super Admin'
            );
        }

        PlatformAuditLog::record(
            'tenant',
            'create_tenant',
            'Tenant',
            $provisioned['tenant']->id,
            $provisioned['tenant']->name,
            "Membuat usaha baru {$provisioned['tenant']->name} (owner: {$provisioned['user']->username}, paket: {$plan->name} / {$validated['billing_cycle']})."
        );

        return back()->with('success', "Usaha {$provisioned['tenant']->name} (Owner: {$provisioned['user']->username}) berhasil ditambahkan!");
    }

    public function updateTenant(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant->update($validated);

        PlatformAuditLog::record(
            'tenant',
            'update_tenant',
            'Tenant',
            $tenant->id,
            $tenant->name,
            "Memperbarui profil usaha {$tenant->name} ({$tenant->business_type})."
        );

        return back()->with('success', "Informasi usaha {$tenant->name} berhasil diperbarui!");
    }

    public function destroyTenant(Tenant $tenant)
    {
        if ($tenant->is_active) {
            return back()->with('error', "Usaha {$tenant->name} masih aktif. Nonaktifkan (Suspend) terlebih dahulu sebelum menghapus permanen.");
        }

        if ($tenant->is_lifetime) {
            return back()->with('error', "Usaha VIP Lifetime ({$tenant->name}) dilindungi dari penghapusan permanen.");
        }

        $hasSuperAdmin = User::where('tenant_id', $tenant->id)->where('role', 'super_admin')->exists();
        if ($hasSuperAdmin) {
            return back()->with('error', 'Usaha ini terhubung dengan akun Super Admin dan tidak dapat dihapus.');
        }

        $tenantId = $tenant->id;
        $tenantName = $tenant->name;

        DB::transaction(function () use ($tenant) {
            User::where('tenant_id', $tenant->id)->delete();
            $tenant->forceDelete();
        });

        PlatformAuditLog::record(
            'tenant',
            'delete_tenant',
            'Tenant',
            $tenantId,
            $tenantName,
            "Menghapus permanen usaha {$tenantName} (ID #{$tenantId}) beserta seluruh data terkait."
        );

        return back()->with('success', "Usaha {$tenantName} beserta seluruh datanya berhasil dihapus secara permanen.");
    }

    public function resetOwnerPassword(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:6'],
        ]);

        $owner = User::where('tenant_id', $tenant->id)
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        if (!$owner) {
            return back()->with('error', "Akun admin/owner untuk usaha {$tenant->name} tidak ditemukan.");
        }

        $owner->update([
            'password' => \Illuminate\Support\Facades\Hash::make($validated['new_password']),
        ]);

        PlatformAuditLog::record(
            'tenant',
            'reset_owner_password',
            'Tenant',
            $tenant->id,
            $tenant->name,
            "Mereset password pemilik usaha {$tenant->name} (username: {$owner->username})."
        );

        return back()->with('success', "Password pemilik {$tenant->name} ({$owner->username}) berhasil direset!");
    }

    public function updateTenantSubscription(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:trial,monthly,yearly,lifetime'],
            'custom_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        $this->subscriptionService->assignSubscriptionManually(
            $tenant,
            $plan,
            $validated['billing_cycle'],
            $validated['custom_days'] ?? null,
            $validated['notes'] ?? null
        );

        PlatformAuditLog::record(
            'tenant',
            'update_subscription',
            'Tenant',
            $tenant->id,
            $tenant->name,
            "Mengatur langganan manual {$tenant->name} ke paket {$plan->name} ({$validated['billing_cycle']}" . (!empty($validated['custom_days']) ? ", {$validated['custom_days']} hari" : '') . ').'
        );

        return back()->with('success', "Langganan usaha {$tenant->name} berhasil diperbarui ke paket {$plan->name} ({$validated['billing_cycle']}).");
    }

    public function toggleTenantStatus(Tenant $tenant)
    {
        $tenant->update(['is_active' => !$tenant->is_active]);
        $statusText = $tenant->is_active ? 'diaktifkan kembali' : 'dinonaktifkan (suspend)';

        PlatformAuditLog::record(
            'tenant',
            $tenant->is_active ? 'activate_tenant' : 'suspend_tenant',
            'Tenant',
            $tenant->id,
            $tenant->name,
            "Status usaha {$tenant->name} {$statusText}."
        );

        return back()->with('success', "Status usaha {$tenant->name} berhasil {$statusText}.");
    }

    public function impersonateTenant(Tenant $tenant)
    {
        session(['impersonating_tenant_id' => $tenant->id]);
        TenantContext::set($tenant);

        PlatformAuditLog::record(
            'impersonate',
            'impersonate_tenant',
            'Tenant',
            $tenant->id,
            $tenant->name,
            "Masuk Mode Support (impersonate) ke panel usaha {$tenant->name}."
        );

        return redirect()->route('dashboard')
            ->with('success', "Mode Support aktif: Anda sekarang melihat dashboard sebagai admin {$tenant->name}.");
    }

    public function stopImpersonate()
    {
        session()->forget('impersonating_tenant_id');
        TenantContext::clear();

        return redirect()->route('superadmin.tenants')
            ->with('success', 'Anda telah kembali ke Panel Super Admin.');
    }

    public function invoices(Request $request)
    {
        SubscriptionInvoice::expireOverdue();

        $query = $this->buildInvoiceQuery($request);
        $invoiceSummary = [
            'total_count' => (clone $query)->count(),
            'paid_count' => (clone $query)->where('status', 'paid')->count(),
            'paid_total' => (float) (clone $query)->where('status', 'paid')->sum('base_amount'),
            'unpaid_count' => (clone $query)->where('status', 'unpaid')->count(),
            'unpaid_total' => (float) (clone $query)->where('status', 'unpaid')->sum('final_amount'),
        ];

        $invoices = $query->paginate(20)->withQueryString();
        $allTenants = Tenant::orderBy('name')->get(['id', 'name', 'slug']);
        $purchasablePlans = Plan::purchasable()->get();

        return view('superadmin.invoices', compact('invoices', 'invoiceSummary', 'allTenants', 'purchasablePlans'));
    }

    public function storeInvoice(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'payment_method' => ['required', 'in:qris,transfer'],
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $plan = Plan::findOrFail($validated['plan_id']);

        if ($plan->priceForCycle($validated['billing_cycle']) <= 0) {
            return back()->with('error', 'Pilih paket berbayar yang valid untuk menerbitkan invoice.');
        }

        $invoice = $this->subscriptionService->createInvoice(
            $tenant,
            $plan,
            $validated['billing_cycle'],
            $validated['payment_method']
        );

        PlatformAuditLog::record(
            'invoice',
            'create_invoice',
            'SubscriptionInvoice',
            $invoice->id,
            $invoice->invoice_number,
            "Menerbitkan invoice manual {$invoice->invoice_number} untuk {$tenant->name} ({$plan->name} {$validated['billing_cycle']}, Rp" . number_format((float) $invoice->final_amount, 0, ',', '.') . ').'
        );

        return back()->with('success', "Invoice {$invoice->invoice_number} untuk {$tenant->name} (Rp" . number_format((float) $invoice->final_amount, 0, ',', '.') . ') berhasil diterbitkan!');
    }

    public function exportInvoices(Request $request)
    {
        $invoices = $this->buildInvoiceQuery($request)->get();
        $platformName = strtoupper(PlatformSetting::platformName());
        $filename = 'laporan-invoice-saas-' . Carbon::now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($invoices, $platformName) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ["LAPORAN TAGIHAN & REVENUE SAAS {$platformName}"]);
            fputcsv($file, ['Tanggal Export', Carbon::now()->translatedFormat('d F Y H:i')]);
            fputcsv($file, ['Total Baris Invoice', $invoices->count()]);
            fputcsv($file, ['Total Revenue Lunas (Rp)', number_format((float) $invoices->where('status', 'paid')->sum('base_amount'), 0, ',', '.')]);
            fputcsv($file, []);

            fputcsv($file, ['No. Invoice', 'Tanggal Buat', 'Nama Usaha', 'Paket', 'Siklus', 'Harga Dasar (Rp)', 'Kode Unik', 'Total Tagihan (Rp)', 'Metode', 'Status', 'Tanggal Lunas', 'Referensi']);

            foreach ($invoices as $inv) {
                fputcsv($file, [
                    $inv->invoice_number,
                    $inv->created_at?->format('Y-m-d H:i') ?? '-',
                    $inv->tenant?->name ?? '-',
                    $inv->plan?->name ?? '-',
                    $inv->billing_cycle,
                    (float) $inv->base_amount,
                    $inv->unique_code,
                    (float) $inv->final_amount,
                    strtoupper($inv->payment_method),
                    strtoupper($inv->status),
                    $inv->paid_at?->format('Y-m-d H:i') ?? '-',
                    $inv->payment_reference ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function buildInvoiceQuery(Request $request)
    {
        $query = SubscriptionInvoice::with(['tenant', 'plan', 'approver'])->latest();

        if ($request->filled('status') && in_array($request->status, ['unpaid', 'paid', 'expired', 'cancelled'], true)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->filled('search')) {
            $s = trim((string) $request->search);
            $numeric = preg_replace('/[^0-9]/', '', $s);
            $query->where(function ($q) use ($s, $numeric) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhere('payment_reference', 'like', "%{$s}%")
                  ->orWhere('promo_code_snapshot', 'like', "%{$s}%")
                  ->orWhereHas('tenant', function ($tq) use ($s) {
                      $tq->where('name', 'like', "%{$s}%")
                         ->orWhere('slug', 'like', "%{$s}%");
                  });

                if ($numeric !== '') {
                    $q->orWhere('unique_code', (int) $numeric)
                      ->orWhere('final_amount', (float) $numeric);
                }
            });
        }

        return $query;
    }

    public function approveInvoice(SubscriptionInvoice $invoice)
    {
        if ($invoice->status === 'paid') {
            return back()->with('info', "Invoice {$invoice->invoice_number} sudah lunas sebelumnya.");
        }

        $this->subscriptionService->markInvoicePaid(
            $invoice,
            'MANUAL-SUPERADMIN-' . strtoupper(uniqid()),
            'Approval Manual Super Admin',
            Auth::id()
        );

        PlatformAuditLog::record(
            'invoice',
            'approve_invoice',
            'SubscriptionInvoice',
            $invoice->id,
            $invoice->invoice_number,
            "Menyetujui lunas invoice {$invoice->invoice_number} ({$invoice->tenant?->name}, Rp" . number_format((float) $invoice->final_amount, 0, ',', '.') . ').'
        );

        return back()->with('success', "Invoice {$invoice->invoice_number} dikonfirmasi LUNAS! Langganan {$invoice->tenant->name} langsung aktif.");
    }

    public function cancelInvoice(SubscriptionInvoice $invoice)
    {
        if ($invoice->status === 'unpaid') {
            $invoice->update(['status' => 'cancelled']);

            PlatformAuditLog::record(
                'invoice',
                'cancel_invoice',
                'SubscriptionInvoice',
                $invoice->id,
                $invoice->invoice_number,
                "Membatalkan invoice {$invoice->invoice_number} milik {$invoice->tenant?->name}."
            );
        }

        return back()->with('success', "Invoice {$invoice->invoice_number} dibatalkan.");
    }

    public function plans()
    {
        $plans = Plan::withCount(['subscriptions', 'invoices'])->orderBy('sort_order')->get();
        $tenants = Tenant::with('activeSubscription')->get();
        $planUsageCounts = [];
        foreach ($tenants as $t) {
            $planId = $t->activeSubscription?->plan_id;
            if ($planId) {
                $planUsageCounts[$planId] = ($planUsageCounts[$planId] ?? 0) + 1;
            }
        }

        return view('superadmin.plans', compact('plans', 'planUsageCounts'));
    }

    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_menus' => ['nullable', 'integer', 'min:1'],
        ]);

        $baseSlug = Str::slug($validated['name']) ?: 'paket';
        $slug = $baseSlug;
        $idx = 1;
        while (Plan::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $idx++;
        }

        $plan = Plan::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'price_yearly' => $validated['price_yearly'],
            'max_users' => ($validated['max_users'] ?? null) ?: null,
            'max_menus' => ($validated['max_menus'] ?? null) ?: null,
            'is_active' => $request->has('is_active'),
            'is_featured' => $request->has('is_featured'),
            'sort_order' => ((int) Plan::max('sort_order')) + 1,
            'features' => [
                'offline_sync' => $request->has('feature_offline_sync'),
                'expenses' => $request->has('feature_expenses'),
                'pdf_export' => $request->has('feature_pdf_export'),
                'qris_dynamic' => $request->has('feature_qris_dynamic'),
                'custom_branding' => true,
            ],
        ]);

        PlatformAuditLog::record(
            'plan',
            'create_plan',
            'Plan',
            $plan->id,
            $plan->name,
            "Menambahkan paket baru {$plan->name} (Rp" . number_format((float) $plan->price_monthly, 0, ',', '.') . '/bln).'
        );

        return back()->with('success', "Paket baru {$plan->name} berhasil ditambahkan!");
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_menus' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'feature_qris_dynamic' => ['sometimes', 'boolean'],
            'feature_pdf_export' => ['sometimes', 'boolean'],
            'feature_expenses' => ['sometimes', 'boolean'],
            'feature_offline_sync' => ['sometimes', 'boolean'],
        ]);

        $plan->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'price_yearly' => $validated['price_yearly'],
            'max_users' => ($validated['max_users'] ?? null) ?: null,
            'max_menus' => ($validated['max_menus'] ?? null) ?: null,
            'is_active' => $request->has('is_active'),
            'is_featured' => $request->has('is_featured'),
            'features' => [
                'offline_sync' => $request->has('feature_offline_sync'),
                'expenses' => $request->has('feature_expenses'),
                'pdf_export' => $request->has('feature_pdf_export'),
                'qris_dynamic' => $request->has('feature_qris_dynamic'),
                'custom_branding' => true,
            ],
        ]);

        PlatformAuditLog::record(
            'plan',
            'update_plan',
            'Plan',
            $plan->id,
            $plan->name,
            "Memperbarui paket {$plan->name} (Rp" . number_format((float) $plan->price_monthly, 0, ',', '.') . '/bln, Rp' . number_format((float) $plan->price_yearly, 0, ',', '.') . '/thn).'
        );

        return back()->with('success', "Konfigurasi paket {$plan->name} berhasil diperbarui!");
    }

    public function destroyPlan(Plan $plan)
    {
        if ($plan->slug === 'trial') {
            return back()->with('error', 'Paket Trial sistem dilindungi dan tidak dapat dihapus.');
        }

        if ($plan->subscriptions()->exists() || $plan->invoices()->exists()) {
            return back()->with('error', "Paket {$plan->name} sudah memiliki riwayat langganan atau invoice. Nonaktifkan paket saja agar data historis tetap aman.");
        }

        $planId = $plan->id;
        $planName = $plan->name;
        $plan->delete();

        PlatformAuditLog::record(
            'plan',
            'delete_plan',
            'Plan',
            $planId,
            $planName,
            "Menghapus paket {$planName} (ID #{$planId}) yang belum memiliki riwayat pemakaian."
        );

        return back()->with('success', "Paket {$planName} berhasil dihapus.");
    }

    public function promos()
    {
        $promos = PromoCode::with('plan')->latest('id')->paginate(20);
        $plans = Plan::purchasable()->get();

        return view('superadmin.promos', compact('promos', 'plans'));
    }

    public function storePromo(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:promo_codes,code'],
            'name' => ['required', 'string', 'max:120'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:1'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:1'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        if ($validated['discount_type'] === 'percent' && (float) $validated['discount_value'] > 100) {
            return back()->with('error', 'Diskon persentase tidak boleh lebih dari 100%.')->withInput();
        }

        $promo = PromoCode::create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'max_discount_amount' => ($validated['max_discount_amount'] ?? null) ?: null,
            'plan_id' => ($validated['plan_id'] ?? null) ?: null,
            'max_uses' => ($validated['max_uses'] ?? null) ?: null,
            'starts_at' => ($validated['starts_at'] ?? null) ?: null,
            'expires_at' => ($validated['expires_at'] ?? null) ?: null,
            'is_active' => $request->has('is_active'),
        ]);

        PlatformAuditLog::record(
            'promo',
            'create_promo',
            'PromoCode',
            $promo->id,
            $promo->code,
            "Membuat kode promo {$promo->code} ({$promo->name})."
        );

        return back()->with('success', "Kode promo {$promo->code} berhasil ditambahkan!");
    }

    public function updatePromo(Request $request, PromoCode $promo)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', \Illuminate\Validation\Rule::unique('promo_codes', 'code')->ignore($promo->id)],
            'name' => ['required', 'string', 'max:120'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:1'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:1'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if ($validated['discount_type'] === 'percent' && (float) $validated['discount_value'] > 100) {
            return back()->with('error', 'Diskon persentase tidak boleh lebih dari 100%.');
        }

        $promo->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'max_discount_amount' => ($validated['max_discount_amount'] ?? null) ?: null,
            'plan_id' => ($validated['plan_id'] ?? null) ?: null,
            'max_uses' => ($validated['max_uses'] ?? null) ?: null,
            'starts_at' => ($validated['starts_at'] ?? null) ?: null,
            'expires_at' => ($validated['expires_at'] ?? null) ?: null,
            'is_active' => $request->has('is_active'),
        ]);

        PlatformAuditLog::record(
            'promo',
            'update_promo',
            'PromoCode',
            $promo->id,
            $promo->code,
            "Memperbarui kode promo {$promo->code} ({$promo->name})."
        );

        return back()->with('success', "Kode promo {$promo->code} berhasil diperbarui!");
    }

    public function togglePromo(PromoCode $promo)
    {
        $promo->update(['is_active' => !$promo->is_active]);
        $stateText = $promo->is_active ? 'diaktifkan' : 'dinonaktifkan';

        PlatformAuditLog::record(
            'promo',
            'toggle_promo',
            'PromoCode',
            $promo->id,
            $promo->code,
            "Kode promo {$promo->code} {$stateText}."
        );

        return back()->with('success', "Kode promo {$promo->code} berhasil {$stateText}.");
    }

    public function destroyPromo(PromoCode $promo)
    {
        $code = $promo->code;
        $id = $promo->id;
        $promo->delete();

        PlatformAuditLog::record(
            'promo',
            'delete_promo',
            'PromoCode',
            $id,
            $code,
            "Menghapus kode promo {$code}."
        );

        return back()->with('success', "Kode promo {$code} berhasil dihapus.");
    }

    public function auditLogs(Request $request)
    {
        $logs = $this->buildAuditLogQuery($request)->paginate(25)->withQueryString();

        return view('superadmin.audit-logs', compact('logs'));
    }

    public function exportAuditLogs(Request $request)
    {
        $logs = $this->buildAuditLogQuery($request)->get();
        $platformName = strtoupper(PlatformSetting::platformName());
        $filename = 'audit-trail-' . Carbon::now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($logs, $platformName) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ["LOG AKTIVITAS AUDIT {$platformName}"]);
            fputcsv($file, ['Tanggal Export', Carbon::now()->translatedFormat('d F Y H:i')]);
            fputcsv($file, ['Total Catatan', $logs->count()]);
            fputcsv($file, []);
            fputcsv($file, ['Waktu (WIB)', 'Kategori', 'Aksi', 'Pelaku', 'Target', 'Deskripsi', 'IP Address']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at?->format('Y-m-d H:i:s') ?? '-',
                    strtoupper((string) $log->category),
                    $log->action,
                    $log->admin?->name ?? 'System',
                    $log->target_label ?? '-',
                    $log->description,
                    $log->ip_address ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function buildAuditLogQuery(Request $request)
    {
        $query = PlatformAuditLog::with('admin')->latest('id');

        if ($request->filled('category') && in_array($request->category, ['tenant', 'invoice', 'plan', 'promo', 'settings', 'impersonate'], true)) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $s = trim((string) $request->search);
            $query->where(function ($q) use ($s) {
                $q->where('description', 'like', "%{$s}%")
                  ->orWhere('target_label', 'like', "%{$s}%")
                  ->orWhere('action', 'like', "%{$s}%");
            });
        }

        return $query;
    }

    public function testPlatformWebhook()
    {
        $secret = (string) PlatformSetting::getValue('platform_qris_secret', '');
        $hasQris = !empty(PlatformSetting::getValue('platform_qris_payload', ''));
        if (strlen($secret) < 8) {
            return response()->json([
                'status' => 'error',
                'message' => 'Platform Webhook Secret Key belum valid (minimal 8 karakter).',
            ], 422);
        }

        $ts = (string) time();
        $payload = json_encode(['ping' => true, 'time' => $ts]);
        $sig = hash_hmac('sha256', $ts . '.' . $payload, $secret);
        $verified = hash_equals(hash_hmac('sha256', $ts . '.' . $payload, $secret), $sig);

        return response()->json([
            'status' => $verified ? 'success' : 'error',
            'message' => $verified
                ? 'Signature HMAC-SHA256 & konfigurasi endpoint billing siap digunakan!' . ($hasQris ? ' (QRIS Statis Aktif)' : ' (Catatan: Payload QRIS Statis belum diisi)')
                : 'Gagal memverifikasi signature HMAC.',
            'webhook_url' => url('/listener/platform/payment'),
            'has_qris' => $hasQris,
        ]);
    }

    public function pruneWebhookLogs()
    {
        $deleted = PaymentListenerLog::withoutGlobalScope('tenant')
            ->where('is_platform_billing', true)
            ->where('created_at', '<', Carbon::now()->subDays(30))
            ->delete();

        PlatformAuditLog::record(
            'settings',
            'prune_webhook_logs',
            'PaymentListenerLog',
            null,
            'Platform Webhook Logs',
            "Membersihkan {$deleted} log webhook billing yang berusia lebih dari 30 hari."
        );

        return back()->with('success', "Berhasil membersihkan {$deleted} log webhook billing yang lebih dari 30 hari.");
    }

    public function settings()
    {
        $settings = [
            'platform_name' => PlatformSetting::getValue('platform_name', config('platform.name', 'KasirLoka')),
            'platform_tagline' => PlatformSetting::getValue('platform_tagline', config('platform.tagline')),
            'support_whatsapp' => PlatformSetting::getValue('support_whatsapp', ''),
            'bank_name' => PlatformSetting::getValue('bank_name', ''),
            'bank_account_number' => PlatformSetting::getValue('bank_account_number', ''),
            'bank_account_name' => PlatformSetting::getValue('bank_account_name', ''),
            'platform_qris_payload' => PlatformSetting::getValue('platform_qris_payload', ''),
            'platform_qris_secret' => PlatformSetting::getValue('platform_qris_secret', ''),
            'trial_days' => PlatformSetting::getValue('trial_days', '14'),
            'grace_days' => PlatformSetting::getValue('grace_days', '3'),
            'announcement_enabled' => PlatformSetting::getValue('announcement_enabled', '0'),
            'announcement_type' => PlatformSetting::getValue('announcement_type', 'info'),
            'announcement_message' => PlatformSetting::getValue('announcement_message', ''),
        ];

        $platformWebhookUrl = url('/listener/platform/payment');
        $platformTestUrl = url('/listener/platform/test-connection');
        $platformWebhookLogs = PaymentListenerLog::withoutGlobalScope('tenant')
            ->where('is_platform_billing', true)
            ->with('invoice.tenant')
            ->latest('id')
            ->take(20)
            ->get();

        return view('superadmin.settings', compact('settings', 'platformWebhookUrl', 'platformTestUrl', 'platformWebhookLogs'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'platform_name' => ['required', 'string', 'max:80'],
            'platform_tagline' => ['nullable', 'string', 'max:200'],
            'support_whatsapp' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'platform_qris_payload' => ['nullable', 'string'],
            'platform_qris_secret' => ['required', 'string', 'min:8'],
            'trial_days' => ['required', 'integer', 'min:1', 'max:90'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:30'],
            'announcement_type' => ['nullable', 'in:info,warning,success,danger'],
            'announcement_message' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['announcement_enabled'] = $request->has('announcement_enabled') ? '1' : '0';
        $validated['announcement_type'] = $validated['announcement_type'] ?? 'info';
        $validated['announcement_message'] = trim((string) ($validated['announcement_message'] ?? ''));

        $qrisPayload = trim($validated['platform_qris_payload'] ?? '', " \t\n\r\0\x0B");
        $qrisPayload = str_replace(["\r\n", "\r", "\n", "\t"], '', $qrisPayload);
        if (!empty($qrisPayload) && str_starts_with($qrisPayload, '000201') && str_contains($qrisPayload, '6304')) {
            $body = preg_replace('/6304[0-9A-Fa-f]{0,4}$/', '6304', $qrisPayload);
            $qrisPayload = $body . $this->qrisService->calculateCrc16($body);
        }
        $validated['platform_qris_payload'] = $qrisPayload;

        foreach ($validated as $key => $val) {
            PlatformSetting::setValue($key, $val);
        }

        PlatformAuditLog::record(
            'settings',
            'update_platform_settings',
            'PlatformSetting',
            null,
            $validated['platform_name'],
            "Memperbarui pengaturan platform {$validated['platform_name']} (trial: {$validated['trial_days']}h, broadcast: " . ($validated['announcement_enabled'] === '1' ? 'AKTIF' : 'OFF') . ').'
        );

        return back()->with('success', 'Pengaturan Platform & Pembayaran Langganan berhasil disimpan!');
    }
}
