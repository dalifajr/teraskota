<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index(Request $request)
    {
        $period = $request->get('period', 'this_month');
        $startDate = null;
        $endDate = null;

        // Determine Start and End Dates based on Filter
        switch ($period) {
            case 'today':
                $startDate = Carbon::today();
                $endDate = Carbon::today();
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday();
                $endDate = Carbon::yesterday();
                break;
            case 'this_week':
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            case 'prev_week':
                $startDate = Carbon::now()->subWeek()->startOfWeek();
                $endDate = Carbon::now()->subWeek()->endOfWeek();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
            case 'prev_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                break;
            case 'this_year':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
                break;
            case 'specific_day':
                $dateStr = $request->get('specific_day', Carbon::today()->format('Y-m-d'));
                $startDate = Carbon::parse($dateStr);
                $endDate = Carbon::parse($dateStr);
                break;
            case 'specific_month':
                $monthStr = $request->get('specific_month', Carbon::today()->format('Y-m'));
                $startDate = Carbon::parse($monthStr . '-01')->startOfMonth();
                $endDate = Carbon::parse($monthStr . '-01')->endOfMonth();
                break;
            case 'specific_year':
                $yearStr = $request->get('specific_year', Carbon::today()->format('Y'));
                $startDate = Carbon::parse($yearStr . '-01-01')->startOfYear();
                $endDate = Carbon::parse($yearStr . '-01-01')->endOfYear();
                break;
            case 'custom':
                $startDateStr = $request->get('start_date', Carbon::today()->format('Y-m-d'));
                $endDateStr = $request->get('end_date', Carbon::today()->format('Y-m-d'));
                $startDate = Carbon::parse($startDateStr);
                $endDate = Carbon::parse($endDateStr);
                break;
            default:
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
        }

        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        // Auto-expire overdue pending QRIS transactions before aggregating metrics
        Transaction::expireOverdueQris();

        // Fetch transaction summary (strictly paid transactions only)
        $transactionsQuery = Transaction::paid()
            ->whereDate('transaction_date', '>=', $startStr)
            ->whereDate('transaction_date', '<=', $endStr);
        
        $totalSales = (float) (clone $transactionsQuery)->sum('total_sales');
        $totalProfit = (float) (clone $transactionsQuery)->sum('total_profit');
        $estimatedCost = (float) (clone $transactionsQuery)->sum('estimated_cost');
        $totalDiscount = (float) (clone $transactionsQuery)->sum('discount_amount');
        $totalTransactions = (clone $transactionsQuery)->count();
        $totalQuantity = (int) (clone $transactionsQuery)->sum('total_quantity');
        $averageSales = $totalTransactions > 0 ? ($totalSales / $totalTransactions) : 0;

        $totalExpenses = (float) Expense::whereDate('expense_date', '>=', $startStr)
            ->whereDate('expense_date', '<=', $endStr)
            ->sum('amount');
        $totalCashExpenses = (float) Expense::whereDate('expense_date', '>=', $startStr)
            ->whereDate('expense_date', '<=', $endStr)
            ->where('payment_method', 'tunai')
            ->sum('amount');
        $netProfit = $totalProfit - $totalExpenses;

        // Previous period comparison (same day span immediately preceding startDate)
        $spanDays = max(1, (int) $startDate->diffInDays($endDate) + 1);
        $prevEndDate = $startDate->copy()->subDay();
        $prevStartDate = $prevEndDate->copy()->subDays($spanDays - 1);
        $prevStartStr = $prevStartDate->format('Y-m-d');
        $prevEndStr = $prevEndDate->format('Y-m-d');

        $prevTxQuery = Transaction::paid()
            ->whereDate('transaction_date', '>=', $prevStartStr)
            ->whereDate('transaction_date', '<=', $prevEndStr);
        $prevTotalSales = (float) (clone $prevTxQuery)->sum('total_sales');
        $prevTotalProfit = (float) (clone $prevTxQuery)->sum('total_profit');
        $prevTotalExpenses = (float) Expense::whereDate('expense_date', '>=', $prevStartStr)
            ->whereDate('expense_date', '<=', $prevEndStr)
            ->sum('amount');
        $prevNetProfit = $prevTotalProfit - $prevTotalExpenses;

        $salesGrowthPct = $prevTotalSales > 0
            ? round((($totalSales - $prevTotalSales) / $prevTotalSales) * 100, 1)
            : ($totalSales > 0 ? 100.0 : null);
        $netProfitGrowthPct = abs($prevNetProfit) > 0
            ? round((($netProfit - $prevNetProfit) / abs($prevNetProfit)) * 100, 1)
            : ($netProfit > 0 ? 100.0 : null);

        // Get grouping for charts
        $groupBy = $request->get('group_by', 'daily');
        if (!in_array($groupBy, ['daily', 'weekly', 'monthly', 'yearly'])) {
            $groupBy = 'daily';
        }

        // Group transactions for trend chart
        $trendData = [];
        if ($groupBy === 'daily') {
            $trendQuery = Transaction::paid()
                ->whereDate('transaction_date', '>=', $startStr)
                ->whereDate('transaction_date', '<=', $endStr)
                ->select(
                    DB::raw('DATE(transaction_date) as date_label'),
                    DB::raw('SUM(total_sales) as sales'),
                    DB::raw('SUM(total_profit) as profit'),
                    DB::raw('SUM(total_quantity) as quantity')
                )
                ->groupBy('date_label')
                ->orderBy('date_label', 'asc')
                ->get();
            
            foreach ($trendQuery as $row) {
                $trendData[] = [
                    'label' => Carbon::parse($row->date_label)->translatedFormat('d M Y'),
                    'sales' => (float) $row->sales,
                    'profit' => (float) $row->profit,
                    'quantity' => (int) $row->quantity,
                ];
            }
        } elseif ($groupBy === 'weekly') {
            $trendQuery = Transaction::paid()
                ->whereDate('transaction_date', '>=', $startStr)
                ->whereDate('transaction_date', '<=', $endStr)
                ->select(
                    DB::raw('YEAR(transaction_date) as yr'),
                    DB::raw('WEEK(transaction_date) as wk'),
                    DB::raw('SUM(total_sales) as sales'),
                    DB::raw('SUM(total_profit) as profit'),
                    DB::raw('SUM(total_quantity) as quantity')
                )
                ->groupBy('yr', 'wk')
                ->orderBy('yr', 'asc')
                ->orderBy('wk', 'asc')
                ->get();
            
            foreach ($trendQuery as $row) {
                $trendData[] = [
                    'label' => 'W-' . $row->wk . ' ' . $row->yr,
                    'sales' => (float) $row->sales,
                    'profit' => (float) $row->profit,
                    'quantity' => (int) $row->quantity,
                ];
            }
        } elseif ($groupBy === 'monthly') {
            $trendQuery = Transaction::paid()
                ->whereDate('transaction_date', '>=', $startStr)
                ->whereDate('transaction_date', '<=', $endStr)
                ->select(
                    DB::raw('YEAR(transaction_date) as yr'),
                    DB::raw('MONTH(transaction_date) as mth'),
                    DB::raw('SUM(total_sales) as sales'),
                    DB::raw('SUM(total_profit) as profit'),
                    DB::raw('SUM(total_quantity) as quantity')
                )
                ->groupBy('yr', 'mth')
                ->orderBy('yr', 'asc')
                ->orderBy('mth', 'asc')
                ->get();
            
            foreach ($trendQuery as $row) {
                $trendData[] = [
                    'label' => Carbon::createFromDate($row->yr, $row->mth, 1)->translatedFormat('F Y'),
                    'sales' => (float) $row->sales,
                    'profit' => (float) $row->profit,
                    'quantity' => (int) $row->quantity,
                ];
            }
        } else { // yearly
            $trendQuery = Transaction::paid()
                ->whereDate('transaction_date', '>=', $startStr)
                ->whereDate('transaction_date', '<=', $endStr)
                ->select(
                    DB::raw('YEAR(transaction_date) as yr'),
                    DB::raw('SUM(total_sales) as sales'),
                    DB::raw('SUM(total_profit) as profit'),
                    DB::raw('SUM(total_quantity) as quantity')
                )
                ->groupBy('yr')
                ->orderBy('yr', 'asc')
                ->get();
            
            foreach ($trendQuery as $row) {
                $trendData[] = [
                    'label' => (string) $row->yr,
                    'sales' => (float) $row->sales,
                    'profit' => (float) $row->profit,
                    'quantity' => (int) $row->quantity,
                ];
            }
        }

        // Top 10 Best Sellers
        $topMenus = TransactionDetail::whereHas('transaction', function ($q) use ($startStr, $endStr) {
                $q->paid()
                  ->whereDate('transaction_date', '>=', $startStr)
                  ->whereDate('transaction_date', '<=', $endStr);
            })
            ->select('menu_name_snapshot', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(subtotal) as sales'), DB::raw('SUM(profit_amount) as profit'))
            ->groupBy('menu_name_snapshot')
            ->orderBy('qty', 'desc')
            ->limit(10)
            ->get();

        // Least Sold Menus
        $bottomMenus = TransactionDetail::whereHas('transaction', function ($q) use ($startStr, $endStr) {
                $q->paid()
                  ->whereDate('transaction_date', '>=', $startStr)
                  ->whereDate('transaction_date', '<=', $endStr);
            })
            ->select('menu_name_snapshot', DB::raw('SUM(quantity) as qty'))
            ->groupBy('menu_name_snapshot')
            ->orderBy('qty', 'asc')
            ->limit(10)
            ->get();

        // Sales by Category
        $categorySales = TransactionDetail::whereHas('transaction', function ($q) use ($startStr, $endStr) {
                $q->paid()
                  ->whereDate('transaction_date', '>=', $startStr)
                  ->whereDate('transaction_date', '<=', $endStr);
            })
            ->select('category_name_snapshot as name', DB::raw('SUM(subtotal) as sales'), DB::raw('SUM(quantity) as qty'))
            ->groupBy('category_name_snapshot')
            ->orderBy('sales', 'desc')
            ->get();

        // Payment Method Breakdown for Selected Period (Tunai vs QRIS)
        $cashQuery = (clone $transactionsQuery)->where('payment_method', 'tunai');
        $totalCashSales = (float) (clone $cashQuery)->sum('total_sales');
        $totalCashCount = (int) (clone $cashQuery)->count();

        $qrisQuery = (clone $transactionsQuery)->where('payment_method', 'qris');
        $totalQrisSales = (float) (clone $qrisQuery)->sum('total_sales');
        $totalQrisCount = (int) (clone $qrisQuery)->count();

        // Estimated physical cash in drawer (Cash Sales - Cash Expenses)
        $cashDrawerEstimate = $totalCashSales - $totalCashExpenses;

        // Per-Cashier Performance Breakdown for Shift Reconciliation
        $cashierRaw = Transaction::paid()
            ->whereDate('transaction_date', '>=', $startStr)
            ->whereDate('transaction_date', '<=', $endStr)
            ->select(
                'created_by',
                DB::raw('COUNT(*) as tx_count'),
                DB::raw('SUM(total_sales) as total_sales'),
                DB::raw("SUM(CASE WHEN payment_method = 'tunai' THEN total_sales ELSE 0 END) as cash_sales"),
                DB::raw("SUM(CASE WHEN payment_method = 'qris' THEN total_sales ELSE 0 END) as qris_sales")
            )
            ->groupBy('created_by')
            ->orderByDesc('total_sales')
            ->get();

        $cashierUsers = \App\Models\User::whereIn('id', $cashierRaw->pluck('created_by')->filter()->all())
            ->pluck('name', 'id');

        $cashierStats = $cashierRaw->map(function ($row) use ($cashierUsers) {
            return (object) [
                'name' => $cashierUsers[$row->created_by] ?? 'Sistem / Kasir',
                'tx_count' => (int) $row->tx_count,
                'total_sales' => (float) $row->total_sales,
                'cash_sales' => (float) $row->cash_sales,
                'qris_sales' => (float) $row->qris_sales,
            ];
        });

        // Today's Pulse Metrics (WIB)
        $todayStr = Carbon::today()->format('Y-m-d');
        $todayTxQuery = Transaction::paid()->whereDate('transaction_date', $todayStr);
        $todaySales = (float) (clone $todayTxQuery)->sum('total_sales');
        $todayCashSales = (float) (clone $todayTxQuery)->where('payment_method', 'tunai')->sum('total_sales');
        $todayQrisSales = (float) (clone $todayTxQuery)->where('payment_method', 'qris')->sum('total_sales');
        $todayProfit = (float) (clone $todayTxQuery)->sum('total_profit');
        $todayTransactions = (int) (clone $todayTxQuery)->count();
        $todayExpenses = (float) Expense::whereDate('expense_date', $todayStr)->sum('amount');
        $todayCashExpenses = (float) Expense::whereDate('expense_date', $todayStr)->where('payment_method', 'tunai')->sum('amount');
        $todayNetProfit = $todayProfit - $todayExpenses;
        $todayCashDrawer = $todayCashSales - $todayCashExpenses;

        // Recent 5 Transactions for Admin Monitoring
        $recentTransactions = Transaction::paid()
            ->with('user')
            ->latest('id')
            ->take(5)
            ->get();

        $totalActiveMenus = \App\Models\Menu::where('status', true)->count();
        $totalCategories = Category::count();

        // Monthly Sales Target Progress (always reflects current month regardless of active filter)
        $monthlySalesTarget = (float) \App\Models\Setting::getValue('monthly_sales_target', 0);
        $currentMonthSales = $monthlySalesTarget > 0
            ? (float) Transaction::paid()
                ->whereYear('transaction_date', Carbon::now()->year)
                ->whereMonth('transaction_date', Carbon::now()->month)
                ->sum('total_sales')
            : 0.0;
        $monthlyTargetPct = $monthlySalesTarget > 0
            ? min(100, round(($currentMonthSales / $monthlySalesTarget) * 100, 1))
            : 0.0;
        $daysLeftInMonth = max(1, (int) Carbon::now()->diffInDays(Carbon::now()->endOfMonth()) + 1);

        return view('dashboard.index', compact(
            'period',
            'startDate',
            'endDate',
            'totalSales',
            'totalProfit',
            'estimatedCost',
            'totalDiscount',
            'totalExpenses',
            'totalCashExpenses',
            'cashDrawerEstimate',
            'netProfit',
            'salesGrowthPct',
            'netProfitGrowthPct',
            'totalTransactions',
            'totalQuantity',
            'averageSales',
            'totalCashSales',
            'totalCashCount',
            'totalQrisSales',
            'totalQrisCount',
            'cashierStats',
            'todaySales',
            'todayCashSales',
            'todayQrisSales',
            'todayTransactions',
            'todayExpenses',
            'todayCashExpenses',
            'todayCashDrawer',
            'todayNetProfit',
            'recentTransactions',
            'totalActiveMenus',
            'totalCategories',
            'monthlySalesTarget',
            'currentMonthSales',
            'monthlyTargetPct',
            'daysLeftInMonth',
            'trendData',
            'topMenus',
            'bottomMenus',
            'categorySales',
            'groupBy'
        ));
    }

    /**
     * Poll unnotified paid transactions for Admin browser push notifications.
     */
    public function unreadTransactions(Request $request)
    {
        $transactions = Transaction::where('status', 'paid')
            ->whereNull('notified_at')
            ->where('created_at', '>=', now()->subHours(24))
            ->latest()
            ->take(5)
            ->get();

        if ($transactions->isNotEmpty()) {
            $ids = $transactions->pluck('id')->toArray();
            Transaction::whereIn('id', $ids)->update(['notified_at' => now()]);
        }

        return response()->json([
            'count' => $transactions->count(),
            'items' => $transactions->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'transaction_number' => $tx->transaction_number,
                    'total_sales' => (float) ($tx->final_amount ?: $tx->total_sales),
                    'payment_method' => strtoupper($tx->payment_method),
                    'customer_name' => $tx->customer_name ?: 'Pelanggan',
                    'time' => $tx->transaction_time ? substr((string) $tx->transaction_time, 0, 5) : now()->format('H:i'),
                    'url' => route('transactions.show', $tx->id),
                ];
            }),
        ]);
    }
}
