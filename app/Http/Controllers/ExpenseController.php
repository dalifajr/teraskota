<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\PlatformSetting;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    /**
     * Display a listing of operational expenses.
     */
    public function index(Request $request)
    {
        $query = $this->buildFilteredQuery($request);

        $totalExpenses = (float) (clone $query)->sum('amount');
        $cashExpenses = (float) (clone $query)->where('payment_method', 'tunai')->sum('amount');
        $nonCashExpenses = (float) (clone $query)->where('payment_method', 'non-tunai')->sum('amount');
        $expenseCount = (clone $query)->count();

        $expenses = $query->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $categories = Expense::CATEGORIES;

        return view('expenses.index', compact(
            'expenses',
            'categories',
            'totalExpenses',
            'cashExpenses',
            'nonCashExpenses',
            'expenseCount'
        ));
    }

    public function export(Request $request)
    {
        $rows = $this->buildFilteredQuery($request)
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $storeName = strtoupper(TenantContext::current()?->name ?? PlatformSetting::platformName());
        $filename = 'pengeluaran-kas-' . Carbon::now()->format('Ymd-His') . '.csv';

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

            fputcsv($file, ["LAPORAN PENGELUARAN KAS {$storeName}"]);
            fputcsv($file, ['Tanggal Export', Carbon::now()->translatedFormat('d F Y H:i')]);
            fputcsv($file, ['Total Pengeluaran (Rp)', (float) $rows->sum('amount')]);
            fputcsv($file, []);
            fputcsv($file, ['No. Bukti', 'Tanggal', 'Kategori', 'Deskripsi', 'Sumber Dana', 'Nominal (Rp)', 'Dicatat Oleh', 'Catatan']);

            foreach ($rows as $exp) {
                fputcsv($file, [
                    $exp->expense_number,
                    $exp->expense_date?->format('Y-m-d') ?? '-',
                    $exp->category,
                    $exp->description,
                    strtoupper($exp->payment_method),
                    (float) $exp->amount,
                    $exp->creator?->name ?? 'Admin',
                    $exp->notes ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function buildFilteredQuery(Request $request)
    {
        $query = Expense::with('creator');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('payment_method') && in_array($request->payment_method, ['tunai', 'non-tunai'], true)) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('expense_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('expense_date', '<=', $request->end_date);
        }

        return $query;
    }

    /**
     * Store a newly created expense in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:tunai,non-tunai'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated) {
            $expenseNumber = Expense::generateExpenseNumber($validated['expense_date']);

            Expense::create([
                'expense_number' => $expenseNumber,
                'expense_date' => $validated['expense_date'],
                'category' => $validated['category'],
                'description' => $validated['description'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        return redirect()->route('expenses.index')
            ->with('success', 'Data pengeluaran operasional berhasil dicatat!');
    }

    /**
     * Update the specified expense in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:tunai,non-tunai'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($expense, $validated) {
            if (Carbon::parse($expense->expense_date)->format('Y-m-d') !== Carbon::parse($validated['expense_date'])->format('Y-m-d')) {
                $expense->expense_number = Expense::generateExpenseNumber($validated['expense_date']);
            }

            $expense->expense_date = $validated['expense_date'];
            $expense->category = $validated['category'];
            $expense->description = $validated['description'];
            $expense->amount = $validated['amount'];
            $expense->payment_method = $validated['payment_method'];
            $expense->notes = $validated['notes'] ?? null;
            $expense->save();
        });

        return redirect()->route('expenses.index')
            ->with('success', 'Data pengeluaran operasional berhasil diperbarui!');
    }

    /**
     * Remove the specified expense from storage.
     */
    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Data pengeluaran berhasil dihapus.');
    }
}

