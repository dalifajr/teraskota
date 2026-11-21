<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\PromoCode;
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

    public function checkPromo(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'billing_cycle' => ['nullable', 'in:monthly,yearly'],
        ]);

        $code = strtoupper(trim($validated['code']));
        $promo = PromoCode::with('plan')->where('code', $code)->first();

        if (!$promo) {
            return response()->json([
                'valid' => false,
                'message' => "Kode promo \"{$code}\" tidak ditemukan.",
            ], 404);
        }

        $plan = !empty($validated['plan_id']) ? Plan::find($validated['plan_id']) : null;
        $check = $promo->checkValidity($plan);
        if (!$check['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $check['message'],
            ], 422);
        }

        $cycle = $validated['billing_cycle'] ?? 'monthly';
        $discountPreview = $plan ? $promo->calculateDiscount($plan->priceForCycle($cycle)) : null;
        $label = $promo->discount_type === 'percent'
            ? rtrim(rtrim(number_format((float) $promo->discount_value, 2, '.', ''), '0'), '.') . '%'
                . ($promo->max_discount_amount ? ' (maks. Rp' . number_format((float) $promo->max_discount_amount, 0, ',', '.') . ')' : '')
            : 'Rp' . number_format((float) $promo->discount_value, 0, ',', '.');

        return response()->json([
            'valid' => true,
            'code' => $promo->code,
            'name' => $promo->name,
            'discount_label' => $label,
            'discount_type' => $promo->discount_type,
            'discount_value' => (float) $promo->discount_value,
            'max_discount_amount' => $promo->max_discount_amount ? (float) $promo->max_discount_amount : null,
            'plan_id' => $promo->plan_id,
            'plan_name' => $promo->plan?->name,
            'discount_preview' => $discountPreview,
            'message' => "Kode promo {$promo->code} aktif! Potongan {$label}" . ($promo->plan ? " khusus paket {$promo->plan->name}." : ' untuk semua paket.'),
        ]);
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
            'promo_code' => ['nullable', 'string', 'max:50'],
        ]);

        $plan = Plan::where('is_active', true)->findOrFail($validated['plan_id']);
        if ($plan->priceForCycle($validated['billing_cycle']) <= 0) {
            return back()->with('error', 'Paket yang dipilih tidak valid untuk langganan berbayar.');
        }

        $promo = null;
        if (!empty($validated['promo_code'])) {
            $code = strtoupper(trim($validated['promo_code']));
            $promo = PromoCode::with('plan')->where('code', $code)->first();
            if (!$promo) {
                return back()->with('error', "Kode promo \"{$code}\" tidak ditemukan.");
            }
            $check = $promo->checkValidity($plan);
            if (!$check['valid']) {
                return back()->with('error', $check['message']);
            }
        }

        $invoice = $this->subscriptionService->createInvoice(
            $tenant,
            $plan,
            $validated['billing_cycle'],
            $validated['payment_method'],
            $promo
        );

        $promoInfo = $invoice->discount_amount > 0
            ? " (Diskon promo {$invoice->promo_code_snapshot}: -Rp" . number_format((float) $invoice->discount_amount, 0, ',', '.') . ')'
            : '';

        return redirect()->route('billing.index')
            ->with('success', "Tagihan {$invoice->invoice_number} berhasil dibuat{$promoInfo}. Silakan selesaikan pembayaran sebesar Rp" . number_format((float) $invoice->final_amount, 0, ',', '.') . ' sesuai kode unik.');
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
