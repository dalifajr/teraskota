<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function index()
    {
        $tenant = TenantContext::current();
        if (!$tenant) {
            return redirect()->route('dashboard');
        }

        $plans = Plan::purchasable()->get();
        $subscription = $tenant->activeSubscription;
        $state = $tenant->subscriptionState();
        $daysRemaining = $tenant->daysRemaining();

        $userUsage = $this->subscriptionService->checkUserLimit($tenant);
        $menuUsage = $this->subscriptionService->checkMenuLimit($tenant);

        $activeInvoice = SubscriptionInvoice::where('tenant_id', $tenant->id)
            ->where('status', 'unpaid')
            ->latest()
            ->first();

        if ($activeInvoice && $activeInvoice->qris_expired_at && now()->isAfter($activeInvoice->qris_expired_at)) {
            $activeInvoice->update(['status' => 'expired']);
            $activeInvoice = null;
        }

        $invoices = SubscriptionInvoice::with('plan')
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->take(15)
            ->get();

        $bankInfo = [
            'bank_name' => PlatformSetting::getValue('bank_name', 'BCA / Mandiri / BRI'),
            'account_number' => PlatformSetting::getValue('bank_account_number', ''),
            'account_name' => PlatformSetting::getValue('bank_account_name', ''),
            'support_whatsapp' => PlatformSetting::getValue('support_whatsapp', ''),
            'has_platform_qris' => !empty(PlatformSetting::getValue('platform_qris_payload')),
        ];

        return view('billing.index', compact(
            'tenant',
            'plans',
            'subscription',
            'state',
            'daysRemaining',
            'userUsage',
            'menuUsage',
            'activeInvoice',
            'invoices',
            'bankInfo'
        ));
    }

    public function subscribe(Request $request)
    {
        $tenant = TenantContext::current();
        if (!$tenant) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'payment_method' => ['required', 'in:qris,transfer'],
        ]);

        $plan = Plan::where('is_active', true)->findOrFail($validated['plan_id']);
        if ($plan->priceForCycle($validated['billing_cycle']) <= 0) {
            return back()->with('error', 'Paket yang dipilih tidak valid untuk langganan berbayar.');
        }

        $invoice = $this->subscriptionService->createInvoice(
            $tenant,
            $plan,
            $validated['billing_cycle'],
            $validated['payment_method']
        );

        return redirect()->route('billing.index')
            ->with('success', "Tagihan {$invoice->invoice_number} berhasil dibuat. Silakan selesaikan pembayaran sebesar Rp" . number_format((float) $invoice->final_amount, 0, ',', '.') . ' sesuai kode unik.');
    }

    public function checkInvoiceStatus(SubscriptionInvoice $invoice)
    {
        $tenantId = TenantContext::id();
        if ($tenantId !== null && (int) $invoice->tenant_id !== $tenantId) {
            abort(403);
        }

        if ($invoice->status === 'unpaid' && $invoice->qris_expired_at && now()->isAfter($invoice->qris_expired_at)) {
            $invoice->update(['status' => 'expired']);
        }

        return response()->json([
            'status' => $invoice->status,
            'invoice_number' => $invoice->invoice_number,
            'paid_at' => $invoice->paid_at ? $invoice->paid_at->toIso8601String() : null,
        ]);
    }

    public function cancelInvoice(SubscriptionInvoice $invoice)
    {
        $tenantId = TenantContext::id();
        if ($tenantId !== null && (int) $invoice->tenant_id !== $tenantId) {
            abort(403);
        }

        if ($invoice->status === 'unpaid') {
            $invoice->update(['status' => 'cancelled']);
        }

        return redirect()->route('billing.index')
            ->with('success', "Tagihan {$invoice->invoice_number} berhasil dibatalkan.");
    }
}
