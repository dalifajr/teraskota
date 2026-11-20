<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\PlatformSetting;
use App\Models\Transaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\TransactionService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Transaction::expireOverdueQris();

        $query = $this->buildFilteredQuery($request);

        $summaryStats = [
            'count' => (clone $query)->count(),
            'total_sales' => (float) (clone $query)->where('status', 'paid')->sum('total_sales'),
            'total_profit' => (float) (clone $query)->where('status', 'paid')->sum('total_profit'),
        ];

        $transactions = $query->paginate(15)->withQueryString();

        $tenantId = TenantContext::id();
        $cashiers = User::when($tenantId !== null, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('role', '!=', 'super_admin')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('transactions.index', compact('transactions', 'summaryStats', 'cashiers'));
    }

    public function export(Request $request)
    {
        Transaction::expireOverdueQris();

        $rows = $this->buildFilteredQuery($request)->get();
        $storeName = strtoupper(TenantContext::current()?->name ?? PlatformSetting::platformName());
        $filename = 'riwayat-transaksi-' . Carbon::now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($rows, $storeName) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ["LOG TRANSAKSI {$storeName}"]);
            fputcsv($file, ['Tanggal Export', Carbon::now()->translatedFormat('d F Y H:i')]);
            fputcsv($file, []);
            fputcsv($file, ['No. Transaksi', 'Tanggal', 'Waktu', 'Pelanggan', 'Metode', 'Status', 'Qty', 'Omzet (Rp)', 'Keuntungan (Rp)', 'Kasir/Admin', 'Catatan']);

            foreach ($rows as $trx) {
                fputcsv($file, [
                    $trx->transaction_number,
                    $trx->transaction_date?->format('Y-m-d') ?? '-',
                    $trx->transaction_time ? substr((string) $trx->transaction_time, 0, 5) : '-',
                    $trx->customer_name ?: 'Pelanggan',
                    strtoupper($trx->payment_method ?? 'tunai'),
                    strtoupper($trx->status ?? 'paid'),
                    (int) $trx->total_quantity,
                    (float) $trx->total_sales,
                    (float) $trx->total_profit,
                    $trx->admin?->name ?? 'System',
                    $trx->notes ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function buildFilteredQuery(Request $request)
    {
        $query = Transaction::with(['admin'])->withCount('details');

        // Search by Transaction Number or Customer Name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', '%' . $search . '%')
                  ->orWhere('customer_name', 'like', '%' . $search . '%');
            });
        }

        // Filter by Status
        if ($request->filled('status') && in_array($request->status, ['paid', 'pending', 'expired', 'cancelled'], true)) {
            $query->where('status', $request->status);
        }

        // Filter by Payment Method
        if ($request->filled('payment_method') && in_array($request->payment_method, ['tunai', 'qris'], true)) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by Cashier / Admin
        if ($request->filled('cashier_id')) {
            $query->where('created_by', (int) $request->cashier_id);
        }

        // Filter by Date
        if ($request->filled('date')) {
            $query->whereDate('transaction_date', $request->date);
        }

        // Filter by Month & Year
        if ($request->filled('month')) {
            $query->whereMonth('transaction_date', $request->month);
        }
        if ($request->filled('year')) {
            $query->whereYear('transaction_date', $request->year);
        }

        // Sorting
        $sortOrder = $request->get('sort', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('transaction_date', $sortOrder)
              ->orderBy('transaction_time', $sortOrder);

        return $query;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::where('status', true)->with(['menus' => function($q) {
            $q->where('status', true);
        }])->get();

        $globalProfitPercentage = (float) Setting::getValue('global_profit_percentage', 30);
        $todayDate = Carbon::today()->format('Y-m-d');
        $nowTime = Carbon::now()->format('H:i');

        return view('transactions.create', compact('categories', 'globalProfitPercentage', 'todayDate', 'nowTime'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $request->validate([
            'transaction_date' => ['required', 'date'],
            'transaction_time' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => [
                'required',
                Rule::exists('menus', 'id')->where(fn($q) => $tenantId !== null ? $q->where('tenant_id', $tenantId) : $q),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $transaction = $this->transactionService->createTransaction($request->all(), Auth::id());
            return redirect()->route('transactions.show', $transaction->id)
                ->with('success', 'Transaksi berhasil disimpan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menyimpan transaksi: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction)
    {
        $transaction->load(['details', 'admin']);
        return view('transactions.show', compact('transaction'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transaction $transaction)
    {
        $transaction->load('details');
        
        $categories = Category::where('status', true)->with(['menus' => function($q) {
            $q->where('status', true);
        }])->get();

        $globalProfitPercentage = (float) Setting::getValue('global_profit_percentage', 30);

        return view('transactions.edit', compact('transaction', 'categories', 'globalProfitPercentage'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaction $transaction)
    {
        $tenantId = TenantContext::id();

        $request->validate([
            'transaction_date' => ['required', 'date'],
            'transaction_time' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => [
                'required',
                Rule::exists('menus', 'id')->where(fn($q) => $tenantId !== null ? $q->where('tenant_id', $tenantId) : $q),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->transactionService->updateTransaction($transaction, $request->all());
            return redirect()->route('transactions.show', $transaction->id)
                ->with('success', 'Transaksi berhasil diperbarui!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui transaksi: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction)
    {
        try {
            $transaction->delete();
            return redirect()->route('transactions.index')
                ->with('success', 'Transaksi berhasil dihapus (soft delete).');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}
