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

        // Find the latest transaction for that date
        $latest = Transaction::withTrashed()
            ->whereDate('transaction_date', $date)
            ->where('transaction_number', 'like', "{$prefix}%")
            ->orderBy('transaction_number', 'desc')
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
     * Create a new transaction.
     */
    public function createTransaction(array $data, int $userId): Transaction
    {
        return DB::transaction(function () use ($data, $userId) {
            $transactionDate = $data['transaction_date'];
            $transactionTime = $data['transaction_time'] ?? now()->format('H:i:s');
            
            $transactionNumber = $this->generateTransactionNumber($transactionDate);
            $globalProfitPercentage = (float) Setting::getValue('global_profit_percentage', 30);

            // Create placeholder transaction
            $transaction = Transaction::create([
                'transaction_number' => $transactionNumber,
                'transaction_date' => $transactionDate,
                'transaction_time' => $transactionTime,
                'total_quantity' => 0,
                'total_sales' => 0,
                'total_profit' => 0,
                'estimated_cost' => 0,
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

                $menu = Menu::findOrFail($menuId);

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

            $paymentMethod = $data['payment_method'] ?? 'tunai';
            $cashTendered = isset($data['cash_tendered']) && is_numeric($data['cash_tendered']) ? (float) $data['cash_tendered'] : null;
            $changeReturned = ($cashTendered !== null && $cashTendered >= $totalSales)
                ? ($cashTendered - $totalSales)
                : (isset($data['change_returned']) && is_numeric($data['change_returned']) ? (float) $data['change_returned'] : 0);
            $customerName = $data['customer_name'] ?? null;

            // Update main transaction record with aggregates & POS payment data
            $transaction->update([
                'total_quantity' => $totalQuantity,
                'total_sales' => $totalSales,
                'total_profit' => $totalProfit,
                'estimated_cost' => $totalEstimatedCost,
                'payment_method' => $paymentMethod,
                'cash_tendered' => $cashTendered ?? $totalSales,
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

                $menu = Menu::findOrFail($menuId);

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

            $transaction->total_quantity = $totalQuantity;
            $transaction->total_sales = $totalSales;
            $transaction->total_profit = $totalProfit;
            $transaction->estimated_cost = $totalEstimatedCost;
            $transaction->save();

            return $transaction;
        });
    }
}
