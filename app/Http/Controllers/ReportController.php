<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Display report index.
     */
    public function index(Request $request)
    {
        $reportData = $this->getReportData($request);
        $categories = Category::all();
        $menus = Menu::all();

        return view('reports.index', array_merge($reportData, [
            'categories' => $categories,
            'menus' => $menus,
            'period' => $request->get('period', 'this_month'),
            'start_date' => $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'end_date' => $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'category_id' => $request->get('category_id'),
            'menu_id' => $request->get('menu_id'),
        ]));
    }

    /**
     * Export report to PDF.
     */
    public function pdf(Request $request)
    {
        $data = $this->getReportData($request);
        $pdf = Pdf::loadView('reports.pdf', $data);
        
        $filename = 'laporan-penjualan-' . Carbon::parse($data['start_date'])->format('Ymd') . '-to-' . Carbon::parse($data['end_date'])->format('Ymd') . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Export report to CSV/Excel.
     */
    public function export(Request $request)
    {
        $data = $this->getReportData($request);
        $filename = "laporan-penjualan-" . Carbon::parse($data['start_date'])->format('Ymd') . "-to-" . Carbon::parse($data['end_date'])->format('Ymd') . ".csv";

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Excel UTF-8 BOM
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['LAPORAN PENJUALAN TERAS KOTA BERLIAN MAKMUR']);
            fputcsv($file, ['Periode', $data['date_range_label']]);
            fputcsv($file, ['Tanggal Cetak', now()->translatedFormat('d F Y H:i')]);
            fputcsv($file, ['Nama Admin', $data['admin_name']]);
            fputcsv($file, []);

            fputcsv($file, ['RINGKASAN']);
            fputcsv($file, ['Total Transaksi', $data['total_transactions']]);
            fputcsv($file, ['Total Produk Terjual', $data['total_quantity']]);
            fputcsv($file, ['Total Omzet (Rp)', number_format($data['total_sales'], 0, ',', '.')]);
            fputcsv($file, ['Total Keuntungan (Rp)', number_format($data['total_profit'], 0, ',', '.')]);
            fputcsv($file, ['Estimasi Modal (Rp)', number_format($data['estimated_cost'], 0, ',', '.')]);
            fputcsv($file, []);

            fputcsv($file, ['RINCIAN TRANSAKSI']);
            fputcsv($file, ['No', 'Menu', 'Kategori', 'Jumlah Terjual', 'Omzet (Rp)', 'Keuntungan (Rp)', 'Estimasi Modal (Rp)']);

            $no = 1;
            foreach ($data['items'] as $item) {
                fputcsv($file, [
                    $no++,
                    $item->menu_name,
                    $item->category_name,
                    $item->total_quantity,
                    $item->total_sales,
                    $item->total_profit,
                    $item->total_estimated_cost
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Parse filter params and compute report statistics.
     */
    private function getReportData(Request $request): array
    {
        $period = $request->get('period', 'this_month');
        $startDate = null;
        $endDate = null;

        // Parse date limits
        switch ($period) {
            case 'today':
                $startDate = Carbon::today();
                $endDate = Carbon::today();
                break;
            case 'this_week':
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
            case 'this_year':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
                break;
            case 'custom':
                $startDate = Carbon::parse($request->get('start_date', Carbon::today()->format('Y-m-d')));
                $endDate = Carbon::parse($request->get('end_date', Carbon::today()->format('Y-m-d')));
                break;
            default:
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
        }

        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        $detailsQuery = TransactionDetail::whereHas('transaction', function ($q) use ($startStr, $endStr) {
            $q->whereDate('transaction_date', '>=', $startStr)
              ->whereDate('transaction_date', '<=', $endStr);
        });

        // Filter by Category
        if ($request->filled('category_id')) {
            $category = Category::find($request->category_id);
            if ($category) {
                $detailsQuery->where('category_name_snapshot', $category->name);
            }
        }

        // Filter by Menu
        if ($request->filled('menu_id')) {
            $menu = Menu::find($request->menu_id);
            if ($menu) {
                $detailsQuery->where('menu_name_snapshot', $menu->name);
            }
        }

        // Total Summary Values
        $totalSales = (float) $detailsQuery->sum('subtotal');
        $totalProfit = (float) $detailsQuery->sum('profit_amount');
        $estimatedCost = (float) $detailsQuery->sum('estimated_cost');
        $totalQuantity = (int) $detailsQuery->sum('quantity');

        // Count unique transaction IDs in this selection
        $totalTransactions = $detailsQuery->distinct('transaction_id')->count('transaction_id');

        // Average sales per day
        $daysCount = $startDate->diffInDays($endDate) + 1;
        $averageSalesPerDay = $daysCount > 0 ? ($totalSales / $daysCount) : $totalSales;

        // Best seller and least seller menu inside filtered parameters
        $bestSeller = (clone $detailsQuery)
            ->select('menu_name_snapshot', DB::raw('SUM(quantity) as qty'))
            ->groupBy('menu_name_snapshot')
            ->orderBy('qty', 'desc')
            ->first();

        $leastSeller = (clone $detailsQuery)
            ->select('menu_name_snapshot', DB::raw('SUM(quantity) as qty'))
            ->groupBy('menu_name_snapshot')
            ->orderBy('qty', 'asc')
            ->first();

        // Rincian items list grouped by menu and category name
        $items = $detailsQuery
            ->select(
                'menu_name_snapshot as menu_name',
                'category_name_snapshot as category_name',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(subtotal) as total_sales'),
                DB::raw('SUM(profit_amount) as total_profit'),
                DB::raw('SUM(estimated_cost) as total_estimated_cost')
            )
            ->groupBy('menu_name_snapshot', 'category_name_snapshot')
            ->orderBy('total_quantity', 'desc')
            ->get();

        // Date range text description (Indonesian)
        if ($startDate->equalTo($endDate)) {
            $dateRangeLabel = $startDate->translatedFormat('d F Y');
        } else {
            $dateRangeLabel = $startDate->translatedFormat('d F Y') . ' - ' . $endDate->translatedFormat('d F Y');
        }

        return [
            'start_date' => $startStr,
            'end_date' => $endStr,
            'date_range_label' => $dateRangeLabel,
            'total_sales' => $totalSales,
            'total_profit' => $totalProfit,
            'estimated_cost' => $estimatedCost,
            'total_quantity' => $totalQuantity,
            'total_transactions' => $totalTransactions,
            'average_sales_per_day' => $averageSalesPerDay,
            'best_seller' => $bestSeller ? $bestSeller->menu_name_snapshot . ' (' . $bestSeller->qty . ' pcs)' : '-',
            'least_seller' => $leastSeller ? $leastSeller->menu_name_snapshot . ' (' . $leastSeller->qty . ' pcs)' : '-',
            'items' => $items,
            'admin_name' => Auth::user()->name,
        ];
    }
}
