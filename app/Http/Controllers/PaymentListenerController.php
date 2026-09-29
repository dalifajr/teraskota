<?php

namespace App\Http\Controllers;

use App\Models\PaymentListenerLog;
use App\Models\Transaction;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentListenerController extends Controller
{
    protected QrisService $qrisService;

    public function __construct(QrisService $qrisService)
    {
        $this->qrisService = $qrisService;
    }

    /**
     * Test connection ping from Android Listener app.
     * POST /listener/test-connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        $secret = $this->qrisService->getSecret();
        $timestamp = $request->header('X-Timestamp', '');
        $signature = $request->header('X-Signature', '');
        $rawContent = $request->getContent();

        // Validate signature if secret is configured and headers are provided
        if (!empty($secret)) {
            $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $rawContent, $secret);
            $bodySecret = $request->input('secret');

            if (!empty($signature) && !hash_equals($expectedSignature, $signature)) {
                if ($bodySecret !== $secret) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Signature mismatch (Invalid Secret)',
                    ], 401);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Koneksi ke Teras Kota berhasil terhubung!',
            'server_time' => time(),
            'app_name' => 'Teras Kota Webhook Listener',
        ]);
    }

    /**
     * Handle incoming payment notification from Android Listener.
     * POST /listener/payment
     */
    public function payment(Request $request): JsonResponse
    {
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
                Log::warning('[PaymentListener] Invalid HMAC signature', [
                    'headers' => $request->headers->all(),
                    'body' => $rawContent,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid HMAC-SHA256 signature or secret',
                ], 401);
            }
        }

        // 2. Idempotency Check
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

        // 4. Match against pending QRIS transactions
        // Find pending QRIS transaction with matching final_amount
        $transaction = Transaction::where('payment_method', 'qris')
            ->where('status', 'pending')
            ->where(function ($query) use ($amount) {
                $query->where('final_amount', $amount)
                      ->orWhere(function ($q) use ($amount) {
                          $q->whereNull('final_amount')->where('total_sales', $amount);
                      });
            })
            ->where(function ($query) {
                // Must not be expired (within expiry or recent 3 hours)
                $query->whereNull('qris_expired_at')
                      ->orWhere('qris_expired_at', '>=', now()->subHours(2));
            })
            ->latest()
            ->first();

        if ($transaction) {
            // Mark transaction as paid
            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => $reference,
                'payment_source_app' => $sourceApp,
                'notified_at' => null, // Reset to null so Admin gets notification
            ]);

            // Save log entry as matched
            PaymentListenerLog::create([
                'idempotency_key' => $idempotencyKey,
                'amount' => $amount,
                'source_app' => $sourceApp,
                'reference' => $reference,
                'raw_text' => $rawText,
                'status' => 'matched',
                'matched_transaction_id' => $transaction->id,
            ]);

            Log::info("[PaymentListener] Transaction #{$transaction->id} ({$transaction->transaction_number}) marked PAID via {$sourceApp} Rp{$amount}");

            return response()->json([
                'status' => 'success',
                'message' => 'Payment matched and transaction marked as paid',
                'matched' => true,
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
            ]);
        }

        // 5. No transaction matched (recorded for manual review / ledger)
        PaymentListenerLog::create([
            'idempotency_key' => $idempotencyKey,
            'amount' => $amount,
            'source_app' => $sourceApp,
            'reference' => $reference,
            'raw_text' => $rawText,
            'status' => 'unmatched',
            'matched_transaction_id' => null,
        ]);

        Log::notice("[PaymentListener] Unmatched payment received: Rp{$amount} from {$sourceApp}");

        return response()->json([
            'status' => 'success',
            'message' => 'Payment recorded, but no pending transaction matched with amount ' . $amount,
            'matched' => false,
        ]);
    }
}
