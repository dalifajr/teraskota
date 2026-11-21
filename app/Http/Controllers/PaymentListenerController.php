<?php

namespace App\Http\Controllers;

use App\Models\PaymentListenerLog;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\QrisService;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentListenerController extends Controller
{
    protected QrisService $qrisService;
    protected SubscriptionService $subscriptionService;

    public function __construct(QrisService $qrisService, SubscriptionService $subscriptionService)
    {
        $this->qrisService = $qrisService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Resolve tenant by explicit slug or legacy default (Teras Kota / first tenant).
     */
    protected function resolveTenant(?string $tenantSlug = null): ?Tenant
    {
        if (!empty($tenantSlug)) {
            return Tenant::where('slug', $tenantSlug)->first();
        }

        return Tenant::where('slug', 'teras-kota')->first() ?? Tenant::orderBy('id')->first();
    }

    /**
     * Test connection ping from Android Listener app (Tenant-aware).
     * POST /listener/{tenantSlug}/test-connection or /listener/test-connection
     */
    public function testConnection(Request $request, ?string $tenantSlug = null): JsonResponse
    {
        $tenant = $this->resolveTenant($tenantSlug);
        if (!$tenant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usaha/Tenant tidak ditemukan.',
            ], 404);
        }

        if (!$tenant->canOperate()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Langganan usaha tidak aktif atau sedang disuspend.',
            ], 403);
        }

        return TenantContext::runForTenant($tenant, function () use ($request, $tenant) {
            $secret = $this->qrisService->getSecret();
            $timestamp = $request->header('X-Timestamp', '');
            $signature = $request->header('X-Signature', '');
            $rawContent = $request->getContent();

            if (!empty($secret)) {
                $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawContent, $secret);
                $bodySecret = (string) $request->input('secret', '');

                $isValidSig = (!empty($signature) && hash_equals($expectedSignature, $signature))
                           || ($bodySecret !== '' && hash_equals($secret, $bodySecret));

                if (!$isValidSig) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Signature mismatch (Invalid Secret)',
                    ], 401);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Koneksi ke {$tenant->name} berhasil terhubung!",
                'server_time' => time(),
                'app_name' => $tenant->name . ' Webhook Listener',
                'tenant_slug' => $tenant->slug,
            ]);
        });
    }

    /**
     * Handle incoming payment notification from Android Listener (Tenant-aware).
     * POST /listener/{tenantSlug}/payment or /listener/payment
     */
    public function payment(Request $request, ?string $tenantSlug = null): JsonResponse
    {
        $tenant = $this->resolveTenant($tenantSlug);
        if (!$tenant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usaha/Tenant tidak ditemukan.',
            ], 404);
        }

        if (!$tenant->canOperate()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Langganan usaha tidak aktif atau sedang disuspend.',
            ], 403);
        }

        return TenantContext::runForTenant($tenant, function () use ($request, $tenant) {
            $rawContent = $request->getContent();
            $timestamp = $request->header('X-Timestamp', '');
            $signature = $request->header('X-Signature', '');
            $idempotencyKey = $request->header('X-Idempotency-Key')
                ?: $request->input('reference')
                ?: ('anon-' . md5($rawContent . microtime(true)));

            $secret = $this->qrisService->getSecret();

            // 1. Signature Verification
            if (!empty($secret)) {
                $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawContent, $secret);
                $bodySecret = $request->input('secret');

                $isValidSig = (!empty($signature) && hash_equals($expectedSignature, $signature))
                           || ($bodySecret === $secret);

                if (!$isValidSig) {
                    Log::warning("[PaymentListener:{$tenant->slug}] Invalid HMAC signature");

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid HMAC-SHA256 signature or secret',
                    ], 401);
                }
            }

            // 2. Idempotency Check (automatically tenant-scoped via Global Scope)
            $existingLog = PaymentListenerLog::where('idempotency_key', $idempotencyKey)->first();
            if ($existingLog) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment already processed (idempotent)',
                    'matched' => $existingLog->status === 'matched',
                    'transaction_id' => $existingLog->matched_transaction_id,
                ]);
            }

            // 3. Extract Payment Details
            $amount = (float) $request->input('amount', 0);
            $sourceApp = $request->input('source_app', 'Android');
            $reference = $request->input('reference', '');
            $rawText = $request->input('raw_text', '');

            if ($amount <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid amount provided in payload',
                ], 422);
            }

            // 4. Match against pending QRIS transactions belonging ONLY to this tenant
            $transaction = Transaction::where('payment_method', 'qris')
                ->where('status', 'pending')
                ->where(function ($query) use ($amount) {
                    $query->where('final_amount', $amount)
                          ->orWhere(function ($q) use ($amount) {
                              $q->whereNull('final_amount')->where('total_sales', $amount);
                          });
                })
                ->where(function ($query) {
                    $query->whereNull('qris_expired_at')
                          ->orWhere('qris_expired_at', '>=', now()->subHours(2));
                })
                ->latest()
                ->first();

            if ($transaction) {
                $transaction->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_reference' => $reference,
                    'payment_source_app' => $sourceApp,
                    'notified_at' => null,
                ]);

                PaymentListenerLog::create([
                    'tenant_id' => $tenant->id,
                    'idempotency_key' => $idempotencyKey,
                    'amount' => $amount,
                    'source_app' => $sourceApp,
                    'reference' => $reference,
                    'raw_text' => $rawText,
                    'status' => 'matched',
                    'matched_transaction_id' => $transaction->id,
                ]);

                Log::info("[PaymentListener:{$tenant->slug}] Transaction #{$transaction->id} marked PAID via {$sourceApp} Rp{$amount}");

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment matched and transaction marked as paid',
                    'matched' => true,
                    'transaction_id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                ]);
            }

            PaymentListenerLog::create([
                'tenant_id' => $tenant->id,
                'idempotency_key' => $idempotencyKey,
                'amount' => $amount,
                'source_app' => $sourceApp,
                'reference' => $reference,
                'raw_text' => $rawText,
                'status' => 'unmatched',
                'matched_transaction_id' => null,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment recorded, but no pending transaction matched with amount ' . $amount,
                'matched' => false,
            ]);
        });
    }

    /**
     * Test connection for Platform SaaS Billing Android Listener (Super Admin).
     * POST /listener/platform/test-connection
     */
    public function platformTestConnection(Request $request): JsonResponse
    {
        $secret = (string) PlatformSetting::getValue('platform_qris_secret', '');
        $timestamp = $request->header('X-Timestamp', '');
        $signature = $request->header('X-Signature', '');
        $rawContent = $request->getContent();

        if (!empty($secret)) {
            $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawContent, $secret);
            $bodySecret = (string) $request->input('secret', '');

            $isValid = (!empty($signature) && hash_equals($expectedSignature, $signature))
                    || ($bodySecret !== '' && hash_equals($secret, $bodySecret));

            if (!$isValid) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Signature mismatch (Invalid Platform Secret)',
                ], 401);
            }
        }

        $platformName = PlatformSetting::platformName();

        return response()->json([
            'status' => 'success',
            'message' => "Koneksi ke Billing Platform {$platformName} berhasil terhubung!",
            'server_time' => time(),
            'app_name' => "{$platformName} Subscription Listener",
        ]);
    }

    /**
     * Auto-verify SaaS subscription invoices via Super Admin's Android Payment Listener.
     * POST /listener/platform/payment
     */
    public function platformPayment(Request $request): JsonResponse
    {
        $rawContent = $request->getContent();
        $timestamp = $request->header('X-Timestamp', '');
        $signature = $request->header('X-Signature', '');
        $secret = (string) PlatformSetting::getValue('platform_qris_secret', '');

        $idempotencyKey = $request->header('X-Idempotency-Key')
            ?: $request->input('reference')
            ?: ('plat-' . md5($rawContent . microtime(true)));

        $amount = (float) $request->input('amount', 0);
        $sourceApp = $request->input('source_app', 'Android Listener');
        $reference = $request->input('reference', 'AUTO-' . strtoupper(uniqid()));
        $rawText = (string) $request->input('raw_text', '');

        if (!empty($secret)) {
            $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawContent, $secret);
            $bodySecret = $request->input('secret');

            $isValid = (!empty($signature) && hash_equals($expectedSignature, $signature))
                    || ($bodySecret === $secret);

            if (!$isValid) {
                Log::warning('[PlatformPaymentListener] Invalid HMAC signature');
                try {
                    PaymentListenerLog::withoutGlobalScope('tenant')->create([
                        'tenant_id' => null,
                        'is_platform_billing' => true,
                        'idempotency_key' => 'invsig-' . md5($rawContent . microtime(true)),
                        'amount' => max(0, $amount),
                        'source_app' => $sourceApp,
                        'reference' => $reference,
                        'raw_text' => $rawText ?: 'Invalid HMAC signature',
                        'status' => 'invalid_signature',
                        'matched_invoice_id' => null,
                    ]);
                } catch (\Throwable $e) {
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid HMAC-SHA256 signature or secret',
                ], 401);
            }
        }

        if ($amount <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid amount provided',
            ], 422);
        }

        $existingLog = PaymentListenerLog::withoutGlobalScope('tenant')
            ->where('is_platform_billing', true)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingLog) {
            return response()->json([
                'status' => 'success',
                'message' => 'Payment already processed (idempotent)',
                'matched' => $existingLog->status === 'matched',
                'invoice_number' => $existingLog->invoice?->invoice_number,
            ]);
        }

        $invoice = $this->subscriptionService->matchPlatformPayment($amount, $reference, $sourceApp);

        if ($invoice) {
            PaymentListenerLog::withoutGlobalScope('tenant')->create([
                'tenant_id' => null,
                'is_platform_billing' => true,
                'idempotency_key' => $idempotencyKey,
                'amount' => $amount,
                'source_app' => $sourceApp,
                'reference' => $reference,
                'raw_text' => $rawText,
                'status' => 'matched',
                'matched_invoice_id' => $invoice->id,
            ]);

            Log::info("[PlatformPaymentListener] Subscription Invoice {$invoice->invoice_number} auto-activated via {$sourceApp} Rp{$amount}");

            return response()->json([
                'status' => 'success',
                'message' => "Invoice {$invoice->invoice_number} berhasil diverifikasi & langganan aktif otomatis!",
                'matched' => true,
                'invoice_number' => $invoice->invoice_number,
                'tenant_id' => $invoice->tenant_id,
            ]);
        }

        PaymentListenerLog::withoutGlobalScope('tenant')->create([
            'tenant_id' => null,
            'is_platform_billing' => true,
            'idempotency_key' => $idempotencyKey,
            'amount' => $amount,
            'source_app' => $sourceApp,
            'reference' => $reference,
            'raw_text' => $rawText,
            'status' => 'unmatched',
            'matched_invoice_id' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notifikasi diterima, namun tidak ada invoice langganan unpaid dengan nominal Rp' . number_format($amount, 0, ',', '.'),
            'matched' => false,
        ]);
    }
}
