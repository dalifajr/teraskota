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
        return view('settings.profit', compact('globalProfitPercentage'));
    }

    /**
     * Update global profit.
     */
    public function updateProfit(Request $request)
    {
        $request->validate([
            'global_profit_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'global_profit_percentage'],
            ['value' => $request->global_profit_percentage]
        );

        return redirect()->route('settings.profit.edit')
            ->with('success', 'Persentase keuntungan global berhasil diperbarui!');
    }
}
