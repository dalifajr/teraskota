<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Category;
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

        // Fetch transaction summary
        $transactionsQuery = Transaction::whereBetween('transaction_date', [$startStr, $endStr]);
        
        $totalSales = (float) $transactionsQuery->sum('total_sales');
        $totalProfit = (float) $transactionsQuery->sum('total_profit');
        $estimatedCost = (float) $transactionsQuery->sum('estimated_cost');
        $totalTransactions = $transactionsQuery->count();
        $totalQuantity = (int) $transactionsQuery->sum('total_quantity');
        $averageSales = $totalTransactions > 0 ? ($totalSales / $totalTransactions) : 0;

        // Get grouping for charts
        $groupBy = $request->get('group_by', 'daily');
        if (!in_array($groupBy, ['daily', 'weekly', 'monthly', 'yearly'])) {
            $groupBy = 'daily';
        }

        // Group transactions for trend chart
        $trendData = [];
        if ($groupBy === 'daily') {
            $trendQuery = Transaction::whereBetween('transaction_date', [$startStr, $endStr])
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
            $trendQuery = Transaction::whereBetween('transaction_date', [$startStr, $endStr])
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
            $trendQuery = Transaction::whereBetween('transaction_date', [$startStr, $endStr])
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
            $trendQuery = Transaction::whereBetween('transaction_date', [$startStr, $endStr])
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
                $q->whereBetween('transaction_date', [$startStr, $endStr]);
            })
            ->select('menu_name_snapshot', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(subtotal) as sales'), DB::raw('SUM(profit_amount) as profit'))
            ->groupBy('menu_name_snapshot')
            ->orderBy('qty', 'desc')
            ->limit(10)
            ->get();

        // Least Sold Menus
        $bottomMenus = TransactionDetail::whereHas('transaction', function ($q) use ($startStr, $endStr) {
                $q->whereBetween('transaction_date', [$startStr, $endStr]);
            })
            ->select('menu_name_snapshot', DB::raw('SUM(quantity) as qty'))
            ->groupBy('menu_name_snapshot')
            ->orderBy('qty', 'asc')
            ->limit(10)
            ->get();

        // Sales by Category
        $categorySales = TransactionDetail::whereHas('transaction', function ($q) use ($startStr, $endStr) {
                $q->whereBetween('transaction_date', [$startStr, $endStr]);
            })
            ->select('category_name_snapshot as name', DB::raw('SUM(subtotal) as sales'), DB::raw('SUM(quantity) as qty'))
            ->groupBy('category_name_snapshot')
            ->orderBy('sales', 'desc')
            ->get();

        return view('dashboard.index', compact(
            'period',
            'startDate',
            'endDate',
            'totalSales',
            'totalProfit',
            'estimatedCost',
            'totalTransactions',
            'totalQuantity',
            'averageSales',
            'trendData',
            'topMenus',
            'bottomMenus',
            'categorySales',
            'groupBy'
        ));
    }
}
