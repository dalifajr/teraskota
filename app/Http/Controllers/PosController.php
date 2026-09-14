<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\Setting;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PosController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Display the dedicated POS Cashier interface.
     */
    public function index()
    {
        $categories = Category::where('status', true)
            ->with(['menus' => function ($q) {
                $q->where('status', true)->orderBy('name', 'asc');
            }])
            ->orderBy('name', 'asc')
            ->get();

        $today = Carbon::today()->format('Y-m-d');
        $nowTime = Carbon::now()->format('H:i');

        // Cashier shift / today statistics
        $user = Auth::user();
        $todayQuery = Transaction::whereDate('transaction_date', $today);
        
        // If cashier, limit today's stats to this cashier; if admin, show overall today's stats
        if ($user->isKasir()) {
            $todayQuery->where('created_by', $user->id);
        }

        $todayTrxCount = (clone $todayQuery)->count();
        $todayTotalSales = (clone $todayQuery)->sum('total_sales');

        return view('pos.index', compact(
            'categories',
            'today',
            'nowTime',
            'user',
            'todayTrxCount',
            'todayTotalSales'
        ));
    }

    /**
     * Handle POS checkout and transaction creation.
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'exists:menus,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:tunai,qris,transfer'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $todayDate = Carbon::today()->format('Y-m-d');
        $nowTime = Carbon::now()->format('H:i:s');

        $transactionData = [
            'transaction_date' => $todayDate,
            'transaction_time' => $nowTime,
            'items' => $validated['items'],
            'payment_method' => $validated['payment_method'],
            'cash_tendered' => $validated['cash_tendered'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        try {
            $transaction = $this->transactionService->createTransaction($transactionData, Auth::id());
            $transaction->load(['details', 'cashier']);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transaksi berhasil diproses!',
                    'transaction' => $transaction,
                    'receipt_html' => view('pos.receipt', ['transaction' => $transaction, 'isModal' => true])->render(),
                ]);
            }

            return redirect()->route('pos.receipt', $transaction->id)
                ->with('success', 'Transaksi berhasil disimpan!');
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memproses transaksi: ' . $e->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Gagal memproses transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Display printable thermal receipt.
     */
    public function receipt(Transaction $transaction)
    {
        $transaction->load(['details', 'cashier']);
        return view('pos.receipt', compact('transaction'));
    }

    /**
     * Get summary of today's transactions for the active cashier.
     */
    public function summaryToday()
    {
        $today = Carbon::today()->format('Y-m-d');
        $user = Auth::user();

        $query = Transaction::with(['details'])->whereDate('transaction_date', $today);
        if ($user->isKasir()) {
            $query->where('created_by', $user->id);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $totalTransactions = $transactions->count();
        $totalSales = $transactions->sum('total_sales');
        $totalQty = $transactions->sum('total_quantity');
        $totalTunai = $transactions->where('payment_method', 'tunai')->sum('total_sales');
        $totalNonTunai = $transactions->whereIn('payment_method', ['qris', 'transfer'])->sum('total_sales');

        return response()->json([
            'cashier_name' => $user->name,
            'date' => Carbon::parse($today)->translatedFormat('d F Y'),
            'total_transactions' => $totalTransactions,
            'total_sales' => $totalSales,
            'total_qty' => $totalQty,
            'total_tunai' => $totalTunai,
            'total_non_tunai' => $totalNonTunai,
            'transactions' => $transactions,
        ]);
    }
}
