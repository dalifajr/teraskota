<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Http\Requests\StoreMenuRequest;
use App\Http\Requests\UpdateMenuRequest;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Menu::with('category');

        // Search by Name or Code
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Filter by Category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $menus = $query->orderBy('code', 'asc')->paginate(15)->withQueryString();
        $categories = Category::all();

        return view('menus.index', compact('menus', 'categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::all();
        return view('menus.create', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMenuRequest $request)
    {
        $validated = $request->validated();
        
        // Handle checkbox fields
        $validated['use_global_profit'] = $request->has('use_global_profit');
        $validated['status'] = $request->has('status');

        Menu::create($validated);

        return redirect()->route('menus.index')
            ->with('success', 'Menu berhasil ditambahkan!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Menu $menu)
    {
        $categories = Category::all();
        return view('menus.edit', compact('menu', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMenuRequest $request, Menu $menu)
    {
        $validated = $request->validated();

        // Handle checkbox fields
        $validated['use_global_profit'] = $request->has('use_global_profit');
        $validated['status'] = $request->has('status');

        $menu->update($validated);

        return redirect()->route('menus.index')
            ->with('success', 'Menu berhasil diperbarui!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Menu $menu)
    {
        try {
            // Check if the menu has been used in transactions
            $hasTransactions = $menu->transactionDetails()->exists();

            if ($hasTransactions) {
                // If it has transactions, soft delete is perfect (softDeletes trait is used)
                $menu->delete();
                return redirect()->route('menus.index')
                    ->with('success', 'Menu berhasil dinonaktifkan / dihapus (soft delete) karena sudah memiliki riwayat transaksi.');
            } else {
                // If it has no transactions, we can force-delete or just soft-delete. Let's force-delete if not used to save space, or just delete.
                $menu->forceDelete();
                return redirect()->route('menus.index')
                    ->with('success', 'Menu berhasil dihapus secara permanen.');
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus menu: ' . $e->getMessage());
        }
    }
}
