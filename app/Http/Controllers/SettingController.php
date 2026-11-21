<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Show form for editing global profit.
     */
    public function editProfit()
    {
        $globalProfitPercentage = (float) Setting::getValue('global_profit_percentage', 30);
        $monthlySalesTarget = (float) Setting::getValue('monthly_sales_target', 0);
        return view('settings.profit', compact('globalProfitPercentage', 'monthlySalesTarget'));
    }

    /**
     * Update global profit and optional monthly sales target.
     */
    public function updateProfit(Request $request)
    {
        $request->validate([
            'global_profit_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'monthly_sales_target' => ['nullable', 'numeric', 'min:0'],
        ]);

        Setting::setValue('global_profit_percentage', $request->global_profit_percentage);
        Setting::setValue('monthly_sales_target', (string) max(0, (float) ($request->monthly_sales_target ?? 0)));

        return redirect()->route('settings.profit.edit')
            ->with('success', 'Pengaturan margin keuntungan & target omzet bulanan berhasil diperbarui!');
    }
}
