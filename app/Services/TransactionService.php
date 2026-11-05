<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionService
{
    /**
     * Generate a unique transaction number.
     * Format: TRX-YYYYMMDD-XXXX
     */
    public function generateTransactionNumber(string $date): string
    {
        $formattedDate = Carbon::parse($date)->format('Ymd');
        $prefix = "TRX-{$formattedDate}-";

        // Find the latest transaction for that date with row-level lock inside transaction
        $latest = Transaction::withTrashed()
            ->whereDate('transaction_date', $date)
            ->where('transaction_number', 'like', "{$prefix}%")
            ->orderBy('transaction_number', 'desc')
            ->lockForUpdate()
            ->first();

        if ($latest) {
            // Extract the counter
            $parts = explode('-', $latest->transaction_number);
            $counter = (int) end($parts);
            $nextCounter = str_pad($counter + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextCounter = '0001';
        }

        return $prefix . $nextCounter;
    }

    /**
     * Calculate discount type, value, and Rupiah amount safely against subtotal.
     */
    public function resolveDiscount(float $subtotalAmount, array $data, ?Transaction $existing = null): array
    {
        $discountType = $data['discount_type'] ?? ($existing?->discount_type ?? null);
        $discountValue = isset($data['discount_value']) && is_numeric($data['discount_value'])
            ? (float) $data['discount_value']
            : (isset($data['discount_amount']) && is_numeric($data['discount_amount']) && empty($discountType)
                ? (float) $data['discount_amount']
                : (float) ($existing?->discount_value ?? 0));

        if (empty($discountType) && $discountValue > 0) {
            $discountType = 'nominal';
        }

        if ($discountValue <= 0 || !in_array($discountType, ['nominal', 'percentage'], true)) {
            return [
                'discount_type' => null,
                'discount_value' => 0.0,
                'discount_amount' => 0.0,
            ];
        }

        if ($discountType === 'percentage') {
            $discountValue = min(100.0, max(0.0, $discountValue));
            $discountAmount = round($subtotalAmount * ($discountValue / 100.0), 2);
        } else {
            $discountValue = max(0.0, $discountValue);
            $discountAmount = min($subtotalAmount, $discountValue);
        }

        return [
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
        ];
    }

    /**
     * Create a new transaction.
     */
    public function createTransaction(array $data, int $userId): Transaction
    {
        // Idempotency check: if sync_id already exists, return existing transaction
        if (!empty($data['sync_id'])) {
            $existing = Transaction::where('sync_id', $data['sync_id'])
                ->with(['details', 'cashier'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($data, $userId) {
            $transactionDate = $data['transaction_date'];
            $transactionTime = $data['transaction_time'] ?? now()->format('H:i:s');
            
            $transactionNumber = $this->generateTransactionNumber($transactionDate);
            $globalProfitPercentage = (float) Setting::getValue('global_profit_percentage', 30);
            $status = $data['status'] ?? 'paid';

            // Pre-fetch all required menus with their categories to prevent N+1 queries
            $menuIds = collect($data['items'])->pluck('menu_id')->unique()->filter()->values();
            $menus = Menu::with('category')->whereIn('id', $menuIds)->get()->keyBy('id');

            // Create placeholder transaction
            $transaction = Transaction::create([
                'sync_id' => $data['sync_id'] ?? null,
                'transaction_number' => $transactionNumber,
                'transaction_date' => $transactionDate,
                'transaction_time' => $transactionTime,
                'total_quantity' => 0,
                'total_sales' => 0,
                'total_profit' => 0,
                'estimated_cost' => 0,
                'status' => $status,
                'unique_code' => $data['unique_code'] ?? null,
                'final_amount' => $data['final_amount'] ?? null,
                'qris_payload' => $data['qris_payload'] ?? null,
                'qris_expired_at' => $data['qris_expired_at'] ?? null,
                'paid_at' => $status === 'paid' ? ($data['paid_at'] ?? now()) : null,
                'source_device_id' => $data['source_device_id'] ?? null,
                'sync_status' => $data['sync_status'] ?? 'synced',
                'synced_at' => $data['synced_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $totalQuantity = 0;
            $totalSales = 0.0;
            $totalProfit = 0.0;
            $totalEstimatedCost = 0.0;

            foreach ($data['items'] as $item) {
                $menuId = $item['menu_id'];
                $quantity = (int) $item['quantity'];

                if ($quantity <= 0) continue;

                $menu = $menus->get($menuId) ?? Menu::with('category')->findOrFail($menuId);

                // Determine profit percentage
                $profitPercentage = $menu->use_global_profit 
                    ? $globalProfitPercentage 
                    : (float) ($menu->profit_percentage ?? $globalProfitPercentage);

                $price = (float) $menu->price;
                $subtotal = $price * $quantity;
                $profitAmount = $subtotal * ($profitPercentage / 100);
                $estimatedCost = $subtotal - $profitAmount;

                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'menu_id' => $menu->id,
                    'menu_name_snapshot' => $menu->name,
                    'category_name_snapshot' => $menu->category->name,
                    'price_snapshot' => $price,
                    'profit_percentage_snapshot' => $profitPercentage,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'profit_amount' => $profitAmount,
                    'estimated_cost' => $estimatedCost,
                ]);

                $totalQuantity += $quantity;
                $totalSales += $subtotal;
                $totalProfit += $profitAmount;
                $totalEstimatedCost += $estimatedCost;
            }

            $subtotalAmount = $totalSales;
            $discountData = $this->resolveDiscount($subtotalAmount, $data);
            $discountAmount = $discountData['discount_amount'];
            $netSales = max(0.0, $subtotalAmount - $discountAmount);
            $netProfit = $totalProfit - $discountAmount;

            $paymentMethod = $data['payment_method'] ?? 'tunai';
            $cashTendered = isset($data['cash_tendered']) && is_numeric($data['cash_tendered']) ? (float) $data['cash_tendered'] : null;

            if ($paymentMethod === 'tunai' && $cashTendered !== null && $cashTendered < $netSales) {
                throw new \InvalidArgumentException(
                    'Nominal uang tunai yang diterima (Rp' . number_format($cashTendered, 0, ',', '.') . ') kurang dari total belanja setelah diskon (Rp' . number_format($netSales, 0, ',', '.') . ').'
                );
            }

            $changeReturned = ($cashTendered !== null && $cashTendered >= $netSales)
                ? ($cashTendered - $netSales)
                : (isset($data['change_returned']) && is_numeric($data['change_returned']) ? (float) $data['change_returned'] : 0);
            $customerName = $data['customer_name'] ?? null;

            // Update main transaction record with aggregates & POS payment data
            $transaction->update([
                'total_quantity' => $totalQuantity,
                'subtotal_amount' => $subtotalAmount,
                'discount_type' => $discountData['discount_type'],
                'discount_value' => $discountData['discount_value'],
                'discount_amount' => $discountAmount,
                'total_sales' => $netSales,
                'total_profit' => $netProfit,
                'estimated_cost' => $totalEstimatedCost,
                'payment_method' => $paymentMethod,
                'cash_tendered' => $cashTendered ?? ($data['final_amount'] ?? $netSales),
                'change_returned' => $changeReturned,
                'customer_name' => $customerName,
            ]);

            return $transaction;
        });
    }

    /**
     * Update an existing transaction.
     */
    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $globalProfitPercentage = (float) Setting::getValue('global_profit_percentage', 30);

            // Update header info (except transaction number, or update it if date changes)
            $transactionDate = $data['transaction_date'];
            $transactionTime = $data['transaction_time'] ?? $transaction->transaction_time;

            if (Carbon::parse($transaction->transaction_date)->format('Y-m-d') !== Carbon::parse($transactionDate)->format('Y-m-d')) {
                $transactionNumber = $this->generateTransactionNumber($transactionDate);
                $transaction->transaction_number = $transactionNumber;
            }

            $transaction->transaction_date = $transactionDate;
            $transaction->transaction_time = $transactionTime;
            $transaction->notes = $data['notes'] ?? null;

            // Pre-fetch all required menus with their categories to prevent N+1 queries
            $menuIds = collect($data['items'])->pluck('menu_id')->unique()->filter()->values();
            $menus = Menu::with('category')->whereIn('id', $menuIds)->get()->keyBy('id');

            // Delete old details
            $transaction->details()->delete();

            $totalQuantity = 0;
            $totalSales = 0.0;
            $totalProfit = 0.0;
            $totalEstimatedCost = 0.0;

            foreach ($data['items'] as $item) {
                $menuId = $item['menu_id'];
                $quantity = (int) $item['quantity'];

                if ($quantity <= 0) continue;

                $menu = $menus->get($menuId) ?? Menu::with('category')->findOrFail($menuId);

                // Determine profit percentage
                $profitPercentage = $menu->use_global_profit 
                    ? $globalProfitPercentage 
                    : (float) ($menu->profit_percentage ?? $globalProfitPercentage);

                $price = (float) $menu->price;
                $subtotal = $price * $quantity;
                $profitAmount = $subtotal * ($profitPercentage / 100);
                $estimatedCost = $subtotal - $profitAmount;

                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'menu_id' => $menu->id,
                    'menu_name_snapshot' => $menu->name,
                    'category_name_snapshot' => $menu->category->name,
                    'price_snapshot' => $price,
                    'profit_percentage_snapshot' => $profitPercentage,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'profit_amount' => $profitAmount,
                    'estimated_cost' => $estimatedCost,
                ]);

                $totalQuantity += $quantity;
                $totalSales += $subtotal;
                $totalProfit += $profitAmount;
                $totalEstimatedCost += $estimatedCost;
            }

            $oldTotalSales = (float) $transaction->total_sales;
            $subtotalAmount = $totalSales;
            $discountData = $this->resolveDiscount($subtotalAmount, $data, $transaction);
            $discountAmount = $discountData['discount_amount'];
            $netSales = max(0.0, $subtotalAmount - $discountAmount);
            $netProfit = $totalProfit - $discountAmount;

            $transaction->total_quantity = $totalQuantity;
            $transaction->subtotal_amount = $subtotalAmount;
            $transaction->discount_type = $discountData['discount_type'];
            $transaction->discount_value = $discountData['discount_value'];
            $transaction->discount_amount = $discountAmount;
            $transaction->total_sales = $netSales;
            $transaction->total_profit = $netProfit;
            $transaction->estimated_cost = $totalEstimatedCost;

            // Reconcile payment fields when transaction items are edited
            if ($transaction->unique_code) {
                $transaction->final_amount = $netSales + (int) $transaction->unique_code;
            }
            if ($transaction->payment_method === 'tunai') {
                $currentCash = (float) ($transaction->cash_tendered ?? 0);
                if ($currentCash <= $oldTotalSales || $currentCash < $netSales) {
                    $transaction->cash_tendered = $netSales;
                    $transaction->change_returned = 0;
                } else {
                    $transaction->change_returned = $currentCash - $netSales;
                }
            } else {
                $transaction->cash_tendered = $transaction->final_amount ?? $netSales;
                $transaction->change_returned = 0;
            }

            $transaction->save();

            return $transaction;
        });
    }
}
