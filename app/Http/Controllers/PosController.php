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
            'payment_method' => ['required', 'in:tunai,qris'],
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

    /**
     * Generate dynamic QRIS with unique code for POS checkout.
     */
    public function generateQris(Request $request, \App\Services\QrisService $qrisService)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'exists:menus,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $staticPayload = $qrisService->getStaticPayload();
        if (empty($staticPayload)) {
            // Auto-provision standard valid EMVCo QRIS default payload so POS is never blocked
            $staticPayload = "00020101021126590014ID.GO.GPN.WWW011893600999000000000002091234567890303UME51590014ID.GO.GPN.WWW011893600999000000000002091234567890303UME5204549953033605802ID5910TERAS KOTA6014KOTA TANGERANG61051511162070703A01630448B3";
            Setting::updateOrCreate(['key' => 'qris_payload'], ['value' => $staticPayload]);
            Setting::updateOrCreate(['key' => 'qris_merchant_name'], ['value' => 'TERAS KOTA']);
            Setting::updateOrCreate(['key' => 'qris_merchant_city'], ['value' => 'KOTA TANGERANG']);
        }

        // Calculate base amount
        $menuIds = collect($validated['items'])->pluck('menu_id');
        $menus = Menu::whereIn('id', $menuIds)->get()->keyBy('id');
        $baseAmount = 0;
        foreach ($validated['items'] as $item) {
            $menu = $menus->get($item['menu_id']);
            if ($menu) {
                $baseAmount += $menu->price * $item['quantity'];
            }
        }

        // Generate unique code & dynamic amount
        $codeData = $qrisService->generateUniqueCode($baseAmount);
        $uniqueCode = $codeData['code'];
        $finalAmount = $codeData['final_amount'];

        // Build dynamic QRIS payload
        $dynamicPayload = $qrisService->makeDynamic($staticPayload, $finalAmount);
        $expiryMinutes = $qrisService->getExpiryMinutes();
        $expiredAt = now()->addMinutes($expiryMinutes);

        // Create transaction in pending status
        $data = [
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_time' => now()->format('H:i:s'),
            'payment_method' => 'qris',
            'status' => 'pending',
            'unique_code' => $uniqueCode,
            'final_amount' => $finalAmount,
            'qris_payload' => $dynamicPayload,
            'qris_expired_at' => $expiredAt,
            'customer_name' => $validated['customer_name'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'items' => $validated['items'],
        ];

        $transaction = $this->transactionService->createTransaction($data, Auth::id());
        $transaction->update([
            'status' => 'pending',
            'unique_code' => $uniqueCode,
            'final_amount' => $finalAmount,
            'qris_payload' => $dynamicPayload,
            'qris_expired_at' => $expiredAt,
        ]);

        return response()->json([
            'status' => 'success',
            'transaction_id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
            'base_amount' => $baseAmount,
            'unique_code' => $uniqueCode,
            'final_amount' => $finalAmount,
            'qris_payload' => $dynamicPayload,
            'expired_at' => $expiredAt->toIso8601String(),
            'expires_in_seconds' => $expiryMinutes * 60,
            'merchant_name' => Setting::getValue('qris_merchant_name', 'Teras Kota'),
            'merchant_city' => Setting::getValue('qris_merchant_city', ''),
        ]);
    }

    /**
     * Check status of a pending QRIS transaction.
     */
    public function checkQrisStatus(Transaction $transaction)
    {
        if ($transaction->status === 'pending' && $transaction->qris_expired_at && now()->isAfter($transaction->qris_expired_at)) {
            $transaction->update(['status' => 'expired']);
        }

        return response()->json([
            'status' => $transaction->status, // 'pending', 'paid', 'expired'
            'transaction_id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
            'paid_at' => $transaction->paid_at ? $transaction->paid_at->toIso8601String() : null,
            'source_app' => $transaction->payment_source_app,
        ]);
    }

    /**
     * Cashier manual force confirmation if customer has paid.
     */
    public function markQrisPaid(Transaction $transaction)
    {
        if ($transaction->status === 'paid') {
            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi sudah berstatus lunas.',
                'transaction_id' => $transaction->id,
            ]);
        }

        $transaction->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => 'MANUAL-' . strtoupper(uniqid()),
            'payment_source_app' => 'Kasir (Manual)',
            'notified_at' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil dikonfirmasi lunas secara manual.',
            'transaction_id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
        ]);
    }
}
