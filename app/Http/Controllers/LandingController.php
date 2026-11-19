<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Auth;

class LandingController extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->isSuperAdmin() && !session()->has('impersonating_tenant_id')) {
                return redirect()->route('superadmin.dashboard');
            }
            return $user->isKasir()
                ? redirect()->route('pos.index')
                : redirect()->route('dashboard');
        }

        $plans = Plan::purchasable()->get();
        $trialDays = (int) PlatformSetting::getValue('trial_days', config('platform.trial_days', 14));
        $supportWhatsapp = PlatformSetting::getValue('support_whatsapp', config('platform.support_whatsapp', ''));
        $platformTagline = PlatformSetting::platformTagline();

        return view('landing.index', compact(
            'plans',
            'trialDays',
            'supportWhatsapp',
            'platformTagline'
        ));
    }
}
