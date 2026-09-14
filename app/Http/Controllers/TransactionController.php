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
        $query = Transaction::with(['admin']);

        // Search by Transaction Number
        if ($request->filled('search')) {
            $query->where('transaction_number', 'like', '%' . $request->search . '%');
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

        $transactions = $query->paginate(15)->withQueryString();

        return view('transactions.index', compact('transactions'));
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
        $request->validate([
            'transaction_date' => ['required', 'date'],
            'transaction_time' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'exists:menus,id'],
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
        $request->validate([
            'transaction_date' => ['required', 'date'],
            'transaction_time' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'exists:menus,id'],
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
