<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlatformSetting;
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
        // Always ensure we are in global platform context on superadmin routes
        $tenants = Tenant::with(['activeSubscription.plan'])
            ->withCount(['users', 'menus', 'transactions'])
            ->latest()
            ->get();

        $totalTenants = $tenants->count();
        $activeTenants = $tenants->filter(fn($t) => in_array($t->subscriptionState(), ['active', 'lifetime'], true))->count();
        $trialTenants = $tenants->filter(fn($t) => $t->subscriptionState() === 'trial')->count();
        $expiredTenants = $tenants->filter(fn($t) => in_array($t->subscriptionState(), ['expired', 'suspended'], true))->count();

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

        $revenueThisMonth = (float) SubscriptionInvoice::where('status', 'paid')
            ->whereYear('paid_at', Carbon::now()->year)
            ->whereMonth('paid_at', Carbon::now()->month)
            ->sum('base_amount');

        $revenueAllTime = (float) SubscriptionInvoice::where('status', 'paid')->sum('base_amount');
        $pendingInvoicesCount = SubscriptionInvoice::where('status', 'unpaid')->count();
        $totalPlatformTransactions = Transaction::withoutGlobalScope('tenant')->paid()->count();

        $recentTenants = $tenants->take(8);
        $pendingInvoices = SubscriptionInvoice::with(['tenant', 'plan'])
            ->where('status', 'unpaid')
            ->latest()
            ->take(8)
            ->get();

        return view('superadmin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'trialTenants',
            'expiredTenants',
            'mrr',
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
        $query = Tenant::with([
            'activeSubscription.plan',
            'users' => fn($q) => $q->where('role', 'admin')->orderBy('id'),
        ])->withCount(['users', 'menus', 'transactions']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('slug', 'like', "%{$s}%")
                  ->orWhere('business_type', 'like', "%{$s}%");
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
            } elseif ($status === 'expired') {
                $query->where(function ($q) use ($now) {
                    $q->where('is_active', false)
                      ->orWhere(function ($eq) use ($now) {
                          $eq->where('is_lifetime', false)
                             ->where(function ($subQ) use ($now) {
                                 $subQ->whereDoesntHave('activeSubscription')
                                      ->orWhereHas('activeSubscription', function ($sq) use ($now) {
                                          $sq->whereNotNull('ends_at')->where('ends_at', '<', $now);
                                      });
                             });
                      });
                });
            }
        }

        $tenants = $query->latest()->paginate(15)->withQueryString();
        $plans = Plan::orderBy('sort_order')->get();

        return view('superadmin.tenants', compact('tenants', 'plans'));
    }

    public function storeTenant(Request $request)
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', 'string', 'max:60'],
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

        return back()->with('success', "Usaha {$provisioned['tenant']->name} (Owner: {$provisioned['user']->username}) berhasil ditambahkan!");
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

        return back()->with('success', "Langganan usaha {$tenant->name} berhasil diperbarui ke paket {$plan->name} ({$validated['billing_cycle']}).");
    }

    public function toggleTenantStatus(Tenant $tenant)
    {
        $tenant->update(['is_active' => !$tenant->is_active]);
        $statusText = $tenant->is_active ? 'diaktifkan kembali' : 'dinonaktifkan (suspend)';

        return back()->with('success', "Status usaha {$tenant->name} berhasil {$statusText}.");
    }

    public function impersonateTenant(Tenant $tenant)
    {
        session(['impersonating_tenant_id' => $tenant->id]);
        TenantContext::set($tenant);

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
        $query = SubscriptionInvoice::with(['tenant', 'plan', 'approver'])->latest();

        if ($request->filled('status') && in_array($request->status, ['unpaid', 'paid', 'expired', 'cancelled'], true)) {
            $query->where('status', $request->status);
        }

        $invoices = $query->paginate(20)->withQueryString();

        return view('superadmin.invoices', compact('invoices'));
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

        return back()->with('success', "Invoice {$invoice->invoice_number} dikonfirmasi LUNAS! Langganan {$invoice->tenant->name} langsung aktif.");
    }

    public function cancelInvoice(SubscriptionInvoice $invoice)
    {
        if ($invoice->status === 'unpaid') {
            $invoice->update(['status' => 'cancelled']);
        }

        return back()->with('success', "Invoice {$invoice->invoice_number} dibatalkan.");
    }

    public function plans()
    {
        $plans = Plan::orderBy('sort_order')->get();
        return view('superadmin.plans', compact('plans'));
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
            'max_users' => $validated['max_users'] ?: null,
            'max_menus' => $validated['max_menus'] ?: null,
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

        return back()->with('success', "Konfigurasi paket {$plan->name} berhasil diperbarui!");
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
        ];

        $platformWebhookUrl = url('/listener/platform/payment');
        $platformTestUrl = url('/listener/platform/test-connection');

        return view('superadmin.settings', compact('settings', 'platformWebhookUrl', 'platformTestUrl'));
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
        ]);

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

        return back()->with('success', 'Pengaturan Platform & Pembayaran Langganan berhasil disimpan!');
    }
}
