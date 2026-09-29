<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class OfflineSyncController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Bootstrap data for offline POS operation.
     * Returns master categories, active menus, profit settings, and active cashier profile.
     */
    public function bootstrap(Request $request)
    {
        $user = Auth::user();

        $categories = Category::where('status', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        $menus = Menu::where('status', true)
            ->with(['category:id,name'])
            ->orderBy('name', 'asc')
            ->get([
                'id',
                'category_id',
                'name',
                'price',
                'use_global_profit',
                'profit_percentage',
                'status',
                'updated_at'
            ]);

        $globalProfit = (float) Setting::getValue('global_profit_percentage', 30);

        return response()->json([
            'success' => true,
            'cashier' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
            'settings' => [
                'global_profit_percentage' => $globalProfit,
            ],
            'categories' => $categories,
            'menus' => $menus,
            'server_time' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Handle synchronization of offline transactions.
     * Supports both single transaction payload and batch transactions array.
     */
    public function sync(Request $request)
    {
        // Support both single transaction payload and batch payload
        $transactionsInput = $request->has('transactions') && is_array($request->input('transactions'))
            ? $request->input('transactions')
            : [$request->all()];

        if (empty($transactionsInput)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data transaksi untuk disinkronkan.',
            ], 422);
        }

        $userId = Auth::id();
        $results = [];
        $hasErrors = false;

        foreach ($transactionsInput as $input) {
            $syncId = $input['sync_id'] ?? null;

            if (empty($syncId)) {
                $results[] = [
                    'sync_id' => null,
                    'status' => 'failed',
                    'message' => 'Atribut sync_id wajib diisi untuk setiap transaksi offline.',
                ];
                $hasErrors = true;
                continue;
            }

            // Check if already processed (Idempotency fast path)
            $existing = Transaction::where('sync_id', $syncId)->first();
            if ($existing) {
                $results[] = [
                    'sync_id' => $syncId,
                    'status' => 'synced',
                    'is_duplicate' => true,
                    'server_id' => $existing->id,
                    'transaction_number' => $existing->transaction_number,
                    'total_sales' => (float) $existing->total_sales,
                    'synced_at' => $existing->synced_at ? $existing->synced_at->toIso8601String() : now()->toIso8601String(),
                ];
                continue;
            }

            // Validate transaction payload
            $validator = Validator::make($input, [
                'sync_id' => ['required', 'string', 'max:64'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.menu_id' => ['required', 'integer', 'exists:menus,id'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
                'payment_method' => ['nullable', 'string', 'in:tunai,qris'],
                'cash_tendered' => ['nullable', 'numeric', 'min:0'],
                'change_returned' => ['nullable', 'numeric', 'min:0'],
                'customer_name' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:500'],
                'source_device_id' => ['nullable', 'string', 'max:100'],
                'transaction_date' => ['nullable', 'date_format:Y-m-d'],
                'transaction_time' => ['nullable'],
            ]);

            if ($validator->fails()) {
                $results[] = [
                    'sync_id' => $syncId,
                    'status' => 'conflict',
                    'message' => 'Validasi gagal: ' . implode(', ', $validator->errors()->all()),
                    'errors' => $validator->errors()->toArray(),
                ];
                $hasErrors = true;
                continue;
            }

            $validated = $validator->validated();

            // Prepare transaction structure for service
            $txDate = $validated['transaction_date'] ?? Carbon::today()->format('Y-m-d');
            $txTime = $validated['transaction_time'] ?? Carbon::now()->format('H:i:s');
            // If time contains full ISO or invalid format, sanitize to H:i:s
            if (strlen($txTime) > 8) {
                try {
                    $txTime = Carbon::parse($txTime)->format('H:i:s');
                } catch (\Exception $e) {
                    $txTime = Carbon::now()->format('H:i:s');
                }
            }

            $txData = [
                'sync_id' => $syncId,
                'source_device_id' => $validated['source_device_id'] ?? ($input['device_id'] ?? null),
                'sync_status' => 'synced',
                'synced_at' => Carbon::now(),
                'transaction_date' => $txDate,
                'transaction_time' => $txTime,
                'items' => $validated['items'],
                'payment_method' => $validated['payment_method'] ?? 'tunai',
                'cash_tendered' => $validated['cash_tendered'] ?? null,
                'change_returned' => $validated['change_returned'] ?? 0,
                'customer_name' => $validated['customer_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ];

            try {
                $transaction = $this->transactionService->createTransaction($txData, $userId);

                $results[] = [
                    'sync_id' => $syncId,
                    'status' => 'synced',
                    'is_duplicate' => false,
                    'server_id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'total_sales' => (float) $transaction->total_sales,
                    'synced_at' => $transaction->synced_at ? $transaction->synced_at->toIso8601String() : now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'sync_id' => $syncId,
                    'status' => 'failed',
                    'message' => 'Gagal memproses transaksi: ' . $e->getMessage(),
                ];
                $hasErrors = true;
            }
        }

        $allSynced = !empty($results) && collect($results)->every(fn($r) => $r['status'] === 'synced');

        return response()->json([
            'success' => $allSynced,
            'message' => $allSynced ? 'Semua transaksi offline berhasil disinkronkan.' : 'Sebagian atau seluruh transaksi gagal disinkronkan.',
            'synced_count' => collect($results)->where('status', 'synced')->count(),
            'total_count' => count($results),
            'results' => $results,
            // Convenience shortcut for single transaction sync response
            'sync_id' => $results[0]['sync_id'] ?? null,
            'status' => $results[0]['status'] ?? 'unknown',
            'server_id' => $results[0]['server_id'] ?? null,
            'transaction_number' => $results[0]['transaction_number'] ?? null,
        ], $allSynced ? 200 : ($hasErrors ? 422 : 200));
    }
}
