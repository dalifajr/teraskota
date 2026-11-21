<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SubscriptionService
{
    protected QrisService $qrisService;

    public function __construct(QrisService $qrisService)
    {
        $this->qrisService = $qrisService;
    }

    /**
     * Register a new tenant with owner account, trial subscription, and default settings.
     *
     * @return array{tenant: Tenant, user: User, subscription: Subscription}
     */
    public function provisionTenant(array $tenantData, array $ownerData, bool $seedSampleData = false): array
    {
        return DB::transaction(function () use ($tenantData, $ownerData, $seedSampleData) {
            $trialDays = (int) PlatformSetting::getValue('trial_days', config('platform.trial_days', 14));
            $graceDays = (int) PlatformSetting::getValue('grace_days', config('platform.grace_days', 3));
            $now = Carbon::now();
            $trialEnds = $now->copy()->addDays($trialDays);

            $tenant = Tenant::create([
                'name' => $tenantData['name'],
                'slug' => Tenant::generateUniqueSlug($tenantData['name']),
                'business_type' => $tenantData['business_type'] ?? 'Kedai / F&B',
                'address' => $tenantData['address'] ?? null,
                'phone' => $tenantData['phone'] ?? null,
                'receipt_footer' => $tenantData['receipt_footer'] ?? 'Terima kasih atas kunjungan Anda!',
                'timezone' => $tenantData['timezone'] ?? 'Asia/Jakarta',
                'is_active' => true,
                'is_lifetime' => false,
                'trial_ends_at' => $trialEnds,
            ]);

            $trialPlan = Plan::where('slug', 'trial')->first()
                ?? Plan::orderBy('sort_order')->firstOrFail();

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $trialPlan->id,
                'billing_cycle' => 'trial',
                'status' => 'trial',
                'starts_at' => $now,
                'ends_at' => $trialEnds,
                'grace_ends_at' => $trialEnds->copy()->addDays($graceDays),
                'notes' => "Uji coba gratis {$trialDays} hari otomatis saat pendaftaran.",
            ]);

            $username = !empty($ownerData['username'])
                ? $ownerData['username']
                : $this->generateUniqueUsername($ownerData['email'], $ownerData['name']);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $ownerData['name'],
                'username' => $username,
                'email' => $ownerData['email'],
                'password' => Hash::make($ownerData['password']),
                'role' => 'admin',
            ]);

            // Initialize tenant-scoped default settings
            Setting::setValue('global_profit_percentage', '30', $tenant->id);
            Setting::setValue('qris_secret', bin2hex(random_bytes(16)), $tenant->id);

            if ($seedSampleData) {
                TenantContext::runForTenant($tenant, function () use ($tenant) {
                    $this->seedSampleCatalogForBusinessType($tenant);
                });
            }

            return [
                'tenant' => $tenant,
                'user' => $user,
                'subscription' => $subscription,
            ];
        });
    }

    /**
     * Seed smart sample categories & menus tailored to the tenant's business_type.
     */
    protected function seedSampleCatalogForBusinessType(Tenant $tenant): void
    {
        $type = Str::lower((string) $tenant->business_type);

        if (str_contains($type, 'bakery') || str_contains($type, 'roti') || str_contains($type, 'kue')) {
            $preset = [
                'Minuman Pendamping' => [
                    ['code' => 'MNM-001', 'name' => 'Kopi Susu Bakery', 'price' => 15000],
                ],
                'Roti & Pastry' => [
                    ['code' => 'BKR-001', 'name' => 'Roti Bakar Cokelat Keju', 'price' => 15000],
                    ['code' => 'BKR-002', 'name' => 'Croissant Butter', 'price' => 18000],
                ],
                'Kue & Dessert' => [
                    ['code' => 'BKR-003', 'name' => 'Donat Kampung Gula', 'price' => 8000],
                ],
            ];
        } elseif (str_contains($type, 'kelontong') || str_contains($type, 'retail') || str_contains($type, 'minimarket') || str_contains($type, 'sembako')) {
            $preset = [
                'Minuman Kemasan' => [
                    ['code' => 'MNM-001', 'name' => 'Air Mineral 600ml', 'price' => 4000],
                ],
                'Sembako & Kebutuhan' => [
                    ['code' => 'RTL-001', 'name' => 'Mie Instan Goreng', 'price' => 3500],
                    ['code' => 'RTL-002', 'name' => 'Kopi Sachet Renteng', 'price' => 15000],
                    ['code' => 'RTL-003', 'name' => 'Sabun Mandi Batang', 'price' => 5000],
                ],
            ];
        } elseif (str_contains($type, 'warung') || str_contains($type, 'makan') || str_contains($type, 'resto') || str_contains($type, 'kuliner')) {
            $preset = [
                'Minuman' => [
                    ['code' => 'MNM-001', 'name' => 'Es Teh Manis', 'price' => 5000],
                ],
                'Makanan Utama' => [
                    ['code' => 'MKN-001', 'name' => 'Nasi Goreng Spesial', 'price' => 18000],
                    ['code' => 'MKN-002', 'name' => 'Ayam Bakar Madu + Nasi', 'price' => 22000],
                ],
                'Lauk & Camilan' => [
                    ['code' => 'MKN-003', 'name' => 'Tempe Mendoan Hangat', 'price' => 10000],
                ],
            ];
        } else {
            // Default: Kedai Minuman / Kopi / F&B
            $preset = [
                'Kopi & Minuman' => [
                    ['code' => 'MNM-001', 'name' => 'Es Kopi Susu Gula Aren', 'price' => 15000],
                    ['code' => 'MNM-002', 'name' => 'Es Teh Manis Segar', 'price' => 5000],
                    ['code' => 'MNM-003', 'name' => 'Matcha Latte Creamy', 'price' => 18000],
                ],
                'Makanan & Snack' => [
                    ['code' => 'MKN-001', 'name' => 'Kentang Goreng Crispy', 'price' => 12000],
                ],
            ];
        }

        foreach ($preset as $categoryName => $menus) {
            $cat = Category::create([
                'tenant_id' => $tenant->id,
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
                'status' => true,
            ]);

            foreach ($menus as $item) {
                Menu::create([
                    'tenant_id' => $tenant->id,
                    'category_id' => $cat->id,
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'use_global_profit' => true,
                    'status' => true,
                ]);
            }
        }
    }

    /**
     * Create a new subscription invoice with unique verification code, optional promo discount, and optional dynamic QRIS.
     */
    public function createInvoice(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle = 'monthly',
        string $paymentMethod = 'qris',
        ?PromoCode $promo = null
    ): SubscriptionInvoice {
        $billingCycle = in_array($billingCycle, ['monthly', 'yearly'], true) ? $billingCycle : 'monthly';
        $paymentMethod = in_array($paymentMethod, ['qris', 'transfer'], true) ? $paymentMethod : 'qris';

        // Cancel previous unpaid invoices for the same tenant so there is no ambiguity
        SubscriptionInvoice::where('tenant_id', $tenant->id)
            ->where('status', 'unpaid')
            ->update(['status' => 'cancelled']);

        $grossAmount = $plan->priceForCycle($billingCycle);
        $discountAmount = 0.0;
        $appliedPromo = null;

        if ($promo) {
            $check = $promo->checkValidity($plan);
            if ($check['valid']) {
                $discountAmount = $promo->calculateDiscount($grossAmount);
                if ($discountAmount > 0) {
                    $appliedPromo = $promo;
                }
            }
        }

        $baseAmount = max(1000.0, $grossAmount - $discountAmount);
        $uniqueCode = $this->generateUniqueInvoiceCode($baseAmount);
        $finalAmount = (int) round($baseAmount) + $uniqueCode;

        $qrisPayload = null;
        $staticPlatformQris = PlatformSetting::getValue('platform_qris_payload');
        if ($paymentMethod === 'qris' && !empty($staticPlatformQris)) {
            $qrisPayload = $this->qrisService->makeDynamic($staticPlatformQris, $finalAmount);
        }

        $invoiceNumber = $this->generateInvoiceNumber();

        return SubscriptionInvoice::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'promo_code_id' => $appliedPromo?->id,
            'promo_code_snapshot' => $appliedPromo?->code,
            'invoice_number' => $invoiceNumber,
            'billing_cycle' => $billingCycle,
            'base_amount' => $baseAmount,
            'discount_amount' => $discountAmount,
            'unique_code' => $uniqueCode,
            'final_amount' => $finalAmount,
            'payment_method' => $paymentMethod,
            'qris_payload' => $qrisPayload,
            'qris_expired_at' => Carbon::now()->addHours(24),
            'status' => 'unpaid',
        ]);
    }

    /**
     * Mark an invoice as paid and activate/extend the tenant subscription.
     */
    public function markInvoicePaid(
        SubscriptionInvoice $invoice,
        ?string $reference = null,
        ?string $sourceApp = null,
        ?int $approvedBy = null
    ): Subscription {
        return DB::transaction(function () use ($invoice, $reference, $sourceApp, $approvedBy) {
            $invoice = SubscriptionInvoice::lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === 'paid' && $invoice->subscription) {
                return $invoice->subscription;
            }

            $tenant = Tenant::lockForUpdate()->findOrFail($invoice->tenant_id);
            $plan = Plan::findOrFail($invoice->plan_id);
            $graceDays = (int) PlatformSetting::getValue('grace_days', config('platform.grace_days', 3));
            $now = Carbon::now();

            $currentSub = $tenant->activeSubscription;
            $baseStart = $now->copy();

            // Extend seamlessly from current ends_at if still active on the same paid plan
            if (
                $currentSub
                && $currentSub->status === 'active'
                && $currentSub->plan_id === $plan->id
                && $currentSub->ends_at
                && $currentSub->ends_at->isFuture()
            ) {
                $baseStart = $currentSub->ends_at->copy();
            }

            $endsAt = $invoice->billing_cycle === 'yearly'
                ? $baseStart->copy()->addYear()
                : $baseStart->copy()->addMonth();

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $invoice->billing_cycle,
                'status' => 'active',
                'starts_at' => $now,
                'ends_at' => $endsAt,
                'grace_ends_at' => $endsAt->copy()->addDays($graceDays),
                'notes' => "Aktivasi dari invoice {$invoice->invoice_number}",
            ]);

            $invoice->update([
                'subscription_id' => $subscription->id,
                'status' => 'paid',
                'paid_at' => $now,
                'payment_reference' => $reference ?: $invoice->payment_reference,
                'payment_source_app' => $sourceApp ?: $invoice->payment_source_app,
                'approved_by' => $approvedBy,
            ]);

            if ($invoice->promo_code_id) {
                PromoCode::where('id', $invoice->promo_code_id)->increment('used_count');
            }

            if (!$tenant->is_active) {
                $tenant->update(['is_active' => true]);
            }

            TenantContext::refresh();

            return $subscription;
        });
    }

    /**
     * Match incoming Android Listener payment notification against unpaid subscription invoices.
     */
    public function matchPlatformPayment(float $amount, ?string $reference = null, ?string $sourceApp = null): ?SubscriptionInvoice
    {
        // Expire overdue invoices first
        SubscriptionInvoice::where('status', 'unpaid')
            ->whereNotNull('qris_expired_at')
            ->where('qris_expired_at', '<', Carbon::now())
            ->update(['status' => 'expired']);

        $invoice = SubscriptionInvoice::where('status', 'unpaid')
            ->where('final_amount', $amount)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$invoice) {
            return null;
        }

        $this->markInvoicePaid($invoice, $reference, $sourceApp, null);
        return $invoice->fresh();
    }

    /**
     * Check if a tenant can add another user under its current plan.
     */
    public function checkUserLimit(Tenant $tenant): array
    {
        $current = User::where('tenant_id', $tenant->id)->count();
        if ($tenant->is_lifetime) {
            return ['allowed' => true, 'current' => $current, 'limit' => null, 'message' => null];
        }

        $limit = $tenant->currentPlan()?->max_users;
        if ($limit !== null && $current >= $limit) {
            return [
                'allowed' => false,
                'current' => $current,
                'limit' => $limit,
                'message' => "Batas akun pengguna paket Anda ({$limit} akun) telah tercapai. Upgrade ke paket lebih tinggi untuk menambah kasir.",
            ];
        }

        return ['allowed' => true, 'current' => $current, 'limit' => $limit, 'message' => null];
    }

    /**
     * Check if a tenant can add another menu item under its current plan.
     */
    public function checkMenuLimit(Tenant $tenant): array
    {
        $current = Menu::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
        if ($tenant->is_lifetime) {
            return ['allowed' => true, 'current' => $current, 'limit' => null, 'message' => null];
        }

        $limit = $tenant->currentPlan()?->max_menus;
        if ($limit !== null && $current >= $limit) {
            return [
                'allowed' => false,
                'current' => $current,
                'limit' => $limit,
                'message' => "Batas jumlah menu paket Anda ({$limit} menu) telah tercapai. Upgrade paket Anda untuk menu tanpa batas.",
            ];
        }

        return ['allowed' => true, 'current' => $current, 'limit' => $limit, 'message' => null];
    }

    /**
     * Manually assign or extend a subscription from Super Admin.
     */
    public function assignSubscriptionManually(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        ?int $customDays = null,
        ?string $notes = null
    ): Subscription {
        $now = Carbon::now();
        $graceDays = (int) PlatformSetting::getValue('grace_days', config('platform.grace_days', 3));

        if ($billingCycle === 'lifetime') {
            $tenant->update(['is_lifetime' => true, 'is_active' => true]);
            $endsAt = null;
            $graceEnds = null;
            $status = 'active';
        } else {
            $tenant->update(['is_lifetime' => false, 'is_active' => true]);
            if ($customDays !== null && $customDays > 0) {
                $endsAt = $now->copy()->addDays($customDays);
            } elseif ($billingCycle === 'yearly') {
                $endsAt = $now->copy()->addYear();
            } elseif ($billingCycle === 'trial') {
                $endsAt = $now->copy()->addDays((int) PlatformSetting::getValue('trial_days', 14));
            } else {
                $endsAt = $now->copy()->addMonth();
            }
            $graceEnds = $endsAt->copy()->addDays($graceDays);
            $status = $billingCycle === 'trial' ? 'trial' : 'active';
        }

        $sub = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'status' => $status,
            'starts_at' => $now,
            'ends_at' => $endsAt,
            'grace_ends_at' => $graceEnds,
            'notes' => $notes ?: 'Diatur manual oleh Super Admin',
        ]);

        TenantContext::refresh();
        return $sub;
    }

    protected function generateUniqueInvoiceCode(float $baseAmount): int
    {
        $occupied = SubscriptionInvoice::where('status', 'unpaid')
            ->where(function ($q) {
                $q->whereNull('qris_expired_at')->orWhere('qris_expired_at', '>', Carbon::now());
            })
            ->pluck('final_amount')
            ->map(fn($v) => (int) $v)
            ->toArray();

        for ($i = 0; $i < 60; $i++) {
            $code = rand(101, 999);
            if (!in_array((int) round($baseAmount) + $code, $occupied, true)) {
                return $code;
            }
        }

        for ($code = 101; $code <= 999; $code++) {
            if (!in_array((int) round($baseAmount) + $code, $occupied, true)) {
                return $code;
            }
        }

        return rand(101, 999);
    }

    protected function generateInvoiceNumber(): string
    {
        $prefix = 'INV-KL-' . Carbon::now()->format('Ymd') . '-';
        $latest = SubscriptionInvoice::where('invoice_number', 'like', "{$prefix}%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        $next = 1;
        if ($latest) {
            $parts = explode('-', $latest->invoice_number);
            $next = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    protected function generateUniqueUsername(string $email, string $name): string
    {
        $base = Str::slug(strstr($email, '@', true) ?: $name, '_');
        if (strlen($base) < 3) {
            $base = 'owner_' . Str::lower(Str::random(4));
        }

        $candidate = $base;
        $i = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base . $i;
            $i++;
        }

        return $candidate;
    }
}
