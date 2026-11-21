<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\PlatformSetting;
use App\Http\Requests\StoreMenuRequest;
use App\Http\Requests\UpdateMenuRequest;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }
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

        // Filter by Status (Aktif / Habis)
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('status', true);
            } elseif ($request->status === 'inactive') {
                $query->where('status', false);
            }
        }

        $menus = $query->orderBy('code', 'asc')->paginate(15)->withQueryString();
        $categories = Category::all();
        $tenant = TenantContext::current();
        $menuLimitInfo = $tenant ? $this->subscriptionService->checkMenuLimit($tenant) : null;

        return view('menus.index', compact('menus', 'categories', 'menuLimitInfo'));
    }

    /**
     * Quick 1-click toggle menu active/out-of-stock status.
     */
    public function toggleStatus(Menu $menu)
    {
        $menu->update([
            'status' => !$menu->status,
        ]);

        $stateLabel = $menu->status ? 'diaktifkan (Tersedia di POS)' : 'dinonaktifkan (Habis / Disembunyikan dari POS)';

        return back()->with('success', "Status menu {$menu->name} berhasil {$stateLabel}.");
    }

    /**
     * Duplicate an existing menu item in 1 click (respecting plan quota).
     */
    public function duplicate(Menu $menu)
    {
        $tenant = TenantContext::current();
        if ($tenant) {
            $limitCheck = $this->subscriptionService->checkMenuLimit($tenant);
            if (!$limitCheck['allowed']) {
                return redirect()->route('menus.index')->with('error', $limitCheck['message']);
            }
        }

        $baseCode = preg_replace('/-COPY\d*$/i', '', (string) $menu->code);
        $candidate = substr($baseCode, 0, 42) . '-COPY';
        $idx = 2;
        while (Menu::withTrashed()->where('code', $candidate)->exists()) {
            $candidate = substr($baseCode, 0, 40) . '-COPY' . $idx++;
        }

        $copy = Menu::create([
            'category_id' => $menu->category_id,
            'code' => $candidate,
            'name' => $menu->name . ' (Salinan)',
            'price' => $menu->price,
            'use_global_profit' => $menu->use_global_profit,
            'profit_percentage' => $menu->profit_percentage,
            'status' => $menu->status,
        ]);

        return redirect()->route('menus.edit', $copy->id)
            ->with('success', "Menu {$menu->name} berhasil diduplikasi sebagai {$copy->code}. Silakan sesuaikan nama/harga.");
    }

    /**
     * Export catalog menus to CSV.
     */
    public function export()
    {
        $menus = Menu::with('category')->orderBy('code')->get();
        $storeName = strtoupper(TenantContext::current()?->name ?? PlatformSetting::platformName());
        $filename = 'katalog-menu-' . Carbon::now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($menus, $storeName) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['Kode', 'Nama Menu', 'Kategori', 'Harga Jual', 'Margin Khusus (%)', 'Status']);
            foreach ($menus as $m) {
                fputcsv($file, [
                    $m->code,
                    $m->name,
                    $m->category?->name ?? 'Umum',
                    (float) $m->price,
                    $m->use_global_profit ? '' : (float) $m->profit_percentage,
                    $m->status ? 'aktif' : 'nonaktif',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Download clean CSV import template.
     */
    public function downloadTemplate()
    {
        $filename = 'template-import-menu.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Kode', 'Nama Menu', 'Kategori', 'Harga Jual', 'Margin Khusus (%)', 'Status']);
            fputcsv($file, ['MNM-101', 'Es Kopi Susu Aren', 'Minuman', 18000, '', 'aktif']);
            fputcsv($file, ['MKN-101', 'Roti Bakar Cokelat', 'Makanan', 15000, 35, 'aktif']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk import menus from CSV (auto-creates categories & respects plan menu quota).
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $tenant = TenantContext::current();
        $limitInfo = $tenant ? $this->subscriptionService->checkMenuLimit($tenant) : ['allowed' => true, 'current' => 0, 'limit' => null];
        $remainingQuota = $limitInfo['limit'] !== null ? max(0, $limitInfo['limit'] - $limitInfo['current']) : PHP_INT_MAX;

        if ($remainingQuota <= 0) {
            return redirect()->route('menus.index')->with('error', $limitInfo['message'] ?? 'Kuota menu paket Anda sudah penuh.');
        }

        $path = $request->file('csv_file')->getRealPath();
        $rawContent = (string) file_get_contents($path);
        $rawContent = preg_replace('/^\xEF\xBB\xBF/', '', $rawContent);
        $lines = preg_split('/\r\n|\r|\n/', trim($rawContent));

        if (!$lines || count($lines) < 2) {
            return back()->with('error', 'File CSV kosong atau tidak memiliki baris data menu.');
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $imported = 0;
        $skipped = 0;
        $quotaHit = false;

        DB::transaction(function () use ($lines, $delimiter, $remainingQuota, &$imported, &$skipped, &$quotaHit) {
            foreach ($lines as $index => $line) {
                if ($index === 0 || trim($line) === '') {
                    continue;
                }

                if ($imported >= $remainingQuota) {
                    $quotaHit = true;
                    break;
                }

                $cols = str_getcsv($line, $delimiter);
                $code = strtoupper(trim((string) ($cols[0] ?? '')));
                $name = trim((string) ($cols[1] ?? ''));
                $catName = trim((string) ($cols[2] ?? 'Umum')) ?: 'Umum';
                $priceRaw = preg_replace('/[^0-9.]/', '', (string) ($cols[3] ?? '0'));
                $price = (float) $priceRaw;
                $marginRaw = trim((string) ($cols[4] ?? ''));
                $statusRaw = strtolower(trim((string) ($cols[5] ?? 'aktif')));

                if ($code === '' || $name === '' || $price <= 0) {
                    $skipped++;
                    continue;
                }

                if (Menu::withTrashed()->where('code', $code)->exists()) {
                    $skipped++;
                    continue;
                }

                $category = Category::where('name', $catName)->first();
                if (!$category) {
                    $category = Category::create([
                        'name' => $catName,
                        'slug' => Str::slug($catName) ?: 'kategori-' . Str::lower(Str::random(4)),
                        'status' => true,
                    ]);
                }

                $useGlobal = ($marginRaw === '');
                $profitPct = $useGlobal ? null : min(100, max(0, (float) $marginRaw));

                Menu::create([
                    'category_id' => $category->id,
                    'code' => substr($code, 0, 50),
                    'name' => substr($name, 0, 150),
                    'price' => $price,
                    'use_global_profit' => $useGlobal,
                    'profit_percentage' => $profitPct,
                    'status' => !in_array($statusRaw, ['0', 'nonaktif', 'inactive', 'habis', 'false'], true),
                ]);

                $imported++;
            }
        });

        $msg = "Import selesai: {$imported} menu berhasil ditambahkan" . ($skipped > 0 ? ", {$skipped} baris dilewati (duplikat/tidak valid)." : '.');
        if ($quotaHit) {
            $msg .= ' Sebagian baris dihentikan karena kuota menu paket langganan tercapai.';
        }

        return redirect()->route('menus.index')->with($imported > 0 ? 'success' : 'error', $msg);
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
        $tenant = TenantContext::current();
        if ($tenant) {
            $limitCheck = $this->subscriptionService->checkMenuLimit($tenant);
            if (!$limitCheck['allowed']) {
                return redirect()->route('menus.index')->with('error', $limitCheck['message']);
            }
        }

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
